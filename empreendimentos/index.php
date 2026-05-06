<?php
$jsonFile  = __DIR__ . '/data/fazendas.json';
$uploadDir = __DIR__ . '/uploads/';
$fazendas  = [];
if (file_exists($jsonFile)) {
    $raw = file_get_contents($jsonFile);
    $all = json_decode($raw, true) ?: [];
    foreach ($all as $f) {
        if (empty($f['publicado'])) continue;
        /* resolve thumb por foto — thumb pequeno para cards, original para modal */
        $thumbs = [];
        foreach ($f['fotos'] ?? [] as $foto) {
            $thumbs[] = file_exists($uploadDir . 'thumb_' . $foto)
                ? 'thumb_' . $foto
                : $foto;
        }
        $f['thumbs'] = $thumbs;
        $fazendas[] = $f;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Fontec Empreendimentos | Fazendas e Imóveis Rurais em Goiás</title>
  <meta name="description" content="Fontec Empreendimentos — fazendas, sítios e chácaras à venda em Goiás e região. Imóveis rurais selecionados com toda a infraestrutura." />
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <style>
    :root {
      --bg:        #f5f8f6;
      --bg2:       #edf2ef;
      --surface:   #ffffff;
      --text:      #0b1f14;
      --muted:     #4a6657;
      --accent:    #1a6b42;
      --accent2:   #22c55e;
      --border:    rgba(26,107,66,.12);
      --shadow:    0 2px 20px rgba(26,107,66,.08);
      --radius:    14px;
      --radius-sm: 8px;
      --trans:     .35s cubic-bezier(.4,0,.2,1);
    }
    [data-theme="dark"] {
      --bg:      #060d09;
      --bg2:     #0b1610;
      --surface: #0f1c13;
      --text:    #e2f0e8;
      --muted:   #7aad8e;
      --accent:  #22c55e;
      --accent2: #4ade80;
      --border:  rgba(34,197,94,.1);
      --shadow:  0 2px 20px rgba(0,0,0,.4);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      transition: background var(--trans), color var(--trans);
      overflow-x: hidden;
    }
    img { max-width: 100%; display: block; -webkit-user-drag: none; user-drag: none; }
    a { text-decoration: none; color: inherit; }

    /* ── ANTI-CÓPIA GLOBAL ── */
    body, img, p, h1, h2, h3, span, div {
      -webkit-user-select: none;
      -moz-user-select: none;
      user-select: none;
    }
    @media print {
      body::before {
        content: '© Fontec Empreendimentos — Reprodução não autorizada. Lei nº 9.610/98.';
        display: block; text-align: center; padding: 40px;
        font-size: 1.2rem; font-weight: bold; color: #1a6b42;
      }
      img, .gallery, .card-thumb { display: none !important; }
    }

    /* ── HEADER ── */
    header {
      position: fixed; top: 0; left: 0; right: 0;
      z-index: 100;
      padding: 0 5%;
      height: 80px;
      display: flex; align-items: center; justify-content: space-between;
      background: rgba(245,248,246,.9);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border);
      transition: background var(--trans), border-color var(--trans);
    }
    [data-theme="dark"] header { background: rgba(6,13,9,.9); }

    .logo { display: flex; align-items: center; gap: 4px; cursor: default; }
    .logo img {
      height: 234px;
      width: auto;
      object-fit: contain;
      mix-blend-mode: multiply;
    }
    [data-theme="dark"] .logo img {
      mix-blend-mode: normal;
      filter: brightness(0) invert(1);
    }
    .logo-text { display: flex; flex-direction: column; line-height: 1.2; }
    .logo-sub { font-size: .75rem; color: var(--muted); letter-spacing: .04em; }
    .logo-badge {
      display: inline-block; font-size: .6rem; font-weight: 700;
      letter-spacing: .08em; text-transform: uppercase;
      background: var(--accent); color: #fff;
      padding: 2px 8px; border-radius: 20px;
      margin-top: 4px; width: fit-content;
    }

    .header-right { display: flex; align-items: center; gap: 10px; }
    .theme-toggle {
      width: 36px; height: 36px; border-radius: 50%;
      border: 1px solid var(--border); background: var(--surface);
      cursor: pointer; display: flex; align-items: center; justify-content: center;
      color: var(--muted); transition: all var(--trans);
    }
    .theme-toggle:hover { background: var(--bg2); color: var(--accent); }
    .theme-toggle svg { width: 16px; height: 16px; }
    .icon-moon { display: none; }
    [data-theme="dark"] .icon-sun  { display: none; }
    [data-theme="dark"] .icon-moon { display: block; }

    /* ── HERO ── */
    .hero {
      min-height: 58vh;
      padding: 130px 5% 80px;
      display: flex; align-items: center; justify-content: center;
      text-align: center;
      background: linear-gradient(160deg, var(--bg) 0%, var(--bg2) 100%);
      position: relative; overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse 70% 60% at 50% 0%, rgba(26,107,66,.09) 0%, transparent 70%);
    }
    .hero-content { position: relative; max-width: 760px; }
    .hero-tag {
      display: inline-flex; align-items: center; gap: 8px;
      font-size: .78rem; font-weight: 600; letter-spacing: .08em;
      text-transform: uppercase; color: var(--accent);
      background: rgba(26,107,66,.1); padding: 6px 16px;
      border-radius: 20px; margin-bottom: 24px;
    }
    [data-theme="dark"] .hero-tag { background: rgba(34,197,94,.1); }
    .hero h1 {
      font-family: 'Syne', sans-serif;
      font-size: clamp(2rem, 5vw, 3.2rem);
      font-weight: 800; line-height: 1.15;
      color: var(--text); margin-bottom: 20px;
    }
    .hero h1 span { color: var(--accent); }
    .hero p {
      font-size: 1.05rem; color: var(--muted);
      line-height: 1.75; max-width: 560px;
      margin: 0 auto 36px;
    }
    .hero-stats { display: flex; gap: 40px; justify-content: center; flex-wrap: wrap; }
    .hero-stat { text-align: center; }
    .hero-stat strong {
      display: block; font-family: 'Syne', sans-serif;
      font-size: 1.8rem; font-weight: 800; color: var(--accent);
    }
    .hero-stat span { font-size: .82rem; color: var(--muted); }

    /* ── FILTROS ── */
    .filters-bar {
      padding: 40px 5% 0;
      display: flex; align-items: center; gap: 12px;
      flex-wrap: wrap; justify-content: center;
    }
    .filter-btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 20px; border-radius: 24px;
      border: 1.5px solid var(--border); background: var(--surface);
      color: var(--muted); font-family: 'DM Sans', sans-serif;
      font-size: .88rem; font-weight: 500;
      cursor: pointer; transition: all var(--trans);
    }
    .filter-btn:hover, .filter-btn.active {
      background: var(--accent); border-color: var(--accent); color: #fff;
    }

    /* ── GRID ── */
    .grid-section {
      padding: 40px 5% 80px;
      max-width: 1400px; margin: 0 auto;
    }
    .grid-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 32px; flex-wrap: wrap; gap: 12px;
    }
    .grid-header h2 {
      font-family: 'Syne', sans-serif;
      font-size: 1.5rem; font-weight: 700; color: var(--text);
    }
    .grid-count { font-size: .88rem; color: var(--muted); }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 28px;
    }

    /* ── CARD ── */
    .card {
      background: var(--surface); border-radius: var(--radius);
      overflow: hidden; border: 1px solid var(--border);
      box-shadow: var(--shadow);
      transition: transform var(--trans), box-shadow var(--trans);
      cursor: pointer;
    }
    .card:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(26,107,66,.15); }

    .card-thumb {
      position: relative; width: 100%; height: 220px;
      overflow: hidden; background: var(--bg2);
      cursor: pointer;
    }
    .card-thumb img {
      width: 100%; height: 100%; object-fit: cover;
      transition: transform .5s ease;
    }
    .card:hover .card-thumb img { transform: scale(1.06); }
    .card-thumb::after {
      content: '\f065';
      font-family: 'Font Awesome 6 Free'; font-weight: 900;
      position: absolute; inset: 0;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem; color: #fff;
      background: rgba(0,0,0,.28);
      opacity: 0; transition: opacity .3s;
    }
    .card-thumb:hover::after { opacity: 1; }
    .card-thumb-placeholder {
      width: 100%; height: 100%;
      display: flex; align-items: center; justify-content: center;
      color: var(--muted); font-size: 2.5rem;
    }

    /* MARCA D'ÁGUA NOS CARDS */
    .card-thumb .wm-overlay {
      position: absolute; inset: 0;
      pointer-events: none; user-select: none;
      overflow: hidden;
    }

    .card-badge {
      position: absolute; top: 12px; left: 12px;
      background: var(--accent); color: #fff;
      font-size: .7rem; font-weight: 700; letter-spacing: .06em;
      text-transform: uppercase; padding: 4px 10px; border-radius: 12px;
      z-index: 2;
    }
    .card-count {
      position: absolute; bottom: 10px; right: 10px;
      background: rgba(0,0,0,.5); color: #fff;
      font-size: .75rem; padding: 3px 9px; border-radius: 10px;
      backdrop-filter: blur(4px); z-index: 2;
    }

    .card-body { padding: 20px; }
    .card-title {
      font-family: 'Syne', sans-serif;
      font-size: 1.1rem; font-weight: 700;
      color: var(--text); margin-bottom: 6px;
    }
    .card-location {
      font-size: .82rem; color: var(--muted);
      display: flex; align-items: center; gap: 5px;
      margin-bottom: 14px;
    }
    .card-specs { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 16px; }
    .card-spec { display: flex; align-items: center; gap: 5px; font-size: .8rem; color: var(--muted); }
    .card-spec i { color: var(--accent); font-size: .85rem; }
    .card-price {
      font-family: 'Syne', sans-serif;
      font-size: 1.25rem; font-weight: 800;
      color: var(--accent); margin-bottom: 16px;
    }
    .card-btns { display: flex; gap: 8px; }
    .card-btn {
      display: flex; align-items: center; justify-content: center; gap: 7px;
      flex: 1; padding: 11px;
      background: var(--accent); color: #fff;
      border-radius: var(--radius-sm);
      font-size: .88rem; font-weight: 600;
      transition: background var(--trans), transform var(--trans);
      border: none; cursor: pointer;
    }
    .card-btn:hover { background: var(--accent2); transform: translateY(-1px); }
    .card-btn-video {
      display: flex; align-items: center; justify-content: center; gap: 6px;
      padding: 11px 14px;
      background: var(--accent); color: #fff;
      border-radius: var(--radius-sm);
      font-size: .88rem; font-weight: 700;
      border: none; cursor: pointer;
      transition: background var(--trans), transform var(--trans);
      white-space: nowrap;
    }
    .card-btn-video:hover { background: var(--accent2); transform: translateY(-1px); }
    .card-video-badge {
      position: absolute; bottom: 10px; left: 12px;
      background: rgba(220,38,38,.92);
      color: #fff; font-size: .72rem; font-weight: 700;
      padding: 4px 10px; border-radius: 10px;
      display: flex; align-items: center; gap: 5px;
      z-index: 2; backdrop-filter: blur(4px);
    }
    /* dot de vídeo na galeria */
    .gallery-dot.is-video {
      background: rgba(26,107,66,.55);
      width: 28px; border-radius: 5px;
      font-size: .55rem; line-height: 8px;
      display: flex; align-items: center; justify-content: center;
      color: rgba(255,255,255,.8);
    }
    .gallery-dot.is-video.active { background: var(--accent); color: #fff; }
    /* botão ir ao vídeo no modal */
    .btn-goto-video {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      padding: 14px 20px;
      background: var(--accent); color: #fff;
      border-radius: var(--radius-sm);
      font-weight: 700; font-size: .95rem;
      border: none; cursor: pointer;
      transition: background var(--trans), transform var(--trans);
      white-space: nowrap;
    }
    .btn-goto-video:hover { background: var(--accent2); transform: translateY(-2px); }


    /* bloqueia barra de download do Google Drive no iframe */
    .drive-block {
      position: absolute; bottom: 0; left: 0; right: 0;
      height: 48px; z-index: 4;
      pointer-events: none;
      background: #000;
    }


    /* ── EMPTY STATE ── */
    .empty-state {
      grid-column: 1/-1; text-align: center;
      padding: 80px 20px; color: var(--muted);
    }
    .empty-state i { font-size: 3rem; margin-bottom: 16px; opacity: .4; }
    .empty-state h3 { font-family: 'Syne', sans-serif; font-size: 1.3rem; margin-bottom: 8px; color: var(--text); }

    /* ── MODAL ── */
    .modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,.8); z-index: 200;
      backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
      align-items: flex-start; justify-content: center;
      padding: 24px 20px;
      overflow-y: auto;
    }
    .modal-overlay.open { display: flex; }
    .modal {
      background: var(--surface); border-radius: var(--radius);
      width: 100%; max-width: 900px;
      position: relative; flex-shrink: 0;
      border: 1px solid var(--border);
      box-shadow: 0 30px 80px rgba(0,0,0,.35);
      margin: auto;
    }
    .modal-close {
      position: absolute; top: 14px; right: 14px;
      width: 36px; height: 36px; border-radius: 50%;
      border: 1px solid var(--border); background: var(--surface);
      color: var(--muted); cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      z-index: 10; transition: all var(--trans);
    }
    .modal-close:hover { background: var(--accent); color: #fff; border-color: var(--accent); }

    /* GALERIA */
    .gallery {
      position: relative; width: 100%; height: 400px;
      overflow: hidden;
      border-radius: var(--radius) var(--radius) 0 0;
      background: #000;
    }
    .gallery-slides { display: flex; height: 100%; transition: transform .4s ease; }
    .gallery-slide {
      min-width: 100%; height: 100%;
      object-fit: cover; flex-shrink: 0;
    }
    .gallery-slide.video-slide { min-width: 100%; height: 100%; border: none; flex-shrink: 0; cursor: default; }

    /* wrapper vídeo local com barra de progresso */
    .video-player-wrap {
      min-width: 100%; height: 100%; flex-shrink: 0;
      display: flex; flex-direction: column; background: #000;
    }
    .video-player-wrap video {
      flex: 1; width: 100%; object-fit: contain; min-height: 0;
    }
    .video-prog-bar {
      width: 100%; height: 3px; background: rgba(255,255,255,.15); flex-shrink: 0;
    }
    .video-prog-fill {
      height: 100%; width: 0%; background: #22c55e; transition: width .1s linear;
    }

    /* wrapper YouTube — bloqueia título e logo clicáveis */
    .yt-wrapper {
      position: relative; min-width: 100%; height: 100%; flex-shrink: 0;
    }
    .yt-wrapper::before {
      content: ''; position: absolute;
      top: 0; left: 0; right: 0; height: 70px;
      z-index: 2; background: transparent;
    }
    .yt-wrapper::after {
      content: ''; position: absolute;
      bottom: 0; right: 0; width: 180px; height: 44px;
      z-index: 2; background: transparent;
    }
    .yt-wrapper .video-slide { min-width: 100%; height: 100%; }

    /* MARCA D'ÁGUA NA GALERIA */
    .gallery-wm {
      position: absolute; inset: 0;
      pointer-events: none; user-select: none;
      z-index: 5; overflow: hidden;
    }

    .gallery-nav {
      position: absolute; top: 50%; transform: translateY(-50%);
      background: rgba(0,0,0,.55); color: #fff;
      border: none; cursor: pointer;
      width: 42px; height: 42px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      transition: background var(--trans); z-index: 6;
      touch-action: manipulation; /* evita zoom duplo toque */
      -webkit-tap-highlight-color: transparent;
      user-select: none;
    }
    .gallery-nav:hover { background: var(--accent); }
    .gallery-nav.prev { left: 12px; }
    .gallery-nav.next { right: 12px; }
    .gallery-slides { touch-action: pan-x; } /* scroll horizontal sem zoom */

    .gallery-dots {
      position: absolute; bottom: 14px; left: 50%; transform: translateX(-50%);
      display: flex; gap: 6px; z-index: 6;
    }
    .gallery-dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: rgba(255,255,255,.45); cursor: pointer;
      transition: background var(--trans);
    }
    .gallery-dot.active { background: #fff; }

    .modal-body { padding: 28px; }
    .modal-title {
      font-family: 'Syne', sans-serif;
      font-size: 1.6rem; font-weight: 800;
      color: var(--text); margin-bottom: 6px;
    }
    .modal-location { font-size: .9rem; color: var(--muted); display: flex; align-items: center; gap: 6px; }
    .modal-price {
      font-family: 'Syne', sans-serif;
      font-size: 2rem; font-weight: 800;
      color: var(--accent); margin: 16px 0;
    }
    .modal-specs {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 12px; margin-bottom: 24px;
    }
    .modal-spec {
      background: var(--bg2); border-radius: var(--radius-sm); padding: 12px 14px;
    }
    .modal-spec label {
      display: block; font-size: .72rem;
      text-transform: uppercase; letter-spacing: .05em;
      color: var(--muted); margin-bottom: 4px;
    }
    .modal-spec span { font-size: .95rem; font-weight: 600; color: var(--text); }
    .modal-desc { color: var(--muted); line-height: 1.8; margin-bottom: 28px; }
    .modal-actions { display: flex; gap: 12px; flex-wrap: wrap; }
    .btn-whatsapp {
      flex: 1; min-width: 200px;
      display: flex; align-items: center; justify-content: center; gap: 10px;
      padding: 14px; background: #25d366; color: #fff;
      border-radius: var(--radius-sm);
      font-weight: 700; font-size: .95rem;
      transition: background var(--trans), transform var(--trans);
    }
    .btn-whatsapp:hover { background: #128c7e; transform: translateY(-2px); }

    /* ── FOOTER ── */
    footer {
      background: var(--bg2);
      border-top: 1px solid var(--border);
      padding: 48px 5% 24px;
    }
    .footer-grid {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr;
      gap: 40px; margin-bottom: 36px;
    }
    .footer-brand img {
      height: 200px; width: auto; object-fit: contain;
      mix-blend-mode: multiply; margin-bottom: 4px;
    }
    [data-theme="dark"] .footer-brand img { mix-blend-mode: normal; filter: brightness(0) invert(1); }
    .footer-brand p { font-size: .88rem; color: var(--muted); line-height: 1.7; }
    .footer-col h4 {
      font-family: 'Syne', sans-serif;
      font-size: .9rem; font-weight: 700;
      color: var(--text); margin-bottom: 16px;
    }
    .footer-col ul { list-style: none; display: flex; flex-direction: column; gap: 8px; }
    .footer-col ul li a { font-size: .88rem; color: var(--muted); transition: color var(--trans); }
    .footer-col ul li a:hover { color: var(--accent); }
    .footer-legal {
      border-top: 1px solid var(--border); padding: 16px 0;
      font-size: .78rem; color: var(--muted); line-height: 1.6;
      display: flex; align-items: flex-start; gap: 10px;
    }
    .footer-legal i { color: var(--accent); margin-top: 2px; flex-shrink: 0; }
    .footer-bottom {
      border-top: 1px solid var(--border); padding-top: 20px;
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 8px;
      font-size: .8rem; color: var(--muted);
    }

    /* ── WHATSAPP FLOAT ── */
    /* ── SCROLL TO TOP ── */
    .scroll-top {
      position: fixed; bottom: 90px; right: 28px;
      width: 44px; height: 44px;
      background: var(--accent); color: #fff;
      border: none; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; z-index: 150;
      opacity: 0; transform: translateY(16px);
      transition: opacity .3s ease, transform .3s ease, background var(--trans);
      box-shadow: 0 4px 14px rgba(26,107,66,.35);
    }
    .scroll-top.visible { opacity: 1; transform: translateY(0); }
    .scroll-top:hover { background: var(--accent2); transform: translateY(-3px); }
    .scroll-top i { font-size: 16px; animation: arrowBounce 1.4s ease-in-out infinite; }
    @keyframes arrowBounce {
      0%, 100% { transform: translateY(0); }
      40%       { transform: translateY(-6px); }
      60%       { transform: translateY(2px); }
    }

    /* ── LOGO NÃO ARRASTÁVEL ── */
    .logo img, .footer-brand img { -webkit-user-drag: none; user-drag: none; pointer-events: none; }
    .logo, .footer-brand > div { pointer-events: auto; }

    .whatsapp-float {
      position: fixed; bottom: 24px; right: 24px;
      width: 58px; height: 58px; background: #25d366;
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      z-index: 150; box-shadow: 0 4px 20px rgba(37,211,102,.4);
      transition: transform var(--trans), box-shadow var(--trans);
    }
    .whatsapp-float::before {
      content: ''; position: absolute;
      width: 100%; height: 100%; border-radius: 50%;
      background: #25d366; animation: waPulse 2s ease-out infinite;
    }
    @keyframes waPulse {
      0%   { transform: scale(1); opacity: .6; }
      100% { transform: scale(1.8); opacity: 0; }
    }
    .whatsapp-float:hover { transform: scale(1.1); box-shadow: 0 8px 28px rgba(37,211,102,.5); }
    .whatsapp-float svg { width: 28px; height: 28px; position: relative; }

    /* ── RESPONSIVO ── */
    @media (max-width: 900px) {
      .grid { grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
    }
    @media (max-width: 768px) {
      header { height: 64px; padding: 0 4%; }
      .logo img { height: 188px; }
      .hero { padding: 100px 4% 60px; min-height: auto; }
      .hero h1 { font-size: clamp(1.6rem, 5vw, 2.4rem); }
      .hero p { font-size: .95rem; }
      .hero-stats { gap: 20px; }
      .hero-stat strong { font-size: 1.4rem; }
      .filters-bar { padding: 28px 4% 0; gap: 8px; }
      .filter-btn { padding: 7px 14px; font-size: .82rem; }
      .grid-section { padding: 28px 4% 60px; }
      .grid { grid-template-columns: 1fr 1fr; gap: 16px; }
      .footer-grid { grid-template-columns: 1fr; gap: 28px; }
      .gallery { height: 240px; }
      .modal { max-height: none; }
      .modal-body { padding: 18px; }
      .modal-specs { grid-template-columns: 1fr 1fr; }
      .modal-title { font-size: 1.3rem; }
      .modal-price { font-size: 1.5rem; }
      .hero-stats { gap: 24px; }
    }
    @media (max-width: 480px) {
      .grid { grid-template-columns: 1fr; gap: 16px; }
      .card-thumb { height: 200px; }
      .gallery { height: 200px; }
      .modal-specs { grid-template-columns: 1fr; }
      .filters-bar { gap: 6px; }
      .filter-btn { padding: 6px 12px; font-size: .78rem; }
      .hero-stats { flex-wrap: wrap; gap: 16px; }
      .hero-stat { min-width: 80px; }
      .footer-grid { gap: 20px; }
    }
  </style>
</head>
<body>

<!-- HEADER -->
<header>
  <a href="index.php" class="logo" aria-label="Fontec Empreendimentos - Início">
    <img src="../assets/img/logo.png?v=2" alt="Fontec Empreendimentos" />
    <div class="logo-text">
      <span class="logo-sub">Empreendimentos</span>
      <span class="logo-badge">Imóveis Rurais</span>
    </div>
  </a>
  <div class="header-right">
    <button class="theme-toggle" id="themeToggle" aria-label="Alternar tema">
      <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
      <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
    </button>
  </div>
</header>

<!-- HERO -->
<section class="hero">
  <div class="hero-content">
    <div class="hero-tag"><i class="fa fa-leaf"></i> Imóveis Rurais</div>
    <h1>Fazendas &amp; Propriedades<br><span>à Venda em Goiás</span></h1>
    <p>Selecione o imóvel rural ideal para seu investimento ou moradia. Propriedades verificadas com toda a documentação em ordem.</p>
    <div class="hero-stats">
      <div class="hero-stat">
        <strong><?= count($fazendas) ?></strong>
        <span>Propriedades</span>
      </div>
      <div class="hero-stat">
        <strong>GO</strong>
        <span>Estado</span>
      </div>
      <div class="hero-stat">
        <strong>100%</strong>
        <span>Verificadas</span>
      </div>
    </div>
  </div>
</section>

<!-- FILTROS -->
<div class="filters-bar">
  <button class="filter-btn active" data-filter="todos"><i class="fa fa-th-large"></i> Todos</button>
  <button class="filter-btn" data-filter="fazenda"><i class="fa fa-tractor"></i> Fazenda</button>
  <button class="filter-btn" data-filter="sitio"><i class="fa fa-tree"></i> Sítio</button>
  <button class="filter-btn" data-filter="chacara"><i class="fa fa-home"></i> Chácara</button>
  <button class="filter-btn" data-filter="terra"><i class="fa fa-seedling"></i> Terra Nua</button>
</div>

<!-- GRID -->
<section class="grid-section">
  <div class="grid-header">
    <h2>Propriedades Disponíveis</h2>
    <span class="grid-count" id="gridCount"></span>
  </div>
  <div class="grid" id="cardsGrid"></div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-grid">
    <div class="footer-brand">
      <div style="display:flex;align-items:center;gap:4px;margin-bottom:12px;">
        <img src="../assets/img/logo.png?v=2" alt="Fontec Empreendimentos" />
        <div class="logo-text">
          <span class="logo-sub">Empreendimentos</span>
          <span class="logo-badge">Imóveis Rurais</span>
        </div>
      </div>
      <p>Fontec Empreendimentos — imóveis rurais selecionados em Goiás e região. Propriedades verificadas, documentação em ordem.</p>
    </div>
    <div class="footer-col">
      <h4>Navegação</h4>
      <ul>
        <li><a href="#">Início</a></li>
        <li><a href="#" onclick="document.querySelector('.filter-btn[data-filter=fazenda]').click();return false;">Fazendas</a></li>
        <li><a href="#" onclick="document.querySelector('.filter-btn[data-filter=sitio]').click();return false;">Sítios</a></li>
        <li><a href="#" onclick="document.querySelector('.filter-btn[data-filter=chacara]').click();return false;">Chácaras</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Contato</h4>
      <ul>
        <li><a href="https://wa.me/5562994712382?text=Ol%C3%A1%2C+tenho+interesse+em+um+im%C3%B3vel+rural.+Poderia+me+passar+mais+informa%C3%A7%C3%B5es%3F" target="_blank" rel="noopener">WhatsApp</a></li>
        <li><span>Anápolis · GO</span></li>
      </ul>
    </div>
  </div>
  <div class="footer-legal">
    <i class="fa fa-shield-halved"></i>
    <span>O conteúdo desta página — incluindo textos, imagens, descrições e dados de propriedades — é de propriedade exclusiva da <strong>Fontec Empreendimentos</strong>. É vedada a reprodução, parcial ou integral, sem autorização prévia e expressa. Todos os direitos reservados conforme a <strong>Lei nº 9.610/98</strong>.</span>
  </div>
  <div class="footer-bottom">
    <span>© 2026 Fontec Empreendimentos. Todos os direitos reservados.</span>
    <span>Desenvolvido pela FONTEC</span>
  </div>
</footer>

<!-- SCROLL TO TOP -->
<button class="scroll-top" id="scrollTop" aria-label="Voltar ao topo">
  <i class="fa fa-arrow-up"></i>
</button>

<!-- WHATSAPP FLOAT -->
<a href="https://wa.me/5562994712382?text=Ol%C3%A1%2C+tenho+interesse+em+um+im%C3%B3vel+rural.+Poderia+me+passar+mais+informa%C3%A7%C3%B5es%3F" class="whatsapp-float" target="_blank" rel="noopener" aria-label="WhatsApp">
  <svg viewBox="0 0 24 24" fill="#fff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
</a>

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal" id="modal">
    <button class="modal-close" id="modalClose" aria-label="Fechar"><i class="fa fa-times"></i></button>
    <div class="gallery" id="gallery">
      <div class="gallery-slides" id="gallerySlides"></div>
      <div class="gallery-wm" id="galleryWm"></div>
      <div class="drive-block" id="driveBlock" style="display:none"></div>
      <button class="gallery-nav prev" id="galleryPrev" aria-label="Anterior"><i class="fa fa-chevron-left"></i></button>
      <button class="gallery-nav next" id="galleryNext" aria-label="Próximo"><i class="fa fa-chevron-right"></i></button>
      <div class="gallery-dots" id="galleryDots"></div>
    </div>
    <div class="modal-body">
      <div class="modal-title" id="modalTitle"></div>
      <div class="modal-location" id="modalLocation"></div>
      <div class="modal-price" id="modalPrice"></div>
      <div class="modal-specs" id="modalSpecs"></div>
      <div class="modal-desc" id="modalDesc"></div>
      <div class="modal-actions">
        <button id="btnGotoVideo" class="btn-goto-video" style="display:none" onclick="goToVideo()">
          <i class="fa fa-play-circle"></i> Assistir vídeo
        </button>
        <a id="modalWA" href="#" class="btn-whatsapp" target="_blank" rel="noopener">
          <i class="fa-brands fa-whatsapp" style="font-size:1.2rem"></i>
          Tenho interesse
        </a>
      </div>
    </div>
  </div>
</div>


<script>
/* ── DADOS ── */
const fazendas = <?= json_encode($fazendas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

/* ── TEMA ── */
const html  = document.documentElement;
const saved = localStorage.getItem('emp-theme') || 'light';
html.setAttribute('data-theme', saved);
document.getElementById('themeToggle').addEventListener('click', () => {
  const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('emp-theme', next);
});

/* ── MARCA D'ÁGUA (canvas) ── */
function buildWatermark(container, w, h) {
  const canvas = document.createElement('canvas');
  canvas.width  = w || container.offsetWidth  || 400;
  canvas.height = h || container.offsetHeight || 300;
  canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none;user-select:none;display:block;';
  const ctx = canvas.getContext('2d');
  const text = '© Fontec Empreendimentos';
  ctx.font = 'bold 13px Arial, sans-serif';
  ctx.fillStyle = 'rgba(255,255,255,0.30)';
  ctx.strokeStyle = 'rgba(0,0,0,0.15)';
  ctx.lineWidth = 0.6;
  ctx.textAlign = 'center';
  ctx.save();
  ctx.translate(canvas.width / 2, canvas.height / 2);
  ctx.rotate(-30 * Math.PI / 180);
  const stepX = 190, stepY = 75;
  const cols = Math.ceil(canvas.width  / stepX) + 3;
  const rows = Math.ceil(canvas.height / stepY) + 3;
  for (let r = -rows; r <= rows; r++) {
    for (let c = -cols; c <= cols; c++) {
      const x = c * stepX + (r % 2 === 0 ? 0 : stepX / 2);
      const y = r * stepY;
      ctx.strokeText(text, x, y);
      ctx.fillText(text, x, y);
    }
  }
  ctx.restore();
  /* NÃO sobrescreve position — container já tem position:absolute via CSS */
  container.appendChild(canvas);
}

/* ── PREÇO ── */
function fmtPrice(v) {
  if (!v) return 'Consultar';
  const n = parseFloat(v);
  if (isNaN(n)) return v;
  return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 0 });
}

/* ── RENDER CARDS ── */
function renderCards(list) {
  const grid  = document.getElementById('cardsGrid');
  const count = document.getElementById('gridCount');
  count.textContent = list.length + ' propriedade' + (list.length !== 1 ? 's' : '');

  if (list.length === 0) {
    grid.innerHTML = `<div class="empty-state">
      <i class="fa fa-map-marked-alt"></i>
      <h3>Nenhuma propriedade encontrada</h3>
      <p>Novas propriedades em breve. Entre em contato pelo WhatsApp.</p>
    </div>`;
    return;
  }

  grid.innerHTML = list.map((f, i) => {
    /* usa thumbnail (pequeno/rápido) no card, original fica para o modal */
    const thumbSrc = f.thumbs && f.thumbs.length ? f.thumbs[0] : (f.fotos && f.fotos.length ? f.fotos[0] : null);
    const priority = i < 4 ? 'fetchpriority="high"' : 'loading="lazy" decoding="async"';
    const thumbEl  = thumbSrc
      ? `<img src="uploads/${thumbSrc}" alt="${f.nome}" ${priority} />`
      : `<div class="card-thumb-placeholder"><i class="fa fa-image"></i></div>`;

    const fotoCount = f.fotos ? f.fotos.length : 0;
    const hasVideo  = !!f.video;
    const countBadge = fotoCount > 1 ? `<span class="card-count"><i class="fa fa-images"></i> ${fotoCount}${hasVideo?' + vídeo':''}</span>` : '';
    const videoBadge = '';

    const alqInfo = f.alqueires ? `<span class="card-spec"><i class="fa fa-ruler-combined"></i> ${f.alqueires} alq</span>` : '';
    const haInfo  = f.hectares  ? `<span class="card-spec"><i class="fa fa-expand-arrows-alt"></i> ${f.hectares} ha</span>` : '';

    const videoBtnCard = hasVideo
      ? `<button class="card-btn-video" onclick="openModal(${i},true)" title="Assistir vídeo"><i class="fa fa-play-circle"></i></button>`
      : '';

    return `<article class="card" data-idx="${i}" data-tipo="${(f.tipo||'').toLowerCase()}">
      <div class="card-thumb" id="cthumb_${i}" onclick="openModal(${i})">
        ${thumbEl}
        <div class="wm-overlay" id="cwm_${i}"></div>
        <span class="card-badge">${f.tipo || 'Imóvel'}</span>
        ${videoBadge}
        ${countBadge}
      </div>
      <div class="card-body">
        <div class="card-title">${f.nome}</div>
        <div class="card-location"><i class="fa fa-map-pin"></i> ${f.cidade||''}${f.estado?', '+f.estado:''}</div>
        <div class="card-specs">
          ${alqInfo}${haInfo}
          ${f.agua  ? `<span class="card-spec"><i class="fa fa-water"></i> ${f.agua}</span>`  : ''}
          ${f.solo  ? `<span class="card-spec"><i class="fa fa-leaf"></i> ${f.solo}</span>`   : ''}
        </div>
        <div class="card-price">${fmtPrice(f.preco)}</div>
        <div class="card-btns">
          <button class="card-btn" onclick="openModal(${i})">
            <i class="fa fa-expand"></i> Ver detalhes
          </button>
          ${videoBtnCard}
        </div>
      </div>
    </article>`;
  }).join('');

  /* aplica marca d'água de forma assíncrona para não travar a renderização */
  requestAnimationFrame(() => {
    list.forEach((f, i) => {
      if (f.fotos && f.fotos.length) {
        const wmEl = document.getElementById('cwm_' + i);
        if (wmEl) buildWatermark(wmEl, 320, 220);
      }
    });
  });
}

renderCards(fazendas);

/* ── FILTROS ── */
document.querySelectorAll('.filter-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const f = btn.dataset.filter;
    const filtered = f === 'todos' ? fazendas : fazendas.filter(x => (x.tipo||'').toLowerCase().includes(f));
    renderCards(filtered);
  });
});

/* ── MODAL / GALERIA ── */
let currentSlide = 0;
let slides = [];

/* ── RESOLVE URL DE VÍDEO ── */
function resolveVideoEmbed(url) {
  if (!url) return { type: 'file' };

  /* YouTube */
  if (url.includes('youtube.com') || url.includes('youtu.be')) {
    let src = url;
    const match = url.match(/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    src = match ? `https://www.youtube.com/embed/${match[1]}?rel=0&modestbranding=1&fs=0&showinfo=0&vq=hd2160&hd=1` : url;
    return { type: 'iframe', src };
  }

  /* Google Drive — converte qualquer formato para /preview */
  if (url.includes('drive.google.com')) {
    let fileId = null;
    const m1 = url.match(/\/file\/d\/([a-zA-Z0-9_-]+)/);
    const m2 = url.match(/[?&]id=([a-zA-Z0-9_-]+)/);
    if (m1) fileId = m1[1];
    else if (m2) fileId = m2[1];
    if (fileId) return { type: 'iframe', src: `https://drive.google.com/file/d/${fileId}/preview` };
  }

  /* Vimeo */
  if (url.includes('vimeo.com')) {
    const match = url.match(/vimeo\.com\/(\d+)/);
    if (match) return { type: 'iframe', src: `https://player.vimeo.com/video/${match[1]}?byline=0&portrait=0` };
  }

  /* arquivo local */
  return { type: 'file' };
}

let videoSlideIndex = -1;

function goToVideo() {
  if (videoSlideIndex >= 0) goSlide(videoSlideIndex);
  document.getElementById('gallery').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function openModal(idx, goVideo = false) {
  const f = fazendas[idx];
  if (!f) return;

  document.getElementById('modalTitle').textContent    = f.nome || '';
  document.getElementById('modalLocation').innerHTML   = `<i class="fa fa-map-pin"></i> ${f.cidade||''}${f.estado?', '+f.estado:''}`;
  document.getElementById('modalPrice').textContent    = fmtPrice(f.preco);

  const specs = [
    { label: 'Alqueires',   val: f.alqueires ? f.alqueires + ' alq goianos' : null },
    { label: 'Hectares',    val: f.hectares  ? f.hectares  + ' ha' : null },
    { label: 'Água',        val: f.agua      || null },
    { label: 'Solo',        val: f.solo      || null },
    { label: 'Acesso',      val: f.acesso    || null },
    { label: 'Benfeitorias',val: f.benfeitorias || null },
    { label: 'Tipo',        val: f.tipo      || null },
  ];
  document.getElementById('modalSpecs').innerHTML = specs
    .filter(s => s.val)
    .map(s => `<div class="modal-spec"><label>${s.label}</label><span>${s.val}</span></div>`)
    .join('');

  document.getElementById('modalDesc').textContent = f.descricao || '';

  const waMsg = encodeURIComponent(`Olá! Tenho interesse na propriedade: ${f.nome} – ${f.cidade||''}/${f.estado||''}. Poderia me passar mais informações?`);
  document.getElementById('modalWA').href = `https://wa.me/5562994712382?text=${waMsg}`;

  /* slides — fotos originais (alta qualidade) + vídeo */
  slides = [];
  videoSlideIndex = -1;
  (f.fotos || []).forEach(foto => slides.push({ type: 'img', src: 'uploads/' + foto }));
  if (f.video) {
    const embedSrc = resolveVideoEmbed(f.video);
    videoSlideIndex = slides.length;
    if (embedSrc.type === 'iframe') slides.push({ type: 'yt',    src: embedSrc.src });
    else                            slides.push({ type: 'video', src: 'uploads/' + f.video });
  }

  /* botão "Assistir vídeo" no modal */
  const btnVideo = document.getElementById('btnGotoVideo');
  btnVideo.style.display = videoSlideIndex >= 0 ? '' : 'none';

  const slidesEl = document.getElementById('gallerySlides');
  const dotsEl   = document.getElementById('galleryDots');
  const wmEl     = document.getElementById('galleryWm');

  wmEl.innerHTML = '';

  if (slides.length === 0) {
    slidesEl.innerHTML = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:3rem;"><i class="fa fa-image"></i></div>`;
    dotsEl.innerHTML = '';
    document.getElementById('galleryPrev').style.display = 'none';
    document.getElementById('galleryNext').style.display = 'none';
  } else {
    slidesEl.innerHTML = slides.map(s => {
      if (s.type === 'img')   return `<img class="gallery-slide" src="${s.src}" alt="" />`;
      if (s.type === 'yt')    return `<div class="yt-wrapper"><iframe class="gallery-slide video-slide" src="${s.src}" frameborder="0"></iframe></div>`;
      if (s.type === 'video') return `<div class="video-player-wrap"><video class="gallery-slide" src="${s.src}" autoplay muted playsinline loop></video><div class="video-prog-bar"><div class="video-prog-fill"></div></div></div>`;
    }).join('');
    const vidEl = slidesEl.querySelector('video');
    if (vidEl) {
      vidEl.addEventListener('timeupdate', () => {
        const fill = slidesEl.querySelector('.video-prog-fill');
        if (fill && vidEl.duration) fill.style.width = (vidEl.currentTime / vidEl.duration * 100) + '%';
      });
    }
    dotsEl.innerHTML = slides.map((s, i) => {
      const isVid = (s.type === 'yt' || s.type === 'video');
      const cls   = ['gallery-dot', i===0?'active':'', isVid?'is-video':''].filter(Boolean).join(' ');
      const lbl   = isVid ? '▶' : '';
      return `<span class="${cls}" data-dot="${i}" title="${isVid?'Vídeo':'Foto '+(i+1)}">${lbl}</span>`;
    }).join('');
    dotsEl.querySelectorAll('.gallery-dot').forEach(d =>
      d.addEventListener('click', () => goSlide(parseInt(d.dataset.dot)))
    );
    document.getElementById('galleryPrev').style.display = slides.length > 1 ? '' : 'none';
    document.getElementById('galleryNext').style.display = slides.length > 1 ? '' : 'none';

    /* marca d'água na galeria */
    const gal = document.getElementById('gallery');
    buildWatermark(wmEl, gal.offsetWidth, gal.offsetHeight);
  }

  document.getElementById('modalOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
  /* se clicou em "ver vídeo", vai direto ao slide de vídeo */
  const startSlide = (goVideo && videoSlideIndex >= 0) ? videoSlideIndex : 0;
  currentSlide = 0;
  goSlide(startSlide);
}

function goSlide(n) {
  currentSlide = (n + slides.length) % slides.length;
  document.getElementById('gallerySlides').style.transform = `translateX(-${currentSlide * 100}%)`;
  document.querySelectorAll('.gallery-dot').forEach((d, i) => d.classList.toggle('active', i === currentSlide));
  const vid = document.querySelector('#gallerySlides video');
  if (vid) {
    if (slides[currentSlide]?.type === 'video') vid.play().catch(() => {});
    else vid.pause();
  }
  if (typeof updateDriveBlock === 'function') updateDriveBlock();
}

function closeModal() {
  const vid = document.querySelector('#gallerySlides video');
  if (vid) vid.pause();
  const modal = document.getElementById('modal');
  modal.style.transform = '';
  modal.style.transition = '';
  document.getElementById('modalOverlay').classList.remove('open');
  document.body.style.overflow = '';
  document.getElementById('gallerySlides').innerHTML = '';
  document.getElementById('galleryWm').innerHTML = '';
}

document.getElementById('modalClose').addEventListener('click', closeModal);
document.getElementById('modalOverlay').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });
document.getElementById('galleryPrev').addEventListener('click', () => goSlide(currentSlide - 1));
document.getElementById('galleryNext').addEventListener('click', () => goSlide(currentSlide + 1));


/* mostra bloqueio da barra do Google Drive */
function updateDriveBlock() {
  const isDrive = slides[currentSlide] && slides[currentSlide].src && slides[currentSlide].src.includes('drive.google.com');
  document.getElementById('driveBlock').style.display = isDrive ? '' : 'none';
}

document.addEventListener('keydown', e => {
  if (!document.getElementById('modalOverlay').classList.contains('open')) return;
  if (e.key === 'Escape')      closeModal();
  if (e.key === 'ArrowLeft')   goSlide(currentSlide - 1);
  if (e.key === 'ArrowRight')  goSlide(currentSlide + 1);
});

/* ── SCROLL TO TOP ── */
const scrollTopBtn = document.getElementById('scrollTop');
window.addEventListener('scroll', () => {
  scrollTopBtn.classList.toggle('visible', window.scrollY > 400);
}, { passive: true });
scrollTopBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

/* ── ANTI-CÓPIA ── */
document.addEventListener('contextmenu',  e => e.preventDefault());
document.addEventListener('selectstart',  e => e.preventDefault());
document.addEventListener('dragstart',    e => e.preventDefault());
document.addEventListener('copy',         e => e.preventDefault());
document.addEventListener('cut',          e => e.preventDefault());
document.addEventListener('keydown', e => {
  const k = e.key.toLowerCase();
  if (e.key === 'F12') { e.preventDefault(); return false; }
  /* Ctrl/Cmd: U=view-source, S=save, P=print, A=select-all, C=copy, X=cut */
  if ((e.ctrlKey || e.metaKey) && ['u','s','p','a','c','x'].includes(k)) { e.preventDefault(); return false; }
  /* Ctrl+Shift: I=devtools, J=console, C=inspector, K=console (Firefox) */
  if ((e.ctrlKey || e.metaKey) && e.shiftKey && ['i','j','c','k'].includes(k)) { e.preventDefault(); return false; }
});
</script>
</body>
</html>
