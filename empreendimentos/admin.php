<?php
session_start();

/* ── CONFIG ── */
define('ADMIN_PASS',  'Fontec@2026');
define('JSON_FILE',   __DIR__ . '/data/fazendas.json');
define('UPLOAD_DIR',  __DIR__ . '/uploads/');
define('ALLOWED_IMG',   ['image/jpeg','image/png','image/webp','image/gif']);
define('ALLOWED_VID',   ['video/mp4','video/webm','video/ogg']);
define('FILIADOS_FILE', __DIR__ . '/data/filiados.json');
define('COOKIE_SECRET', 'fontec_empr_2026_sec');
define('PERMS_LIST', [
    'ver_imoveis'        => 'Visualizar imóveis',
    'cadastrar_imoveis'  => 'Cadastrar imóveis',
    'editar_imoveis'     => 'Editar imóveis',
    'excluir_imoveis'    => 'Excluir imóveis',
    'publicar_imoveis'   => 'Publicar / Ocultar imóveis',
    'gerenciar_imagens'  => 'Gerenciar imagens do servidor',
    'ver_filiados'       => 'Visualizar filiados',
    'gerenciar_filiados' => 'Cadastrar e editar filiados',
]);

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

function loadFiliados(): array {
    if (!file_exists(FILIADOS_FILE)) return [];
    return json_decode(file_get_contents(FILIADOS_FILE), true) ?: [];
}

function saveFiliados(array $data): void {
    file_put_contents(FILIADOS_FILE, json_encode(array_values($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function gerarCodigo(array $rows): string {
    $max = 202600;
    foreach ($rows as $r) {
        if (!empty($r['codigo']) && (int)$r['codigo'] > $max) $max = (int)$r['codigo'];
    }
    return (string)($max + 1);
}

function can(string $perm): bool {
    if (!empty($_SESSION['admin'])) return true;
    return in_array($perm, $_SESSION['filiado']['perms'] ?? []);
}

/* ── AUTH ── */
$loginError = '';
$secure     = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

/* auto-login via cookie "lembrar-me" */
if (empty($_SESSION['admin']) && empty($_SESSION['filiado'])) {
    if (isset($_COOKIE['adm_tok'])) {
        $exp = hash_hmac('sha256', 'admin', COOKIE_SECRET . ADMIN_PASS);
        if (hash_equals($exp, $_COOKIE['adm_tok'])) $_SESSION['admin'] = true;
    }
    if (empty($_SESSION['admin']) && isset($_COOKIE['fil_tok'])) {
        $parts = explode('|', $_COOKIE['fil_tok'], 2);
        if (count($parts) === 2) {
            [$fid, $sig] = $parts;
            foreach (loadFiliados() as $f) {
                if ($f['id'] === $fid && !empty($f['ativo'])) {
                    $exp = hash_hmac('sha256', $fid, COOKIE_SECRET . ($f['senha'] ?? ''));
                    if (hash_equals($exp, $sig)) {
                        $_SESSION['filiado'] = ['id' => $f['id'], 'nome' => $f['nome'], 'perms' => $f['perms'] ?? []];
                        break;
                    }
                }
            }
        }
    }
}

if (isset($_POST['logout'])) {
    session_destroy();
    setcookie('adm_tok', '', time() - 3600, '/', '', $secure, true);
    setcookie('fil_tok', '', time() - 3600, '/', '', $secure, true);
    header('Location: admin.php');
    exit;
}
if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASS) {
        $_SESSION['admin'] = true;
        if (!empty($_POST['remember'])) {
            $tok = hash_hmac('sha256', 'admin', COOKIE_SECRET . ADMIN_PASS);
            setcookie('adm_tok', $tok, time() + 30*24*3600, '/', '', $secure, true);
        }
    } else {
        $loginError = 'Senha incorreta. Tente novamente.';
    }
}
if (isset($_POST['filiado_login'])) {
    $loginError = 'E-mail ou senha incorretos.';
    foreach (loadFiliados() as $f) {
        if ($f['email'] === trim($_POST['filiado_email'] ?? '')
            && !empty($f['ativo'])
            && password_verify($_POST['filiado_senha'] ?? '', $f['senha'] ?? '')) {
            $_SESSION['filiado'] = ['id' => $f['id'], 'nome' => $f['nome'], 'perms' => $f['perms'] ?? []];
            if (!empty($_POST['remember'])) {
                $tok = hash_hmac('sha256', $f['id'], COOKIE_SECRET . ($f['senha'] ?? ''));
                setcookie('fil_tok', $f['id'] . '|' . $tok, time() + 30*24*3600, '/', '', $secure, true);
            }
            $loginError = '';
            break;
        }
    }
}
$isAdmin   = !empty($_SESSION['admin']);
$isFiliado = !empty($_SESSION['filiado']);
$isAuth    = $isAdmin || $isFiliado;

/* ── AÇÕES (requer auth) ── */
$msg = '';
if ($isAuth) {

    /* PUBLICAR / OCULTAR */
    if (isset($_GET['toggle']) && can('publicar_imoveis')) {
        $id = $_GET['toggle'];
        $rows = loadFazendas();
        foreach ($rows as &$r) {
            if ($r['id'] === $id) $r['publicado'] = empty($r['publicado']) ? 1 : 0;
        }
        saveFazendas($rows);
        header('Location: admin.php?ok=toggle');
        exit;
    }

    /* EXCLUIR IMÓVEL */
    if (isset($_GET['del']) && can('excluir_imoveis')) {
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

    /* EXCLUIR IMAGEM DO SERVIDOR */
    if (isset($_GET['delimg']) && can('gerenciar_imagens')) {
        deleteFile(basename($_GET['delimg']));
        header('Location: admin.php?ok=delimg&section=imagens');
        exit;
    }

    /* EXCLUIR IMAGENS EM LOTE */
    if (isset($_POST['action']) && $_POST['action'] === 'del_imgs' && can('gerenciar_imagens')) {
        foreach ($_POST['imgs'] ?? [] as $img) deleteFile(basename($img));
        header('Location: admin.php?ok=delimg&section=imagens');
        exit;
    }

    /* SALVAR FILIADO */
    if (isset($_POST['action']) && $_POST['action'] === 'save_filiado' && ($isAdmin || can('gerenciar_filiados'))) {
        $filiados = loadFiliados();
        $fid      = $_POST['filiado_id'] ?? '';
        $isNewF   = empty($fid);
        $filiado  = [
            'id'        => $isNewF ? newId() : $fid,
            'nome'      => sanitize($_POST['filiado_nome']     ?? ''),
            'cpf'       => sanitize($_POST['filiado_cpf']      ?? ''),
            'email'     => sanitize($_POST['filiado_email']    ?? ''),
            'telefone'  => sanitize($_POST['filiado_telefone'] ?? ''),
            'perms'     => array_values(array_filter($_POST['filiado_perms'] ?? [], fn($p) => array_key_exists($p, PERMS_LIST))),
            'ativo'     => isset($_POST['filiado_ativo']) ? 1 : 0,
            'criado_em' => '',
            'senha'     => '',
        ];
        $senha = $_POST['filiado_senha'] ?? '';
        if ($isNewF) {
            $filiado['senha']     = password_hash($senha ?: 'trocar123', PASSWORD_DEFAULT);
            $filiado['criado_em'] = date('Y-m-d H:i:s');
        } else {
            foreach ($filiados as $f) {
                if ($f['id'] === $fid) {
                    $filiado['senha']     = $senha ? password_hash($senha, PASSWORD_DEFAULT) : ($f['senha'] ?? '');
                    $filiado['criado_em'] = $f['criado_em'] ?? '';
                    break;
                }
            }
        }
        if ($isNewF) $filiados[] = $filiado;
        else foreach ($filiados as &$f) { if ($f['id'] === $fid) { $f = $filiado; break; } }
        saveFiliados($filiados);
        header('Location: admin.php?ok=filiado&section=filiados');
        exit;
    }

    /* EXCLUIR FILIADO */
    if (isset($_GET['del_filiado']) && ($isAdmin || can('gerenciar_filiados'))) {
        $filiados = array_filter(loadFiliados(), fn($f) => $f['id'] !== $_GET['del_filiado']);
        saveFiliados($filiados);
        header('Location: admin.php?ok=del_filiado&section=filiados');
        exit;
    }

    /* SALVAR IMÓVEL */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
        $rows  = loadFazendas();
        $id    = $_POST['id'] ?? '';
        $isNew = empty($id);

        if (!$isNew && !can('editar_imoveis')) { header('Location: admin.php'); exit; }
        if ($isNew  && !can('cadastrar_imoveis')) { header('Location: admin.php'); exit; }

        $fazenda = [
            'id'          => $isNew ? newId() : $id,
            'codigo'      => '',
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
            foreach ($rows as $r) {
                if ($r['id'] === $id) {
                    $fazenda['fotos']      = $r['fotos']      ?? [];
                    $fazenda['video_file'] = $r['video_file'] ?? '';
                    $fazenda['criado_em']  = $r['criado_em']  ?? '';
                    $fazenda['codigo']     = $r['codigo']     ?? gerarCodigo($rows);
                    break;
                }
            }
        } else {
            $fazenda['codigo']    = gerarCodigo($rows);
            $fazenda['criado_em'] = date('Y-m-d H:i:s');
        }

        /* upload fotos */
        $newFotos = uploadFiles('fotos', ALLOWED_IMG, 'foto');
        $fazenda['fotos'] = array_merge($fazenda['fotos'], $newFotos);

        /* fotos já existentes no servidor selecionadas pelo usuário */
        foreach ($_POST['exist_fotos'] ?? [] as $ef) {
            $ef = basename($ef);
            if ($ef && file_exists(UPLOAD_DIR . $ef) && !in_array($ef, $fazenda['fotos'])) {
                $fazenda['fotos'][] = $ef;
            }
        }

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

        /* reordenar fotos */
        if (!empty($_POST['foto_order'])) {
            $cur     = $fazenda['fotos'];
            $ordered = array_values(array_filter($_POST['foto_order'], fn($f) => in_array($f, $cur)));
            if (count($ordered) === count($cur)) $fazenda['fotos'] = $ordered;
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

$rows    = $isAuth && can('ver_imoveis') ? loadFazendas() : [];
$editRow = null;
if (!empty($_GET['edit']) && $isAuth && can('editar_imoveis')) {
    $eid = $_GET['edit'];
    foreach ($rows as $r) { if ($r['id'] === $eid) { $editRow = $r; break; } }
}
$filiados    = ($isAdmin || can('gerenciar_filiados')) ? loadFiliados() : [];
$editFiliado = null;
if (!empty($_GET['edit_filiado']) && ($isAdmin || can('gerenciar_filiados'))) {
    foreach ($filiados as $f) { if ($f['id'] === $_GET['edit_filiado']) { $editFiliado = $f; break; } }
}
$section = $_GET['section'] ?? 'imoveis';
$ok      = $_GET['ok'] ?? '';
$okMsg   = match($ok) {
    'save'        => 'Propriedade salva com sucesso!',
    'del'         => 'Propriedade excluída.',
    'toggle'      => 'Visibilidade atualizada.',
    'delimg'      => 'Imagem(ns) excluída(s) com sucesso.',
    'filiado'     => 'Filiado salvo com sucesso!',
    'del_filiado' => 'Filiado excluído.',
    default       => '',
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
      padding: 0 5%;
      height: 80px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 50;
      box-shadow: var(--shadow);
      overflow: visible;
    }
    .admin-brand { display: flex; align-items: center; gap: 4px; text-decoration: none; }
    .admin-brand img {
      height: 234px; width: auto; object-fit: contain;
      mix-blend-mode: multiply; -webkit-user-drag: none; user-drag: none; pointer-events: none;
    }
    [data-theme="dark"] .admin-brand img { mix-blend-mode: normal; filter: brightness(0) invert(1); }
    .admin-brand-text { display: flex; flex-direction: column; line-height: 1.2; }
    .admin-brand-name { font-size: .75rem; color: var(--muted); letter-spacing: .04em; }
    .admin-brand-badge {
      display: inline-block; font-size: .6rem; font-weight: 700; letter-spacing: .08em;
      background: var(--accent); color: #fff;
      padding: 2px 8px; border-radius: 20px; text-transform: uppercase;
      margin-top: 4px; width: fit-content;
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
    .login-logo-wrap {
      display: flex; align-items: center; justify-content: center; gap: 6px;
      height: 70px; overflow: hidden; margin-bottom: 24px;
    }
    .login-logo-wrap img {
      height: 220px; width: auto; object-fit: contain;
      mix-blend-mode: multiply; flex-shrink: 0;
      -webkit-user-drag: none; pointer-events: none;
    }
    [data-theme="dark"] .login-logo-wrap img { mix-blend-mode: normal; filter: brightness(0) invert(1); }
    .login-logo-text { display: flex; flex-direction: column; line-height: 1.2; text-align: left; }
    .login-logo-sub  { font-size: .75rem; color: var(--muted); letter-spacing: .04em; }
    .login-logo-badge {
      display: inline-block; font-size: .6rem; font-weight: 700;
      letter-spacing: .08em; text-transform: uppercase;
      background: var(--accent); color: #fff;
      padding: 2px 8px; border-radius: 20px; margin-top: 4px; width: fit-content;
    }
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
      cursor: grab; user-select: none;
    }
    .foto-thumb:active { cursor: grabbing; }
    .foto-thumb.dragging { opacity: .35; }
    .foto-thumb.drag-over .foto-thumb-img { border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent2); }
    .foto-drag-handle {
      font-size: .65rem; color: var(--muted); letter-spacing: .04em;
      display: flex; align-items: center; gap: 3px;
    }
    .foto-thumb-img {
      position: relative; width: 90px; height: 70px;
      border-radius: var(--radius-sm); overflow: hidden;
      border: 1.5px solid var(--border); transition: border-color .2s, opacity .2s, box-shadow .2s;
    }
    .foto-thumb-img img { width: 100%; height: 100%; object-fit: cover; pointer-events: none; }
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

    .remember-wrap {
      display: flex; align-items: center; gap: 8px;
      font-size: .84rem; color: var(--muted);
      margin-bottom: 16px; cursor: pointer; user-select: none; text-align: left;
    }
    .remember-wrap input { accent-color: var(--accent); width: 16px; height: 16px; cursor: pointer; flex-shrink: 0; }

    /* LOGIN TABS */
    .login-tabs { display: flex; gap: 8px; margin-bottom: 20px; }
    .ltab {
      flex: 1; padding: 9px; border-radius: var(--radius-sm);
      border: 1.5px solid var(--border); background: var(--bg);
      color: var(--muted); font-size: .85rem; font-weight: 600;
      cursor: pointer; transition: all var(--trans);
    }
    .ltab.active { background: var(--accent); color: #fff; border-color: var(--accent); }

    /* SECTION TABS */
    .section-nav { display: flex; gap: 8px; margin-bottom: 28px; flex-wrap: wrap; }
    .snav-btn {
      padding: 8px 20px; border-radius: 20px;
      border: 1.5px solid var(--border); background: var(--surface);
      color: var(--muted); font-size: .85rem; font-weight: 600;
      cursor: pointer; text-decoration: none; transition: all var(--trans);
      display: inline-flex; align-items: center; gap: 7px;
    }
    .snav-btn:hover, .snav-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }

    /* PERMISSÕES */
    .perms-grid {
      display: grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr));
      gap: 10px; margin-top: 8px;
    }
    .perm-item {
      display: flex; align-items: center; gap: 8px;
      padding: 8px 12px; border-radius: var(--radius-sm);
      border: 1.5px solid var(--border); background: var(--bg);
      cursor: pointer; transition: all .2s; font-size: .85rem;
    }
    .perm-item:has(input:checked) { border-color: var(--accent); background: rgba(26,107,66,.08); color: var(--accent); }
    .perm-item input { accent-color: var(--accent); width: 16px; height: 16px; cursor: pointer; }

    /* IMAGENS GRID */
    .imgs-grid { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 16px; }
    .img-card {
      display: flex; flex-direction: column; align-items: center; gap: 6px;
    }
    .img-card-thumb {
      width: 110px; height: 80px; border-radius: var(--radius-sm);
      overflow: hidden; border: 1.5px solid var(--border); position: relative;
    }
    .img-card-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .img-card-thumb.sel { border-color: var(--accent); }
    .img-card input[type=checkbox] { accent-color: var(--accent); width: 15px; height: 15px; }
    .img-card-name { font-size: .65rem; color: var(--muted); max-width: 110px; word-break: break-all; text-align: center; }

    /* FILIADOS BADGE */
    .badge-ativo   { background: rgba(26,107,66,.12);  color: var(--accent); padding: 3px 10px; border-radius: 12px; font-size: .73rem; font-weight: 700; }
    .badge-inativo { background: rgba(107,114,128,.12); color: var(--muted);  padding: 3px 10px; border-radius: 12px; font-size: .73rem; font-weight: 700; }

    @media (max-width: 768px) {
      .admin-header { padding: 0 4%; height: 64px; }
      .admin-brand img { height: 188px; }
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

<?php if (!$isAuth): ?>
<!-- ════════════════ LOGIN ════════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo-wrap">
      <img src="../assets/img/logo.png?v=2" alt="FONTEC" />
      <div class="login-logo-text">
        <span class="login-logo-sub">Empreendimentos</span>
        <span class="login-logo-badge">Imóveis Rurais</span>
      </div>
    </div>
    <h2>Painel Administrativo</h2>
    <p>Fontec Empreendimentos — acesso restrito</p>
    <div class="login-tabs">
      <button class="ltab active" onclick="switchTab('admin',this)"><i class="fa fa-shield-halved"></i> Administrador</button>
      <button class="ltab"        onclick="switchTab('filiado',this)"><i class="fa fa-user"></i> Filiado</button>
    </div>
    <?php if ($loginError): ?>
      <div class="login-error"><i class="fa fa-exclamation-triangle"></i> <?= $loginError ?></div>
    <?php endif; ?>
    <div id="tab-admin">
      <form method="POST" autocomplete="on">
        <!-- campo oculto necessário para gerenciadores de senha e Face ID/Touch ID -->
        <input type="text" name="username" value="Administrador" autocomplete="username"
               style="position:absolute;opacity:0;pointer-events:none;height:0;width:0;overflow:hidden" tabindex="-1" aria-hidden="true" />
        <div class="field">
          <label for="pw">Senha de acesso</label>
          <input type="password" id="pw" name="password" placeholder="••••••••••••"
                 autocomplete="current-password" autofocus />
        </div>
        <label class="remember-wrap">
          <input type="checkbox" name="remember" value="1" />
          <span>Lembrar-me por 30 dias</span>
        </label>
        <button class="btn-primary" type="submit"><i class="fa fa-lock"></i> Entrar</button>
      </form>
    </div>
    <div id="tab-filiado" style="display:none">
      <form method="POST" autocomplete="on">
        <input type="hidden" name="filiado_login" value="1" />
        <div class="field">
          <label>E-mail</label>
          <input type="email" name="filiado_email" placeholder="seu@email.com"
                 autocomplete="email" />
        </div>
        <div class="field">
          <label>Senha</label>
          <input type="password" name="filiado_senha" placeholder="••••••••••••"
                 autocomplete="current-password" />
        </div>
        <label class="remember-wrap">
          <input type="checkbox" name="remember" value="1" />
          <span>Lembrar-me por 30 dias</span>
        </label>
        <button class="btn-primary" type="submit"><i class="fa fa-user"></i> Entrar como Filiado</button>
      </form>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ════════════════ PAINEL ════════════════ -->
<header class="admin-header">
  <a href="admin.php" class="admin-brand">
    <img src="../assets/img/logo.png?v=2" alt="Fontec Empreendimentos" />
    <div class="admin-brand-text">
      <span class="admin-brand-name">Empreendimentos</span>
      <span class="admin-brand-badge"><?= $isAdmin ? 'Painel Admin' : 'Filiado: '.htmlspecialchars($_SESSION['filiado']['nome'] ?? '') ?></span>
    </div>
  </a>
  <div class="header-right">
    <a href="index.php" class="btn-sm" target="_blank"><i class="fa fa-eye"></i> <span>Ver site</span></a>
    <form method="POST" style="display:inline">
      <button name="logout" class="btn-sm btn-danger"><i class="fa fa-sign-out-alt"></i> <span>Sair</span></button>
    </form>
  </div>
</header>

<main class="admin-main">
  <?php if ($okMsg): ?>
    <div class="alert"><i class="fa fa-check-circle"></i> <?= $okMsg ?></div>
  <?php endif; ?>

  <!-- NAVEGAÇÃO DE SEÇÕES -->
  <nav class="section-nav">
    <?php if (can('ver_imoveis') || can('cadastrar_imoveis')): ?>
    <a href="admin.php?section=imoveis" class="snav-btn <?= $section==='imoveis'?'active':'' ?>"><i class="fa fa-home"></i> Imóveis</a>
    <?php endif; ?>
    <?php if (can('gerenciar_imagens')): ?>
    <a href="admin.php?section=imagens" class="snav-btn <?= $section==='imagens'?'active':'' ?>"><i class="fa fa-images"></i> Imagens</a>
    <?php endif; ?>
    <?php if ($isAdmin || can('gerenciar_filiados')): ?>
    <a href="admin.php?section=filiados" class="snav-btn <?= $section==='filiados'?'active':'' ?>"><i class="fa fa-users"></i> Filiados</a>
    <?php endif; ?>
  </nav>

<?php if ($section === 'imoveis' && (can('ver_imoveis') || can('cadastrar_imoveis'))): ?>

  <!-- FORMULÁRIO CRIAR / EDITAR -->
  <?php if (can('cadastrar_imoveis') || ($editRow && can('editar_imoveis'))): ?>
  <div class="form-card" id="form-section">
    <h2><?= $editRow ? '<i class="fa fa-edit"></i> Editar Propriedade' : '<i class="fa fa-plus-circle"></i> Nova Propriedade' ?></h2>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save" />
      <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>" />

      <div class="form-grid">
        <div class="field">
          <label>Código</label>
          <?php if ($editRow): ?>
            <input type="text" value="<?= !empty($editRow['codigo']) ? htmlspecialchars($editRow['codigo']) : 'Será gerado ao salvar' ?>"
                   readonly style="background:var(--bg2);color:var(--muted);cursor:default" />
          <?php else: ?>
            <?php $proximoCodigo = gerarCodigo(loadFazendas()); ?>
            <input type="text" value="Gerado automaticamente — próximo: <?= $proximoCodigo ?>"
                   readonly style="background:var(--bg2);color:var(--muted);cursor:default;font-size:.82rem" />
          <?php endif; ?>
        </div>
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
          <label>Fotos atuais <small style="color:var(--muted)"> — arraste para reordenar · clique em Remover para excluir</small></label>
          <div class="fotos-preview" id="fotos-preview">
            <?php foreach ($editRow['fotos'] as $foto): ?>
              <div class="foto-thumb" id="thumb_<?= md5($foto) ?>" draggable="true">
                <div class="foto-drag-handle"><i class="fa fa-grip-dots-vertical"></i> mover</div>
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
                <input type="hidden" name="foto_order[]" value="<?= htmlspecialchars($foto) ?>" />
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- UPLOAD FOTOS -->
        <div class="field field-full">
          <label>
            <?= !empty($editRow['fotos']) ? 'Adicionar mais fotos' : 'Fotos' ?>
            <small style="color:var(--muted)"> — JPG/PNG/WebP · selecione e arraste para ordenar antes de salvar</small>
          </label>
          <input type="file" id="fotoFileInput" accept="image/jpeg,image/png,image/webp,image/gif" multiple />
          <div class="fotos-preview" id="new-fotos-preview" style="margin-top:10px;min-height:0"></div>
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
  <?php endif; /* can create/edit form */ ?>

  <!-- OVERLAY PROGRESSO -->
  <div class="upload-overlay" id="uploadOverlay">
    <div class="upload-box">
      <h3><i class="fa fa-cloud-upload-alt"></i> Enviando dados…</h3>
      <p id="progressLabel">Preparando o envio</p>
      <div class="progress-track"><div class="progress-fill" id="progressFill"></div></div>
      <div class="progress-pct" id="progressPct">0%</div>
      <div class="progress-label" id="progressSub">Aguarde, não feche esta janela</div>
    </div>
  </div>

  <!-- TABELA IMÓVEIS -->
  <?php if (can('ver_imoveis')): ?>
  <div class="admin-top" id="sec-propriedades">
    <h1>Propriedades cadastradas <small style="font-size:.8rem;font-weight:400;color:var(--muted)">(<?= count($rows) ?>)</small></h1>
    <?php if (can('cadastrar_imoveis')): ?>
    <a href="admin.php?section=imoveis#form-section" class="btn-sm"><i class="fa fa-plus"></i> Nova propriedade</a>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Código</th><th>Nome</th><th>Cidade/Estado</th><th>Tipo</th>
          <th>Preço</th><th>Fotos</th><th>Status</th><th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr class="empty-row"><td colspan="8">Nenhuma propriedade cadastrada.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><code style="font-size:.82rem;color:var(--accent);font-weight:700"><?= htmlspecialchars($r['codigo'] ?? '—') ?></code></td>
            <td><strong><?= htmlspecialchars($r['nome'] ?? '-') ?></strong></td>
            <td><?= htmlspecialchars(($r['cidade'] ?? '-') . '/' . ($r['estado'] ?? '')) ?></td>
            <td><?= htmlspecialchars($r['tipo'] ?? '-') ?></td>
            <td><?php $p=$r['preco']??''; echo $p?'R$ '.number_format((float)$p,0,',','.') :'Consultar'; ?></td>
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
                <?php if (can('editar_imoveis')): ?>
                <a href="admin.php?section=imoveis&edit=<?= urlencode($r['id']) ?>#form-section" class="btn-icon" title="Editar"><i class="fa fa-edit"></i></a>
                <?php endif; ?>
                <?php if (can('publicar_imoveis')): ?>
                <a href="admin.php?toggle=<?= urlencode($r['id']) ?>" class="btn-icon toggle" title="Alternar visibilidade"><i class="fa fa-eye"></i></a>
                <?php endif; ?>
                <?php if (can('excluir_imoveis')): ?>
                <a href="admin.php?del=<?= urlencode($r['id']) ?>" class="btn-icon del" title="Excluir"
                   onclick="return confirm('Excluir esta propriedade? Não pode ser desfeito.')"><i class="fa fa-trash"></i></a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; /* ver_imoveis */ ?>

<?php elseif ($section === 'imagens' && can('gerenciar_imagens')): ?>

  <!-- ════ GERENCIADOR DE IMAGENS ════ -->
  <div class="admin-top">
    <h1><i class="fa fa-images"></i> Imagens no servidor</h1>
  </div>
  <?php
  $allImgs = array_filter(glob(UPLOAD_DIR . '*') ?: [], function($p) {
      $n = basename($p);
      return !str_starts_with($n, 'thumb_') && in_array(strtolower(pathinfo($n, PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp','gif']);
  });
  ?>
  <?php if (empty($allImgs)): ?>
    <div class="form-card"><p style="color:var(--muted);text-align:center;padding:20px">Nenhuma imagem encontrada.</p></div>
  <?php else: ?>
  <div class="form-card">
    <form method="POST" id="formDelImgs">
      <input type="hidden" name="action" value="del_imgs" />
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <span style="font-size:.88rem;color:var(--muted)"><?= count($allImgs) ?> imagem(ns) encontrada(s)</span>
        <div style="display:flex;gap:8px">
          <button type="button" class="btn-sm" onclick="toggleSelAll()"><i class="fa fa-check-square"></i> Sel. todas</button>
          <button type="submit" class="btn-sm btn-danger" onclick="return confirm('Excluir imagens selecionadas?')"><i class="fa fa-trash"></i> Excluir selecionadas</button>
        </div>
      </div>
      <div class="imgs-grid">
        <?php foreach ($allImgs as $imgPath):
          $imgName  = basename($imgPath);
          $thumbSrc = file_exists(UPLOAD_DIR.'thumb_'.$imgName) ? 'uploads/thumb_'.$imgName : 'uploads/'.$imgName;
        ?>
        <div class="img-card">
          <label>
            <div class="img-card-thumb" id="icard_<?= md5($imgName) ?>">
              <img src="<?= htmlspecialchars($thumbSrc) ?>" alt="" loading="lazy" />
            </div>
            <input type="checkbox" name="imgs[]" value="<?= htmlspecialchars($imgName) ?>"
                   onchange="document.getElementById('icard_<?= md5($imgName) ?>').classList.toggle('sel',this.checked)" />
          </label>
          <span class="img-card-name"><?= htmlspecialchars($imgName) ?></span>
          <a href="admin.php?delimg=<?= urlencode($imgName) ?>&section=imagens" class="foto-del-btn"
             onclick="return confirm('Excluir esta imagem?')"><i class="fa fa-trash"></i> Excluir</a>
        </div>
        <?php endforeach; ?>
      </div>
    </form>
  </div>
  <?php endif; ?>

<?php elseif ($section === 'filiados' && ($isAdmin || can('gerenciar_filiados'))): ?>

  <!-- ════ FILIADOS ════ -->
  <?php if ($isAdmin || can('gerenciar_filiados')): ?>
  <div class="form-card">
    <h2><?= $editFiliado ? '<i class="fa fa-user-edit"></i> Editar Filiado' : '<i class="fa fa-user-plus"></i> Novo Filiado' ?></h2>
    <form method="POST">
      <input type="hidden" name="action" value="save_filiado" />
      <input type="hidden" name="filiado_id" value="<?= htmlspecialchars($editFiliado['id'] ?? '') ?>" />
      <div class="form-grid">
        <div class="field">
          <label>Nome completo *</label>
          <input type="text" name="filiado_nome" required value="<?= htmlspecialchars($editFiliado['nome'] ?? '') ?>" placeholder="Ex: João da Silva" />
        </div>
        <div class="field">
          <label>CPF</label>
          <input type="text" name="filiado_cpf" value="<?= htmlspecialchars($editFiliado['cpf'] ?? '') ?>" placeholder="000.000.000-00" />
        </div>
        <div class="field">
          <label>E-mail *</label>
          <input type="email" name="filiado_email" required value="<?= htmlspecialchars($editFiliado['email'] ?? '') ?>" placeholder="email@exemplo.com" />
        </div>
        <div class="field">
          <label>Telefone</label>
          <input type="text" name="filiado_telefone" value="<?= htmlspecialchars($editFiliado['telefone'] ?? '') ?>" placeholder="(62) 99999-9999" />
        </div>
        <div class="field">
          <label>Senha <?= $editFiliado ? '<small style="color:var(--muted)">(deixe em branco para manter)</small>' : '*' ?></label>
          <input type="password" name="filiado_senha" placeholder="••••••••" <?= $editFiliado ? '' : 'required' ?> />
        </div>
        <div class="field">
          <label>Status</label>
          <div class="toggle-wrap">
            <input type="checkbox" name="filiado_ativo" id="chkFiliado" <?= !empty($editFiliado['ativo']) || !$editFiliado ? 'checked' : '' ?> />
            <span>Filiado ativo</span>
          </div>
        </div>
        <div class="field field-full">
          <label>Permissões</label>
          <?php $todasPerms = array_keys(PERMS_LIST); $temTodas = !array_diff($todasPerms, $editFiliado['perms'] ?? []); ?>
          <label class="perm-item" style="margin-bottom:10px;border-color:var(--accent);background:rgba(26,107,66,.06);font-weight:700">
            <input type="checkbox" id="chkControleTotal" onchange="toggleControleTotal(this.checked)"
                   <?= $temTodas ? 'checked' : '' ?> />
            <i class="fa fa-shield-halved" style="color:var(--accent)"></i> Controle Total
          </label>
          <div class="perms-grid" id="permsGrid">
            <?php foreach (PERMS_LIST as $key => $label): ?>
            <label class="perm-item">
              <input type="checkbox" name="filiado_perms[]" value="<?= $key ?>" class="perm-cb"
                     <?= in_array($key, $editFiliado['perms'] ?? []) ? 'checked' : '' ?> />
              <?= htmlspecialchars($label) ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn-primary" type="submit" style="width:auto;padding:12px 32px">
          <i class="fa fa-save"></i> <?= $editFiliado ? 'Salvar alterações' : 'Cadastrar filiado' ?>
        </button>
        <?php if ($editFiliado): ?>
        <a href="admin.php?section=filiados" class="btn-cancel"><i class="fa fa-times"></i> Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="admin-top">
    <h1>Filiados cadastrados <small style="font-size:.8rem;font-weight:400;color:var(--muted)">(<?= count($filiados) ?>)</small></h1>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Nome</th><th>E-mail</th><th>Telefone</th><th>Permissões</th><th>Status</th><?php if($isAdmin || can('gerenciar_filiados')):?><th>Ações</th><?php endif;?></tr>
      </thead>
      <tbody>
        <?php if (empty($filiados)): ?>
          <tr class="empty-row"><td colspan="6">Nenhum filiado cadastrado.</td></tr>
        <?php else: ?>
          <?php foreach ($filiados as $f): ?>
          <tr>
            <td><strong><?= htmlspecialchars($f['nome'] ?? '-') ?></strong><br><small style="color:var(--muted)"><?= htmlspecialchars($f['cpf'] ?? '') ?></small></td>
            <td><?= htmlspecialchars($f['email'] ?? '-') ?></td>
            <td><?= htmlspecialchars($f['telefone'] ?? '-') ?></td>
            <td style="font-size:.78rem;color:var(--muted);max-width:220px">
              <?php
              $permsF = $f['perms'] ?? [];
              $todasF = array_keys(PERMS_LIST);
              if (!array_diff($todasF, $permsF)) {
                  echo '<strong style="color:var(--accent)"><i class="fa fa-shield-halved"></i> Controle Total</strong>';
              } else {
                  echo implode(', ', array_map(fn($p) => PERMS_LIST[$p] ?? $p, $permsF)) ?: '—';
              }
              ?>
            </td>
            <td>
              <?php if (!empty($f['ativo'])): ?>
                <span class="badge-ativo"><i class="fa fa-circle"></i> Ativo</span>
              <?php else: ?>
                <span class="badge-inativo"><i class="fa fa-circle"></i> Inativo</span>
              <?php endif; ?>
            </td>
            <?php if ($isAdmin || can('gerenciar_filiados')): ?>
            <td>
              <div class="td-actions">
                <a href="admin.php?section=filiados&edit_filiado=<?= urlencode($f['id']) ?>" class="btn-icon" title="Editar"><i class="fa fa-edit"></i></a>
                <a href="admin.php?del_filiado=<?= urlencode($f['id']) ?>" class="btn-icon del" title="Excluir"
                   onclick="return confirm('Excluir este filiado?')"><i class="fa fa-trash"></i></a>
              </div>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php endif; /* section */ ?>
</main>

<?php endif; ?>

<script>
  /* tema */
  const saved = localStorage.getItem('emp-theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);

  /* ── ABAS LOGIN ── */
  function switchTab(tab, btn) {
    document.getElementById('tab-admin').style.display   = tab === 'admin'   ? '' : 'none';
    document.getElementById('tab-filiado').style.display = tab === 'filiado' ? '' : 'none';
    document.querySelectorAll('.ltab').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
  }

  /* ── CONTROLE TOTAL FILIADOS ── */
  function toggleControleTotal(checked) {
    document.querySelectorAll('.perm-cb').forEach(cb => {
      cb.checked = checked;
      cb.closest('.perm-item').style.pointerEvents = checked ? 'none' : '';
      cb.closest('.perm-item').style.opacity = checked ? '.6' : '';
    });
  }
  /* aplica estado inicial ao carregar */
  (function() {
    const ct = document.getElementById('chkControleTotal');
    if (ct && ct.checked) toggleControleTotal(true);
  })();

  /* ── SELECIONAR TODAS AS IMAGENS ── */
  function toggleSelAll() {
    const cbs = document.querySelectorAll('#formDelImgs input[type=checkbox]');
    const allChecked = [...cbs].every(c => c.checked);
    cbs.forEach(c => {
      c.checked = !allChecked;
      c.dispatchEvent(new Event('change'));
    });
  }

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

    /* injeta fotos novas na ordem arrastada pelo usuário */
    const newPrev = document.getElementById('new-fotos-preview');
    if (newPrev) {
      fd.delete('fotos[]');
      newPrev.querySelectorAll('.foto-thumb').forEach(el => {
        if (el._file) fd.append('fotos[]', el._file, el._file.name);
      });
    }

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

  /* ── PREVIEW E REORDENAÇÃO DE NOVAS FOTOS ── */
  (function() {
    const input   = document.getElementById('fotoFileInput');
    const preview = document.getElementById('new-fotos-preview');
    if (!input || !preview) return;

    input.addEventListener('change', () => {
      Array.from(input.files).forEach(file => addCard(file));
      input.value = ''; // permite selecionar o mesmo arquivo novamente
    });

    function addCard(file) {
      const reader = new FileReader();
      reader.onload = ev => {
        const el = document.createElement('div');
        el.className  = 'foto-thumb';
        el.draggable  = true;
        el._file      = file;
        el.innerHTML  = `
          <div class="foto-drag-handle"><i class="fa fa-grip-dots-vertical"></i> mover</div>
          <div class="foto-thumb-img">
            <img src="${ev.target.result}" alt="" style="pointer-events:none" />
          </div>
          <button type="button" class="foto-del-btn"
            onclick="this.closest('.foto-thumb').remove()">
            <i class="fa fa-trash"></i> Remover
          </button>`;
        preview.appendChild(el);
      };
      reader.readAsDataURL(file);
    }

    let draggingNew = null;
    preview.addEventListener('dragstart', e => {
      draggingNew = e.target.closest('.foto-thumb');
      if (draggingNew) setTimeout(() => draggingNew.classList.add('dragging'), 0);
    });
    preview.addEventListener('dragend', () => {
      if (draggingNew) draggingNew.classList.remove('dragging');
      preview.querySelectorAll('.foto-thumb').forEach(el => el.classList.remove('drag-over'));
      draggingNew = null;
    });
    preview.addEventListener('dragover', e => {
      e.preventDefault();
      const target = e.target.closest('.foto-thumb');
      if (!target || target === draggingNew) return;
      preview.querySelectorAll('.foto-thumb').forEach(el => el.classList.remove('drag-over'));
      target.classList.add('drag-over');
      const rect = target.getBoundingClientRect();
      if (e.clientX < rect.left + rect.width / 2) preview.insertBefore(draggingNew, target);
      else preview.insertBefore(draggingNew, target.nextSibling);
    });
    preview.addEventListener('drop', e => e.preventDefault());
  })();

  /* ── REORDENAR FOTOS (drag-and-drop) ── */
  (function() {
    const list = document.getElementById('fotos-preview');
    if (!list) return;
    let dragging = null;

    list.addEventListener('dragstart', e => {
      dragging = e.target.closest('.foto-thumb');
      if (!dragging) return;
      setTimeout(() => dragging.classList.add('dragging'), 0);
    });
    list.addEventListener('dragend', () => {
      if (dragging) dragging.classList.remove('dragging');
      list.querySelectorAll('.foto-thumb').forEach(el => el.classList.remove('drag-over'));
      dragging = null;
    });
    list.addEventListener('dragover', e => {
      e.preventDefault();
      const target = e.target.closest('.foto-thumb');
      if (!target || target === dragging) return;
      list.querySelectorAll('.foto-thumb').forEach(el => el.classList.remove('drag-over'));
      target.classList.add('drag-over');
      const rect = target.getBoundingClientRect();
      if (e.clientX < rect.left + rect.width / 2) {
        list.insertBefore(dragging, target);
      } else {
        list.insertBefore(dragging, target.nextSibling);
      }
    });
    list.addEventListener('drop', e => e.preventDefault());
  })();

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
