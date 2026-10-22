<?php
// helpers/sanitize_helper.php
declare(strict_types=1);

function clean_input(mixed $data): string {
    if (is_null($data)) {
        return '';
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function flash_set(string $type, string $message): void {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function render_flash_messages(): string {
    $flashes = flash_get();
    if (empty($flashes)) {
        return '';
    }

    $html = '<div class="flash-container">';
    foreach ($flashes as $flash) {
        $type = clean_input($flash['type']);
        $msg = clean_input($flash['message']);
        $icon = match($type) {
            'success' => '✓',
            'danger', 'error' => '✕',
            'warning' => '⚠',
            default => 'ℹ'
        };
        $html .= "<div class=\"alert alert-{$type}\"><span class=\"alert-icon\">{$icon}</span><span class=\"alert-text\">{$msg}</span><button class=\"alert-close\" onclick=\"this.parentElement.remove()\">×</button></div>";
    }
    $html .= '</div>';
    return $html;
}
