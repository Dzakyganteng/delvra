<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] != 'admin') {
    echo "Akses ditolak!";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f6f5fb">
    <title>Dashboard Admin - Delvra</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --admin-accent: #1c39b7;
            --admin-accent-dark: #102878;
            --admin-ink: #211b36;
            --admin-muted: #817b94;
            --admin-line: #ebe8f3;
        }

        body {
            min-height: 100vh;
            color: var(--admin-ink);
            background: #f6f5fb;
            font-family: Inter, "Segoe UI", sans-serif;
        }

        .topbar {
            background: rgba(255, 255, 255, .94);
            border-bottom: 1px solid var(--admin-line);
        }

        .topbar-inner {
            min-height: 76px;
        }

        .brand-logo {
            width: 44px;
            height: 44px;
            object-fit: contain;
        }

        .brand-name {
            color: var(--admin-ink);
            font-size: 1.35rem;
            font-weight: 750;
            letter-spacing: -.05em;
        }

        .account-label {
            color: var(--admin-muted);
            font-size: .88rem;
        }

        .btn-logout {
            border: 1px solid var(--admin-line);
            border-radius: 10px;
            color: #536078;
            font-weight: 600;
        }

        .btn-logout:hover {
            border-color: #cbd4f0;
            color: var(--admin-accent-dark);
            background: #f7f9fd;
        }

        .dashboard {
            max-width: 1120px;
            padding-top: 48px;
            padding-bottom: 64px;
        }

        .welcome-card {
            position: relative;
            overflow: hidden;
            padding: clamp(28px, 5vw, 52px);
            border: 0;
            border-radius: 24px;
            color: #fff;
            background: linear-gradient(120deg, var(--admin-accent-dark) 0%, var(--admin-accent) 100%);
            box-shadow: 0 18px 40px rgba(28, 57, 183, .18);
        }

        .welcome-card::after {
            position: absolute;
            top: -125px;
            right: -40px;
            width: 330px;
            height: 330px;
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 50%;
            content: "";
            box-shadow: 0 0 0 42px rgba(255, 255, 255, .04), 0 0 0 84px rgba(255, 255, 255, .025);
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            max-width: 610px;
        }

        .welcome-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
            color: rgba(255, 255, 255, .82);
            font-size: .88rem;
            font-weight: 600;
        }

        .welcome-eyebrow::before {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #c7d2ff;
            content: "";
        }

        .welcome-card h1 {
            margin: 0;
            font-size: clamp(2rem, 5vw, 2.8rem);
            font-weight: 750;
            letter-spacing: -.05em;
            line-height: 1.12;
        }

        .welcome-card p {
            max-width: 490px;
            margin: 16px 0 0;
            color: rgba(255, 255, 255, .8);
            font-size: 1.02rem;
            line-height: 1.7;
        }

        .section-heading {
            margin: 42px 0 18px;
        }

        .section-heading h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 730;
            letter-spacing: -.025em;
        }

        .section-heading p {
            margin: 6px 0 0;
            color: var(--admin-muted);
            font-size: .94rem;
        }

        .menu-card {
            display: flex;
            min-height: 250px;
            flex-direction: column;
            align-items: flex-start;
            padding: 28px;
            border: 1px solid var(--admin-line);
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 8px 26px rgba(30, 48, 90, .04);
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }

        .menu-card:hover {
            transform: translateY(-4px);
            border-color: #d9e1f3;
            box-shadow: 0 16px 34px rgba(30, 48, 90, .09);
        }

        .menu-icon {
            display: grid;
            width: 52px;
            height: 52px;
            place-items: center;
            margin-bottom: 22px;
            border-radius: 16px;
            color: var(--admin-accent-dark);
            background: #eef2ff;
        }

        .menu-card.delivery .menu-icon {
            color: var(--admin-accent-dark);
            background: #eef2ff;
        }

        .menu-card h3 {
            margin-bottom: 9px;
            font-size: 1.16rem;
            font-weight: 720;
        }

        .menu-card p {
            flex-grow: 1;
            margin-bottom: 22px;
            color: var(--admin-muted);
            font-size: .94rem;
            line-height: 1.65;
        }

        .menu-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--admin-accent-dark);
            font-weight: 650;
            text-decoration: none;
        }

        .menu-card.delivery .menu-link {
            color: var(--admin-accent-dark);
        }

        .menu-link:hover {
            text-decoration: underline;
        }

        .page-footer {
            margin-top: 40px;
            color: #9690a5;
            font-size: .85rem;
            text-align: center;
        }

        @media (max-width: 575.98px) {
            .topbar-inner {
                min-height: 68px;
            }

            .brand-logo {
                width: 38px;
                height: 38px;
            }

            .account-label {
                display: none;
            }

            .dashboard {
                padding-top: 28px;
            }

            .welcome-card {
                border-radius: 20px;
            }

            .menu-card {
                min-height: 220px;
                padding: 24px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
</head>

<body>
    <?php include __DIR__ . '/includes/navbar_admin.php'; ?>

    <main class="container dashboard">
        <section class="welcome-card" aria-labelledby="welcome-title">
            <div class="welcome-content">
                <div class="welcome-eyebrow">Admin</div>
                <h1 id="welcome-title">
                    Halo, <?php echo htmlspecialchars($_SESSION['nama'], ENT_QUOTES, 'UTF-8'); ?>!
                </h1>
                <p>Kelola permintaan pelanggan dan informasi pengiriman Delvra melalui menu administrasi berikut.</p>
            </div>
        </section>

        <div class="section-heading">
            <h2>Administrasi Delvra</h2>
            <p>Pilih bagian yang ingin Anda kelola.</p>
        </div>

        <section class="row g-4" aria-label="Menu administrasi">
            <div class="col-md-6">
                <article class="menu-card h-100">
                    <div class="menu-icon" aria-hidden="true">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                            <path d="M7 3.5h7l4 4V20a.5.5 0 0 1-.5.5h-11A.5.5 0 0 1 6 20V4.5a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                            <path d="M14 3.8V8h4M9 12h6M9 15.5h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3>Request pelanggan</h3>
                    <p>Tinjau permintaan produk pelanggan dan kelola proses request dari satu halaman.</p>
                    <a class="menu-link" href="request_admin.php">
                        Kelola request <span aria-hidden="true">&rarr;</span>
                    </a>
                </article>
            </div>

            <div class="col-md-6">
                <article class="menu-card delivery h-100">
                    <div class="menu-icon" aria-hidden="true">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                            <path d="M3 6.5h11v11H3v-11Zm11 4h3.5l3.5 3.5v3.5h-7v-7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                            <circle cx="7.5" cy="18" r="1.8" fill="white" stroke="currentColor" stroke-width="1.7"/>
                            <circle cx="18" cy="18" r="1.8" fill="white" stroke="currentColor" stroke-width="1.7"/>
                        </svg>
                    </div>
                    <h3>Delivery</h3>
                    <p>Pantau dan kelola informasi pengiriman serta status delivery pelanggan.</p>
                    <a class="menu-link" href="delivery_admin.php">
                        Kelola delivery <span aria-hidden="true">&rarr;</span>
                    </a>
                </article>
            </div>
        </section>

        <footer class="page-footer">Delvra Powered by PT Intra Presisi Indonesia</footer>
    </main>
</body>
</html>
    