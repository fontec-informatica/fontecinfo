<?php
session_start();

// — CONFIGURAÇÃO — altere a senha aqui
define('ADMIN_SENHA', 'Fontec@2026');
define('DATA_FILE', __DIR__ . '/data/fazendas.json');
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Login
if (isset($_POST['senha'])) {
    if ($_POST['senha'] === ADMIN_SENHA) {
        $_SESSION['admin'] = true;
    } else {
        $erro_login = 'Senha incorreta.';
    }
}
if (isset($_GET['sair'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Carrega fazendas
function carregarFazendas() {
    if (!file_exists(DATA_FILE)) return [];
    return json_decode(file_get_contents(DATA_FILE), true) ?? [];
}

function salvarFazendas($fazendas) {
    if (!is_dir(dirname(DATA_FILE))) mkdir(dirname(DATA_FILE), 0755, true);
    file_put_contents(DATA_FILE, json_encode(array_values($fazendas), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// Ações (só admin)
if (!empty($_SESSION['admin'])) {

    // Salvar/Editar fazenda
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
        $fazendas = carregarFazendas();
        $idx = isset($_POST['idx']) && $_POST['idx'] !== '' ? (int)$_POST['idx'] : null;

        $fazenda = [
            'nome'        => trim($_POST['nome'] ?? ''),
            'cidade'      => trim($_POST['cidade'] ?? ''),
            'estado'      => trim($_POST['estado'] ?? 'GO'),
            'tipo'        => $_POST['tipo'] ?? 'mista',
            'hectares'    => $_POST['hectares'] ?? '',
            'preco'       => preg_replace('/\D/', '', $_POST['preco'] ?? ''),
            'agua'        => trim($_POST['agua'] ?? ''),
            'solo'        => trim($_POST['solo'] ?? ''),
            'benfeitorias'=> trim($_POST['benfeitorias'] ?? ''),
            'acesso'      => trim($_POST['acesso'] ?? ''),
            'descricao'   => trim($_POST['descricao'] ?? ''),
            'video'       => trim($_POST['video'] ?? ''),
            'ativo'       => isset($_POST['ativo']) ? 1 : 0,
            'fotos'       => $idx !== null ? ($fazendas[$idx]['fotos'] ?? []) : [],
        ];

        // Upload de fotos
        if (!empty($_FILES['fotos']['name'][0])) {
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            foreach ($_FILES['fotos']['tmp_name'] as $i => $tmp) {
                if ($_FILES['fotos']['error'][$i] === 0) {
                    $ext = strtolower(pathinfo($_FILES['fotos']['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                        $nome = uniqid('foto_') . '.' . $ext;
                        move_uploaded_file($tmp, UPLOAD_DIR . $nome);
                        $fazenda['fotos'][] = $nome;
                    }
                }
            }
        }

        // Upload de vídeo
        if (!empty($_FILES['video_file']['tmp_name']) && $_FILES['video_file']['error'] === 0) {
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            $ext = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['mp4','mov','webm'])) {
                $nome = uniqid('video_') . '.' . $ext;
                move_uploaded_file($_FILES['video_file']['tmp_name'], UPLOAD_DIR . $nome);
                $fazenda['video'] = $nome;
            }
        }

        if ($idx !== null) {
            $fazendas[$idx] = $fazenda;
        } else {
            $fazendas[] = $fazenda;
        }
        salvarFazendas($fazendas);
        header('Location: admin.php?ok=1');
        exit;
    }

    // Excluir foto
    if (isset($_GET['excluir_foto'])) {
        $idx = (int)$_GET['fazenda'];
        $fi  = (int)$_GET['excluir_foto'];
        $fazendas = carregarFazendas();
        if (isset($fazendas[$idx]['fotos'][$fi])) {
            @unlink(UPLOAD_DIR . $fazendas[$idx]['fotos'][$fi]);
            array_splice($fazendas[$idx]['fotos'], $fi, 1);
            salvarFazendas($fazendas);
        }
        header("Location: admin.php?editar=$idx");
        exit;
    }

    // Excluir fazenda
    if (isset($_GET['excluir'])) {
        $fazendas = carregarFazendas();
        $idx = (int)$_GET['excluir'];
        if (isset($fazendas[$idx])) {
            foreach ($fazendas[$idx]['fotos'] ?? [] as $foto) @unlink(UPLOAD_DIR . $foto);
            if (!empty($fazendas[$idx]['video']) && !str_starts_with($fazendas[$idx]['video'], 'http')) {
                @unlink(UPLOAD_DIR . $fazendas[$idx]['video']);
            }
            array_splice($fazendas, $idx, 1);
            salvarFazendas($fazendas);
        }
        header('Location: admin.php?ok=2');
        exit;
    }

    // Toggle ativo
    if (isset($_GET['toggle'])) {
        $fazendas = carregarFazendas();
        $idx = (int)$_GET['toggle'];
        if (isset($fazendas[$idx])) {
            $fazendas[$idx]['ativo'] = $fazendas[$idx]['ativo'] ? 0 : 1;
            salvarFazendas($fazendas);
        }
        header('Location: admin.php');
        exit;
    }
}

$fazendas  = carregarFazendas();
$editando  = isset($_GET['editar']) ? (int)$_GET['editar'] : null;
$fazEditar = $editando !== null ? ($fazendas[$editando] ?? null) : null;
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Admin — Fontec Empreendimentos</title>
  <meta name="robots" content="noindex,nofollow"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    :root{--accent:#1a6b42;--accent2:#22c55e;--bg:#f5f8f6;--surface:#fff;--text:#0b1f14;--muted:#4a6657;--border:rgba(26,107,66,.15);--radius:12px;--shadow:0 2px 16px rgba(26,107,66,.08)}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"DM Sans",sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
    a{text-decoration:none;color:inherit}

    /* Header */
    .admin-header{background:var(--accent);color:#fff;padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between}
    .admin-header strong{font-family:"Syne",sans-serif;font-size:1.1rem}
    .admin-header a{color:rgba(255,255,255,.8);font-size:.85rem;transition:color .2s}
    .admin-header a:hover{color:#fff}

    /* Container */
    .container{max-width:1100px;margin:0 auto;padding:32px 20px}

    /* Alert */
    .alert{padding:12px 18px;border-radius:8px;margin-bottom:24px;font-size:.9rem}
    .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
    .alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}

    /* Login */
    .login-box{max-width:400px;margin:80px auto;background:var(--surface);border-radius:var(--radius);padding:40px;box-shadow:var(--shadow);text-align:center}
    .login-box h1{font-family:"Syne",sans-serif;font-size:1.6rem;margin-bottom:8px}
    .login-box p{color:var(--muted);margin-bottom:28px;font-size:.9rem}
    .form-group{margin-bottom:16px;text-align:left}
    .form-group label{display:block;font-size:.85rem;font-weight:500;color:var(--muted);margin-bottom:6px}
    .form-control{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-size:.95rem;font-family:inherit;background:var(--bg);color:var(--text);transition:border-color .2s}
    .form-control:focus{outline:none;border-color:var(--accent)}
    textarea.form-control{resize:vertical;min-height:100px}
    .btn{padding:10px 20px;border-radius:8px;font-size:.9rem;font-weight:500;cursor:pointer;border:none;transition:all .2s;display:inline-flex;align-items:center;gap:6px}
    .btn-primary{background:var(--accent);color:#fff}
    .btn-primary:hover{background:var(--accent2)}
    .btn-danger{background:#dc3545;color:#fff}
    .btn-danger:hover{background:#c82333}
    .btn-secondary{background:var(--bg);color:var(--text);border:1px solid var(--border)}
    .btn-secondary:hover{background:var(--border)}
    .btn-sm{padding:6px 12px;font-size:.8rem}
    .btn-full{width:100%;justify-content:center}

    /* Tabela */
    .card{background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:28px}
    .card-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
    .card-header h2{font-family:"Syne",sans-serif;font-size:1.1rem}
    .table{width:100%;border-collapse:collapse}
    .table th{background:var(--bg);padding:12px 16px;text-align:left;font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
    .table td{padding:12px 16px;border-top:1px solid var(--border);vertical-align:middle}
    .table tr:hover td{background:var(--bg)}
    .badge{display:inline-block;padding:3px 10px;border-radius:100px;font-size:.72rem;font-weight:600}
    .badge-ativo{background:#d4edda;color:#155724}
    .badge-inativo{background:#f8d7da;color:#721c24}
    .badge-tipo{background:rgba(26,107,66,.1);color:var(--accent)}
    .thumb{width:56px;height:40px;object-fit:cover;border-radius:6px;display:block}
    .thumb-placeholder{width:56px;height:40px;background:var(--bg);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:1.2rem}
    .actions{display:flex;gap:6px;align-items:center}

    /* Formulário */
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .form-full{grid-column:1/-1}
    .section-label{font-family:"Syne",sans-serif;font-size:.95rem;font-weight:700;color:var(--accent);margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid var(--border)}
    .foto-preview{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
    .foto-item{position:relative;width:90px;height:70px}
    .foto-item img{width:100%;height:100%;object-fit:cover;border-radius:6px}
    .foto-item .del-foto{position:absolute;top:-6px;right:-6px;width:20px;height:20px;background:#dc3545;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;cursor:pointer;text-decoration:none}
    .switch{position:relative;display:inline-block;width:44px;height:24px}
    .switch input{opacity:0;width:0;height:0}
    .slider{position:absolute;inset:0;background:#ccc;border-radius:24px;cursor:pointer;transition:.3s}
    .slider::before{content:"";position:absolute;width:18px;height:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}
    input:checked+.slider{background:var(--accent)}
    input:checked+.slider::before{transform:translateX(20px)}
    .form-row{display:flex;align-items:center;gap:10px}

    @media(max-width:640px){
      .form-grid{grid-template-columns:1fr}
      .table{font-size:.82rem}
      .table th:nth-child(3),.table td:nth-child(3){display:none}
    }
  </style>
</head>
<body>

<?php if (empty($_SESSION['admin'])): ?>
<!-- LOGIN -->
<div class="login-box">
  <h1>🔐 Admin</h1>
  <p>Fontec Empreendimentos</p>
  <?php if (!empty($erro_login)): ?>
  <div class="alert alert-error"><?= $erro_login ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label>Senha de acesso</label>
      <input type="password" name="senha" class="form-control" placeholder="••••••••" autofocus required/>
    </div>
    <button type="submit" class="btn btn-primary btn-full">Entrar</button>
  </form>
</div>

<?php else: ?>
<!-- PAINEL -->
<header class="admin-header">
  <strong>🌾 Fontec Empreendimentos — Admin</strong>
  <div style="display:flex;gap:16px;align-items:center">
    <a href="index.php" target="_blank">Ver site ↗</a>
    <a href="?sair">Sair</a>
  </div>
</header>

<div class="container">

  <?php if (isset($_GET['ok'])): ?>
  <div class="alert alert-success">
    <?= $_GET['ok'] == 1 ? '✅ Fazenda salva com sucesso!' : '🗑️ Fazenda excluída.' ?>
  </div>
  <?php endif; ?>

  <?php if ($fazEditar !== null): ?>
  <!-- FORMULÁRIO DE EDIÇÃO -->
  <div class="card">
    <div class="card-header">
      <h2><?= $editando !== null && $fazEditar ? 'Editar fazenda' : 'Nova fazenda' ?></h2>
      <a href="admin.php" class="btn btn-secondary btn-sm">← Voltar</a>
    </div>
    <div style="padding:24px">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="acao" value="salvar"/>
        <?php if ($editando !== null): ?><input type="hidden" name="idx" value="<?= $editando ?>"/><?php endif; ?>

        <p class="section-label">Informações gerais</p>
        <div class="form-grid">
          <div class="form-group">
            <label>Nome da fazenda *</label>
            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($fazEditar['nome'] ?? '') ?>" required/>
          </div>
          <div class="form-group">
            <label>Tipo</label>
            <select name="tipo" class="form-control">
              <?php foreach(['mista','pecuaria','agricultura','lazer'] as $t): ?>
              <option value="<?=$t?>" <?= ($fazEditar['tipo']??'mista')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Cidade</label>
            <input type="text" name="cidade" class="form-control" value="<?= htmlspecialchars($fazEditar['cidade'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label>Estado</label>
            <input type="text" name="estado" class="form-control" value="<?= htmlspecialchars($fazEditar['estado'] ?? 'GO') ?>"/>
          </div>
        </div>

        <p class="section-label" style="margin-top:20px">Características</p>
        <div class="form-grid">
          <div class="form-group">
            <label>Área (hectares)</label>
            <input type="text" name="hectares" class="form-control" value="<?= htmlspecialchars($fazEditar['hectares'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label>Preço (R$)</label>
            <input type="text" name="preco" class="form-control" value="<?= htmlspecialchars($fazEditar['preco'] ?? '') ?>" placeholder="Ex: 2500000"/>
          </div>
          <div class="form-group">
            <label>Água</label>
            <input type="text" name="agua" class="form-control" value="<?= htmlspecialchars($fazEditar['agua'] ?? '') ?>" placeholder="Ex: Rio, córrego, poço"/>
          </div>
          <div class="form-group">
            <label>Solo</label>
            <input type="text" name="solo" class="form-control" value="<?= htmlspecialchars($fazEditar['solo'] ?? '') ?>" placeholder="Ex: Argiloso, arenoso"/>
          </div>
          <div class="form-group">
            <label>Benfeitorias</label>
            <input type="text" name="benfeitorias" class="form-control" value="<?= htmlspecialchars($fazEditar['benfeitorias'] ?? '') ?>" placeholder="Ex: Casa sede, curral, galpão"/>
          </div>
          <div class="form-group">
            <label>Acesso</label>
            <input type="text" name="acesso" class="form-control" value="<?= htmlspecialchars($fazEditar['acesso'] ?? '') ?>" placeholder="Ex: Asfalto, estrada de terra"/>
          </div>
          <div class="form-group form-full">
            <label>Descrição completa</label>
            <textarea name="descricao" class="form-control"><?= htmlspecialchars($fazEditar['descricao'] ?? '') ?></textarea>
          </div>
        </div>

        <p class="section-label" style="margin-top:20px">Mídia</p>
        <div class="form-grid">
          <div class="form-group form-full">
            <label>Fotos (JPG, PNG, WEBP — múltiplas)</label>
            <input type="file" name="fotos[]" class="form-control" multiple accept="image/*"/>
            <?php if (!empty($fazEditar['fotos'])): ?>
            <div class="foto-preview">
              <?php foreach ($fazEditar['fotos'] as $fi => $foto): ?>
              <div class="foto-item">
                <img src="uploads/<?= htmlspecialchars($foto) ?>" alt=""/>
                <a href="?excluir_foto=<?=$fi?>&fazenda=<?=$editando?>" class="del-foto" onclick="return confirm('Excluir foto?')">✕</a>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label>Vídeo (MP4/MOV — upload direto)</label>
            <input type="file" name="video_file" class="form-control" accept="video/*"/>
            <?php if (!empty($fazEditar['video']) && !str_starts_with($fazEditar['video'],'http')): ?>
            <p style="font-size:.78rem;color:var(--muted);margin-top:6px">Atual: <?= htmlspecialchars($fazEditar['video']) ?></p>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label>Vídeo (link YouTube — opcional)</label>
            <input type="text" name="video" class="form-control" value="<?= htmlspecialchars($fazEditar['video'] ?? '') ?>" placeholder="https://youtube.com/watch?v=..."/>
          </div>
        </div>

        <div class="form-group form-row" style="margin-top:20px">
          <label class="switch">
            <input type="checkbox" name="ativo" <?= !empty($fazEditar['ativo']) ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
          <span>Publicar no site</span>
        </div>

        <div style="display:flex;gap:12px;margin-top:24px">
          <button type="submit" class="btn btn-primary">💾 Salvar fazenda</button>
          <a href="admin.php" class="btn btn-secondary">Cancelar</a>
        </div>
      </form>
    </div>
  </div>

  <?php else: ?>
  <!-- LISTAGEM -->
  <div class="card">
    <div class="card-header">
      <h2>Fazendas cadastradas (<?= count($fazendas) ?>)</h2>
      <a href="?editar=new" class="btn btn-primary btn-sm">+ Nova fazenda</a>
    </div>
    <?php if (empty($fazendas)): ?>
    <div style="padding:48px;text-align:center;color:var(--muted)">
      <p style="font-size:2rem;margin-bottom:12px">🌾</p>
      <p>Nenhuma fazenda cadastrada ainda.</p>
      <a href="?editar=new" class="btn btn-primary" style="margin-top:16px">+ Adicionar primeira fazenda</a>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table class="table">
      <thead>
        <tr>
          <th>Foto</th>
          <th>Nome</th>
          <th>Cidade/Tipo</th>
          <th>Preço</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($fazendas as $i => $f): ?>
        <tr>
          <td>
            <?php if (!empty($f['fotos'][0])): ?>
            <img src="uploads/<?= htmlspecialchars($f['fotos'][0]) ?>" class="thumb" alt=""/>
            <?php else: ?><div class="thumb-placeholder">🌾</div><?php endif; ?>
          </td>
          <td><strong><?= htmlspecialchars($f['nome'] ?? '') ?></strong><br><span style="font-size:.78rem;color:var(--muted)"><?= $f['hectares'] ?? '—' ?> ha</span></td>
          <td><?= htmlspecialchars($f['cidade'] ?? '—') ?><br><span class="badge badge-tipo"><?= htmlspecialchars(ucfirst($f['tipo'] ?? 'mista')) ?></span></td>
          <td><?= !empty($f['preco']) ? 'R$ ' . number_format($f['preco'],0,',','.') : '—' ?></td>
          <td>
            <a href="?toggle=<?=$i?>" title="Clique para alternar">
              <span class="badge <?= !empty($f['ativo']) ? 'badge-ativo' : 'badge-inativo' ?>">
                <?= !empty($f['ativo']) ? 'Publicada' : 'Oculta' ?>
              </span>
            </a>
          </td>
          <td>
            <div class="actions">
              <a href="?editar=<?=$i?>" class="btn btn-secondary btn-sm">✏️ Editar</a>
              <a href="?excluir=<?=$i?>" class="btn btn-danger btn-sm" onclick="return confirm('Excluir esta fazenda?')">🗑️</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
<?php endif; ?>
</body>
</html>
<?php
// Redireciona ?editar=new para idx vazio
if (isset($_GET['editar']) && $_GET['editar'] === 'new') {
    // Já tratado acima com $fazEditar = null quando editando=null
}
?>
