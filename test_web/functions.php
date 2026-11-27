<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

function require_login(): int
{
    if (!isset($_SESSION['id_user'])) {
        redirect('login.xhtml');
    }

    return (int) $_SESSION['id_user'];
}

function run_cat_schema_migration(string $query): void
{
    global $conn;

    try {
        $conn->query($query);
    } catch (mysqli_sql_exception $exception) {
        if ((int) $exception->getCode() === 1060) {
            return;
        }

        error_log('Unable to update kucing schema: ' . $exception->getMessage());
        http_response_code(500);
        exit('Pembaruan database gagal. Jalankan database.sql atau pastikan akun database memiliki izin ALTER TABLE.');
    }
}

function ensure_cat_schema(): void
{
    global $conn;

    $result = $conn->query('SHOW COLUMNS FROM kucing');
    $columns = [];
    while ($column = $result->fetch_assoc()) {
        $columns[$column['Field']] = $column;
    }
    $result->free();

    if (!isset($columns['kepemilikan'])) {
        run_cat_schema_migration(
            "ALTER TABLE kucing
             ADD COLUMN kepemilikan ENUM('liar', 'peliharaan', 'belum_diketahui')
             NOT NULL DEFAULT 'belum_diketahui' AFTER status"
        );
    }
    if (!isset($columns['keterangan_status'])) {
        run_cat_schema_migration(
            'ALTER TABLE kucing ADD COLUMN keterangan_status VARCHAR(255) NULL AFTER kepemilikan'
        );
    }
    if (!isset($columns['foto_path'])) {
        run_cat_schema_migration(
            'ALTER TABLE kucing ADD COLUMN foto_path VARCHAR(255) NULL AFTER lokasi_nongkrong'
        );
    }

    $statusType = (string) ($columns['status']['Type'] ?? '');
    if (strpos($statusType, "'sakit'") === false || strpos($statusType, "'lainnya'") === false) {
        run_cat_schema_migration(
            "ALTER TABLE kucing
             MODIFY COLUMN status ENUM('aktif', 'hilang', 'sakit', 'lainnya', 'diadopsi')
             NOT NULL DEFAULT 'aktif'"
        );
    }
}

function ensure_user_profile_schema(): void
{
    global $conn;

    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'foto_path'");
    $columnExists = $result->num_rows > 0;
    $result->free();
    if ($columnExists) {
        return;
    }

    try {
        $conn->query('ALTER TABLE users ADD COLUMN foto_path VARCHAR(255) NULL AFTER bio');
    } catch (mysqli_sql_exception $exception) {
        if ((int) $exception->getCode() === 1060) {
            return;
        }

        error_log('Unable to update user profile schema: ' . $exception->getMessage());
        http_response_code(500);
        exit('Pembaruan database profil gagal. Jalankan database.sql atau pastikan akun database memiliki izin ALTER TABLE.');
    }
}

function store_uploaded_photo(array $file, string $folderName, string $requiredMessage): string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran foto melebihi batas yang diizinkan server.',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran foto terlalu besar.',
            UPLOAD_ERR_PARTIAL => 'Foto tidak terkirim sepenuhnya. Coba pilih ulang.',
            UPLOAD_ERR_NO_FILE => $requiredMessage,
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara untuk unggahan tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Server tidak dapat menyimpan foto.',
            UPLOAD_ERR_EXTENSION => 'Unggahan foto dihentikan oleh konfigurasi server.',
        ];
        throw new InvalidArgumentException($messages[$error] ?? 'Unggahan foto gagal.');
    }

    $temporaryPath = $file['tmp_name'] ?? '';
    $size = (int) ($file['size'] ?? 0);
    if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('Unggahan foto tidak valid. Pilih ulang foto.');
    }
    if ($size < 1 || $size > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran foto maksimal 5 MB.');
    }

    $imageInfo = @getimagesize($temporaryPath);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($temporaryPath);
    $extensions = [
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        'image/webp' => '.webp',
    ];
    if (
        $imageInfo === false
        || !is_string($mimeType)
        || !isset($extensions[$mimeType])
        || ($imageInfo['mime'] ?? '') !== $mimeType
        || $imageInfo[0] > 8000
        || $imageInfo[1] > 8000
        || $imageInfo[0] * $imageInfo[1] > 32000000
    ) {
        throw new InvalidArgumentException('Format foto harus JPG, PNG, atau WebP dengan dimensi gambar yang wajar.');
    }

    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folderName;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan foto tidak dapat dibuat.');
    }

    $filename = bin2hex(random_bytes(16)) . $extensions[$mimeType];
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('Foto tidak dapat dipindahkan ke folder penyimpanan.');
    }

    return 'uploads/' . $folderName . '/' . $filename;
}

function store_cat_photo(array $file): string
{
    return store_uploaded_photo($file, 'cats', 'Foto kucing wajib dipilih.');
}

function store_profile_photo(array $file): string
{
    return store_uploaded_photo($file, 'profiles', 'Foto profil wajib dipilih.');
}

function delete_uploaded_photo(?string $photoPath, string $folderName): void
{
    $pattern = '~\Auploads/' . preg_quote($folderName, '~') . '/[a-f0-9]{32}\.(?:jpg|png|webp)\z~';
    if ($photoPath === null || !preg_match($pattern, $photoPath)) {
        return;
    }

    $filePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $photoPath);
    if (is_file($filePath) && !unlink($filePath)) {
        error_log('Unable to remove uploaded photo: ' . basename($filePath));
    }
}

function delete_cat_photo(?string $photoPath): void
{
    delete_uploaded_photo($photoPath, 'cats');
}

function delete_profile_photo(?string $photoPath): void
{
    delete_uploaded_photo($photoPath, 'profiles');
}

function user_is_admin(int $userId): bool
{
    global $conn;

    $stmt = $conn->prepare('SELECT role FROM users WHERE id_user = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($role);
    $isAdmin = $stmt->fetch() && $role === 'admin';
    $stmt->close();

    return $isAdmin;
}

function require_admin(): int
{
    $userId = require_login();
    if (!user_is_admin($userId)) {
        set_flash('error', 'Halaman ini hanya dapat diakses oleh admin.');
        redirect('index.php');
    }

    return $userId;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(400);
        exit('Permintaan tidak valid. Muat ulang halaman, lalu coba lagi.');
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function render_flash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $type = $flash['type'] === 'success' ? 'success' : 'error';
    echo '<div class="flash-message flash-' . $type . '" role="status">' . h($flash['message']) . '</div>';
}

function render_header(string $title, string $activePage, string $username, bool $isAdmin): void
{
    header('Content-Type: application/xhtml+xml; charset=UTF-8');
    header('Cache-Control: no-store, private');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="id" lang="id">
<head>
  <meta http-equiv="Content-Type" content="application/xhtml+xml; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo h($title); ?> - PawTrack</title>
  <script type="text/javascript">
    //<![CDATA[
    try {
      var savedTheme = window.localStorage.getItem('pawtrack-theme');
      if (savedTheme === 'light' || savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', savedTheme);
      }
    } catch (error) {}
    //]]>
  </script>
  <link rel="stylesheet" type="text/css" href="style.css?v=16" />
</head>
<body>
  <header class="navbar" id="page-top">
    <div class="navbar-inner">
      <a href="index.php" class="navbar-brand"><span class="brand-icon">&#x1F43E;</span><span class="brand-copy"><strong>PawTrack</strong><small>Stray Cat Tracker</small></span></a>
      <nav class="nav-links<?php echo $isAdmin ? ' nav-links-admin' : ''; ?>" aria-label="Navigasi utama">
        <a href="index.php#data-kucing" class="nav-link<?php echo $activePage === 'cats' ? ' active' : ''; ?>"<?php echo $activePage === 'cats' ? ' aria-current="page"' : ''; ?>><span aria-hidden="true">&#x1F431;</span> Data Kucing</a>
        <a href="logs.php" class="nav-link<?php echo $activePage === 'logs' ? ' active' : ''; ?>"<?php echo $activePage === 'logs' ? ' aria-current="page"' : ''; ?>><span aria-hidden="true">&#x1F4CB;</span> Log Interaksi</a>
        <?php if ($isAdmin): ?><a href="admin.php" class="nav-link<?php echo $activePage === 'admin' ? ' active' : ''; ?>"<?php echo $activePage === 'admin' ? ' aria-current="page"' : ''; ?>><span aria-hidden="true">&#x2699;</span> Admin</a><?php endif; ?>
        <a href="profile.php" class="nav-link<?php echo $activePage === 'profile' ? ' active' : ''; ?>"<?php echo $activePage === 'profile' ? ' aria-current="page"' : ''; ?>><span aria-hidden="true">&#x1F464;</span> Profil Saya</a>
        <button type="button" class="nav-link theme-toggle" id="theme-toggle" aria-label="Aktifkan tema gelap">&#x1F319; Tema gelap</button>
        <form action="logout.php" method="post" class="logout-form">
          <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>" />
          <button type="submit" class="nav-link nav-logout">Logout</button>
        </form>
      </nav>
    </div>
  </header>
<?php
}

function render_footer(): void
{
    ?>
  <div class="footer"><p>&#x1F43E; PawTrack &#xA9; <?php echo date('Y'); ?> &#x2014; Komunitas Peduli Kucing Jalanan</p></div>
  <a class="back-to-top" id="back-to-top" href="#page-top" aria-label="Kembali ke atas" aria-hidden="true" tabindex="-1" title="Kembali ke atas">&#x2191;</a>
  <script type="text/javascript" src="script.js?v=12"></script>
</body>
</html>
<?php
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Metode permintaan tidak diizinkan.');
    }
}
