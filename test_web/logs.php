<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
$userId = require_login();
ensure_cat_schema();

$userStmt = $conn->prepare('SELECT nama_lengkap, username, role FROM users WHERE id_user = ?');
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userStmt->bind_result($userName, $username, $role);
if (!$userStmt->fetch()) {
    $userStmt->close();
    $_SESSION = [];
    session_destroy();
    redirect('login.xhtml');
}
$userStmt->close();
$isAdmin = $role === 'admin';

$cats = [];
$catResult = $conn->query('SELECT id_kucing, nama FROM kucing ORDER BY created_at DESC');
while ($row = $catResult->fetch_assoc()) {
    $cats[] = $row;
}

$logs = [];
$logStmt = $conn->prepare(
    'SELECT l.id_log, l.id_user, l.id_kucing, l.jenis_interaksi, l.catatan, l.latitude, l.longitude, l.waktu_kejadian, u.username, k.nama AS nama_kucing
     FROM log_interaksi l
     INNER JOIN users u ON u.id_user = l.id_user
     INNER JOIN kucing k ON k.id_kucing = l.id_kucing
     ORDER BY l.waktu_kejadian DESC
     LIMIT 50'
);
$logStmt->execute();
$logResult = $logStmt->get_result();
while ($row = $logResult->fetch_assoc()) {
    $logs[] = $row;
}
$logStmt->close();

$editLog = null;
$editLogId = filter_var($_GET['edit_log'] ?? null, FILTER_VALIDATE_INT);
if ($editLogId && $editLogId > 0) {
    if ($isAdmin) {
        $stmt = $conn->prepare(
            'SELECT id_log, id_kucing, jenis_interaksi, catatan, latitude, longitude
             FROM log_interaksi WHERE id_log = ?'
        );
        $stmt->bind_param('i', $editLogId);
    } else {
        $stmt = $conn->prepare(
            'SELECT id_log, id_kucing, jenis_interaksi, catatan, latitude, longitude
             FROM log_interaksi WHERE id_log = ? AND id_user = ?'
        );
        $stmt->bind_param('ii', $editLogId, $userId);
    }
    $stmt->execute();
    $editLog = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

render_header('Log Interaksi', 'logs', $username, $isAdmin);
?>
  <main class="container main-content">
    <?php render_flash(); ?>
    <section class="page-banner">
      <div>
        <p class="eyebrow">CERITA TERBARU</p>
        <h1>Timeline &amp; Log Interaksi Bersama Kucing</h1>
        <p>Halo, <?php echo h($userName); ?>. Catat dan pantau aktivitas komunitas.</p>
      </div>
      <span class="role-badge role-<?php echo h($role); ?>"><?php echo $isAdmin ? 'Administrator' : 'Relawan'; ?></span>
    </section>

    <section class="crud-section" id="log-interaksi">
      <div class="section-header">
        <div>
          <p class="eyebrow">TIMELINE KOMUNITAS</p>
          <h2 class="section-title">Aktivitas Terkini</h2>
        </div>
        <a class="btn btn-primary" href="#form-log">&#x1F4CB; Catat Interaksi Baru</a>
      </div>
      <div class="search-box search-toolbar">
        <label class="sr-only" for="cari-log">Cari log interaksi</label>
        <input class="input-field" type="search" id="cari-log" placeholder="Cari nama kucing, aktivitas, catatan, atau pelapor..." />
        <span class="result-count" id="logs-count" role="status">Menampilkan <?php echo count($logs); ?> dari <?php echo count($logs); ?> log</span>
      </div>
      <div class="table-container card">
        <div class="log-table-heading"><h3>Timeline Aktivitas Terkini</h3><span>Diurutkan dari yang terbaru</span></div>
        <table class="data-table log-table" id="tabel-log">
          <thead><tr><th>Waktu</th><th>Nama Kucing</th><th>Jenis Interaksi</th><th>Catatan Kondisi / Lokasi</th><th>Pelapor (Users)</th><th>Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><time datetime="<?php echo h(date('c', strtotime($log['waktu_kejadian']))); ?>"><?php echo h(date('d M Y, H:i', strtotime($log['waktu_kejadian']))); ?> WIB</time></td>
              <td><strong><?php echo h($log['nama_kucing']); ?></strong></td>
              <td><span class="badge badge-<?php echo h($log['jenis_interaksi']); ?>"><?php
                $typeLabels = ['penampakan' => 'Penampakan', 'beri_makan' => 'Memberi Makan', 'bermain' => 'Mengelus / Bermain', 'lainnya' => 'Lainnya'];
                echo h($typeLabels[$log['jenis_interaksi']] ?? 'Aktivitas');
              ?></span></td>
              <td><?php echo $log['catatan'] !== null && $log['catatan'] !== '' ? nl2br(h($log['catatan'])) : '<span class="muted-text">Tidak ada catatan</span>'; ?>
                <?php if ($log['latitude'] !== null && $log['longitude'] !== null): ?><small class="table-subtext"><?php echo h((string) $log['latitude']); ?>, <?php echo h((string) $log['longitude']); ?></small><?php endif; ?>
              </td>
              <td><span class="reporter">@<?php echo h($log['username']); ?></span><?php if ((int) $log['id_user'] === $userId): ?><small class="table-subtext">(Saya)</small><?php endif; ?></td>
              <td>
                <?php if ($isAdmin || (int) $log['id_user'] === $userId): ?>
                  <div class="action-btns action-stack">
                    <a class="btn btn-secondary btn-sm" href="logs.php?edit_log=<?php echo (int) $log['id_log']; ?>#form-log">Ubah</a>
                    <form action="interaction_action.php" method="post" class="confirm-delete" data-item-name="catatan ini">
                      <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>" />
                      <input type="hidden" name="aksi" value="hapus" />
                      <input type="hidden" name="id_log" value="<?php echo (int) $log['id_log']; ?>" />
                      <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                    </form>
                  </div>
                <?php else: ?>
                  <span class="muted-text">Hanya Melihat</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (!$logs): ?><div class="empty-state" id="logs-empty-state"><div class="empty-icon">&#x1F4D6;</div><p>Belum ada aktivitas.</p><a class="text-link" href="#form-log">Catat interaksi pertama &#x2192;</a></div><?php endif; ?>
        <p class="no-results" id="logs-no-results" hidden="hidden">Belum ada log interaksi.</p>
      </div>

      <div class="form-card card" id="form-log">
        <div class="form-card-heading">
          <div><p class="eyebrow"><?php echo $editLog ? 'PERBARUI CATATAN' : 'BAGIKAN KABAR'; ?></p><h3><?php echo $editLog ? 'Ubah aktivitas' : 'Catat Interaksi Baru'; ?></h3></div>
          <?php if ($editLog): ?><a class="text-link" href="logs.php#log-interaksi">Batal mengubah</a><?php endif; ?>
        </div>
        <?php if (!$cats): ?>
          <p class="form-note">Tambahkan data kucing terlebih dahulu sebelum membuat catatan aktivitas.</p>
        <?php else: ?>
        <form action="interaction_action.php" method="post" id="form-interaction">
          <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>" />
          <input type="hidden" name="aksi" value="<?php echo $editLog ? 'edit' : 'tambah'; ?>" />
          <?php if ($editLog): ?><input type="hidden" name="id_log" value="<?php echo (int) $editLog['id_log']; ?>" /><?php endif; ?>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label" for="log-cat">Pilih kucing <span class="required-mark">*</span></label>
              <select class="input-field" id="log-cat" name="id_kucing" required="required">
                <option value="">Pilih kucing</option>
                <?php foreach ($cats as $cat): ?>
                  <option value="<?php echo (int) $cat['id_kucing']; ?>"<?php echo (int) ($editLog['id_kucing'] ?? 0) === (int) $cat['id_kucing'] ? ' selected="selected"' : ''; ?>><?php echo h($cat['nama']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="log-type">Jenis aktivitas <span class="required-mark">*</span></label>
              <select class="input-field" id="log-type" name="jenis_interaksi" required="required">
                <option value="">Pilih aktivitas</option>
                <?php foreach (['penampakan' => 'Penampakan', 'beri_makan' => 'Memberi makan', 'bermain' => 'Bermain', 'lainnya' => 'Lainnya'] as $value => $label): ?>
                  <option value="<?php echo h($value); ?>"<?php echo ($editLog['jenis_interaksi'] ?? '') === $value ? ' selected="selected"' : ''; ?>><?php echo h($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group form-span">
              <label class="form-label" for="log-note">Catatan</label>
              <textarea class="input-field" id="log-note" name="catatan" rows="3"><?php echo h($editLog['catatan'] ?? ''); ?></textarea>
            </div>
            <div class="form-group gps-section form-span">
              <div><strong>Tambahkan titik lokasi (opsional)</strong><p class="field-hint">Browser akan meminta izin lokasi.</p></div>
              <div class="gps-fields">
                <label class="sr-only" for="latitude">Latitude</label>
                <input class="input-field gps-input" type="text" id="latitude" name="latitude" readonly="readonly" value="<?php echo h((string) ($editLog['latitude'] ?? '')); ?>" />
                <label class="sr-only" for="longitude">Longitude</label>
                <input class="input-field gps-input" type="text" id="longitude" name="longitude" readonly="readonly" value="<?php echo h((string) ($editLog['longitude'] ?? '')); ?>" />
              </div>
              <button class="btn btn-gps" type="button" id="btn-gps">Ambil titik lokasi</button>
              <p id="gps-status" class="gps-status"></p>
            </div>
          </div>
          <button class="btn btn-primary" type="submit"><?php echo $editLog ? 'Simpan perubahan' : 'Simpan catatan'; ?></button>
        </form>
        <?php endif; ?>
      </div>
    </section>
  </main>
<?php
render_footer();
