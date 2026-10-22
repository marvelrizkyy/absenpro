<?php
// employee_leaves.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/date_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';

require_employee();
$user = current_user();
$db = get_db_connection();
$employeeId = (int)$user['id'];

// Fetch employee's leave requests
$stmt = $db->prepare("SELECT lr.*, u.nama_lengkap as approver_name 
    FROM leave_requests lr
    LEFT JOIN users u ON lr.decided_by = u.id
    WHERE lr.employee_id = ?
    ORDER BY lr.created_at DESC");
$stmt->execute([$employeeId]);
$leaves = $stmt->fetchAll();

// Calculate leave balances/counts
$totalApprovedIzin = 0;
$totalApprovedSakit = 0;
$totalPending = 0;

foreach ($leaves as $l) {
    if ($l['status'] === 'disetujui') {
        if ($l['type'] === 'izin') $totalApprovedIzin += (int)$l['total_days'];
        if ($l['type'] === 'sakit') $totalApprovedSakit += (int)$l['total_days'];
    } elseif ($l['status'] === 'menunggu') {
        $totalPending++;
    }
}
$totalDaysTaken = $totalApprovedIzin + $totalApprovedSakit;

$pageTitle = 'Pengajuan Izin & Sakit';
$pageSubtitle = 'Kelola permohonan ketidakhadiran resmi dan kuota cuti';

require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar_employee.php';
require_once __DIR__ . '/views/layouts/top_navbar.php';
?>

<div style="max-width: 680px; margin: 0 auto;">

    <!-- ========================================================
         CIRCULAR LEAVE BALANCE CARD (MATCHES SCREEN 8 IN REFERENCE)
         ======================================================== -->
    <div class="card" style="text-align: center; padding: 32px 20px 24px; margin-bottom: 24px;">
        <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--text-dark); margin-bottom: 20px;">
            Rekap Izin & Sakit Saya
        </h2>

        <!-- Circular Gauge (Screen 8) -->
        <div style="position: relative; width: 140px; height: 140px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
            <svg viewBox="0 0 36 36" style="width: 100%; height: 100%; transform: rotate(-90deg);">
                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#EEF2FF" stroke-width="3.2"/>
                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="url(#leaveGrad)" stroke-width="3.2" stroke-dasharray="<?= min(100, $totalDaysTaken * 10) ?>, 100" stroke-linecap="round"/>
                <defs>
                    <linearGradient id="leaveGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#38BDF8"/>
                        <stop offset="100%" stop-color="#4F46E5"/>
                    </linearGradient>
                </defs>
            </svg>
            <div style="position: absolute; display: flex; flex-direction: column; align-items: center;">
                <span style="font-size: 2.2rem; font-weight: 800; color: var(--text-dark); line-height: 1;"><?= sprintf('%02d', $totalDaysTaken) ?></span>
                <span style="font-size: 0.68rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-top: 2px;">Hari Diambil</span>
            </div>
        </div>

        <!-- Micro stats below circle -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; border-top: 1px solid rgba(241, 245, 249, 0.9); padding-top: 18px;">
            <div>
                <div style="font-size: 1.15rem; font-weight: 800; color: #2563EB;"><?= $totalApprovedIzin ?></div>
                <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Hari Izin</div>
            </div>
            <div>
                <div style="font-size: 1.15rem; font-weight: 800; color: #F43F5E;"><?= $totalApprovedSakit ?></div>
                <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Hari Sakit</div>
            </div>
            <div>
                <div style="font-size: 1.15rem; font-weight: 800; color: #F59E0B;"><?= $totalPending ?></div>
                <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Menunggu HR</div>
            </div>
        </div>
    </div>

    <!-- ========================================================
         FORM PENGAJUAN BARU
         ======================================================== -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <h2 class="card-title">Formulir Pengajuan Baru</h2>
        </div>

        <form action="<?= BASE_URL ?>/actions/do_leave.php" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label for="type" class="form-label required">Jenis Pengajuan</label>
                <select id="type" name="type" class="form-select" required>
                    <option value="izin">Izin Tidak Masuk Kerja</option>
                    <option value="sakit">Sakit (Dengan/Tanpa Surat Dokter)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="start_date" class="form-label required">Tanggal Mulai</label>
                <input type="date" id="start_date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label for="end_date" class="form-label required">Tanggal Selesai</label>
                <input type="date" id="end_date" name="end_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label for="reason" class="form-label required">Alasan / Keperluan</label>
                <textarea id="reason" name="reason" class="form-control" placeholder="Tuliskan alasan izin atau keluhan sakit secara jelas..." required></textarea>
            </div>

            <div class="form-group">
                <label for="evidence" class="form-label">Lampiran Bukti (Opsional)</label>
                <input type="file" id="evidence" name="evidence" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                <div class="form-hint">Maksimal 2 MB (Foto/PDF surat dokter atau dokumen penjelas).</div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 8px;">
                Kirim Permohonan ke HR
            </button>
        </form>
    </div>

    <!-- ========================================================
         DAFTAR PENGAJUAN (NOTIFICATION / LEAVE CARDS STYLE)
         ======================================================== -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding: 0 4px;">
        <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-dark);">Riwayat Pengajuan</h3>
        <span class="badge badge-secondary"><?= count($leaves) ?> Catatan</span>
    </div>

    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php if (empty($leaves)): ?>
            <div class="card" style="text-align: center; padding: 32px; color: var(--text-muted); font-size: 0.88rem;">
                Belum ada riwayat permohonan izin atau sakit.
            </div>
        <?php else: ?>
            <?php foreach ($leaves as $item): ?>
                <div class="card" style="padding: 18px 20px; margin-bottom: 0;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <span class="badge <?= $item['type'] === 'sakit' ? 'badge-danger' : 'badge-blue' ?>">
                                    <?= strtoupper($item['type']) ?>
                                </span>
                                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">
                                    <?= (int)$item['total_days'] ?> Hari Kerja
                                </span>
                            </div>

                            <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-dark);">
                                <?= format_short_date($item['start_date']) ?>
                                <?php if ($item['start_date'] !== $item['end_date']): ?>
                                    s/d <?= format_short_date($item['end_date']) ?>
                                <?php endif; ?>
                            </div>

                            <div style="font-size: 0.84rem; color: var(--text-muted); margin-top: 6px; line-height: 1.4;">
                                <?= clean_input($item['reason']) ?>
                            </div>

                            <?php if (!empty($item['decision_reason'])): ?>
                                <div style="font-size: 0.78rem; color: #DC2626; margin-top: 6px; background: #FFF1F2; padding: 6px 10px; border-radius: var(--radius-sm);">
                                    <strong>Catatan HR:</strong> <?= clean_input($item['decision_reason']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Status & Action Buttons -->
                        <div style="text-align: right; flex-shrink: 0;">
                            <?php if ($item['status'] === 'menunggu'): ?>
                                <span class="badge badge-warning" style="margin-bottom: 8px;">Menunggu</span>
                                <form action="<?= BASE_URL ?>/actions/do_leave.php" method="POST" onsubmit="return confirm('Batalkan pengajuan ini?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" style="display: block; width: 100%;">
                                        Batalkan
                                    </button>
                                </form>
                            <?php elseif ($item['status'] === 'disetujui'): ?>
                                <span class="badge badge-success">Disetujui</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Ditolak</span>
                            <?php endif; ?>

                            <?php if (!empty($item['evidence_path'])): ?>
                                <a href="<?= UPLOAD_URL . clean_input($item['evidence_path']) ?>" target="_blank" style="display: block; font-size: 0.75rem; color: #4F46E5; font-weight: 700; margin-top: 8px;">
                                    📎 Buka File
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php
require_once __DIR__ . '/views/layouts/footer.php';
?>
