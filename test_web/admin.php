<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
$adminId = require_admin();
ensure_user_profile_schema();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    verify_csrf();

    $action = (string) ($_POST['action'] ?? 'role');
    $targetId = filter_var($_POST['id_user'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'delete_user') {
        if (!$targetId || $targetId < 1) {
            set_flash('error', 'Akun yang dipilih tidak valid.');
            redirect('admin.php');
        }

        if ($targetId === $adminId) {
            set_flash('error', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
            redirect('admin.php');
        }

        $conn->begin_transaction();
        $targetStmt = $conn->prepare('SELECT role, foto_path FROM users WHERE id_user = ? FOR UPDATE');
        $targetStmt->bind_param('i', $targetId);
        $targetStmt->execute();
        $targetStmt->bind_result($targetRole, $targetPhotoPath);
        $targetExists = $targetStmt->fetch();
        $targetStmt->close();

        if (!$targetExists) {
            $conn->rollback();
            set_flash('error', 'Akun tidak ditemukan.');
            redirect('admin.php');
        }

        if ($targetRole === 'admin') {
            $adminsStmt = $conn->query("SELECT id_user FROM users WHERE role = 'admin' FOR UPDATE");
            $adminCount = $adminsStmt->num_rows;
            $adminsStmt->free();

            if ($adminCount <= 1) {
                $conn->rollback();
                set_flash('error', 'Admin terakhir tidak dapat dihapus.');
                redirect('admin.php');
            }
        }

        $logsStmt = $conn->prepare('SELECT COUNT(*) FROM log_interaksi WHERE id_user = ?');
        $logsStmt->bind_param('i', $targetId);
        $logsStmt->execute();
        $logsStmt->bind_result($targetLogCount);
        $logsStmt->fetch();
        $logsStmt->close();

        if ($targetLogCount > 0) {
            $conn->rollback();
            set_flash('error', 'Akun tidak dapat dihapus karena memiliki ' . $targetLogCount . ' log interaksi. Hapus log terlebih dahulu jika ingin menghapus akun.');
            redirect('admin.php');
        }

        $deleteStmt = $conn->prepare('DELETE FROM users WHERE id_user = ?');
        $deleteStmt->bind_param('i', $targetId);
        $deleteStmt->execute();
        $deleted = $deleteStmt->affected_rows;
        $deleteStmt->close();

        if ($deleted !== 1) {
            $conn->rollback();
            set_flash('error', 'Akun tidak ditemukan atau gagal dihapus.');
            redirect('admin.php');
        }

        $conn->commit();
        delete_profile_photo($targetPhotoPath);
        set_flash('success', 'Akun berhasil dihapus.');
        redirect('admin.php');
    }

    if ($action !== 'role') {
        set_flash('error', 'Aksi akun tidak valid.');
        redirect('admin.php');
    }

    $newRole = (string) ($_POST['role'] ?? '');
    if (!$targetId || $targetId < 1 || !in_array($newRole, ['admin', 'user'], true)) {
        set_flash('error', 'Akun atau peran yang dipilih tidak valid.');
        redirect('admin.php');
    }

    $conn->begin_transaction();
    $targetStmt = $conn->prepare('SELECT role FROM users WHERE id_user = ? FOR UPDATE');
    $targetStmt->bind_param('i', $targetId);
    $targetStmt->execute();
    $targetStmt->bind_result($currentRole);
    $targetExists = $targetStmt->fetch();
    $targetStmt->close();

    if (!$targetExists) {
        $conn->rollback();
        set_flash('error', 'Akun tidak ditemukan.');
        redirect('admin.php');
    }

    if ($currentRole === 'admin' && $newRole === 'user') {
        $adminsStmt = $conn->query("SELECT id_user FROM users WHERE role = 'admin' FOR UPDATE");
        $otherAdmins = 0;
        while ($admin = $adminsStmt->fetch_assoc()) {
            if ((int) $admin['id_user'] !== $targetId) {
                $otherAdmins++;
            }
        }

        if ($otherAdmins === 0) {
            $conn->rollback();
            set_flash('error', 'Peran admin terakhir tidak dapat dihapus. Jadikan akun lain admin terlebih dahulu.');
            redirect('admin.php');
        }
    }

    $update = $conn->prepare('UPDATE users SET role = ? WHERE id_user = ?');
    $update->bind_param('si', $newRole, $targetId);
    $update->execute();
    $update->close();
    $conn->commit();
    if ($targetId === $adminId && $newRole === 'user') {
        set_flash('success', 'Peran Anda berubah menjadi relawan. Akses dan navigasi Admin telah dinonaktifkan.');
        redirect('index.php');
    }
    set_flash('success', 'Peran akun berhasil diperbarui.');
    redirect('admin.php');
}

$userStmt = $conn->prepare('SELECT username FROM users WHERE id_user = ?');
$userStmt->bind_param('i', $adminId);
$userStmt->execute();
$userStmt->bind_result($adminUsername);
$userStmt->fetch();
$userStmt->close();

$userCount = (int) $conn->query('SELECT COUNT(*) FROM users')->fetch_row()[0];
$catCount = (int) $conn->query('SELECT COUNT(*) FROM kucing')->fetch_row()[0];
$logCount = (int) $conn->query('SELECT COUNT(*) FROM log_interaksi')->fetch_row()[0];
$adminCount = (int) $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetch_row()[0];
$users = [];
$usersResult = $conn->query(
    'SELECT u.id_user, u.nama_lengkap, u.username, u.role, u.created_at,
            (SELECT COUNT(*) FROM log_interaksi l WHERE l.id_user = u.id_user) AS log_count
     FROM users u
     ORDER BY u.created_at DESC'
);
while ($row = $usersResult->fetch_assoc()) {
    $users[] = $row;
}

render_header('Panel Admin', 'admin', $adminUsername, true);
?>
  <main class="container main-content admin-page">
    <?php render_flash(); ?>
    <section class="page-banner">
      <div>
        <p class="eyebrow">PAWTRACK ADMINISTRATION</p>
        <h1>Panel Administrator</h1>
        <p>Kelola akses relawan dan pantau aktivitas komunitas.</p>
      </div>
      <span class="role-badge role-admin">Administrator</span>
    </section>

    <section class="admin-stats" aria-label="Ringkasan komunitas">
      <article class="admin-stat card"><span class="admin-stat-icon">&#x1F465;</span><span class="admin-stat-value"><?php echo $userCount; ?></span><span class="admin-stat-label">Total Akun</span></article>
      <article class="admin-stat card"><span class="admin-stat-icon">&#x1F431;</span><span class="admin-stat-value"><?php echo $catCount; ?></span><span class="admin-stat-label">Data kucing</span></article>
      <article class="admin-stat card"><span class="admin-stat-icon">&#x1F4CB;</span><span class="admin-stat-value"><?php echo $logCount; ?></span><span class="admin-stat-label">Log interaksi</span></article>
      <article class="admin-stat card"><span class="admin-stat-icon">&#x2699;</span><span class="admin-stat-value"><?php echo $adminCount; ?></span><span class="admin-stat-label">Administrator aktif</span></article>
    </section>

    <section class="admin-users-section">
      <div class="section-header">
        <div>
          <p class="eyebrow">MANAJEMEN AKUN</p>
          <h2 class="section-title">Akun Relawan &amp; Peran</h2>
        </div>
        <a class="btn btn-secondary" href="index.php#data-kucing">Kelola Data Kucing &amp; Log</a>
      </div>
      <div class="search-box search-toolbar">
        <label class="sr-only" for="cari-admin-user">Cari akun relawan</label>
        <input class="input-field" type="search" id="cari-admin-user" placeholder="Cari nama, username, atau peran..." />
        <span class="result-count" id="admin-users-count" role="status">Menampilkan <?php echo count($users); ?> dari <?php echo count($users); ?> akun</span>
      </div>
      <div class="table-container card">
        <table class="data-table admin-users-table" id="tabel-admin-users">
          <thead><tr><th>Nama Relawan</th><th>Username</th><th>Tanggal Bergabung</th><th>Log Dibuat</th><th>Peran</th><th>Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($users as $user): ?>
            <?php $isSelf = (int) $user['id_user'] === $adminId; ?>
            <tr>
              <td><strong><?php echo h($user['nama_lengkap']); ?></strong><?php if ($isSelf): ?><small class="table-subtext">Akun Anda</small><?php endif; ?></td>
              <td>@<?php echo h($user['username']); ?></td>
              <td><?php echo h(date('d M Y', strtotime($user['created_at']))); ?></td>
              <td><?php echo (int) $user['log_count']; ?></td>
              <td><span class="badge role-<?php echo h($user['role']); ?>"><?php echo $user['role'] === 'admin' ? 'Admin' : 'Relawan'; ?></span></td>
              <td>
                <?php $canChangeRole = $isSelf ? $adminCount > 1 : !($user['role'] === 'admin' && $adminCount <= 1); ?>
                <?php if ($canChangeRole): ?>
                  <div class="role-actions">
                    <form action="admin.php" method="post" class="role-form">
                      <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>" />
                      <input type="hidden" name="action" value="role" />
                      <input type="hidden" name="id_user" value="<?php echo (int) $user['id_user']; ?>" />
                      <label class="sr-only" for="role-<?php echo (int) $user['id_user']; ?>">Peran untuk <?php echo h($user['username']); ?></label>
                      <select class="input-field role-select" id="role-<?php echo (int) $user['id_user']; ?>" name="role">
                        <option value="user"<?php echo $user['role'] === 'user' ? ' selected="selected"' : ''; ?>>Relawan</option>
                        <option value="admin"<?php echo $user['role'] === 'admin' ? ' selected="selected"' : ''; ?>>Admin</option>
                      </select>
                      <button class="btn btn-secondary btn-sm" type="submit">Simpan Peran</button>
                    </form>
                    <?php if ($isSelf): ?>
                      <span class="muted-text">Akun aktif dilindungi dari penghapusan</span>
                    <?php elseif ((int) $user['log_count'] === 0): ?>
                      <form action="admin.php" method="post" class="delete-account-form confirm-delete" data-item-name="akun @<?php echo h($user['username']); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>" />
                        <input type="hidden" name="action" value="delete_user" />
                        <input type="hidden" name="id_user" value="<?php echo (int) $user['id_user']; ?>" />
                        <button class="btn btn-danger btn-sm" type="submit">Hapus Akun</button>
                      </form>
                    <?php else: ?>
                      <span class="muted-text">Tidak dapat dihapus: memiliki log</span>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <span class="muted-text"><?php echo $isSelf ? 'Akun aktif dilindungi' : 'Admin terakhir dilindungi'; ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <p class="no-results" id="admin-users-no-results" hidden="hidden">Tidak ada akun yang cocok.</p>
      </div>
      <p class="admin-note">Akun baru otomatis menjadi relawan. Admin terakhir tidak dapat diturunkan; jika admin mengubah perannya sendiri menjadi relawan, akses admin langsung berakhir.</p>
    </section>
  </main>
<?php
render_footer();
