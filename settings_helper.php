<?php
/**
 * settings_helper.php — MSJOBS Global Site Settings
 * Uses PDO (same as index.php) for maximum compatibility.
 * Auto-creates and seeds the `site_settings` table on first load.
 */
declare(strict_types=1);

/* ===== DB CONFIG ===== */
if (!defined('SETTINGS_DB_DSN')) {
    define('SETTINGS_DB_HOST', '127.0.0.1');
    define('SETTINGS_DB_PORT', 3306);
    define('SETTINGS_DB_USER', 'u903588615_root');
    define('SETTINGS_DB_PASS', 'Msjobs#1');
    define('SETTINGS_DB_NAME', 'u903588615_exaple');
    define('SETTINGS_DB_DSN',  'mysql:host=127.0.0.1;port=3306;dbname=u903588615_exaple;charset=utf8mb4');
}

/* ===== Singleton PDO connection ===== */
function _settings_pdo(): ?PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $pdo = new PDO(
            SETTINGS_DB_DSN,
            SETTINGS_DB_USER,
            SETTINGS_DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (Throwable $e) {
        // Connection failed — helper will fall back to defaults
        $pdo = null;
    }
    return $pdo;
}

/* ===== Default values seeded on first install ===== */
function _settings_defaults(): array {
    return [
        // General
        'site_name'             => 'MSJOBS',
        'site_tagline'          => 'Recruitment made simple — across the GCC & beyond.',
        'support_email'         => 'support@msjobs.net',
        'support_hours'         => 'Sun–Sat: 9:00 AM – 7:00 PM (Gulf Time)',

        // Contact
        'contact_phone'         => '+971 58 597 4340',
        'contact_whatsapp'      => 'https://wa.me/971585974340',
        'contact_address'       => 'Real Group Building, Ajman Industrial Area 2, United Arab Emirates',
        'contact_address_short' => 'Real Group Building, Ajman Industrial Area 2, UAE',

        // Map
        'map_embed_url'         => 'https://www.google.com/maps?q=Ajman%20Industrial%20Area%202%2C%20Ajman%2C%20UAE&output=embed',
        'map_label'             => 'Ajman Industrial Area 2, Ajman, UAE',

        // Social
        'social_facebook'       => 'https://www.facebook.com/share/1CNVH7tY6K/',
        'social_tiktok'         => 'https://www.tiktok.com/@msjobs2026?_r=1&_t=ZS-93fwEDZ7G3r',
        'social_twitter'        => '',
        'social_linkedin'       => '',
        'social_instagram'      => '',
        'social_youtube'        => '',

        // Copyright
        'copyright_text'        => 'MSJOBS. All rights reserved.',
    ];
}

/* ===== Auto-migrate & seed table ===== */
function _settings_migrate(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    // Create table if missing
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_settings_v2 (
            `setting_key`   VARCHAR(100)  NOT NULL,
            `setting_value` TEXT          NOT NULL DEFAULT '',
            `label`         VARCHAR(255)  NOT NULL DEFAULT '',
            `group_name`    VARCHAR(100)  NOT NULL DEFAULT 'general',
            `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Seed missing keys using INSERT IGNORE
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO site_settings_v2 (setting_key, setting_value, label, group_name)
         VALUES (:k, :v, :l, :g)"
    );

    $meta = [
        'site_name'             => ['Site Name', 'general'],
        'site_tagline'          => ['Site Tagline', 'general'],
        'support_email'         => ['Support Email', 'contact'],
        'support_hours'         => ['Support Hours', 'contact'],
        'contact_phone'         => ['Phone Number', 'contact'],
        'contact_whatsapp'      => ['WhatsApp Link (full URL)', 'contact'],
        'contact_address'       => ['Full Address', 'contact'],
        'contact_address_short' => ['Short Address (for footer)', 'contact'],
        'map_embed_url'         => ['Google Maps Embed URL', 'map'],
        'map_label'             => ['Map Location Label', 'map'],
        'social_facebook'       => ['Facebook URL', 'social'],
        'social_tiktok'         => ['TikTok URL', 'social'],
        'social_twitter'        => ['Twitter/X URL', 'social'],
        'social_linkedin'       => ['LinkedIn URL', 'social'],
        'social_instagram'      => ['Instagram URL', 'social'],
        'social_youtube'        => ['YouTube URL', 'social'],
        'copyright_text'        => ['Copyright Text', 'general'],
    ];

    foreach (_settings_defaults() as $k => $v) {
        [$label, $group] = $meta[$k] ?? [$k, 'general'];
        $stmt->execute([':k' => $k, ':v' => $v, ':l' => $label, ':g' => $group]);
    }
}

/* ===== Load all settings (cached per request) ===== */
function get_site_settings(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $pdo = _settings_pdo();
    if (!$pdo) {
        $cache = _settings_defaults();
        return $cache;
    }

    _settings_migrate($pdo);

    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings_v2")->fetchAll(PDO::FETCH_KEY_PAIR);
        $cache = array_merge(_settings_defaults(), $rows);
    } catch (Throwable $e) {
        $cache = _settings_defaults();
    }
    return $cache;
}

/* ===== Convenient single-key lookup (HTML-safe) ===== */
function site_setting(string $key, string $default = ''): string {
    $settings = get_site_settings();
    $val = (string)($settings[$key] ?? $default);
    return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
}

/* ===== Raw value (no HTML encoding) ===== */
function site_setting_raw(string $key, string $default = ''): string {
    $settings = get_site_settings();
    return (string)($settings[$key] ?? $default);
}

/* ===== Save a single setting ===== */
function save_site_setting(string $key, string $value): bool {
    $pdo = _settings_pdo();
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO site_settings_v2 (setting_key, setting_value)
             VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        return $stmt->execute([':k' => $key, ':v' => $value]);
    } catch (Throwable $e) {
        return false;
    }
}

/* ===== Save multiple settings at once ===== */
function save_site_settings(array $data): bool {
    $pdo = _settings_pdo();
    if (!$pdo) return false;
    $ok = true;
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO site_settings_v2 (setting_key, setting_value)
             VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        foreach ($data as $k => $v) {
            if (!$stmt->execute([':k' => (string)$k, ':v' => (string)$v])) {
                $ok = false;
            }
        }
    } catch (Throwable $e) {
        return false;
    }
    return $ok;
}
