<?php
/**
 * Partial: <head> común para todas las páginas
 * 
 * Variables esperadas:
 * - $pageTitle (opcional): Título de la página, default "Ebonia — IA Corporativa"
 * - $csrfToken: Token CSRF de la sesión
 */
$pageTitle = $pageTitle ?? 'Ebonia — IA Corporativa';
?>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  
  <!-- PWA -->
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#23AAC5">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="Ebonia">
  
  <!-- Icons -->
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/assets/icons/icon-192x192.png">
  <link rel="apple-touch-icon" sizes="152x152" href="/assets/icons/icon-152x152.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/icon-192x192.png">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
  <script>window.CSRF_TOKEN = '<?php echo $csrfToken; ?>';</script>
  
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
      --brand-primary: #23AAC5;
      --brand-dark: #115c6c;
    }
    
    /* Animated mesh gradient background */
    .bg-mesh {
      background: linear-gradient(135deg, #f0f9ff 0%, #e8f7fa 25%, #fff 50%, #f0fdf4 75%, #fefce8 100%);
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
      background: linear-gradient(135deg, #23AAC5 0%, #115c6c 100%);
    }
    .gradient-brand-btn {
      background: linear-gradient(90deg, #23AAC5 0%, #115c6c 100%);
    }
    
    /* Glow effects */
    .glow-soft { box-shadow: 0 20px 50px -15px rgba(35,170,197,0.2); }
    
    /* Smooth transitions */
    .transition-smooth { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    
    /* Card hover effects */
    .card-hover {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .card-hover:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 40px -15px rgba(35,170,197,0.25);
    }
    
    /* Floating animation */
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-8px); }
    }
    .animate-float { animation: float 6s ease-in-out infinite; }
    
    /* Pulse glow */
    @keyframes pulseGlow {
      0%, 100% { box-shadow: 0 0 20px rgba(35,170,197,0.2); }
      50% { box-shadow: 0 0 30px rgba(35,170,197,0.4); }
    }
    .animate-pulse-glow { animation: pulseGlow 2s ease-in-out infinite; }
    
    .tab-item {
      position: relative;
      transition: all 0.2s ease;
    }
    .tab-item:hover {
      background: rgba(35, 170, 197, 0.1);
    }
    .tab-item.active {
      background: rgba(35, 170, 197, 0.15);
    }
    .tab-item.active::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 3px;
      height: 32px;
      background: #23AAC5;
      border-radius: 0 2px 2px 0;
    }
    
    /* Input focus */
    .input-focus:focus {
      outline: none;
      border-color: var(--brand-primary);
      box-shadow: 0 0 0 4px rgba(35,170,197,0.15);
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
      background: #23AAC5;
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
        No tienes permiso para acceder a esta funcionalidad. Si crees que se trata de un error, contacta con <a href="mailto:it@ebone.es" class="text-[#23AAC5] font-semibold hover:underline">it@ebone.es</a>.
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
