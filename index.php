<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$message = "";
$status = "";

$input_dir = "/input";
$output_dir = "/output";

// Définition de toutes les actions disponibles
$actions = [
    'dump_to_ffpkg'   => ['label' => 'Créer image UFS2 (.ffpkg) depuis Dossier / Archive', 'type' => 'file_or_dir', 'ext' => ['rar', 'zip', '7z', 'tar', 'gz'], 'out_type' => 'file', 'def_ext' => '.ffpkg'],
    'dump_to_exfat'   => ['label' => 'Créer image exFAT (.exfat) depuis Dossier / Archive', 'type' => 'file_or_dir', 'ext' => ['rar', 'zip', '7z', 'tar', 'gz'], 'out_type' => 'file', 'def_ext' => '.exfat'],
    'dump_to_pkg'     => ['label' => 'Convertir Dossier / Dump vers Package PS5 (.pkg)', 'type' => 'dir_only', 'ext' => [], 'out_type' => 'file', 'def_ext' => '.pkg'],
    'image_to_pkg'    => ['label' => 'Convertir Image (.exfat / .ffpfsc) vers Package PS5 (.pkg)', 'type' => 'file_only', 'ext' => ['exfat', 'ffpfsc'], 'out_type' => 'file', 'def_ext' => '.pkg'],
    'extract_image'   => ['label' => 'Extraire Image (.exfat / .ffpfsc) vers Dossier de jeu', 'type' => 'file_only', 'ext' => ['exfat', 'ffpfsc'], 'out_type' => 'dir', 'def_ext' => ''],
    'extract_pkg'     => ['label' => 'Extraire Package PS5 (.pkg) vers Dossier de jeu', 'type' => 'file_only', 'ext' => ['pkg'], 'out_type' => 'dir', 'def_ext' => ''],
    'exfat_to_ffpfsc' => ['label' => 'Convertir Image .exfat vers .ffpfsc (Format Compressé)', 'type' => 'file_only', 'ext' => ['exfat'], 'out_type' => 'file', 'def_ext' => '.ffpfsc'],
    'ffpfsc_to_exfat' => ['label' => 'Décompresser Image .ffpfsc vers .exfat brute', 'type' => 'file_only', 'ext' => ['ffpfsc'], 'out_type' => 'file', 'def_ext' => '.exfat'],
];

// Lister tous les éléments disponibles dans /input
$all_inputs = [];
if (is_dir($input_dir)) {
    $scanned = @scandir($input_dir);
    if ($scanned !== false) {
        $scan = array_diff($scanned, ['.', '..']);
        foreach ($scan as $item) {
            $path = $input_dir . '/' . $item;
            $all_inputs[] = [
                'name' => $item,
                'is_dir' => is_dir($path),
                'ext' => strtolower(pathinfo($item, PATHINFO_EXTENSION))
            ];
        }
    } else {
        $message = "Attention : permissions insuffisantes pour lire le dossier /input.";
        $status = "error";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_key = $_POST['action_mode'] ?? 'dump_to_ffpkg';
    $selected_input = $_POST['input_path'] ?? '';
    $output_target = trim($_POST['output_target'] ?? '');

    if (!empty($selected_input) && !empty($output_target) && isset($actions[$action_key])) {
        $full_input = $input_dir . '/' . $selected_input;
        $action_info = $actions[$action_key];
        
        // Ajuster l'extension si la sortie attendue est un fichier
        if ($action_info['out_type'] === 'file' && !empty($action_info['def_ext'])) {
            if (!str_ends_with(strtolower($output_target), $action_info['def_ext'])) {
                $output_target .= $action_info['def_ext'];
            }
        }

        // Appel de entrypoint.sh avec le flag d'action -m (--mode)
        $cmd = "bash /usr/local/bin/entrypoint.sh -m " . escapeshellarg($action_key) . 
               " -i " . escapeshellarg($full_input) . 
               " -o " . escapeshellarg($output_target) . " -y 2>&1";

        $output_log = [];
        $return_var = 0;
        exec($cmd, $output_log, $return_var);

        if ($return_var === 0) {
            $message = "Opération terminée avec succès ! Cible : <strong>" . htmlspecialchars($output_target) . "</strong>";
            $status = "success";
        } else {
            $message = "Erreur d'exécution :<br><pre>" . htmlspecialchars(implode("\n", $output_log)) . "</pre>";
            $status = "error";
        }
    } else {
        $message = "Veuillez sélectionner une action valide, un élément source et indiquer une destination.";
        $status = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>PS5 Toolstation & Dump Converter</title>
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
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .app-header {
      display: flex;
      align-items: center;
      padding: 14px 24px;
      border-bottom: 1px solid var(--border-color);
      background: var(--bg-card);
    }
    .app-title { display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 15px; }
    .app-version { color: #a855f7; font-size: 12px; font-weight: 500; }
    .app-subtitle { color: var(--text-muted); font-size: 12px; margin-left: 10px; }

    .app-body {
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 16px;
      flex: 1;
      max-width: 1000px;
      width: 100%;
      margin: 0 auto;
    }
    .card {
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      border-radius: 10px;
      padding: 16px 20px;
    }
    .card-label {
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--text-muted);
      margin-bottom: 8px;
    }
    select, input[type="text"] {
      width: 100%;
      background: var(--bg-input);
      border: 1px solid var(--border-color);
      color: var(--text-main);
      padding: 10px 12px;
      border-radius: 6px;
      font-size: 13px;
    }
    select:focus, input[type="text"]:focus {
      outline: none;
      border-color: var(--accent);
    }
    .alert { padding: 12px 16px; border-radius: 6px; font-size: 13px; }
    .alert.success { background: rgba(16,185,129,0.15); color: var(--success); border: 1px solid rgba(16,185,129,0.3); }
    .alert.error { background: rgba(239,68,68,0.15); color: var(--error); border: 1px solid rgba(239,68,68,0.3); }
    pre { white-space: pre-wrap; font-size: 11px; margin-top: 8px; }

    .bottom-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: var(--bg-card);
      padding: 16px 24px;
      border-top: 1px solid var(--border-color);
      margin-top: auto;
    }
    .btn-convert {
      background: var(--accent);
      color: white;
      border: none;
      padding: 12px 28px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 13px;
      cursor: pointer;
      transition: background 0.2s;
    }
    .btn-convert:hover { background: var(--accent-hover); }
    .meta-desc { font-size: 12px; color: var(--text-muted); margin-top: 6px; }
  </style>
</head>
<body>

  <div class="app-header">
    <div class="app-title">
      📦 PS5 Toolstation <span class="app-version">v2.0</span>
      <span class="app-subtitle">UFS2 / exFAT / FFPFSC / PKG Builder</span>
    </div>
  </div>

  <form method="POST" class="app-body" id="mainForm">
    
    <?php if($message): ?>
      <div class="alert <?php echo $status; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- 1. Sélection de l'action / opération -->
    <div class="card">
      <div class="card-label">⚙️ Opération à effectuer</div>
      <select name="action_mode" id="action_mode" onchange="updateFormContext()" required>
        <?php foreach($actions as $key => $info): ?>
          <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($info['label']); ?></option>
        <?php endforeach; ?>
      </select>
      <div class="meta-desc" id="action_help">Sélectionnez le mode de traitement adapté à vos fichiers.</div>
    </div>

    <!-- 2. Sélection de l'élément source dans /input -->
    <div class="card">
      <div class="card-label">📂 Fichier ou Dossier source (/input)</div>
      <select name="input_path" id="input_path" required>
        <option value="">-- Choisissez un élément --</option>
        <?php foreach($all_inputs as $in): ?>
          <option value="<?php echo htmlspecialchars($in['name']); ?>" 
                  data-isdir="<?php echo $in['is_dir'] ? '1' : '0'; ?>" 
                  data-ext="<?php echo htmlspecialchars($in['ext']); ?>">
            <?php echo ($in['is_dir'] ? '📁 ' : '📄 ') . htmlspecialchars($in['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- 3. Nom de destination dans /output -->
    <div class="card">
      <div class="card-label" id="target_label">📁 Nom du fichier de sortie (/output)</div>
      <input type="text" name="output_target" id="output_target" placeholder="ex: mon_jeu" required>
      <div class="meta-desc" id="target_help">Indiquez le nom final souhaité.</div>
    </div>

    <!-- Barre inférieure d'exécution -->
    <div class="bottom-bar">
      <div style="font-size: 13px; color: var(--text-muted);">
        Traitement conteneurisé natif Linux (.NET 10 / makefs / fuse)
      </div>
      <button type="submit" class="btn-convert" id="submit_btn">⚡ Démarrer l'opération</button>
    </div>
  </form>

  <script>
    const actionsConfig = <?php echo json_encode($actions); ?>;

    function updateFormContext() {
      const mode = document.getElementById('action_mode').value;
      const config = actionsConfig[mode];
      const sourceSelect = document.getElementById('input_path');
      const targetLabel = document.getElementById('target_label');
      const targetInput = document.getElementById('output_target');
      const targetHelp = document.getElementById('target_help');
      const btn = document.getElementById('submit_btn');

      // Filtrer et adapter les options de sources selon le type requis
      const options = sourceSelect.querySelectorAll('option');
      options.forEach(opt => {
        if (!opt.value) return;
        const isDir = opt.getAttribute('data-isdir') === '1';
        const ext = opt.getAttribute('data-ext');

        let show = true;
        if (config.type === 'dir_only' && !isDir) show = false;
        if (config.type === 'file_only' && isDir) show = false;
        if (config.type === 'file_only' && config.ext.length > 0 && !config.ext.includes(ext)) show = false;
        
        opt.style.display = show ? '' : 'none';
        opt.disabled = !show;
      });

      // Adapter le libellé de sortie
      if (config.out_type === 'dir') {
        targetLabel.innerText = "📁 Nom du sous-dossier de sortie (/output)";
        targetInput.placeholder = "ex: dump_extrait";
        targetHelp.innerText = "Un dossier contenant l'intégralité du contenu sera créé dans /output.";
        btn.innerText = "⚡ Lancer l'extraction";
      } else {
        targetLabel.innerText = "📄 Nom du fichier de sortie (/output)";
        targetInput.placeholder = "ex: MonJeu" + config.def_ext;
        targetHelp.innerText = "Le fichier généré aura automatiquement l'extension " + config.def_ext;
        btn.innerText = "⚡ Lancer la conversion";
      }
    }

    // Auto-remplissage intuitif du nom de sortie quand l'utilisateur choisit sa source
    document.getElementById('input_path').addEventListener('change', function() {
      const val = this.value;
      if (!val) return;
      const mode = document.getElementById('action_mode').value;
      const config = actionsConfig[mode];
      
      let baseName = val.replace(/\.[^/.]+$/, ""); // Retire l'extension
      if (config.out_type === 'file') {
        document.getElementById('output_target').value = baseName + config.def_ext;
      } else {
        document.getElementById('output_target').value = baseName + "_extracted";
      }
    });

    // Initialisation au chargement
    updateFormContext();
  </script>
</body>
</html>
