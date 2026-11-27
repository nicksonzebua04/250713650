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
$ownershipLabels = [
    'liar' => 'Liar',
    'peliharaan' => 'Ada pemilik / dipelihara',
    'belum_diketahui' => 'Belum diketahui',
];
$statusOptions = [
    'hilang' => 'Hilang',
    'sakit' => 'Sakit',
    'lainnya' => 'Lainnya',
];

$cats = [];
$catResult = $conn->query('SELECT id_kucing, nama, ciri_fisik, lokasi_nongkrong, status, kepemilikan, keterangan_status, foto_path FROM kucing ORDER BY created_at DESC');
while ($row = $catResult->fetch_assoc()) {
    $cats[] = $row;
}

$editCat = null;
$editCatId = filter_var($_GET['edit_kucing'] ?? null, FILTER_VALIDATE_INT);
if ($editCatId && $editCatId > 0) {
    if (!$isAdmin) {
        set_flash('error', 'Data kucing hanya dapat diubah oleh admin.');
        redirect('index.php#data-kucing');
    }

    $stmt = $conn->prepare('SELECT id_kucing, nama, ciri_fisik, lokasi_nongkrong, status, kepemilikan, keterangan_status, foto_path FROM kucing WHERE id_kucing = ?');
    $stmt->bind_param('i', $editCatId);
    $stmt->execute();
    $editCat = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}
if ($editCat && in_array($editCat['status'], ['aktif', 'diadopsi'], true)) {
    $currentStatusLabel = $editCat['status'] === 'aktif' ? 'Aktif' : 'Diadopsi';
    $statusOptions = [$editCat['status'] => $currentStatusLabel . ' (status saat ini)'] + $statusOptions;
}

render_header('Data Kucing', 'cats', $username, $isAdmin);
?>
  <main class="container main-content">
    <?php render_flash(); ?>
    <section class="page-banner">
      <div>
        <p class="eyebrow">PAWTRACK COMMUNITY</p>
        <h1>Stray Cat Tracker</h1>
        <p>Halo, <?php echo h($userName); ?>. Pantau dan sayangi kucing di sekitar kita.</p>
      </div>
      <span class="role-badge role-<?php echo h($role); ?>"><?php echo $isAdmin ? 'Administrator' : 'Relawan'; ?></span>
    </section>

    <section class="crud-section" id="data-kucing">
      <div class="section-header">
        <div>
          <p class="eyebrow">KENALI MEREKA</p>
          <h2 class="section-title">Daftar Kucing Terdata di Lingkungan</h2>
        </div>
        <a class="btn btn-primary" href="#form-kucing">&#x2295; Tambah Kucing Baru</a>
      </div>
      <div class="search-box search-toolbar">
        <label class="sr-only" for="cari-kucing">Cari data kucing</label>
        <input class="input-field" type="search" id="cari-kucing" placeholder="Cari nama, ciri, atau lokasi..." />
        <span class="result-count" id="cats-count" role="status">Menampilkan <?php echo count($cats); ?> dari <?php echo count($cats); ?> kucing</span>
      </div>
      <div class="cat-status-filters" role="group" aria-label="Filter cepat berdasarkan status kucing">
        <button class="cat-status-filter is-active" type="button" data-cat-filter="semua" aria-pressed="true">Semua</button>
        <button class="cat-status-filter" type="button" data-cat-filter="hilang" aria-pressed="false">Hilang</button>
        <button class="cat-status-filter" type="button" data-cat-filter="sakit" aria-pressed="false">Sakit</button>
        <button class="cat-status-filter" type="button" data-cat-filter="diadopsi" aria-pressed="false">Ditemukan / Selesai</button>
        <button class="cat-status-filter" type="button" data-cat-filter="lainnya" aria-pressed="false">Lainnya</button>
      </div>
      <div class="table-container card">
        <table class="data-table" id="tabel-kucing">
          <thead><tr><th>Foto</th><th>Nama Kucing</th><th>Ciri Fisik</th><th>Lokasi</th><th>Status Kucing</th><th>Keterangan Status</th><th>Kepemilikan</th><?php if ($isAdmin): ?><th>Aksi</th><?php endif; ?></tr></thead>
          <tbody>
          <?php foreach ($cats as $cat): ?>
            <tr data-status="<?php echo h($cat['status']); ?>">
              <td><?php if ($cat['foto_path']): ?><img class="cat-photo cat-photo-image" src="<?php echo h($cat['foto_path']); ?>" alt="Foto <?php echo h($cat['nama']); ?>" loading="lazy" /><?php else: ?><span class="cat-photo" aria-label="Foto kucing belum tersedia">&#x1F431;</span><?php endif; ?></td>
              <td><strong><?php echo h($cat['nama']); ?></strong><small class="table-subtext">ID: KCG-<?php echo str_pad((string) $cat['id_kucing'], 2, '0', STR_PAD_LEFT); ?></small></td>
              <td><?php echo h($cat['ciri_fisik']); ?></td>
              <td><span class="location-name"><?php echo h($cat['lokasi_nongkrong']); ?></span></td>
              <td><span class="badge badge-<?php echo h($cat['status']); ?>"><?php echo h(['aktif' => 'Aktif', 'hilang' => 'Hilang', 'sakit' => 'Sakit', 'lainnya' => 'Lainnya', 'diadopsi' => 'Sudah Diadopsi'][$cat['status']] ?? ucfirst($cat['status'])); ?></span></td>
              <td><?php echo $cat['keterangan_status'] !== null && $cat['keterangan_status'] !== '' ? h($cat['keterangan_status']) : '<span class="muted-text">Tidak ada keterangan</span>'; ?></td>
              <td><span class="badge badge-kepemilikan badge-<?php echo h($cat['kepemilikan']); ?>"><?php echo h($ownershipLabels[$cat['kepemilikan']] ?? $ownershipLabels['belum_diketahui']); ?></span></td>
              <?php if ($isAdmin): ?>
              <td>
                <div class="action-btns action-stack">
                  <a class="btn btn-secondary btn-sm" href="index.php?edit_kucing=<?php echo (int) $cat['id_kucing']; ?>#form-kucing">Ubah</a>
                  <form action="cat_action.php" method="post" class="confirm-delete" data-item-name="<?php echo h($cat['nama']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>" />
                    <input type="hidden" name="aksi" value="hapus" />
                    <input type="hidden" name="id_kucing" value="<?php echo (int) $cat['id_kucing']; ?>" />
                    <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                  </form>
                </div>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (!$cats): ?>
          <div class="empty-state" id="cats-empty-state"><div class="empty-icon">&#x1F431;</div><p>Belum ada data kucing.</p><a class="text-link" href="#form-kucing">Tambahkan kucing pertama &#x2192;</a></div>
        <?php endif; ?>
        <p class="no-results" id="cats-no-results" hidden="hidden">Tidak ada kucing yang cocok dengan pencarian atau filter.</p>
        <nav class="cat-pagination" id="cats-pagination" aria-label="Navigasi halaman daftar kucing" hidden="hidden"></nav>
      </div>

      <div class="form-card card" id="form-kucing">
        <div class="form-card-heading">
          <div><p class="eyebrow"><?php echo $editCat ? 'PERBARUI DATA' : 'TAMBAHKAN KE KOMUNITAS'; ?></p><h3><?php echo $editCat ? 'Ubah data kucing' : 'Tambah Kucing Baru'; ?></h3></div>
          <?php if ($editCat): ?><a class="text-link" href="index.php#data-kucing">Batal mengubah</a><?php endif; ?>
        </div>
        <form action="cat_action.php" method="post" id="form-cat" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>" />
          <input type="hidden" name="aksi" value="<?php echo $editCat ? 'edit' : 'tambah'; ?>" />
          <?php if ($editCat): ?><input type="hidden" name="id_kucing" value="<?php echo (int) $editCat['id_kucing']; ?>" /><?php endif; ?>
          <div class="form-grid">
            <div class="form-group form-span">
              <label class="form-label" for="cat-photo-file">Tambahkan foto kucing<?php if (!$editCat): ?> <span class="required-mark">*</span><?php endif; ?></label>
              <input class="input-field" type="file" id="cat-photo-file" name="foto" accept="image/jpeg,image/png,image/webp" aria-describedby="cat-photo-hint"<?php if (!$editCat): ?> required="required"<?php endif; ?> />
              <small class="field-hint" id="cat-photo-hint">Pilih JPG, PNG, atau WebP dari galeri/folder di ponsel atau File Explorer di laptop. Maksimal 5 MB.</small>
              <?php if (!empty($editCat['foto_path'])): ?>
                <img class="cat-photo-preview" id="cat-photo-preview" src="<?php echo h($editCat['foto_path']); ?>" alt="Pratinjau foto kucing" />
              <?php else: ?>
                <img class="cat-photo-preview" id="cat-photo-preview" alt="Pratinjau foto kucing" hidden="hidden" />
              <?php endif; ?>
            </div>
            <div class="form-group">
              <label class="form-label" for="cat-name">Nama kucing <span class="required-mark">*</span></label>
              <input class="input-field" type="text" id="cat-name" name="nama" maxlength="100" required="required" value="<?php echo h($editCat['nama'] ?? ''); ?>" />
            </div>
            <div class="form-group">
              <label class="form-label" for="cat-status">Status <span class="required-mark">*</span></label>
              <select class="input-field" id="cat-status" name="status" required="required">
                <?php if (!$editCat): ?><option value="" selected="selected" disabled="disabled">Pilih status</option><?php endif; ?>
                <?php foreach ($statusOptions as $value => $label): ?>
                  <option value="<?php echo h($value); ?>"<?php echo ($editCat['status'] ?? 'aktif') === $value ? ' selected="selected"' : ''; ?>><?php echo h($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="cat-status-detail">Keterangan status</label>
              <textarea class="input-field" id="cat-status-detail" name="keterangan_status" rows="3" maxlength="255" aria-describedby="cat-status-detail-hint"><?php echo h($editCat['keterangan_status'] ?? ''); ?></textarea>
              <small class="field-hint" id="cat-status-detail-hint">Opsional untuk Hilang atau Sakit; wajib diisi untuk Lainnya.</small>
            </div>
            <div class="form-group">
              <label class="form-label" for="cat-ownership">Kepemilikan</label>
              <select class="input-field" id="cat-ownership" name="kepemilikan" required="required">
                <?php foreach ($ownershipLabels as $value => $label): ?>
                  <option value="<?php echo h($value); ?>"<?php echo ($editCat['kepemilikan'] ?? 'belum_diketahui') === $value ? ' selected="selected"' : ''; ?>><?php echo h($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="cat-description">Ciri fisik</label>
              <textarea class="input-field" id="cat-description" name="ciri_fisik" rows="3"><?php echo h($editCat['ciri_fisik'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label" for="cat-location">Lokasi Nongkrong</label>
              <input class="input-field" type="text" id="cat-location" name="lokasi_nongkrong" maxlength="255" value="<?php echo h($editCat['lokasi_nongkrong'] ?? ''); ?>" />
            </div>
          </div>
          <button class="btn btn-primary" type="submit"><?php echo $editCat ? 'Simpan perubahan' : 'Simpan Data Kucing'; ?></button>
        </form>
      </div>
    </section>

  </main>
<?php
render_footer();
