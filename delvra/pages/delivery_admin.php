
<?php
session_start();
require_once "../config/database.php";

// Cek login dan role
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] != 'admin') {
    echo "Akses ditolak!";
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = "";
$error = "";

$status_list = ["Dikirim", "Selesai"];

// Validasi tanggal
function tanggalValid($tanggal) {
    if (!is_string($tanggal)) {
        return false;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $tanggal);

    return $date && $date->format('Y-m-d') === $tanggal;
}


// ========================================
// PROSES CRUD
// ========================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Token keamanan tidak valid!");
    }

    $aksi = $_POST['aksi'] ?? '';

    try {

        // ====================================
        // CREATE DELIVERY
        // ====================================

        if ($aksi == 'tambah') {

            $id_deliverer = (int)($_POST['id_deliverer'] ?? 0);
            $tanggal_kirim = $_POST['tanggal_kirim'] ?? '';
            $tanggal_selesai = $_POST['tanggal_selesai'] ?? '';
            $status = $_POST['status_pengiriman'] ?? '';
            $id_requests = $_POST['id_requests'] ?? [];

            if (
                $id_deliverer <= 0 ||
                !tanggalValid($tanggal_kirim) ||
                !is_string($status) ||
                !in_array($status, $status_list, true) ||
                !is_array($id_requests) ||
                empty($id_requests)
            ) {
                throw new Exception("Data delivery tidak valid!");
            }

            if ($status == 'Selesai') {

                if (!tanggalValid($tanggal_selesai)) {
                    throw new Exception("Tanggal selesai wajib diisi!");
                }

            } else {
                $tanggal_selesai = null;
            }

            // Bersihkan ID request
            $id_requests = array_values(
                array_unique(
                    array_map('intval', $id_requests)
                )
            );

            if (in_array(0, $id_requests, true) || in_array(-1, $id_requests, true)) {
                throw new Exception("Pilihan request tidak valid!");
            }

            foreach ($id_requests as $id_request) {
                if ($id_request <= 0) {
                    throw new Exception("Pilihan request tidak valid!");
                }
            }

            mysqli_begin_transaction($conn);


            // Validasi Deliverer
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_user
                 FROM users
                 WHERE id_user = ?
                 AND role = 'deliverer'
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param($stmt, "i", $id_deliverer);
            mysqli_stmt_execute($stmt);

            $deliverer_result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($deliverer_result) == 0) {
                throw new Exception("Deliverer tidak ditemukan!");
            }

            mysqli_stmt_close($stmt);


            // Kunci dan periksa request yang dipilih
            $placeholders = implode(',', array_fill(0, count($id_requests), '?'));
            $types = str_repeat('i', count($id_requests));

            $sql = "
                SELECT
                    id_request,
                    id_customer,
                    tanggal_request,
                    status,
                    id_pengiriman
                FROM requests
                WHERE id_request IN ($placeholders)
                FOR UPDATE
            ";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param($stmt, $types, ...$id_requests);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            $selected = [];

            while ($row = mysqli_fetch_assoc($result)) {
                $selected[] = $row;
            }

            mysqli_stmt_close($stmt);


            if (count($selected) != count($id_requests)) {
                throw new Exception("Ada request yang tidak ditemukan!");
            }


            // Validasi customer, tanggal, dan status request
            $customer_id = (int)$selected[0]['id_customer'];
            $tanggal_request = $selected[0]['tanggal_request'];

            foreach ($selected as $request) {

                if ((int)$request['id_customer'] != $customer_id) {
                    throw new Exception(
                        "Request harus berasal dari customer yang sama!"
                    );
                }

                if ($request['tanggal_request'] != $tanggal_request) {
                    throw new Exception(
                        "Request harus memiliki tanggal request yang sama!"
                    );
                }

                if ($request['status'] != 'Diproses') {
                    throw new Exception(
                        "Hanya request berstatus Diproses yang dapat dimasukkan!"
                    );
                }

                if ($request['id_pengiriman'] !== null) {
                    throw new Exception(
                        "Ada request yang sudah masuk delivery lain!"
                    );
                }
            }


            // Buat delivery
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO pengiriman
                    (id_deliverer, tanggal_kirim, status_pengiriman, tanggal_selesai)
                 VALUES (?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "isss",
                $id_deliverer,
                $tanggal_kirim,
                $status,
                $tanggal_selesai
            );

            mysqli_stmt_execute($stmt);

            $id_pengiriman = mysqli_insert_id($conn);

            mysqli_stmt_close($stmt);


            // Hubungkan request ke delivery
            $stmt_update = mysqli_prepare(
                $conn,
                "UPDATE requests
                 SET id_pengiriman = ?,
                     status = ?
                 WHERE id_request = ?
                 AND id_pengiriman IS NULL
                 AND status = 'Diproses'"
            );

            foreach ($id_requests as $id_request) {

                mysqli_stmt_bind_param(
                    $stmt_update,
                    "isi",
                    $id_pengiriman,
                    $status,
                    $id_request
                );

                if (
                    !mysqli_stmt_execute($stmt_update) ||
                    mysqli_stmt_affected_rows($stmt_update) != 1
                ) {
                    throw new Exception(
                        "Gagal menghubungkan request ke delivery!"
                    );
                }
            }

            mysqli_stmt_close($stmt_update);


            mysqli_commit($conn);

            header("Location: delivery_admin.php?success=tambah");
            exit;
        }


        // ====================================
        // UPDATE DELIVERY
        // ====================================

        elseif ($aksi == 'edit') {

            $id_pengiriman = (int)($_POST['id_pengiriman'] ?? 0);
            $tanggal_kirim = $_POST['tanggal_kirim'] ?? '';
            $tanggal_selesai = $_POST['tanggal_selesai'] ?? '';
            $status = $_POST['status_pengiriman'] ?? '';

            if (
                $id_pengiriman <= 0 ||
                !tanggalValid($tanggal_kirim) ||
                !is_string($status) ||
                !in_array($status, $status_list, true)
            ) {
                throw new Exception("Data delivery tidak valid!");
            }

            if ($status == 'Selesai') {

                if (!tanggalValid($tanggal_selesai)) {
                    throw new Exception("Tanggal selesai wajib diisi!");
                }

            } else {
                $tanggal_selesai = null;
            }

            mysqli_begin_transaction($conn);


            // Kunci delivery
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_pengiriman
                 FROM pengiriman
                 WHERE id_pengiriman = ?
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param($stmt, "i", $id_pengiriman);
            mysqli_stmt_execute($stmt);

            $delivery_result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($delivery_result) == 0) {
                throw new Exception("Delivery tidak ditemukan!");
            }

            mysqli_stmt_close($stmt);


            // Kunci seluruh request dalam delivery
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_request
                 FROM requests
                 WHERE id_pengiriman = ?
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param($stmt, "i", $id_pengiriman);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            $id_requests = [];

            while ($row = mysqli_fetch_assoc($result)) {
                $id_requests[] = (int)$row['id_request'];
            }

            mysqli_stmt_close($stmt);


            if (empty($id_requests)) {
                throw new Exception("Delivery tidak memiliki request!");
            }


            // Update data delivery
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE pengiriman
                 SET tanggal_kirim = ?,
                     status_pengiriman = ?,
                     tanggal_selesai = ?
                 WHERE id_pengiriman = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sssi",
                $tanggal_kirim,
                $status,
                $tanggal_selesai,
                $id_pengiriman
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);


            // Sinkronisasi status seluruh request
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE requests
                 SET status = ?
                 WHERE id_pengiriman = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "si",
                $status,
                $id_pengiriman
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);


            mysqli_commit($conn);

            header("Location: delivery_admin.php?success=edit");
            exit;
        }


        // ====================================
        // DELETE DELIVERY
        // ====================================

        elseif ($aksi == 'hapus') {

            $id_pengiriman = (int)($_POST['id_pengiriman'] ?? 0);

            if ($id_pengiriman <= 0) {
                throw new Exception("ID delivery tidak valid!");
            }

            mysqli_begin_transaction($conn);


            // Kunci delivery
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_pengiriman
                 FROM pengiriman
                 WHERE id_pengiriman = ?
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param($stmt, "i", $id_pengiriman);
            mysqli_stmt_execute($stmt);

            $delivery_result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($delivery_result) == 0) {
                throw new Exception("Delivery tidak ditemukan!");
            }

            mysqli_stmt_close($stmt);


            // Kunci request dalam delivery
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_request
                 FROM requests
                 WHERE id_pengiriman = ?
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param($stmt, "i", $id_pengiriman);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            $id_requests = [];

            while ($row = mysqli_fetch_assoc($result)) {
                $id_requests[] = (int)$row['id_request'];
            }

            mysqli_stmt_close($stmt);


            // Lepaskan request dari delivery dan kembalikan statusnya
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE requests
                 SET id_pengiriman = NULL,
                     status = 'Diproses'
                 WHERE id_pengiriman = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $id_pengiriman
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);


            // Hapus delivery
            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM pengiriman
                 WHERE id_pengiriman = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $id_pengiriman
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);


            mysqli_commit($conn);

            header("Location: delivery_admin.php?success=hapus");
            exit;
        }

        else {
            throw new Exception("Aksi tidak dikenal!");
        }

    } catch (Throwable $ex) {

        mysqli_rollback($conn);
        $error = $ex->getMessage();
    }
}


// ========================================
// PESAN
// ========================================

if (isset($_GET['success'])) {

    if ($_GET['success'] == 'tambah') {
        $pesan = "Delivery berhasil ditambahkan!";
    } elseif ($_GET['success'] == 'edit') {
        $pesan = "Delivery berhasil diperbarui!";
    } elseif ($_GET['success'] == 'hapus') {
        $pesan = "Delivery berhasil dihapus!";
    }
}


// ========================================
// DATA DELIVERER
// ========================================

$query_deliverer = mysqli_query(
    $conn,
    "SELECT id_user, nama
     FROM users
     WHERE role = 'deliverer'
     ORDER BY nama ASC"
);

$deliverers = [];

while ($row = mysqli_fetch_assoc($query_deliverer)) {
    $deliverers[] = $row;
}


// ========================================
// REQUEST YANG BELUM MASUK DELIVERY
// ========================================

$query_request = mysqli_query(
    $conn,
    "SELECT
        r.id_request,
        r.id_customer,
        r.nama_produk,
        r.jumlah,
        r.tanggal_request,
        u.nama AS nama_customer
     FROM requests r
     JOIN users u ON r.id_customer = u.id_user
     WHERE r.status = 'Diproses'
       AND r.id_pengiriman IS NULL
     ORDER BY r.tanggal_request DESC, u.nama ASC"
);

$available_requests = [];

while ($row = mysqli_fetch_assoc($query_request)) {
    $available_requests[] = $row;
}


// ========================================
// DATA DELIVERY
// ========================================

$query_delivery = mysqli_query(
    $conn,
    "SELECT
        p.id_pengiriman,
        p.id_deliverer,
        p.tanggal_kirim,
        p.status_pengiriman,
        p.tanggal_selesai,
        u.nama AS nama_deliverer,
        COUNT(DISTINCT r.id_request) AS total_request,
        MIN(r.id_customer) AS id_customer,
        MIN(c.nama) AS nama_customer
     FROM pengiriman p
     JOIN users u ON p.id_deliverer = u.id_user
     LEFT JOIN requests r ON p.id_pengiriman = r.id_pengiriman
     LEFT JOIN users c ON r.id_customer = c.id_user
     GROUP BY
        p.id_pengiriman,
        p.id_deliverer,
        p.tanggal_kirim,
        p.status_pengiriman,
        p.tanggal_selesai,
        u.nama
     ORDER BY p.tanggal_kirim DESC, p.id_pengiriman DESC"
);

$deliveries_by_date = [];

while ($row = mysqli_fetch_assoc($query_delivery)) {

    $tanggal = $row['tanggal_kirim'];
    $group_customer_id = $row['id_customer'] ?? 'tanpa_customer';

    if (!isset($deliveries_by_date[$tanggal])) {
        $deliveries_by_date[$tanggal] = [];
    }

    if (!isset($deliveries_by_date[$tanggal][$group_customer_id])) {
        $deliveries_by_date[$tanggal][$group_customer_id] = [
            'nama_customer' => $row['nama_customer'] ?? 'Tidak ada customer',
            'deliveries' => []
        ];
    }

    $deliveries_by_date[$tanggal][$group_customer_id]['deliveries'][] = $row;
}

$jumlah_delivery = 0;
$jumlah_delivery_selesai = 0;
foreach ($deliveries_by_date as $customer_groups) {
    foreach ($customer_groups as $group) {
        foreach ($group['deliveries'] as $delivery) {
            $jumlah_delivery++;
            if ($delivery['status_pengiriman'] === 'Selesai') {
                $jumlah_delivery_selesai++;
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Delivery - Admin Delvra</title>
    <link rel="stylesheet" href="css/operations.css?v=2">
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
</head>

<body class="ops-page ops-delivery-admin">
    <?php include __DIR__ . '/includes/navbar_admin.php'; ?>

    <main class="ops-main">
        <div class="ops-heading">
            <p class="ops-eyebrow">Admin</p>
            <h1>Pengiriman</h1>
            <p>Buat pengiriman dari permintaan yang sudah diproses, tetapkan kurir, dan perbarui status pengiriman.</p>
        </div>

        <div class="ops-summary delivery-summary">
            <div class="ops-summary-item"><strong><?php echo $jumlah_delivery; ?></strong><span>Total pengiriman</span></div>
            <div class="ops-summary-item"><strong><?php echo $jumlah_delivery_selesai; ?></strong><span>Pengiriman selesai</span></div>
            <div class="ops-summary-item"><strong><?php echo count($available_requests); ?></strong><span>Permintaan siap dikirim</span></div>
        </div>

    <?php if ($pesan != ''): ?>
        <div class="ops-alert success" role="status"><?php echo e($pesan); ?></div>
    <?php endif; ?>

    <?php if ($error != ''): ?>
        <div class="ops-alert error" role="alert"><?php echo e($error); ?></div>
    <?php endif; ?>


    <!-- ================================= -->
    <!-- FORM TAMBAH DELIVERY -->
    <!-- ================================= -->

    <section class="ops-section" aria-labelledby="create-delivery-title">
        <div class="ops-section-title">
            <div>
                <h2 id="create-delivery-title">Buat pengiriman baru</h2>
                <p>Pilih kurir, tanggal, status, lalu permintaan dari pelanggan dan tanggal yang sama.</p>
            </div>
        </div>

    <?php if (empty($available_requests)): ?>

        <div class="ops-empty">Belum ada permintaan berstatus Diproses yang siap dimasukkan ke pengiriman.</div>

    <?php else: ?>

        <form method="POST" class="ops-panel ops-create-form delivery-create-form">

            <input type="hidden" name="csrf_token"
                   value="<?php echo e($_SESSION['csrf_token']); ?>">

            <input type="hidden" name="aksi" value="tambah">

            <div class="delivery-form-grid">
                <label>
                    <span>Kurir</span>
                    <select name="id_deliverer" required>
                        <option value="">Pilih kurir</option>
                        <?php foreach ($deliverers as $deliverer): ?>
                            <option value="<?php echo e($deliverer['id_user']); ?>">
                                <?php echo e($deliverer['nama']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Tanggal kirim</span>
                    <input type="date" name="tanggal_kirim"
                           value="<?php echo date('Y-m-d'); ?>" required>
                </label>

                <label>
                    <span>Status pengiriman</span>
                    <select name="status_pengiriman" id="status_tambah"
                            onchange="aturTanggalSelesai()" required>
                        <option value="Dikirim">Dikirim</option>
                        <option value="Selesai">Selesai</option>
                    </select>
                </label>
            </div>

            <div id="tanggal_selesai_tambah" class="delivery-completion-field" style="display:none;">
                <label>
                    <span>Tanggal selesai</span>
                    <input type="date" name="tanggal_selesai" id="input_tanggal_selesai">
                </label>
            </div>

            <div class="delivery-requests-heading">
                <h3>Pilih permintaan</h3>
                <p>Pilih permintaan dari pelanggan dan tanggal pengajuan yang sama untuk digabung dalam satu pengiriman.</p>
            </div>

            <?php
            $request_groups = [];

            foreach ($available_requests as $request) {

                $key = $request['id_customer'] . '_' . $request['tanggal_request'];

                if (!isset($request_groups[$key])) {
                    $request_groups[$key] = [
                        'nama_customer' => $request['nama_customer'],
                        'tanggal_request' => $request['tanggal_request'],
                        'requests' => []
                    ];
                }

                $request_groups[$key]['requests'][] = $request;
            }
            ?>

            <?php foreach ($request_groups as $group): ?>

                <fieldset class="delivery-request-group">
                    <legend>
                        <?php echo e($group['nama_customer']); ?>
                        <span>Tanggal permintaan <?php echo date('d-m-Y', strtotime($group['tanggal_request'])); ?></span>
                    </legend>

                <?php foreach ($group['requests'] as $request): ?>

                    <label class="ops-choice">
                        <input type="checkbox" name="id_requests[]"
                               value="<?php echo e($request['id_request']); ?>">
                        <span class="delivery-request-copy">
                            <strong><?php echo e($request['nama_produk']); ?></strong>
                            <small>ID permintaan #<?php echo e($request['id_request']); ?> · Jumlah <?php echo e($request['jumlah']); ?></small>
                        </span>
                    </label>

                <?php endforeach; ?>
                </fieldset>

            <?php endforeach; ?>

            <div class="delivery-form-footer">
                <p>Pastikan kurir dan permintaan sudah benar sebelum membuat pengiriman.</p>
                <button type="submit">Buat pengiriman</button>
            </div>

        </form>

    <?php endif; ?>

    </section>


    <!-- ================================= -->
    <!-- DAFTAR DELIVERY -->
    <!-- ================================= -->

    <section class="ops-section" aria-labelledby="delivery-list-title">
        <div class="ops-section-title">
            <div>
                <h2 id="delivery-list-title">Daftar pengiriman</h2>
                <p>Setiap tanggal berisi daftar pengiriman, dan setiap pengiriman menampilkan permintaan yang dibawanya.</p>
            </div>
        </div>

    <?php if (empty($deliveries_by_date)): ?>

        <div class="ops-empty">Belum ada data pengiriman.</div>

    <?php else: ?>

        <?php foreach ($deliveries_by_date as $tanggal => $customer_groups): ?>

            <section class="delivery-date-group">
            <h3 class="delivery-date-heading">Pengiriman tanggal <time datetime="<?php echo e($tanggal); ?>"><?php echo date('d-m-Y', strtotime($tanggal)); ?></time></h3>

            <?php foreach ($customer_groups as $group): ?>
                <?php foreach ($group['deliveries'] as $delivery): ?>
                    <article class="delivery-card" aria-labelledby="delivery-title-<?php echo e($delivery['id_pengiriman']); ?>">
                        <header class="delivery-card-header">
                            <div>
                                <p class="delivery-card-kicker">Pengiriman</p>
                                <h4 id="delivery-title-<?php echo e($delivery['id_pengiriman']); ?>">#<?php echo e($delivery['id_pengiriman']); ?></h4>
                                <p class="delivery-customer-name">Pelanggan: <?php echo e($group['nama_customer']); ?></p>
                            </div>
                            <button
                                class="ops-action-button"
                                type="button"
                                data-open-dialog="delivery-actions-<?php echo e($delivery['id_pengiriman']); ?>"
                                aria-haspopup="dialog"
                            >
                                Aksi
                            </button>

                            <dialog class="ops-action-dialog" id="delivery-actions-<?php echo e($delivery['id_pengiriman']); ?>" aria-labelledby="delivery-actions-title-<?php echo e($delivery['id_pengiriman']); ?>">
                                <div class="ops-dialog-header">
                                    <div>
                                        <p class="ops-dialog-eyebrow">Kelola pengiriman</p>
                                        <h2 id="delivery-actions-title-<?php echo e($delivery['id_pengiriman']); ?>">
                                            Pengiriman #<?php echo e($delivery['id_pengiriman']); ?>
                                        </h2>
                                    </div>
                                    <button class="ops-dialog-close" type="button" data-close-dialog aria-label="Tutup popup">&times;</button>
                                </div>

                                <form method="POST" class="ops-dialog-form ops-edit-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="aksi" value="edit">
                                    <input type="hidden" name="id_pengiriman" value="<?php echo e($delivery['id_pengiriman']); ?>">

                                    <label>
                                        Tanggal kirim
                                        <input type="date" name="tanggal_kirim" value="<?php echo e($delivery['tanggal_kirim']); ?>" required>
                                    </label>

                                    <label>
                                        Status pengiriman
                                        <select name="status_pengiriman" onchange="aturTanggalEdit(this)" required>
                                            <option value="Dikirim" <?php echo $delivery['status_pengiriman'] == 'Dikirim' ? 'selected' : ''; ?>>Dikirim</option>
                                            <option value="Selesai" <?php echo $delivery['status_pengiriman'] == 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                                        </select>
                                    </label>

                                    <label>
                                        Tanggal selesai
                                        <input
                                            type="date"
                                            name="tanggal_selesai"
                                            value="<?php echo e($delivery['tanggal_selesai'] ?? ''); ?>"
                                            class="tanggal-selesai-edit"
                                            <?php echo $delivery['status_pengiriman'] == 'Selesai' ? 'required' : ''; ?>
                                        >
                                    </label>

                                    <div class="ops-dialog-actions">
                                        <button class="ops-dialog-save" type="submit">Simpan perubahan</button>
                                    </div>
                                </form>

                                <div class="ops-dialog-divider"></div>
                                <div class="ops-delete-section">
                                    <div>
                                        <strong>Hapus pengiriman</strong>
                                        <p>Permintaan di dalamnya akan dikembalikan ke status Diproses.</p>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Yakin ingin menghapus pengiriman ini? Request di dalamnya akan dikembalikan ke status Diproses.')">
                                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="aksi" value="hapus">
                                        <input type="hidden" name="id_pengiriman" value="<?php echo e($delivery['id_pengiriman']); ?>">
                                        <button class="ops-dialog-delete" type="submit">Hapus</button>
                                    </form>
                                </div>
                            </dialog>
                        </header>

                        <dl class="delivery-card-meta">
                            <div>
                                <dt>Kurir</dt>
                                <dd><?php echo e($delivery['nama_deliverer']); ?></dd>
                            </div>
                            <div>
                                <dt>Status</dt>
                                <dd>
                                    <?php $delivery_status_class = $delivery['status_pengiriman'] === 'Selesai' ? 'complete' : 'shipping'; ?>
                                    <span class="ops-status <?php echo $delivery_status_class; ?>"><?php echo e($delivery['status_pengiriman']); ?></span>
                                </dd>
                            </div>
                            <div>
                                <dt>Tanggal selesai</dt>
                                <dd><?php echo $delivery['tanggal_selesai'] ? date('d-m-Y', strtotime($delivery['tanggal_selesai'])) : 'Belum selesai'; ?></dd>
                            </div>
                            <div>
                                <dt>Jumlah permintaan</dt>
                                <dd><?php echo e($delivery['total_request']); ?></dd>
                            </div>
                        </dl>

                        <div class="delivery-requests">
                            <h5>Permintaan dalam pengiriman ini</h5>

                    <?php

                    $stmt_detail = mysqli_prepare(
                        $conn,
                        "SELECT
                            r.id_request,
                            r.nama_produk,
                            r.jumlah,
                            r.status,
                            u.nama AS nama_customer
                         FROM requests r
                         JOIN users u ON r.id_customer = u.id_user
                         WHERE r.id_pengiriman = ?
                         ORDER BY r.id_request ASC"
                    );

                    mysqli_stmt_bind_param(
                        $stmt_detail,
                        "i",
                        $delivery['id_pengiriman']
                    );

                    mysqli_stmt_execute($stmt_detail);

                    $detail_result = mysqli_stmt_get_result($stmt_detail);

                    ?>

                        <?php while ($detail = mysqli_fetch_assoc($detail_result)): ?>
                            <div class="delivery-request-item">
                                <div>
                                    <strong><?php echo e($detail['nama_produk']); ?></strong>
                                    <span>ID permintaan #<?php echo e($detail['id_request']); ?></span>
                                </div>
                                <span class="delivery-request-quantity">Jumlah: <?php echo e($detail['jumlah']); ?></span>
                                <span class="ops-status <?php echo strtolower($detail['status']) === 'diproses' ? 'processing' : ''; ?>"><?php echo e($detail['status']); ?></span>
                            </div>
                        <?php endwhile; ?>

                    <?php mysqli_stmt_close($stmt_detail); ?>

                        </div>
                    </article>

                <?php endforeach; ?>

            <?php endforeach; ?>

        <?php endforeach; ?>

    <?php endif; ?>
    </section>


    <script>

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

        // Form tambah
        function aturTanggalSelesai() {

            const status = document.getElementById('status_tambah').value;
            const area = document.getElementById('tanggal_selesai_tambah');
            const input = document.getElementById('input_tanggal_selesai');

            if (status === 'Selesai') {
                area.style.display = 'block';
                input.required = true;
            } else {
                area.style.display = 'none';
                input.required = false;
                input.value = '';
            }
        }

        // Form edit
        function aturTanggalEdit(select) {

            const form = select.closest('form');
            const input = form.querySelector('.tanggal-selesai-edit');

            if (select.value === 'Selesai') {
                input.required = true;
            } else {
                input.required = false;
                input.value = '';
            }
        }

    </script>

</main>
</body>
</html>
