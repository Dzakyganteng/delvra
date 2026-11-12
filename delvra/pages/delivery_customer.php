
<?php

session_start();
require_once "../config/database.php";

// Cek login
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// Hanya Customer
if ($_SESSION['role'] != 'customer') {
    echo "Akses ditolak!";
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id_customer = (int) $_SESSION['id_user'];
$error = "";

// Token keamanan
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ======================================
// PROSES KONFIRMASI DELIVERY
// ======================================

if (isset($_POST['konfirmasi_delivery'])) {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Permintaan tidak valid.";

    } else {

        $id_pengiriman = filter_var(
            $_POST['id_pengiriman'] ?? '',
            FILTER_VALIDATE_INT
        );

        if (!$id_pengiriman || $id_pengiriman < 1) {

            $error = "ID Delivery tidak valid.";

        } else {

            mysqli_begin_transaction($conn);

            try {

                // Kunci data pengiriman
                $stmt_delivery = mysqli_prepare(
                    $conn,
                    "SELECT id_pengiriman, status_pengiriman
                     FROM pengiriman
                     WHERE id_pengiriman = ?
                     FOR UPDATE"
                );

                mysqli_stmt_bind_param(
                    $stmt_delivery,
                    "i",
                    $id_pengiriman
                );

                mysqli_stmt_execute($stmt_delivery);

                $delivery_result = mysqli_stmt_get_result($stmt_delivery);

                if (mysqli_num_rows($delivery_result) != 1) {
                    throw new Exception("Delivery tidak ditemukan.");
                }

                $data_delivery = mysqli_fetch_assoc($delivery_result);

                mysqli_stmt_close($stmt_delivery);


                // Ambil seluruh request dalam delivery
                $stmt_request = mysqli_prepare(
                    $conn,
                    "SELECT
                        id_request,
                        id_customer,
                        status
                     FROM requests
                     WHERE id_pengiriman = ?
                     FOR UPDATE"
                );

                mysqli_stmt_bind_param(
                    $stmt_request,
                    "i",
                    $id_pengiriman
                );

                mysqli_stmt_execute($stmt_request);

                $request_result = mysqli_stmt_get_result($stmt_request);

                $daftar_request = [];

                while ($request = mysqli_fetch_assoc($request_result)) {

                    // Pastikan semua request milik customer login
                    if ((int)$request['id_customer'] !== $id_customer) {
                        throw new Exception("Akses Delivery ditolak.");
                    }

                    $daftar_request[] = $request;
                }

                mysqli_stmt_close($stmt_request);


                if (empty($daftar_request)) {
                    throw new Exception("Delivery tidak memiliki request.");
                }


                // Cek status delivery
                if ($data_delivery['status_pengiriman'] == 'Selesai') {
                    throw new Exception("Delivery sudah dikonfirmasi sebelumnya.");
                }


                // Pastikan semua request masih Dikirim
                foreach ($daftar_request as $request) {

                    if ($request['status'] != 'Dikirim') {
                        throw new Exception(
                            "Status salah satu request tidak sesuai."
                        );
                    }
                }


                // Ubah seluruh status request menjadi Selesai
                $stmt_update = mysqli_prepare(
                    $conn,
                    "UPDATE requests
                     SET status = 'Selesai'
                     WHERE id_pengiriman = ?
                     AND id_customer = ?
                     AND status = 'Dikirim'"
                );

                mysqli_stmt_bind_param(
                    $stmt_update,
                    "ii",
                    $id_pengiriman,
                    $id_customer
                );

                if (
                    !mysqli_stmt_execute($stmt_update) ||
                    mysqli_stmt_affected_rows($stmt_update) != count($daftar_request)
                ) {
                    throw new Exception(
                        "Gagal memperbarui status request."
                    );
                }

                mysqli_stmt_close($stmt_update);


                // Ubah status pengiriman
                $stmt_update_delivery = mysqli_prepare(
                    $conn,
                    "UPDATE pengiriman
                     SET status_pengiriman = 'Selesai'
                     WHERE id_pengiriman = ?"
                );

                mysqli_stmt_bind_param(
                    $stmt_update_delivery,
                    "i",
                    $id_pengiriman
                );

                if (!mysqli_stmt_execute($stmt_update_delivery)) {
                    throw new Exception(
                        "Gagal memperbarui status Delivery."
                    );
                }

                mysqli_stmt_close($stmt_update_delivery);


                // Simpan seluruh perubahan
                mysqli_commit($conn);

                header("Location: delivery_customer.php?success=1");
                exit;

            } catch (Throwable $e) {

                mysqli_rollback($conn);

                $error = $e->getMessage();
            }
        }
    }
}


// ======================================
// AMBIL DELIVERY MILIK CUSTOMER
// ======================================

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        p.id_pengiriman,
        p.tanggal_kirim,
        p.status_pengiriman,
        COUNT(r.id_request) AS total_request

     FROM pengiriman p

     JOIN requests r
        ON p.id_pengiriman = r.id_pengiriman

     WHERE r.id_customer = ?

     GROUP BY
        p.id_pengiriman,
        p.tanggal_kirim,
        p.status_pengiriman

     ORDER BY p.id_pengiriman DESC"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id_customer
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$delivery_count = mysqli_num_rows($result);


// ======================================
// QUERY DETAIL REQUEST
// ======================================

$stmt_detail = mysqli_prepare(
    $conn,
    "SELECT
        id_request,
        nama_produk,
        jumlah,
        tanggal_request,
        status

     FROM requests

     WHERE id_pengiriman = ?
     AND id_customer = ?

     ORDER BY id_request ASC"
);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Delivery Customer - Delvra</title>
    <style>
        :root {
            --delivery-primary: #1c39b7;
            --delivery-primary-dark: #102878;
            --delivery-ink: #18233d;
            --delivery-muted: #78839a;
            --delivery-line: #e4e9f2;
            --delivery-surface: #fff;
            --delivery-background: #f4f7fc;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--delivery-ink);
            background: var(--delivery-background);
            font-family: Inter, "Segoe UI", sans-serif;
        }

        .delivery-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 14px clamp(20px, 5vw, 72px);
            background: var(--delivery-surface);
            border-bottom: 1px solid var(--delivery-line);
        }

        .delivery-brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            color: var(--delivery-ink);
            font-weight: 750;
            text-decoration: none;
        }

        .delivery-brand img {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .delivery-account {
            display: flex;
            align-items: center;
            gap: 14px;
            color: var(--delivery-muted);
            font-size: .9rem;
        }

        .delivery-account strong {
            color: var(--delivery-ink);
        }

        .delivery-main {
            width: min(100% - 40px, 1120px);
            margin: 0 auto;
            padding: 46px 0 72px;
        }

        .delivery-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 22px;
            margin-bottom: 26px;
        }

        .delivery-eyebrow {
            margin: 0 0 9px;
            color: var(--delivery-primary);
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        .delivery-heading h1 {
            margin: 0;
            font-size: clamp(2rem, 5vw, 2.8rem);
            font-weight: 760;
            letter-spacing: -.05em;
        }

        .delivery-heading-copy {
            max-width: 650px;
            margin: 12px 0 0;
            color: var(--delivery-muted);
            line-height: 1.65;
        }

        .delivery-total {
            flex: 0 0 auto;
            padding: 13px 17px;
            color: var(--delivery-primary-dark);
            background: #eef2ff;
            border-radius: 13px;
            font-size: .9rem;
            font-weight: 750;
        }

        .delivery-total strong {
            margin-right: 5px;
            font-size: 1.2rem;
        }

        .delivery-alert {
            display: flex;
            gap: 10px;
            margin: 0 0 18px;
            padding: 13px 15px;
            border: 1px solid;
            border-radius: 11px;
            font-size: .9rem;
            line-height: 1.45;
        }

        .delivery-alert.error {
            color: #a32828;
            background: #fff1f1;
            border-color: #f3caca;
        }

        .delivery-alert.success {
            color: #176b4b;
            background: #edfaf3;
            border-color: #c7ecd8;
        }

        .delivery-list {
            display: grid;
            gap: 20px;
        }

        .delivery-card {
            min-width: 0;
            overflow: hidden;
            background: var(--delivery-surface);
            border: 1px solid var(--delivery-line);
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(30, 48, 90, .045);
        }

        .delivery-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--delivery-line);
        }

        .delivery-title {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
        }

        .delivery-icon {
            display: grid;
            width: 44px;
            height: 44px;
            flex: 0 0 auto;
            place-items: center;
            color: var(--delivery-primary);
            background: #eef2ff;
            border-radius: 13px;
            font-size: 1.1rem;
            font-weight: 800;
        }

        .delivery-title h2 {
            margin: 0;
            font-size: 1.08rem;
            font-weight: 750;
            letter-spacing: -.02em;
        }

        .delivery-title p {
            margin: 4px 0 0;
            color: var(--delivery-muted);
            font-size: .82rem;
        }

        .delivery-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            flex: 0 0 auto;
            padding: 7px 11px;
            color: #075d79;
            background: #e2f6fb;
            border-radius: 999px;
            font-size: .77rem;
            font-weight: 750;
            white-space: nowrap;
        }

        .delivery-status::before {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
            content: "";
        }

        .delivery-status.complete {
            color: #176b4b;
            background: #e7f7ee;
        }

        .delivery-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            padding: 18px 24px;
            background: #f9faff;
            border-bottom: 1px solid var(--delivery-line);
        }

        .delivery-metric {
            padding: 2px 18px;
            border-right: 1px solid var(--delivery-line);
        }

        .delivery-metric:first-child {
            padding-left: 0;
        }

        .delivery-metric:last-child {
            padding-right: 0;
            border-right: 0;
        }

        .delivery-metric-label {
            display: block;
            margin-bottom: 5px;
            color: var(--delivery-muted);
            font-size: .76rem;
            font-weight: 650;
        }

        .delivery-metric-value {
            color: var(--delivery-ink);
            font-size: .95rem;
            font-weight: 750;
        }

        .delivery-content {
            padding: 22px 24px 24px;
        }

        .delivery-section-heading {
            margin: 0 0 13px;
            font-size: .96rem;
            font-weight: 730;
        }

        .delivery-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--delivery-line);
            border-radius: 12px;
        }

        .delivery-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .87rem;
            text-align: left;
        }

        .delivery-table th {
            padding: 12px 14px;
            color: var(--delivery-muted);
            background: #f7f9fc;
            border-bottom: 1px solid var(--delivery-line);
            font-size: .72rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .delivery-table td {
            padding: 14px;
            border-bottom: 1px solid var(--delivery-line);
            vertical-align: middle;
        }

        .delivery-table tr:last-child td {
            border-bottom: 0;
        }

        .delivery-request-id {
            color: var(--delivery-muted);
            font-variant-numeric: tabular-nums;
        }

        .delivery-quantity {
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
            font-size: .75rem;
            font-weight: 750;
            white-space: nowrap;
        }

        .request-status.shipping {
            color: #075d79;
            background: #e2f6fb;
        }

        .request-status.complete {
            color: #176b4b;
            background: #e7f7ee;
        }

        .delivery-confirmation {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-top: 20px;
            padding: 16px 18px;
            background: #f5f7ff;
            border: 1px solid #e1e7fa;
            border-radius: 13px;
        }

        .delivery-confirmation-copy {
            margin: 0;
            color: var(--delivery-muted);
            font-size: .85rem;
            line-height: 1.55;
        }

        .delivery-confirmation-copy strong {
            display: block;
            margin-bottom: 3px;
            color: var(--delivery-ink);
            font-size: .9rem;
        }

        .delivery-confirm-button {
            display: inline-flex;
            min-height: 44px;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            color: #fff;
            background: var(--delivery-primary);
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            font: inherit;
            font-size: .84rem;
            font-weight: 700;
            transition: background .16s ease, transform .16s ease;
        }

        .delivery-confirm-button:hover {
            background: var(--delivery-primary-dark);
            transform: translateY(-1px);
        }

        .delivery-complete-note {
            margin: 20px 0 0;
            padding: 13px 15px;
            color: #176b4b;
            background: #edfaf3;
            border: 1px solid #c7ecd8;
            border-radius: 11px;
            font-size: .88rem;
            font-weight: 650;
        }

        .delivery-empty {
            padding: 42px 22px;
            background: var(--delivery-surface);
            border: 1px dashed #cfd8e8;
            border-radius: 16px;
            color: var(--delivery-muted);
            text-align: center;
        }

        .delivery-empty-icon {
            display: grid;
            width: 52px;
            height: 52px;
            place-items: center;
            margin: 0 auto 13px;
            color: var(--delivery-primary);
            background: #eef2ff;
            border-radius: 16px;
            font-size: 1.3rem;
        }

        .delivery-empty strong {
            display: block;
            margin-bottom: 6px;
            color: var(--delivery-ink);
            font-size: 1rem;
        }

        .delivery-empty p {
            margin: 0;
            font-size: .88rem;
        }

        .delivery-footer {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 24px;
        }

        .delivery-nav-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 9px 14px;
            color: var(--delivery-ink);
            background: var(--delivery-surface);
            border: 1px solid var(--delivery-line);
            border-radius: 10px;
            font-size: .88rem;
            font-weight: 650;
            text-decoration: none;
            transition: border-color .16s ease, background .16s ease, transform .16s ease;
        }

        .delivery-nav-link:hover {
            border-color: #bdc9e8;
            background: #f7f9ff;
            transform: translateY(-1px);
        }

        body[data-theme="dark"] .delivery-topbar,
        body[data-theme="dark"] .delivery-card,
        body[data-theme="dark"] .delivery-empty {
            color: var(--theme-text) !important;
            background: var(--theme-surface) !important;
            border-color: var(--theme-border) !important;
        }

        body[data-theme="dark"] .delivery-brand,
        body[data-theme="dark"] .delivery-heading h1,
        body[data-theme="dark"] .delivery-title h2,
        body[data-theme="dark"] .delivery-metric-value,
        body[data-theme="dark"] .delivery-confirmation-copy strong,
        body[data-theme="dark"] .delivery-empty strong {
            color: var(--theme-text) !important;
        }

        body[data-theme="dark"] .delivery-account,
        body[data-theme="dark"] .delivery-heading-copy,
        body[data-theme="dark"] .delivery-title p,
        body[data-theme="dark"] .delivery-metric-label,
        body[data-theme="dark"] .delivery-confirmation-copy,
        body[data-theme="dark"] .delivery-request-id,
        body[data-theme="dark"] .delivery-empty {
            color: var(--theme-muted) !important;
        }

        body[data-theme="dark"] .delivery-total,
        body[data-theme="dark"] .delivery-icon {
            color: #c7d2ff !important;
            background: #293d68 !important;
        }

        body[data-theme="dark"] .delivery-metrics,
        body[data-theme="dark"] .delivery-table th,
        body[data-theme="dark"] .delivery-confirmation {
            background: var(--theme-surface-raised) !important;
            border-color: var(--theme-border) !important;
        }

        body[data-theme="dark"] .delivery-table th,
        body[data-theme="dark"] .delivery-table td,
        body[data-theme="dark"] .delivery-metric {
            border-color: var(--theme-border) !important;
        }

        body[data-theme="dark"] .delivery-table th,
        body[data-theme="dark"] .delivery-table td {
            color: var(--theme-text) !important;
        }

        body[data-theme="dark"] .delivery-status,
        body[data-theme="dark"] .request-status.shipping {
            color: #9de7f5 !important;
            background: #16414e !important;
        }

        body[data-theme="dark"] .delivery-status.complete,
        body[data-theme="dark"] .request-status.complete,
        body[data-theme="dark"] .delivery-complete-note {
            color: #a8eac2 !important;
            background: #1d4936 !important;
            border-color: #2f6651 !important;
        }

        body[data-theme="dark"] .delivery-nav-link {
            color: var(--theme-text) !important;
            background: var(--theme-surface) !important;
            border-color: var(--theme-border) !important;
        }

        body[data-theme="dark"] .delivery-alert.error {
            color: #fecdd3 !important;
            background: #422b35 !important;
            border-color: #754253 !important;
        }

        body[data-theme="dark"] .delivery-alert.success {
            color: #b7f2ce !important;
            background: #173b30 !important;
            border-color: #2f6651 !important;
        }

        @media (max-width: 700px) {
            .delivery-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .delivery-total {
                padding: 9px 12px;
            }
        }

        @media (max-width: 560px) {
            .delivery-topbar {
                align-items: flex-start;
                padding: 12px 16px;
            }

            .delivery-brand img {
                width: 36px;
                height: 36px;
            }

            .delivery-account {
                flex-direction: column;
                align-items: flex-end;
                gap: 3px;
                font-size: .8rem;
            }

            .delivery-main {
                width: min(100% - 28px, 1120px);
                padding-top: 30px;
            }

            .delivery-card-header {
                align-items: flex-start;
                flex-direction: column;
                padding: 17px 16px;
            }

            .delivery-metrics {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 16px;
            }

            .delivery-metric,
            .delivery-metric:first-child,
            .delivery-metric:last-child {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                padding: 0;
                border: 0;
            }

            .delivery-metric-label {
                margin: 0;
            }

            .delivery-content {
                padding: 18px 14px;
            }

            .delivery-confirmation {
                align-items: stretch;
                flex-direction: column;
                padding: 14px;
            }

            .delivery-confirm-button {
                width: 100%;
            }

            .delivery-footer > * {
                flex: 1 1 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
</head>

<body>
    <?php include __DIR__ . '/includes/navbar_customer.php'; ?>

    <main class="delivery-main">
        <div class="delivery-heading">
            <div>
                <p class="delivery-eyebrow">Customer</p>
                <h1>Pengiriman saya</h1>
                <p class="delivery-heading-copy">Pantau jadwal dan status pengiriman produk Anda. Setelah semua barang diterima, konfirmasikan penerimaan di bagian bawah pengiriman.</p>
            </div>
            <div class="delivery-total" aria-label="<?php echo $delivery_count; ?> pengiriman">
                <strong><?php echo $delivery_count; ?></strong> pengiriman
            </div>
        </div>

        <?php if ($error !== "") { ?>
            <div class="delivery-alert error" role="alert">
                <span aria-hidden="true">!</span>
                <span><?php echo e($error); ?></span>
            </div>
        <?php } ?>

        <?php if (isset($_GET['success'])) { ?>
            <div class="delivery-alert success" role="status">
                <span aria-hidden="true">✓</span>
                <span>Barang berhasil dikonfirmasi sebagai diterima. Terima kasih!</span>
            </div>
        <?php } ?>

        <?php if ($delivery_count > 0) { ?>
            <div class="delivery-list">
                <?php while ($delivery = mysqli_fetch_assoc($result)) {
                    $tanggal_kirim = new DateTime($delivery['tanggal_kirim']);
                    $estimasi_tiba = clone $tanggal_kirim;
                    $estimasi_tiba->modify('+3 days');
                    $is_complete = $delivery['status_pengiriman'] === 'Selesai';
                ?>
                    <article class="delivery-card" aria-labelledby="delivery-title-<?php echo e($delivery['id_pengiriman']); ?>">
                        <div class="delivery-card-header">
                            <div class="delivery-title">
                                <span class="delivery-icon" aria-hidden="true">▣</span>
                                <div>
                                    <h2 id="delivery-title-<?php echo e($delivery['id_pengiriman']); ?>">
                                        Pengiriman #<?php echo e($delivery['id_pengiriman']); ?>
                                    </h2>
                                    <p><?php echo e($delivery['total_request']); ?> jenis produk dalam pengiriman ini</p>
                                </div>
                            </div>
                            <span class="delivery-status <?php echo $is_complete ? 'complete' : ''; ?>">
                                <?php echo $is_complete ? 'Selesai' : e($delivery['status_pengiriman']); ?>
                            </span>
                        </div>

                        <div class="delivery-metrics" aria-label="Informasi pengiriman">
                            <div class="delivery-metric">
                                <span class="delivery-metric-label">Tanggal dikirim</span>
                                <span class="delivery-metric-value"><?php echo e($tanggal_kirim->format('d-m-Y')); ?></span>
                            </div>
                            <div class="delivery-metric">
                                <span class="delivery-metric-label">Perkiraan tiba</span>
                                <span class="delivery-metric-value"><?php echo e($estimasi_tiba->format('d-m-Y')); ?></span>
                            </div>
                            <div class="delivery-metric">
                                <span class="delivery-metric-label">Jumlah produk</span>
                                <span class="delivery-metric-value"><?php echo e($delivery['total_request']); ?> jenis</span>
                            </div>
                        </div>

                        <div class="delivery-content">
                            <h3 class="delivery-section-heading">Produk dalam pengiriman</h3>
                            <div class="delivery-table-wrap">
                                <table class="delivery-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Produk</th>
                                            <th scope="col">Jumlah</th>
                                            <th scope="col">Tanggal permintaan</th>
                                            <th scope="col">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        mysqli_stmt_bind_param(
                                            $stmt_detail,
                                            "ii",
                                            $delivery['id_pengiriman'],
                                            $id_customer
                                        );
                                        mysqli_stmt_execute($stmt_detail);
                                        $detail_result = mysqli_stmt_get_result($stmt_detail);
                                        while ($detail = mysqli_fetch_assoc($detail_result)) {
                                            $request_status_class = $detail['status'] === 'Selesai' ? 'complete' : ($detail['status'] === 'Dikirim' ? 'shipping' : '');
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="delivery-request-id">#<?php echo e($detail['id_request']); ?></span>
                                                    <?php echo e($detail['nama_produk']); ?>
                                                </td>
                                                <td class="delivery-quantity"><?php echo e($detail['jumlah']); ?></td>
                                                <td><?php echo e(date('d-m-Y', strtotime($detail['tanggal_request']))); ?></td>
                                                <td>
                                                    <span class="request-status <?php echo e($request_status_class); ?>">
                                                        <?php echo e($detail['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if (!$is_complete) { ?>
                                <div class="delivery-confirmation">
                                    <p class="delivery-confirmation-copy">
                                        <strong>Sudah menerima semua produk?</strong>
                                        Konfirmasi hanya jika seluruh barang pada pengiriman ini sudah Anda terima.
                                    </p>
                                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin semua barang dalam pengiriman ini sudah diterima?');">
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo e($_SESSION['csrf_token']); ?>"
                                        >
                                        <input
                                            type="hidden"
                                            name="id_pengiriman"
                                            value="<?php echo e($delivery['id_pengiriman']); ?>"
                                        >
                                        <button class="delivery-confirm-button" type="submit" name="konfirmasi_delivery" value="1">
                                            ✓ Konfirmasi diterima
                                        </button>
                                    </form>
                                </div>
                            <?php } else { ?>
                                <p class="delivery-complete-note" role="status">
                                    ✓ Pengiriman ini telah selesai dan penerimaan barang sudah dikonfirmasi.
                                </p>
                            <?php } ?>
                        </div>
                    </article>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="delivery-empty">
                <span class="delivery-empty-icon" aria-hidden="true">▣</span>
                <strong>Belum ada pengiriman</strong>
                <p>Pengiriman akan tampil di sini setelah permintaan produk Anda diproses.</p>
            </div>
        <?php } ?>

    </main>

</body>

</html>

<?php

mysqli_stmt_close($stmt_detail);
mysqli_stmt_close($stmt);

?>
