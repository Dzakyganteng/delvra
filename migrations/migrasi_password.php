
<?php

require_once "config/database.php";
$akun_lama = [
    [
        "username" => "admin",
        "password" => "admin123"
    ],
    [
        "username" => "deliverer",
        "password" => "deliverer123"
    ]
];

foreach ($akun_lama as $akun) {

    $username = $akun["username"];
    $password_hash = password_hash(
        $akun["password"],
        PASSWORD_DEFAULT
    );

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE users
         SET password = ?
         WHERE username = ?
         AND role IN ('admin', 'deliverer')"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $password_hash,
        $username
    );

    if (mysqli_stmt_execute($stmt)) {
        echo htmlspecialchars($username)
            . ": migrasi selesai. Baris diperbarui: "
            . mysqli_stmt_affected_rows($stmt)
            . "<br>";
    } else {
        echo htmlspecialchars($username)
            . ": migrasi gagal.<br>";
    }

    mysqli_stmt_close($stmt);
}

mysqli_close($conn);

?>
