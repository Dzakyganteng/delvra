<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$activeSection = $currentPage === 'request_customer.php'
    ? 'requests'
    : ($currentPage === 'delivery_customer.php' ? 'deliveries' : 'dashboard');
?>
<header class="app-navbar">
    <a class="app-navbar-brand" href="customer.php" aria-label="Delvra, dashboard pelanggan">
        <img src="../assets/logo/logo1.png" alt="">
        <span>PT Intra Presisi Indonesia</span>
    </a>
    <nav class="app-navbar-links" aria-label="Navigasi pelanggan">
        <a href="customer.php" <?php echo $activeSection === 'dashboard' ? 'aria-current="page"' : ''; ?>>Dashboard</a>
        <a href="request_customer.php" <?php echo $activeSection === 'requests' ? 'aria-current="page"' : ''; ?>>Permintaan</a>
        <a href="delivery_customer.php" <?php echo $activeSection === 'deliveries' ? 'aria-current="page"' : ''; ?>>Pengiriman</a>
    </nav>
    <div class="app-navbar-account">
        <span>Pelanggan: <strong><?php echo htmlspecialchars((string)($_SESSION['nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></span>
        <a class="app-navbar-logout" href="logout.php">Keluar</a>
    </div>
</header>