
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

$error = "";

// Buat token keamanan
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// PROSES MEMBUAT REQUEST
if (isset($_POST['buat_request'])) {

    // Validasi token
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Permintaan tidak valid. Silakan coba lagi.";

    } else {

        $nama_produk = trim($_POST['nama_produk'] ?? '');
        $jumlah_input = $_POST['jumlah'] ?? '';

        $jumlah = filter_var(
            $jumlah_input,
            FILTER_VALIDATE_INT
        );

        // Validasi input
        if ($nama_produk == "") {

            $error = "Nama produk wajib diisi.";

        } elseif (strlen($nama_produk) > 100) {

            $error = "Nama produk maksimal 100 karakter.";

        } elseif ($jumlah === false || $jumlah < 1) {

            $error = "Jumlah produk minimal 1.";

        } else {

            $id_customer = $_SESSION['id_user'];

            // Simpan request ke database
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO requests
                (id_customer, nama_produk, jumlah, tanggal_request, status)
                VALUES (?, ?, ?, CURDATE(), 'Menunggu')"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "isi",
                $id_customer,
                $nama_produk,
                $jumlah
            );

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                header("Location: request_customer.php?success=1");
                exit;

            } else {

                $error = "Request gagal dibuat. Silakan coba lagi.";
                mysqli_stmt_close($stmt);
            }
        }
    }
}


// AMBIL RIWAYAT REQUEST MILIK CUSTOMER
$id_customer = $_SESSION['id_user'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        id_request,
        nama_produk,
        jumlah,
        tanggal_request,
        status
     FROM requests
     WHERE id_customer = ?
     ORDER BY tanggal_request DESC, id_request DESC"
);

mysqli_stmt_bind_param($stmt, "i", $id_customer);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

// Kelompokkan request berdasarkan tanggal
$requests_by_date = [];

while ($data = mysqli_fetch_assoc($result)) {

    $tanggal = $data['tanggal_request'];

    if (!isset($requests_by_date[$tanggal])) {
        $requests_by_date[$tanggal] = [];
    }

    $requests_by_date[$tanggal][] = $data;
}

$request_count = array_sum(array_map('count', $requests_by_date));

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Barang - Delvra</title>
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

        .request-logout,
        .request-back {
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

        .request-logout:hover,
        .request-back:hover {
            border-color: #bdc9e8;
            background: #f7f9ff;
            transform: translateY(-1px);
        }

        .request-main {
            width: min(100% - 40px, 1120px);
            margin: 0 auto;
            padding: 46px 0 72px;
        }

        .request-heading {
            margin-bottom: 28px;
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

        .request-heading p:last-child {
            max-width: 650px;
            margin: 12px 0 0;
            color: var(--request-muted);
            line-height: 1.65;
        }

        .request-layout {
            display: grid;
            grid-template-columns: minmax(0, .9fr) minmax(0, 1.5fr);
            align-items: start;
            gap: 22px;
        }

        .request-card {
            min-width: 0;
            padding: clamp(20px, 3vw, 30px);
            background: var(--request-surface);
            border: 1px solid var(--request-line);
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(30, 48, 90, .045);
        }

        .request-card-heading {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            margin-bottom: 22px;
        }

        .request-card-icon {
            display: grid;
            width: 42px;
            height: 42px;
            flex: 0 0 auto;
            place-items: center;
            color: var(--request-primary);
            background: #eef2ff;
            border-radius: 13px;
            font-size: 1.1rem;
            font-weight: 800;
        }

        .request-card-heading h2 {
            margin: 0;
            font-size: 1.12rem;
            font-weight: 730;
            letter-spacing: -.02em;
        }

        .request-card-heading p {
            margin: 5px 0 0;
            color: var(--request-muted);
            font-size: .88rem;
            line-height: 1.5;
        }

        .request-alert {
            display: flex;
            gap: 10px;
            margin: 0 0 18px;
            padding: 13px 15px;
            border: 1px solid;
            border-radius: 11px;
            font-size: .9rem;
            line-height: 1.45;
        }

        .request-alert.error {
            color: #a32828;
            background: #fff1f1;
            border-color: #f3caca;
        }

        .request-alert.success {
            color: #176b4b;
            background: #edfaf3;
            border-color: #c7ecd8;
        }

        .request-field {
            display: block;
            margin-bottom: 18px;
        }

        .request-field-label {
            display: block;
            margin-bottom: 7px;
            font-size: .88rem;
            font-weight: 680;
        }

        .request-field-hint {
            display: block;
            margin-top: 6px;
            color: var(--request-muted);
            font-size: .8rem;
        }

        .request-input {
            width: 100%;
            min-height: 48px;
            padding: 11px 13px;
            color: var(--request-ink);
            background: var(--request-surface);
            border: 1px solid #dce3ee;
            border-radius: 10px;
            font: inherit;
            transition: border-color .16s ease, box-shadow .16s ease;
        }

        .request-input:focus {
            border-color: var(--request-primary);
            outline: 0;
            box-shadow: 0 0 0 3px rgba(28, 57, 183, .12);
        }

        .request-submit {
            display: inline-flex;
            width: 100%;
            min-height: 48px;
            align-items: center;
            justify-content: center;
            gap: 9px;
            margin-top: 3px;
            padding: 12px 18px;
            color: #fff;
            background: var(--request-primary);
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            transition: background .16s ease, transform .16s ease, box-shadow .16s ease;
        }

        .request-submit:hover {
            background: var(--request-primary-dark);
            box-shadow: 0 8px 18px rgba(28, 57, 183, .18);
            transform: translateY(-1px);
        }

        .request-help {
            margin: 15px 0 0;
            color: var(--request-muted);
            font-size: .82rem;
            line-height: 1.55;
        }

        .history-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 20px;
        }

        .history-heading .request-card-heading {
            margin: 0;
        }

        .history-count {
            flex: 0 0 auto;
            padding: 6px 10px;
            color: var(--request-primary-dark);
            background: #eef2ff;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 750;
        }

        .request-date-group + .request-date-group {
            margin-top: 24px;
        }

        .request-date {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 10px;
            color: var(--request-muted);
            font-size: .82rem;
            font-weight: 700;
        }

        .request-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--request-line);
            border-radius: 12px;
        }

        .request-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .88rem;
            text-align: left;
        }

        .request-table th {
            padding: 12px 14px;
            color: var(--request-muted);
            background: #f7f9fc;
            border-bottom: 1px solid var(--request-line);
            font-size: .72rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .request-table td {
            padding: 14px;
            border-bottom: 1px solid var(--request-line);
            vertical-align: middle;
        }

        .request-table tr:last-child td {
            border-bottom: 0;
        }

        .request-id {
            color: var(--request-muted);
            font-variant-numeric: tabular-nums;
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
            font-size: .75rem;
            font-weight: 750;
            white-space: nowrap;
        }

        .request-status.waiting {
            color: #875a08;
            background: #fff5d8;
        }

        .request-status.processing {
            color: #2446a8;
            background: #eaf0ff;
        }

        .request-status.shipping {
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

        .request-empty {
            padding: 28px 18px;
            color: var(--request-muted);
            background: #f8faff;
            border: 1px dashed #cfd8e8;
            border-radius: 12px;
            text-align: center;
        }

        .request-empty strong {
            display: block;
            margin-bottom: 5px;
            color: var(--request-ink);
        }

        .request-footer {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 24px;
        }

        body[data-theme="dark"] .request-card-heading p,
        body[data-theme="dark"] .request-date,
        body[data-theme="dark"] .request-id,
        body[data-theme="dark"] .request-help {
            color: var(--theme-muted) !important;
        }

        body[data-theme="dark"] .request-table th,
        body[data-theme="dark"] .request-empty {
            background: var(--theme-surface-raised) !important;
        }

        body[data-theme="dark"] .request-alert.error {
            color: #fecdd3 !important;
            background: #422b35 !important;
            border-color: #754253 !important;
        }

        body[data-theme="dark"] .request-alert.success {
            color: #b7f2ce !important;
            background: #173b30 !important;
            border-color: #2f6651 !important;
        }

        body[data-theme="dark"] .request-status {
            color: var(--theme-text) !important;
            background: #3b475a !important;
        }

        body[data-theme="dark"] .request-status.waiting {
            color: #f8d77b !important;
            background: #554525 !important;
        }

        body[data-theme="dark"] .request-status.processing {
            color: #b9c9ff !important;
            background: #293d68 !important;
        }

        body[data-theme="dark"] .request-status.shipping {
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

        @media (max-width: 800px) {
            .request-layout {
                grid-template-columns: 1fr;
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
                gap: 7px;
                font-size: .8rem;
            }

            .request-main {
                width: min(100% - 28px, 1120px);
                padding-top: 32px;
            }

            .request-heading {
                margin-bottom: 20px;
            }

            .request-card {
                padding: 19px 16px;
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
    <?php include __DIR__ . '/includes/navbar_customer.php'; ?>

    <main class="request-main">
        <div class="request-heading">
            <p class="request-eyebrow">Customer</p>
            <h1>Permintaan produk</h1>
            <p>Ajukan produk yang Anda butuhkan, lalu pantau perkembangan permintaan melalui riwayat di halaman ini.</p>
        </div>

        <?php if ($error !== "") { ?>
            <div class="request-alert error" role="alert">
                <span aria-hidden="true">!</span>
                <span><?php echo e($error); ?></span>
            </div>
        <?php } ?>

        <?php if (isset($_GET['success'])) { ?>
            <div class="request-alert success" role="status">
                <span aria-hidden="true">✓</span>
                <span>Permintaan berhasil dikirim. Anda dapat melihat status terbaru di riwayat permintaan.</span>
            </div>
        <?php } ?>

        <div class="request-layout">
            <section class="request-card" aria-labelledby="create-request-title">
                <div class="request-card-heading">
                    <span class="request-card-icon" aria-hidden="true">＋</span>
                    <div>
                        <h2 id="create-request-title">Buat permintaan</h2>
                        <p>Isi nama produk dan jumlah yang Anda perlukan.</p>
                    </div>
                </div>

                <form method="POST" action="request_customer.php">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo e($_SESSION['csrf_token']); ?>"
                    >

                    <label class="request-field" for="nama_produk">
                        <span class="request-field-label">Nama produk</span>
                        <input
                            class="request-input"
                            type="text"
                            id="nama_produk"
                            name="nama_produk"
                            maxlength="100"
                            placeholder="Contoh: Komponen elektronik"
                            required
                        >
                        <span class="request-field-hint">Maksimal 100 karakter.</span>
                    </label>

                    <label class="request-field" for="jumlah">
                        <span class="request-field-label">Jumlah</span>
                        <input
                            class="request-input"
                            type="number"
                            id="jumlah"
                            name="jumlah"
                            min="1"
                            step="1"
                            placeholder="Contoh: 10"
                            required
                        >
                        <span class="request-field-hint">Masukkan jumlah minimal 1.</span>
                    </label>

                    <button class="request-submit" type="submit" name="buat_request" value="1">
                        Kirim permintaan <span aria-hidden="true">→</span>
                    </button>
                </form>

                <p class="request-help">Setelah dikirim, permintaan akan berstatus <strong>Menunggu</strong> hingga ditinjau.</p>
            </section>

            <section class="request-card" aria-labelledby="request-history-title">
                <div class="history-heading">
                    <div class="request-card-heading">
                        <span class="request-card-icon" aria-hidden="true">≡</span>
                        <div>
                            <h2 id="request-history-title">Riwayat permintaan</h2>
                            <p>Periksa status semua produk yang pernah Anda ajukan.</p>
                        </div>
                    </div>
                    <span class="history-count"><?php echo $request_count; ?> permintaan</span>
                </div>

                <?php if (count($requests_by_date) > 0) { ?>
                    <?php foreach ($requests_by_date as $tanggal => $requests) { ?>
                        <section class="request-date-group" aria-label="Permintaan tanggal <?php echo e(date('d-m-Y', strtotime($tanggal))); ?>">
                            <h3 class="request-date">
                                <span aria-hidden="true">▦</span>
                                <?php echo e(date('d-m-Y', strtotime($tanggal))); ?>
                            </h3>
                            <div class="request-table-wrap">
                                <table class="request-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Produk</th>
                                            <th scope="col">Jumlah</th>
                                            <th scope="col">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requests as $data) {
                                            $status_classes = [
                                                'Menunggu' => 'waiting',
                                                'Diproses' => 'processing',
                                                'Dikirim' => 'shipping',
                                                'Selesai' => 'complete',
                                                'Dibatalkan' => 'cancelled',
                                            ];
                                            $status_class = $status_classes[$data['status']] ?? '';
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="request-id">#<?php echo e($data['id_request']); ?></span>
                                                    <?php echo e($data['nama_produk']); ?>
                                                </td>
                                                <td class="request-quantity"><?php echo e($data['jumlah']); ?></td>
                                                <td>
                                                    <span class="request-status <?php echo e($status_class); ?>">
                                                        <?php echo e($data['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    <?php } ?>
                <?php } else { ?>
                    <div class="request-empty">
                        <strong>Belum ada permintaan</strong>
                        Permintaan produk yang Anda kirim akan muncul di sini.
                    </div>
                <?php } ?>
            </section>
        </div>

    </main>

</body>
</html>

<?php

mysqli_stmt_close($stmt);

?>
