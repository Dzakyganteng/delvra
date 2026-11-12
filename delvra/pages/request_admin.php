
<?php
session_start();
require_once "../config/database.php";

// Cek login dan role
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    echo "Akses ditolak!";
    exit;
}

// Fungsi keamanan output
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = "";
$error = "";

// Status yang boleh dipilih admin untuk request
$status_list = [
    "Menunggu",
    "Diproses"
];

// Proses CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validasi CSRF
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Token keamanan tidak valid!");
    }

    $aksi = $_POST['aksi'] ?? '';

    try {

        // =========================
        // CREATE REQUEST
        // =========================
        if ($aksi === 'tambah') {

            $id_customer = (int)($_POST['id_customer'] ?? 0);
            $nama_produk = trim($_POST['nama_produk'] ?? '');
            $jumlah = (int)($_POST['jumlah'] ?? 0);
            $tanggal_request = $_POST['tanggal_request'] ?? '';
            $status = $_POST['status'] ?? 'Menunggu';

            if (
                $id_customer <= 0 ||
                $nama_produk === '' ||
                strlen($nama_produk) > 100 ||
                $jumlah <= 0 ||
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_request) ||
                !in_array($status, $status_list, true)
            ) {
                throw new Exception("Data request tidak valid!");
            }

            // Pastikan customer terdaftar
            $cek = mysqli_prepare(
                $conn,
                "SELECT id_user
                 FROM users
                 WHERE id_user = ? AND role = 'customer'"
            );

            mysqli_stmt_bind_param($cek, "i", $id_customer);
            mysqli_stmt_execute($cek);

            $hasil_cek = mysqli_stmt_get_result($cek);

            if (mysqli_num_rows($hasil_cek) === 0) {
                throw new Exception("Customer tidak ditemukan!");
            }

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO requests
                (id_customer, nama_produk, jumlah, tanggal_request, status)
                VALUES (?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "isiss",
                $id_customer,
                $nama_produk,
                $jumlah,
                $tanggal_request,
                $status
            );

            mysqli_stmt_execute($stmt);

            header("Location: request_admin.php?success=tambah");
            exit;
        }

        // =========================
        // MOVE REQUEST TO PROCESSING
        // =========================
        elseif ($aksi === 'proses') {

            $id_request = (int)($_POST['id_request'] ?? 0);

            if (
                $id_request <= 0 ||
                ($_POST['konfirmasi_proses'] ?? '') !== '1'
            ) {
                throw new Exception("Pilih permintaan Menunggu yang ingin diproses.");
            }

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE requests
                 SET status = 'Diproses'
                 WHERE id_request = ?
                 AND status = 'Menunggu'
                 AND id_pengiriman IS NULL"
            );

            mysqli_stmt_bind_param($stmt, "i", $id_request);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception(
                    "Status tidak berubah. Permintaan mungkin sudah diproses atau masuk pengiriman."
                );
            }

            header("Location: request_admin.php?success=proses");
            exit;
        }

        // =========================
        // UPDATE REQUEST
        // =========================
        elseif ($aksi === 'edit') {

            $id_request = (int)($_POST['id_request'] ?? 0);
            $nama_produk = trim($_POST['nama_produk'] ?? '');
            $jumlah = (int)($_POST['jumlah'] ?? 0);
            $tanggal_request = $_POST['tanggal_request'] ?? '';

            if (
                $id_request <= 0 ||
                $nama_produk === '' ||
                strlen($nama_produk) > 100 ||
                $jumlah <= 0 ||
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_request)
            ) {
                throw new Exception("Data request tidak valid!");
            }

            // Ambil kondisi request saat ini
            $cek = mysqli_prepare(
                $conn,
                "SELECT status, id_pengiriman
                 FROM requests
                 WHERE id_request = ?"
            );

            mysqli_stmt_bind_param($cek, "i", $id_request);
            mysqli_stmt_execute($cek);

            $hasil_cek = mysqli_stmt_get_result($cek);
            $request_lama = mysqli_fetch_assoc($hasil_cek);

            if (!$request_lama) {
                throw new Exception("Request tidak ditemukan!");
            }

            // Request yang sudah masuk delivery tidak boleh
            // diubah statusnya secara manual
            if ($request_lama['id_pengiriman'] !== null) {

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE requests
                     SET nama_produk = ?,
                         jumlah = ?,
                         tanggal_request = ?
                     WHERE id_request = ?
                     AND id_pengiriman IS NOT NULL"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "sisi",
                    $nama_produk,
                    $jumlah,
                    $tanggal_request,
                    $id_request
                );

                mysqli_stmt_execute($stmt);

            } else {

                if (
                    !in_array($request_lama['status'], $status_list, true)
                ) {
                    throw new Exception(
                        "Status request ini harus dikelola melalui halaman delivery!"
                    );
                }

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE requests
                     SET nama_produk = ?,
                         jumlah = ?,
                         tanggal_request = ?
                     WHERE id_request = ?
                     AND id_pengiriman IS NULL"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "sisi",
                    $nama_produk,
                    $jumlah,
                    $tanggal_request,
                    $id_request
                );

                mysqli_stmt_execute($stmt);
            }

            header("Location: request_admin.php?success=edit");
            exit;
        }

        // =========================
        // DELETE REQUEST
        // =========================
        elseif ($aksi === 'hapus') {

            $id_request = (int)($_POST['id_request'] ?? 0);

            if ($id_request <= 0) {
                throw new Exception("ID request tidak valid!");
            }

            // Request yang sudah masuk delivery tidak boleh dihapus
            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM requests
                 WHERE id_request = ?
                 AND id_pengiriman IS NULL"
            );

            mysqli_stmt_bind_param($stmt, "i", $id_request);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) === 0) {
                throw new Exception(
                    "Request tidak dapat dihapus karena sudah masuk delivery atau tidak ditemukan!"
                );
            }

            header("Location: request_admin.php?success=hapus");
            exit;
        }

    } catch (Exception $ex) {
        $error = $ex->getMessage();
    }
}

// Pesan berhasil
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'tambah') {
        $pesan = "Request berhasil ditambahkan!";
    } elseif ($_GET['success'] === 'edit') {
        $pesan = "Request berhasil diperbarui!";
    } elseif ($_GET['success'] === 'hapus') {
        $pesan = "Request berhasil dihapus!";
    } elseif ($_GET['success'] === 'proses') {
        $pesan = "Status permintaan berhasil diubah menjadi Diproses.";
    }
}

// Ambil seluruh customer
$query_customer = mysqli_query(
    $conn,
    "SELECT id_user, nama
     FROM users
     WHERE role = 'customer'
     ORDER BY nama ASC"
);

$customers = [];

while ($customer = mysqli_fetch_assoc($query_customer)) {
    $customers[] = $customer;
}

// Ambil seluruh request
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        r.id_request,
        r.id_customer,
        r.nama_produk,
        r.jumlah,
        r.tanggal_request,
        r.status,
        r.id_pengiriman,
        u.nama AS nama_customer
     FROM requests r
     JOIN users u ON r.id_customer = u.id_user
     ORDER BY r.tanggal_request DESC,
              u.nama ASC,
              r.id_request DESC"
);

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Kelompokkan berdasarkan tanggal dan customer
$requests_by_date = [];
$jumlah_request = 0;
$jumlah_menunggu = 0;
$jumlah_diproses = 0;
$jumlah_dalam_pengiriman = 0;

while ($data = mysqli_fetch_assoc($result)) {

    $jumlah_request++;
    if ($data['status'] === 'Menunggu') {
        $jumlah_menunggu++;
    } elseif ($data['status'] === 'Diproses') {
        $jumlah_diproses++;
    }
    if ($data['id_pengiriman'] !== null) {
        $jumlah_dalam_pengiriman++;
    }

    $tanggal = $data['tanggal_request'];
    $id_customer = $data['id_customer'];

    if (!isset($requests_by_date[$tanggal])) {
        $requests_by_date[$tanggal] = [];
    }

    if (!isset($requests_by_date[$tanggal][$id_customer])) {
        $requests_by_date[$tanggal][$id_customer] = [
            'nama_customer' => $data['nama_customer'],
            'requests' => []
        ];
    }

    $requests_by_date[$tanggal][$id_customer]['requests'][] = $data;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Customer - Admin Delvra</title>
    <link rel="stylesheet" href="css/operations.css">
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
</head>
<body class="ops-page ops-request-admin">
    <?php include __DIR__ . '/includes/navbar_admin.php'; ?>

    <main class="ops-main">
        <div class="ops-heading">
            <p class="ops-eyebrow">Admin</p>
            <h1>Permintaan pelanggan</h1>
            <p>Tambahkan permintaan atas nama pelanggan, tinjau status, dan perbarui detail yang belum masuk ke pengiriman.</p>
        </div>

        <div class="ops-summary request-summary">
            <div class="ops-summary-item"><strong><?php echo $jumlah_request; ?></strong><span>Total permintaan</span></div>
            <div class="ops-summary-item"><strong><?php echo $jumlah_menunggu; ?></strong><span>Menunggu</span></div>
            <div class="ops-summary-item"><strong><?php echo $jumlah_diproses; ?></strong><span>Diproses</span></div>
            <div class="ops-summary-item"><strong><?php echo $jumlah_dalam_pengiriman; ?></strong><span>Sudah masuk pengiriman</span></div>
        </div>

    <?php if ($pesan !== ''): ?>
        <div class="ops-alert success" role="status"><?php echo e($pesan); ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="ops-alert error" role="alert"><?php echo e($error); ?></div>
    <?php endif; ?>

    <!-- FORM TAMBAH REQUEST -->
    <section class="ops-section" aria-labelledby="create-request-title">
        <div class="ops-section-title">
            <div>
                <h2 id="create-request-title">Tambah permintaan</h2>
                <p>Isi informasi pelanggan dan produk yang diminta.</p>
            </div>
        </div>

    <form method="POST" class="ops-panel ops-form request-create-form">

        <input type="hidden" name="csrf_token"
               value="<?php echo e($_SESSION['csrf_token']); ?>">

        <input type="hidden" name="aksi" value="tambah">

        <div class="request-create-grid">
            <label>
                <span>Pelanggan</span>
                <select name="id_customer" required>
                    <option value="">Pilih pelanggan</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo e($customer['id_user']); ?>">
                            <?php echo e($customer['nama']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Nama produk</span>
                <input type="text" name="nama_produk" maxlength="100" required>
            </label>

            <label>
                <span>Jumlah</span>
                <input type="number" name="jumlah" min="1" required>
            </label>

            <label>
                <span>Tanggal permintaan</span>
                <input type="date" name="tanggal_request" value="<?php echo date('Y-m-d'); ?>" required>
            </label>

            <label>
                <span>Status awal</span>
                <select name="status" required>
                    <option value="Menunggu">Menunggu</option>
                    <option value="Diproses">Diproses</option>
                </select>
            </label>
        </div>

        <div class="request-create-footer">
            <p>Pastikan pelanggan, produk, jumlah, dan tanggal sudah sesuai.</p>
            <button type="submit">Tambah permintaan</button>
        </div>

    </form>
    </section>

    <!-- DAFTAR REQUEST -->
    <section class="ops-section" aria-labelledby="request-list-title">
        <div class="ops-section-title">
            <div>
                <h2 id="request-list-title">Daftar permintaan</h2>
                <p>Permintaan diurutkan berdasarkan tanggal, lalu dikelompokkan menurut pelanggan.</p>
            </div>
        </div>

    <?php if (empty($requests_by_date)): ?>

        <div class="ops-empty">Belum ada permintaan dari pelanggan.</div>

    <?php else: ?>

        <?php foreach ($requests_by_date as $tanggal => $customer_groups): ?>

            <section class="request-date-group">
                <h3 class="request-date-heading">Permintaan tanggal <time datetime="<?php echo e($tanggal); ?>"><?php echo date('d-m-Y', strtotime($tanggal)); ?></time></h3>

            <?php foreach ($customer_groups as $id_customer => $group): ?>

                <div class="request-customer-group">
                    <h4><?php echo e($group['nama_customer']); ?></h4>
                    <div class="request-row request-column-heading" aria-hidden="true">
                        <span>Permintaan</span>
                        <span>Jumlah</span>
                        <span>Status</span>
                        <span>Pengiriman</span>
                        <span>Perbarui status</span>
                        <span>Aksi</span>
                    </div>

                    <?php foreach ($group['requests'] as $request): ?>

                        <?php
                        $sudah_masuk_delivery =
                            $request['id_pengiriman'] !== null;
                        ?>

                        <?php
                        $status_class = [
                            'Menunggu' => 'waiting',
                            'Diproses' => 'processing',
                            'Dikirim' => 'shipping',
                            'Selesai' => 'complete',
                            'Dibatalkan' => 'cancelled',
                        ][$request['status']] ?? '';
                        ?>

                        <article class="request-row">
                            <div class="request-main">
                                <span class="request-id">#<?php echo e($request['id_request']); ?></span>
                                <strong><?php echo e($request['nama_produk']); ?></strong>
                            </div>
                            <span class="request-quantity">Jumlah: <?php echo e($request['jumlah']); ?></span>
                            <span class="ops-status <?php echo e($status_class); ?>"><?php echo e($request['status']); ?></span>
                            <span class="request-delivery">
                                <?php if ($sudah_masuk_delivery): ?>
                                    Pengiriman #<?php echo e($request['id_pengiriman']); ?>
                                <?php else: ?>
                                    Belum masuk pengiriman
                                <?php endif; ?>
                            </span>
                            <div class="request-status-cell">
                                <?php if (!$sudah_masuk_delivery && $request['status'] === 'Menunggu'): ?>
                                    <?php $promote_button_id = 'save-request-status-' . $request['id_request']; ?>
                                    <form method="POST" class="request-status-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="aksi" value="proses">
                                        <input type="hidden" name="id_request" value="<?php echo e($request['id_request']); ?>">
                                        <label class="request-promote-choice">
                                            <input
                                                type="checkbox"
                                                name="konfirmasi_proses"
                                                value="1"
                                                required
                                                data-reveal-target="<?php echo e($promote_button_id); ?>"
                                            >
                                            <span>Ubah ke Diproses</span>
                                        </label>
                                        <button id="<?php echo e($promote_button_id); ?>" class="request-status-save" type="submit" hidden>
                                            Simpan perubahan
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="request-status-empty" aria-hidden="true">—</span>
                                <?php endif; ?>
                            </div>
                            <button
                                class="ops-action-button"
                                type="button"
                                data-open-dialog="request-actions-<?php echo e($request['id_request']); ?>"
                                aria-haspopup="dialog"
                            >
                                Edit
                            </button>

                            <dialog class="ops-action-dialog" id="request-actions-<?php echo e($request['id_request']); ?>" aria-labelledby="request-actions-title-<?php echo e($request['id_request']); ?>">
                                <div class="ops-dialog-header">
                                    <div>
                                        <p class="ops-dialog-eyebrow">Kelola permintaan</p>
                                        <h2 id="request-actions-title-<?php echo e($request['id_request']); ?>">
                                            Permintaan #<?php echo e($request['id_request']); ?>
                                        </h2>
                                    </div>
                                    <button class="ops-dialog-close" type="button" data-close-dialog aria-label="Tutup popup">&times;</button>
                                </div>

                                <form method="POST" class="ops-dialog-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="aksi" value="edit">
                                    <input type="hidden" name="id_request" value="<?php echo e($request['id_request']); ?>">

                                    <label>
                                        Nama produk
                                        <input type="text" name="nama_produk" value="<?php echo e($request['nama_produk']); ?>" maxlength="100" required>
                                    </label>

                                    <div class="request-dialog-fields">
                                        <label>
                                            Jumlah
                                            <input type="number" name="jumlah" value="<?php echo e($request['jumlah']); ?>" min="1" required>
                                        </label>
                                        <label>
                                            Tanggal permintaan
                                            <input type="date" name="tanggal_request" value="<?php echo e($request['tanggal_request']); ?>" required>
                                        </label>
                                    </div>

                                    <div class="ops-dialog-actions">
                                        <button class="ops-dialog-save" type="submit">Simpan perubahan</button>
                                    </div>
                                </form>

                                <div class="ops-dialog-divider"></div>
                                <div class="ops-delete-section">
                                    <div>
                                        <strong>Hapus permintaan</strong>
                                        <p>
                                            <?php if ($sudah_masuk_delivery): ?>
                                                Permintaan yang sudah masuk pengiriman tidak dapat dihapus.
                                            <?php else: ?>
                                                Permintaan ini akan dihapus permanen.
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <?php if (!$sudah_masuk_delivery): ?>
                                        <form method="POST" onsubmit="return confirm('Yakin ingin menghapus permintaan ini?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="aksi" value="hapus">
                                            <input type="hidden" name="id_request" value="<?php echo e($request['id_request']); ?>">
                                            <button class="ops-dialog-delete" type="submit">Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </dialog>
                        </article>

                    <?php endforeach; ?>

                </div>
            <?php endforeach; ?>
            </section>

        <?php endforeach; ?>

    <?php endif; ?>
    </section>

    <script>
        document.querySelectorAll('[data-reveal-target]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                const button = document.getElementById(checkbox.dataset.revealTarget);
                if (button) {
                    button.hidden = !checkbox.checked;
                }
            });
        });

        document.querySelectorAll('[data-open-dialog]').forEach((button) => {
            button.addEventListener('click', () => {
                const dialog = document.getElementById(button.dataset.openDialog);
                if (dialog) {
                    dialog.showModal();
                }
            });
        });

        document.querySelectorAll('[data-close-dialog]').forEach((button) => {
            button.addEventListener('click', () => {
                button.closest('dialog').close();
            });
        });

        document.querySelectorAll('.ops-action-dialog').forEach((dialog) => {
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });
        });
    </script>

    </main>
</body>
</html>
