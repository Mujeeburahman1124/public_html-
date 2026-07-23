<?php
/**
 * migrate_settings.php — ONE-TIME migration
 * Renames old `key`/`value` columns to `setting_key`/`setting_value`
 * and re-seeds from existing data.
 * DELETE THIS FILE after running.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
$host_parts = explode(':', $servername);
$DB_HOST_ONLY = $host_parts[0];
$DB_PORT = isset($host_parts[1]) ? (int)$host_parts[1] : 3306;
$pdo = new PDO(
    "mysql:host=$DB_HOST_ONLY;port=$DB_PORT;dbname=$dbname;charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$steps = [];

// Check if old table exists with old column names
$tableExists = false;
$hasOldCols  = false;
$hasNewCols  = false;

$res = $pdo->query("SHOW TABLES LIKE 'site_settings'");
if ($res->fetch()) {
    $tableExists = true;
    $cols = $pdo->query("SHOW COLUMNS FROM site_settings")->fetchAll(PDO::FETCH_COLUMN);
    $hasOldCols = in_array('key', $cols);
    $hasNewCols = in_array('setting_key', $cols);
}

if (!$tableExists) {
    $steps[] = "ℹ️ Table does not exist yet — will be auto-created on next page load.";
} elseif ($hasOldCols && !$hasNewCols) {
    // Backup existing data
    $oldData = $pdo->query("SELECT `key`, `value` FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $steps[] = "📋 Backed up " . count($oldData) . " rows from old table.";

    // Drop old table and recreate with new schema
    $pdo->exec("DROP TABLE site_settings");
    $steps[] = "🗑️ Dropped old site_settings table.";

    $pdo->exec("
        CREATE TABLE site_settings (
            `setting_key`   VARCHAR(100)  NOT NULL,
            `setting_value` TEXT          NOT NULL DEFAULT '',
            `label`         VARCHAR(255)  NOT NULL DEFAULT '',
            `group_name`    VARCHAR(100)  NOT NULL DEFAULT 'general',
            `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $steps[] = "✅ Created new site_settings table with setting_key/setting_value columns.";

    // Restore data
    if ($oldData) {
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES (:k, :v)"
        );
        foreach ($oldData as $k => $v) {
            $stmt->execute([':k' => $k, ':v' => $v]);
        }
        $steps[] = "✅ Restored " . count($oldData) . " settings rows.";
    }
} elseif ($hasNewCols) {
    $steps[] = "✅ Table already has new column schema (setting_key/setting_value). No migration needed.";
} else {
    $steps[] = "⚠️ Unknown column structure. Manual inspection required.";
}

// Show current data
try {
    $all = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $steps[] = ['Current data' => $all];
} catch (Throwable $e) {
    $steps[] = "⚠️ Could not read current data: " . $e->getMessage();
}

// Self-delete
@unlink(__FILE__);
$steps[] = "🗑️ This migration script has self-deleted.";
?>
<!DOCTYPE html>
<html>
<head>
  <title>Settings Migration</title>
  <style>
    body { font-family: monospace; background:#0f172a; color:#e2e8f0; padding:2rem; }
    h2 { color:#22d3ee; }
    .step { background:#1e293b; padding:10px 16px; margin:6px 0; border-radius:8px; }
    pre { background:#0a1628; padding:1rem; border-radius:8px; overflow-x:auto; font-size:12px; }
  </style>
</head>
<body>
<h2>🔧 Settings Migration</h2>
<?php foreach ($steps as $step): ?>
  <?php if (is_array($step)): ?>
    <?php foreach ($step as $k => $v): ?>
      <div class="step"><strong><?= htmlspecialchars($k) ?>:</strong><pre><?= htmlspecialchars(print_r($v, true)) ?></pre></div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="step"><?= htmlspecialchars($step) ?></div>
  <?php endif; ?>
<?php endforeach; ?>
<hr>
<p>✅ Migration complete. Visit <a href="admin_site_settings.php" style="color:#22d3ee">Site Settings</a> to manage your settings.</p>
</body>
</html>
