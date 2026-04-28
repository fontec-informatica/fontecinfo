<?php
$fazendas = [];
$json_file = __DIR__ . '/data/fazendas.json';
if (file_exists($json_file)) {
    $fazendas = json_decode(file_get_contents($json_file), true) ?? [];
    $fazendas = array_filter($fazendas, fn($f) => !empty($f['ativo']));
}
?><!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Fontec Empreendimentos — Fazendas e propriedades rurais exclusivas à venda em Goiás e região." />
  <meta name="keywords" content="fazendas à venda, propriedades rurais, Goiás, Fontec Empreendimentos" />
  <meta property="og:title" content="Fontec Empreendimentos — Fazendas à Venda" />
  <title>Fontec Empreendimentos — Fazendas à Venda</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet" />
  <style>
    :root {
      --bg:#f5f8f6;--bg2:#edf2ef;--surface:#ffffff;--text:#0b1f14;
      --muted:#4a6657;--accent:#1a6b42;--accent2:#22c55e;
      --border:rgba(26,107,66,.12);--shadow:0 2px 20px rgba(26,107,66,.08);
      --radius:14px;--radius-sm:8px;--trans:.35s cubic-bezier(.4,0,.2,1);
    }
    [data-theme="dark"] {
      --bg:#060d09;--bg2:#0b1610;--surface:#0f1c13;--text:#e2f0e8;
      --muted:#7aab8a;--accent:#22c55e;--accent2:#4ade80;
      --border:rgba(34,197,94,.12);--shadow:0 2px 20px rgba(0,0,0,.3);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth}
    body{font-family:"DM Sans",sans-serif;background:var(--bg);color:var(--text);line-height:1.6;transition:background var(--trans),color var(--trans)}
    img{max-width:100%;display:block;-webkit-user-drag:none;user-select:none;pointer-events:none}
    a{text-decoration:none;color:inherit}

    /* Header */
    header{position:fixed;top:0;left:0;right:0;z-index:100;padding:0 5%;height:70px;display:flex;align-items:center;justify-content:space-between;background:rgba(245,248,246,.9);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid var(--border);transition:background var(--trans)}
    [data-theme="dark"] header{background:rgba(6,13,9,.9)}
    .logo-link{display:flex;align-items:center;gap:10px}
    .logo-link img{height:44px;width:auto;mix-blend-mode:multiply;pointer-events:all}
    [data-theme="dark"] .logo-link img{mix-blend-mode:normal;filter:brightness(0) invert(1)}
    .header-badge{background:var(--accent);color:#fff;font-family:"Syne",sans-serif;font-weight:700;font-size:.7rem;letter-spacing:.08em;padding:3px 10px;border-radius:100px;text-transform:uppercase}
    .header-right{display:flex;align-items:center;gap:12px}
    .theme-toggle{width:36px;height:36px;border-radius:50%;border:1px solid var(--border);background:var(--surface);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--muted);transition:all var(--trans)}
    .theme-toggle:hover{background:var(--bg2);color:var(--accent)}
    .theme-toggle svg{width:16px;height:16px}
    .icon-moon{display:none}
    [data-theme="dark"] .icon-sun{display:none}
    [data-theme="dark"] .icon-moon{display:block}
    .btn-contato{background:var(--accent);color:#fff;padding:8px 18px;border-radius:100px;font-weight:500;font-size:.9rem;transition:all var(--trans)}
    .btn-contato:hover{background:var(--accent2);transform:translateY(-2px)}

    /* Hero */
    .hero{min-height:420px;padding:130px 5% 80px;background:linear-gradient(135deg,#e8f5ee 0%,#f5f8f6 60%,#d4eddf 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center}
    [data-theme="dark"] .hero{background:linear-gradient(135deg,#0b1f14 0%,#060d09 60%,#0d1f10 100%)}
    .hero-tag{display:inline-flex;align-items:center;gap:6px;background:rgba(26,107,66,.1);border:1px solid rgba(26,107,66,.2);color:var(--accent);padding:6px 16px;border-radius:100px;font-size:.85rem;font-weight:500;margin-bottom:20px}
    .hero h1{font-family:"Syne",sans-serif;font-weight:800;font-size:clamp(2rem,5vw,3.5rem);line-height:1.1;margin-bottom:16px}
    .hero h1 span{color:var(--accent)}
    .hero p{font-size:1.1rem;color:var(--muted);max-width:560px;margin-bottom:32px}
    .hero-stats{display:flex;gap:40px;justify-content:center;flex-wrap:wrap}
    .hero-stat strong{display:block;font-family:"Syne",sans-serif;font-weight:800;font-size:2rem;color:var(--accent)}
    .hero-stat span{font-size:.85rem;color:var(--muted)}

    /* Filtros */
    .filters{padding:32px 5% 0;display:flex;gap:12px;flex-wrap:wrap;justify-content:center}
    .filter-btn{padding:8px 20px;border-radius:100px;border:1px solid var(--border);background:var(--surface);color:var(--muted);cursor:pointer;font-size:.88rem;font-weight:500;transition:all var(--trans)}
    .filter-btn:hover,.filter-btn.active{background:var(--accent);color:#fff;border-color:var(--accent)}

    /* Grid */
    .fazendas-section{padding:48px 5% 80px}
    .section-title{font-family:"Syne",sans-serif;font-weight:800;font-size:1.8rem;margin-bottom:8px}
    .section-sub{color:var(--muted);margin-bottom:40px}
    .fazendas-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:28px}

    /* Card */
    .fazenda-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);overflow:hidden;box-shadow:var(--shadow);transition:transform var(--trans),box-shadow var(--trans)}
    .fazenda-card:hover{transform:translateY(-6px);box-shadow:0 12px 40px rgba(26,107,66,.15)}
    .card-img{position:relative;height:220px;overflow:hidden}
    .card-img img{width:100%;height:100%;object-fit:cover;transition:transform .6s ease}
    .fazenda-card:hover .card-img img{transform:scale(1.05)}
    .card-img::after{content:"© Fontec Empreendimentos";position:absolute;bottom:8px;right:8px;background:rgba(0,0,0,.55);color:#fff;font-size:.62rem;padding:3px 8px;border-radius:4px}
    .card-placeholder{width:100%;height:100%;background:var(--bg2);display:flex;align-items:center;justify-content:center;font-size:3rem}
    .card-badge{position:absolute;top:12px;left:12px;background:var(--accent);color:#fff;font-size:.72rem;font-weight:600;padding:4px 12px;border-radius:100px}
    .card-body{padding:20px}
    .card-title{font-family:"Syne",sans-serif;font-weight:700;font-size:1.15rem;margin-bottom:8px}
    .card-location{color:var(--muted);font-size:.85rem;margin-bottom:14px}
    .card-specs{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px}
    .card-spec{font-size:.82rem;color:var(--muted)}
    .card-spec strong{color:var(--text)}
    .card-price{font-family:"Syne",sans-serif;font-weight:800;font-size:1.4rem;color:var(--accent);margin-bottom:16px}
    .card-price small{font-size:.75rem;color:var(--muted);font-family:"DM Sans",sans-serif;font-weight:400}
    .card-actions{display:flex;gap:10px}
    .btn-ver{flex:1;padding:10px;border-radius:var(--radius-sm);background:var(--accent);color:#fff;text-align:center;font-weight:500;font-size:.9rem;transition:all var(--trans)}
    .btn-ver:hover{background:var(--accent2)}
    .btn-wpp{width:42px;height:42px;border-radius:var(--radius-sm);background:#25d366;color:#fff;display:flex;align-items:center;justify-content:center;transition:all var(--trans);pointer-events:all}
    .btn-wpp:hover{background:#1ebe5d}
    .btn-wpp svg{width:20px;height:20px}

    /* Empty */
    .empty-state{text-align:center;padding:80px 20px;color:var(--muted);grid-column:1/-1}
    .empty-state svg{width:64px;height:64px;margin:0 auto 16px;opacity:.4}

    /* Modal */
    .modal-overlay{display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.75);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px}
    .modal-overlay.open{display:flex}
    .modal{background:var(--surface);border-radius:var(--radius);width:100%;max-width:860px;max-height:90vh;overflow-y:auto;position:relative}
    .modal-close{position:absolute;top:16px;right:16px;z-index:10;width:36px;height:36px;border-radius:50%;background:var(--bg2);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text);font-size:1.2rem;transition:all var(--trans)}
    .modal-close:hover{background:var(--accent);color:#fff}
    .modal-gallery{position:relative;height:320px;overflow:hidden}
    .modal-gallery img{width:100%;height:100%;object-fit:cover}
    .modal-gallery::after{content:"© Fontec Empreendimentos — Conteúdo Exclusivo";position:absolute;bottom:10px;right:10px;background:rgba(0,0,0,.6);color:#fff;font-size:.7rem;padding:4px 10px;border-radius:4px}
    .gallery-nav{position:absolute;top:50%;transform:translateY(-50%);display:flex;justify-content:space-between;width:100%;padding:0 12px;pointer-events:none}
    .gallery-btn{width:36px;height:36px;border-radius:50%;background:rgba(0,0,0,.5);color:#fff;border:none;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;pointer-events:all;transition:background var(--trans)}
    .gallery-btn:hover{background:var(--accent)}
    .modal-body{padding:28px}
    .modal-title{font-family:"Syne",sans-serif;font-weight:800;font-size:1.6rem;margin-bottom:8px}
    .modal-location{color:var(--muted);margin-bottom:20px}
    .modal-specs{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin-bottom:24px}
    .modal-spec{background:var(--bg2);border-radius:var(--radius-sm);padding:12px;text-align:center}
    .modal-spec strong{display:block;font-family:"Syne",sans-serif;font-weight:700;font-size:1.1rem;color:var(--accent)}
    .modal-spec span{font-size:.78rem;color:var(--muted)}
    .modal-desc{color:var(--muted);line-height:1.8;margin-bottom:24px}
    .modal-price{font-family:"Syne",sans-serif;font-weight:800;font-size:2rem;color:var(--accent);margin-bottom:20px}
    .modal-video{margin-bottom:24px;border-radius:var(--radius-sm);overflow:hidden}
    .modal-video video{width:100%;display:block}
    .modal-video iframe{width:100%;height:340px;border:none;display:block}
    .modal-cta{display:flex;gap:12px;flex-wrap:wrap}
    .btn-primary{flex:1;min-width:180px;padding:14px 24px;border-radius:var(--radius-sm);background:var(--accent);color:#fff;text-align:center;font-weight:600;font-size:1rem;transition:all var(--trans);pointer-events:all}
    .btn-primary:hover{background:var(--accent2);transform:translateY(-2px)}
    .btn-secondary{flex:1;min-width:180px;padding:14px 24px;border-radius:var(--radius-sm);background:#25d366;color:#fff;text-align:center;font-weight:600;font-size:1rem;transition:all var(--trans);pointer-events:all}
    .btn-secondary:hover{background:#1ebe5d;transform:translateY(-2px)}

    /* Footer */
    .footer{background:var(--text);color:var(--bg);padding:40px 5%;text-align:center}
    .footer p{opacity:.7;font-size:.85rem;margin-top:8px}
    .footer strong{color:var(--accent2)}

    /* WhatsApp float */
    .wpp-float{position:fixed;bottom:24px;right:24px;z-index:150;width:56px;height:56px;border-radius:50%;background:#25d366;color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(37,211,102,.4);transition:transform var(--trans);pointer-events:all}
    .wpp-float:hover{transform:scale(1.1)}
    .wpp-float svg{width:28px;height:28px}
    .wpp-pulse{position:absolute;inset:0;border-radius:50%;background:#25d366;animation:pulse 2s infinite}
    @keyframes pulse{0%,100%{transform:scale(1);opacity:.7}70%{transform:scale(1.6);opacity:0}}

    @media(max-width:768px){
      .fazendas-grid{grid-template-columns:1fr}
      .hero{padding:110px 5% 60px}
      .hero-stats{gap:24px}
      .modal-specs{grid-template-columns:repeat(2,1fr)}
      .modal-gallery{height:220px}
    }
  </style>
</head>
<body>

<script>
  document.addEventListener('contextmenu',e=>e.preventDefault());
  document.addEventListener('keydown',e=>{
    if((e.ctrlKey||e.metaKey)&&['s','a','u','p'].includes(e.key.toLowerCase()))e.preventDefault();
    if(e.key==='F12')e.preventDefault();
  });
  document.addEventListener('selectstart',e=>e.preventDefault());
</script>

<header>
  <a href="../" class="logo-link">
    <img src="../assets/img/logo.png" alt="FONTEC" />
    <span class="header-badge">Empreendimentos</span>
  </a>
  <div class="header-right">
    <button class="theme-toggle" id="themeToggle" aria-label="Alternar tema">
      <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
    </button>
    <a href="https://wa.me/message/AVL74KZZJTWMO1" class="btn-contato" target="_blank">Fale Conosco</a>
  </div>
</header>

<section class="hero">
  <div class="hero-tag">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
    Anápolis, Goiás
  </div>
  <h1>Fazendas <span>exclusivas</span><br>à venda</h1>
  <p>Propriedades rurais selecionadas pela FONTEC com segurança e suporte completo.</p>
  <div class="hero-stats">
    <div class="hero-stat"><strong><?= count($fazendas) ?: '10' ?>+</strong><span>Propriedades</span></div>
    <div class="hero-stat"><strong>100%</strong><span>Verificadas</span></div>
    <div class="hero-stat"><strong>GO</strong><span>& região</span></div>
  </div>
</section>

<div class="filters">
  <button class="filter-btn active" data-filter="all">Todas</button>
  <button class="filter-btn" data-filter="pecuaria">Pecuária</button>
  <button class="filter-btn" data-filter="agricultura">Agricultura</button>
  <button class="filter-btn" data-filter="mista">Mista</button>
  <button class="filter-btn" data-filter="lazer">Lazer</button>
</div>

<section class="fazendas-section">
  <h2 class="section-title">Propriedades disponíveis</h2>
  <p class="section-sub">Clique em qualquer imóvel para ver todos os detalhes.</p>
  <div class="fazendas-grid" id="fazendas-grid">
    <?php if(empty($fazendas)): ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <p>Nenhuma propriedade cadastrada ainda.</p>
    </div>
    <?php else: foreach($fazendas as $i=>$f): ?>
    <div class="fazenda-card" data-tipo="<?=htmlspecialchars($f['tipo']??'mista')?>" data-id="<?=$i?>">
      <div class="card-img">
        <?php if(!empty($f['fotos'][0])): ?>
        <img src="uploads/<?=htmlspecialchars($f['fotos'][0])?>" alt="<?=htmlspecialchars($f['nome'])?>" loading="lazy" />
        <?php else: ?><div class="card-placeholder">🌾</div><?php endif; ?>
        <span class="card-badge"><?=htmlspecialchars(ucfirst($f['tipo']??'Mista'))?></span>
      </div>
      <div class="card-body">
        <h3 class="card-title"><?=htmlspecialchars($f['nome'])?></h3>
        <p class="card-location">📍 <?=htmlspecialchars($f['cidade']??'')?>, <?=htmlspecialchars($f['estado']??'GO')?></p>
        <div class="card-specs">
          <?php if(!empty($f['hectares'])): ?><span class="card-spec">🌿 <strong><?=$f['hectares']?> ha</strong></span><?php endif; ?>
          <?php if(!empty($f['agua'])): ?><span class="card-spec">💧 <strong><?=htmlspecialchars($f['agua'])?></strong></span><?php endif; ?>
          <?php if(!empty($f['solo'])): ?><span class="card-spec">🪨 <strong><?=htmlspecialchars($f['solo'])?></strong></span><?php endif; ?>
        </div>
        <div class="card-price">
          <?=!empty($f['preco'])?'R$ '.number_format($f['preco'],0,',','.'):'Consulte'?>
          <?php if(!empty($f['preco'])): ?><small> / negociável</small><?php endif; ?>
        </div>
        <div class="card-actions">
          <a href="#" class="btn-ver" onclick="abrirModal(<?=$i?>);return false;">Ver detalhes</a>
          <a href="https://wa.me/message/AVL74KZZJTWMO1?text=Olá!%20Interesse%20na%20fazenda:%20<?=urlencode($f['nome'])?>" class="btn-wpp" target="_blank" aria-label="WhatsApp">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</section>

<!-- Modal -->
<div class="modal-overlay" id="modal">
  <div class="modal">
    <button class="modal-close" onclick="fecharModal()">✕</button>
    <div class="modal-gallery" id="modal-gallery">
      <img id="modal-foto-principal" src="" alt="" />
      <div class="gallery-nav">
        <button class="gallery-btn" onclick="fotoAnterior()">‹</button>
        <button class="gallery-btn" onclick="fotoProxima()">›</button>
      </div>
    </div>
    <div class="modal-body">
      <h2 class="modal-title" id="modal-nome"></h2>
      <p class="modal-location" id="modal-loc"></p>
      <div class="modal-specs" id="modal-specs"></div>
      <p class="modal-desc" id="modal-desc"></p>
      <div class="modal-video" id="modal-video" style="display:none"></div>
      <div class="modal-price" id="modal-preco"></div>
      <div class="modal-cta">
        <a href="#" class="btn-primary" id="modal-wpp">📞 Solicitar informações</a>
        <a href="https://wa.me/message/AVL74KZZJTWMO1" class="btn-secondary" target="_blank">💬 WhatsApp</a>
      </div>
      <p style="font-size:.72rem;color:var(--muted);margin-top:16px;text-align:center;">© Fontec Empreendimentos — Conteúdo Exclusivo. Reprodução proibida.</p>
    </div>
  </div>
</div>

<a href="https://wa.me/message/AVL74KZZJTWMO1" class="wpp-float" target="_blank" aria-label="WhatsApp">
  <div class="wpp-pulse"></div>
  <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
</a>

<footer class="footer">
  <strong>FONTEC Empreendimentos</strong>
  <p>© <?= date('Y') ?> FONTEC Informática & Tecnologia. Todos os direitos reservados.</p>
  <p style="margin-top:4px;font-size:.75rem;">Anápolis, Goiás — <a href="../" style="color:var(--accent2)">fontecinfo.com</a></p>
</footer>

<script>
const fazendas = <?= json_encode(array_values($fazendas), JSON_UNESCAPED_UNICODE) ?>;
let fotoAtual = 0, fazendaAtual = null;

const html = document.documentElement;
html.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
document.getElementById('themeToggle').addEventListener('click', () => {
  const t = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', t);
  localStorage.setItem('theme', t);
});

document.querySelectorAll('.filter-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const f = btn.dataset.filter;
    document.querySelectorAll('.fazenda-card').forEach(card => {
      card.style.display = (f === 'all' || card.dataset.tipo === f) ? '' : 'none';
    });
  });
});

function abrirModal(idx) {
  fazendaAtual = fazendas[idx]; fotoAtual = 0;
  document.getElementById('modal-nome').textContent = fazendaAtual.nome || '';
  document.getElementById('modal-loc').textContent = '📍 ' + (fazendaAtual.cidade||'') + ', ' + (fazendaAtual.estado||'GO');
  document.getElementById('modal-desc').textContent = fazendaAtual.descricao || '';
  document.getElementById('modal-preco').textContent = fazendaAtual.preco ? 'R$ ' + Number(fazendaAtual.preco).toLocaleString('pt-BR') : 'Consulte o valor';
  const specs = [
    {label:'Área', value: fazendaAtual.hectares ? fazendaAtual.hectares+' ha' : null},
    {label:'Água', value: fazendaAtual.agua},
    {label:'Solo', value: fazendaAtual.solo},
    {label:'Tipo', value: fazendaAtual.tipo},
    {label:'Benfeitorias', value: fazendaAtual.benfeitorias},
    {label:'Acesso', value: fazendaAtual.acesso},
  ].filter(s=>s.value);
  document.getElementById('modal-specs').innerHTML = specs.map(s=>`<div class="modal-spec"><strong>${s.value}</strong><span>${s.label}</span></div>`).join('');
  atualizarFoto();
  const videoEl = document.getElementById('modal-video');
  if (fazendaAtual.video) {
    videoEl.style.display = '';
    if (fazendaAtual.video.includes('youtube')||fazendaAtual.video.includes('youtu.be')) {
      const vid = fazendaAtual.video.match(/(?:v=|youtu\.be\/)([^&?]+)/)?.[1];
      videoEl.innerHTML = `<iframe src="https://www.youtube.com/embed/${vid}" allowfullscreen></iframe>`;
    } else {
      videoEl.innerHTML = `<video controls><source src="uploads/${fazendaAtual.video}"></video>`;
    }
  } else { videoEl.style.display='none'; videoEl.innerHTML=''; }
  document.getElementById('modal-wpp').href = `https://wa.me/message/AVL74KZZJTWMO1?text=Olá!%20Interesse%20na%20fazenda:%20${encodeURIComponent(fazendaAtual.nome)}`;
  document.getElementById('modal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function fecharModal() {
  document.getElementById('modal').classList.remove('open');
  document.body.style.overflow = '';
  document.getElementById('modal-video').innerHTML = '';
}

function atualizarFoto() {
  const fotos = fazendaAtual?.fotos || [];
  const g = document.getElementById('modal-gallery');
  if (fotos.length > 0) {
    g.style.display = '';
    document.getElementById('modal-foto-principal').src = 'uploads/' + fotos[fotoAtual];
  } else { g.style.display = 'none'; }
}

function fotoProxima() { const f=fazendaAtual?.fotos||[]; if(f.length>1){fotoAtual=(fotoAtual+1)%f.length;atualizarFoto();} }
function fotoAnterior() { const f=fazendaAtual?.fotos||[]; if(f.length>1){fotoAtual=(fotoAtual-1+f.length)%f.length;atualizarFoto();} }

document.getElementById('modal').addEventListener('click', e => { if(e.target===document.getElementById('modal'))fecharModal(); });
</script>
</body>
</html>
