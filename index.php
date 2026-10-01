<?php
$message = "";
$status = "";

$input_dir = "/input";
$output_dir = "/output";

// Extensions d'archives supportées
$archive_extensions = ['rar', 'zip', '7z', 'tar', 'gz', 'bz2'];

// Lister les éléments disponibles dans /input (dossiers et fichiers valides)
$inputs = [];
if (is_dir($input_dir)) {
    $scan = array_diff(scandir($input_dir), ['.', '..']);
    foreach ($scan as $item) {
        $path = $input_dir . '/' . $item;
        if (is_dir($path)) {
            $inputs[] = $item;
        } else {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (in_array($ext, $archive_extensions)) {
                $inputs[] = $item;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_input = $_POST['input_path'] ?? '';
    $output_filename = $_POST['output_filename'] ?? '';
    $format = $_POST['format'] ?? 'ffpkg';
    
    if (!empty($selected_input) && !empty($output_filename)) {
        $full_input = $input_dir . '/' . $selected_input;
        
        // S'assurer de l'extension correcte selon le format choisi
        if ($format === 'exfat') {
            if (!str_ends_with($output_filename, '.exfat')) $output_filename .= '.exfat';
        } else {
            if (!str_ends_with($output_filename, '.ffpkg')) $output_filename .= '.ffpkg';
        }
        
        // Commande d'exécution vers l'entrypoint bash (gère automatiquement les dossiers ou les fichiers .rar)
        $cmd = "bash /usr/local/bin/entrypoint.sh -i " . escapeshellarg($full_input) . " -o " . escapeshellarg($output_filename) . " -y 2>&1";
        
        $output_log = [];
        $return_var = 0;
        exec($cmd, $output_log, $return_var);
        
        if ($return_var === 0) {
            $message = "Conversion réussie ! Fichier généré : " . htmlspecialchars($output_filename);
            $status = "success";
        } else {
            $message = "Erreur lors de la conversion :<br><pre>" . htmlspecialchars(implode("\n", $output_log)) . "</pre>";
            $status = "error";
        }
    } else {
        $message = "Veuillez sélectionner un élément source et indiquer un nom de fichier de sortie.";
        $status = "error";
    }
}
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
      --error: #ef4444;
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
    .dropzone {
      background: var(--bg-card);
      border: 1px dashed var(--border-color);
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      color: var(--text-muted);
    }
    .dropzone select {
      margin-top: 10px;
      width: 100%;
      max-width: 400px;
      padding: 10px;
      background: var(--bg-input);
      border: 1px solid var(--border-color);
      color: var(--text-main);
      border-radius: 6px;
      font-size: 13px;
    }
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
    }
    .alert { padding: 10px; border-radius: 6px; font-size: 12px; margin-bottom: 10px; }
    .alert.success { background: rgba(16,185,129,0.15); color: var(--success); border: 1px solid rgba(16,185,129,0.3); }
    .alert.error { background: rgba(239,68,68,0.15); color: var(--error); border: 1px solid rgba(239,68,68,0.3); }
    pre { white-space: pre-wrap; font-size: 11px; margin-top: 5px; }

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
    .footer-log {
      background: #030712;
      padding: 6px 16px;
      font-size: 11px;
      color: var(--text-muted);
      border-top: 1px solid var(--border-color);
    }
  </style>
</head>
<body>

  <div class="app-header">
    <div class="app-title">
      📦 dump2ufs <span class="app-version">v1.4.0</span>
      <span class="app-subtitle">PS5 Game Dump Converter</span>
    </div>
  </div>

  <form method="POST" class="app-body">
    
    <?php if($message): ?>
      <div class="alert <?php echo $status; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Sélection de la source (Dossier ou Archive .rar / .zip) -->
    <div class="dropzone">
      <div style="font-size: 24px; margin-bottom: 5px;">📂</div>
      <div style="font-size: 13px; font-weight: 500;">Sélectionnez un dossier de jeu ou une archive (.rar) depuis /input</div>
      <select name="input_path" required>
        <option value="">-- Choisissez un dossier ou une archive source --</option>
        <?php foreach($inputs as $item): ?>
          <option value="<?php echo htmlspecialchars($item); ?>"><?php echo htmlspecialchars($item); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Output Directory -->
    <div class="output-row">
      <span class="output-label">📁 Output Filename:</span>
      <input type="text" name="output_filename" class="output-input" placeholder="ex: mon_jeu.ffpkg" required>
    </div>

    <!-- Conversion Queue / Infos -->
    <div class="queue-box">
      <div class="queue-header">
        <span>📋 Status & Informations</span>
      </div>
      <div class="queue-content">
        <div style="color: var(--text-muted); font-size: 12px; line-height: 1.5;">
          • Compatible avec les dossiers de jeux et les archives compressées (<code style="color:var(--text-main)">.rar</code>, etc.) situés dans <code style="color:var(--text-main)">/input</code>.<br>
          • L'outil monte automatiquement les archives via FUSE et génère le fichier UFS2 dans <code style="color:var(--text-main)">/output</code>.
        </div>
      </div>
    </div>

    <!-- Bottom Bar inside form to submit -->
    <div class="bottom-bar" style="position: absolute; bottom: 24px; left: 0; right: 0; width: 100%;">
      <div class="format-group">
        <span style="font-size: 13px; color: var(--text-muted);">Format:</span>
        <select name="format" class="select-format">
          <option value="ffpkg">UFS2 Image (.ffpkg)</option>
          <option value="exfat">exFAT Image (.exfat)</option>
        </select>
        <div class="status-badge">
          <div class="status-dot"></div>
          <span>Container ready</span>
        </div>
      </div>
      <div>
        <button type="submit" class="btn-convert">⚡ Convert to UFS2</button>
      </div>
    </div>
  </form>

  <div class="footer-log">
    <span>Ready — En attente d'une action</span>
  </div>

</body>
</html>
