<?php
session_start();
if (empty($_SESSION['admin'])) {
    header('Location: admin.php');
    exit;
}

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('JSON_FILE',  __DIR__ . '/data/fazendas.json');

function generateThumb(string $src, string $dest, int $w = 420, int $h = 280): string {
    if (!function_exists('imagecreatefromjpeg')) return 'GD não disponível';
    if (!file_exists($src)) return 'arquivo não encontrado';
    $info = @getimagesize($src);
    if (!$info) return 'não é imagem válida';
    [$srcW, $srcH, $type] = [$info[0], $info[1], $info[2]];
    $img = match($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => @imagecreatefrompng($src),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        IMAGETYPE_GIF  => @imagecreatefromgif($src),
        default        => false,
    };
    if (!$img) return 'erro ao abrir imagem';
    $ratio = max($w / $srcW, $h / $srcH);
    $cropW = (int)round($w / $ratio);
    $cropH = (int)round($h / $ratio);
    $offX  = (int)(($srcW - $cropW) / 2);
    $offY  = (int)(($srcH - $cropH) / 2);
    $thumb = imagecreatetruecolor($w, $h);
    imagecopyresampled($thumb, $img, 0, 0, $offX, $offY, $w, $h, $cropW, $cropH);
    imagejpeg($thumb, $dest, 82);
    imagedestroy($img);
    imagedestroy($thumb);
    return 'ok';
}

$raw      = file_exists(JSON_FILE) ? file_get_contents(JSON_FILE) : '[]';
$fazendas = json_decode($raw, true) ?: [];

$total = 0;
$gerados = 0;
$pulados = 0;
$erros   = [];
$log     = [];

foreach ($fazendas as $f) {
    foreach ($f['fotos'] ?? [] as $foto) {
        $total++;
        $src   = UPLOAD_DIR . $foto;
        $dest  = UPLOAD_DIR . 'thumb_' . $foto;
        if (file_exists($dest)) {
            $pulados++;
            $log[] = ['status' => 'pulado', 'file' => $foto];
            continue;
        }
        $res = generateThumb($src, $dest);
        if ($res === 'ok') {
            $gerados++;
            $log[] = ['status' => 'gerado', 'file' => $foto];
        } else {
            $erros[] = "$foto: $res";
            $log[] = ['status' => 'erro', 'file' => $foto, 'msg' => $res];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Gerar Thumbnails — Fontec Admin</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico" />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    :root { --bg:#f5f8f6; --surface:#fff; --text:#0b1f14; --muted:#4a6657; --accent:#1a6b42; --accent2:#22c55e; --border:rgba(26,107,66,.12); --radius:14px; }
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; padding:40px 5%; }
    h1 { font-family:'Syne',sans-serif; font-size:1.6rem; font-weight:800; margin-bottom:6px; }
    .subtitle { color:var(--muted); font-size:.9rem; margin-bottom:32px; }
    .stats { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:32px; }
    .stat-box {
      background:var(--surface); border:1px solid var(--border);
      border-radius:var(--radius); padding:20px 28px; text-align:center; min-width:120px;
    }
    .stat-box strong { display:block; font-family:'Syne',sans-serif; font-size:2rem; font-weight:800; }
    .stat-box.ok   strong { color:var(--accent); }
    .stat-box.skip strong { color:#d97706; }
    .stat-box.err  strong { color:#dc2626; }
    .stat-box.tot  strong { color:var(--text); }
    .stat-box span { font-size:.8rem; color:var(--muted); }
    .log-table { width:100%; border-collapse:collapse; background:var(--surface); border-radius:var(--radius); overflow:hidden; border:1px solid var(--border); }
    th { padding:12px 16px; text-align:left; font-family:'Syne',sans-serif; font-size:.75rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--muted); background:#edf2ef; border-bottom:1px solid var(--border); }
    td { padding:10px 16px; font-size:.85rem; border-bottom:1px solid var(--border); }
    tr:last-child td { border-bottom:none; }
    .badge { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:10px; font-size:.75rem; font-weight:700; }
    .badge.ok   { background:rgba(26,107,66,.1);  color:var(--accent); }
    .badge.skip { background:rgba(217,119,6,.1);  color:#d97706; }
    .badge.err  { background:rgba(220,38,38,.1);  color:#dc2626; }
    .actions { display:flex; gap:12px; margin-top:28px; flex-wrap:wrap; }
    .btn { display:inline-flex; align-items:center; gap:8px; padding:12px 24px; border-radius:10px; font-size:.9rem; font-weight:600; text-decoration:none; border:none; cursor:pointer; transition:all .2s; }
    .btn-primary { background:var(--accent); color:#fff; }
    .btn-primary:hover { background:var(--accent2); }
    .btn-outline { background:var(--surface); color:var(--muted); border:1.5px solid var(--border); }
    .btn-outline:hover { border-color:var(--accent); color:var(--accent); }
    .alert-ok  { background:rgba(26,107,66,.08);  border:1px solid rgba(26,107,66,.2);  color:var(--accent); padding:14px 18px; border-radius:10px; margin-bottom:24px; font-weight:500; }
    .alert-err { background:rgba(220,38,38,.07);  border:1px solid rgba(220,38,38,.2);  color:#dc2626;       padding:14px 18px; border-radius:10px; margin-bottom:24px; font-weight:500; }
  </style>
</head>
<body>

<h1><i class="fa fa-images"></i> Geração de Thumbnails</h1>
<p class="subtitle">Resultado do processamento de todas as fotos cadastradas</p>

<?php if ($total === 0): ?>
  <div class="alert-ok"><i class="fa fa-info-circle"></i> Nenhuma foto cadastrada nas propriedades.</div>
<?php elseif (empty($erros)): ?>
  <div class="alert-ok"><i class="fa fa-check-circle"></i> Concluído sem erros!</div>
<?php else: ?>
  <div class="alert-err"><i class="fa fa-exclamation-triangle"></i> <?= count($erros) ?> erro(s) encontrado(s). Verifique os arquivos.</div>
<?php endif; ?>

<div class="stats">
  <div class="stat-box tot"><strong><?= $total ?></strong><span>Total de fotos</span></div>
  <div class="stat-box ok"><strong><?= $gerados ?></strong><span>Thumbnails gerados</span></div>
  <div class="stat-box skip"><strong><?= $pulados ?></strong><span>Já existiam</span></div>
  <div class="stat-box err"><strong><?= count($erros) ?></strong><span>Erros</span></div>
</div>

<?php if (!empty($log)): ?>
<table class="log-table">
  <thead>
    <tr><th>Arquivo</th><th>Status</th><?php if (!empty($erros)): ?><th>Detalhe</th><?php endif; ?></tr>
  </thead>
  <tbody>
    <?php foreach ($log as $entry): ?>
    <tr>
      <td><?= htmlspecialchars($entry['file']) ?></td>
      <td>
        <?php if ($entry['status'] === 'gerado'): ?>
          <span class="badge ok"><i class="fa fa-check"></i> Gerado</span>
        <?php elseif ($entry['status'] === 'pulado'): ?>
          <span class="badge skip"><i class="fa fa-forward"></i> Já existia</span>
        <?php else: ?>
          <span class="badge err"><i class="fa fa-times"></i> Erro</span>
        <?php endif; ?>
      </td>
      <?php if (!empty($erros)): ?>
      <td><?= htmlspecialchars($entry['msg'] ?? '') ?></td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<div class="actions">
  <a href="admin.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Voltar ao painel</a>
  <a href="gerar-thumbs.php" class="btn btn-outline"><i class="fa fa-redo"></i> Executar novamente</a>
</div>

</body>
</html>
