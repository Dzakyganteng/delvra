<?php
session_start();
require_once "../config/database.php";

// Proteksi halaman
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Proses perubahan status
$data_berhasil_diubah = false;
if (isset($_POST['ubah_status'])) {
    $statuses = $_POST['status'] ?? null;
    $allowed_statuses = ['Menunggu', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan'];

    if (!is_array($statuses)) {
        http_response_code(400);
        die("Data status request tidak valid.");
    }

    $stmt = mysqli_prepare($conn, "
        UPDATE requests
        SET status = ?
        WHERE id_request = ? AND status <> ?
    ");

    if (!$stmt) {
        die("Status request gagal disimpan: " . mysqli_error($conn));
    }

    $jumlah_perubahan = 0;
    foreach ($statuses as $id_request => $status) {
        $id_request = filter_var($id_request, FILTER_VALIDATE_INT);
        if ($id_request === false || $id_request < 1 || !in_array($status, $allowed_statuses, true)) {
            http_response_code(400);
            die("Data status request tidak valid.");
        }

        mysqli_stmt_bind_param($stmt, "sis", $status, $id_request, $status);
        if (!mysqli_stmt_execute($stmt)) {
            die("Status request gagal disimpan: " . mysqli_stmt_error($stmt));
        }
        $jumlah_perubahan += mysqli_stmt_affected_rows($stmt);
    }

    mysqli_stmt_close($stmt);
    $data_berhasil_diubah = $jumlah_perubahan > 0;
}

// Ambil data request
$query = "
    SELECT 
        requests.id_request,
        users.nama AS nama_customer,
        requests.nama_produk,
        requests.jumlah,
        requests.tanggal_request,
        requests.status
    FROM requests
    INNER JOIN users 
        ON requests.id_customer = users.id_user
    ORDER BY requests.id_request DESC
";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Permintaan Produk - Delvra</title>
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
            width: min(100%, 1280px);
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

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            flex: 0 0 auto;
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
            min-width: 980px;
            border-collapse: collapse;
        }

        th {
            padding: 17px 18px;
            color: var(--muted);
            background: #f7f9f8;
            border-bottom: 1px solid var(--line);
            font-size: 0.72rem;
            letter-spacing: 0.07em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 16px 18px;
            border-bottom: 1px solid var(--line);
            font-size: 0.88rem;
            white-space: nowrap;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        tbody tr:hover {
            background: #fbfdfc;
        }

        .request-id {
            font-weight: 800;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 800;
        }

        .status-waiting {
            color: #875c08;
            background: #fff1cf;
        }

        .status-processing {
            color: #08734d;
            background: rgba(28, 57, 183, 0.2);
        }

        .status-shipped {
            color: #286c9f;
            background: #e1f1ff;
        }

        .status-complete {
            color: #59635f;
            background: #edf0ef;
        }

        .status-cancelled {
            color: #9b3030;
            background: #ffebeb;
        }

        .status-select {
            min-width: 132px;
            padding: 9px 32px 9px 11px;
            color: var(--ink);
            background: #ffffff;
            border: 1px solid #cbd4d0;
            border-radius: 7px;
            font: inherit;
            font-size: 0.82rem;
        }

        .save-button {
            padding: 9px 13px;
            color: var(--ink);
            background: var(--primary);
            border: 1px solid var(--primary);
            border-radius: 7px;
            cursor: pointer;
            font: inherit;
            font-size: 0.82rem;
            font-weight: 800;
            transition: background 160ms ease, transform 160ms ease;
        }

        .save-button:hover {
            background: #35efad;
            transform: translateY(-1px);
        }

        .save-button[hidden] {
            display: none;
        }

        .request-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 12px;
        }

        .empty-state {
            padding: 50px 24px;
            color: var(--muted);
            text-align: center;
            white-space: normal;
        }

        .back-link:focus-visible,
        .logout:focus-visible,
        .status-select:focus-visible,
        .save-button:focus-visible {
            outline: 3px solid var(--primary-dark);
            outline-offset: 3px;
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
                <h1>Kelola Permintaan</h1>
                <p class="heading-copy">Periksa detail permintaan produk dan perbarui statusnya sesuai progres.</p>
            </div>
            <a class="back-link" href="admin.php"><span aria-hidden="true">←</span> Kembali ke Dashboard</a>
        </section>

        <section aria-labelledby="requests-heading">
            <div class="section-heading">
                <h2 id="requests-heading">Daftar permintaan produk</h2>
                <span>Permintaan terbaru ditampilkan lebih dulu</span>
            </div>

            <form method="POST" id="request-status-form">
                <div class="request-actions">
                    <button class="save-button" type="submit" name="ubah_status" hidden>Simpan Perubahan</button>
                </div>
                <div class="table-shell">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Produk</th>
                            <th>Jumlah</th>
                            <th>Tanggal Request</th>
                            <th>Status</th>
                            <th>Ubah Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <?php
                                $status_class = [
                                    'Menunggu' => 'status-waiting',
                                    'Diproses' => 'status-processing',
                                    'Dikirim' => 'status-shipped',
                                    'Selesai' => 'status-complete',
                                    'Dibatalkan' => 'status-cancelled',
                                ][$row['status']] ?? '';
                                ?>
                                <tr>
                                    <td class="request-id"><?= htmlspecialchars($row['id_request'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($row['nama_customer'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($row['nama_produk'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($row['jumlah'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($row['tanggal_request'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="status <?= $status_class; ?>"><?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td>
                                        <select class="status-select" name="status[<?= htmlspecialchars($row['id_request'], ENT_QUOTES, 'UTF-8'); ?>]" data-original-status="<?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Status request <?= htmlspecialchars($row['id_request'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <option value="Menunggu" <?= $row['status'] == 'Menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                                            <option value="Diproses" <?= $row['status'] == 'Diproses' ? 'selected' : ''; ?>>Diproses</option>
                                            <option value="Dikirim" <?= $row['status'] == 'Dikirim' ? 'selected' : ''; ?>>Dikirim</option>
                                            <option value="Selesai" <?= $row['status'] == 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                                            <option value="Dibatalkan" <?= $row['status'] == 'Dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td class="empty-state" colspan="7">Belum ada permintaan produk.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </form>
        </section>
    </main>
    <script>
        <?php if ($data_berhasil_diubah): ?>
            alert(<?= json_encode('Data berhasil diubah!', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        <?php endif; ?>

        const statusForm = document.querySelector('#request-status-form');
        const saveButton = statusForm.querySelector('.save-button');
        const statusSelects = statusForm.querySelectorAll('.status-select');

        const updateSaveButtonVisibility = () => {
            saveButton.hidden = !Array.from(statusSelects).some(
                (select) => select.value !== select.dataset.originalStatus
            );
        };

        statusSelects.forEach((select) => {
            select.addEventListener('change', updateSaveButtonVisibility);
        });
    </script>
</body>
</html>