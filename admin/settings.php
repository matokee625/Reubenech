<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

$active_tab = $_GET['tab'] ?? 'general';
$success = '';
$error   = '';

// Function to export database
function exportDatabase($conn) {
    try {
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $sql = "-- Reubentech Hub Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            $createTableStmt = $conn->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createTableStmt[1] . ";\n\n";

            $rows = $conn->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) > 0) {
                $sql .= "INSERT INTO `$table` (";
                $cols = array_keys($rows[0]);
                $sql .= implode(", ", array_map(function($c) { return "`$c`"; }, $cols));
                $sql .= ") VALUES\n";

                $valStrings = [];
                foreach ($rows as $row) {
                    $rowVals = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $rowVals[] = "NULL";
                        } else {
                            $rowVals[] = $conn->quote($val);
                        }
                    }
                    $valStrings[] = "(" . implode(", ", $rowVals) . ")";
                }
                $sql .= implode(",\n", $valStrings) . ";\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="milkproject_backup_' . date('Y-m-d_H-i-s') . '.sql"');
        header('Content-Length: ' . strlen($sql));
        echo $sql;
        exit();
    } catch (Exception $e) {
        return "Export error: " . $e->getMessage();
    }
}

// Handle Database Export
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'export') {
    $err = exportDatabase($conn);
    if ($err) {
        $error = $err;
        $active_tab = 'backup';
    }
}

// Handle Database Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'import') {
    $active_tab = 'backup';
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $file_path = $_FILES['backup_file']['tmp_name'];
        $sql_content = file_get_contents($file_path);
        if ($sql_content !== false) {
            try {
                $conn->exec($sql_content);
                $success = 'Database restored successfully from backup file.';
            } catch(PDOException $e) {
                $error = 'Failed to restore database: ' . $e->getMessage();
            }
        } else {
            $error = 'Could not read the uploaded file.';
        }
    } else {
        $error = 'Please select a valid SQL file to upload.';
    }
}

// --- Handle form submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    $tab = $_POST['settings_tab'] ?? 'general';

    if ($tab === 'general') {
        $site_name   = trim($_POST['site_name'] ?? '');
        $site_email  = trim($_POST['site_email'] ?? '');
        $site_phone  = trim($_POST['site_phone'] ?? '');
        $currency    = trim($_POST['currency'] ?? 'KSH');
        $timezone    = trim($_POST['timezone'] ?? 'Africa/Nairobi');
        $maintenance = isset($_POST['maintenance']) ? 1 : 0;

        try {
            $settings = [
                'site_name'    => $site_name,
                'site_email'   => $site_email,
                'site_phone'   => $site_phone,
                'currency'     => $currency,
                'timezone'     => $timezone,
                'maintenance'  => $maintenance,
            ];
            foreach ($settings as $key => $val) {
                $stmt = $conn->prepare("INSERT INTO settings (`key`,`value`) VALUES (:k,:v)
                    ON DUPLICATE KEY UPDATE `value`=:v2");
                $stmt->execute([':k'=>$key, ':v'=>$val, ':v2'=>$val]);
            }
            $success = 'General settings saved successfully.';
        } catch(PDOException $e) {
            $error = 'Could not save settings: ' . $e->getMessage();
        }
        $active_tab = 'general';

    } elseif ($tab === 'security') {
        $min_pass   = (int)($_POST['min_password'] ?? 8);
        $max_login  = (int)($_POST['max_login_attempts'] ?? 5);
        $session_t  = (int)($_POST['session_timeout'] ?? 30);
        $allow_reg  = isset($_POST['allow_registration']) ? 1 : 0;
        $require_2fa= isset($_POST['require_2fa']) ? 1 : 0;

        try {
            $sec = [
                'min_password_length'  => $min_pass,
                'max_login_attempts'   => $max_login,
                'session_timeout_mins' => $session_t,
                'allow_registration'   => $allow_reg,
                'require_2fa'          => $require_2fa,
            ];
            foreach ($sec as $key => $val) {
                $stmt = $conn->prepare("INSERT INTO settings (`key`,`value`) VALUES (:k,:v)
                    ON DUPLICATE KEY UPDATE `value`=:v2");
                $stmt->execute([':k'=>$key, ':v'=>$val, ':v2'=>$val]);
            }
            $success = 'Security settings saved successfully.';
        } catch(PDOException $e) {
            $error = 'Could not save: ' . $e->getMessage();
        }
        $active_tab = 'security';

    } elseif ($tab === 'email') {
        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_port = (int)($_POST['smtp_port'] ?? 587);
        $smtp_user = trim($_POST['smtp_user'] ?? '');
        $smtp_pass = trim($_POST['smtp_pass'] ?? '');
        $smtp_enc  = trim($_POST['smtp_enc'] ?? 'tls');
        $from_name = trim($_POST['from_name'] ?? '');
        $from_email= trim($_POST['from_email'] ?? '');

        try {
            $em = [
                'smtp_host'   => $smtp_host,
                'smtp_port'   => $smtp_port,
                'smtp_user'   => $smtp_user,
                'smtp_pass'   => $smtp_pass,
                'smtp_enc'    => $smtp_enc,
                'email_from_name'  => $from_name,
                'email_from_email' => $from_email,
            ];
            foreach ($em as $key => $val) {
                $stmt = $conn->prepare("INSERT INTO settings (`key`,`value`) VALUES (:k,:v)
                    ON DUPLICATE KEY UPDATE `value`=:v2");
                $stmt->execute([':k'=>$key, ':v'=>$val, ':v2'=>$val]);
            }
            $success = 'Email settings saved.';
        } catch(PDOException $e) {
            $error = 'Could not save: ' . $e->getMessage();
        }
        $active_tab = 'email';

    } elseif ($tab === 'appearance') {
        $primary_color = trim($_POST['primary_color'] ?? '#2563eb');
        $sidebar_theme = trim($_POST['sidebar_theme'] ?? 'dark');
        $logo_url      = trim($_POST['logo_url'] ?? '');
        $items_per_page= (int)($_POST['items_per_page'] ?? 20);

        try {
            $ap = [
                'primary_color'  => $primary_color,
                'sidebar_theme'  => $sidebar_theme,
                'logo_url'       => $logo_url,
                'items_per_page' => $items_per_page,
            ];
            foreach ($ap as $key => $val) {
                $stmt = $conn->prepare("INSERT INTO settings (`key`,`value`) VALUES (:k,:v)
                    ON DUPLICATE KEY UPDATE `value`=:v2");
                $stmt->execute([':k'=>$key, ':v'=>$val, ':v2'=>$val]);
            }
            $success = 'Appearance settings saved.';
        } catch(PDOException $e) {
            $error = 'Could not save: ' . $e->getMessage();
        }
        $active_tab = 'appearance';
    } elseif ($tab === 'mpesa') {
        $mock_mode       = isset($_POST['mpesa_mock_mode']) ? 1 : 0;
        $consumer_key    = trim($_POST['mpesa_consumer_key'] ?? '');
        $consumer_secret = trim($_POST['mpesa_consumer_secret'] ?? '');
        $shortcode       = trim($_POST['mpesa_shortcode'] ?? '');
        $passkey         = trim($_POST['mpesa_passkey'] ?? '');
        $callback_url    = trim($_POST['mpesa_callback_url'] ?? '');
        $environment     = trim($_POST['mpesa_environment'] ?? 'sandbox');
        $test_pin        = trim($_POST['mpesa_test_pin'] ?? '2026');

        try {
            $mp = [
                'mpesa_mock_mode'       => $mock_mode,
                'mpesa_consumer_key'    => $consumer_key,
                'mpesa_consumer_secret' => $consumer_secret,
                'mpesa_shortcode'       => $shortcode,
                'mpesa_passkey'         => $passkey,
                'mpesa_callback_url'    => $callback_url,
                'mpesa_environment'     => $environment,
                'mpesa_test_pin'        => $test_pin,
            ];
            foreach ($mp as $key => $val) {
                $stmt = $conn->prepare("INSERT INTO settings (`key`,`value`) VALUES (:k,:v)
                    ON DUPLICATE KEY UPDATE `value`=:v2");
                $stmt->execute([':k'=>$key, ':v'=>$val, ':v2'=>$val]);
            }
            $success = 'M-Pesa API settings saved successfully.';
        } catch(PDOException $e) {
            $error = 'Could not save: ' . $e->getMessage();
        }
        $active_tab = 'mpesa';
    }
}

// Load all settings from DB
$cfg = [];
try {
    $rows = $conn->query("SELECT `key`,`value` FROM settings")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $cfg[$r['key']] = $r['value'];
    }
} catch(PDOException $e) {
    // settings table may not exist yet
}

function s($cfg, $key, $default='') {
    return htmlspecialchars($cfg[$key] ?? $default);
}

adminHeader('settings', ucfirst($active_tab));
?>

<div class="page-header">
    <h1>⚙️ SETTINGS</h1>
</div>

<?php if($success): ?>
<div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if($error): ?>
<div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Settings Tabs -->
<div class="settings-tabs">
    <a href="?tab=general"    class="settings-tab <?= $active_tab==='general'    ? 'active' : '' ?>">🌐 General</a>
    <a href="?tab=security"   class="settings-tab <?= $active_tab==='security'   ? 'active' : '' ?>">🔒 Security</a>
    <a href="?tab=email"      class="settings-tab <?= $active_tab==='email'      ? 'active' : '' ?>">📧 Email / SMTP</a>
    <a href="?tab=appearance" class="settings-tab <?= $active_tab==='appearance' ? 'active' : '' ?>">🎨 Appearance</a>
    <a href="?tab=backup"     class="settings-tab <?= $active_tab==='backup'     ? 'active' : '' ?>">💾 Backup</a>
    <a href="?tab=mpesa"      class="settings-tab <?= $active_tab==='mpesa'      ? 'active' : '' ?>">📲 M-Pesa API</a>
</div>

<div class="settings-body">

<?php if($active_tab === 'general'): ?>
<!-- ====== GENERAL ====== -->
<form method="POST" class="settings-form">
    <input type="hidden" name="settings_tab" value="general">
    <div class="settings-section">
        <h2 class="settings-section-title">Site Information</h2>
        <div class="form-grid">
            <div class="form-group">
                <label>Site Name</label>
                <input type="text" name="site_name" value="<?= s($cfg,'site_name','Reubentech Hub') ?>" placeholder="Reubentech Hub">
            </div>
            <div class="form-group">
                <label>Admin Email</label>
                <input type="email" name="site_email" value="<?= s($cfg,'site_email','reubenmatoke2005@gmail.com') ?>">
            </div>
            <div class="form-group">
                <label>Contact Phone</label>
                <input type="text" name="site_phone" value="<?= s($cfg,'site_phone','+254 799031535') ?>">
            </div>
            <div class="form-group">
                <label>Currency Symbol</label>
                <input type="text" name="currency" value="<?= s($cfg,'currency','KSH') ?>" style="max-width:120px;">
            </div>
            <div class="form-group">
                <label>Timezone</label>
                <select name="timezone">
                    <?php
                    $tzones = ['Africa/Nairobi','Africa/Lagos','Africa/Cairo','UTC','Europe/London','America/New_York'];
                    foreach($tzones as $tz):
                    ?>
                    <option value="<?= $tz ?>" <?= ($cfg['timezone'] ?? 'Africa/Nairobi') === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="settings-section">
        <h2 class="settings-section-title">System Status</h2>
        <div class="toggle-row">
            <div>
                <strong>Maintenance Mode</strong>
                <p class="setting-desc">When enabled, visitors see a maintenance message instead of the site.</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="maintenance" <?= ($cfg['maintenance'] ?? 0) ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">💾 Save General Settings</button>
</form>

<?php elseif($active_tab === 'security'): ?>
<!-- ====== SECURITY ====== -->
<form method="POST" class="settings-form">
    <input type="hidden" name="settings_tab" value="security">
    <div class="settings-section">
        <h2 class="settings-section-title">Password Policy</h2>
        <div class="form-grid">
            <div class="form-group">
                <label>Minimum Password Length</label>
                <input type="number" name="min_password" value="<?= s($cfg,'min_password_length','8') ?>" min="6" max="32">
            </div>
            <div class="form-group">
                <label>Max Login Attempts (before lockout)</label>
                <input type="number" name="max_login_attempts" value="<?= s($cfg,'max_login_attempts','5') ?>" min="1" max="20">
            </div>
            <div class="form-group">
                <label>Session Timeout (minutes)</label>
                <input type="number" name="session_timeout" value="<?= s($cfg,'session_timeout_mins','30') ?>" min="5" max="1440">
            </div>
        </div>
    </div>

    <div class="settings-section">
        <h2 class="settings-section-title">Access Control</h2>
        <div class="toggle-row">
            <div>
                <strong>Allow New Registrations</strong>
                <p class="setting-desc">Allows new users to create accounts on the public site.</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="allow_registration" <?= ($cfg['allow_registration'] ?? 1) ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="toggle-row" style="margin-top:1rem;">
            <div>
                <strong>Require 2FA for Admins</strong>
                <p class="setting-desc">Enforce two-factor authentication for admin accounts.</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="require_2fa" <?= ($cfg['require_2fa'] ?? 0) ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">🔒 Save Security Settings</button>
</form>

<?php elseif($active_tab === 'email'): ?>
<!-- ====== EMAIL ====== -->
<form method="POST" class="settings-form">
    <input type="hidden" name="settings_tab" value="email">
    <div class="settings-section">
        <h2 class="settings-section-title">SMTP Configuration</h2>
        <div class="form-grid">
            <div class="form-group">
                <label>SMTP Host</label>
                <input type="text" name="smtp_host" value="<?= s($cfg,'smtp_host','smtp.gmail.com') ?>" placeholder="smtp.gmail.com">
            </div>
            <div class="form-group">
                <label>SMTP Port</label>
                <input type="number" name="smtp_port" value="<?= s($cfg,'smtp_port','587') ?>">
            </div>
            <div class="form-group">
                <label>SMTP Username</label>
                <input type="text" name="smtp_user" value="<?= s($cfg,'smtp_user') ?>" placeholder="your@gmail.com">
            </div>
            <div class="form-group">
                <label>SMTP Password</label>
                <input type="password" name="smtp_pass" placeholder="••••••••">
                <small style="color:#94a3b8;">Leave blank to keep current password</small>
            </div>
            <div class="form-group">
                <label>Encryption</label>
                <select name="smtp_enc">
                    <option value="tls" <?= ($cfg['smtp_enc']??'tls')==='tls'?'selected':'' ?>>TLS (Port 587)</option>
                    <option value="ssl" <?= ($cfg['smtp_enc']??'')==='ssl'?'selected':'' ?>>SSL (Port 465)</option>
                    <option value="none" <?= ($cfg['smtp_enc']??'')==='none'?'selected':'' ?>>None</option>
                </select>
            </div>
        </div>
    </div>
    <div class="settings-section">
        <h2 class="settings-section-title">Sender Details</h2>
        <div class="form-grid">
            <div class="form-group">
                <label>From Name</label>
                <input type="text" name="from_name" value="<?= s($cfg,'email_from_name','Reubentech Hub') ?>">
            </div>
            <div class="form-group">
                <label>From Email</label>
                <input type="email" name="from_email" value="<?= s($cfg,'email_from_email','no-reply@example.com') ?>">
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">📧 Save Email Settings</button>
</form>

<?php elseif($active_tab === 'appearance'): ?>
<!-- ====== APPEARANCE ====== -->
<form method="POST" class="settings-form">
    <input type="hidden" name="settings_tab" value="appearance">
    <div class="settings-section">
        <h2 class="settings-section-title">Theme & Colors</h2>
        <div class="form-grid">
            <div class="form-group">
                <label>Primary Color</label>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <input type="color" name="primary_color" value="<?= s($cfg,'primary_color','#2563eb') ?>" style="width:50px;height:36px;border:1px solid #cbd5e1;border-radius:4px;cursor:pointer;">
                    <input type="text" id="color_hex" value="<?= s($cfg,'primary_color','#2563eb') ?>" style="max-width:100px;" oninput="document.querySelector('[name=primary_color]').value=this.value">
                </div>
            </div>
            <div class="form-group">
                <label>Sidebar Theme</label>
                <select name="sidebar_theme">
                    <option value="dark"  <?= ($cfg['sidebar_theme']??'dark')==='dark'?'selected':'' ?>>Dark (default)</option>
                    <option value="light" <?= ($cfg['sidebar_theme']??'')==='light'?'selected':'' ?>>Light</option>
                    <option value="navy"  <?= ($cfg['sidebar_theme']??'')==='navy'?'selected':'' ?>>Navy Blue</option>
                </select>
            </div>
            <div class="form-group">
                <label>Items Per Page (tables)</label>
                <select name="items_per_page">
                    <?php foreach([10,20,50,100] as $n): ?>
                    <option value="<?= $n ?>" <?= ($cfg['items_per_page']??20)==$n?'selected':'' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Logo URL <small style="color:#94a3b8;">(optional)</small></label>
                <input type="text" name="logo_url" value="<?= s($cfg,'logo_url') ?>" placeholder="../logomilk.avif">
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">🎨 Save Appearance</button>
</form>

<?php elseif($active_tab === 'backup'): ?>
<!-- ====== BACKUP ====== -->
<div class="settings-section">
    <h2 class="settings-section-title">Database Backup</h2>
    <p style="color:#475569;margin-bottom:1.5rem;">Download a full SQL dump of your database, or restore from a previous backup file.</p>

    <div class="backup-actions">
        <a href="?tab=backup&action=export" class="btn btn-primary">⬇️ Export Database (SQL)</a>

        <form method="POST" action="?tab=backup&action=import" enctype="multipart/form-data" style="display:inline-flex;gap:0.5rem;align-items:center;">
            <input type="file" name="backup_file" accept=".sql" style="border:1px solid #cbd5e1;padding:0.35rem;border-radius:6px;font-size:0.8rem;">
            <button type="submit" class="btn btn-outline">⬆️ Import SQL</button>
        </form>
    </div>
</div>

<div class="settings-section">
    <h2 class="settings-section-title">System Info</h2>
    <table class="admin-table" style="max-width:600px;">
        <tbody>
            <tr><td style="font-weight:600;width:200px;">PHP Version</td><td><?= phpversion() ?></td></tr>
            <tr><td style="font-weight:600;">MySQL Version</td>
                <td><?php try { echo $conn->query("SELECT VERSION()")->fetchColumn(); } catch(Exception $e){ echo 'N/A'; } ?></td>
            </tr>
            <tr><td style="font-weight:600;">Server Software</td><td><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') ?></td></tr>
            <tr><td style="font-weight:600;">Server Time</td><td><?= date('Y-m-d H:i:s') ?></td></tr>
            <tr><td style="font-weight:600;">Document Root</td><td><?= htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') ?></td></tr>
        </tbody>
    </table>
</div>

<?php elseif($active_tab === 'mpesa'): ?>
<!-- ====== MPESA ====== -->
<form method="POST" class="settings-form">
    <input type="hidden" name="settings_tab" value="mpesa">
    <div class="settings-section">
        <h2 class="settings-section-title">M-Pesa Daraja API Integration</h2>
        <div class="toggle-row">
            <div>
                <strong>M-Pesa Sandbox Mock Mode</strong>
                <p class="setting-desc">When enabled, the system uses a simulated phone popup for demonstration and local testing without calling the actual Safaricom API.</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="mpesa_mock_mode" <?= ($cfg['mpesa_mock_mode'] ?? 1) ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="form-grid" style="margin-top: 1.5rem;">
            <div class="form-group">
                <label>Consumer Key</label>
                <input type="text" name="mpesa_consumer_key" value="<?= s($cfg,'mpesa_consumer_key') ?>" placeholder="Enter Safaricom Consumer Key" required>
            </div>
            <div class="form-group">
                <label>Consumer Secret</label>
                <input type="password" name="mpesa_consumer_secret" value="<?= s($cfg,'mpesa_consumer_secret') ?>" placeholder="Enter Safaricom Consumer Secret" required>
            </div>
            <div class="form-group">
                <label>Lipa Na M-Pesa Shortcode</label>
                <input type="text" name="mpesa_shortcode" value="<?= s($cfg,'mpesa_shortcode','174379') ?>" required>
            </div>
            <div class="form-group">
                <label>Lipa Na M-Pesa Passkey</label>
                <input type="password" name="mpesa_passkey" value="<?= s($cfg,'mpesa_passkey','bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919') ?>" required>
            </div>
            <div class="form-group" style="grid-column: span 2;">
                <label>STK Callback URL</label>
                <input type="url" name="mpesa_callback_url" value="<?= s($cfg,'mpesa_callback_url','http://localhost/milkproject/member/mpesa_callback.php') ?>" required>
                <small style="color:#94a3b8;">Must be a publicly accessible URL for Safaricom servers to reach (e.g. ngrok or live server URL) when Mock Mode is disabled.</small>
            </div>
            <div class="form-group">
                <label>Daraja Environment</label>
                <select name="mpesa_environment">
                    <option value="sandbox" <?= ($cfg['mpesa_environment']??'sandbox')==='sandbox'?'selected':'' ?>>Sandbox (Testing)</option>
                    <option value="live" <?= ($cfg['mpesa_environment']??'')==='live'?'selected':'' ?>>Live (Production)</option>
                </select>
            </div>
            <div class="form-group">
                <label>M-Pesa Test PIN (Mock Mode)</label>
                <input type="text" name="mpesa_test_pin" value="<?= s($cfg,'mpesa_test_pin','2026') ?>" pattern="[0-9]{4}" title="Must be a 4-digit number" required style="max-width: 150px;">
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">📲 Save M-Pesa Settings</button>
</form>
<?php endif; ?>

</div><!-- .settings-body -->

<script>
// Sync color picker with hex input
document.querySelector('[name=primary_color]')?.addEventListener('input', function() {
    const hex = document.getElementById('color_hex');
    if(hex) hex.value = this.value;
});
</script>

<?php adminFooter(); ?>
