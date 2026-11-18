<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$activeSection = in_array($currentPage, ['request_admin.php', 'kelola_request.php'], true)
    ? 'requests'
    : (in_array($currentPage, ['delivery_admin.php', 'kelola_pengiriman.php'], true) ? 'deliveries' : 'dashboard');
?>
<header class="app-navbar">
    <a class="app-navbar-brand" href="admin.php" aria-label="Delvra, dashboard admin">
        <img src="../assets/logo/logo1.png" alt="">
        <span>PT Intra Presisi Indonesia</span>
    </a>
    <nav class="app-navbar-links" aria-label="Navigasi admin">
        <a href="admin.php" <?php echo $activeSection === 'dashboard' ? 'aria-current="page"' : ''; ?>>Dashboard</a>
        <a href="request_admin.php" <?php echo $activeSection === 'requests' ? 'aria-current="page"' : ''; ?>>Permintaan</a>
        <a href="delivery_admin.php" <?php echo $activeSection === 'deliveries' ? 'aria-current="page"' : ''; ?>>Pengiriman</a>
    </nav>
    <div class="app-navbar-account">
        <span>Admin: <strong><?php echo htmlspecialchars((string)($_SESSION['nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></span>
        <a class="app-navbar-logout" href="logout.php">Keluar</a>
    </div>
</header>