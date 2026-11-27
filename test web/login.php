<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_post();

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    redirect('login.xhtml?pesan=gagal');
}

$stmt = $conn->prepare('SELECT id_user, password FROM users WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->bind_result($userId, $passwordHash);
$validLogin = $stmt->fetch() && password_verify($password, $passwordHash);
$stmt->close();

if (!$validLogin) {
    redirect('login.xhtml?pesan=gagal');
}

session_regenerate_id(true);
$_SESSION['id_user'] = (int) $userId;
redirect('index.php');
