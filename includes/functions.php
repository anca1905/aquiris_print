<?php
// includes/functions.php

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function formatRupiah($amount): string {
    $val = (float)($amount ?? 0);
    return 'Rp ' . number_format($val, 0, ',', '.');
}

function formatDate($datetime, bool $includeTime = false): string {
    if (!$datetime || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    $timestamp = strtotime($datetime);
    $months = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    $d = date('d', $timestamp);
    $m = $months[(int)date('m', $timestamp)];
    $y = date('Y', $timestamp);

    if ($includeTime) {
        $t = date('H:i', $timestamp);
        return "$d $m $y, $t WIB";
    }
    return "$d $m $y";
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'warning', 'info'
        'message' => $message
    ];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function getSetting(string $key, string $default = ''): string {
    static $settingsCache = null;
    $pdo = Database::getConnection();

    if ($settingsCache === null) {
        $settingsCache = [];
        $stmt = $pdo->query("SELECT `setting_key`, `setting_value` FROM `settings`");
        while ($row = $stmt->fetch()) {
            $settingsCache[$row['setting_key']] = $row['setting_value'];
        }
    }

    return $settingsCache[$key] ?? $default;
}

function updateSetting(string $key, string $value): bool {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)");
    return $stmt->execute([$key, $value]);
}

function generateInvoiceNumber(PDO $pdo): string {
    $prefix = 'INV-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("SELECT invoice_number FROM orders WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $lastSeq = (int)substr($last, -4);
        $nextSeq = str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $nextSeq = '0001';
    }
    return $prefix . $nextSeq;
}

function generateRentalCode(PDO $pdo): string {
    $prefix = 'SEWA-' . date('Ym') . '-';
    $stmt = $pdo->prepare("SELECT rental_code FROM acrylic_rentals WHERE rental_code LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $lastSeq = (int)substr($last, -3);
        $nextSeq = str_pad($lastSeq + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $nextSeq = '001';
    }
    return $prefix . $nextSeq;
}

function generateExpenseCode(PDO $pdo): string {
    $prefix = 'EXP-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("SELECT expense_code FROM expenses WHERE expense_code LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $lastSeq = (int)substr($last, -4);
        $nextSeq = str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $nextSeq = '0001';
    }
    return $prefix . $nextSeq;
}

function generateDistributionCode(PDO $pdo): string {
    $prefix = 'DIV-' . date('Ym') . '-';
    $stmt = $pdo->prepare("SELECT distribution_code FROM profit_distributions WHERE distribution_code LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $lastSeq = (int)substr($last, -3);
        $nextSeq = str_pad($lastSeq + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $nextSeq = '001';
    }
    return $prefix . $nextSeq;
}

function formatWaPhone(string $phone): string {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($clean, '0')) {
        return '62' . substr($clean, 1);
    }
    if (str_starts_with($clean, '8')) {
        return '62' . $clean;
    }
    return $clean;
}

function buildWaLink(string $phone, string $message): string {
    $targetPhone = formatWaPhone($phone);
    return 'https://api.whatsapp.com/send?phone=' . $targetPhone . '&text=' . rawurlencode($message);
}

function generateOnlineOrderCode(PDO $pdo): string {
    $prefix = 'ORD-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("SELECT order_code FROM online_orders WHERE order_code LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $lastSeq = (int)substr($last, -4);
        $nextSeq = str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $nextSeq = '0001';
    }
    return $prefix . $nextSeq;
}

function getBaseUrl(): string {
    $custom = getSetting('base_url', '');
    if (!empty($custom)) {
        return rtrim($custom, '/');
    }

    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($script);
    if ($dir === '/' || $dir === '\\') {
        $dir = '';
    }
    return $scheme . '://' . $host . $dir;
}

function countPendingOnlineOrders(): int {
    static $count = null;
    if ($count !== null) return $count;
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM online_orders WHERE status = 'menunggu'");
        $count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $count = 0;
    }
    return $count;
}

/**
 * Send WhatsApp Message via Fonnte Gateway API
 * https://api.fonnte.com/send
 */
function sendFonnteMessage(string $target, string $message, string $fileUrl = '', string $filename = ''): array {
    $token = getSetting('fonnte_token', '');
    if (empty($token)) {
        return [
            'status' => false,
            'message' => 'Token Fonnte belum dikonfigurasi di Pengaturan Toko.'
        ];
    }

    $targetClean = formatWaPhone($target);

    $postData = [
        'target' => $targetClean,
        'message' => $message,
        'countryCode' => '62'
    ];

    if (!empty($fileUrl)) {
        $postData['url'] = $fileUrl;
        if (!empty($filename)) {
            $postData['filename'] = $filename;
        }
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token
        ],
        CURLOPT_SSL_VERIFYPEER => false // For local Laragon environments
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        return [
            'status' => false,
            'message' => 'cURL Error: ' . $curlError
        ];
    }

    $decoded = json_decode($response, true);
    if ($decoded && isset($decoded['status']) && $decoded['status'] == true) {
        return [
            'status' => true,
            'response' => $decoded
        ];
    } else {
        $detail = $decoded['reason'] ?? $decoded['message'] ?? ('HTTP ' . $httpCode . ': ' . substr($response, 0, 100));
        return [
            'status' => false,
            'message' => $detail
        ];
    }
}
