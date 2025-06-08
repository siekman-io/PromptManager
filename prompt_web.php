<?php
require_once __DIR__ . '/config/config.php';

$dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (Exception $e) {
    die("DB-connectie mislukt: " . htmlspecialchars($e->getMessage()));
}

function findVariables($prompt) {
    preg_match_all('/{{(.*?)}}/', $prompt, $matches);
    return array_unique($matches[1]);
}

$added_msg = $deleted_msg = $filled_prompt = '';
$edit_id = $show_edit = $copied = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $title = trim($_POST['title']);
        $omschrijving = trim($_POST['omschrijving']);
        $prompt_body = trim($_POST['prompt_body']);
        $subcategory = trim($_POST['subcategory']);
        $ai_platform = trim($_POST['ai_platform']);
        if ($title && $prompt_body) {
            $stmt = $pdo->prepare("INSERT INTO prompts (title, omschrijving, prompt_body, subcategory, ai_platform) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $omschrijving, $prompt_body, $subcategory, $ai_platform]);
            $added_msg = "Prompt toegevoegd!";
        }
    } elseif (isset($_POST['delete_id'])) {
        $id = intval($_POST['delete_id']);
        $pdo->prepare("DELETE FROM prompts WHERE id=?")->execute([$id]);
        $deleted_msg = "Prompt verwijderd!";
    } elseif (isset($_POST['select'])) {
        $edit_id = intval($_POST['id']);
        $show_edit = false;
    } elseif (isset($_POST['fillvars'])) {
        $id = intval($_POST['id']);
        $prompt = $pdo->query("SELECT * FROM prompts WHERE id=$id")->fetch();
        $prompt_body = $prompt['prompt_body'];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'var_') === 0) {
                $varname = substr($key, 4);
                $prompt_body = str_replace('{{'.$varname.'}}', $value, $prompt_body);
            }
        }
        $pdo->prepare("UPDATE prompts SET last_used=NOW() WHERE id=?")->execute([$id]);
        $filled_prompt = $prompt_body;
        $edit_id = $id;
        $show_edit = true;
    } elseif (isset($_POST['edit_and_copy'])) {
        $edit_id = intval($_POST['edit_id']);
        $filled_prompt = $_POST['edit_prompt'];
        $pdo->prepare("UPDATE prompts SET last_used=NOW() WHERE id=?")->execute([$edit_id]);
        $copied = true;
        $show_edit = true;
    }
}

$search = trim($_GET['search'] ?? '');
$list_sql = "SELECT * FROM prompts";
if ($search) {
    $search_esc = "%$search%";
    $list_sql .= " WHERE title LIKE :q OR omschrijving LIKE :q OR subcategory LIKE :q OR ai_platform LIKE :q";
}
$list_sql .= " ORDER BY last_used DESC, date_added DESC";
$list_stmt = $pdo->prepare($list_sql);
if ($search) {
    $list_stmt->execute(['q' => $search_esc]);
} else {
    $list_stmt->execute();
}
$prompts = $list_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Prompt Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        body {
            background: #121212;
            color: #eaeaea;
            font-family: 'Inter', Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .container {
            width: 100vw;
            max-width: none;
            margin: 0 auto;
            padding: 10px 0 36px 0;
            background: transparent;
            min-height: 100vh;
            z-index: 2;
            position: relative;
        }
        h1, h2 {margin-top: 0;}
        .searchbar {
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
        }
        .searchbar input[type="text"] {
            flex: 1;
            font-size: 1.2em;
            padding: 8px 14px;
            border-radius: 10px;
            border: none;
            background: #333;
            color: #fff;
        }
        .searchbar button {
            padding: 8px 22px;
            border-radius: 10px;
            background: #c26c14;
            color: #fff;
            border: none;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.15s;
        }
        .searchbar button:hover { background: #ffb554;}
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
            table-layout: fixed;
            word-break: break-word;
            display: block;
            overflow-x: auto;
            min-width: 450px;
            font-size: 0.95em;
        }
        th, td {
            padding: 7px 10px;
            border-bottom: 1px solid #333;
            text-align: left;
            font-size: 0.97em;
        }
        th { color: #ff9900; font-size: 1.01em;}
        tr:hover {background: #181818;}
        .btn-delete {
            background: #a12d37;
            color: #fff;
            padding: 4px 13px;
            border-radius: 7px;
            border: none;
            cursor: pointer;
            font-size: 0.93em;
        }
        .btn-delete:hover { background: #e83b4a; }
        .btn-edit, .btn-copy {
            background: #c26c14;
            color: #fff;
            padding: 4px 13px;
            border-radius: 7px;
            border: none;
            cursor: pointer;
            font-size: 0.93em;
            margin-right: 8px;
        }
        .btn-copy {background: #ffb554;}
        .btn-edit:hover { background: #ff9900;}
        .btn-copy:hover { background: #ffc77d;}
        .msg {
            padding: 12px;
            background: #33210c;
            color: #ffb554;
            margin-bottom: 18px;
            border-radius: 8px;
        }
        .form-box {
            background: #191c1d;
            padding: 18px 16px 10px 16px;
            border-radius: 12px;
            margin-bottom: 34px;
            max-width: 900px;
        }
        .form-box label {display:block; margin:10px 0 3px;}
        .form-box input, .form-box textarea {
            width: 100%; border-radius: 8px; border: none; padding: 7px;
            background: #242628; color: #fff; font-size: 1em;
        }
        .form-box textarea {min-height: 110px;}
        .form-box button {margin-top:14px;}
        .promptarea {
            background: #181f23; color: #fff; width: 100%;
            border-radius: 10px; border: 1px solid #444;
            font-size: 1.08em; padding: 15px; min-height: 90px; margin-top:8px;
        }
        .varsform {margin-bottom: 15px;}
        .varsform label {font-weight: bold; color: #ff9900;}
        .copiedmsg {
            background: #c26c14; color: #fff; padding: 6px 18px;
            display: inline-block; margin-top: 8px; border-radius: 8px;
            font-weight: bold;
        }
        /* Mobiele kaarten (cards) */
        .mobile-cards { display:none; }
        @media (max-width:700px) {
            table { display:none; }
            .container { padding: 4px 0 28px 0; }
            .mobile-cards { display:block; }
            .mob-card {
                background: #191c1d;
                margin-bottom: 13px;
                border-radius: 10px;
                padding: 13px 12px;
                box-shadow: 0 2px 8px #0006;
            }
            .mob-title { font-weight:bold; color:#ff9900; font-size:1.1em; margin-bottom:4px;}
            .mob-omsch { color:#fff; margin-bottom:9px; font-size:1em;}
            .mob-actions button { margin-right:10px; }
        }
        @media (max-width:900px){
            .container {padding: 0 3vw;}
            .form-box {max-width: 99vw;}
        }
        #vanta-bg {
            position:fixed; z-index:0; left:0; top:0; width:100vw; height:100vh; pointer-events:none;
        }
        #vanta-overlay {
            position:fixed; z-index:1; left:0; top:0; width:100vw; height:100vh;
            background: rgba(18,18,18,0.95);
            pointer-events:none;
        }
        .container { position:relative; z-index:2; }
    </style>
    <script>
        function copyPrompt() {
            let textarea = document.getElementById('promptText');
            textarea.select();
            textarea.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(textarea.value);
            document.getElementById('copiedmsg').style.display = "inline-block";
            setTimeout(()=>{ document.getElementById('copiedmsg').style.display = "none"; }, 1400);
        }
    </script>
</head>
<body>
<div id="vanta-bg"></div>
<div id="vanta-overlay"></div>
<div class="container">
    <h1>🪄 Prompt Manager</h1>
    <?php if (!empty($added_msg)): ?><div class="msg"><?=$added_msg?></div><?php endif;?>
    <?php if (!empty($deleted_msg)): ?><div class="msg"><?=$deleted_msg?></div><?php endif;?>

    <form class="searchbar" method="get" action="">
        <input type="text" name="search" placeholder="Zoeken op titel, omschrijving, subcat, platform" value="<?=htmlspecialchars($search)?>">
        <button type="submit">Zoek</button>
        <button type="button" onclick="document.getElementById('addform').style.display='block'; this.style.display='none';">+ Prompt toevoegen</button>
    </form>

    <!-- Desktop/tablet: tabel -->
    <table>
        <tr>
            <th></th>
            <th>Titel</th>
            <th>Omschrijving</th>
            <th>Subcat</th>
            <th>Platform</th>
            <th>Toegevoegd</th>
            <th>Laatst gebruikt</th>
            <th>Acties</th>
        </tr>
        <?php foreach($prompts as $row): ?>
        <tr>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="id" value="<?=$row['id']?>">
                    <button class="btn-edit" name="select" value="1">Gebruik</button>
                </form>
            </td>
            <td><?=htmlspecialchars($row['title'])?></td>
            <td><?=htmlspecialchars($row['omschrijving'])?></td>
            <td><?=htmlspecialchars($row['subcategory'])?></td>
            <td><?=htmlspecialchars($row['ai_platform'])?></td>
            <td><?=$row['date_added']?></td>
            <td><?=$row['last_used']?></td>
            <td>
                <form method="post" style="display:inline;" onsubmit="return confirm('Prompt verwijderen?');">
                    <input type="hidden" name="delete_id" value="<?=$row['id']?>">
                    <button class="btn-delete">Verwijder</button>
                </form>
            </td>
        </tr>
        <?php endforeach;?>
    </table>

    <!-- Mobiel: cards -->
    <?php if (count($prompts) > 0): ?>
    <div class="mobile-cards">
      <?php foreach($prompts as $row): ?>
        <div class="mob-card">
          <div class="mob-title"><?=htmlspecialchars($row['title'])?></div>
          <div class="mob-omsch"><?=htmlspecialchars($row['omschrijving'])?></div>
          <div class="mob-actions">
            <form method="post" style="display:inline;">
              <input type="hidden" name="id" value="<?=$row['id']?>">
              <button class="btn-edit" name="select" value="1">Gebruik</button>
            </form>
            <form method="post" style="display:inline;" onsubmit="return confirm('Prompt verwijderen?');">
              <input type="hidden" name="delete_id" value="<?=$row['id']?>">
              <button class="btn-delete">Verwijder</button>
            </form>
          </div>
        </div>
      <?php endforeach;?>
    </div>
    <?php endif; ?>

    <div id="addform" class="form-box" style="display:<?=(isset($_POST['add']) ? 'block' : 'none')?>;">
        <h2>Prompt toevoegen</h2>
        <form method="post">
            <label>Titel *</label>
            <input type="text" name="title" required>
            <label>Omschrijving / details</label>
            <textarea name="omschrijving"></textarea>
            <label>Subcategorie</label>
            <input type="text" name="subcategory">
            <label>AI platform</label>
            <input type="text" name="ai_platform" placeholder="ChatGPT, Claude, etc">
            <label>Prompt body *</label>
            <textarea name="prompt_body" required></textarea>
            <button type="submit" name="add">Toevoegen</button>
        </form>
    </div>

    <?php
    if ((isset($_POST['select']) && isset($_POST['id'])) || (isset($show_edit) && $show_edit && isset($edit_id))) {
        $edit_id = $edit_id ?? intval($_POST['id']);
        $prompt = $pdo->query("SELECT * FROM prompts WHERE id=$edit_id")->fetch();
        $vars = findVariables($prompt['prompt_body']);

        if (!$show_edit): // Variabelen invullen
    ?>
        <div class="form-box">
            <h2>Vul variabelen in</h2>
            <p style="margin:7px 0 0 0; color:#ff9900; font-size:1.03em;">
                <strong>Omschrijving:</strong> <?=htmlspecialchars($prompt['omschrijving'])?>
            </p>
            <form method="post" class="varsform">
                <input type="hidden" name="id" value="<?=$edit_id?>">
                <?php foreach($vars as $v): ?>
                    <label><?=htmlspecialchars($v)?></label>
                    <input type="text" name="var_<?=htmlspecialchars($v)?>" required>
                <?php endforeach;?>
                <button type="submit" name="fillvars">Genereer prompt</button>
            </form>
        </div>
    <?php
        elseif ($show_edit):
    ?>
        <div class="form-box" style="max-width:1400px;">
            <h2>Bewerk & kopieer prompt</h2>
            <p style="margin:7px 0 0 0; color:#ff9900; font-size:1.03em;">
                <strong>Omschrijving:</strong> <?=htmlspecialchars($prompt['omschrijving'])?>
            </p>
            <form method="post">
                <textarea id="promptText" name="edit_prompt" style="height:200px;"><?=htmlspecialchars($filled_prompt)?></textarea>
                <input type="hidden" name="edit_id" value="<?=$edit_id?>">
                <button type="submit" name="edit_and_copy" class="btn-copy">Kopieer</button>
                <span id="copiedmsg" class="copiedmsg" style="display:<?=(!empty($copied) ? 'inline-block' : 'none')?>;">Gekopieerd!</span>
            </form>
            <button type="button" onclick="copyPrompt();" class="btn-copy" style="margin-top:8px;">Kopieer naar clipboard</button>
        </div>
    <?php endif; } ?>

</div>
<!-- Polygoon-animatie VANTA.NET -->
<script src="https://cdn.jsdelivr.net/npm/three@0.150.1/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.net.min.js"></script>
<script>
VANTA.NET({
  el: "#vanta-bg",
  mouseControls: false,
  touchControls: false,
  gyroControls: false,
  minHeight: 200.00,
  minWidth: 200.00,
  scale: 1.0,
  scaleMobile: 1.0,
  color: 0xffa64d, // metallic oranje
  backgroundColor: 0x121212,
  points: 7.0,
  maxDistance: 19.0,
  spacing: 18.0,
  showDots: false
})
</script>
</body>
</html>


