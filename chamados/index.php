<?php
session_start();

/* ── CONFIG ── */
define('CHAMADOS_FILE', __DIR__ . '/data/chamados.json');
define('EMPRESAS_FILE', __DIR__ . '/data/empresas.json');
define('UPLOAD_DIR',    __DIR__ . '/uploads/');
define('SITE_URL',      'https://fontecinfo.com/chamados');
define('MAX_ATTACH',    5 * 1024 * 1024);

$ALLOWED_MIME = [
    'image/jpeg','image/png','image/webp','image/gif','application/pdf','text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];
$CATS  = ['Redes/Infraestrutura','CCTV/Segurança','Servidores','Website/TI','Suporte Geral','Outros'];
$PRIOS = ['Baixa','Média','Alta','Urgente'];
$ST = [
    'aberto'       => ['label'=>'Aberto',       'c'=>'#2563eb','bg'=>'#eff6ff'],
    'em_andamento' => ['label'=>'Em Andamento', 'c'=>'#d97706','bg'=>'#fffbeb'],
    'aguardando'   => ['label'=>'Aguardando',   'c'=>'#7c3aed','bg'=>'#f5f3ff'],
    'resolvido'    => ['label'=>'Resolvido',    'c'=>'#059669','bg'=>'#ecfdf5'],
    'fechado'      => ['label'=>'Fechado',      'c'=>'#374151','bg'=>'#f3f4f6'],
];
$PR = [
    'Baixa'   => ['c'=>'#059669','bg'=>'#ecfdf5'],
    'Média'   => ['c'=>'#2563eb','bg'=>'#eff6ff'],
    'Alta'    => ['c'=>'#d97706','bg'=>'#fffbeb'],
    'Urgente' => ['c'=>'#dc2626','bg'=>'#fef2f2'],
];

/* ── HELPERS ── */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function s(string $v): string { return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8'); }
function lj(string $f): array { return file_exists($f) ? (json_decode(file_get_contents($f), true) ?: []) : []; }
function wj(string $f, array $d): void {
    $dir = dirname($f);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($f, json_encode(array_values($d), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
}
function nextId(array $list, string $pfx, int $pad = 4): string {
    $max = 0;
    foreach ($list as $x) {
        if (preg_match('/'.preg_quote($pfx).'-(\d+)/', $x['id'] ?? '', $m)) $max = max($max, (int)$m[1]);
    }
    return $pfx . '-' . str_pad($max + 1, $pad, '0', STR_PAD_LEFT);
}
function stBadge(string $st): string {
    global $ST;
    $x = $ST[$st] ?? ['label' => h($st), 'c' => '#374151', 'bg' => '#f3f4f6'];
    return "<span class='badge' style='background:{$x['bg']};color:{$x['c']}'>" . h($x['label']) . "</span>";
}
function prBadge(string $pr): string {
    global $PR;
    $x = $PR[$pr] ?? ['c' => '#374151', 'bg' => '#f3f4f6'];
    return "<span class='badge' style='background:{$x['bg']};color:{$x['c']}'>" . h($pr) . "</span>";
}
function fdate(string $d): string { return $d ? date('d/m/Y H:i', strtotime($d)) : '-'; }

/* ── CSRF ── */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];
function checkCsrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403); die('Token inválido.');
    }
}

/* ── AUTH ── */
$loginErr = '';
if (isset($_POST['logout'])) { session_destroy(); header('Location: index.php'); exit; }
if (isset($_POST['do_login'])) {
    $empresas = lj(EMPRESAS_FILE);
    $em = trim($_POST['email'] ?? '');
    $pw = $_POST['senha'] ?? '';
    $found = false;
    foreach ($empresas as $e) {
        if ($e['email'] === $em && !empty($e['ativo']) && password_verify($pw, $e['senha_hash'] ?? '')) {
            $_SESSION['emp'] = ['id' => $e['id'], 'nome' => $e['nome'], 'email' => $e['email'], 'contato' => $e['contato'] ?? ''];
            $found = true;
            break;
        }
    }
    if (!$found) $loginErr = 'E-mail ou senha incorretos. Entre em contato com a FONTEC se precisar de acesso.';
}
$auth = !empty($_SESSION['emp']);
$emp  = $_SESSION['emp'] ?? [];

/* ── AÇÕES ── */
$flash = '';
if ($auth) {
    /* abrir novo chamado */
    if (($_POST['action'] ?? '') === 'novo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        global $CATS, $PRIOS, $ALLOWED_MIME;
        $titulo = s($_POST['titulo'] ?? '');
        $cat    = in_array($_POST['cat'] ?? '', $CATS)  ? $_POST['cat']  : 'Outros';
        $prio   = in_array($_POST['prio'] ?? '', $PRIOS) ? $_POST['prio'] : 'Média';
        $desc   = trim(strip_tags($_POST['desc'] ?? ''));
        if (mb_strlen($titulo) < 5) {
            $flash = 'Título muito curto (mínimo 5 caracteres).';
        } elseif (mb_strlen($desc) < 15) {
            $flash = 'Descrição muito curta (mínimo 15 caracteres).';
        } else {
            $anexos = [];
            if (!empty($_FILES['anexo']['name']) && $_FILES['anexo']['error'] === 0) {
                $f = $_FILES['anexo'];
                if ($f['size'] > MAX_ATTACH) {
                    $flash = 'Arquivo grande demais (máximo 5 MB).';
                } elseif (!in_array($f['type'], $ALLOWED_MIME)) {
                    $flash = 'Tipo de arquivo não permitido.';
                } else {
                    $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                    $safe = 'a_' . uniqid() . '.' . $ext;
                    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
                    if (move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $safe))
                        $anexos[] = ['nome' => basename($f['name']), 'arq' => $safe];
                }
            }
            if (!$flash) {
                $all = lj(CHAMADOS_FILE);
                $id  = nextId($all, 'CHM');
                $now = date('Y-m-d H:i:s');
                $all[] = [
                    'id' => $id, 'empresa_id' => $emp['id'], 'empresa_nome' => $emp['nome'],
                    'titulo' => $titulo, 'categoria' => $cat, 'prioridade' => $prio,
                    'status' => 'aberto', 'descricao' => $desc,
                    'anexos' => $anexos, 'created_at' => $now, 'updated_at' => $now, 'mensagens' => [],
                ];
                wj(CHAMADOS_FILE, $all);
                header("Location: index.php?page=chamado&id={$id}&ok=1");
                exit;
            }
        }
    }
    /* responder a chamado */
    if (($_POST['action'] ?? '') === 'reply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        $cid = s($_POST['cid'] ?? '');
        $msg = trim(strip_tags($_POST['msg'] ?? ''));
        if (mb_strlen($msg) < 3) {
            $flash = 'Mensagem muito curta.';
        } else {
            $all = lj(CHAMADOS_FILE);
            foreach ($all as &$c) {
                if ($c['id'] === $cid && $c['empresa_id'] === $emp['id'] && !in_array($c['status'], ['resolvido', 'fechado'])) {
                    $c['mensagens'][] = ['tipo' => 'empresa', 'autor' => $emp['contato'] ?: $emp['nome'], 'msg' => $msg, 'at' => date('Y-m-d H:i:s')];
                    if ($c['status'] === 'aguardando') $c['status'] = 'em_andamento';
                    $c['updated_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            wj(CHAMADOS_FILE, $all);
            header("Location: index.php?page=chamado&id={$cid}");
            exit;
        }
    }
}

/* ── DADOS PARA VIEWS ── */
$page  = $auth ? s($_GET['page'] ?? 'dashboard') : 'login';
$okMsg = !empty($_GET['ok']);
$all   = $auth ? lj(CHAMADOS_FILE) : [];
$mine  = array_values(array_filter($all, fn($c) => ($c['empresa_id'] ?? '') === $emp['id']));
usort($mine, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
$stats = ['aberto' => 0, 'em_andamento' => 0, 'aguardando' => 0, 'resolvido' => 0, 'total' => count($mine)];
foreach ($mine as $c) { if (isset($stats[$c['status']])) $stats[$c['status']]++; }

$cur = null;
$cid = s($_GET['id'] ?? '');
if ($page === 'chamado' && $cid) {
    foreach ($mine as $c) { if ($c['id'] === $cid) { $cur = $c; break; } }
    if (!$cur) { header('Location: index.php'); exit; }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Portal de Chamados — FONTEC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f5f8f6;--bg2:#edf2ef;--surface:#fff;
  --text:#0b1f14;--muted:#4a6657;
  --accent:#1a6b42;--accent2:#22c55e;
  --border:rgba(26,107,66,.12);--shadow:0 2px 20px rgba(26,107,66,.08);
  --radius:14px;--radius-sm:8px;--trans:.35s cubic-bezier(.4,0,.2,1);
  --sidebar:#ffffff;--sidebar-w:260px;
}
body{font-family:'DM Sans',Arial,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
a{color:var(--accent);text-decoration:none}
a:hover{text-decoration:underline}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap}

/* ── LOGIN ── */
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0b1f14 0%,#1a6b42 100%);padding:20px}
.login-card{background:#fff;border-radius:var(--radius);padding:40px 36px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,.3);border:1px solid rgba(26,107,66,.1)}
.login-logo{text-align:center;margin-bottom:28px}
.login-logo img{height:90px;width:auto;object-fit:contain;display:block;margin:0 auto 10px}
.login-logo .sub{font-size:13px;color:var(--muted);margin-top:4px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px;font-family:inherit;background:#fff;color:var(--text);transition:border-color .2s}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--accent)}
.btn-primary{width:100%;padding:12px;background:var(--accent);color:#fff;border:none;border-radius:var(--radius-sm);font-size:15px;font-weight:600;cursor:pointer;font-family:inherit;transition:background .2s}
.btn-primary:hover{background:#155736}
.alert-err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:12px 14px;border-radius:var(--radius-sm);font-size:13px;margin-bottom:18px}
.alert-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#059669;padding:12px 14px;border-radius:var(--radius-sm);font-size:13px;margin-bottom:18px}

/* ── LAYOUT ── */
.layout{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar-w);background:var(--sidebar);border-right:1px solid var(--border);box-shadow:2px 0 20px rgba(26,107,66,.06);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:100;transition:transform .3s}
.sidebar-logo{padding:20px 20px 16px;border-bottom:1px solid var(--border)}
.sidebar-logo img{height:234px;width:auto;object-fit:contain;mix-blend-mode:multiply;display:block;margin-bottom:4px;pointer-events:none}
.sidebar-logo .brand{font-family:'Syne',sans-serif;font-size:13px;font-weight:600;color:var(--muted);letter-spacing:.04em;text-transform:uppercase}
.sidebar-user{padding:14px 20px;border-bottom:1px solid var(--border)}
.sidebar-user .name{font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sidebar-user .email{font-size:11px;color:var(--muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sidebar-nav{flex:1;padding:12px 0}
.nav-item{display:flex;align-items:center;gap:10px;padding:11px 20px;color:var(--muted);font-size:14px;font-weight:500;transition:background .15s,color .15s;cursor:pointer;text-decoration:none;border-left:3px solid transparent}
.nav-item:hover{background:rgba(26,107,66,.06);color:var(--accent);text-decoration:none;border-left-color:rgba(26,107,66,.3)}
.nav-item.active{background:rgba(26,107,66,.08);color:var(--accent);text-decoration:none;border-left-color:var(--accent);font-weight:600}
.nav-item i{width:18px;text-align:center;font-size:14px;color:var(--accent);opacity:.5}
.nav-item.active i,.nav-item:hover i{opacity:1}
.sidebar-bottom{padding:16px 20px;border-top:1px solid var(--border)}
.btn-logout{width:100%;padding:9px;background:rgba(26,107,66,.06);color:var(--muted);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:13px;cursor:pointer;font-family:inherit;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-logout:hover{background:rgba(26,107,66,.12);color:var(--accent);border-color:rgba(26,107,66,.3)}
.main{margin-left:var(--sidebar-w);flex:1;padding:32px;max-width:calc(100% - var(--sidebar-w))}
.page-header{margin-bottom:26px}
.page-header h1{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:var(--text)}
.page-header p{font-size:14px;color:var(--muted);margin-top:4px}
.topbar{display:none}

/* ── CARDS ── */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px}
.stat-card{background:var(--surface);border-radius:var(--radius);padding:20px;border:1px solid var(--border);box-shadow:var(--shadow)}
.stat-card .num{font-family:'Syne',sans-serif;font-size:32px;font-weight:800;color:var(--text)}
.stat-card .lbl{font-size:12px;color:var(--muted);margin-top:4px;font-weight:500}
.stat-card .icon{font-size:22px;margin-bottom:8px}
.card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);overflow:hidden;margin-bottom:24px;box-shadow:var(--shadow)}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-header h2{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;color:var(--text)}
.card-body{padding:20px}

/* ── TABLE ── */
.tbl-wrap{overflow-x:auto}
table.data{width:100%;border-collapse:collapse;font-size:13px}
table.data th{padding:10px 14px;background:var(--bg2);text-align:left;font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
table.data td{padding:12px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
table.data tr:last-child td{border-bottom:none}
table.data tr:hover td{background:var(--bg)}
.tbl-link{font-weight:600;color:var(--accent)}
.tbl-link:hover{color:#155736}
.empty{text-align:center;padding:40px;color:var(--muted);font-size:14px}
.empty i{font-size:36px;margin-bottom:12px;display:block;opacity:.3;color:var(--accent)}

/* ── FORM ── */
.form-wrap{max-width:700px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group textarea{resize:vertical;min-height:120px;line-height:1.6}
.form-group .hint{font-size:11px;color:var(--muted);margin-top:4px}
.btn-send{padding:11px 24px;background:var(--accent);color:#fff;border:none;border-radius:var(--radius-sm);font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;transition:background .2s;display:inline-flex;align-items:center;gap:8px}
.btn-send:hover{background:#155736}
.btn-sec{padding:11px 20px;background:var(--surface);color:var(--muted);border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px;font-weight:500;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:border-color .2s,color .2s}
.btn-sec:hover{border-color:var(--accent);color:var(--accent);text-decoration:none}
.form-actions{display:flex;gap:12px;align-items:center;margin-top:24px;flex-wrap:wrap}

/* ── CHAMADO VIEW ── */
.chamado-header{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:22px;margin-bottom:20px;box-shadow:var(--shadow)}
.chamado-id{font-size:12px;color:var(--muted);font-weight:600;margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em}
.chamado-titulo{font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:var(--text);margin-bottom:14px}
.chamado-meta{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.meta-item{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted)}
.meta-item i{font-size:12px;color:var(--accent)}
.desc-box{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;font-size:14px;line-height:1.7;white-space:pre-wrap;margin-bottom:16px;color:var(--text)}
.msg-thread{display:flex;flex-direction:column;gap:14px;margin-bottom:20px}
.msg{padding:14px 16px;border-radius:10px;font-size:14px;line-height:1.7;max-width:90%}
.msg.empresa{background:rgba(26,107,66,.06);border:1px solid rgba(26,107,66,.18);align-self:flex-end;border-bottom-right-radius:3px}
.msg.admin{background:var(--bg2);border:1px solid var(--border);align-self:flex-start;border-bottom-left-radius:3px}
.msg .msg-meta{font-size:11px;color:var(--muted);margin-bottom:6px;font-weight:600}
.msg .msg-text{white-space:pre-wrap}
.reply-form{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
.reply-form h3{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;color:var(--text);margin-bottom:14px}
.reply-form textarea{width:100%;min-height:100px;padding:10px 14px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px;font-family:inherit;resize:vertical;transition:border-color .2s}
.reply-form textarea:focus{outline:none;border-color:var(--accent)}
.anexo-list{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.anexo-chip{display:inline-flex;align-items:center;gap:6px;background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:5px 10px;font-size:12px;color:var(--text);text-decoration:none}
.anexo-chip:hover{background:rgba(26,107,66,.08);text-decoration:none;border-color:rgba(26,107,66,.3)}
.status-resolved{background:#ecfdf5;border:1px solid #a7f3d0;border-radius:var(--radius-sm);padding:14px 16px;font-size:14px;color:#059669;margin-bottom:20px;display:flex;align-items:center;gap:8px}

/* ── MOBILE ── */
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .main{margin-left:0;padding:16px;max-width:100%}
  .topbar{display:flex;align-items:center;background:var(--accent);padding:14px 16px;gap:14px;position:sticky;top:0;z-index:50}
  .topbar .brand{font-family:'Syne',sans-serif;font-size:16px;font-weight:800;color:#fff;flex:1}
  .topbar button{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;padding:2px}
  .form-row{grid-template-columns:1fr}
  .stat-grid{grid-template-columns:1fr 1fr}
  .chamado-meta{gap:8px}
  .msg{max-width:100%}
  .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:90}
  .overlay.open{display:block}
}
</style>
</head>
<body>

<?php if (!$auth): ?>
<!-- ── LOGIN ── -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <img src="../assets/img/logo.png?v=2" alt="FONTEC Informática & Tecnologia" />
      <div class="sub">Portal de Chamados — Clientes</div>
    </div>
    <?php if ($loginErr): ?>
    <div class="alert-err"><i class="fa fa-circle-exclamation"></i> <?= h($loginErr) ?></div>
    <?php endif ?>
    <form method="post" autocomplete="on">
      <div class="form-group">
        <label>E-mail corporativo</label>
        <input type="email" name="email" placeholder="empresa@exemplo.com" required autofocus
               value="<?= h($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Senha</label>
        <input type="password" name="senha" placeholder="••••••••" required>
      </div>
      <button type="submit" name="do_login" class="btn-primary">
        <i class="fa fa-right-to-bracket"></i> Entrar
      </button>
    </form>
    <p style="text-align:center;margin-top:20px;font-size:12px;color:#94a3b8">
      Problemas de acesso? Fale com a FONTEC:<br>
      <a href="mailto:caio@fontecinfo.com">caio@fontecinfo.com</a>
    </p>
  </div>
</div>

<?php else: ?>
<!-- ── LAYOUT AUTENTICADO ── -->
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <img src="../assets/img/logo.png?v=2" alt="FONTEC" />
    <div class="brand">Portal de Chamados</div>
  </div>
  <div class="sidebar-user">
    <div class="name"><i class="fa fa-building" style="opacity:.6;margin-right:4px"></i><?= h($emp['nome']) ?></div>
    <div class="email"><?= h($emp['email']) ?></div>
  </div>
  <nav class="sidebar-nav">
    <a href="index.php?page=dashboard" class="nav-item <?= $page==='dashboard'?'active':'' ?>">
      <i class="fa fa-gauge"></i> Dashboard
    </a>
    <a href="index.php?page=novo" class="nav-item <?= $page==='novo'?'active':'' ?>">
      <i class="fa fa-plus-circle"></i> Abrir Chamado
    </a>
    <a href="index.php?page=chamados" class="nav-item <?= $page==='chamados'?'active':'' ?>">
      <i class="fa fa-ticket"></i> Meus Chamados
    </a>
  </nav>
  <div class="sidebar-bottom">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <button type="submit" name="logout" class="btn-logout">
        <i class="fa fa-right-from-bracket"></i> Sair
      </button>
    </form>
  </div>
</div>

<div class="topbar">
  <button onclick="toggleSidebar()"><i class="fa fa-bars"></i></button>
  <span class="brand">FONTEC Chamados</span>
</div>

<main class="main">

<?php if ($flash): ?>
<div class="alert-err" style="margin-bottom:20px"><i class="fa fa-circle-exclamation"></i> <?= h($flash) ?></div>
<?php endif ?>
<?php if ($okMsg): ?>
<div class="alert-ok" style="margin-bottom:20px"><i class="fa fa-circle-check"></i> Chamado aberto com sucesso! Nossa equipe entrará em contato em breve.</div>
<?php endif ?>

<?php /* ── DASHBOARD ── */
if ($page === 'dashboard'): ?>
  <div class="page-header">
    <h1>Dashboard</h1>
    <p>Bem-vindo, <?= h($emp['contato'] ?: $emp['nome']) ?>. Acompanhe seus chamados abaixo.</p>
  </div>
  <div class="stat-grid">
    <div class="stat-card">
      <div class="icon" style="color:#2563eb"><i class="fa fa-folder-open"></i></div>
      <div class="num"><?= $stats['aberto'] ?></div>
      <div class="lbl">Abertos</div>
    </div>
    <div class="stat-card">
      <div class="icon" style="color:#d97706"><i class="fa fa-gear"></i></div>
      <div class="num"><?= $stats['em_andamento'] ?></div>
      <div class="lbl">Em Andamento</div>
    </div>
    <div class="stat-card">
      <div class="icon" style="color:#7c3aed"><i class="fa fa-clock"></i></div>
      <div class="num"><?= $stats['aguardando'] ?></div>
      <div class="lbl">Aguardando</div>
    </div>
    <div class="stat-card">
      <div class="icon" style="color:#059669"><i class="fa fa-circle-check"></i></div>
      <div class="num"><?= $stats['resolvido'] ?></div>
      <div class="lbl">Resolvidos</div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2><i class="fa fa-clock-rotate-left" style="margin-right:6px;opacity:.7"></i> Chamados Recentes</h2>
      <a href="index.php?page=novo" class="btn-send" style="font-size:13px;padding:8px 14px">
        <i class="fa fa-plus"></i> Novo Chamado
      </a>
    </div>
    <?php $recent = array_slice($mine, 0, 8); ?>
    <?php if ($recent): ?>
    <div class="tbl-wrap">
      <table class="data">
        <thead><tr>
          <th>ID</th><th>Título</th><th>Categoria</th><th>Prioridade</th><th>Status</th><th>Atualizado</th>
        </tr></thead>
        <tbody>
        <?php foreach ($recent as $c): ?>
        <tr>
          <td><a href="index.php?page=chamado&id=<?= h($c['id']) ?>" class="tbl-link"><?= h($c['id']) ?></a></td>
          <td><?= h(mb_strimwidth($c['titulo'], 0, 50, '…')) ?></td>
          <td><span style="font-size:12px;color:var(--muted)"><?= h($c['categoria']) ?></span></td>
          <td><?= prBadge($c['prioridade']) ?></td>
          <td><?= stBadge($c['status']) ?></td>
          <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?= fdate($c['updated_at']) ?></td>
        </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty">
      <i class="fa fa-inbox"></i>
      Nenhum chamado ainda.<br>
      <a href="index.php?page=novo" style="margin-top:10px;display:inline-block">Abrir primeiro chamado</a>
    </div>
    <?php endif ?>
  </div>

<?php /* ── NOVO CHAMADO ── */
elseif ($page === 'novo'): ?>
  <div class="page-header">
    <h1>Abrir Chamado</h1>
    <p>Descreva o problema com o máximo de detalhes possível.</p>
  </div>
  <div class="form-wrap">
    <div class="card">
      <div class="card-header"><h2><i class="fa fa-ticket" style="margin-right:6px;opacity:.7"></i> Novo Chamado</h2></div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf"   value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="novo">
          <div class="form-group">
            <label>Título <span style="color:#dc2626">*</span></label>
            <input type="text" name="titulo" required maxlength="120" placeholder="Ex: Sem acesso à internet no setor administrativo"
                   value="<?= h($_POST['titulo'] ?? '') ?>">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Categoria <span style="color:#dc2626">*</span></label>
              <select name="cat">
                <?php foreach ($CATS as $c): ?>
                <option value="<?= h($c) ?>" <?= (($_POST['cat']??'')===$c)?'selected':'' ?>><?= h($c) ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="form-group">
              <label>Prioridade <span style="color:#dc2626">*</span></label>
              <select name="prio">
                <?php foreach ($PRIOS as $p): ?>
                <option value="<?= h($p) ?>" <?= (($_POST['prio']??'')===$p)?'selected':'' ?>><?= h($p) ?></option>
                <?php endforeach ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Descrição detalhada <span style="color:#dc2626">*</span></label>
            <textarea name="desc" required placeholder="Descreva o problema, quando começou, equipamentos afetados e qualquer informação relevante..."><?= h($_POST['desc'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label>Anexo (opcional)</label>
            <input type="file" name="anexo" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.doc,.docx,.xls,.xlsx">
            <div class="hint">Formatos aceitos: imagens, PDF, Word, Excel. Máximo 5 MB.</div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-send"><i class="fa fa-paper-plane"></i> Enviar Chamado</button>
            <a href="index.php" class="btn-sec"><i class="fa fa-arrow-left"></i> Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>

<?php /* ── LISTA DE CHAMADOS ── */
elseif ($page === 'chamados'): ?>
  <div class="page-header">
    <h1>Meus Chamados</h1>
    <p>Total: <?= $stats['total'] ?> chamados</p>
  </div>
  <div class="card">
    <div class="card-header">
      <h2><i class="fa fa-list" style="margin-right:6px;opacity:.7"></i> Todos os Chamados</h2>
      <a href="index.php?page=novo" class="btn-send" style="font-size:13px;padding:8px 14px">
        <i class="fa fa-plus"></i> Novo
      </a>
    </div>
    <?php if ($mine): ?>
    <div class="tbl-wrap">
      <table class="data">
        <thead><tr>
          <th>ID</th><th>Título</th><th>Categoria</th><th>Prioridade</th><th>Status</th><th>Aberto em</th><th>Atualizado</th>
        </tr></thead>
        <tbody>
        <?php foreach ($mine as $c): ?>
        <tr>
          <td><a href="index.php?page=chamado&id=<?= h($c['id']) ?>" class="tbl-link"><?= h($c['id']) ?></a></td>
          <td><?= h(mb_strimwidth($c['titulo'], 0, 55, '…')) ?></td>
          <td><span style="font-size:12px;color:var(--muted)"><?= h($c['categoria']) ?></span></td>
          <td><?= prBadge($c['prioridade']) ?></td>
          <td><?= stBadge($c['status']) ?></td>
          <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?= fdate($c['created_at']) ?></td>
          <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?= fdate($c['updated_at']) ?></td>
        </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty">
      <i class="fa fa-inbox"></i>
      Nenhum chamado encontrado.<br>
      <a href="index.php?page=novo" style="margin-top:10px;display:inline-block">Abrir primeiro chamado</a>
    </div>
    <?php endif ?>
  </div>

<?php /* ── VER CHAMADO ── */
elseif ($page === 'chamado' && $cur): ?>
  <div style="margin-bottom:16px">
    <a href="index.php?page=chamados" class="btn-sec" style="font-size:13px;padding:8px 14px">
      <i class="fa fa-arrow-left"></i> Voltar
    </a>
  </div>
  <div class="chamado-header">
    <div class="chamado-id"><?= h($cur['id']) ?></div>
    <div class="chamado-titulo"><?= h($cur['titulo']) ?></div>
    <div class="chamado-meta">
      <?= stBadge($cur['status']) ?>
      <?= prBadge($cur['prioridade']) ?>
      <span class="meta-item"><i class="fa fa-folder"></i> <?= h($cur['categoria']) ?></span>
      <span class="meta-item"><i class="fa fa-calendar"></i> <?= fdate($cur['created_at']) ?></span>
    </div>
  </div>

  <div class="card" style="margin-bottom:20px">
    <div class="card-header"><h2>Descrição inicial</h2></div>
    <div class="card-body">
      <div class="desc-box"><?= nl2br(h($cur['descricao'])) ?></div>
      <?php if (!empty($cur['anexos'])): ?>
      <div class="anexo-list">
        <?php foreach ($cur['anexos'] as $a): ?>
        <a href="uploads/<?= h($a['arq']) ?>" target="_blank" class="anexo-chip">
          <i class="fa fa-paperclip"></i> <?= h($a['nome']) ?>
        </a>
        <?php endforeach ?>
      </div>
      <?php endif ?>
    </div>
  </div>

  <?php if (!empty($cur['mensagens'])): ?>
  <div class="card" style="margin-bottom:20px">
    <div class="card-header"><h2>Histórico de mensagens</h2></div>
    <div class="card-body">
      <div class="msg-thread">
        <?php foreach ($cur['mensagens'] as $m): ?>
        <div class="msg <?= h($m['tipo']) ?>">
          <div class="msg-meta">
            <?= $m['tipo']==='admin' ? '<i class="fa fa-headset"></i> FONTEC — Suporte' : '<i class="fa fa-building"></i> ' . h($m['autor']) ?>
            &bull; <?= fdate($m['at']) ?>
          </div>
          <div class="msg-text"><?= nl2br(h($m['msg'])) ?></div>
        </div>
        <?php endforeach ?>
      </div>
    </div>
  </div>
  <?php endif ?>

  <?php if (in_array($cur['status'], ['resolvido', 'fechado'])): ?>
  <div class="status-resolved">
    <i class="fa fa-circle-check"></i>
    Este chamado foi <?= $cur['status'] === 'resolvido' ? 'marcado como resolvido' : 'fechado' ?>.
    Se o problema persistir, abra um novo chamado.
  </div>
  <?php else: ?>
  <div class="reply-form">
    <h3><i class="fa fa-reply" style="margin-right:6px;opacity:.7"></i> Responder / Adicionar informações</h3>
    <form method="post">
      <input type="hidden" name="csrf"   value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="reply">
      <input type="hidden" name="cid"    value="<?= h($cur['id']) ?>">
      <textarea name="msg" required placeholder="Digite sua mensagem ou informações adicionais..."></textarea>
      <div class="form-actions">
        <button type="submit" class="btn-send"><i class="fa fa-paper-plane"></i> Enviar Resposta</button>
      </div>
    </form>
  </div>
  <?php endif ?>

<?php endif ?>

</main>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('open');
}
</script>
<?php endif ?>
</body>
</html>
