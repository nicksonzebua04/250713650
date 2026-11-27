<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_post();

$name = trim((string) ($_POST['nama_lengkap'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if (
    $name === ''
    || strlen($name) > 100
    || preg_match('/\A.{3,50}\z/us', $username) !== 1
    || strlen($password) < 6
) {
    redirect('register.xhtml?pesan=validasi');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare('INSERT INTO users (nama_lengkap, username, password) VALUES (?, ?, ?)');
$stmt->bind_param('sss', $name, $username, $passwordHash);

try {
    $stmt->execute();
} catch (mysqli_sql_exception $exception) {
    $stmt->close();
    if ($exception->getCode() === 1062) {
        redirect('register.xhtml?pesan=duplikat');
    }

    throw $exception;
}

$stmt->close();
redirect('login.xhtml?pesan=berhasil');
