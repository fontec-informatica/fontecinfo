<?php
session_start();

/* ── CONFIG ── */
define('ADMIN_PASS',  'Fontec@2026');
define('JSON_FILE',   __DIR__ . '/data/fazendas.json');
define('UPLOAD_DIR',  __DIR__ . '/uploads/');
define('ALLOWED_IMG', ['image/jpeg','image/png','image/webp','image/gif']);
define('ALLOWED_VID', ['video/mp4','video/webm','video/ogg']);

/* ── HELPERS ── */
function loadFazendas(): array {
    if (!file_exists(JSON_FILE)) return [];
    return json_decode(file_get_contents(JSON_FILE), true) ?: [];
}

function saveFazendas(array $data): void {
    file_put_contents(JSON_FILE, json_encode(array_values($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function sanitize(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}

function generateThumb(string $src, string $dest, int $w = 420, int $h = 280): void {
    if (!function_exists('imagecreatefromjpeg')) return;
    $info = @getimagesize($src);
    if (!$info) return;
    [$srcW, $srcH, $type] = [$info[0], $info[1], $info[2]];
    $img = match($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => @imagecreatefrompng($src),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        IMAGETYPE_GIF  => @imagecreatefromgif($src),
        default        => false,
    };
    if (!$img) return;
    $ratio  = max($w / $srcW, $h / $srcH);
    $cropW  = (int)round($w / $ratio);
    $cropH  = (int)round($h / $ratio);
    $offX   = (int)(($srcW - $cropW) / 2);
    $offY   = (int)(($srcH - $cropH) / 2);
    $thumb  = imagecreatetruecolor($w, $h);
    imagecopyresampled($thumb, $img, 0, 0, $offX, $offY, $w, $h, $cropW, $cropH);
    imagejpeg($thumb, $dest, 82);
    imagedestroy($img);
    imagedestroy($thumb);
}

function uploadFiles(string $field, array $allowed, string $prefix): array {
    $result = [];
    if (empty($_FILES[$field]['name'][0])) return $result;
    $names = $_FILES[$field]['name'];
    $tmps  = $_FILES[$field]['tmp_name'];
    $types = $_FILES[$field]['type'];
    foreach ($names as $i => $name) {
        if (empty($tmps[$i]) || !is_uploaded_file($tmps[$i])) continue;
        if (!in_array($types[$i], $allowed)) continue;
        $ext  = pathinfo($name, PATHINFO_EXTENSION);
        $safe = $prefix . '_' . uniqid() . '.' . strtolower($ext);
        $dest = UPLOAD_DIR . $safe;
        if (move_uploaded_file($tmps[$i], $dest)) {
            $result[] = $safe;
            /* gera miniatura para o painel admin (carregamento rápido) */
            if (in_array($types[$i], ['image/jpeg','image/png','image/webp','image/gif'])) {
                generateThumb($dest, UPLOAD_DIR . 'thumb_' . $safe);
            }
        }
    }
    return $result;
}

function deleteFile(string $filename): void {
    $base  = basename($filename);
    $path  = UPLOAD_DIR . $base;
    $thumb = UPLOAD_DIR . 'thumb_' . $base;
    if (file_exists($path))  unlink($path);
    if (file_exists($thumb)) unlink($thumb);
}

function newId(): string {
    return uniqid('f_', true);
}

/* ── AUTH ── */
$loginError = '';
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASS) {
        $_SESSION['admin'] = true;
    } else {
        $loginError = 'Senha incorreta. Tente novamente.';
    }
}

/* ── AÇÕES (requer auth) ── */
$msg = '';
if (!empty($_SESSION['admin'])) {

    /* PUBLICAR / OCULTAR */
    if (isset($_GET['toggle'])) {
        $id = $_GET['toggle'];
        $rows = loadFazendas();
        foreach ($rows as &$r) {
            if ($r['id'] === $id) $r['publicado'] = empty($r['publicado']) ? 1 : 0;
        }
        saveFazendas($rows);
        header('Location: admin.php?ok=toggle');
        exit;
    }

    /* EXCLUIR */
    if (isset($_GET['del'])) {
        $id = $_GET['del'];
        $rows = loadFazendas();
        foreach ($rows as $r) {
            if ($r['id'] === $id) {
                foreach ($r['fotos'] ?? [] as $f) deleteFile($f);
                if (!empty($r['video_file'])) deleteFile($r['video_file']);
            }
        }
        $rows = array_filter($rows, fn($r) => $r['id'] !== $id);
        saveFazendas($rows);
        header('Location: admin.php?ok=del');
        exit;
    }

    /* SALVAR (criar / editar) */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
        $rows = loadFazendas();
        $id   = $_POST['id'] ?? '';
        $isNew = empty($id);

        $fazenda = [
            'id'          => $isNew ? newId() : $id,
            'nome'        => sanitize($_POST['nome']        ?? ''),
            'cidade'      => sanitize($_POST['cidade']      ?? ''),
            'estado'      => sanitize($_POST['estado']      ?? ''),
            'tipo'        => sanitize($_POST['tipo']        ?? ''),
            'hectares'    => sanitize($_POST['hectares']    ?? ''),
            'alqueires'   => sanitize($_POST['alqueires']   ?? ''),
            'preco'       => sanitize(str_replace(['.', ','], ['', '.'], $_POST['preco'] ?? '')),
            'agua'        => sanitize($_POST['agua']        ?? ''),
            'solo'        => sanitize($_POST['solo']        ?? ''),
            'benfeitorias'=> sanitize($_POST['benfeitorias']?? ''),
            'acesso'      => sanitize($_POST['acesso']      ?? ''),
            'descricao'   => sanitize($_POST['descricao']   ?? ''),
            'publicado'   => isset($_POST['publicado']) ? 1 : 0,
            'video_link'  => sanitize($_POST['video_link']  ?? ''),
            'fotos'       => [],
            'video_file'  => '',
            'criado_em'   => '',
        ];

        if (!$isNew) {
            /* mantém dados existentes */
            foreach ($rows as $r) {
                if ($r['id'] === $id) {
                    $fazenda['fotos']      = $r['fotos']      ?? [];
                    $fazenda['video_file'] = $r['video_file'] ?? '';
                    $fazenda['criado_em']  = $r['criado_em']  ?? '';
                    break;
                }
            }
        } else {
            $fazenda['criado_em'] = date('Y-m-d H:i:s');
        }

        /* upload fotos */
        $newFotos = uploadFiles('fotos', ALLOWED_IMG, 'foto');
        $fazenda['fotos'] = array_merge($fazenda['fotos'], $newFotos);

        /* upload vídeo */
        $newVid = uploadFiles('video_file', ALLOWED_VID, 'vid');
        if (!empty($newVid)) {
            if (!empty($fazenda['video_file'])) deleteFile($fazenda['video_file']);
            $fazenda['video_file'] = $newVid[0];
        }

        /* campo video: prioridade upload > link YouTube */
        $fazenda['video'] = !empty($fazenda['video_file'])
            ? $fazenda['video_file']
            : $fazenda['video_link'];

        /* fotos a remover */
        $delFotos = $_POST['del_fotos'] ?? [];
        if (!empty($delFotos)) {
            foreach ($delFotos as $df) deleteFile($df);
            $fazenda['fotos'] = array_values(array_filter($fazenda['fotos'], fn($x) => !in_array($x, $delFotos)));
        }

        if ($isNew) {
            $rows[] = $fazenda;
        } else {
            foreach ($rows as &$r) {
                if ($r['id'] === $id) { $r = $fazenda; break; }
            }
        }
        saveFazendas($rows);
        header('Location: admin.php?ok=save');
        exit;
    }
}

$rows    = !empty($_SESSION['admin']) ? loadFazendas() : [];
$editRow = null;
if (!empty($_GET['edit']) && !empty($_SESSION['admin'])) {
    $eid = $_GET['edit'];
    foreach ($rows as $r) { if ($r['id'] === $eid) { $editRow = $r; break; } }
}
$ok = $_GET['ok'] ?? '';
$okMsg = match($ok) {
    'save'   => 'Propriedade salva com sucesso!',
    'del'    => 'Propriedade excluída.',
    'toggle' => 'Visibilidade atualizada.',
    default  => '',
};
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin — Fontec Empreendimentos</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    :root {
      --bg:      #f5f8f6; --bg2: #edf2ef; --surface: #ffffff;
      --text:    #0b1f14; --muted: #4a6657;
      --accent:  #1a6b42; --accent2: #22c55e;
      --border:  rgba(26,107,66,.14);
      --shadow:  0 2px 20px rgba(26,107,66,.08);
      --radius:  14px; --radius-sm: 8px;
      --trans:   .3s ease;
      --danger:  #dc2626; --warn: #d97706;
    }
    [data-theme="dark"] {
      --bg:#060d09; --bg2:#0b1610; --surface:#0f1c13;
      --text:#e2f0e8; --muted:#7aad8e; --accent:#22c55e; --accent2:#4ade80;
      --border:rgba(34,197,94,.1); --shadow:0 2px 20px rgba(0,0,0,.4);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg); color: var(--text);
      transition: background var(--trans), color var(--trans);
      min-height: 100vh;
    }
    a { text-decoration: none; color: inherit; }

    /* HEADER */
    .admin-header {
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      padding: 0 5%; height: 64px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 50;
      box-shadow: var(--shadow);
    }
    .admin-brand { display: flex; align-items: center; gap: 10px; }
    .admin-brand img { height: 38px; mix-blend-mode: multiply; }
    [data-theme="dark"] .admin-brand img { mix-blend-mode: normal; filter: brightness(0) invert(1); }
    .admin-brand-text { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; }
    .admin-brand-badge {
      font-size: .6rem; font-weight: 700; letter-spacing: .06em;
      background: var(--accent); color: #fff;
      padding: 2px 8px; border-radius: 12px; text-transform: uppercase;
    }
    .header-right { display: flex; align-items: center; gap: 10px; }
    .btn-sm {
      padding: 7px 16px; border-radius: 20px;
      font-size: .82rem; font-weight: 600;
      border: 1.5px solid var(--border);
      background: var(--surface); color: var(--muted);
      cursor: pointer; transition: all var(--trans);
    }
    .btn-sm:hover { border-color: var(--accent); color: var(--accent); }
    .btn-danger { border-color: var(--danger); color: var(--danger); }
    .btn-danger:hover { background: var(--danger); color: #fff; }

    /* LOGIN */
    .login-wrap {
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      background: linear-gradient(160deg, var(--bg) 0%, var(--bg2) 100%);
    }
    .login-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 48px 40px;
      width: 100%; max-width: 400px;
      box-shadow: 0 20px 60px rgba(26,107,66,.12);
      text-align: center;
    }
    .login-card img { height: 60px; margin: 0 auto 20px; mix-blend-mode: multiply; }
    [data-theme="dark"] .login-card img { mix-blend-mode: normal; filter: brightness(0) invert(1); }
    .login-card h2 {
      font-family: 'Syne', sans-serif; font-size: 1.4rem; font-weight: 800;
      margin-bottom: 6px;
    }
    .login-card p { font-size: .88rem; color: var(--muted); margin-bottom: 28px; }
    .login-error {
      background: rgba(220,38,38,.1); color: var(--danger);
      border: 1px solid rgba(220,38,38,.2);
      border-radius: var(--radius-sm); padding: 10px 14px;
      font-size: .85rem; margin-bottom: 16px;
    }
    .field { margin-bottom: 16px; text-align: left; }
    .field label { display: block; font-size: .82rem; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
    .field input, .field textarea, .field select {
      width: 100%;
      padding: 11px 14px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      background: var(--bg); color: var(--text);
      font-family: 'DM Sans', sans-serif; font-size: .95rem;
      transition: border-color var(--trans);
    }
    .field input:focus, .field textarea:focus, .field select:focus {
      outline: none; border-color: var(--accent);
    }
    .field textarea { resize: vertical; min-height: 100px; }
    .btn-primary {
      width: 100%; padding: 13px;
      background: var(--accent); color: #fff;
      border: none; border-radius: var(--radius-sm);
      font-family: 'DM Sans', sans-serif; font-size: .95rem; font-weight: 700;
      cursor: pointer; transition: background var(--trans), transform var(--trans);
    }
    .btn-primary:hover { background: var(--accent2); transform: translateY(-1px); }

    /* MAIN */
    .admin-main { padding: 36px 5%; max-width: 1200px; margin: 0 auto; }

    /* ALERT */
    .alert {
      padding: 12px 18px; border-radius: var(--radius-sm);
      margin-bottom: 24px; font-size: .9rem; font-weight: 500;
      background: rgba(26,107,66,.1); color: var(--accent);
      border: 1px solid rgba(26,107,66,.2);
    }

    /* TOPO ADMIN */
    .admin-top {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 28px; flex-wrap: wrap; gap: 12px;
    }
    .admin-top h1 { font-family: 'Syne', sans-serif; font-size: 1.5rem; font-weight: 800; }

    /* TABELA */
    .table-wrap { overflow-x: auto; }
    table {
      width: 100%; border-collapse: collapse;
      background: var(--surface);
      border-radius: var(--radius); overflow: hidden;
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
    }
    thead { background: var(--bg2); }
    th {
      padding: 13px 16px; text-align: left;
      font-family: 'Syne', sans-serif; font-size: .78rem;
      font-weight: 700; letter-spacing: .04em;
      text-transform: uppercase; color: var(--muted);
      border-bottom: 1px solid var(--border);
    }
    td {
      padding: 13px 16px; font-size: .88rem;
      border-bottom: 1px solid var(--border);
      color: var(--text); vertical-align: middle;
    }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: var(--bg2); }

    .badge-pub {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 3px 10px; border-radius: 12px;
      font-size: .73rem; font-weight: 700; letter-spacing: .04em;
    }
    .badge-pub.pub  { background: rgba(26,107,66,.12); color: var(--accent); }
    .badge-pub.oculto { background: rgba(107,114,128,.12); color: var(--muted); }

    .td-actions { display: flex; gap: 8px; align-items: center; }
    .btn-icon {
      width: 32px; height: 32px;
      border-radius: 8px; border: 1px solid var(--border);
      background: var(--surface); color: var(--muted);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: all var(--trans); font-size: .85rem;
    }
    .btn-icon:hover          { background: var(--accent);  color: #fff; border-color: var(--accent); }
    .btn-icon.del:hover      { background: var(--danger);  color: #fff; border-color: var(--danger); }
    .btn-icon.toggle:hover   { background: var(--warn);    color: #fff; border-color: var(--warn); }

    /* FORMULÁRIO */
    .form-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 32px; margin-bottom: 36px;
      box-shadow: var(--shadow);
    }
    .form-card h2 {
      font-family: 'Syne', sans-serif; font-size: 1.2rem; font-weight: 800;
      margin-bottom: 24px;
      padding-bottom: 14px; border-bottom: 1px solid var(--border);
    }
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 16px;
    }
    .form-grid .field { margin-bottom: 0; }
    .field-full { grid-column: 1 / -1; }

    .fotos-preview {
      display: flex; flex-wrap: wrap; gap: 12px; margin-top: 10px;
    }
    .foto-thumb {
      display: flex; flex-direction: column; align-items: center; gap: 5px;
    }
    .foto-thumb-img {
      position: relative; width: 90px; height: 70px;
      border-radius: var(--radius-sm); overflow: hidden;
      border: 1.5px solid var(--border); transition: border-color .2s, opacity .2s;
    }
    .foto-thumb-img img { width: 100%; height: 100%; object-fit: cover; }
    .foto-thumb.marcada .foto-thumb-img { border-color: var(--danger); opacity: .4; }
    .foto-del-btn {
      font-size: .7rem; color: var(--danger);
      background: none; border: 1px solid var(--danger);
      border-radius: 6px; padding: 2px 7px;
      cursor: pointer; transition: all .2s; white-space: nowrap;
    }
    .foto-del-btn:hover, .foto-thumb.marcada .foto-del-btn {
      background: var(--danger); color: #fff;
    }
    .foto-thumb input[type=checkbox] { display: none; }

    .toggle-wrap {
      display: flex; align-items: center; gap: 10px;
      margin-top: 4px;
    }
    .toggle-wrap input[type=checkbox] {
      width: 44px; height: 24px;
      appearance: none; -webkit-appearance: none;
      border-radius: 12px; background: var(--bg2);
      border: 1.5px solid var(--border);
      cursor: pointer; position: relative;
      transition: background var(--trans);
    }
    .toggle-wrap input[type=checkbox]::before {
      content: ''; position: absolute;
      width: 18px; height: 18px; border-radius: 50%;
      background: #fff; top: 2px; left: 2px;
      transition: transform var(--trans);
      box-shadow: 0 1px 4px rgba(0,0,0,.2);
    }
    .toggle-wrap input[type=checkbox]:checked { background: var(--accent); border-color: var(--accent); }
    .toggle-wrap input[type=checkbox]:checked::before { transform: translateX(20px); }
    .toggle-wrap span { font-size: .88rem; color: var(--muted); }

    .form-actions { display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap; }
    .btn-cancel {
      padding: 12px 24px; border-radius: var(--radius-sm);
      border: 1.5px solid var(--border); background: var(--surface);
      color: var(--muted); font-size: .9rem; font-weight: 600;
      cursor: pointer; transition: all var(--trans);
    }
    .btn-cancel:hover { border-color: var(--accent); color: var(--accent); }

    /* EMPTY */
    .empty-row td { text-align: center; color: var(--muted); padding: 40px; }

    /* PROGRESS */
    .upload-overlay {
      display: none;
      position: fixed; inset: 0; z-index: 300;
      background: rgba(0,0,0,.6);
      backdrop-filter: blur(4px);
      align-items: center; justify-content: center;
    }
    .upload-overlay.show { display: flex; }
    .upload-box {
      background: var(--surface);
      border-radius: var(--radius);
      padding: 40px 48px;
      text-align: center;
      width: 100%; max-width: 420px;
      border: 1px solid var(--border);
      box-shadow: 0 30px 80px rgba(0,0,0,.3);
    }
    .upload-box h3 {
      font-family: 'Syne', sans-serif;
      font-size: 1.1rem; font-weight: 700;
      margin-bottom: 8px; color: var(--text);
    }
    .upload-box p { font-size: .88rem; color: var(--muted); margin-bottom: 24px; }
    .progress-track {
      background: var(--bg2);
      border-radius: 99px; height: 10px;
      overflow: hidden; margin-bottom: 12px;
    }
    .progress-fill {
      height: 100%; width: 0%;
      background: linear-gradient(90deg, var(--accent), var(--accent2));
      border-radius: 99px;
      transition: width .2s ease;
    }
    .progress-pct {
      font-family: 'Syne', sans-serif;
      font-size: 1.6rem; font-weight: 800;
      color: var(--accent); margin-bottom: 4px;
    }
    .progress-label { font-size: .8rem; color: var(--muted); }

    @media (max-width: 768px) {
      .admin-header { padding: 0 4%; height: 56px; }
      .admin-brand-text { font-size: .9rem; }
      .header-right .btn-sm span { display: none; }
      .form-grid { grid-template-columns: 1fr 1fr; }
      .upload-box { padding: 28px 20px; }
    }
    @media (max-width: 640px) {
      .admin-main { padding: 20px 4%; }
      .form-card  { padding: 16px; }
      .form-grid  { grid-template-columns: 1fr; }
      .admin-top  { flex-direction: column; align-items: flex-start; }
      th, td { padding: 9px 10px; font-size: .82rem; }
      .td-actions { gap: 5px; }
      /* esconde colunas menos importantes em tela pequena */
      th:nth-child(4), td:nth-child(4),
      th:nth-child(6), td:nth-child(6) { display: none; }
    }
  </style>
</head>
<body>

<?php if (empty($_SESSION['admin'])): ?>
<!-- ════════════════ LOGIN ════════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <img src="../assets/img/logo.png?v=2" alt="FONTEC" />
    <h2>Painel Administrativo</h2>
    <p>Fontec Empreendimentos — acesso restrito</p>
    <?php if ($loginError): ?>
      <div class="login-error"><i class="fa fa-exclamation-triangle"></i> <?= $loginError ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="field">
        <label for="pw">Senha de acesso</label>
        <input type="password" id="pw" name="password" placeholder="••••••••••••" autofocus required />
      </div>
      <button class="btn-primary" type="submit"><i class="fa fa-lock"></i> Entrar</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ════════════════ PAINEL ════════════════ -->
<header class="admin-header">
  <div class="admin-brand">
    <img src="../assets/img/logo.png?v=2" alt="FONTEC" />
    <div>
      <div class="admin-brand-text">FONTEC</div>
      <span class="admin-brand-badge">Admin</span>
    </div>
  </div>
  <div class="header-right">
    <a href="index.php" class="btn-sm" target="_blank"><i class="fa fa-eye"></i> Ver site</a>
    <form method="POST" style="display:inline">
      <button name="logout" class="btn-sm btn-danger"><i class="fa fa-sign-out-alt"></i> Sair</button>
    </form>
  </div>
</header>

<main class="admin-main">
  <?php if ($okMsg): ?>
    <div class="alert"><i class="fa fa-check-circle"></i> <?= $okMsg ?></div>
  <?php endif; ?>

  <!-- FORMULÁRIO CRIAR / EDITAR -->
  <div class="form-card" id="form-section">
    <h2><?= $editRow ? '<i class="fa fa-edit"></i> Editar Propriedade' : '<i class="fa fa-plus-circle"></i> Nova Propriedade' ?></h2>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save" />
      <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>" />

      <div class="form-grid">
        <div class="field">
          <label>Nome / Identificação *</label>
          <input type="text" name="nome" required value="<?= htmlspecialchars($editRow['nome'] ?? '') ?>" placeholder="Ex: Fazenda Santa Helena" />
        </div>
        <div class="field">
          <label>Cidade</label>
          <input type="text" name="cidade" value="<?= htmlspecialchars($editRow['cidade'] ?? '') ?>" placeholder="Ex: Anápolis" />
        </div>
        <div class="field">
          <label>Estado</label>
          <select name="estado">
            <?php
            $estados = ['GO','DF','MG','MT','MS','TO','BA','MG','SP','PR'];
            $sel = $editRow['estado'] ?? 'GO';
            foreach ($estados as $e) echo "<option" . ($e===$sel?' selected':'') . ">$e</option>";
            ?>
          </select>
        </div>
        <div class="field">
          <label>Tipo</label>
          <select name="tipo">
            <?php foreach (['Fazenda','Sítio','Chácara','Terra Nua','Outros'] as $t):
              $s = ($editRow['tipo'] ?? '') === $t ? ' selected' : ''; ?>
              <option<?= $s ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Hectares</label>
          <input type="number" step="0.01" id="inp_hectares" name="hectares" value="<?= htmlspecialchars($editRow['hectares'] ?? '') ?>" placeholder="Ex: 484" />
        </div>
        <div class="field">
          <label>Alqueires Goianos <small style="color:var(--muted)">(1 alq = 4,84 ha)</small></label>
          <input type="number" step="0.01" id="inp_alqueires" name="alqueires" value="<?= htmlspecialchars($editRow['alqueires'] ?? '') ?>" placeholder="Ex: 100" />
        </div>
        <div class="field">
          <label>Preço (R$)</label>
          <input type="text" id="inp_preco" name="preco" value="<?= htmlspecialchars(!empty($editRow['preco']) ? number_format((float)$editRow['preco'], 2, ',', '.') : '') ?>" placeholder="Ex: 2.500.000,00" />
          <input type="hidden" id="inp_preco_raw" name="preco_raw" value="<?= htmlspecialchars($editRow['preco'] ?? '') ?>" />
        </div>
        <div class="field">
          <label>Água / Irrigação</label>
          <input type="text" name="agua" value="<?= htmlspecialchars($editRow['agua'] ?? '') ?>" placeholder="Ex: Rio perene, pivô" />
        </div>
        <div class="field">
          <label>Solo</label>
          <input type="text" name="solo" value="<?= htmlspecialchars($editRow['solo'] ?? '') ?>" placeholder="Ex: Latossolo vermelho" />
        </div>
        <div class="field">
          <label>Benfeitorias</label>
          <input type="text" name="benfeitorias" value="<?= htmlspecialchars($editRow['benfeitorias'] ?? '') ?>" placeholder="Ex: Casa sede, curral, silos" />
        </div>
        <div class="field">
          <label>Acesso</label>
          <input type="text" name="acesso" value="<?= htmlspecialchars($editRow['acesso'] ?? '') ?>" placeholder="Ex: Asfalto + 5 km terra" />
        </div>

        <div class="field field-full">
          <label>Descrição</label>
          <textarea name="descricao" rows="5" placeholder="Descreva a propriedade com detalhes relevantes para o comprador..."><?= htmlspecialchars($editRow['descricao'] ?? '') ?></textarea>
        </div>

        <!-- FOTOS EXISTENTES -->
        <?php if (!empty($editRow['fotos'])): ?>
        <div class="field field-full">
          <label>Fotos atuais <small style="color:var(--muted)">(clique para marcar remoção)</small></label>
          <div class="fotos-preview">
            <?php foreach ($editRow['fotos'] as $foto): ?>
              <div class="foto-thumb" id="thumb_<?= md5($foto) ?>">
                <div class="foto-thumb-img">
                  <?php
                  $thumbFile = file_exists(UPLOAD_DIR . 'thumb_' . $foto)
                    ? 'uploads/thumb_' . $foto
                    : 'uploads/' . $foto;
                  ?>
                  <img src="<?= htmlspecialchars($thumbFile) ?>" alt="" loading="lazy" decoding="async" />
                </div>
                <button type="button" class="foto-del-btn" onclick="toggleDelFoto('<?= md5($foto) ?>', '<?= htmlspecialchars($foto) ?>')">
                  <i class="fa fa-trash"></i> Remover
                </button>
                <input type="checkbox" name="del_fotos[]" id="cb_<?= md5($foto) ?>" value="<?= htmlspecialchars($foto) ?>" />
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- UPLOAD FOTOS -->
        <div class="field field-full">
          <label>
            <?= !empty($editRow['fotos']) ? 'Adicionar mais fotos' : 'Fotos' ?>
            <small style="color:var(--muted)"> — JPG/PNG/WebP, múltiplas</small>
          </label>
          <input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple />
        </div>

        <!-- UPLOAD VÍDEO -->
        <div class="field field-full">
          <label>Vídeo (arquivo) <small style="color:var(--muted)"> — MP4/WebM</small></label>
          <?php if (!empty($editRow['video_file'])): ?>
            <p style="font-size:.82rem;color:var(--muted);margin-bottom:6px">
              <i class="fa fa-video"></i> Arquivo atual: <?= htmlspecialchars($editRow['video_file']) ?>
            </p>
          <?php endif; ?>
          <input type="file" name="video_file[]" accept="video/mp4,video/webm,video/ogg" />
        </div>

        <div class="field field-full">
          <label>Link do vídeo <small style="color:var(--muted)"> — YouTube, Google Drive ou Vimeo (prioridade sobre upload)</small></label>
          <input type="url" name="video_link" value="<?= htmlspecialchars($editRow['video_link'] ?? '') ?>" placeholder="YouTube, Google Drive ou Vimeo..." />
          <div style="margin-top:8px;padding:12px 14px;background:var(--bg2);border-radius:var(--radius-sm);font-size:.8rem;color:var(--muted);line-height:1.7;">
            <strong style="color:var(--text)">Como obter o link do Google Drive:</strong><br>
            1. Clique com botão direito no vídeo no Drive → <em>Compartilhar</em><br>
            2. Em "Acesso geral" selecione <em>"Qualquer pessoa com o link"</em><br>
            3. Clique em <em>Copiar link</em> e cole aqui<br>
            <small>Formatos aceitos: <code>drive.google.com/file/d/ID/view</code> ou <code>drive.google.com/open?id=ID</code></small>
          </div>
        </div>

        <div class="field field-full">
          <label>Visibilidade</label>
          <div class="toggle-wrap">
            <input type="checkbox" name="publicado" id="chkPub" <?= !empty($editRow['publicado']) ? 'checked' : '' ?> />
            <span>Publicar no site público</span>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn-primary" id="btnSalvar" type="button" style="width:auto;padding:12px 32px" onclick="submitForm()">
          <i class="fa fa-save"></i> <?= $editRow ? 'Salvar alterações' : 'Criar propriedade' ?>
        </button>
        <?php if ($editRow): ?>
          <a href="admin.php" class="btn-cancel"><i class="fa fa-times"></i> Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- OVERLAY PROGRESSO -->
  <div class="upload-overlay" id="uploadOverlay">
    <div class="upload-box">
      <h3><i class="fa fa-cloud-upload-alt"></i> Enviando dados…</h3>
      <p id="progressLabel">Preparando o envio</p>
      <div class="progress-track">
        <div class="progress-fill" id="progressFill"></div>
      </div>
      <div class="progress-pct" id="progressPct">0%</div>
      <div class="progress-label" id="progressSub">Aguarde, não feche esta janela</div>
    </div>
  </div>

  <!-- TABELA -->
  <div class="admin-top">
    <h1>Propriedades cadastradas <small style="font-size:.8rem;font-weight:400;color:var(--muted)">(<?= count($rows) ?>)</small></h1>
    <a href="#form-section" class="btn-sm"><i class="fa fa-arrow-up"></i> Nova propriedade</a>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Nome</th>
          <th>Cidade/Estado</th>
          <th>Tipo</th>
          <th>Hectares</th>
          <th>Preço</th>
          <th>Fotos</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr class="empty-row"><td colspan="8">Nenhuma propriedade cadastrada. Use o formulário acima para criar.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?= htmlspecialchars($r['nome'] ?? '-') ?></strong></td>
            <td><?= htmlspecialchars(($r['cidade'] ?? '-') . '/' . ($r['estado'] ?? '')) ?></td>
            <td><?= htmlspecialchars($r['tipo'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['hectares'] ?? '-') ?></td>
            <td>
              <?php
              $p = $r['preco'] ?? '';
              echo $p ? 'R$ ' . number_format((float)$p, 0, ',', '.') : 'Consultar';
              ?>
            </td>
            <td><?= count($r['fotos'] ?? []) ?></td>
            <td>
              <?php if (!empty($r['publicado'])): ?>
                <span class="badge-pub pub"><i class="fa fa-circle"></i> Publicado</span>
              <?php else: ?>
                <span class="badge-pub oculto"><i class="fa fa-eye-slash"></i> Oculto</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="td-actions">
                <a href="admin.php?edit=<?= urlencode($r['id']) ?>#form-section" class="btn-icon" title="Editar"><i class="fa fa-edit"></i></a>
                <a href="admin.php?toggle=<?= urlencode($r['id']) ?>" class="btn-icon toggle" title="Alternar visibilidade"><i class="fa fa-eye"></i></a>
                <a href="admin.php?del=<?= urlencode($r['id']) ?>" class="btn-icon del" title="Excluir"
                   onclick="return confirm('Excluir esta propriedade? Esta ação não pode ser desfeita.')"><i class="fa fa-trash"></i></a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<?php endif; ?>

<script>
  /* tema */
  const saved = localStorage.getItem('emp-theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);

  /* ── UPLOAD COM PROGRESSO ── */
  function submitForm() {
    const form    = document.querySelector('form[enctype]');
    const overlay = document.getElementById('uploadOverlay');
    const fill    = document.getElementById('progressFill');
    const pct     = document.getElementById('progressPct');
    const lbl     = document.getElementById('progressLabel');
    const sub     = document.getElementById('progressSub');

    if (!form.checkValidity()) { form.reportValidity(); return; }

    const fd = new FormData(form);
    const xhr = new XMLHttpRequest();

    overlay.classList.add('show');

    xhr.upload.addEventListener('progress', e => {
      if (!e.lengthComputable) return;
      const p = Math.round((e.loaded / e.total) * 100);
      fill.style.width = p + '%';
      pct.textContent  = p + '%';
      lbl.textContent  = p < 100 ? 'Enviando arquivos…' : 'Processando no servidor…';
      sub.textContent  = formatBytes(e.loaded) + ' de ' + formatBytes(e.total);
    });

    xhr.addEventListener('load', () => {
      fill.style.width = '100%';
      pct.textContent  = '100%';
      lbl.textContent  = 'Concluído!';
      sub.textContent  = 'Redirecionando…';
      setTimeout(() => { window.location.href = 'admin.php?ok=save'; }, 400);
    });

    xhr.addEventListener('error', () => {
      overlay.classList.remove('show');
      alert('Erro no envio. Verifique sua conexão e tente novamente.');
    });

    xhr.open('POST', 'admin.php');
    xhr.send(fd);
  }

  function formatBytes(b) {
    if (b < 1024)       return b + ' B';
    if (b < 1048576)    return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
  }

  /* ── DELETE FOTO ── */
  function toggleDelFoto(hash, filename) {
    const thumb = document.getElementById('thumb_' + hash);
    const cb    = document.getElementById('cb_'    + hash);
    const btn   = thumb.querySelector('.foto-del-btn');
    cb.checked  = !cb.checked;
    thumb.classList.toggle('marcada', cb.checked);
    btn.innerHTML = cb.checked
      ? '<i class="fa fa-undo"></i> Cancelar'
      : '<i class="fa fa-trash"></i> Remover';
  }

  /* ── PREÇO COM FORMATAÇÃO ── */
  const inpPreco = document.getElementById('inp_preco');
  if (inpPreco) {
    inpPreco.addEventListener('input', () => {
      let raw = inpPreco.value.replace(/\D/g, '');
      if (!raw) { inpPreco.value = ''; return; }
      const num = (parseInt(raw) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      inpPreco.value = num;
    });
    inpPreco.addEventListener('blur', () => {
      // garante formato correto ao sair do campo
      let raw = inpPreco.value.replace(/\./g, '').replace(',', '.');
      const n = parseFloat(raw);
      if (!isNaN(n)) inpPreco.value = n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    });
  }

  /* ── CONVERSÃO HECTARES ↔ ALQUEIRES GOIANOS ── */
  const ALQ = 4.84;
  const inpHa  = document.getElementById('inp_hectares');
  const inpAlq = document.getElementById('inp_alqueires');

  if (inpHa && inpAlq) {
    inpHa.addEventListener('input', () => {
      const v = parseFloat(inpHa.value);
      inpAlq.value = isNaN(v) ? '' : (v / ALQ).toFixed(2);
    });
    inpAlq.addEventListener('input', () => {
      const v = parseFloat(inpAlq.value);
      inpHa.value = isNaN(v) ? '' : (v * ALQ).toFixed(2);
    });
  }
</script>
</body>
</html>
