
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


// ========================================
// NGAMBIL DAFTAR DELIVERY
// ========================================

$id_deliverer = (int) $_SESSION['id_user'];
$is_history_page = basename($_SERVER['PHP_SELF'] ?? '') === 'riwayat_pengiriman.php';
$status_filter = $is_history_page
    ? "AND p.status_pengiriman = 'Selesai'"
    : "AND p.status_pengiriman <> 'Selesai'";

$query = "
    SELECT
        p.id_pengiriman,
        p.tanggal_kirim,
        p.status_pengiriman,
        d.nama AS nama_deliverer,

        COUNT(r.id_request) AS total_request,

        SUM(
            CASE
                WHEN r.status = 'Selesai' THEN 1
                ELSE 0
            END
        ) AS total_selesai

    FROM pengiriman AS p

    JOIN users AS d
        ON p.id_deliverer = d.id_user

    LEFT JOIN requests AS r
        ON p.id_pengiriman = r.id_pengiriman

    WHERE p.id_deliverer = ?
      $status_filter

    GROUP BY
        p.id_pengiriman,
        p.tanggal_kirim,
        p.status_pengiriman,
        d.nama

    ORDER BY p.id_pengiriman DESC
";

$stmt = mysqli_prepare($conn, $query);

mysqli_stmt_bind_param($stmt, "i", $id_deliverer);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$deliveries = [];
while ($delivery = mysqli_fetch_assoc($result)) {
    $deliveries[] = $delivery;
}


// ========================================
// QUERY DETAIL REQUEST
// ========================================

$stmt_detail = mysqli_prepare(
    $conn,
    "SELECT
        r.id_request,
        u.nama AS nama_customer,
        r.nama_produk,
        r.jumlah,
        r.tanggal_request,
        r.status

    FROM requests AS r

    JOIN users AS u
        ON r.id_customer = u.id_user

    WHERE r.id_pengiriman = ?

    ORDER BY r.id_request ASC"
);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo $is_history_page ? 'Riwayat Pengiriman' : 'Delivery'; ?> - Delvra</title>
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

        * { box-sizing: border-box; }

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

        .delivery-brand img { width: 42px; height: 42px; object-fit: contain; }

        .delivery-account {
            display: flex;
            align-items: center;
            gap: 14px;
            color: var(--delivery-muted);
            font-size: .9rem;
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
            margin-bottom: 24px;
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
            margin: 12px 0 0;
            color: var(--delivery-muted);
            line-height: 1.65;
        }

        .delivery-summary { display: flex; gap: 10px; }

        .delivery-summary-item {
            min-width: 105px;
            padding: 11px 14px;
            background: var(--delivery-surface);
            border: 1px solid var(--delivery-line);
            border-radius: 12px;
        }

        .delivery-summary-item strong,
        .delivery-summary-item span { display: block; }

        .delivery-summary-item strong { font-size: 1.1rem; }

        .delivery-summary-item span {
            margin-top: 2px;
            color: var(--delivery-muted);
            font-size: .74rem;
        }

        .delivery-list { display: grid; gap: 20px; }

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

        .delivery-title { display: flex; align-items: center; gap: 13px; }

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
        }

        .delivery-title h2 { margin: 0; font-size: 1.08rem; font-weight: 750; }

        .delivery-title p {
            margin: 4px 0 0;
            color: var(--delivery-muted);
            font-size: .82rem;
        }

        .delivery-status,
        .request-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 11px;
            color: #075d79;
            background: #e2f6fb;
            border-radius: 999px;
            font-size: .77rem;
            font-weight: 750;
            white-space: nowrap;
        }

        .delivery-status.complete,
        .request-status.complete {
            color: #176b4b;
            background: #e7f7ee;
        }

        .delivery-status.partial { color: #875a08; background: #fff5d8; }

        .delivery-status::before {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
            content: "";
        }

        .delivery-metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            padding: 18px 24px;
            background: #f9faff;
            border-bottom: 1px solid var(--delivery-line);
        }

        .delivery-metric {
            padding: 2px 16px;
            border-right: 1px solid var(--delivery-line);
        }

        .delivery-metric:first-child { padding-left: 0; }
        .delivery-metric:last-child { padding-right: 0; border-right: 0; }

        .delivery-metric-label {
            display: block;
            margin-bottom: 5px;
            color: var(--delivery-muted);
            font-size: .76rem;
            font-weight: 650;
        }

        .delivery-metric-value { font-size: .93rem; font-weight: 750; }
        .delivery-content { padding: 22px 24px 24px; }
        .delivery-section-heading { margin: 0 0 13px; font-size: .96rem; font-weight: 730; }

        .delivery-table-wrap { overflow-x: auto; border: 1px solid var(--delivery-line); border-radius: 12px; }

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

        .delivery-table td { padding: 14px; border-bottom: 1px solid var(--delivery-line); }
        .delivery-table tr:last-child td { border-bottom: 0; }
        .delivery-request-id { display: block; margin-bottom: 3px; color: var(--delivery-muted); font-size: .74rem; }
        .delivery-quantity { font-weight: 700; white-space: nowrap; }
        .request-status { padding: 6px 10px; font-size: .74rem; color: #526078; background: #edf0f5; }
        .request-status.shipping { color: #075d79; background: #e2f6fb; }

        .delivery-empty {
            padding: 42px 22px;
            color: var(--delivery-muted);
            background: var(--delivery-surface);
            border: 1px dashed #cfd8e8;
            border-radius: 16px;
            text-align: center;
        }

        .delivery-empty strong { display: block; margin-bottom: 6px; color: var(--delivery-ink); }
        .delivery-empty p { margin: 0; font-size: .88rem; }

        .delivery-footer { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px; }

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
        }

        body[data-theme="dark"] .delivery-topbar,
        body[data-theme="dark"] .delivery-card,
        body[data-theme="dark"] .delivery-empty,
        body[data-theme="dark"] .delivery-summary-item {
            color: var(--theme-text) !important;
            background: var(--theme-surface) !important;
            border-color: var(--theme-border) !important;
        }

        body[data-theme="dark"] .delivery-brand,
        body[data-theme="dark"] .delivery-heading h1,
        body[data-theme="dark"] .delivery-title h2,
        body[data-theme="dark"] .delivery-metric-value,
        body[data-theme="dark"] .delivery-empty strong {
            color: var(--theme-text) !important;
        }

        body[data-theme="dark"] .delivery-account,
        body[data-theme="dark"] .delivery-heading-copy,
        body[data-theme="dark"] .delivery-title p,
        body[data-theme="dark"] .delivery-summary-item span,
        body[data-theme="dark"] .delivery-metric-label,
        body[data-theme="dark"] .delivery-request-id,
        body[data-theme="dark"] .delivery-empty {
            color: var(--theme-muted) !important;
        }

        body[data-theme="dark"] .delivery-icon { color: #c7d2ff !important; background: #293d68 !important; }
        body[data-theme="dark"] .delivery-metrics,
        body[data-theme="dark"] .delivery-table th {
            background: var(--theme-surface-raised) !important;
            border-color: var(--theme-border) !important;
        }

        body[data-theme="dark"] .delivery-table th,
        body[data-theme="dark"] .delivery-table td,
        body[data-theme="dark"] .delivery-metric { border-color: var(--theme-border) !important; }
        body[data-theme="dark"] .delivery-table th,
        body[data-theme="dark"] .delivery-table td { color: var(--theme-text) !important; }
        body[data-theme="dark"] .delivery-status,
        body[data-theme="dark"] .request-status.shipping { color: #9de7f5 !important; background: #16414e !important; }
        body[data-theme="dark"] .delivery-status.complete,
        body[data-theme="dark"] .request-status.complete { color: #a8eac2 !important; background: #1d4936 !important; }
        body[data-theme="dark"] .delivery-status.partial { color: #f8d77b !important; background: #554525 !important; }
        body[data-theme="dark"] .request-status { color: var(--theme-text) !important; background: #3b475a !important; }

        body[data-theme="dark"] .delivery-nav-link {
            color: var(--theme-text) !important;
            background: var(--theme-surface) !important;
            border-color: var(--theme-border) !important;
        }

        @media (max-width: 760px) {
            .delivery-heading { align-items: flex-start; flex-direction: column; }
        }

        @media (max-width: 600px) {
            .delivery-topbar { align-items: flex-start; padding: 12px 16px; }
            .delivery-brand img { width: 36px; height: 36px; }
            .delivery-account { flex-direction: column; align-items: flex-end; gap: 5px; font-size: .8rem; }
            .delivery-main { width: min(100% - 28px, 1120px); padding-top: 30px; }
            .delivery-summary { width: 100%; }
            .delivery-summary-item { flex: 1; }
            .delivery-card-header { align-items: flex-start; flex-direction: column; padding: 17px 16px; }
            .delivery-metrics { grid-template-columns: 1fr 1fr; gap: 14px 8px; padding: 16px; }
            .delivery-metric, .delivery-metric:first-child, .delivery-metric:last-child { padding: 0; border: 0; }
            .delivery-content { padding: 18px 14px; }
            .delivery-footer > * { flex: 1 1 100%; }
        }
    </style>
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
</head>

<body>
    <?php include __DIR__ . '/includes/navbar_deliverer.php'; ?>

    <main class="delivery-main">
        <div class="delivery-heading">
            <div>
                <p class="delivery-eyebrow">Deliverer</p>
                <h1><?php echo $is_history_page ? 'Riwayat pengiriman' : 'Pengiriman saya'; ?></h1>
                <p class="delivery-heading-copy">
                    <?php echo $is_history_page
                        ? 'Lihat semua pengiriman yang sudah berstatus Selesai beserta rincian produknya.'
                        : 'Pantau progres pengiriman dan produk yang termasuk di dalamnya.'; ?>
                </p>
            </div>
            <div class="delivery-summary" aria-label="Ringkasan pengiriman">
                <div class="delivery-summary-item">
                    <strong><?php echo count($deliveries); ?></strong>
                    <span><?php echo $is_history_page ? 'Pengiriman selesai' : 'Pengiriman selain status Selesai'; ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($deliveries)) { ?>
            <div class="delivery-list">
                <?php foreach ($deliveries as $delivery) {
                    $total = (int) $delivery['total_request'];
                    $selesai = (int) $delivery['total_selesai'];
                    $status_delivery = $delivery['status_pengiriman'];
                    $status_class = $status_delivery === 'Selesai' ? 'complete' : '';
                ?>
                    <article class="delivery-card" aria-labelledby="delivery-title-<?php echo e($delivery['id_pengiriman']); ?>">
                        <div class="delivery-card-header">
                            <div class="delivery-title">
                                <span class="delivery-icon" aria-hidden="true">▣</span>
                                <div>
                                    <h2 id="delivery-title-<?php echo e($delivery['id_pengiriman']); ?>">
                                        Pengiriman #<?php echo e($delivery['id_pengiriman']); ?>
                                    </h2>
                                    <p><?php echo $total; ?> permintaan produk</p>
                                </div>
                            </div>
                            <span class="delivery-status <?php echo $status_class; ?>"><?php echo e($status_delivery); ?></span>
                        </div>

                        <div class="delivery-metrics" aria-label="Ringkasan pengiriman">
                            <div class="delivery-metric">
                                <span class="delivery-metric-label">Tanggal dikirim</span>
                                <span class="delivery-metric-value"><?php echo e(date('d-m-Y', strtotime($delivery['tanggal_kirim']))); ?></span>
                            </div>
                            <div class="delivery-metric">
                                <span class="delivery-metric-label">Jumlah permintaan</span>
                                <span class="delivery-metric-value"><?php echo $total; ?></span>
                            </div>
                            <div class="delivery-metric">
                                <span class="delivery-metric-label">Sudah selesai</span>
                                <span class="delivery-metric-value"><?php echo $selesai; ?> dari <?php echo $total; ?></span>
                            </div>
                            <div class="delivery-metric">
                                <span class="delivery-metric-label">Kurir</span>
                                <span class="delivery-metric-value"><?php echo e($delivery['nama_deliverer']); ?></span>
                            </div>
                        </div>

                        <div class="delivery-content">
                            <h3 class="delivery-section-heading">Rincian produk</h3>
                            <div class="delivery-table-wrap">
                                <table class="delivery-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Produk</th>
                                            <th scope="col">Pelanggan</th>
                                            <th scope="col">Jumlah</th>
                                            <th scope="col">Tanggal permintaan</th>
                                            <th scope="col">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        mysqli_stmt_bind_param($stmt_detail, "i", $delivery['id_pengiriman']);
                                        mysqli_stmt_execute($stmt_detail);
                                        $detail_result = mysqli_stmt_get_result($stmt_detail);
                                        while ($detail = mysqli_fetch_assoc($detail_result)) {
                                            $detail_status_class = $detail['status'] === 'Selesai' ? 'complete' : ($detail['status'] === 'Dikirim' ? 'shipping' : '');
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="delivery-request-id">#<?php echo e($detail['id_request']); ?></span>
                                                    <?php echo e($detail['nama_produk']); ?>
                                                </td>
                                                <td><?php echo e($detail['nama_customer']); ?></td>
                                                <td class="delivery-quantity"><?php echo e($detail['jumlah']); ?></td>
                                                <td><?php echo e(date('d-m-Y', strtotime($detail['tanggal_request']))); ?></td>
                                                <td><span class="request-status <?php echo $detail_status_class; ?>"><?php echo e($detail['status']); ?></span></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </article>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="delivery-empty">
                <strong><?php echo $is_history_page ? 'Belum ada riwayat pengiriman selesai' : 'Belum ada pengiriman'; ?></strong>
                <p><?php echo $is_history_page
                    ? 'Pengiriman yang berstatus Selesai akan ditampilkan di sini.'
                    : 'Pengiriman yang ditugaskan kepada Anda akan muncul di halaman ini.'; ?></p>
            </div>
        <?php } ?>

    </main>

</body>
</html>

<?php

mysqli_stmt_close($stmt_detail);
mysqli_stmt_close($stmt);

?>
