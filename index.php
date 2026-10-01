<?php
// Logique de traitement si un dossier est soumis
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'convert') {
    $input_path = $_POST['input_path'] ?? '';
    $output_filename = $_POST['output_filename'] ?? 'game.ffpkg';
    
    if (!empty($input_path)) {
        $full_input = "/input/" . $input_path;
        $cmd = "/var/www/html/entrypoint.sh -i " . escapeshellarg($full_input) . " -o " . escapeshellarg($output_filename) . " -y 2>&1";
        exec($cmd, $output_log, $return_var);
        
        if ($return_var === 0) {
            $message = "[SUCCESS] Conversion terminée avec succès pour : " . htmlspecialchars($input_path);
        } else {
            $message = "[ERROR] Échec de la conversion : " . htmlspecialchars(implode(" ", $output_log));
        }
    }
}

// Récupération des dossiers dans /input
$input_dir = "/input";
$folders = is_dir($input_dir) ? array_diff(scandir($input_dir), ['.', '..']) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>dump2ufs v1.4.0 - PS5 Game Dump Converter</title>
  <style>
    :root {
      --bg-main: #0b0f19;
      --bg-card: #111827;
      --bg-input: #1f2937;
      --border-color: rgba(255, 255, 255, 0.08);
      --text-main: #f8fafc;
      --text-muted: #94a3b8;
      --accent: #3b82f6;
      --accent-hover: #2563eb;
      --success: #10b981;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background-color: var(--bg-main);
      color: var(--text-main);
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
    }
    
    /* Header */
    .app-header {
      display: flex;
      align-items: center;
      padding: 12px 20px;
      border-bottom: 1px solid var(--border-color);
      background: var(--bg-card);
      font-size: 14px;
    }
    .app-title { display: flex; align-items: center; gap: 10px; font-weight: 600; }
    .app-version { color: #a855f7; font-size: 12px; font-weight: 500; }
    .app-subtitle { color: var(--text-muted); font-size: 11px; margin-left: 8px; }

    /* Main Container */
    .app-body {
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 16px;
      flex: 1;
      max-width: 1200px;
      width: 100%;
      margin: 0 auto;
    }

    /* Dropzone Box */
    .dropzone {
      background: var(--bg-card);
      border: 1px dashed var(--border-color);
      border-radius: 12px;
      padding: 24px;
      text-align: center;
      color: var(--text-muted);
      transition: border-color 0.2s;
    }
    .dropzone:hover { border-color: var(--accent); }
    .dropzone-icon { font-size: 28px; margin-bottom: 8px; }
    .dropzone-text { font-size: 13px; font-weight: 500; color: var(--text-main); }
    .dropzone-sub { font-size: 11px; margin-top: 4px; }

    /* Output Row */
    .output-row {
      display: flex;
      gap: 12px;
      background: var(--bg-card);
      padding: 12px;
      border-radius: 10px;
      border: 1px solid var(--border-color);
      align-items: center;
    }
    .output-label { font-size: 13px; color: var(--text-muted); white-space: nowrap; }
    .output-input {
      flex: 1;
      background: var(--bg-input);
      border: 1px solid var(--border-color);
      border-radius: 6px;
      padding: 8px 12px;
      color: var(--text-main);
      font-size: 13px;
    }
    .btn {
      background: var(--bg-input);
      border: 1px solid var(--border-color);
      color: var(--text-main);
      padding: 8px 16px;
      border-radius: 6px;
      font-size: 13px;
      cursor: pointer;
      font-weight: 500;
    }
    .btn:hover { background: #374151; }

    /* Queue Section */
    .queue-box {
      flex: 1;
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      border-radius: 12px;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .queue-header {
      padding: 12px 16px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 13px;
    }
    .queue-content {
      padding: 16px;
      overflow-y: auto;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .queue-item {
      background: var(--bg-input);
      padding: 10px 14px;
      border-radius: 6px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 13px;
      border: 1px solid var(--border-color);
    }

    /* Bottom Control Bar */
    .bottom-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: var(--bg-card);
      padding: 14px 20px;
      border-top: 1px solid var(--border-color);
    }
    .format-group { display: flex; align-items: center; gap: 12px; }
    .select-format {
      background: var(--bg-input);
      border: 1px solid var(--border-color);
      color: var(--text-main);
      padding: 8px 12px;
      border-radius: 6px;
      font-size: 13px;
    }
    .status-badge { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--success); }
    .status-dot { width: 8px; height: 8px; background: var(--success); border-radius: 50%; }
    .btn-convert {
      background: var(--accent);
      color: white;
      border: none;
      padding: 10px 24px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 13px;
      cursor: pointer;
    }
    .btn-convert:hover { background: var(--accent-hover); }

    /* Footer Log Bar */
    .footer-log {
      background: #030712;
      padding: 6px 16px;
      font-size: 11px;
      color: var(--text-muted);
      border-top: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
    }
  </style>
</head>
<body>

  <!-- Header -->
  <div class="app-header">
    <div class="app-title">
      📦 dump2ufs <span class="app-version">v1.4.0</span>
      <span class="app-subtitle">PS5 Game Dump Converter</span>
    </div>
  </div>

  <!-- Body -->
  <div class="app-body">
    
    <!-- Zone de glisser-déposer / sélection -->
    <div class="dropzone">
      <div class="dropzone-icon">📁</div>
      <div class="dropzone-text">Glissez-déposez vos dossiers de dumps de jeux PS5 ici</div>
      <div class="dropzone-sub">ou sélectionnez un dossier depuis le volume d'entrée du NAS</div>
    </div>

    <!-- Output Directory -->
    <div class="output-row">
      <span class="output-label">📁 Output Directory:</span>
      <input type="text" class="output-input" value="/output" readonly>
    </div>

    <!-- Conversion Queue -->
    <div class="queue-box">
      <div class="queue-header">
        <span>📋 Conversion Queue</span>
        <button class="btn" style="padding: 4px 10px; font-size: 11px;">Clear Queue</button>
      </div>
      <div class="queue-content">
        <?php if(empty($folders)): ?>
          <div style="color: var(--text-muted); text-align: center; margin-top: 40px; font-size: 13px;">
            Aucun jeu détecté dans le dossier /input de votre Synology.
          </div>
        <?php else: ?>
          <?php foreach($folders as $folder): ?>
            <div class="queue-item">
              <span>🎮 <?php echo htmlspecialchars($folder); ?></span>
              <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="convert">
                <input type="hidden" name="input_path" value="<?php echo htmlspecialchars($folder); ?>">
                <input type="hidden" name="output_filename" value="<?php echo htmlspecialchars($folder); ?>.ffpkg">
                <button type="submit" class="btn" style="padding: 4px 10px; font-size: 11px; background: var(--accent); border:none; color:white;">Convertir</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- Bottom Bar -->
  <form method="POST" class="bottom-bar">
    <div class="format-group">
      <span style="font-size: 13px; color: var(--text-muted);">Format:</span>
      <select class="select-format">
        <option>UFS2 Image (.ffpkg)</option>
        <option>exFAT Image (.exfat)</option>
      </select>
      <div class="status-badge">
        <div class="status-dot"></div>
        <span>Container ready</span>
      </div>
    </div>
    <div>
      <button type="submit" class="btn-convert">⚡ Convert to .ffpkg</option>
    </div>
  </form>

  <!-- Footer Log -->
  <div class="footer-log">
    <span><?php echo $message ? $message : "Ready — en attente d'action sur le NAS"; ?></span>
    <span>UFS2Tool v1.4.0</span>
  </div>

</body>
</html>