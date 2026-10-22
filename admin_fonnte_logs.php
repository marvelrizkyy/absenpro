<?php
// admin_fonnte_logs.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/date_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';

require_admin();
$db = get_db_connection();

$filterStatus = $_GET['status'] ?? 'all';
$filterEvent = $_GET['event'] ?? 'all';

$sql = "SELECT nl.*, u.nama_lengkap as user_name 
    FROM notification_logs nl 
    LEFT JOIN users u ON nl.user_id = u.id 
    WHERE 1=1";
$params = [];

if ($filterStatus !== 'all') {
    $sql .= " AND nl.delivery_status = :status";
    $params[':status'] = $filterStatus;
}

if ($filterEvent !== 'all') {
    $sql .= " AND nl.event_type = :event";
    $params[':event'] = $filterEvent;
}

$sql .= " ORDER BY nl.sent_at DESC LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique event types for filter dropdown
$stmtEvents = $db->query("SELECT DISTINCT event_type FROM notification_logs ORDER BY event_type ASC");
$eventTypes = $stmtEvents->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Log Notifikasi WhatsApp Fonnte';
$pageSubtitle = 'Audit riwayat pengiriman pesan gateway dan penanganan kegagalan';

require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar_admin.php';
require_once __DIR__ . '/views/layouts/top_navbar.php';
?>

<!-- Filter Card -->
<div class="card" style="margin-bottom: 24px;">
    <form method="GET" action="<?= BASE_URL ?>/admin_fonnte_logs.php" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 160px;">
            <label class="form-label">Status Pengiriman</label>
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="sent" <?= $filterStatus === 'sent' ? 'selected' : '' ?>>Terkirim (Sent)</option>
                <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>Gagal (Failed / Simulasi)</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label class="form-label">Tipe Event</label>
            <select name="event" class="form-select" onchange="this.form.submit()">
                <option value="all" <?= $filterEvent === 'all' ? 'selected' : '' ?>>Semua Tipe Event</option>
                <?php foreach ($eventTypes as $evt): ?>
                    <option value="<?= clean_input($evt) ?>" <?= $filterEvent === $evt ? 'selected' : '' ?>>
                        <?= clean_input($evt) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <a href="<?= BASE_URL ?>/admin_fonnte_logs.php" class="btn btn-secondary">
                Reset Filter
            </a>
        </div>
    </form>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Histori Aktivitas Gateway WhatsApp</h2>
        <span class="badge badge-secondary"><?= count($logs) ?> Catatan</span>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu Kirim</th>
                    <th>No. Tujuan</th>
                    <th>Penerima Terkait</th>
                    <th>Event Notifikasi</th>
                    <th>Isi Pesan</th>
                    <th>Status Gateway</th>
                    <th>Detail Error / Ref</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            Belum ada riwayat pengiriman notifikasi WhatsApp.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="font-size: 0.78rem; white-space: nowrap;">
                                <?= substr($log['sent_at'], 0, 10) ?><br>
                                <strong><?= substr($log['sent_at'], 11, 8) ?> WIB</strong>
                            </td>
                            <td>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $log['recipient_number']) ?>" target="_blank" style="font-weight: 600; color: var(--navy-900);">
                                    <?= clean_input($log['recipient_number']) ?>
                                </a>
                            </td>
                            <td>
                                <?= !empty($log['user_name']) ? clean_input($log['user_name']) : '<span style="color: var(--text-light);">-</span>' ?>
                            </td>
                            <td>
                                <span class="badge badge-secondary"><?= clean_input($log['event_type']) ?></span>
                            </td>
                            <td>
                                <div style="max-width: 220px; font-size: 0.78rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= clean_input($log['message_payload']) ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($log['delivery_status'] === 'sent'): ?>
                                    <span class="badge badge-success">TERKIRIM</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">GAGAL</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.74rem;">
                                <?php if (!empty($log['error_message'])): ?>
                                    <span style="color: var(--danger-600);" title="<?= clean_input($log['error_message']) ?>">
                                        <?= clean_input(substr($log['error_message'], 0, 45)) ?>...
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--success-600);">Ref: <?= clean_input($log['provider_reference'] ?? 'OK') ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <button type="button" class="btn btn-sm btn-secondary" onclick='viewMessagePayload(<?= json_encode($log['message_payload']) ?>)'>
                                        Lihat
                                    </button>

                                    <?php if ($log['delivery_status'] === 'failed'): ?>
                                        <form action="<?= BASE_URL ?>/actions/do_admin_action.php" method="POST" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="resend_fonnte">
                                            <input type="hidden" name="id" value="<?= $log['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Kirim ulang pesan ini sekarang?')">
                                                Ulang
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal View Message -->
<div id="viewPayloadModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Isi Lengkap Pesan WhatsApp</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('viewPayloadModal')">&times;</button>
        </div>

        <div style="background: #F8FAFC; border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 18px; font-family: monospace; font-size: 0.85rem; white-space: pre-wrap; line-height: 1.6; color: var(--navy-900);" id="payloadText">
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 18px;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewPayloadModal')">Tutup</button>
        </div>
    </div>
</div>

<script>
function viewMessagePayload(msg) {
    document.getElementById('payloadText').textContent = msg;
    openModal('viewPayloadModal');
}
</script>

<?php
require_once __DIR__ . '/views/layouts/footer.php';
?>
