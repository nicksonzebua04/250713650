<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
$userId = require_login();
require_post();
verify_csrf();
$isAdmin = user_is_admin($userId);

$action = (string) ($_POST['aksi'] ?? '');
$logId = filter_var($_POST['id_log'] ?? null, FILTER_VALIDATE_INT);

if ($action === 'hapus') {
    if (!$logId || $logId < 1) {
        set_flash('error', 'Data log tidak valid.');
        redirect('logs.php#log-interaksi');
    }

    if ($isAdmin) {
        $stmt = $conn->prepare('DELETE FROM log_interaksi WHERE id_log = ?');
        $stmt->bind_param('i', $logId);
    } else {
        $stmt = $conn->prepare('DELETE FROM log_interaksi WHERE id_log = ? AND id_user = ?');
        $stmt->bind_param('ii', $logId, $userId);
    }
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    set_flash($deleted > 0 ? 'success' : 'error', $deleted > 0 ? 'Log interaksi berhasil dihapus.' : 'Log tidak ditemukan atau Anda tidak memiliki akses.');
    redirect('logs.php#log-interaksi');
}

$catId = filter_var($_POST['id_kucing'] ?? null, FILTER_VALIDATE_INT);
$type = (string) ($_POST['jenis_interaksi'] ?? '');
$note = trim((string) ($_POST['catatan'] ?? ''));
$validTypes = ['penampakan', 'beri_makan', 'bermain', 'lainnya'];
$latitudeInput = trim((string) ($_POST['latitude'] ?? ''));
$longitudeInput = trim((string) ($_POST['longitude'] ?? ''));

if (!$catId || $catId < 1 || !in_array($type, $validTypes, true)) {
    set_flash('error', 'Pilih kucing dan jenis interaksi yang valid.');
    redirect('logs.php#log-interaksi');
}

$catStmt = $conn->prepare('SELECT id_kucing FROM kucing WHERE id_kucing = ?');
$catStmt->bind_param('i', $catId);
$catStmt->execute();
$catExists = $catStmt->get_result()->num_rows === 1;
$catStmt->close();
if (!$catExists) {
    set_flash('error', 'Kucing yang dipilih tidak ditemukan.');
    redirect('logs.php#log-interaksi');
}

$latitude = null;
$longitude = null;
if ($latitudeInput !== '' || $longitudeInput !== '') {
    if (
        !is_numeric($latitudeInput)
        || !is_numeric($longitudeInput)
        || (float) $latitudeInput < -90
        || (float) $latitudeInput > 90
        || (float) $longitudeInput < -180
        || (float) $longitudeInput > 180
    ) {
        set_flash('error', 'Koordinat GPS tidak valid.');
        redirect('logs.php#log-interaksi');
    }
    $latitude = (float) $latitudeInput;
    $longitude = (float) $longitudeInput;
}

if ($action === 'tambah') {
    $stmt = $conn->prepare(
        'INSERT INTO log_interaksi (id_user, id_kucing, jenis_interaksi, catatan, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iissdd', $userId, $catId, $type, $note, $latitude, $longitude);
    $stmt->execute();
    $stmt->close();
    set_flash('success', 'Log interaksi berhasil ditambahkan.');
    redirect('logs.php#log-interaksi');
}

if ($action === 'edit' && $logId && $logId > 0) {
    if ($isAdmin) {
        $ownerStmt = $conn->prepare('SELECT id_log FROM log_interaksi WHERE id_log = ?');
        $ownerStmt->bind_param('i', $logId);
    } else {
        $ownerStmt = $conn->prepare('SELECT id_log FROM log_interaksi WHERE id_log = ? AND id_user = ?');
        $ownerStmt->bind_param('ii', $logId, $userId);
    }
    $ownerStmt->execute();
    $logExists = $ownerStmt->get_result()->num_rows === 1;
    $ownerStmt->close();
    if (!$logExists) {
        set_flash('error', 'Log tidak ditemukan atau Anda tidak memiliki akses.');
        redirect('logs.php#log-interaksi');
    }

    if ($isAdmin) {
        $stmt = $conn->prepare(
            'UPDATE log_interaksi SET id_kucing = ?, jenis_interaksi = ?, catatan = ?, latitude = ?, longitude = ? WHERE id_log = ?'
        );
        $stmt->bind_param('issddi', $catId, $type, $note, $latitude, $longitude, $logId);
    } else {
        $stmt = $conn->prepare(
            'UPDATE log_interaksi SET id_kucing = ?, jenis_interaksi = ?, catatan = ?, latitude = ?, longitude = ? WHERE id_log = ? AND id_user = ?'
        );
        $stmt->bind_param('issddii', $catId, $type, $note, $latitude, $longitude, $logId, $userId);
    }
    $stmt->execute();
    $stmt->close();
    set_flash('success', 'Log interaksi berhasil diperbarui.');
    redirect('logs.php#log-interaksi');
}

set_flash('error', 'Aksi log tidak valid.');
redirect('logs.php#log-interaksi');
