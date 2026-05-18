<?php
session_start();

/* ── CONFIG ── */
define('CHAMADOS_FILE', __DIR__ . '/data/chamados.json');
define('EMPRESAS_FILE', __DIR__ . '/data/empresas.json');
define('UPLOAD_DIR',    __DIR__ . '/uploads/');
define('ADMIN_PASS',    'Fontec@2026');
define('ADMIN_EMAIL',   'caio@fontecinfo.com');
define('SITE_URL',      'https://fontecinfo.com/chamados');

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
function newEmpId(): string { return 'emp_' . uniqid(); }
function mailSend(string $to, string $subj, string $html): void {
    $hdr  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $hdr .= "From: FONTEC Suporte <caio@fontecinfo.com>\r\nReply-To: caio@fontecinfo.com\r\n";
    $hdr .= "X-Mailer: FONTEC-Chamados/1.0\r\n";
    $ok = mail($to, '=?UTF-8?B?' . base64_encode($subj) . '?=', $html, $hdr, '-f caio@fontecinfo.com');
    if (!$ok) {
        $log = date('Y-m-d H:i:s') . " [ADMIN] Falha ao enviar para {$to} — {$subj}\n";
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
function fdateShort(string $d): string { return $d ? date('d/m/Y', strtotime($d)) : '-'; }

/* ── CSRF ── */
if (empty($_SESSION['adm_csrf'])) $_SESSION['adm_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['adm_csrf'];
function checkCsrf(): void {
    if (!hash_equals($_SESSION['adm_csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403); die('Token inválido.');
    }
}

/* ── AUTH ── */
$loginErr = '';
if (isset($_POST['logout'])) { unset($_SESSION['chm_admin']); header('Location: admin.php'); exit; }
if (isset($_POST['do_login'])) {
    if ($_POST['password'] === ADMIN_PASS) {
        $_SESSION['chm_admin'] = true;
    } else {
        $loginErr = 'Senha incorreta. Tente novamente.';
    }
}
$auth = !empty($_SESSION['chm_admin']);

/* ── AÇÕES ── */
$flash = $flashType = '';
if ($auth) {

    /* RESPONDER */
    if (($_POST['action'] ?? '') === 'reply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        $cid = s($_POST['cid'] ?? '');
        $msg = trim(strip_tags($_POST['msg'] ?? ''));
        if (mb_strlen($msg) < 3) {
            $flash = 'Mensagem muito curta.'; $flashType = 'err';
        } else {
            $all = lj(CHAMADOS_FILE);
            foreach ($all as &$c) {
                if ($c['id'] === $cid) {
                    $c['mensagens'][] = ['tipo' => 'admin', 'autor' => 'FONTEC Suporte', 'msg' => $msg, 'at' => date('Y-m-d H:i:s')];
                    if (!in_array($c['status'], ['resolvido', 'fechado'])) $c['status'] = 'aguardando';
                    $c['updated_at'] = date('Y-m-d H:i:s');
                    /* notifica empresa */
                    $empresas = lj(EMPRESAS_FILE);
                    foreach ($empresas as $e) {
                        if ($e['id'] === $c['empresa_id'] && !empty($e['email'])) {
                            $mb = mailTpl("Atualização no Chamado [{$cid}]",
                                "<p>Sua solicitação foi respondida pela equipe FONTEC.</p>
                                <table>
                                  <tr><td>Chamado</td><td><strong>{$cid}</strong></td></tr>
                                  <tr><td>Título</td><td>" . h($c['titulo']) . "</td></tr>
                                </table>
                                <p><strong>Resposta da FONTEC:</strong></p>
                                <div class='bx'>" . nl2br(h($msg)) . "</div>
                                <a class='btn' href='" . SITE_URL . "/index.php?page=chamado&amp;id={$cid}'>Ver no Portal</a>");
                            mailSend($e['email'], "FONTEC — Resposta no Chamado [{$cid}]", $mb);
                            break;
                        }
                    }
                    break;
                }
            }
            wj(CHAMADOS_FILE, $all);
            header("Location: admin.php?page=chamado&id={$cid}");
            exit;
        }
    }

    /* MUDAR STATUS */
    if (isset($_GET['status']) && isset($_GET['id'])) {
        $allowedStatus = ['aberto', 'em_andamento', 'aguardando', 'resolvido', 'fechado'];
        $newStatus = $_GET['status'];
        $cid       = s($_GET['id']);
        if (in_array($newStatus, $allowedStatus)) {
            $all = lj(CHAMADOS_FILE);
            foreach ($all as &$c) {
                if ($c['id'] === $cid) {
                    $c['status']     = $newStatus;
                    $c['updated_at'] = date('Y-m-d H:i:s');
                    /* notifica empresa se resolvido */
                    if ($newStatus === 'resolvido') {
                        $empresas = lj(EMPRESAS_FILE);
                        foreach ($empresas as $e) {
                            if ($e['id'] === $c['empresa_id'] && !empty($e['email'])) {
                                $mb = mailTpl("Chamado [{$cid}] Resolvido",
                                    "<p>Seu chamado foi marcado como <strong>Resolvido</strong> pela equipe FONTEC.</p>
                                    <table>
                                      <tr><td>Chamado</td><td><strong>{$cid}</strong></td></tr>
                                      <tr><td>Título</td><td>" . h($c['titulo']) . "</td></tr>
                                    </table>
                                    <p>Se o problema persistir, acesse o portal e abra um novo chamado.</p>
                                    <a class='btn' href='" . SITE_URL . "/index.php?page=chamado&amp;id={$cid}'>Acessar Portal</a>");
                                mailSend($e['email'], "FONTEC — Chamado [{$cid}] Resolvido", $mb);
                                break;
                            }
                        }
                    }
                    break;
                }
            }
            wj(CHAMADOS_FILE, $all);
        }
        header("Location: admin.php?page=chamado&id={$cid}");
        exit;
    }

    /* MUDAR PRIORIDADE */
    if (isset($_GET['prio']) && isset($_GET['id'])) {
        global $PRIOS;
        $newPrio = $_GET['prio'];
        $cid     = s($_GET['id']);
        if (in_array($newPrio, $PRIOS)) {
            $all = lj(CHAMADOS_FILE);
            foreach ($all as &$c) {
                if ($c['id'] === $cid) { $c['prioridade'] = $newPrio; break; }
            }
            wj(CHAMADOS_FILE, $all);
        }
        header("Location: admin.php?page=chamado&id={$cid}");
        exit;
    }

    /* EXCLUIR CHAMADO */
    if (isset($_GET['del_chamado'])) {
        $cid = s($_GET['del_chamado']);
        $all = lj(CHAMADOS_FILE);
        foreach ($all as $c) {
            if ($c['id'] === $cid) {
                foreach ($c['anexos'] ?? [] as $a) {
                    $path = UPLOAD_DIR . basename($a['arq'] ?? '');
                    if (file_exists($path)) unlink($path);
                }
            }
        }
        wj(CHAMADOS_FILE, array_filter($all, fn($c) => $c['id'] !== $cid));
        header('Location: admin.php?page=chamados&ok=del');
        exit;
    }

    /* SALVAR EMPRESA */
    if (($_POST['action'] ?? '') === 'save_empresa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        $empresas = lj(EMPRESAS_FILE);
        $eid      = $_POST['eid'] ?? '';
        $isNew    = empty($eid);
        $nome     = s($_POST['nome']     ?? '');
        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $contato  = s($_POST['contato']  ?? '');
        $tel      = s($_POST['telefone'] ?? '');
        $ativo    = isset($_POST['ativo']) ? 1 : 0;
        $senha    = $_POST['senha'] ?? '';
        if (mb_strlen($nome) < 2) { $flash = 'Nome inválido.'; $flashType = 'err'; }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $flash = 'E-mail inválido.'; $flashType = 'err'; }
        elseif ($isNew && mb_strlen($senha) < 6) { $flash = 'Senha muito curta (mínimo 6 caracteres).'; $flashType = 'err'; }
        else {
            if ($isNew) {
                $empresas[] = [
                    'id'         => newEmpId(),
                    'nome'       => $nome,
                    'email'      => $email,
                    'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                    'contato'    => $contato,
                    'telefone'   => $tel,
                    'ativo'      => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                $flash = 'Empresa cadastrada com sucesso!'; $flashType = 'ok';
            } else {
                foreach ($empresas as &$e) {
                    if ($e['id'] === $eid) {
                        $e['nome']     = $nome;
                        $e['email']    = $email;
                        $e['contato']  = $contato;
                        $e['telefone'] = $tel;
                        $e['ativo']    = $ativo;
                        if ($senha) $e['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
                        break;
                    }
                }
                $flash = 'Empresa atualizada!'; $flashType = 'ok';
            }
            wj(EMPRESAS_FILE, $empresas);
            header('Location: admin.php?page=empresas&ok=1');
            exit;
        }
    }

    /* EXCLUIR EMPRESA */
    if (isset($_GET['del_empresa'])) {
        $eid      = s($_GET['del_empresa']);
        $empresas = array_filter(lj(EMPRESAS_FILE), fn($e) => $e['id'] !== $eid);
        wj(EMPRESAS_FILE, $empresas);
        header('Location: admin.php?page=empresas&ok=del');
        exit;
    }
}

/* ── DADOS ── */
$page     = $auth ? s($_GET['page'] ?? 'dashboard') : 'login';
$okMsg    = !empty($_GET['ok']);
$all      = $auth ? lj(CHAMADOS_FILE)  : [];
$empresas = $auth ? lj(EMPRESAS_FILE)  : [];
usort($all, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));

/* stats */
$stats = [
    'total'    => count($all),
    'aberto'   => count(array_filter($all, fn($c) => $c['status'] === 'aberto')),
    'andamento'=> count(array_filter($all, fn($c) => $c['status'] === 'em_andamento')),
    'aguardando'=> count(array_filter($all, fn($c) => $c['status'] === 'aguardando')),
    'resolvido'=> count(array_filter($all, fn($c) => $c['status'] === 'resolvido')),
    'urgente'  => count(array_filter($all, fn($c) => $c['prioridade'] === 'Urgente' && !in_array($c['status'],['resolvido','fechado']))),
];

/* filtros na lista */
$fStatus  = s($_GET['fs'] ?? '');
$fEmpresa = s($_GET['fe'] ?? '');
$fCat     = s($_GET['fc'] ?? '');
$filtered = $all;
if ($fStatus)  $filtered = array_filter($filtered, fn($c) => $c['status']   === $fStatus);
if ($fEmpresa) $filtered = array_filter($filtered, fn($c) => $c['empresa_id'] === $fEmpresa);
if ($fCat)     $filtered = array_filter($filtered, fn($c) => $c['categoria']  === $fCat);
$filtered = array_values($filtered);

/* chamado atual */
$cur = null;
$cid = s($_GET['id'] ?? '');
if ($page === 'chamado' && $cid) {
    foreach ($all as $c) { if ($c['id'] === $cid) { $cur = $c; break; } }
    if (!$cur) { header('Location: admin.php'); exit; }
}

/* empresa para edição */
$editEmp = null;
$eid = s($_GET['eid'] ?? '');
if ($page === 'empresa_form' && $eid) {
    foreach ($empresas as $e) { if ($e['id'] === $eid) { $editEmp = $e; break; } }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Chamados FONTEC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --primary:#1e3a5f;--primary-h:#16304f;--accent:#3b82f6;
  --bg:#f1f5f9;--card:#fff;--border:#e2e8f0;
  --text:#0f172a;--muted:#64748b;--sidebar-w:250px;
  --danger:#dc2626;--danger-bg:#fef2f2;
}
body{font-family:'DM Sans',Arial,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
a{color:var(--accent);text-decoration:none}
a:hover{text-decoration:underline}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap}

/* ── LOGIN ── */
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1e3a5f 0%,#2d5a8e 100%);padding:20px}
.login-card{background:#fff;border-radius:16px;padding:40px 36px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.login-logo{text-align:center;margin-bottom:28px}
.login-logo .brand{font-family:'Syne',sans-serif;font-size:22px;font-weight:700;color:var(--primary)}
.login-logo .sub{font-size:13px;color:var(--muted);margin-top:4px}
.login-logo .adm-tag{display:inline-block;background:#fef3c7;color:#92400e;border-radius:20px;padding:3px 12px;font-size:11px;font-weight:700;margin-top:8px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;background:#fff;color:var(--text);transition:border-color .2s}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--accent)}
.form-group .hint{font-size:11px;color:var(--muted);margin-top:4px}
.btn-primary{width:100%;padding:12px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit;transition:background .2s}
.btn-primary:hover{background:var(--primary-h)}
.alert-err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:18px}
.alert-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#059669;padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:18px}

/* ── LAYOUT ── */
.layout{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar-w);background:var(--primary);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:100;transition:transform .3s}
.sidebar-logo{padding:22px 20px 14px;border-bottom:1px solid rgba(255,255,255,.1)}
.sidebar-logo .brand{font-family:'Syne',sans-serif;font-size:18px;font-weight:700;color:#fff}
.sidebar-logo .sub{font-size:11px;color:rgba(255,255,255,.5);margin-top:2px}
.sidebar-logo .adm-tag{display:inline-block;background:rgba(251,191,36,.2);color:#fbbf24;border-radius:20px;padding:2px 8px;font-size:10px;font-weight:700;margin-top:6px}
.sidebar-nav{flex:1;padding:12px 0}
.nav-section{padding:10px 20px 4px;font-size:10px;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.8px}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 20px;color:rgba(255,255,255,.75);font-size:14px;font-weight:500;transition:background .15s,color .15s;text-decoration:none}
.nav-item:hover,.nav-item.active{background:rgba(255,255,255,.12);color:#fff;text-decoration:none}
.nav-item i{width:18px;text-align:center;font-size:14px}
.nav-badge{margin-left:auto;background:#ef4444;color:#fff;border-radius:20px;padding:1px 7px;font-size:11px;font-weight:700;min-width:20px;text-align:center}
.sidebar-bottom{padding:16px 20px;border-top:1px solid rgba(255,255,255,.1)}
.btn-logout{width:100%;padding:9px;background:rgba(255,255,255,.1);color:rgba(255,255,255,.8);border:1px solid rgba(255,255,255,.2);border-radius:8px;font-size:13px;cursor:pointer;font-family:inherit;transition:background .2s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-logout:hover{background:rgba(255,255,255,.2);color:#fff}
.main{margin-left:var(--sidebar-w);flex:1;padding:30px;max-width:calc(100% - var(--sidebar-w))}
.page-header{margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
.page-header-left h1{font-family:'Syne',sans-serif;font-size:22px;font-weight:700;color:var(--text)}
.page-header-left p{font-size:14px;color:var(--muted);margin-top:4px}
.topbar{display:none}

/* ── STATS ── */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:28px}
.stat-card{background:var(--card);border-radius:12px;padding:18px;border:1px solid var(--border)}
.stat-card .num{font-family:'Syne',sans-serif;font-size:30px;font-weight:700;color:var(--text)}
.stat-card .lbl{font-size:12px;color:var(--muted);margin-top:3px;font-weight:500}
.stat-card .icon{font-size:20px;margin-bottom:6px}
.stat-card.urgent{border-color:#fecaca;background:#fef2f2}
.stat-card.urgent .num{color:#dc2626}

/* ── CARDS / TABLES ── */
.card{background:var(--card);border-radius:12px;border:1px solid var(--border);overflow:hidden;margin-bottom:24px}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.card-header h2{font-size:15px;font-weight:600;color:var(--text)}
.card-body{padding:20px}
.tbl-wrap{overflow-x:auto}
table.data{width:100%;border-collapse:collapse;font-size:13px}
table.data th{padding:10px 14px;background:#f8fafc;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
table.data td{padding:11px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
table.data tr:last-child td{border-bottom:none}
table.data tr:hover td{background:#f8fafc}
.tbl-link{font-weight:600;color:var(--primary)}
.tbl-link:hover{color:var(--accent)}
.empty{text-align:center;padding:40px;color:var(--muted);font-size:14px}
.empty i{font-size:36px;margin-bottom:12px;display:block;opacity:.4}

/* ── FILTERS ── */
.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px}
.filters select,.filters input{padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;background:#fff;color:var(--text);min-width:140px}
.filters select:focus,.filters input:focus{outline:none;border-color:var(--accent)}
.btn-filter{padding:8px 16px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-size:13px;cursor:pointer;font-family:inherit}
.btn-reset{padding:8px 12px;background:#fff;color:var(--muted);border:1.5px solid var(--border);border-radius:8px;font-size:13px;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.btn-reset:hover{border-color:var(--accent);color:var(--accent);text-decoration:none}

/* ── ACTIONS ── */
.btn-send{padding:10px 20px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;transition:background .2s;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.btn-send:hover{background:var(--primary-h);text-decoration:none;color:#fff}
.btn-sec{padding:9px 16px;background:#fff;color:var(--muted);border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:border-color .2s}
.btn-sec:hover{border-color:var(--accent);color:var(--accent);text-decoration:none}
.btn-danger{padding:8px 14px;background:#fef2f2;color:#dc2626;border:1.5px solid #fecaca;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:background .2s}
.btn-danger:hover{background:#fee2e2;text-decoration:none;color:#dc2626}
.btn-success{padding:8px 14px;background:#ecfdf5;color:#059669;border:1.5px solid #a7f3d0;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.btn-success:hover{background:#d1fae5;text-decoration:none;color:#059669}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-actions{display:flex;gap:12px;align-items:center;margin-top:24px;flex-wrap:wrap}

/* ── CHAMADO VIEW ── */
.chamado-header{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:22px;margin-bottom:20px}
.chamado-id{font-size:12px;color:var(--muted);font-weight:600;margin-bottom:6px}
.chamado-titulo{font-family:'Syne',sans-serif;font-size:20px;font-weight:700;color:var(--text);margin-bottom:14px}
.chamado-meta{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.meta-item{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted)}
.actions-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;padding:14px;background:var(--card);border:1px solid var(--border);border-radius:10px}
.actions-bar label{font-size:12px;font-weight:600;color:var(--muted);display:block;margin-bottom:4px}
.actions-bar select{padding:7px 10px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;font-family:inherit;background:#fff;cursor:pointer}
.desc-box{background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:16px;font-size:14px;line-height:1.7;white-space:pre-wrap;margin-bottom:8px;color:var(--text)}
.msg-thread{display:flex;flex-direction:column;gap:14px;margin-bottom:16px}
.msg{padding:14px 16px;border-radius:10px;font-size:14px;line-height:1.7;max-width:88%}
.msg.empresa{background:#eff6ff;border:1px solid #bfdbfe;align-self:flex-end;border-bottom-right-radius:3px}
.msg.admin{background:#f0fdf4;border:1px solid #bbf7d0;align-self:flex-start;border-bottom-left-radius:3px}
.msg .msg-meta{font-size:11px;color:var(--muted);margin-bottom:6px;font-weight:600}
.msg .msg-text{white-space:pre-wrap}
.reply-form{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px}
.reply-form h3{font-size:15px;font-weight:600;margin-bottom:14px}
.reply-form textarea{width:100%;min-height:100px;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;resize:vertical;transition:border-color .2s}
.reply-form textarea:focus{outline:none;border-color:var(--accent)}
.anexo-list{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.anexo-chip{display:inline-flex;align-items:center;gap:6px;background:#f8fafc;border:1px solid var(--border);border-radius:6px;padding:5px 10px;font-size:12px;color:var(--text);text-decoration:none}
.anexo-chip:hover{background:#eff6ff;text-decoration:none}
.two-col{display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start}
.sidebar-info .card{margin-bottom:0}
.info-row{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border);font-size:13px;align-items:center}
.info-row:last-child{border-bottom:none}
.info-row .lbl{color:var(--muted);font-weight:500}
.info-row .val{font-weight:600;text-align:right}

/* ── TOGGLE ── */
.toggle-wrap{display:flex;align-items:center;gap:10px}
.toggle{position:relative;width:44px;height:24px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:#d1d5db;border-radius:24px;cursor:pointer;transition:background .2s}
.toggle-slider::before{content:'';position:absolute;width:18px;height:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:transform .2s}
.toggle input:checked + .toggle-slider{background:#059669}
.toggle input:checked + .toggle-slider::before{transform:translateX(20px)}

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
  .two-col{grid-template-columns:1fr}
  .msg{max-width:100%}
  .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:90}
  .overlay.open{display:block}
}
@media(max-width:480px){.stat-grid{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>

<?php if (!$auth): ?>
<!-- ── LOGIN ── -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <div class="brand">FONTEC</div>
      <div class="sub">Painel Administrativo</div>
      <div class="adm-tag">ADMIN</div>
    </div>
    <?php if ($loginErr): ?>
    <div class="alert-err"><i class="fa fa-circle-exclamation"></i> <?= h($loginErr) ?></div>
    <?php endif ?>
    <form method="post" autocomplete="off">
      <div class="form-group">
        <label>Senha de acesso</label>
        <input type="password" name="password" placeholder="••••••••" required autofocus autocomplete="current-password">
      </div>
      <button type="submit" name="do_login" class="btn-primary">
        <i class="fa fa-shield-halved"></i> Entrar
      </button>
    </form>
    <p style="text-align:center;margin-top:20px;font-size:12px;color:#94a3b8">
      <a href="index.php">← Portal do Cliente</a>
    </p>
  </div>
</div>

<?php else: ?>
<!-- ── LAYOUT AUTENTICADO ── -->
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="brand">FONTEC</div>
    <div class="sub">Sistema de Chamados</div>
    <div class="adm-tag">ADMINISTRADOR</div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">Principal</div>
    <a href="admin.php?page=dashboard" class="nav-item <?= $page==='dashboard'?'active':'' ?>">
      <i class="fa fa-gauge"></i> Dashboard
      <?php if ($stats['urgente']>0): ?><span class="nav-badge"><?= $stats['urgente'] ?></span><?php endif ?>
    </a>
    <a href="admin.php?page=chamados" class="nav-item <?= $page==='chamados'?'active':'' ?>">
      <i class="fa fa-ticket"></i> Todos os Chamados
      <?php if ($stats['aberto']>0): ?><span class="nav-badge"><?= $stats['aberto'] ?></span><?php endif ?>
    </a>
    <div class="nav-section">Gestão</div>
    <a href="admin.php?page=empresas" class="nav-item <?= in_array($page,['empresas','empresa_form'])?'active':'' ?>">
      <i class="fa fa-building"></i> Empresas
    </a>
    <a href="index.php" target="_blank" class="nav-item">
      <i class="fa fa-arrow-up-right-from-square"></i> Portal Cliente
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
  <span class="brand">FONTEC Admin</span>
</div>

<main class="main">

<?php if ($flash): ?>
<div class="<?= $flashType==='ok'?'alert-ok':'alert-err' ?>" style="margin-bottom:20px">
  <i class="fa <?= $flashType==='ok'?'fa-circle-check':'fa-circle-exclamation' ?>"></i> <?= h($flash) ?>
</div>
<?php endif ?>
<?php if ($okMsg && !$flash): ?>
<div class="alert-ok" style="margin-bottom:20px"><i class="fa fa-circle-check"></i> Operação realizada com sucesso!</div>
<?php endif ?>

<?php /* ── DASHBOARD ── */
if ($page === 'dashboard'): ?>
  <div class="page-header">
    <div class="page-header-left">
      <h1>Dashboard</h1>
      <p>Visão geral dos chamados — <?= date('d/m/Y') ?></p>
    </div>
    <a href="admin.php?page=empresas" class="btn-sec"><i class="fa fa-building"></i> Gerenciar Empresas</a>
  </div>
  <div class="stat-grid">
    <div class="stat-card">
      <div class="icon" style="color:#2563eb"><i class="fa fa-folder-open"></i></div>
      <div class="num"><?= $stats['aberto'] ?></div>
      <div class="lbl">Abertos</div>
    </div>
    <div class="stat-card">
      <div class="icon" style="color:#d97706"><i class="fa fa-gear"></i></div>
      <div class="num"><?= $stats['andamento'] ?></div>
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
    <div class="stat-card <?= $stats['urgente']>0?'urgent':'' ?>">
      <div class="icon" style="color:#dc2626"><i class="fa fa-fire"></i></div>
      <div class="num"><?= $stats['urgente'] ?></div>
      <div class="lbl">Urgentes Abertos</div>
    </div>
    <div class="stat-card">
      <div class="icon" style="color:var(--muted)"><i class="fa fa-list"></i></div>
      <div class="num"><?= $stats['total'] ?></div>
      <div class="lbl">Total</div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2><i class="fa fa-clock-rotate-left" style="margin-right:6px;opacity:.7"></i> Chamados Recentes</h2>
      <a href="admin.php?page=chamados" class="btn-sec" style="font-size:13px;padding:7px 12px">Ver todos</a>
    </div>
    <?php $recent = array_slice($all, 0, 10); ?>
    <?php if ($recent): ?>
    <div class="tbl-wrap">
      <table class="data">
        <thead><tr>
          <th>ID</th><th>Empresa</th><th>Título</th><th>Categoria</th><th>Prioridade</th><th>Status</th><th>Atualizado</th>
        </tr></thead>
        <tbody>
        <?php foreach ($recent as $c): ?>
        <tr>
          <td><a href="admin.php?page=chamado&id=<?= h($c['id']) ?>" class="tbl-link"><?= h($c['id']) ?></a></td>
          <td style="font-size:12px"><?= h($c['empresa_nome']) ?></td>
          <td><?= h(mb_strimwidth($c['titulo'], 0, 45, '…')) ?></td>
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
    <div class="empty"><i class="fa fa-inbox"></i> Nenhum chamado ainda.</div>
    <?php endif ?>
  </div>

<?php /* ── LISTA CHAMADOS ── */
elseif ($page === 'chamados'): ?>
  <div class="page-header">
    <div class="page-header-left">
      <h1>Todos os Chamados</h1>
      <p><?= count($filtered) ?> de <?= $stats['total'] ?> chamados</p>
    </div>
  </div>
  <form method="get" action="admin.php">
    <input type="hidden" name="page" value="chamados">
    <div class="filters">
      <select name="fs">
        <option value="">Todos os status</option>
        <?php foreach ($ST as $k => $v): ?>
        <option value="<?= h($k) ?>" <?= $fStatus===$k?'selected':'' ?>><?= h($v['label']) ?></option>
        <?php endforeach ?>
      </select>
      <select name="fe">
        <option value="">Todas as empresas</option>
        <?php foreach ($empresas as $e): ?>
        <option value="<?= h($e['id']) ?>" <?= $fEmpresa===$e['id']?'selected':'' ?>><?= h($e['nome']) ?></option>
        <?php endforeach ?>
      </select>
      <select name="fc">
        <option value="">Todas as categorias</option>
        <?php foreach ($CATS as $cat): ?>
        <option value="<?= h($cat) ?>" <?= $fCat===$cat?'selected':'' ?>><?= h($cat) ?></option>
        <?php endforeach ?>
      </select>
      <button type="submit" class="btn-filter"><i class="fa fa-filter"></i> Filtrar</button>
      <?php if ($fStatus||$fEmpresa||$fCat): ?>
      <a href="admin.php?page=chamados" class="btn-reset"><i class="fa fa-xmark"></i> Limpar</a>
      <?php endif ?>
    </div>
  </form>
  <div class="card">
    <?php if ($filtered): ?>
    <div class="tbl-wrap">
      <table class="data">
        <thead><tr>
          <th>ID</th><th>Empresa</th><th>Título</th><th>Categoria</th><th>Prioridade</th><th>Status</th><th>Aberto</th><th>Atualizado</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($filtered as $c): ?>
        <tr>
          <td><a href="admin.php?page=chamado&id=<?= h($c['id']) ?>" class="tbl-link"><?= h($c['id']) ?></a></td>
          <td style="font-size:12px"><?= h($c['empresa_nome']) ?></td>
          <td><?= h(mb_strimwidth($c['titulo'], 0, 42, '…')) ?></td>
          <td><span style="font-size:12px;color:var(--muted)"><?= h($c['categoria']) ?></span></td>
          <td><?= prBadge($c['prioridade']) ?></td>
          <td><?= stBadge($c['status']) ?></td>
          <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?= fdate($c['created_at']) ?></td>
          <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?= fdate($c['updated_at']) ?></td>
          <td>
            <a href="admin.php?page=chamado&id=<?= h($c['id']) ?>" class="btn-sec" style="font-size:12px;padding:5px 10px">
              <i class="fa fa-eye"></i>
            </a>
          </td>
        </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty"><i class="fa fa-inbox"></i> Nenhum chamado encontrado.</div>
    <?php endif ?>
  </div>

<?php /* ── VER CHAMADO ── */
elseif ($page === 'chamado' && $cur): ?>
  <div style="margin-bottom:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <a href="admin.php?page=chamados" class="btn-sec" style="font-size:13px;padding:8px 14px">
      <i class="fa fa-arrow-left"></i> Voltar
    </a>
    <a href="admin.php?del_chamado=<?= h($cur['id']) ?>" class="btn-danger" style="font-size:13px;padding:8px 14px"
       onclick="return confirm('Excluir este chamado permanentemente?')">
      <i class="fa fa-trash"></i> Excluir
    </a>
  </div>

  <div class="chamado-header">
    <div class="chamado-id"><?= h($cur['id']) ?></div>
    <div class="chamado-titulo"><?= h($cur['titulo']) ?></div>
    <div class="chamado-meta">
      <?= stBadge($cur['status']) ?>
      <?= prBadge($cur['prioridade']) ?>
      <span class="meta-item"><i class="fa fa-building"></i> <?= h($cur['empresa_nome']) ?></span>
      <span class="meta-item"><i class="fa fa-folder"></i> <?= h($cur['categoria']) ?></span>
      <span class="meta-item"><i class="fa fa-calendar"></i> <?= fdate($cur['created_at']) ?></span>
    </div>
  </div>

  <div class="actions-bar">
    <div>
      <label>Alterar Status</label>
      <select onchange="location.href='admin.php?page=chamado&id=<?= h($cur['id']) ?>&status='+this.value">
        <?php foreach ($ST as $k => $v): ?>
        <option value="<?= h($k) ?>" <?= $cur['status']===$k?'selected':'' ?>><?= h($v['label']) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div>
      <label>Alterar Prioridade</label>
      <select onchange="location.href='admin.php?page=chamado&id=<?= h($cur['id']) ?>&prio='+this.value">
        <?php foreach ($PRIOS as $p): ?>
        <option value="<?= h($p) ?>" <?= $cur['prioridade']===$p?'selected':'' ?>><?= h($p) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <?php if (!in_array($cur['status'],['resolvido','fechado'])): ?>
    <div style="align-self:flex-end">
      <a href="admin.php?page=chamado&id=<?= h($cur['id']) ?>&status=resolvido" class="btn-success">
        <i class="fa fa-circle-check"></i> Marcar Resolvido
      </a>
    </div>
    <?php endif ?>
  </div>

  <div class="two-col">
    <div>
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
        <div class="card-header"><h2>Histórico de mensagens (<?= count($cur['mensagens']) ?>)</h2></div>
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

      <div class="reply-form">
        <h3><i class="fa fa-reply" style="margin-right:6px;opacity:.7"></i> Responder à Empresa</h3>
        <form method="post">
          <input type="hidden" name="csrf"   value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="reply">
          <input type="hidden" name="cid"    value="<?= h($cur['id']) ?>">
          <textarea name="msg" required placeholder="Escreva a resposta para a empresa..."></textarea>
          <div class="form-actions">
            <button type="submit" class="btn-send"><i class="fa fa-paper-plane"></i> Enviar Resposta</button>
            <small style="color:var(--muted)">A empresa receberá um e-mail com sua resposta.</small>
          </div>
        </form>
      </div>
    </div>

    <div class="sidebar-info">
      <div class="card">
        <div class="card-header"><h2>Informações</h2></div>
        <div class="card-body" style="padding:12px 16px">
          <div class="info-row"><span class="lbl">ID</span><span class="val"><?= h($cur['id']) ?></span></div>
          <div class="info-row"><span class="lbl">Empresa</span><span class="val"><?= h($cur['empresa_nome']) ?></span></div>
          <div class="info-row"><span class="lbl">Categoria</span><span class="val"><?= h($cur['categoria']) ?></span></div>
          <div class="info-row"><span class="lbl">Prioridade</span><span class="val"><?= prBadge($cur['prioridade']) ?></span></div>
          <div class="info-row"><span class="lbl">Status</span><span class="val"><?= stBadge($cur['status']) ?></span></div>
          <div class="info-row"><span class="lbl">Aberto em</span><span class="val" style="font-size:12px"><?= fdate($cur['created_at']) ?></span></div>
          <div class="info-row"><span class="lbl">Atualizado</span><span class="val" style="font-size:12px"><?= fdate($cur['updated_at']) ?></span></div>
          <div class="info-row"><span class="lbl">Mensagens</span><span class="val"><?= count($cur['mensagens'] ?? []) ?></span></div>
        </div>
      </div>
    </div>
  </div>

<?php /* ── EMPRESAS ── */
elseif ($page === 'empresas'): ?>
  <div class="page-header">
    <div class="page-header-left">
      <h1>Empresas</h1>
      <p><?= count($empresas) ?> empresa(s) cadastrada(s)</p>
    </div>
    <a href="admin.php?page=empresa_form" class="btn-send">
      <i class="fa fa-plus"></i> Nova Empresa
    </a>
  </div>
  <div class="card">
    <?php if ($empresas): ?>
    <div class="tbl-wrap">
      <table class="data">
        <thead><tr>
          <th>Empresa</th><th>E-mail</th><th>Contato</th><th>Telefone</th><th>Cadastro</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($empresas as $e): ?>
        <tr>
          <td><strong><?= h($e['nome']) ?></strong></td>
          <td style="font-size:12px"><?= h($e['email']) ?></td>
          <td style="font-size:12px"><?= h($e['contato'] ?? '-') ?></td>
          <td style="font-size:12px"><?= h($e['telefone'] ?? '-') ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= fdateShort($e['created_at'] ?? '') ?></td>
          <td><?= !empty($e['ativo']) ? "<span class='badge' style='background:#ecfdf5;color:#059669'>Ativo</span>" : "<span class='badge' style='background:#f3f4f6;color:#4b5563'>Inativo</span>" ?></td>
          <td style="white-space:nowrap">
            <a href="admin.php?page=empresa_form&eid=<?= h($e['id']) ?>" class="btn-sec" style="font-size:12px;padding:5px 10px;margin-right:4px">
              <i class="fa fa-pen"></i>
            </a>
            <a href="admin.php?del_empresa=<?= h($e['id']) ?>" class="btn-danger" style="font-size:12px;padding:5px 10px"
               onclick="return confirm('Excluir empresa <?= h(addslashes($e['nome'])) ?>?')">
              <i class="fa fa-trash"></i>
            </a>
          </td>
        </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty">
      <i class="fa fa-building"></i>
      Nenhuma empresa cadastrada.<br>
      <a href="admin.php?page=empresa_form" style="margin-top:10px;display:inline-block">Cadastrar primeira empresa</a>
    </div>
    <?php endif ?>
  </div>

<?php /* ── FORM EMPRESA ── */
elseif ($page === 'empresa_form'): ?>
  <div class="page-header">
    <div class="page-header-left">
      <h1><?= $editEmp ? 'Editar Empresa' : 'Nova Empresa' ?></h1>
      <p><?= $editEmp ? 'Atualize os dados de acesso da empresa.' : 'Cadastre uma empresa para que ela possa abrir chamados.' ?></p>
    </div>
  </div>
  <div style="max-width:680px">
    <div class="card">
      <div class="card-header">
        <h2><i class="fa fa-building" style="margin-right:6px;opacity:.7"></i>
          <?= $editEmp ? h($editEmp['nome']) : 'Dados da Empresa' ?>
        </h2>
      </div>
      <div class="card-body">
        <?php if ($flash): ?>
        <div class="alert-err" style="margin-bottom:16px"><i class="fa fa-circle-exclamation"></i> <?= h($flash) ?></div>
        <?php endif ?>
        <form method="post">
          <input type="hidden" name="csrf"   value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="save_empresa">
          <input type="hidden" name="eid"    value="<?= h($editEmp['id'] ?? '') ?>">
          <div class="form-row">
            <div class="form-group">
              <label>Nome da Empresa <span style="color:#dc2626">*</span></label>
              <input type="text" name="nome" required maxlength="120" placeholder="Ex: Alfa Distribuidora Ltda"
                     value="<?= h($editEmp['nome'] ?? $_POST['nome'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>E-mail de acesso <span style="color:#dc2626">*</span></label>
              <input type="email" name="email" required placeholder="empresa@exemplo.com"
                     value="<?= h($editEmp['email'] ?? $_POST['email'] ?? '') ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Nome do Contato</label>
              <input type="text" name="contato" maxlength="80" placeholder="Ex: João Silva"
                     value="<?= h($editEmp['contato'] ?? $_POST['contato'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Telefone</label>
              <input type="text" name="telefone" maxlength="20" placeholder="(62) 9 0000-0000"
                     value="<?= h($editEmp['telefone'] ?? $_POST['telefone'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label><?= $editEmp ? 'Nova Senha (deixe em branco para manter)' : 'Senha de acesso' ?> <?= !$editEmp ? '<span style="color:#dc2626">*</span>' : '' ?></label>
            <input type="password" name="senha" <?= !$editEmp?'required':'' ?> minlength="6"
                   placeholder="<?= $editEmp ? 'Nova senha (opcional)' : 'Mínimo 6 caracteres' ?>"
                   autocomplete="new-password">
            <?php if ($editEmp): ?>
            <div class="hint">Deixe em branco para não alterar a senha atual.</div>
            <?php endif ?>
          </div>
          <?php if ($editEmp): ?>
          <div class="form-group">
            <div class="toggle-wrap">
              <label class="toggle">
                <input type="checkbox" name="ativo" <?= !empty($editEmp['ativo'])?'checked':'' ?>>
                <span class="toggle-slider"></span>
              </label>
              <span style="font-size:14px;font-weight:500">Empresa ativa</span>
            </div>
            <div class="hint">Empresas inativas não conseguem fazer login no portal.</div>
          </div>
          <?php endif ?>
          <div class="form-actions">
            <button type="submit" class="btn-send">
              <i class="fa <?= $editEmp?'fa-floppy-disk':'fa-plus' ?>"></i>
              <?= $editEmp ? 'Salvar Alterações' : 'Cadastrar Empresa' ?>
            </button>
            <a href="admin.php?page=empresas" class="btn-sec"><i class="fa fa-arrow-left"></i> Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>

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
