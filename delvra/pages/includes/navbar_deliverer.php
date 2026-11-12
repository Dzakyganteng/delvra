<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$activeSection = $currentPage === 'request_deliverer.php'
    ? 'requests'
    : ($currentPage === 'delivery_deliverer.php'
        ? 'deliveries'
        : ($currentPage === 'riwayat_pengiriman.php' ? 'history' : 'dashboard'));
?>
<header class="app-navbar">
    <a class="app-navbar-brand" href="deliverer.php" aria-label="Delvra, dashboard kurir">
        <img src="../assets/logo/logo1.png" alt="">
        <span>PT Intra Presisi Indonesia</span>
    </a>
    <nav class="app-navbar-links" aria-label="Navigasi kurir">
        <a href="deliverer.php" <?php echo $activeSection === 'dashboard' ? 'aria-current="page"' : ''; ?>>Dashboard</a>
        <a href="request_deliverer.php" <?php echo $activeSection === 'requests' ? 'aria-current="page"' : ''; ?>>Permintaan</a>
        <a href="delivery_deliverer.php" <?php echo $activeSection === 'deliveries' ? 'aria-current="page"' : ''; ?>>Pengiriman</a>
        <a href="riwayat_pengiriman.php" <?php echo $activeSection === 'history' ? 'aria-current="page"' : ''; ?>>Riwayat</a>
    </nav>
    <div class="app-navbar-account">
        <span>Kurir: <strong><?php echo htmlspecialchars((string)($_SESSION['nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></span>
        <a class="app-navbar-logout" href="logout.php">Keluar</a>
    </div>
</header>