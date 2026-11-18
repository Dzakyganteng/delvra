
<?php

session_start();
require_once "../config/database.php";

$error = "";

if (isset($_POST['register'])) {

    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $konfirmasi = $_POST['konfirmasi_password'];

    if ($nama == "" || $username == "" || $password == "") {
        $error = "Semua kolom wajib diisi.";
    } elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter.";
    } elseif ($password !== $konfirmasi) {
        $error = "Konfirmasi password tidak cocok.";
    } else {

        // Cek username sudah digunakan atau belum
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_user FROM users WHERE username = ?"
        );

        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {

            $error = "Username sudah digunakan.";

        } else {

            // Hash password sebelum disimpan
            $hash_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Role selalu customer
            $role = "customer";

            $stmt_insert = mysqli_prepare(
                $conn,
                "INSERT INTO users (nama, username, password, role)
                 VALUES (?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt_insert,
                "ssss",
                $nama,
                $username,
                $hash_password,
                $role
            );

            if (mysqli_stmt_execute($stmt_insert)) {

                echo "<script>
                    alert('Registrasi berhasil! Silakan login.');
                    window.location='login.php';
                </script>";

                exit;

            } else {

                $error = "Registrasi gagal. Silakan coba lagi.";
            }

            mysqli_stmt_close($stmt_insert);
        }

        mysqli_stmt_close($stmt);
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Delvra</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --delvra-blue: #2446d8;
            --delvra-ink: #17213d;
            --delvra-muted: #7d879e;
        }

        body {
            min-height: 100vh;
            color: var(--delvra-ink);
            background: #f4f6fc;
            font-family: Inter, "Segoe UI", sans-serif;
        }

        .login-shell {
            min-height: 100vh;
            padding: 32px 16px;
        }

        .login-card {
            width: min(100%, 1000px);
            height: min(760px, calc(100vh - 64px));
            min-height: 640px;
            overflow: hidden;
            border: 0;
            border-radius: 28px;
            box-shadow: 0 24px 70px rgba(32, 51, 112, .12);
        }

        .brand-panel {
            position: relative;
            display: flex;
            min-height: 100%;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            padding: 48px;
            color: #fff;
            background: linear-gradient(145deg, #2446d8 0%, #142b9a 100%);
        }

        .brand-panel::before,
        .brand-panel::after {
            position: absolute;
            content: "";
            border: 1px solid rgba(255, 255, 255, .13);
            border-radius: 50%;
            pointer-events: none;
        }

        .brand-panel::before {
            width: 360px;
            height: 360px;
            right: -155px;
            top: 105px;
        }

        .brand-panel::after {
            width: 245px;
            height: 245px;
            right: -95px;
            top: 162px;
        }

        .brand-content,
        .brand-footer {
            position: relative;
            z-index: 1;
        }

        .brand-logo {
            display: inline-flex;
            width: 52px;
            height: 52px;
            margin-right: 10px;
            border-radius: 0;
            object-fit: cover;
            vertical-align: middle;
        }

        .brand-name {
            font-size: 1.45rem;
            font-weight: 750;
            letter-spacing: -.04em;
            vertical-align: middle;
        }

        .brand-copy {
            max-width: 370px;
            margin-top: 92px;
        }

        .brand-copy h1 {
            font-size: clamp(2.2rem, 4vw, 3.25rem);
            font-weight: 750;
            letter-spacing: -.05em;
            line-height: 1.12;
        }

        .brand-copy p,
        .brand-footer {
            color: rgba(255, 255, 255, .76);
        }

        .brand-copy p {
            max-width: 330px;
            margin-top: 18px;
            font-size: 1.05rem;
            line-height: 1.7;
        }

        .brand-footer {
            font-size: .86rem;
        }

        .form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px clamp(28px, 7vw, 80px);
        }

        .form-content {
            width: 100%;
            max-width: 380px;
        }

        .form-content h2 {
            margin-bottom: 8px;
            font-size: 2rem;
            font-weight: 750;
            letter-spacing: -.045em;
        }

        .form-intro {
            margin-bottom: 24px;
            color: var(--delvra-muted);
        }

        .form-label {
            margin-bottom: 6px;
            font-size: .9rem;
            font-weight: 650;
        }

        .form-control {
            min-height: 48px;
            border-color: #e2e7f0;
            border-radius: 12px;
            padding: 10px 15px;
        }

        .form-control:focus {
            border-color: var(--delvra-blue);
            box-shadow: 0 0 0 .22rem rgba(36, 70, 216, .12);
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap .form-control {
            padding-right: 76px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 14px;
            padding: 4px;
            border: 0;
            color: var(--delvra-muted);
            background: transparent;
            font-size: .82rem;
            font-weight: 600;
            transform: translateY(-50%);
        }

        .btn-login {
            min-height: 52px;
            border: 0;
            border-radius: 12px;
            background: var(--delvra-blue);
            box-shadow: 0 8px 18px rgba(36, 70, 216, .2);
            font-weight: 650;
        }

        .btn-login:hover,
        .btn-login:focus {
            background: #1c39b7;
        }

        .register-copy {
            margin: 18px 0 0;
            color: var(--delvra-muted);
            text-align: center;
            font-size: .92rem;
        }

        .register-copy a {
            color: var(--delvra-blue);
            font-weight: 650;
            text-decoration: none;
        }

        .register-copy a:hover {
            text-decoration: underline;
        }

        @media (max-width: 767.98px) {
            .login-shell {
                padding: 16px 12px;
            }

            .login-card {
                height: auto;
                min-height: auto;
                border-radius: 22px;
            }

            .brand-panel {
                min-height: 195px;
                padding: 24px;
            }

            .brand-copy {
                margin-top: 28px;
            }

            .brand-copy h1 {
                max-width: 300px;
                font-size: 1.8rem;
            }

            .brand-copy p,
            .brand-footer {
                display: none;
            }

            .form-panel {
                padding: 36px 24px 32px;
            }

            .form-content h2 {
                font-size: 1.75rem;
            }
        }
    </style>
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
</head>

<body>
    <main class="login-shell d-flex align-items-center justify-content-center">
        <div class="card login-card">
            <div class="row g-0 h-100">
                <section class="col-md-6 brand-panel" aria-label="Tentang Delvra">
                    <div class="brand-content">
                        <a class="text-white text-decoration-none" href="../index.php" aria-label="Delvra">
                            <img class="brand-logo" src="../assets/logo/logo1.png" alt="Logo Delvra">
                            <span class="brand-name">PT Intra Preisi Indonesia</span>
                        </a>
                        <div class="brand-copy">
                            <p>selamat datang di..</p>
                            <h1>Delvra</h1>
                            <p>Platform pengiriman produk yang cepat, aman, dan terpercaya.</p>
                        </div>
                    </div>
                    <div class="brand-footer">made by Dzaky Alfahkry Ramadhan for PT Intra Preisi Indonesia</div>
                </section>

                <section class="col-md-6 form-panel" aria-labelledby="register-title">
                    <div class="form-content">
                        <h2 id="register-title">Buat akun customer</h2>
                        <p class="form-intro">Lengkapi data berikut untuk mendaftar.</p>

                        <?php if ($error !== "") { ?>
                            <div class="alert alert-danger py-3" role="alert">
                                <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
                            </div>
                        <?php } ?>

                        <form method="POST" action="register.php">
                            <div class="mb-3">
                                <label class="form-label" for="nama">Nama Lengkap</label>
                                <input
                                    class="form-control"
                                    type="text"
                                    id="nama"
                                    name="nama"
                                    maxlength="100"
                                    autocomplete="name"
                                    required
                                    autofocus
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="username">Username</label>
                                <input
                                    class="form-control"
                                    type="text"
                                    id="username"
                                    name="username"
                                    maxlength="50"
                                    autocomplete="username"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="password">Password</label>
                                <div class="password-wrap">
                                    <input
                                        class="form-control"
                                        type="password"
                                        id="password"
                                        name="password"
                                        minlength="8"
                                        autocomplete="new-password"
                                        required
                                    >
                                    <button class="password-toggle" type="button" data-password-toggle="password" aria-controls="password" aria-label="Tampilkan password">Lihat</button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label" for="konfirmasi_password">Konfirmasi Password</label>
                                <div class="password-wrap">
                                    <input
                                        class="form-control"
                                        type="password"
                                        id="konfirmasi_password"
                                        name="konfirmasi_password"
                                        minlength="8"
                                        autocomplete="new-password"
                                        required
                                    >
                                    <button class="password-toggle" type="button" data-password-toggle="konfirmasi_password" aria-controls="konfirmasi_password" aria-label="Tampilkan konfirmasi password">Lihat</button>
                                </div>
                            </div>

                            <button class="btn btn-primary btn-login w-100" type="submit" name="register" value="1">
                                Daftar
                            </button>
                        </form>

                        <p class="register-copy">
                            Sudah punya akun? <a href="login.php">Login di sini</a>
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <script>
        document.querySelectorAll("[data-password-toggle]").forEach((toggle) => {
            toggle.addEventListener("click", () => {
                const input = document.getElementById(toggle.dataset.passwordToggle);
                const isPasswordHidden = input.type === "password";
                input.type = isPasswordHidden ? "text" : "password";
                toggle.textContent = isPasswordHidden ? "Sembunyi" : "Lihat";
                toggle.setAttribute(
                    "aria-label",
                    isPasswordHidden ? "Sembunyikan password" : "Tampilkan password"
                );
            });
        });
    </script>
</body>

</html>
