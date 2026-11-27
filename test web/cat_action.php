<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
$userId = require_login();
require_post();
verify_csrf();
ensure_cat_schema();

$action = (string) ($_POST['aksi'] ?? '');
$id = filter_var($_POST['id_kucing'] ?? null, FILTER_VALIDATE_INT);
$isAdmin = user_is_admin($userId);

if (in_array($action, ['edit', 'hapus'], true) && !$isAdmin) {
    http_response_code(403);
    exit('Hanya admin yang dapat mengubah atau menghapus data kucing.');
}

if ($action === 'hapus') {
    if (!$id || $id < 1) {
        set_flash('error', 'Data kucing tidak valid.');
        redirect('index.php#data-kucing');
    }

    $photoStmt = $conn->prepare('SELECT foto_path FROM kucing WHERE id_kucing = ?');
    $photoStmt->bind_param('i', $id);
    $photoStmt->execute();
    $existingCat = $photoStmt->get_result()->fetch_assoc();
    $photoStmt->close();

    $stmt = $conn->prepare('DELETE FROM kucing WHERE id_kucing = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();

    if ($deleted > 0) {
        delete_cat_photo($existingCat['foto_path'] ?? null);
    }
    set_flash($deleted > 0 ? 'success' : 'error', $deleted > 0 ? 'Data kucing berhasil dihapus.' : 'Data kucing tidak ditemukan.');
    redirect('index.php#data-kucing');
}

if (!in_array($action, ['tambah', 'edit'], true)) {
    set_flash('error', 'Aksi kucing tidak valid.');
    redirect('index.php#data-kucing');
}

$name = trim((string) ($_POST['nama'] ?? ''));
$description = trim((string) ($_POST['ciri_fisik'] ?? ''));
$location = trim((string) ($_POST['lokasi_nongkrong'] ?? ''));
$status = (string) ($_POST['status'] ?? '');
$ownership = (string) ($_POST['kepemilikan'] ?? '');
$statusReason = trim((string) ($_POST['keterangan_status'] ?? ''));
$validStatuses = ['aktif', 'hilang', 'sakit', 'lainnya', 'diadopsi'];
$validOwnership = ['liar', 'peliharaan', 'belum_diketahui'];

if (
    $name === ''
    || strlen($name) > 100
    || strlen($location) > 255
    || strlen($statusReason) > 255
    || !in_array($status, $validStatuses, true)
    || !in_array($ownership, $validOwnership, true)
    || ($status === 'lainnya' && $statusReason === '')
) {
    set_flash('error', 'Nama, status, dan kepemilikan harus valid. Keterangan wajib diisi untuk status Lainnya.');
    redirect('index.php#data-kucing');
}

$statusReason = $statusReason === '' ? null : $statusReason;

$existingPhoto = null;
if ($action === 'edit') {
    if (!$id || $id < 1) {
        set_flash('error', 'Data kucing tidak valid.');
        redirect('index.php#data-kucing');
    }

    $photoStmt = $conn->prepare('SELECT foto_path FROM kucing WHERE id_kucing = ?');
    $photoStmt->bind_param('i', $id);
    $photoStmt->execute();
    $existingCat = $photoStmt->get_result()->fetch_assoc();
    $photoStmt->close();
    if (!$existingCat) {
        set_flash('error', 'Data kucing tidak ditemukan.');
        redirect('index.php#data-kucing');
    }
    $existingPhoto = $existingCat['foto_path'];
}

$photoFile = $_FILES['foto'] ?? null;
$hasPhoto = is_array($photoFile) && (int) ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
if ($action === 'tambah' && !$hasPhoto) {
    set_flash('error', 'Pilih foto kucing sebelum menyimpan data.');
    redirect('index.php#form-kucing');
}

$newPhotoPath = null;
if ($hasPhoto) {
    try {
        $newPhotoPath = store_cat_photo($photoFile);
    } catch (InvalidArgumentException $exception) {
        set_flash('error', $exception->getMessage());
        redirect('index.php#form-kucing');
    } catch (RuntimeException $exception) {
        error_log('Unable to store cat photo: ' . $exception->getMessage());
        set_flash('error', 'Foto tidak dapat disimpan. Periksa izin folder penyimpanan lalu coba lagi.');
        redirect('index.php#form-kucing');
    }
}

$photoPath = $newPhotoPath ?? $existingPhoto;

if ($action === 'tambah') {
    $stmt = $conn->prepare('INSERT INTO kucing (nama, ciri_fisik, lokasi_nongkrong, status, kepemilikan, keterangan_status, foto_path) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sssssss', $name, $description, $location, $status, $ownership, $statusReason, $photoPath);
    $stmt->execute();
    $stmt->close();
    set_flash('success', 'Kucing berhasil ditambahkan.');
    redirect('index.php#data-kucing');
}

if ($action === 'edit' && $id && $id > 0) {
    $stmt = $conn->prepare('UPDATE kucing SET nama = ?, ciri_fisik = ?, lokasi_nongkrong = ?, status = ?, kepemilikan = ?, keterangan_status = ?, foto_path = ? WHERE id_kucing = ?');
    $stmt->bind_param('sssssssi', $name, $description, $location, $status, $ownership, $statusReason, $photoPath, $id);
    $stmt->execute();
    $stmt->close();
    if ($newPhotoPath !== null) {
        delete_cat_photo($existingPhoto);
    }
    set_flash('success', 'Data kucing berhasil diperbarui.');
    redirect('index.php#data-kucing');
}

set_flash('error', 'Aksi kucing tidak valid.');
redirect('index.php#data-kucing');
