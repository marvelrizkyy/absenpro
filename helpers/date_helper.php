<?php
// helpers/date_helper.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function format_indo_date(string $dateStr): string {
    if (empty($dateStr) || $dateStr === '0000-00-00') {
        return '-';
    }
    $timestamp = strtotime($dateStr);
    if (!$timestamp) return '-';

    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $days = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    ];

    $dayName = $days[date('l', $timestamp)] ?? '';
    $day = date('d', $timestamp);
    $month = $months[(int)date('m', $timestamp)] ?? '';
    $year = date('Y', $timestamp);

    return "{$dayName}, {$day} {$month} {$year}";
}

function format_short_date(string $dateStr): string {
    if (empty($dateStr)) return '-';
    $timestamp = strtotime($dateStr);
    if (!$timestamp) return '-';

    $months = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    return date('d', $timestamp) . ' ' . ($months[(int)date('m', $timestamp)] ?? '') . ' ' . date('Y', $timestamp);
}

function format_time(?string $timeStr): string {
    if (empty($timeStr)) return '-';
    $timestamp = strtotime($timeStr);
    return $timestamp ? date('H:i', $timestamp) . ' WIB' : '-';
}

function is_work_day(string $dateStr): bool {
    $timestamp = strtotime($dateStr);
    if (!$timestamp) return false;

    // Day of week: 1 (Monday) to 7 (Sunday)
    $dayOfWeek = (int)date('N', $timestamp);

    $db = get_db_connection();
    $stmt = $db->query("SELECT work_days FROM work_settings WHERE id = 1");
    $settings = $stmt->fetch();
    $allowedDays = isset($settings['work_days']) ? explode(',', $settings['work_days']) : ['1','2','3','4','5','6'];

    if (!in_array((string)$dayOfWeek, $allowedDays)) {
        return false;
    }

    // Check holiday
    if (is_holiday($dateStr)) {
        return false;
    }

    return true;
}

function is_holiday(string $dateStr): bool {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT COUNT(*) FROM holidays WHERE holiday_date = ?");
    $stmt->execute([$dateStr]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function get_holiday_name(string $dateStr): ?string {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT name FROM holidays WHERE holiday_date = ?");
    $stmt->execute([$dateStr]);
    $res = $stmt->fetch();
    return $res['name'] ?? null;
}

function calculate_effective_days(string $startDate, string $endDate): int {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day'); // inclusive

    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    $count = 0;
    foreach ($period as $dt) {
        $dateStr = $dt->format('Y-m-d');
        if (is_work_day($dateStr)) {
            $count++;
        }
    }
    return max(1, $count);
}
