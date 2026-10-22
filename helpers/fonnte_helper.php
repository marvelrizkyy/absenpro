<?php
// helpers/fonnte_helper.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/fonnte.php';

/**
 * Normalisasi nomor WhatsApp ke format internasional (628...)
 */
function normalize_phone_number(string $phone): string {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($phone, '0')) {
        $phone = '62' . substr($phone, 1);
    } elseif (str_starts_with($phone, '+62')) {
        $phone = substr($phone, 1);
    } elseif (!str_starts_with($phone, '62')) {
        $phone = '62' . $phone;
    }
    return $phone;
}

/**
 * Mengambil nomor WhatsApp Admin dari .env atau database
 */
function get_admin_phone(): string {
    // 1. Cek .env terlebih dahulu
    $envPhone = env('ADMIN_PHONE');
    if (!empty($envPhone)) {
        return trim((string)$envPhone);
    }

    try {
        $db = get_db_connection();
        // 2. Cek nomor WA dari akun admin aktif di database
        $stmt = $db->query("SELECT no_wa FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id ASC LIMIT 1");
        $phone = $stmt->fetchColumn();
        if (!empty($phone)) {
            return trim((string)$phone);
        }

        // 3. Cek pengaturan work_settings
        $stmt2 = $db->query("SELECT admin_phone FROM work_settings WHERE id = 1");
        $phone2 = $stmt2->fetchColumn();
        if (!empty($phone2)) {
            return trim((string)$phone2);
        }
    } catch (Exception $e) {
        // Abaikan jika database belum siap
    }

    return '083174943920';
}

/**
 * Mengirim pesan WhatsApp via Fonnte dan mencatat ke notification_logs.
 * Secara otomatis mengirimkan pesan ke nomor yang dituju DAN nomor admin.
 */
function send_whatsapp_notification(
    string $recipientPhone, 
    string $eventType, 
    string $message, 
    ?int $userId = null,
    bool $copyToAdmin = true
): array {
    $db = get_db_connection();
    $targetPhone = normalize_phone_number($recipientPhone);
    $adminPhone = normalize_phone_number(get_admin_phone());
    $token = get_fonnte_token();

    // Tentukan daftar nomor penerima (nomor dituju + nomor admin)
    $targets = [$targetPhone];
    if ($copyToAdmin && !empty($adminPhone) && $adminPhone !== $targetPhone) {
        $targets[] = $adminPhone;
    }
    $targetString = implode(',', array_unique($targets));

    // Jika token masih placeholder, catat log sebagai simulasi / gagal konfigurasi token
    if ($token === 'YOUR_FONNTE_API_TOKEN_HERE' || empty($token)) {
        $errMsg = 'Token Fonnte belum dikonfigurasi. Pesan disimpan dalam log sistem (Simulasi Pengiriman).';
        
        foreach ($targets as $phone) {
            $eType = ($phone === $adminPhone && $phone !== $targetPhone) ? ($eventType . '_admin_copy') : $eventType;
            $stmt = $db->prepare("INSERT INTO notification_logs 
                (user_id, recipient_number, event_type, message_payload, delivery_status, provider_reference, error_message, sent_at) 
                VALUES (?, ?, ?, ?, 'failed', NULL, ?, NOW())");
            $stmt->execute([$userId, $phone, $eType, $message, $errMsg]);
        }

        return [
            'success' => false,
            'simulated' => true,
            'message' => $errMsg
        ];
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => FONNTE_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => http_build_query([
            'target' => $targetString,
            'message' => $message,
            'countryCode' => '62',
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token
        ],
    ]);

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $deliveryStatus = 'failed';
    $providerRef = null;
    $errorMessage = null;

    if ($curlError) {
        $errorMessage = 'cURL Error: ' . $curlError;
    } else {
        $result = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && isset($result['status']) && $result['status'] === true) {
            $deliveryStatus = 'sent';
            if (isset($result['id'])) {
                $providerRef = is_array($result['id']) ? implode(',', $result['id']) : (string)$result['id'];
            } else {
                $providerRef = isset($result['target'][0]) ? (string)$result['target'][0] : 'OK';
            }
        } else {
            $errorMessage = $result['reason'] ?? ($result['detail'] ?? "HTTP Code {$httpCode}: {$response}");
        }
    }

    // Catat log untuk setiap penerima (Nomor Dituju & Nomor Admin)
    try {
        $stmt = $db->prepare("INSERT INTO notification_logs 
            (user_id, recipient_number, event_type, message_payload, delivery_status, provider_reference, error_message, sent_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

        foreach ($targets as $phone) {
            $eType = ($phone === $adminPhone && $phone !== $targetPhone) ? ($eventType . '_admin_copy') : $eventType;
            $stmt->execute([$userId, $phone, $eType, $message, $deliveryStatus, $providerRef, $errorMessage]);
        }
    } catch (Exception $e) {
        // Jangan gagalkan alur bisnis utama jika logging DB terjadi kendala
        error_log('Failed to log Fonnte notification: ' . $e->getMessage());
    }

    return [
        'success' => ($deliveryStatus === 'sent'),
        'provider_ref' => $providerRef,
        'error' => $errorMessage
    ];
}

