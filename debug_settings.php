<?php
// debug_settings.php — TEMPORARY DIAGNOSTIC — DELETE AFTER USE
session_start();

$results = [];

// 1. PDO connection test
try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;port=3306;dbname=u903588615_exaple;charset=utf8mb4",
        "u903588615_root",
        "Msjobs#1",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $results['pdo_connect'] = "✅ PDO connected OK";
} catch (Throwable $e) {
    $results['pdo_connect'] = "❌ PDO FAILED: " . $e->getMessage();
    $pdo = null;
}

if ($pdo) {
    // 2. Check table exists
    $res = $pdo->query("SHOW TABLES LIKE 'site_settings_v2'");
    if ($res->fetch()) {
        $results['table_exists'] = "✅ site_settings_v2 table EXISTS";

        // 3. Show columns
        $cols = $pdo->query("SHOW COLUMNS FROM site_settings_v2")->fetchAll(PDO::FETCH_COLUMN);
        $results['columns'] = "ℹ️ Columns: " . implode(', ', $cols);

        // 4. Row count
        $cnt = $pdo->query("SELECT COUNT(*) FROM site_settings_v2")->fetchColumn();
        $results['row_count'] = "ℹ️ Total rows: $cnt";

        // 5. Write test using CORRECT column names
        $useOld = in_array('key', $cols);
        $useNew = in_array('setting_key', $cols);
        $results['schema'] = $useNew ? "✅ New schema (setting_key/setting_value)" : ($useOld ? "⚠️ Old schema (key/value) — run migrate_settings.php" : "❌ Unknown schema");

        if ($useNew) {
            $ts = time();
            try {
                $upd = $pdo->prepare("INSERT INTO site_settings_v2 (setting_key, setting_value) VALUES ('_debug_test',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
                $upd->execute(["debug_$ts"]);

                $val = $pdo->query("SELECT setting_value FROM site_settings_v2 WHERE setting_key='_debug_test'")->fetchColumn();
                $results['write_test'] = ($val === "debug_$ts") ? "✅ Write+Read OK: $val" : "❌ Mismatch: got $val";

                // Show all settings
                $all = $pdo->query("SELECT setting_key, setting_value FROM site_settings_v2 WHERE setting_key NOT LIKE '\\_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
                $results['current_settings'] = $all;
            } catch (Throwable $e) {
                $results['write_test'] = "❌ " . $e->getMessage();
            }
        }
    } else {
        $results['table_exists'] = "❌ Table does not exist — will be auto-created on first page load";
    }
}

// 6. Test settings_helper
require_once __DIR__ . '/settings_helper.php';
$addr = site_setting_raw('contact_address_short');
$results['helper_read'] = "ℹ️ contact_address_short = \"$addr\"";
?>
<!DOCTYPE html>
<html>
<head>
  <title>Settings Debug</title>
  <style>
    body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; }
    h2 { color: #22d3ee; }
    .row { padding: 8px 12px; margin: 4px 0; border-radius: 6px; background: #1e293b; }
    pre { background: #0a1628; padding: 1rem; border-radius: 8px; overflow-x: auto; font-size: 12px; }
    a { color: #22d3ee; }
  </style>
</head>
<body>
<h2>🔍 Settings Diagnostic</h2>
<?php foreach ($results as $k => $v): ?>
  <?php if ($k === 'current_settings'): ?>
    <div class="row"><strong>current_settings:</strong><pre><?= htmlspecialchars(print_r($v, true)) ?></pre></div>
  <?php else: ?>
    <div class="row"><strong><?= $k ?>:</strong> <?= htmlspecialchars((string)$v) ?></div>
  <?php endif; ?>
<?php endforeach; ?>
<hr>
<p>→ <a href="admin_site_settings.php">Go to Site Settings</a> | <a href="index.php">Go to Homepage</a></p>
</body>
</html>
