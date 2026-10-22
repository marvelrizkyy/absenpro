<?php
// admin_employees.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/date_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';

require_admin();
$admin = current_user();
$db = get_db_connection();

$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? 'all';

$sql = "SELECT * FROM users WHERE role = 'karyawan'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (nama_lengkap LIKE :s_nama OR nik LIKE :s_nik OR email LIKE :s_email OR jabatan LIKE :s_jabatan)";
    $searchVal = "%{$search}%";
    $params[':s_nama'] = $searchVal;
    $params[':s_nik'] = $searchVal;
    $params[':s_email'] = $searchVal;
    $params[':s_jabatan'] = $searchVal;
}

if ($filterStatus === 'menunggu') {
    $sql .= " AND account_status = 'menunggu'";
} elseif ($filterStatus === 'disetujui') {
    $sql .= " AND account_status = 'disetujui' AND is_active = 1";
} elseif ($filterStatus === 'ditolak') {
    $sql .= " AND account_status = 'ditolak'";
} elseif ($filterStatus === 'archived') {
    $sql .= " AND is_active = 0";
}

$sql .= " ORDER BY created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$pageTitle = 'Manajemen Data Karyawan';
$pageSubtitle = 'Kelola profil, verifikasi pendaftaran, dan status keaktifan staf';

require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar_admin.php';
require_once __DIR__ . '/views/layouts/top_navbar.php';
?>

<!-- Header Action & Filter Bar -->
<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <form method="GET" action="<?= BASE_URL ?>/admin_employees.php" style="display: flex; gap: 12px; flex: 1; min-width: 280px; flex-wrap: wrap;">
            <input type="text" name="search" class="form-control" placeholder="Cari berdasarkan Nama, NIK, Jabatan..." value="<?= clean_input($search) ?>" style="flex: 2; min-width: 200px;">
            <select name="status" class="form-select" style="flex: 1; min-width: 140px;" onchange="this.form.submit()">
                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="disetujui" <?= $filterStatus === 'disetujui' ? 'selected' : '' ?>>Aktif & Disetujui</option>
                <option value="menunggu" <?= $filterStatus === 'menunggu' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                <option value="ditolak" <?= $filterStatus === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                <option value="archived" <?= $filterStatus === 'archived' ? 'selected' : '' ?>>Diarsipkan (Nonaktif)</option>
            </select>
            <button type="submit" class="btn btn-secondary">Cari</button>
            <?php if (!empty($search) || $filterStatus !== 'all'): ?>
                <a href="<?= BASE_URL ?>/admin_employees.php" class="btn btn-sm btn-secondary" style="display: flex; align-items: center;">Reset</a>
            <?php endif; ?>
        </form>

        <button type="button" class="btn btn-primary" onclick="openAddEmployeeModal()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Tambah Karyawan
        </button>
    </div>
</div>

<!-- Employee List Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Daftar Data Karyawan</h2>
        <span class="badge badge-secondary"><?= count($employees) ?> Karyawan Ditemukan</span>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>NIK</th>
                    <th>Nama Karyawan</th>
                    <th>Jabatan</th>
                    <th>WhatsApp</th>
                    <th>Status Akun</th>
                    <th>Keaktifan</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            Tidak ada data karyawan yang sesuai dengan kriteria filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><strong><?= clean_input($emp['nik']) ?></strong></td>
                            <td>
                                <a href="<?= BASE_URL ?>/admin_employee_detail.php?id=<?= $emp['id'] ?>" style="font-weight: 700; color: var(--navy-900);">
                                    <?= clean_input($emp['nama_lengkap']) ?>
                                </a>
                                <div style="font-size: 0.74rem; color: var(--text-muted);"><?= clean_input($emp['email']) ?></div>
                            </td>
                            <td><?= clean_input($emp['jabatan']) ?></td>
                            <td>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $emp['no_wa']) ?>" target="_blank" style="color: var(--success-600); font-weight: 600;">
                                    <?= clean_input($emp['no_wa']) ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($emp['account_status'] === 'disetujui'): ?>
                                    <span class="badge badge-success">Disetujui</span>
                                <?php elseif ($emp['account_status'] === 'menunggu'): ?>
                                    <span class="badge badge-warning">Menunggu</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Ditolak</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($emp['is_active'] == 1): ?>
                                    <span class="badge badge-blue">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Diarsipkan</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px; align-items: center;">
                                    <?php if ($emp['account_status'] === 'menunggu'): ?>
                                        <form action="<?= BASE_URL ?>/actions/do_admin_action.php" method="POST" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="approve_user">
                                            <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success" title="Setujui pendaftaran">
                                                ✓ Setujui
                                            </button>
                                        </form>

                                        <button type="button" class="btn btn-sm btn-danger" onclick="openRejectModal(<?= $emp['id'] ?>, '<?= addslashes(clean_input($emp['nama_lengkap'])) ?>')" title="Tolak pendaftaran">
                                            ✕ Tolak
                                        </button>
                                    <?php endif; ?>

                                    <a href="<?= BASE_URL ?>/admin_employee_detail.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-secondary" title="Lihat profil detail">
                                        Detail
                                    </a>

                                    <button type="button" class="btn btn-sm btn-secondary" onclick='openEditEmployeeModal(<?= json_encode($emp) ?>)' title="Edit data">
                                        Edit
                                    </button>

                                    <form action="<?= BASE_URL ?>/actions/do_admin_action.php" method="POST" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin <?= $emp['is_active'] == 1 ? 'mengarsipkan' : 'mengaktifkan kembali' ?> karyawan <?= clean_input($emp['nama_lengkap']) ?>?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_archive_user">
                                        <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $emp['is_active'] == 1 ? 'btn-secondary' : 'btn-primary' ?>" title="<?= $emp['is_active'] == 1 ? 'Arsipkan Karyawan' : 'Aktifkan Kembali' ?>">
                                            <?= $emp['is_active'] == 1 ? 'Arsipkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>

                                    <form action="<?= BASE_URL ?>/actions/do_admin_action.php" method="POST" style="display: inline;" onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin MENGHAPUS PERMANEN data karyawan <?= addslashes(clean_input($emp['nama_lengkap'])) ?> (NIK: <?= clean_input($emp['nik']) ?>)?\n\nSemua riwayat kehadiran, absensi, dan pengajuan izin karyawan ini akan ikut terhapus selamanya.')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Hapus Data Karyawan Secara Permanen">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add / Edit Employee -->
<div id="employeeModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="employeeModalTitle">Tambah Karyawan Baru</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('employeeModal')">&times;</button>
        </div>

        <form action="<?= BASE_URL ?>/actions/do_admin_action.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_employee">
            <input type="hidden" name="id" id="emp_id" value="0">

            <div class="form-group">
                <label for="emp_nik" class="form-label required">Nomor Induk Karyawan (NIK)</label>
                <input type="text" id="emp_nik" name="nik" class="form-control" placeholder="Contoh: KRY005" required>
            </div>

            <div class="form-group">
                <label for="emp_nama" class="form-label required">Nama Lengkap</label>
                <input type="text" id="emp_nama" name="nama_lengkap" class="form-control" placeholder="Nama lengkap karyawan..." required>
            </div>

            <div class="form-group">
                <label for="emp_jabatan" class="form-label required">Jabatan / Posisi</label>
                <input type="text" id="emp_jabatan" name="jabatan" class="form-control" placeholder="Contoh: Staff Akuntansi" required>
            </div>

            <div class="form-group">
                <label for="emp_no_wa" class="form-label required">Nomor WhatsApp</label>
                <input type="text" id="emp_no_wa" name="no_wa" class="form-control" placeholder="081234567890" required>
            </div>

            <div class="form-group">
                <label for="emp_email" class="form-label required">Alamat Email</label>
                <input type="email" id="emp_email" name="email" class="form-control" placeholder="karyawan@perusahaan.com" required>
            </div>

            <div class="form-group">
                <label for="emp_password" class="form-label" id="emp_password_label">Kata Sandi</label>
                <input type="password" id="emp_password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah sandi...">
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('employeeModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Karyawan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reject Registration -->
<div id="rejectModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Tolak Pendaftaran Karyawan</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('rejectModal')">&times;</button>
        </div>

        <form action="<?= BASE_URL ?>/actions/do_admin_action.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject_user">
            <input type="hidden" name="id" id="reject_user_id" value="">

            <p style="font-size: 0.88rem; color: var(--navy-800); margin-bottom: 14px;">
                Tolak permohonan akun dari: <strong id="reject_user_name"></strong>
            </p>

            <div class="form-group">
                <label for="rejection_reason" class="form-label required">Alasan Penolakan</label>
                <textarea id="rejection_reason" name="rejection_reason" class="form-control" placeholder="Tuliskan alasan penolakan untuk dikirimkan melalui WhatsApp..." required></textarea>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Batal</button>
                <button type="submit" class="btn btn-danger">Konfirmasi Penolakan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddEmployeeModal() {
    document.getElementById('employeeModalTitle').textContent = 'Tambah Karyawan Baru';
    document.getElementById('emp_id').value = '0';
    document.getElementById('emp_nik').value = '';
    document.getElementById('emp_nama').value = '';
    document.getElementById('emp_jabatan').value = '';
    document.getElementById('emp_no_wa').value = '';
    document.getElementById('emp_email').value = '';
    document.getElementById('emp_password').value = 'karyawan123';
    document.getElementById('emp_password_label').textContent = 'Kata Sandi Awal (Default: karyawan123)';
    openModal('employeeModal');
}

function openEditEmployeeModal(emp) {
    document.getElementById('employeeModalTitle').textContent = 'Edit Data Karyawan';
    document.getElementById('emp_id').value = emp.id;
    document.getElementById('emp_nik').value = emp.nik;
    document.getElementById('emp_nama').value = emp.nama_lengkap;
    document.getElementById('emp_jabatan').value = emp.jabatan;
    document.getElementById('emp_no_wa').value = emp.no_wa;
    document.getElementById('emp_email').value = emp.email;
    document.getElementById('emp_password').value = '';
    document.getElementById('emp_password_label').textContent = 'Ganti Kata Sandi (Kosongkan jika tidak diubah)';
    openModal('employeeModal');
}

function openRejectModal(userId, userName) {
    document.getElementById('reject_user_id').value = userId;
    document.getElementById('reject_user_name').textContent = userName;
    document.getElementById('rejection_reason').value = 'Data identitas karyawan belum valid atau belum diverifikasi oleh HR.';
    openModal('rejectModal');
}
</script>

<?php
require_once __DIR__ . '/views/layouts/footer.php';
?>
