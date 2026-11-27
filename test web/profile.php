<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
$userId = require_login();
ensure_user_profile_schema();

$stmt = $conn->prepare('SELECT nama_lengkap, username, password, bio, foto_path, created_at, role FROM users WHERE id_user = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION = [];
    session_destroy();
    redirect('login.xhtml');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['nama_lengkap'] ?? ''));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $currentPassword = (string) ($_POST['password_lama'] ?? '');
    $newPassword = (string) ($_POST['password_baru'] ?? '');
    $photoFile = $_FILES['foto_profil'] ?? null;
    $hasPhoto = is_array($photoFile) && (int) ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($name === '' || strlen($name) > 100) {
        set_flash('error', 'Nama lengkap wajib diisi (maksimal 100 karakter).');
    } elseif ($newPassword !== '' && (strlen($newPassword) < 6 || !password_verify($currentPassword, $user['password']))) {
        set_flash('error', $newPassword !== '' && strlen($newPassword) < 6
            ? 'Password baru minimal 6 karakter.'
            : 'Password saat ini tidak sesuai.');
    } else {
        $newPhotoPath = null;
        if ($hasPhoto) {
            try {
                $newPhotoPath = store_profile_photo($photoFile);
            } catch (InvalidArgumentException $exception) {
                set_flash('error', $exception->getMessage());
            } catch (RuntimeException $exception) {
                error_log('Unable to store profile photo: ' . $exception->getMessage());
                set_flash('error', 'Foto profil tidak dapat disimpan. Periksa izin folder penyimpanan lalu coba lagi.');
            }
        }

        if ($hasPhoto && $newPhotoPath === null) {
            $user['nama_lengkap'] = $name;
            $user['bio'] = $bio;
        } else {
            $passwordHash = $newPassword === '' ? $user['password'] : password_hash($newPassword, PASSWORD_DEFAULT);
            $photoPath = $newPhotoPath ?? $user['foto_path'];
            $update = $conn->prepare('UPDATE users SET nama_lengkap = ?, bio = ?, password = ?, foto_path = ? WHERE id_user = ?');
            $update->bind_param('ssssi', $name, $bio, $passwordHash, $photoPath, $userId);
            try {
                $update->execute();
            } catch (mysqli_sql_exception $exception) {
                $update->close();
                delete_profile_photo($newPhotoPath);
                throw $exception;
            }
            $update->close();
            if ($newPhotoPath !== null) {
                delete_profile_photo($user['foto_path']);
            }
            set_flash('success', 'Profil berhasil diperbarui.');
            redirect('profile.php');
        }
    }

    $user['nama_lengkap'] = $name;
    $user['bio'] = $bio;
}

$logStmt = $conn->prepare('SELECT COUNT(*) FROM log_interaksi WHERE id_user = ?');
$logStmt->bind_param('i', $userId);
$logStmt->execute();
$logStmt->bind_result($userLogCount);
$logStmt->fetch();
$logStmt->close();

$isAdmin = $user['role'] === 'admin';
render_header('Profil Saya', 'profile', $user['username'], $isAdmin);
?>
  <main class="container main-content profile-page">
    <?php render_flash(); ?>
    <section class="page-banner profile-banner">
      <div>
        <p class="eyebrow">RUANG RELAWAN</p>
        <h1>Pengaturan Profil Relawan</h1>
      </div>
      <span class="verified-badge"><span aria-hidden="true">&#x25CF;</span> Sesi Aktif Terverifikasi</span>
    </section>

    <div class="profile-layout">
      <aside class="profile-summary card">
        <div class="profile-avatar">
          <?php if (!empty($user['foto_path'])): ?>
            <img class="profile-avatar-image" src="<?php echo h($user['foto_path']); ?>" alt="Foto profil <?php echo h($user['nama_lengkap']); ?>" />
          <?php else: ?>
            <span aria-hidden="true">&#x1F464;</span>
          <?php endif; ?>
        </div>
        <h2><?php echo h($user['nama_lengkap']); ?></h2>
        <p class="profile-username">@<?php echo h($user['username']); ?></p>
        <?php if ($user['bio'] !== null && $user['bio'] !== ''): ?>
          <blockquote><?php echo h($user['bio']); ?></blockquote>
        <?php else: ?>
          <p class="profile-empty-bio">Bio singkat relawan belum diisi.</p>
        <?php endif; ?>
        <dl class="profile-facts">
          <div><dt>ID User Sesi</dt><dd>USR-<?php echo str_pad((string) $userId, 3, '0', STR_PAD_LEFT); ?></dd></div>
          <div><dt>Laporan Interaksi</dt><dd><?php echo (int) $userLogCount; ?> Log Dibuat</dd></div>
          <div><dt>Tanggal Gabung</dt><dd><?php echo h(date('d M Y', strtotime($user['created_at']))); ?></dd></div>
        </dl>
      </aside>

      <section class="profile-form-card card">
        <div class="profile-form-heading">
          <h2>Formulir Pembaruan Data Pengguna</h2>
          <p>Ubah nama lengkap, bio singkat, atau perbarui kata sandi.</p>
        </div>
        <form action="profile.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>" />
          <div class="form-group">
            <label class="form-label" for="profile-photo-file">Foto Profil</label>
            <input class="input-field" type="file" id="profile-photo-file" name="foto_profil" accept="image/jpeg,image/png,image/webp" aria-describedby="profile-photo-hint" />
            <small class="field-hint" id="profile-photo-hint">Pilih JPG, PNG, atau WebP dari galeri/folder di ponsel atau File Explorer di laptop. Maksimal 5 MB. Kosongkan jika tidak ingin mengganti foto.</small>
            <?php if (!empty($user['foto_path'])): ?>
              <img class="profile-photo-preview" id="profile-photo-preview" src="<?php echo h($user['foto_path']); ?>" alt="Pratinjau foto profil" />
            <?php else: ?>
              <img class="profile-photo-preview" id="profile-photo-preview" alt="Pratinjau foto profil" hidden="hidden" />
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label" for="profile-username">Username</label>
            <input class="input-field" type="text" id="profile-username" value="<?php echo h($user['username']); ?>" disabled="disabled" />
          </div>
          <div class="form-group">
            <label class="form-label" for="profile-name">Nama Lengkap</label>
            <input class="input-field" type="text" id="profile-name" name="nama_lengkap" maxlength="100" placeholder="Contoh: Rahmat Hidayat" required="required" value="<?php echo h($user['nama_lengkap']); ?>" />
          </div>
          <div class="form-group">
            <label class="form-label" for="profile-bio">Bio Singkat Relawan</label>
            <textarea class="input-field" id="profile-bio" name="bio" rows="3" placeholder="Ceritakan kegiatanmu bersama kucing di sekitar."><?php echo h($user['bio']); ?></textarea>
          </div>
          <fieldset class="password-section">
            <legend>Ganti Kata Sandi (Opsional)</legend>
            <p class="field-hint">Kosongkan jika tidak ingin mengubah password.</p>
            <div class="form-group">
              <label class="form-label" for="password-old">Kata Sandi Saat Ini</label>
              <div class="password-field">
                <input class="input-field" type="password" id="password-old" name="password_lama" autocomplete="current-password" />
                <button class="password-toggle" type="button" data-password-target="password-old" aria-label="Tampilkan kata sandi" aria-pressed="false">&#x1F441;</button>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="password-new">Kata Sandi Baru</label>
              <div class="password-field">
                <input class="input-field" type="password" id="password-new" name="password_baru" minlength="6" placeholder="Minimal 6 karakter" autocomplete="new-password" />
                <button class="password-toggle" type="button" data-password-target="password-new" aria-label="Tampilkan kata sandi" aria-pressed="false">&#x1F441;</button>
              </div>
              <span class="field-hint">Masukkan kata sandi saat ini untuk mengonfirmasi perubahan.</span>
            </div>
          </fieldset>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit">&#x1F4BE; Simpan Perubahan Profil</button>
          </div>
        </form>
      </section>
    </div>
  </main>
<?php
render_footer();
