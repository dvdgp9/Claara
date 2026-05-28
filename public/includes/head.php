<?php
/**
 * Partial: shared <head> for all pages
 * 
 * Variables esperadas:
 * - $pageTitle (optional): page title, default "Claara — AI Workspace"
 * - $csrfToken: session CSRF token
 */
$pageTitle = $pageTitle ?? 'Claara — AI Workspace';
?>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  
  <!-- PWA -->
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#B7C9F2">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="Claara">
  
  <!-- Icons -->
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/assets/icons/icon-192x192.png">
  <link rel="apple-touch-icon" sizes="152x152" href="/assets/icons/icon-152x152.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/icon-192x192.png">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
  <link rel="stylesheet" href="/assets/css/styles.css">
  <script>
    window.CSRF_TOKEN = '<?php echo $csrfToken; ?>';
    // Refresh CSRF token periódicamente para evitar expiración por inactividad
    (function() {
      const REFRESH_INTERVAL = 10 * 60 * 1000; // 10 minutos
      setInterval(async () => {
        try {
          const res = await fetch('/api/auth/me.php', { credentials: 'include' });
          if (res.ok) {
            const data = await res.json();
            if (data.csrf_token) {
              window.CSRF_TOKEN = data.csrf_token;
            }
          }
        } catch (e) { /* silencioso */ }
      }, REFRESH_INTERVAL);
    })();
  </script>
  
  <!-- Service Worker Registration -->
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
          .then(reg => console.log('SW registered'))
          .catch(err => console.log('SW registration failed'));
      });
    }
  </script>
  <style>
    :root {
      --brand-primary: #B7C9F2;
      --brand-dark: #2F3440;
      --brand-coral: #FF8B73;
    }
    
    /* Animated mesh gradient background */
    .bg-mesh {
      background: linear-gradient(135deg, #F3F6FA 0%, #EFF4FF 28%, #fff 52%, #FFF6F1 76%, #F3F6FA 100%);
      background-size: 400% 400%;
      animation: meshMove 20s ease infinite;
    }
    @keyframes meshMove {
      0%, 100% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
    }
    
    /* Glassmorphism */
    .glass {
      background: rgba(255,255,255,0.7);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
    }
    .glass-strong {
      background: rgba(255,255,255,0.85);
      backdrop-filter: blur(30px);
      -webkit-backdrop-filter: blur(30px);
    }
    
    .gradient-brand {
      background: linear-gradient(135deg, #B7C9F2 0%, #FF8B73 100%);
    }
    .gradient-brand-btn {
      background: linear-gradient(90deg, #B7C9F2 0%, #FF8B73 100%);
    }
    
    /* Glow effects */
    .glow-soft { box-shadow: 0 20px 50px -15px rgba(183,201,242,0.32); }
    
    /* Smooth transitions */
    .transition-smooth { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    
    /* Card hover effects */
    .card-hover {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .card-hover:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 40px -15px rgba(183,201,242,0.35);
    }
    
    /* Floating animation */
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-8px); }
    }
    .animate-float { animation: float 6s ease-in-out infinite; }
    
    /* Pulse glow */
    @keyframes pulseGlow {
      0%, 100% { box-shadow: 0 0 20px rgba(183,201,242,0.25); }
      50% { box-shadow: 0 0 30px rgba(255,139,115,0.28); }
    }
    .animate-pulse-glow { animation: pulseGlow 2s ease-in-out infinite; }
    
    /* Modern left rail surface */
    .sidebar-rail {
      background:
        radial-gradient(120% 50% at 50% 0%, rgba(183,201,242,0.22), transparent 60%),
        radial-gradient(90% 40% at 50% 100%, rgba(255,139,115,0.10), transparent 65%),
        linear-gradient(180deg, #2F3440 0%, #202531 100%);
      position: relative;
      isolation: isolate;
    }
    .sidebar-rail::after {
      content: '';
      position: absolute;
      top: 0; right: 0; bottom: 0;
      width: 1px;
      background: linear-gradient(180deg, transparent 0%, rgba(183,201,242,0.36) 50%, transparent 100%);
      pointer-events: none;
    }

    .tab-item {
      position: relative;
      color: rgba(255,255,255,0.6);
      transition: background-color .2s ease, color .2s ease, transform .25s cubic-bezier(.16,1,.3,1);
    }
    .tab-item i { transition: transform .25s cubic-bezier(.16,1,.3,1); }
    .tab-item:hover {
      background: rgba(255,255,255,0.06);
      color: rgba(255,255,255,0.95);
    }
    .tab-item:hover i { transform: translateY(-1px); }
    .tab-item:active { transform: scale(0.97); }
    .tab-item.active {
      background: rgba(183,201,242,0.22);
      color: #ffffff;
      box-shadow:
        inset 0 1px 0 rgba(255,255,255,0.08),
        0 10px 26px -12px rgba(183,201,242,0.65);
    }
    .tab-item.active::before {
      content: '';
      position: absolute;
      left: -6px;
      top: 50%;
      transform: translateY(-50%);
      width: 3px;
      height: 22px;
      background: #FF8B73;
      border-radius: 0 3px 3px 0;
      box-shadow: 0 0 14px rgba(255,139,115,0.52);
    }
    
    /* Input focus */
    .input-focus:focus {
      outline: none;
      border-color: var(--brand-primary);
      box-shadow: 0 0 0 4px rgba(183,201,242,0.25);
    }
    
    /* Custom font sizes */
    .text-xs {
      font-size: 0.65rem !important;
    }
    .text-sm {
      font-size: 0.84rem !important;
    }
    .text-conversation {
      font-size: 15px;
    }
    
    /* Prose styling for document viewer */
    .prose {
      color: #334155;
      line-height: 1.75;
    }
    .prose h1 {
      font-size: 1.875rem;
      font-weight: 700;
      margin-top: 1.5rem;
      margin-bottom: 1rem;
      color: #0f172a;
    }
    .prose h2 {
      font-size: 1.5rem;
      font-weight: 600;
      margin-top: 1.5rem;
      margin-bottom: 0.75rem;
      color: #1e293b;
    }
    .prose h3 {
      font-size: 1.25rem;
      font-weight: 600;
      margin-top: 1.25rem;
      margin-bottom: 0.5rem;
      color: #334155;
    }
    .prose p {
      margin-bottom: 1rem;
    }
    .prose ul, .prose ol {
      margin-top: 0.75rem;
      margin-bottom: 1rem;
      padding-left: 1.5rem;
    }
    .prose li {
      margin-bottom: 0.5rem;
    }
    .prose strong {
      font-weight: 600;
      color: #0f172a;
    }
    .prose code {
      background: #f1f5f9;
      padding: 0.125rem 0.375rem;
      border-radius: 0.25rem;
      font-size: 0.875em;
      font-family: ui-monospace, monospace;
    }
    .prose blockquote {
      border-left: 4px solid #e2e8f0;
      padding-left: 1rem;
      font-style: italic;
      color: #64748b;
      margin: 1rem 0;
    }
    
    /* Tables in chat */
    .table-container {
      overflow-x: auto;
      margin: 1rem 0;
      border-radius: 0.75rem;
      border: 1px solid #e2e8f0;
    }
    table.md-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9em;
    }
    table.md-table th {
      background: #f8fafc;
      padding: 0.75rem 1rem;
      text-align: left;
      font-weight: 600;
      border-bottom: 2px solid #e2e8f0;
    }
    table.md-table td {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #f1f5f9;
    }
    table.md-table tr:last-child td {
      border-bottom: none;
    }

    /* Fenced code blocks in chat */
    .code-block {
      position: relative;
      margin: 0.85rem 0;
      border-radius: 0.75rem;
      background: #1e293b;
      border: 1px solid #334155;
      overflow: hidden;
    }
    .code-block pre {
      margin: 0;
      padding: 0.9rem 1rem;
      overflow-x: auto;
    }
    .code-block code {
      background: transparent;
      padding: 0;
      color: #e2e8f0;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size: 0.82rem;
      line-height: 1.6;
      white-space: pre;
    }
    .code-block .code-lang {
      display: block;
      padding: 0.4rem 1rem;
      font-size: 0.68rem;
      font-weight: 600;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      color: #94a3b8;
      background: rgba(148, 163, 184, 0.08);
      border-bottom: 1px solid #334155;
    }
    .code-copy-btn {
      position: absolute;
      top: 0.45rem;
      right: 0.45rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1.9rem;
      height: 1.9rem;
      border-radius: 0.5rem;
      color: #cbd5e1;
      background: rgba(15, 23, 42, 0.6);
      transition: background-color .2s ease, color .2s ease;
    }
    .code-copy-btn:hover {
      background: rgba(148, 163, 184, 0.25);
      color: #fff;
    }

    /* Scroll-to-bottom floating button (display toggled via .is-visible) */
    .scroll-bottom-btn {
      display: none;
      align-items: center;
      justify-content: center;
    }
    .scroll-bottom-btn.is-visible {
      display: flex;
    }

    /* Empty conversation state */
    .empty-shell {
      animation: emptyRise .5s cubic-bezier(.16, 1, .3, 1) both;
    }
    .empty-command {
      box-shadow: 0 28px 70px -36px rgba(47, 52, 64, 0.42);
      transition: transform .28s cubic-bezier(.16, 1, .3, 1), box-shadow .28s cubic-bezier(.16, 1, .3, 1);
    }
    .empty-command:focus-within {
      transform: translateY(-2px);
      box-shadow: 0 34px 80px -38px rgba(47, 52, 64, 0.48);
    }
    .empty-option-panel {
      transition: transform .28s cubic-bezier(.16, 1, .3, 1), border-color .28s cubic-bezier(.16, 1, .3, 1), box-shadow .28s cubic-bezier(.16, 1, .3, 1);
    }
    .empty-option-panel:hover {
      transform: translateY(-3px);
      box-shadow: 0 24px 60px -42px rgba(47, 52, 64, 0.35);
    }
    .empty-action {
      min-height: 4.35rem;
    }
    .empty-action:active {
      transform: scale(0.99);
    }
    @keyframes emptyRise {
      from { opacity: 0; transform: translateY(14px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Per-message action bar (copy / regenerate) */
    .msg-actions {
      opacity: 0;
      transition: opacity .18s ease;
    }
    .group:hover .msg-actions {
      opacity: 1;
    }
    .msg-action-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1.9rem;
      height: 1.9rem;
      border-radius: 0.5rem;
      color: #94a3b8;
      transition: background-color .2s ease, color .2s ease;
    }
    .msg-action-btn:hover {
      color: var(--brand-ink);
      background: rgba(183, 201, 242, 0.18);
    }
    .msg-action-btn:disabled {
      opacity: 0.6;
      cursor: default;
    }
    /* Touch devices have no hover: keep actions visible */
    @media (hover: none) {
      .msg-actions { opacity: 0.85; }
    }

    /* Toast animations */
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    .animate-slide-in {
      animation: slideIn 0.3s ease-out;
    }
    
    /* Streaming indicator - three dots */
    .streaming-indicator {
      display: inline-flex;
      gap: 3px;
      margin-left: 4px;
      vertical-align: middle;
    }
    .streaming-indicator span {
      width: 6px;
      height: 6px;
      background: #B7C9F2;
      border-radius: 50%;
      animation: streamPulse 1.4s ease-in-out infinite;
    }
    .streaming-indicator span:nth-child(2) { animation-delay: 0.2s; }
    .streaming-indicator span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes streamPulse {
      0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); }
      40% { opacity: 1; transform: scale(1); }
    }
  </style>

  <!-- Modal Error de Acceso -->
  <div id="access-denied-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 text-center animate-float">
      <div class="w-20 h-20 rounded-2xl bg-red-50 flex items-center justify-center mx-auto mb-6">
        <i class="iconoir-warning-triangle text-4xl text-red-500"></i>
      </div>
      <h3 class="text-2xl font-bold text-slate-900 mb-3">Acceso restringido</h3>
      <p class="text-slate-600 mb-8">
        No tienes permiso para acceder a esta funcionalidad. Si crees que se trata de un error, contacta con <a href="mailto:it@ebone.es" class="text-[#B7C9F2] font-semibold hover:underline">it@ebone.es</a>.
      </p>
      <button onclick="closeAccessModal()" class="w-full py-3.5 px-6 rounded-xl bg-slate-900 text-white font-semibold hover:bg-slate-800 transition-smooth shadow-lg">
        Entendido
      </button>
    </div>
  </div>

  <script>
    function closeAccessModal() {
      const modal = document.getElementById('access-denied-modal');
      modal.classList.add('hidden');
      // Limpiar URL sin recargar
      const url = new URL(window.location);
      url.searchParams.delete('error');
      window.history.replaceState({}, '', url);
    }

    document.addEventListener('DOMContentLoaded', () => {
      const params = new URLSearchParams(window.location.search);
      if (params.get('error') === 'no_access') {
        document.getElementById('access-denied-modal').classList.remove('hidden');
      }
    });
  </script>
</head>
