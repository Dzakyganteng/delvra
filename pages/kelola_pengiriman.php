
<?php

session_start();
require_once "../config/database.php";

// Cek login
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// Hanya admin
if ($_SESSION['role'] != 'admin') {
    echo "Akses ditolak!";
    exit;
}

// Ambil data pengiriman
$query = "
    SELECT
        pengiriman.id_pengiriman,
        requests.id_request,
        users.nama AS nama_customer,
        requests.nama_produk,
        requests.jumlah,
        pengiriman.tanggal_kirim,
        pengiriman.status_pengiriman,
        pengiriman.tanggal_selesai,
        deliverer.nama AS nama_deliverer
    FROM pengiriman

    JOIN requests
        ON pengiriman.id_request = requests.id_request

    JOIN users
        ON requests.id_customer = users.id_user

    JOIN users AS deliverer
        ON pengiriman.id_deliverer = deliverer.id_user

    ORDER BY pengiriman.id_pengiriman DESC
";

$result = mysqli_query($conn, $query);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengiriman - Delvra</title>
    <style>
        :root {
            --primary: #1c39b7;
            --primary-dark: #102878;
            --ink: #111414;
            --muted: #737b78;
            --line: #e2e7e5;
            --surface: #ffffff;
            --background: #f3f6f5;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--ink);
            background:
                radial-gradient(circle at 92% 8%, rgba(28, 57, 183, 0.2), transparent 26%),
                var(--background);
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 22px clamp(24px, 6vw, 84px);
            background: var(--ink);
        }

        .brand {
            color: var(--primary);
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: 0.18em;
        }

        .logout {
            color: #ffffff;
            font-size: 0.88rem;
            font-weight: 700;
            text-decoration: none;
            transition: color 160ms ease;
        }

        .logout:hover {
            color: var(--primary);
        }

        .dashboard {
            width: min(100%, 1380px);
            margin: 0 auto;
            padding: 62px clamp(24px, 6vw, 84px) 80px;
        }

        .page-heading {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 40px;
        }

        .eyebrow {
            margin: 0 0 12px;
            color: var(--primary-dark);
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(2.2rem, 5vw, 3.8rem);
            line-height: 1;
        }

        .heading-copy {
            max-width: 480px;
            margin: 14px 0 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .page-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 0 0 auto;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 10px 14px;
            color: var(--ink);
            border: 1px solid #cbd4d0;
            border-radius: 8px;
            font-size: 0.86rem;
            font-weight: 800;
            text-decoration: none;
            transition: background 160ms ease, border-color 160ms ease;
        }

        .back-link:hover {
            background: #ffffff;
            border-color: var(--ink);
        }

        .section-heading {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 18px;
        }

        h2 {
            margin: 0;
            font-size: 1.25rem;
        }

        .section-heading span {
            color: var(--muted);
            font-size: 0.84rem;
        }

        .table-shell {
            overflow-x: auto;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: 0 12px 28px rgba(17, 20, 20, 0.05);
        }

        table {
            width: 100%;
            min-width: 1120px;
            border-collapse: collapse;
        }

        th {
            padding: 17px 16px;
            color: var(--muted);
            background: #f7f9f8;
            border-bottom: 1px solid var(--line);
            font-size: 0.7rem;
            letter-spacing: 0.06em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 17px 16px;
            border-bottom: 1px solid var(--line);
            font-size: 0.86rem;
            white-space: nowrap;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        tbody tr:hover {
            background: #fbfdfc;
        }

        .table-id {
            font-weight: 800;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 800;
        }

        .status-complete {
            color: #08734d;
            background: rgba(28, 57, 183, 0.2);
        }

        .status-in-progress {
            color: #286c9f;
            background: #e1f1ff;
        }

        .status-other {
            color: #59635f;
            background: #edf0ef;
        }

        .empty-state {
            padding: 50px 24px;
            color: var(--muted);
            text-align: center;
            white-space: normal;
        }

        .back-link:focus-visible,
        .logout:focus-visible {
            outline: 3px solid var(--primary-dark);
            outline-offset: 4px;
        }

        @media (max-width: 680px) {
            .topbar {
                padding: 20px 22px;
            }

            .dashboard {
                padding: 46px 22px 56px;
            }

            .page-heading {
                align-items: start;
                flex-direction: column;
                margin-bottom: 34px;
            }

            .section-heading {
                align-items: start;
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
</head>

<body>
    <?php include __DIR__ . '/includes/navbar_admin.php'; ?>

    <main class="dashboard">
        <section class="page-heading">
            <div>
                <p class="eyebrow">Ruang kerja admin</p>
                <h1>Kelola Pengiriman</h1>
                <p class="heading-copy">Pantau status pengiriman, customer, dan deliverer yang menangani setiap pesanan.</p>
            </div>
            <div class="page-actions">
                <a class="back-link" href="admin.php"><span aria-hidden="true">←</span> Kembali ke Dashboard</a>
            </div>
        </section>

        <section aria-labelledby="shipping-heading">
            <div class="section-heading">
                <h2 id="shipping-heading">Daftar pengiriman</h2>
                <span>Data terbaru ditampilkan lebih dulu</span>
            </div>

            <div class="table-shell">
                <table>
                    <thead>
                        <tr>
                            <th>ID Pengiriman</th>
                            <th>ID Request</th>
                            <th>Customer</th>
                            <th>Produk</th>
                            <th>Jumlah</th>
                            <th>Deliverer</th>
                            <th>Tanggal Kirim</th>
                            <th>Status Pengiriman</th>
                            <th>Tanggal Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0) { ?>
                            <?php while ($data = mysqli_fetch_assoc($result)) { ?>
                                <?php
                                $status_class = $data['status_pengiriman'] == 'Selesai'
                                    ? 'status-complete'
                                    : ($data['status_pengiriman'] == 'Dikirim' ? 'status-in-progress' : 'status-other');
                                ?>
                                <tr>
                                    <td class="table-id"><?= htmlspecialchars($data['id_pengiriman'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($data['id_request'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($data['nama_customer'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($data['nama_produk'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($data['jumlah'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($data['nama_deliverer'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($data['tanggal_kirim'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="status <?= $status_class; ?>"><?= htmlspecialchars($data['status_pengiriman'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><?= htmlspecialchars($data['tanggal_selesai'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td class="empty-state" colspan="9">Belum ada data pengiriman.</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
d