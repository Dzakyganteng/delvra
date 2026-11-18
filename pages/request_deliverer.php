
<?php

session_start();
require_once "../config/database.php";

// Cek login
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// Hanya Deliverer
if ($_SESSION['role'] != 'deliverer') {
    echo "Akses ditolak!";
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = "";

// Token keamanan form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ========================================
// PROSES MEMBUAT PENGIRIMAN
// ========================================

if (isset($_POST['buat_pengiriman'])) {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Permintaan tidak valid. Silakan coba lagi.";

    } else {

        $daftar_request = $_POST['id_request'] ?? [];

        // Bersihkan ID request
        if (!is_array($daftar_request)) {
            $daftar_request = [];
        }

        $daftar_request = array_values(array_unique(
            array_filter(
                array_map('intval', $daftar_request),
                function ($id) {
                    return $id > 0;
                }
            )
        ));

        if (empty($daftar_request)) {

            $error = "Pilih minimal satu request.";

        } else {

            mysqli_begin_transaction($conn);

            try {

                $id_list = implode(',', $daftar_request);

                // Ambil dan kunci request yang dipilih
                $query_cek = "
                    SELECT
                        id_request,
                        id_customer,
                        tanggal_request,
                        status,
                        id_pengiriman
                    FROM requests
                    WHERE id_request IN ($id_list)
                    FOR UPDATE
                ";

                $cek = mysqli_query($conn, $query_cek);

                if (mysqli_num_rows($cek) != count($daftar_request)) {
                    throw new Exception("Ada request yang tidak ditemukan.");
                }

                $id_customer = null;
                $tanggal_request = null;

                while ($data = mysqli_fetch_assoc($cek)) {

                    // Request harus sudah diproses Admin
                    if ($data['status'] != 'Diproses') {
                        throw new Exception(
                            "Hanya request berstatus Diproses yang dapat dikirim."
                        );
                    }

                    // Request belum boleh memiliki ID pengiriman
                    if ($data['id_pengiriman'] !== null) {
                        throw new Exception(
                            "Ada request yang sudah masuk ke pengiriman lain."
                        );
                    }

                    // Semua request harus dari Customer dan tanggal yang sama
                    if ($id_customer === null) {

                        $id_customer = $data['id_customer'];
                        $tanggal_request = $data['tanggal_request'];

                    } else {

                        if (
                            $id_customer != $data['id_customer'] ||
                            $tanggal_request != $data['tanggal_request']
                        ) {
                            throw new Exception(
                                "Request harus berasal dari Customer dan tanggal yang sama."
                            );
                        }
                    }
                }

                mysqli_free_result($cek);

                // Buat satu data pengiriman
                $id_deliverer = (int) $_SESSION['id_user'];
                $tanggal_kirim = date('Y-m-d');

                $stmt_pengiriman = mysqli_prepare(
                    $conn,
                    "INSERT INTO pengiriman
                    (id_deliverer, tanggal_kirim, status_pengiriman)
                    VALUES (?, ?, 'Dikirim')"
                );

                mysqli_stmt_bind_param(
                    $stmt_pengiriman,
                    "is",
                    $id_deliverer,
                    $tanggal_kirim
                );

                if (!mysqli_stmt_execute($stmt_pengiriman)) {
                    throw new Exception("Gagal membuat data pengiriman.");
                }

                // Ambil ID pengiriman yang baru dibuat
                $id_pengiriman = mysqli_insert_id($conn);

                mysqli_stmt_close($stmt_pengiriman);


                // Simpan ID pengiriman langsung ke tabel requests
                // Sekaligus ubah status request menjadi Dikirim
                $stmt_update = mysqli_prepare(
                    $conn,
                    "UPDATE requests
                     SET
                        id_pengiriman = ?,
                        status = 'Dikirim'
                     WHERE id_request = ?
                     AND status = 'Diproses'
                     AND id_pengiriman IS NULL"
                );

                foreach ($daftar_request as $id_request) {

                    mysqli_stmt_bind_param(
                        $stmt_update,
                        "ii",
                        $id_pengiriman,
                        $id_request
                    );

                    if (
                        !mysqli_stmt_execute($stmt_update) ||
                        mysqli_stmt_affected_rows($stmt_update) != 1
                    ) {
                        throw new Exception(
                            "Gagal memperbarui request ID " . $id_request
                        );
                    }
                }

                mysqli_stmt_close($stmt_update);

                // Simpan seluruh perubahan
                mysqli_commit($conn);

                echo "<script>
                    alert('Pengiriman berhasil dibuat!');
                    window.location='request_deliverer.php';
                </script>";

                exit;

            } catch (Throwable $e) {

                mysqli_rollback($conn);

                $error = $e->getMessage();
            }
        }
    }
}


// ========================================
// AMBIL SEMUA REQUEST
// ========================================

$query = "
    SELECT
        requests.id_request,
        requests.id_customer,
        users.nama AS nama_customer,
        requests.nama_produk,
        requests.jumlah,
        requests.tanggal_request,
        requests.status,
        requests.id_pengiriman AS id_delivery
    FROM requests

    JOIN users
        ON requests.id_customer = users.id_user

    ORDER BY
        requests.tanggal_request DESC,
        users.nama ASC,
        requests.id_request DESC
";

$result = mysqli_query($conn, $query);


// ========================================
// KELOMPOKKAN BERDASARKAN CUSTOMER DAN TANGGAL
// ========================================

$groups = [];

while ($data = mysqli_fetch_assoc($result)) {

    $key = $data['tanggal_request'] . '_' . $data['id_customer'];

    if (!isset($groups[$key])) {

        $groups[$key] = [
            'tanggal' => $data['tanggal_request'],
            'customer' => $data['nama_customer'],
            'customer_id' => $data['id_customer'],
            'requests' => []
        ];
    }

    $groups[$key]['requests'][] = $data;
}

$jumlah_semua_request = 0;
$jumlah_request_siap = 0;
foreach ($groups as $group) {
    foreach ($group['requests'] as $request) {
        $jumlah_semua_request++;
        if ($request['status'] === 'Diproses' && $request['id_delivery'] === null) {
            $jumlah_request_siap++;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Request Deliverer - Delvra</title>
    <style>
        :root {
            --request-primary: #1c39b7;
            --request-primary-dark: #102878;
            --request-ink: #18233d;
            --request-muted: #78839a;
            --request-line: #e4e9f2;
            --request-surface: #fff;
            --request-background: #f4f7fc;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--request-ink);
            background: var(--request-background);
            font-family: Inter, "Segoe UI", sans-serif;
        }

        .request-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 14px clamp(20px, 5vw, 72px);
            background: var(--request-surface);
            border-bottom: 1px solid var(--request-line);
        }

        .request-brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            color: var(--request-ink);
            font-weight: 750;
            text-decoration: none;
        }

        .request-brand img {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .request-account {
            display: flex;
            align-items: center;
            gap: 14px;
            color: var(--request-muted);
            font-size: .9rem;
        }

        .request-account strong {
            color: var(--request-ink);
        }

        .request-main {
            width: min(100% - 40px, 1120px);
            margin: 0 auto;
            padding: 46px 0 72px;
        }

        .request-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 22px;
            margin-bottom: 24px;
        }

        .request-eyebrow {
            margin: 0 0 9px;
            color: var(--request-primary);
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        .request-heading h1 {
            margin: 0;
            font-size: clamp(2rem, 5vw, 2.8rem);
            font-weight: 760;
            letter-spacing: -.05em;
        }

        .request-heading-copy {
            max-width: 650px;
            margin: 12px 0 0;
            color: var(--request-muted);
            line-height: 1.65;
        }

        .request-summary {
            display: flex;
            flex: 0 0 auto;
            gap: 10px;
        }

        .request-summary-item {
            min-width: 104px;
            padding: 11px 14px;
            background: var(--request-surface);
            border: 1px solid var(--request-line);
            border-radius: 12px;
        }

        .request-summary-item strong,
        .request-summary-item span {
            display: block;
        }

        .request-summary-item strong {
            color: var(--request-ink);
            font-size: 1.1rem;
        }

        .request-summary-item span {
            margin-top: 2px;
            color: var(--request-muted);
            font-size: .74rem;
        }

        .request-alert {
            display: flex;
            gap: 10px;
            margin: 0 0 18px;
            padding: 13px 15px;
            color: #a32828;
            background: #fff1f1;
            border: 1px solid #f3caca;
            border-radius: 11px;
            font-size: .9rem;
            line-height: 1.45;
        }

        .request-list {
            display: grid;
            gap: 18px;
        }

        .request-group {
            min-width: 0;
            overflow: hidden;
            background: var(--request-surface);
            border: 1px solid var(--request-line);
            border-radius: 17px;
            box-shadow: 0 12px 30px rgba(30, 48, 90, .045);
        }

        .request-group-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 22px;
            border-bottom: 1px solid var(--request-line);
        }

        .request-customer {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .request-customer-icon {
            display: grid;
            width: 42px;
            height: 42px;
            flex: 0 0 auto;
            place-items: center;
            color: var(--request-primary);
            background: #eef2ff;
            border-radius: 13px;
            font-size: 1rem;
            font-weight: 800;
        }

        .request-customer h2 {
            overflow-wrap: anywhere;
            margin: 0;
            font-size: 1rem;
            font-weight: 750;
        }

        .request-customer p {
            margin: 4px 0 0;
            color: var(--request-muted);
            font-size: .82rem;
        }

        .request-group-count {
            flex: 0 0 auto;
            padding: 6px 10px;
            color: var(--request-primary-dark);
            background: #eef2ff;
            border-radius: 999px;
            font-size: .76rem;
            font-weight: 750;
        }

        .request-group-content {
            padding: 18px 22px 20px;
        }

        .request-selection-hint {
            margin: 0 0 12px;
            color: var(--request-muted);
            font-size: .82rem;
            line-height: 1.5;
        }

        .request-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--request-line);
            border-radius: 12px;
        }

        .request-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .86rem;
            text-align: left;
        }

        .request-table th {
            padding: 12px 13px;
            color: var(--request-muted);
            background: #f7f9fc;
            border-bottom: 1px solid var(--request-line);
            font-size: .7rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .request-table td {
            padding: 13px;
            border-bottom: 1px solid var(--request-line);
            vertical-align: middle;
        }

        .request-table tr:last-child td {
            border-bottom: 0;
        }

        .request-select-cell {
            width: 52px;
            text-align: center;
        }

        .request-checkbox {
            width: 17px;
            height: 17px;
            accent-color: var(--request-primary);
            cursor: pointer;
            vertical-align: middle;
        }

        .request-checkbox:disabled {
            cursor: not-allowed;
        }

        .request-id {
            display: block;
            margin-bottom: 3px;
            color: var(--request-muted);
            font-size: .74rem;
            font-variant-numeric: tabular-nums;
        }

        .request-product {
            font-weight: 650;
        }

        .request-quantity {
            font-weight: 700;
            white-space: nowrap;
        }

        .request-status {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            color: #526078;
            background: #edf0f5;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 750;
            white-space: nowrap;
        }

        .request-status.ready {
            color: #2446a8;
            background: #eaf0ff;
        }

        .request-status.sent {
            color: #075d79;
            background: #e2f6fb;
        }

        .request-status.complete {
            color: #176b4b;
            background: #e7f7ee;
        }

        .request-status.cancelled {
            color: #a32828;
            background: #fff0f0;
        }

        .request-delivery-id {
            color: var(--request-muted);
            font-variant-numeric: tabular-nums;
        }

        .request-group-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 16px;
        }

        .request-selection-count {
            margin: 0;
            color: var(--request-muted);
            font-size: .82rem;
        }

        .request-submit {
            display: inline-flex;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            color: #fff;
            background: var(--request-primary);
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            font: inherit;
            font-size: .84rem;
            font-weight: 700;
            transition: background .16s ease, transform .16s ease, opacity .16s ease;
        }

        .request-submit:hover:not(:disabled) {
            background: var(--request-primary-dark);
            transform: translateY(-1px);
        }

        .request-submit:disabled {
            opacity: .48;
            cursor: not-allowed;
        }

        .request-not-ready {
            margin: 14px 0 0;
            padding: 11px 13px;
            color: var(--request-muted);
            background: #f7f9fc;
            border-radius: 10px;
            font-size: .82rem;
        }

        .request-empty {
            padding: 42px 22px;
            color: var(--request-muted);
            background: var(--request-surface);
            border: 1px dashed #cfd8e8;
            border-radius: 16px;
            text-align: center;
        }

        .request-empty strong {
            display: block;
            margin-bottom: 6px;
            color: var(--request-ink);
            font-size: 1rem;
        }

        .request-empty p {
            margin: 0;
            font-size: .88rem;
        }

        .request-footer {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 24px;
        }

        .request-nav-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 9px 14px;
            color: var(--request-ink);
            background: var(--request-surface);
            border: 1px solid var(--request-line);
            border-radius: 10px;
            font-size: .88rem;
            font-weight: 650;
            text-decoration: none;
            transition: border-color .16s ease, background .16s ease, transform .16s ease;
        }

        .request-nav-link:hover {
            border-color: #bdc9e8;
            background: #f7f9ff;
            transform: translateY(-1px);
        }

        body[data-theme="dark"] .request-topbar,
        body[data-theme="dark"] .request-summary-item,
        body[data-theme="dark"] .request-group,
        body[data-theme="dark"] .request-empty {
            color: var(--theme-text) !important;
            background: var(--theme-surface) !important;
            border-color: var(--theme-border) !important;
        }

        body[data-theme="dark"] .request-brand,
        body[data-theme="dark"] .request-heading h1,
        body[data-theme="dark"] .request-customer h2,
        body[data-theme="dark"] .request-summary-item strong,
        body[data-theme="dark"] .request-product,
        body[data-theme="dark"] .request-empty strong {
            color: var(--theme-text) !important;
        }

        body[data-theme="dark"] .request-account,
        body[data-theme="dark"] .request-heading-copy,
        body[data-theme="dark"] .request-customer p,
        body[data-theme="dark"] .request-summary-item span,
        body[data-theme="dark"] .request-selection-hint,
        body[data-theme="dark"] .request-selection-count,
        body[data-theme="dark"] .request-id,
        body[data-theme="dark"] .request-delivery-id,
        body[data-theme="dark"] .request-empty {
            color: var(--theme-muted) !important;
        }

        body[data-theme="dark"] .request-selection-hint strong {
            color: var(--theme-text) !important;
        }

        body[data-theme="dark"] .request-customer-icon,
        body[data-theme="dark"] .request-group-count {
            color: #c7d2ff !important;
            background: #293d68 !important;
        }

        body[data-theme="dark"] .request-table-wrap {
            border-color: var(--theme-border) !important;
        }

        body[data-theme="dark"] .request-table th {
            color: var(--theme-muted) !important;
            background: var(--theme-surface-raised) !important;
        }

        body[data-theme="dark"] .request-table th,
        body[data-theme="dark"] .request-table td {
            border-color: var(--theme-border) !important;
        }

        body[data-theme="dark"] .request-table td {
            color: var(--theme-text) !important;
        }

        body[data-theme="dark"] .request-status {
            color: var(--theme-text) !important;
            background: #3b475a !important;
        }

        body[data-theme="dark"] .request-status.ready {
            color: #b9c9ff !important;
            background: #293d68 !important;
        }

        body[data-theme="dark"] .request-status.sent {
            color: #9de7f5 !important;
            background: #16414e !important;
        }

        body[data-theme="dark"] .request-status.complete {
            color: #a8eac2 !important;
            background: #1d4936 !important;
        }

        body[data-theme="dark"] .request-status.cancelled {
            color: #fecaca !important;
            background: #512e37 !important;
        }

        body[data-theme="dark"] .request-not-ready {
            color: var(--theme-muted) !important;
            background: var(--theme-surface-raised) !important;
        }

        body[data-theme="dark"] .request-nav-link {
            color: var(--theme-text) !important;
            background: var(--theme-surface) !important;
            border-color: var(--theme-border) !important;
        }

        body[data-theme="dark"] .request-alert {
            color: #fecdd3 !important;
            background: #422b35 !important;
            border-color: #754253 !important;
        }

        body[data-theme="dark"] .request-submit {
            color: #fff !important;
            background: #1c39b7 !important;
        }

        @media (max-width: 820px) {
            .request-heading {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 560px) {
            .request-topbar {
                align-items: flex-start;
                padding: 12px 16px;
            }

            .request-brand img {
                width: 36px;
                height: 36px;
            }

            .request-account {
                flex-direction: column;
                align-items: flex-end;
                gap: 5px;
                font-size: .8rem;
            }

            .request-main {
                width: min(100% - 28px, 1120px);
                padding-top: 30px;
            }

            .request-summary {
                width: 100%;
            }

            .request-summary-item {
                flex: 1;
            }

            .request-group-header {
                align-items: flex-start;
                padding: 16px;
            }

            .request-group-content {
                padding: 15px 12px 16px;
            }

            .request-group-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .request-submit {
                width: 100%;
            }

            .request-footer > * {
                flex: 1 1 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
</head>

<body>
    <?php include __DIR__ . '/includes/navbar_deliverer.php'; ?>

    <main class="request-main">
        <div class="request-heading">
            <div>
                <p class="request-eyebrow">Deliverer</p>
                <h1>Permintaan pelanggan</h1>
                <p class="request-heading-copy">Tinjau permintaan per pelanggan dan tanggal. Pilih hanya permintaan berstatus <strong>Diproses</strong> yang belum masuk ke pengiriman untuk membuat satu pengiriman.</p>
            </div>
            <div class="request-summary" aria-label="Ringkasan permintaan">
                <div class="request-summary-item">
                    <strong><?php echo $jumlah_semua_request; ?></strong>
                    <span>Total permintaan</span>
                </div>
                <div class="request-summary-item">
                    <strong><?php echo $jumlah_request_siap; ?></strong>
                    <span>Siap dikirim</span>
                </div>
            </div>
        </div>

        <?php if ($error !== "") { ?>
            <div class="request-alert" role="alert">
                <span aria-hidden="true">!</span>
                <span><?php echo e($error); ?></span>
            </div>
        <?php } ?>

        <?php if (count($groups) > 0) { ?>
            <div class="request-list">
                <?php foreach ($groups as $group) {
                    $jumlah_tersedia = 0;
                    foreach ($group['requests'] as $request) {
                        if ($request['status'] === 'Diproses' && $request['id_delivery'] === null) {
                            $jumlah_tersedia++;
                        }
                    }
                ?>
                    <section class="request-group" aria-labelledby="customer-<?php echo e(md5($group['tanggal'] . $group['customer_id'])); ?>">
                        <div class="request-group-header">
                            <div class="request-customer">
                                <span class="request-customer-icon" aria-hidden="true">♙</span>
                                <div>
                                    <h2 id="customer-<?php echo e(md5($group['tanggal'] . $group['customer_id'])); ?>">
                                        <?php echo e($group['customer']); ?>
                                    </h2>
                                    <p>Permintaan tanggal <?php echo e(date('d-m-Y', strtotime($group['tanggal']))); ?></p>
                                </div>
                            </div>
                            <span class="request-group-count"><?php echo count($group['requests']); ?> permintaan</span>
                        </div>

                        <div class="request-group-content">
                            <p class="request-selection-hint">
                                <?php if ($jumlah_tersedia > 0) { ?>
                                    Centang satu atau lebih produk berstatus <strong>Diproses</strong> untuk memasukkannya ke pengiriman.
                                <?php } else { ?>
                                    Tidak ada permintaan yang siap dipilih pada kelompok ini.
                                <?php } ?>
                            </p>

                            <form method="POST" class="request-delivery-form">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo e($_SESSION['csrf_token']); ?>"
                                >

                                <div class="request-table-wrap">
                                    <table class="request-table">
                                        <thead>
                                            <tr>
                                                <th scope="col" class="request-select-cell">Pilih</th>
                                                <th scope="col">Produk</th>
                                                <th scope="col">Jumlah</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">ID pengiriman</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($group['requests'] as $request) {
                                                $is_ready = $request['status'] === 'Diproses' && $request['id_delivery'] === null;
                                                $status_classes = [
                                                    'Diproses' => 'ready',
                                                    'Dikirim' => 'sent',
                                                    'Selesai' => 'complete',
                                                    'Dibatalkan' => 'cancelled',
                                                ];
                                                $status_class = $status_classes[$request['status']] ?? '';
                                            ?>
                                                <tr>
                                                    <td class="request-select-cell">
                                                        <?php if ($is_ready) { ?>
                                                            <input
                                                                class="request-checkbox"
                                                                type="checkbox"
                                                                name="id_request[]"
                                                                value="<?php echo e($request['id_request']); ?>"
                                                                aria-label="Pilih <?php echo e($request['nama_produk']); ?> untuk pengiriman"
                                                            >
                                                        <?php } else { ?>
                                                            <span aria-label="Tidak dapat dipilih">—</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td>
                                                        <span class="request-id">#<?php echo e($request['id_request']); ?></span>
                                                        <span class="request-product"><?php echo e($request['nama_produk']); ?></span>
                                                    </td>
                                                    <td class="request-quantity"><?php echo e($request['jumlah']); ?></td>
                                                    <td>
                                                        <span class="request-status <?php echo e($status_class); ?>">
                                                            <?php echo e($request['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="request-delivery-id">
                                                        <?php echo $request['id_delivery'] !== null ? '#' . e($request['id_delivery']) : 'Belum dibuat'; ?>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($jumlah_tersedia > 0) { ?>
                                    <div class="request-group-actions">
                                        <p class="request-selection-count" aria-live="polite">Belum ada permintaan dipilih.</p>
                                        <button class="request-submit" type="submit" name="buat_pengiriman" value="1">
                                            Buat pengiriman <span aria-hidden="true">→</span>
                                        </button>
                                    </div>
                                    <script>
                                        (() => {
                                            const form = document.currentScript.parentElement;
                                            const checkboxes = form.querySelectorAll(".request-checkbox");
                                            const submit = form.querySelector(".request-submit");
                                            const countLabel = form.querySelector(".request-selection-count");

                                            const updateSelection = () => {
                                                const selected = form.querySelectorAll(".request-checkbox:checked").length;
                                                submit.disabled = selected === 0;
                                                countLabel.textContent = selected === 0
                                                    ? "Belum ada permintaan dipilih."
                                                    : `${selected} permintaan dipilih untuk pengiriman.`;
                                            };

                                            checkboxes.forEach((checkbox) => checkbox.addEventListener("change", updateSelection));
                                            updateSelection();
                                        })();
                                    </script>
                                <?php } else { ?>
                                    <p class="request-not-ready">Pengiriman dapat dibuat setelah permintaan disetujui dan berstatus Diproses.</p>
                                <?php } ?>
                            </form>
                        </div>
                    </section>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="request-empty">
                <strong>Belum ada permintaan pelanggan</strong>
                <p>Permintaan baru akan ditampilkan di sini setelah pelanggan mengajukannya.</p>
            </div>
        <?php } ?>

    </main>

</body>
</html>
