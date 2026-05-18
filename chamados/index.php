<?php
session_start();

/* ── CONFIG ── */
define('CHAMADOS_FILE', __DIR__ . '/data/chamados.json');
define('EMPRESAS_FILE', __DIR__ . '/data/empresas.json');
define('UPLOAD_DIR',    __DIR__ . '/uploads/');
define('ADMIN_EMAIL',   'caio@fontecinfo.com');
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
function mailSend(string $to, string $subj, string $html): void {
    $hdr  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $hdr .= "From: FONTEC Chamados <caio@fontecinfo.com>\r\nReply-To: caio@fontecinfo.com\r\n";
    $hdr .= "X-Mailer: FONTEC-Chamados/1.0\r\n";
    $ok = mail($to, '=?UTF-8?B?' . base64_encode($subj) . '?=', $html, $hdr, '-f caio@fontecinfo.com');
    if (!$ok) {
        $log = date('Y-m-d H:i:s') . " [PORTAL] Falha ao enviar para {$to} — {$subj}\n";
        file_put_contents(__DIR__ . '/data/mail.log', $log, FILE_APPEND | LOCK_EX);
    }
}
function mailTpl(string $title, string $body): string {
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
    *{box-sizing:border-box}body{font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:20px}
    .w{max-width:560px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1)}
    .hd{background:#1e3a5f;padding:20px 26px}.hd h1{color:#fff;margin:0;font-size:17px;font-weight:700}
    .bd{padding:22px 26px;color:#374151;font-size:14px;line-height:1.7}
    table{width:100%;border-collapse:collapse;margin:12px 0}
    td{padding:8px 12px;border:1px solid #e5e7eb;font-size:13px}
    td:first-child{background:#f9fafb;font-weight:600;width:120px}
    .bx{background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:14px;margin:12px 0;white-space:pre-wrap;font-size:13px}
    .btn{display:inline-block;background:#1e3a5f;color:#fff!important;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:13px;margin-top:12px}
    .ft{background:#f9fafb;padding:12px 26px;text-align:center;font-size:11px;color:#9ca3af;border-top:1px solid #e5e7eb}
    </style></head><body><div class='w'>
    <div class='hd'><h1>FONTEC &mdash; {$title}</h1></div>
    <div class='bd'>{$body}</div>
    <div class='ft'>FONTEC Informática &amp; Tecnologia &bull; Anápolis, GO &bull; fontecinfo.com</div>
    </div></body></html>";
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
                $mb = mailTpl("Novo Chamado [{$id}]",
                    "<p>Novo chamado aberto por <strong>" . h($emp['nome']) . "</strong>.</p>
                    <table>
                      <tr><td>ID</td><td><strong>{$id}</strong></td></tr>
                      <tr><td>Empresa</td><td>" . h($emp['nome']) . "</td></tr>
                      <tr><td>Contato</td><td>" . h($emp['contato']) . "</td></tr>
                      <tr><td>Categoria</td><td>{$cat}</td></tr>
                      <tr><td>Prioridade</td><td>{$prio}</td></tr>
                    </table>
                    <p><strong>Descrição:</strong></p>
                    <div class='bx'>" . nl2br(h($desc)) . "</div>
                    <a class='btn' href='" . SITE_URL . "/admin.php?page=chamado&amp;id={$id}'>Abrir no Painel Admin</a>");
                mailSend(ADMIN_EMAIL, "Novo Chamado [{$id}] — " . $emp['nome'], $mb);
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
                    $mb = mailTpl("Resposta em [{$cid}]",
                        "<p><strong>" . h($emp['nome']) . "</strong> respondeu ao chamado <strong>{$cid}</strong>.</p>
                        <p><strong>Título:</strong> " . h($c['titulo']) . "</p>
                        <div class='bx'>" . nl2br(h($msg)) . "</div>
                        <a class='btn' href='" . SITE_URL . "/admin.php?page=chamado&amp;id={$cid}'>Ver no Painel Admin</a>");
                    mailSend(ADMIN_EMAIL, "Resposta em [{$cid}] — " . $emp['nome'], $mb);
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
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --primary:#1e3a5f;--primary-h:#16304f;--accent:#3b82f6;
  --bg:#f1f5f9;--card:#fff;--border:#e2e8f0;
  --text:#0f172a;--muted:#64748b;--sidebar-w:250px;
}
body{font-family:'DM Sans',Arial,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
a{color:var(--accent);text-decoration:none}
a:hover{text-decoration:underline}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap}

/* ── LOGIN ── */
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1e3a5f 0%,#2d5a8e 100%);padding:20px}
.login-card{background:#fff;border-radius:16px;padding:40px 36px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.login-logo{text-align:center;margin-bottom:28px}
.login-logo img{height:48px}
.login-logo .brand{font-family:'Syne',sans-serif;font-size:22px;font-weight:700;color:var(--primary);margin-top:10px}
.login-logo .sub{font-size:13px;color:var(--muted);margin-top:4px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;background:#fff;color:var(--text);transition:border-color .2s}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--accent)}
.btn-primary{width:100%;padding:12px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit;transition:background .2s}
.btn-primary:hover{background:var(--primary-h)}
.alert-err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:18px}
.alert-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#059669;padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:18px}

/* ── LAYOUT ── */
.layout{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar-w);background:var(--primary);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:100;transition:transform .3s}
.sidebar-logo{padding:24px 20px 16px;border-bottom:1px solid rgba(255,255,255,.1)}
.sidebar-logo .brand{font-family:'Syne',sans-serif;font-size:18px;font-weight:700;color:#fff}
.sidebar-logo .sub{font-size:11px;color:rgba(255,255,255,.5);margin-top:2px}
.sidebar-user{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.1)}
.sidebar-user .name{font-size:13px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sidebar-user .email{font-size:11px;color:rgba(255,255,255,.5);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sidebar-nav{flex:1;padding:12px 0}
.nav-item{display:flex;align-items:center;gap:10px;padding:11px 20px;color:rgba(255,255,255,.75);font-size:14px;font-weight:500;transition:background .15s,color .15s;cursor:pointer;text-decoration:none}
.nav-item:hover,.nav-item.active{background:rgba(255,255,255,.12);color:#fff;text-decoration:none}
.nav-item i{width:18px;text-align:center;font-size:14px}
.sidebar-bottom{padding:16px 20px;border-top:1px solid rgba(255,255,255,.1)}
.btn-logout{width:100%;padding:9px;background:rgba(255,255,255,.1);color:rgba(255,255,255,.8);border:1px solid rgba(255,255,255,.2);border-radius:8px;font-size:13px;cursor:pointer;font-family:inherit;transition:background .2s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-logout:hover{background:rgba(255,255,255,.2);color:#fff}
.main{margin-left:var(--sidebar-w);flex:1;padding:30px;max-width:calc(100% - var(--sidebar-w))}
.page-header{margin-bottom:24px}
.page-header h1{font-family:'Syne',sans-serif;font-size:22px;font-weight:700;color:var(--text)}
.page-header p{font-size:14px;color:var(--muted);margin-top:4px}
.topbar{display:none}

/* ── CARDS ── */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px}
.stat-card{background:var(--card);border-radius:12px;padding:20px;border:1px solid var(--border)}
.stat-card .num{font-family:'Syne',sans-serif;font-size:32px;font-weight:700;color:var(--text)}
.stat-card .lbl{font-size:12px;color:var(--muted);margin-top:4px;font-weight:500}
.stat-card .icon{font-size:22px;margin-bottom:8px}
.card{background:var(--card);border-radius:12px;border:1px solid var(--border);overflow:hidden;margin-bottom:24px}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-header h2{font-size:15px;font-weight:600;color:var(--text)}
.card-body{padding:20px}

/* ── TABLE ── */
.tbl-wrap{overflow-x:auto}
table.data{width:100%;border-collapse:collapse;font-size:13px}
table.data th{padding:10px 14px;background:#f8fafc;text-align:left;font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
table.data td{padding:12px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
table.data tr:last-child td{border-bottom:none}
table.data tr:hover td{background:#f8fafc}
.tbl-link{font-weight:600;color:var(--primary)}
.tbl-link:hover{color:var(--accent)}
.empty{text-align:center;padding:40px;color:var(--muted);font-size:14px}
.empty i{font-size:36px;margin-bottom:12px;display:block;opacity:.4}

/* ── FORM ── */
.form-wrap{max-width:700px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group textarea{resize:vertical;min-height:120px;line-height:1.6}
.form-group .hint{font-size:11px;color:var(--muted);margin-top:4px}
.btn-send{padding:11px 24px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;transition:background .2s;display:inline-flex;align-items:center;gap:8px}
.btn-send:hover{background:var(--primary-h)}
.btn-sec{padding:11px 20px;background:#fff;color:var(--muted);border:1.5px solid var(--border);border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:border-color .2s}
.btn-sec:hover{border-color:var(--accent);color:var(--accent);text-decoration:none}
.form-actions{display:flex;gap:12px;align-items:center;margin-top:24px;flex-wrap:wrap}

/* ── CHAMADO VIEW ── */
.chamado-header{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:22px;margin-bottom:20px}
.chamado-id{font-size:12px;color:var(--muted);font-weight:600;margin-bottom:6px}
.chamado-titulo{font-family:'Syne',sans-serif;font-size:20px;font-weight:700;color:var(--text);margin-bottom:14px}
.chamado-meta{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.meta-item{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted)}
.meta-item i{font-size:12px}
.desc-box{background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:16px;font-size:14px;line-height:1.7;white-space:pre-wrap;margin-bottom:16px;color:var(--text)}
.msg-thread{display:flex;flex-direction:column;gap:14px;margin-bottom:20px}
.msg{padding:14px 16px;border-radius:10px;font-size:14px;line-height:1.7;max-width:90%}
.msg.empresa{background:#eff6ff;border:1px solid #bfdbfe;align-self:flex-end;border-bottom-right-radius:3px}
.msg.admin{background:#f0fdf4;border:1px solid #bbf7d0;align-self:flex-start;border-bottom-left-radius:3px}
.msg .msg-meta{font-size:11px;color:var(--muted);margin-bottom:6px;font-weight:600}
.msg .msg-text{white-space:pre-wrap}
.reply-form{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px}
.reply-form h3{font-size:15px;font-weight:600;margin-bottom:14px}
.reply-form textarea{width:100%;min-height:100px;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;resize:vertical;transition:border-color .2s}
.reply-form textarea:focus{outline:none;border-color:var(--accent)}
.anexo-list{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.anexo-chip{display:inline-flex;align-items:center;gap:6px;background:#f8fafc;border:1px solid var(--border);border-radius:6px;padding:5px 10px;font-size:12px;color:var(--text);text-decoration:none}
.anexo-chip:hover{background:#eff6ff;text-decoration:none}
.status-resolved{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px 16px;font-size:14px;color:#059669;margin-bottom:20px;display:flex;align-items:center;gap:8px}

/* ── MOBILE ── */
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .main{margin-left:0;padding:16px;max-width:100%}
  .topbar{display:flex;align-items:center;background:var(--primary);padding:14px 16px;gap:14px;position:sticky;top:0;z-index:50}
  .topbar .brand{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:#fff;flex:1}
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
      <div class="brand">FONTEC</div>
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
    <div class="brand">FONTEC</div>
    <div class="sub">Portal de Chamados</div>
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
