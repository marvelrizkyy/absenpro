<?php
// admin_leaves.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/date_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';

require_admin();
$db = get_db_connection();

$filterStatus = $_GET['status'] ?? 'all';
$filterType = $_GET['type'] ?? 'all';

$sql = "SELECT lr.*, u.nama_lengkap, u.nik, u.jabatan, u.no_wa, ap.nama_lengkap as approver_name
    FROM leave_requests lr
    JOIN users u ON lr.employee_id = u.id
    LEFT JOIN users ap ON lr.decided_by = ap.id
    WHERE 1=1";
$params = [];

if ($filterStatus !== 'all') {
    $sql .= " AND lr.status = :status";
    $params[':status'] = $filterStatus;
}

if ($filterType !== 'all') {
    $sql .= " AND lr.type = :type";
    $params[':type'] = $filterType;
}

$sql .= " ORDER BY lr.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$leaves = $stmt->fetchAll();

$pageTitle = 'Persetujuan Izin & Sakit';
$pageSubtitle = 'Kelola permohonan ketidakhadiran karyawan dan riwayat keputusan';

require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar_admin.php';
require_once __DIR__ . '/views/layouts/top_navbar.php';
?>

<!-- Filter Card -->
<div class="card" style="margin-bottom: 24px;">
    <form method="GET" action="<?= BASE_URL ?>/admin_leaves.php" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 160px;">
            <label class="form-label">Filter Status</label>
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="menunggu" <?= $filterStatus === 'menunggu' ? 'selected' : '' ?>>Menunggu Persetujuan</option>
                <option value="disetujui" <?= $filterStatus === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                <option value="ditolak" <?= $filterStatus === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label class="form-label">Filter Jenis</label>
            <select name="type" class="form-select" onchange="this.form.submit()">
                <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>Semua Jenis (Izin & Sakit)</option>
                <option value="izin" <?= $filterType === 'izin' ? 'selected' : '' ?>>Hanya Izin</option>
                <option value="sakit" <?= $filterType === 'sakit' ? 'selected' : '' ?>>Hanya Sakit</option>
            </select>
        </div>

        <div>
            <a href="<?= BASE_URL ?>/admin_leaves.php" class="btn btn-secondary">
                Reset Filter
            </a>
        </div>
    </form>
</div>

<!-- Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Daftar Pengajuan Izin & Sakit</h2>
        <span class="badge badge-secondary"><?= count($leaves) ?> Permohonan</span>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Jenis</th>
                    <th>Periode Tanggal</th>
                    <th>Hari Efektif</th>
                    <th>Alasan</th>
                    <th>Bukti File</th>
                    <th>Status</th>
                    <th style="text-align: right;">Aksi Keputusan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaves)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            Tidak ada pengajuan izin atau sakit yang sesuai dengan filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leaves as $item): ?>
                        <tr>
                            <td>
                                <strong><?= clean_input($item['nama_lengkap']) ?></strong>
                                <div style="font-size: 0.74rem; color: var(--text-muted);"><?= clean_input($item['jabatan']) ?> (<?= clean_input($item['nik']) ?>)</div>
                            </td>
                            <td>
                                <span class="badge <?= $item['type'] === 'sakit' ? 'badge-danger' : 'badge-blue' ?>">
                                    <?= strtoupper($item['type']) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= format_short_date($item['start_date']) ?></strong>
                                <?php if ($item['start_date'] !== $item['end_date']): ?>
                                    <br><span style="font-size: 0.75rem; color: var(--text-muted);">s/d <?= format_short_date($item['end_date']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= (int)$item['total_days'] ?></strong> Hari Kerja
                            </td>
                            <td>
                                <div style="max-width: 180px; font-size: 0.82rem; word-break: break-word;">
                                    <?= clean_input($item['reason']) ?>
                                </div>
                                <?php if (!empty($item['decision_reason'])): ?>
                                    <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 4px;">
                                        <em>Keputusan: <?= clean_input($item['decision_reason']) ?></em>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($item['evidence_path'])): ?>
                                    <a href="<?= UPLOAD_URL . clean_input($item['evidence_path']) ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding: 4px 8px; font-size: 0.72rem;">
                                        📎 Buka Dokumen
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--text-light); font-size: 0.75rem;">Tanpa Bukti</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['status'] === 'menunggu'): ?>
                                    <span class="badge badge-warning">Menunggu</span>
                                <?php elseif ($item['status'] === 'disetujui'): ?>
                                    <span class="badge badge-success">Disetujui</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Ditolak</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($item['status'] === 'menunggu'): ?>
                                    <div style="display: inline-flex; gap: 6px;">
                                        <form action="<?= BASE_URL ?>/actions/do_admin_action.php" method="POST" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="approve_leave">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="decision_reason" value="Disetujui oleh HR/Admin">
                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Setujui pengajuan <?= $item['type'] ?> dari <?= clean_input($item['nama_lengkap']) ?>?')">
                                                ✓ Setujui
                                            </button>
                                        </form>

                                        <button type="button" class="btn btn-sm btn-danger" onclick="openRejectLeaveModal(<?= $item['id'] ?>, '<?= addslashes(clean_input($item['nama_lengkap'])) ?>', '<?= $item['type'] ?>')">
                                            ✕ Tolak
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        Oleh: <strong><?= clean_input($item['approver_name'] ?? 'Admin') ?></strong>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Reject Leave -->
<div id="rejectLeaveModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Tolak Permohonan Izin / Sakit</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('rejectLeaveModal')">&times;</button>
        </div>

        <form action="<?= BASE_URL ?>/actions/do_admin_action.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject_leave">
            <input type="hidden" name="id" id="reject_leave_id" value="">

            <p style="font-size: 0.88rem; color: var(--navy-800); margin-bottom: 14px;">
                Tolak pengajuan <span id="reject_leave_type" style="font-weight: 700; text-transform: uppercase;"></span> dari: <strong id="reject_leave_emp"></strong>
            </p>

            <div class="form-group">
                <label for="reject_leave_reason" class="form-label required">Alasan Penolakan (Wajib Diisi)</label>
                <textarea id="reject_leave_reason" name="decision_reason" class="form-control" placeholder="Contoh: Jadwal operasional tim padat / bukti dokumen tidak lengkap..." required></textarea>
                <div class="form-hint">Alasan penolakan akan dikirimkan langsung ke nomor WhatsApp karyawan.</div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectLeaveModal')">Batal</button>
                <button type="submit" class="btn btn-danger">Konfirmasi Tolak</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectLeaveModal(id, empName, type) {
    document.getElementById('reject_leave_id').value = id;
    document.getElementById('reject_leave_emp').textContent = empName;
    document.getElementById('reject_leave_type').textContent = type;
    document.getElementById('reject_leave_reason').value = 'Permohonan tidak dapat disetujui karena kebutuhan operasional kerja.';
    openModal('rejectLeaveModal');
}
</script>

<?php
require_once __DIR__ . '/views/layouts/footer.php';
?>
