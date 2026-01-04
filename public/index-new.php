<?php
require_once __DIR__ . '/../src/App/bootstrap.php';

use App\Session;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}
$csrfToken = $_SESSION['csrf_token'] ?? '';
$userName = htmlspecialchars($user['first_name'] ?? 'Usuario');
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Ebonia — Tu asistente inteligente</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
  <script>window.CSRF_TOKEN = '<?php echo $csrfToken; ?>';</script>
  <style>
    :root {
      --brand-primary: #23AAC5;
      --brand-dark: #115c6c;
      --brand-light: #e8f7fa;
    }
    
    /* Animated gradient background */
    .bg-mesh {
      background: linear-gradient(135deg, #f0f9ff 0%, #e8f7fa 25%, #fff 50%, #f0fdf4 75%, #fefce8 100%);
      background-size: 400% 400%;
      animation: meshMove 15s ease infinite;
    }
    @keyframes meshMove {
      0%, 100% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
    }
    
    /* Glass effect */
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
    
    /* Brand gradients */
    .gradient-brand { background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-dark) 100%); }
    .gradient-brand-soft { background: linear-gradient(135deg, rgba(35,170,197,0.1) 0%, rgba(17,92,108,0.05) 100%); }
    
    /* Glow effects */
    .glow-brand { box-shadow: 0 0 40px rgba(35,170,197,0.3); }
    .glow-soft { box-shadow: 0 20px 60px -15px rgba(0,0,0,0.1); }
    
    /* Smooth transitions */
    .transition-smooth { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    
    /* Action cards */
    .action-card {
      position: relative;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .action-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      background: linear-gradient(135deg, rgba(35,170,197,0.05) 0%, transparent 50%);
      opacity: 0;
      transition: opacity 0.3s;
    }
    .action-card:hover::before { opacity: 1; }
    .action-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -15px rgba(35,170,197,0.25); }
    
    /* Floating animation */
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
    .animate-float { animation: float 6s ease-in-out infinite; }
    
    /* Pulse glow */
    @keyframes pulseGlow {
      0%, 100% { box-shadow: 0 0 20px rgba(35,170,197,0.3); }
      50% { box-shadow: 0 0 40px rgba(35,170,197,0.5); }
    }
    .animate-pulse-glow { animation: pulseGlow 2s ease-in-out infinite; }
    
    /* Custom scrollbar */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    /* Hide scrollbar but keep functionality */
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    
    /* Navigation pill */
    .nav-pill {
      position: relative;
      padding: 12px 16px;
      border-radius: 12px;
      transition: all 0.2s;
    }
    .nav-pill:hover { background: rgba(35,170,197,0.08); }
    .nav-pill.active { background: rgba(35,170,197,0.12); color: var(--brand-primary); }
    .nav-pill.active::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 3px;
      height: 24px;
      background: var(--brand-primary);
      border-radius: 0 3px 3px 0;
    }
    
    /* Input focus ring */
    .input-focus:focus {
      outline: none;
      border-color: var(--brand-primary);
      box-shadow: 0 0 0 4px rgba(35,170,197,0.15);
    }
    
    /* Text sizes adjusted for clarity */
    .text-display { font-size: 2.5rem; line-height: 1.2; font-weight: 700; }
    .text-title { font-size: 1.5rem; line-height: 1.3; font-weight: 600; }
    .text-subtitle { font-size: 1.125rem; line-height: 1.5; font-weight: 500; }
    .text-body { font-size: 1rem; line-height: 1.6; }
    .text-small { font-size: 0.875rem; line-height: 1.5; }
    .text-tiny { font-size: 0.75rem; line-height: 1.4; }
  </style>
</head>
<body class="bg-mesh min-h-screen text-slate-800 antialiased">
  
  <!-- Main Layout -->
  <div class="flex h-screen overflow-hidden">
    
    <!-- Left Navigation - Minimal & Clear -->
    <nav class="w-20 glass-strong border-r border-slate-200/50 flex flex-col items-center py-6 gap-2">
      <!-- Logo -->
      <div class="w-12 h-12 rounded-2xl gradient-brand flex items-center justify-center text-white font-bold text-xl shadow-lg mb-6">
        E
      </div>
      
      <!-- Nav Items -->
      <div class="flex-1 flex flex-col gap-1 w-full px-3">
        <button data-nav="home" class="nav-pill active flex flex-col items-center gap-1 w-full" title="Inicio">
          <i class="iconoir-home text-xl"></i>
          <span class="text-[10px] font-medium">Inicio</span>
        </button>
        
        <button data-nav="chat" class="nav-pill flex flex-col items-center gap-1 w-full" title="Conversaciones">
          <i class="iconoir-chat-bubble text-xl"></i>
          <span class="text-[10px] font-medium">Chat</span>
        </button>
        
        <button data-nav="write" class="nav-pill flex flex-col items-center gap-1 w-full" title="Escribir contenido">
          <i class="iconoir-edit-pencil text-xl"></i>
          <span class="text-[10px] font-medium">Escribir</span>
        </button>
      </div>
      
      <!-- User Menu -->
      <div class="mt-auto pt-4 border-t border-slate-200/50 w-full px-3">
        <button id="user-menu-btn" class="nav-pill flex flex-col items-center gap-1 w-full">
          <div class="w-8 h-8 rounded-full gradient-brand flex items-center justify-center text-white text-xs font-semibold" id="user-avatar">
            <?php echo strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1)); ?>
          </div>
          <span class="text-[10px] font-medium text-slate-500">Cuenta</span>
        </button>
      </div>
    </nav>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-hidden flex flex-col">
      
      <!-- ===== HOME VIEW (Default) ===== -->
      <section id="view-home" class="flex-1 overflow-auto p-8">
        <div class="max-w-5xl mx-auto">
          
          <!-- Welcome Hero -->
          <div class="text-center mb-12 pt-8">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/80 border border-slate-200/50 shadow-sm mb-6">
              <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
              <span class="text-small text-slate-600">Todo listo para ayudarte</span>
            </div>
            
            <h1 class="text-display text-slate-900 mb-4">
              Hola, <span class="text-transparent bg-clip-text gradient-brand"><?php echo $userName; ?></span> 👋
            </h1>
            
            <p class="text-subtitle text-slate-500 max-w-xl mx-auto">
              Soy Ebonia, tu asistente de inteligencia artificial. 
              ¿En qué te puedo ayudar hoy?
            </p>
          </div>
          
          <!-- Quick Actions - Big & Clear -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
            
            <!-- Chat Action -->
            <button data-action="start-chat" class="action-card glass-strong rounded-3xl p-8 border border-slate-200/50 text-left group">
              <div class="flex items-start gap-5">
                <div class="w-16 h-16 rounded-2xl gradient-brand flex items-center justify-center text-white shadow-lg group-hover:animate-pulse-glow transition-smooth">
                  <i class="iconoir-chat-bubble-empty text-3xl"></i>
                </div>
                <div class="flex-1">
                  <h3 class="text-title text-slate-900 mb-2 group-hover:text-[#115c6c] transition-smooth">
                    Iniciar conversación
                  </h3>
                  <p class="text-body text-slate-500 mb-4">
                    Pregúntame lo que necesites. Puedo ayudarte con información, análisis, ideas y mucho más.
                  </p>
                  <div class="flex items-center gap-2 text-[#23AAC5] font-medium">
                    <span>Empezar a chatear</span>
                    <i class="iconoir-arrow-right group-hover:translate-x-1 transition-smooth"></i>
                  </div>
                </div>
              </div>
            </button>
            
            <!-- Write Action -->
            <a href="/gestures/write-article.php" class="action-card glass-strong rounded-3xl p-8 border border-slate-200/50 text-left group block">
              <div class="flex items-start gap-5">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center text-white shadow-lg group-hover:animate-pulse-glow transition-smooth">
                  <i class="iconoir-page-edit text-3xl"></i>
                </div>
                <div class="flex-1">
                  <h3 class="text-title text-slate-900 mb-2 group-hover:text-purple-700 transition-smooth">
                    Escribir contenido
                  </h3>
                  <p class="text-body text-slate-500 mb-4">
                    Genera artículos, posts de blog o notas de prensa profesionales en segundos.
                  </p>
                  <div class="flex items-center gap-2 text-purple-600 font-medium">
                    <span>Crear contenido</span>
                    <i class="iconoir-arrow-right group-hover:translate-x-1 transition-smooth"></i>
                  </div>
                </div>
              </div>
            </a>
            
          </div>
          
          <!-- Quick Input -->
          <div class="glass-strong rounded-3xl p-6 border border-slate-200/50 shadow-lg max-w-3xl mx-auto mb-12">
            <form id="quick-chat-form" class="flex gap-4">
              <div class="flex-1 relative">
                <input 
                  type="text" 
                  id="quick-chat-input"
                  class="w-full px-6 py-4 pr-14 rounded-2xl border-2 border-slate-200 text-body input-focus bg-white/50"
                  placeholder="Escribe tu pregunta aquí..."
                />
                <button type="button" id="quick-attach-btn" class="absolute right-4 top-1/2 -translate-y-1/2 p-2 text-slate-400 hover:text-[#23AAC5] rounded-lg transition-smooth" title="Adjuntar archivo">
                  <i class="iconoir-attachment text-xl"></i>
                </button>
              </div>
              <button type="submit" class="px-8 py-4 gradient-brand text-white font-semibold rounded-2xl shadow-lg hover:shadow-xl hover:scale-105 transition-smooth flex items-center gap-3">
                <span>Enviar</span>
                <i class="iconoir-send-diagonal text-xl"></i>
              </button>
            </form>
            <input type="file" id="quick-file-input" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp" />
            <div id="quick-file-preview" class="hidden mt-4 p-3 bg-slate-50 rounded-xl flex items-center gap-3">
              <i id="quick-file-icon" class="iconoir-page text-xl text-[#23AAC5]"></i>
              <span id="quick-file-name" class="flex-1 text-small truncate"></span>
              <button type="button" id="quick-file-remove" class="p-1 text-slate-400 hover:text-red-500 rounded">
                <i class="iconoir-xmark"></i>
              </button>
            </div>
          </div>
          
          <!-- Recent Conversations -->
          <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-title text-slate-900">Conversaciones recientes</h2>
              <button data-action="view-all-chats" class="text-small text-[#23AAC5] hover:underline font-medium flex items-center gap-1">
                Ver todas <i class="iconoir-arrow-right"></i>
              </button>
            </div>
            
            <div id="recent-conversations" class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Conversations will be loaded here -->
              <div class="animate-pulse">
                <div class="glass rounded-2xl p-5 border border-slate-200/50">
                  <div class="h-4 bg-slate-200 rounded w-3/4 mb-3"></div>
                  <div class="h-3 bg-slate-100 rounded w-1/2"></div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Help Tips -->
          <div class="glass rounded-2xl p-6 border border-slate-200/50">
            <div class="flex items-start gap-4">
              <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0">
                <i class="iconoir-light-bulb text-xl text-amber-600"></i>
              </div>
              <div>
                <h3 class="font-semibold text-slate-800 mb-1">💡 Consejo</h3>
                <p class="text-small text-slate-600">
                  Puedes adjuntar PDFs o imágenes a tus mensajes para que los analice. 
                  Solo haz clic en el icono 📎 junto al campo de texto.
                </p>
              </div>
            </div>
          </div>
          
        </div>
      </section>
      
      <!-- ===== CHAT VIEW ===== -->
      <section id="view-chat" class="hidden flex-1 flex overflow-hidden">
        
        <!-- Chat Sidebar -->
        <aside id="chat-sidebar" class="w-80 glass-strong border-r border-slate-200/50 flex flex-col">
          <!-- Header -->
          <div class="p-5 border-b border-slate-200/50">
            <button id="new-conv-btn" class="w-full py-3 px-4 gradient-brand text-white font-semibold rounded-xl shadow-md hover:shadow-lg hover:scale-[1.02] transition-smooth flex items-center justify-center gap-2">
              <i class="iconoir-plus text-xl"></i>
              <span>Nueva conversación</span>
            </button>
          </div>
          
          <!-- Folders Section -->
          <div class="p-4 border-b border-slate-200/50">
            <div class="flex items-center justify-between mb-3">
              <span class="text-tiny font-semibold text-slate-400 uppercase tracking-wider">Carpetas</span>
              <button id="new-folder-btn" class="p-1.5 text-slate-400 hover:text-[#23AAC5] hover:bg-[#23AAC5]/10 rounded-lg transition-smooth" title="Nueva carpeta">
                <i class="iconoir-folder-plus text-lg"></i>
              </button>
            </div>
            <div id="folder-list" class="space-y-1">
              <button data-folder-id="-1" class="folder-item nav-pill active w-full text-left flex items-center gap-3">
                <i class="iconoir-folder text-lg"></i>
                <span class="flex-1 text-small">Todas</span>
                <span class="text-tiny text-slate-400" id="all-count">0</span>
              </button>
            </div>
          </div>
          
          <!-- Conversations List -->
          <div class="flex-1 overflow-y-auto p-4 scrollbar-hide">
            <div class="flex items-center justify-between mb-3">
              <span class="text-tiny font-semibold text-slate-400 uppercase tracking-wider">Conversaciones</span>
              <select id="sort-select" class="text-tiny border border-slate-200 rounded-lg px-2 py-1 bg-white/80 input-focus">
                <option value="updated_at">Recientes</option>
                <option value="favorite">Favoritos</option>
                <option value="title">A-Z</option>
              </select>
            </div>
            <div id="conv-list" class="space-y-1">
              <div class="text-small text-slate-400 p-3 text-center">(cargando...)</div>
            </div>
          </div>
        </aside>
        
        <!-- Chat Main -->
        <div class="flex-1 flex flex-col bg-white/30">
          <!-- Chat Header -->
          <header class="h-16 px-6 glass-strong border-b border-slate-200/50 flex items-center justify-between">
            <div id="chat-title" class="flex items-center gap-3">
              <i class="iconoir-chat-bubble text-[#23AAC5]"></i>
              <span class="font-medium text-slate-700">Nueva conversación</span>
            </div>
            <div class="flex items-center gap-2">
              <button id="faq-btn" class="p-2 text-slate-400 hover:text-[#23AAC5] hover:bg-[#23AAC5]/10 rounded-lg transition-smooth" title="Dudas rápidas">
                <i class="iconoir-help-circle text-xl"></i>
              </button>
            </div>
          </header>
          
          <!-- Messages Area -->
          <div id="messages-container" class="flex-1 overflow-y-auto p-6">
            <div id="chat-empty-state" class="h-full flex items-center justify-center">
              <div class="text-center max-w-md">
                <div class="w-20 h-20 rounded-3xl gradient-brand flex items-center justify-center mx-auto mb-6 shadow-lg animate-float">
                  <i class="iconoir-chat-bubble-empty text-4xl text-white"></i>
                </div>
                <h2 class="text-title text-slate-900 mb-3">¿En qué te ayudo?</h2>
                <p class="text-body text-slate-500">
                  Escribe tu pregunta abajo y empezaremos a conversar.
                </p>
              </div>
            </div>
            <div id="messages" class="hidden space-y-6"></div>
            <div id="typing-indicator" class="hidden">
              <div class="flex gap-3 items-start max-w-3xl">
                <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 text-sm font-semibold">E</div>
                <div class="bg-white border border-slate-200 px-5 py-3.5 rounded-2xl rounded-tl-sm shadow-sm">
                  <div class="flex gap-1.5">
                    <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                    <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                    <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Chat Input -->
          <footer class="p-4 glass-strong border-t border-slate-200/50">
            <form id="chat-form" class="max-w-4xl mx-auto">
              <div id="file-preview" class="hidden mb-3 p-3 bg-slate-50 rounded-xl flex items-center gap-3">
                <i id="file-icon" class="iconoir-page text-xl text-[#23AAC5]"></i>
                <div class="flex-1 min-w-0">
                  <div id="file-name" class="text-small font-medium truncate"></div>
                  <div id="file-size" class="text-tiny text-slate-400"></div>
                </div>
                <button type="button" id="remove-file" class="p-1.5 text-slate-400 hover:text-red-500 rounded-lg transition-smooth">
                  <i class="iconoir-xmark"></i>
                </button>
              </div>
              <div class="flex gap-3">
                <input type="file" id="file-input" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp" />
                <button type="button" id="attach-btn" class="p-3 text-slate-400 hover:text-[#23AAC5] hover:bg-[#23AAC5]/10 rounded-xl border-2 border-slate-200 hover:border-[#23AAC5] transition-smooth" title="Adjuntar archivo (PDF o imagen)">
                  <i class="iconoir-attachment text-xl"></i>
                </button>
                <input id="chat-input" class="flex-1 px-5 py-3 rounded-xl border-2 border-slate-200 text-body input-focus bg-white/80" placeholder="Escribe tu mensaje..." />
                <button type="submit" class="px-6 py-3 gradient-brand text-white font-semibold rounded-xl shadow-md hover:shadow-lg hover:scale-105 transition-smooth flex items-center gap-2">
                  <span>Enviar</span>
                  <i class="iconoir-send-diagonal"></i>
                </button>
              </div>
            </form>
          </footer>
        </div>
      </section>
      
    </main>
  </div>

  <!-- User Dropdown Menu -->
  <div id="user-dropdown" class="hidden fixed z-50 w-64 glass-strong rounded-2xl shadow-xl border border-slate-200/50 py-2">
    <div class="px-4 py-3 border-b border-slate-200/50">
      <div id="dropdown-user-name" class="font-semibold text-slate-800"></div>
      <div id="dropdown-user-email" class="text-small text-slate-500"></div>
    </div>
    <a href="/account.php" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-smooth">
      <i class="iconoir-user text-slate-400"></i>
      <span class="text-small">Mi cuenta</span>
    </a>
    <a href="/admin/users.php" id="admin-link" class="hidden flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-smooth border-t border-slate-200/50">
      <i class="iconoir-settings text-slate-400"></i>
      <span class="text-small">Administración</span>
    </a>
    <button id="logout-btn" class="w-full flex items-center gap-3 px-4 py-3 hover:bg-red-50 text-red-600 transition-smooth border-t border-slate-200/50">
      <i class="iconoir-log-out"></i>
      <span class="text-small">Cerrar sesión</span>
    </button>
  </div>

  <!-- Move to Folder Modal -->
  <div id="move-modal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="glass-strong rounded-3xl shadow-2xl max-w-md w-full max-h-[80vh] flex flex-col border border-slate-200/50">
      <div class="p-6 border-b border-slate-200/50 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl gradient-brand flex items-center justify-center">
            <i class="iconoir-folder-settings text-xl text-white"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900">Mover conversación</h3>
            <p class="text-tiny text-slate-500" id="move-conv-title"></p>
          </div>
        </div>
        <button id="close-move-modal" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-smooth">
          <i class="iconoir-xmark text-xl"></i>
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-6">
        <div id="folder-options" class="space-y-2">
          <button data-target-folder="0" class="folder-option w-full p-4 rounded-xl border-2 border-slate-200 hover:border-[#23AAC5] hover:bg-[#23AAC5]/5 transition-smooth text-left flex items-center gap-3">
            <i class="iconoir-folder-minus text-xl text-slate-400"></i>
            <div class="flex-1">
              <div class="font-medium text-slate-700">Sin carpeta</div>
              <div class="text-tiny text-slate-400">Mover a la raíz</div>
            </div>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- FAQ Modal -->
  <div id="faq-modal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="glass-strong rounded-3xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col border border-slate-200/50">
      <div class="p-5 border-b border-slate-200/50 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl gradient-brand flex items-center justify-center">
            <i class="iconoir-help-circle text-xl text-white"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900">Dudas rápidas</h3>
            <p class="text-tiny text-slate-500">Pregunta sobre el Grupo Ebone</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button id="faq-clear-btn" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-smooth" title="Nueva conversación">
            <i class="iconoir-refresh text-lg"></i>
          </button>
          <button id="faq-close-btn" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-smooth">
            <i class="iconoir-xmark text-xl"></i>
          </button>
        </div>
      </div>
      <div id="faq-messages" class="flex-1 overflow-y-auto p-5 space-y-4">
        <div id="faq-suggestions" class="space-y-3">
          <p class="text-small text-slate-600 text-center mb-4">¿Qué quieres saber? Prueba con estas preguntas:</p>
          <div class="grid grid-cols-1 gap-2">
            <button class="faq-suggestion p-4 text-left bg-white/50 hover:bg-[#23AAC5]/5 border border-slate-200 hover:border-[#23AAC5] rounded-xl transition-smooth text-small">
              ¿Qué es CUBOFIT y cómo funciona?
            </button>
            <button class="faq-suggestion p-4 text-left bg-white/50 hover:bg-[#23AAC5]/5 border border-slate-200 hover:border-[#23AAC5] rounded-xl transition-smooth text-small">
              ¿Cuántos empleados tiene el Grupo Ebone?
            </button>
            <button class="faq-suggestion p-4 text-left bg-white/50 hover:bg-[#23AAC5]/5 border border-slate-200 hover:border-[#23AAC5] rounded-xl transition-smooth text-small">
              ¿Qué servicios ofrece UNIGES-3?
            </button>
          </div>
        </div>
      </div>
      <div id="faq-typing" class="hidden px-5 pb-2">
        <div class="flex items-center gap-2 text-slate-500 text-small">
          <div class="flex gap-1">
            <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce"></div>
            <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
            <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
          </div>
          <span>Pensando...</span>
        </div>
      </div>
      <div class="p-4 border-t border-slate-200/50">
        <form id="faq-form" class="flex gap-3">
          <input id="faq-input" type="text" class="flex-1 px-4 py-3 rounded-xl border-2 border-slate-200 text-small input-focus bg-white/80" placeholder="Escribe tu pregunta..." autocomplete="off" />
          <button type="submit" class="px-5 py-3 gradient-brand text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-smooth">
            <i class="iconoir-send-diagonal text-lg"></i>
          </button>
        </form>
      </div>
    </div>
  </div>

<!-- JavaScript -->
<script type="module">
  // ===== STATE =====
  let csrf = window.CSRF_TOKEN;
  let currentUser = null;
  let currentConversationId = null;
  let currentFolderId = -1;
  let allFolders = [];
  let currentFile = null;
  let conversationToMove = null;

  // ===== DOM REFS =====
  const viewHome = document.getElementById('view-home');
  const viewChat = document.getElementById('view-chat');
  const messagesEl = document.getElementById('messages');
  const messagesContainer = document.getElementById('messages-container');
  const chatEmptyState = document.getElementById('chat-empty-state');
  const typingIndicator = document.getElementById('typing-indicator');
  const chatForm = document.getElementById('chat-form');
  const chatInput = document.getElementById('chat-input');
  const chatTitle = document.getElementById('chat-title');
  const convListEl = document.getElementById('conv-list');
  const folderListEl = document.getElementById('folder-list');
  const sortSelect = document.getElementById('sort-select');
  const recentConvEl = document.getElementById('recent-conversations');
  
  // ===== HELPERS =====
  function escapeHtml(str) {
    return str.replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  }
  
  function mdToHtml(md) {
    let s = escapeHtml(md);
    s = s.replace(/^###\s+(.+)$/gm, '<h3 class="font-semibold text-base mb-1">$1</h3>');
    s = s.replace(/^##\s+(.+)$/gm, '<h2 class="font-semibold text-lg mb-1">$1</h2>');
    s = s.replace(/^#\s+(.+)$/gm, '<h1 class="font-semibold text-xl mb-1">$1</h1>');
    s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/\*(.+?)\*/g, '<em>$1</em>');
    s = s.replace(/`([^`]+)`/g, '<code class="px-1 py-0.5 bg-slate-100 rounded text-sm">$1</code>');
    s = s.replace(/\n/g, '<br>');
    return s;
  }
  
  async function api(path, opts = {}) {
    const res = await fetch(path, {
      method: opts.method || 'GET',
      headers: {
        'Content-Type': 'application/json',
        ...(csrf ? { 'X-CSRF-Token': csrf } : {})
      },
      body: opts.body ? JSON.stringify(opts.body) : undefined,
      credentials: 'include'
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data?.error?.message || res.statusText);
    return data;
  }
  
  function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }
  
  function fileToBase64(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result.split(',')[1]);
      reader.onerror = reject;
      reader.readAsDataURL(file);
    });
  }
  
  function timeAgo(date) {
    const now = new Date();
    const diff = Math.floor((now - new Date(date)) / 1000);
    if (diff < 60) return 'ahora';
    if (diff < 3600) return `hace ${Math.floor(diff/60)} min`;
    if (diff < 86400) return `hace ${Math.floor(diff/3600)}h`;
    return new Date(date).toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
  }

  // ===== NAVIGATION =====
  document.querySelectorAll('[data-nav]').forEach(btn => {
    btn.addEventListener('click', () => {
      const nav = btn.dataset.nav;
      document.querySelectorAll('[data-nav]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      
      if (nav === 'home') {
        viewHome.classList.remove('hidden');
        viewChat.classList.add('hidden');
      } else if (nav === 'chat') {
        viewHome.classList.add('hidden');
        viewChat.classList.remove('hidden');
      } else if (nav === 'write') {
        window.location.href = '/gestures/write-article.php';
      }
    });
  });
  
  // Quick actions
  document.querySelector('[data-action="start-chat"]')?.addEventListener('click', () => {
    document.querySelector('[data-nav="chat"]').click();
  });
  
  document.querySelector('[data-action="view-all-chats"]')?.addEventListener('click', () => {
    document.querySelector('[data-nav="chat"]').click();
  });

  // ===== USER MENU =====
  const userMenuBtn = document.getElementById('user-menu-btn');
  const userDropdown = document.getElementById('user-dropdown');
  
  userMenuBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    const rect = userMenuBtn.getBoundingClientRect();
    userDropdown.style.left = `${rect.right + 8}px`;
    userDropdown.style.bottom = `${window.innerHeight - rect.bottom}px`;
    userDropdown.classList.toggle('hidden');
  });
  
  document.addEventListener('click', () => userDropdown?.classList.add('hidden'));
  
  document.getElementById('logout-btn')?.addEventListener('click', async () => {
    try {
      await api('/api/auth/logout.php', { method: 'POST' });
      window.location.href = '/login.php';
    } catch (e) {
      alert('Error al cerrar sesión');
    }
  });

  // ===== INIT SESSION =====
  (async function init() {
    try {
      const res = await fetch('/api/auth/me.php', { credentials: 'include' });
      if (res.status === 401) { window.location.href = '/login.php'; return; }
      const data = await res.json();
      
      csrf = data.csrf_token || csrf;
      currentUser = data.user;
      
      document.getElementById('dropdown-user-name').textContent = `${data.user.first_name} ${data.user.last_name}`;
      document.getElementById('dropdown-user-email').textContent = data.user.email;
      
      if (data.user.roles?.includes('admin')) {
        document.getElementById('admin-link')?.classList.remove('hidden');
      }
      
      await loadFolders();
      await loadConversations();
      await loadRecentConversations();
    } catch (e) {
      window.location.href = '/login.php';
    }
  })();

  // ===== FOLDERS =====
  async function loadFolders() {
    const data = await api('/api/folders/list.php');
    allFolders = data.folders || [];
    
    const allConvs = await api('/api/conversations/list.php?folder_id=-1');
    document.getElementById('all-count').textContent = (allConvs.items || []).length;
    
    // Clear dynamic folders
    folderListEl.querySelectorAll('.dynamic-folder').forEach(el => el.remove());
    
    for (const folder of allFolders) {
      const btn = document.createElement('button');
      btn.dataset.folderId = folder.id;
      btn.className = `folder-item dynamic-folder nav-pill w-full text-left flex items-center gap-3 ${currentFolderId === folder.id ? 'active' : ''}`;
      btn.innerHTML = `
        <i class="iconoir-folder text-lg"></i>
        <span class="flex-1 text-small truncate">${escapeHtml(folder.name)}</span>
        <span class="text-tiny text-slate-400">${folder.conversation_count}</span>
      `;
      btn.addEventListener('click', () => {
        currentFolderId = folder.id;
        loadFolders();
        loadConversations();
      });
      folderListEl.appendChild(btn);
    }
    
    // Update active state
    folderListEl.querySelectorAll('.folder-item').forEach(item => {
      item.classList.toggle('active', parseInt(item.dataset.folderId) === currentFolderId);
    });
  }
  
  document.getElementById('new-folder-btn')?.addEventListener('click', async () => {
    const name = prompt('Nombre de la carpeta:');
    if (!name?.trim()) return;
    try {
      await api('/api/folders/create.php', { method: 'POST', body: { name: name.trim() } });
      await loadFolders();
    } catch (e) {
      alert('Error: ' + e.message);
    }
  });
  
  // Folder "Todas" click
  document.querySelector('[data-folder-id="-1"]')?.addEventListener('click', () => {
    currentFolderId = -1;
    loadFolders();
    loadConversations();
  });

  // ===== CONVERSATIONS =====
  async function loadConversations() {
    const sort = sortSelect?.value || 'updated_at';
    const data = await api(`/api/conversations/list.php?sort=${sort}&folder_id=${currentFolderId}`);
    const items = data.items || [];
    
    if (items.length === 0) {
      convListEl.innerHTML = '<div class="text-small text-slate-400 p-4 text-center">No hay conversaciones</div>';
      return;
    }
    
    convListEl.innerHTML = '';
    for (const c of items) {
      const isActive = currentConversationId === c.id;
      const li = document.createElement('div');
      li.className = `group rounded-xl transition-smooth ${isActive ? 'bg-[#23AAC5]/10' : 'hover:bg-slate-50'}`;
      li.innerHTML = `
        <div class="flex items-center gap-2 p-3">
          <button class="star-btn flex-shrink-0 ${c.is_favorite ? 'text-amber-500' : 'text-slate-300 hover:text-amber-400'}">
            <i class="${c.is_favorite ? 'iconoir-star-solid' : 'iconoir-star'}"></i>
          </button>
          <button class="conv-btn flex-1 text-left min-w-0">
            <div class="font-medium text-small truncate ${isActive ? 'text-[#115c6c]' : 'text-slate-700'}">${escapeHtml(c.title || 'Sin título')}</div>
            <div class="text-tiny text-slate-400">${timeAgo(c.updated_at)}</div>
          </button>
          <div class="actions flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-smooth">
            <button class="move-btn p-1.5 text-slate-400 hover:text-[#23AAC5] rounded-lg"><i class="iconoir-folder-settings"></i></button>
            <button class="del-btn p-1.5 text-slate-400 hover:text-red-500 rounded-lg"><i class="iconoir-trash"></i></button>
          </div>
        </div>
      `;
      
      li.querySelector('.conv-btn').addEventListener('click', () => openConversation(c));
      li.querySelector('.star-btn').addEventListener('click', async () => {
        await api('/api/conversations/toggle_favorite.php', { method: 'POST', body: { id: c.id } });
        loadConversations();
      });
      li.querySelector('.move-btn').addEventListener('click', () => openMoveModal(c));
      li.querySelector('.del-btn').addEventListener('click', async () => {
        if (!confirm('¿Eliminar esta conversación?')) return;
        await api('/api/conversations/delete.php', { method: 'POST', body: { id: c.id } });
        if (currentConversationId === c.id) {
          currentConversationId = null;
          showChatEmpty();
        }
        loadFolders();
        loadConversations();
      });
      
      convListEl.appendChild(li);
    }
  }
  
  async function loadRecentConversations() {
    const data = await api('/api/conversations/list.php?sort=updated_at&limit=3');
    const items = (data.items || []).slice(0, 3);
    
    if (items.length === 0) {
      recentConvEl.innerHTML = `
        <div class="col-span-3 text-center py-8">
          <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4">
            <i class="iconoir-chat-bubble text-2xl text-slate-400"></i>
          </div>
          <p class="text-small text-slate-500">Aún no tienes conversaciones</p>
        </div>
      `;
      return;
    }
    
    recentConvEl.innerHTML = items.map(c => `
      <button class="glass rounded-2xl p-5 border border-slate-200/50 text-left hover:shadow-lg hover:-translate-y-1 transition-smooth group" data-conv-id="${c.id}">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-xl bg-[#23AAC5]/10 flex items-center justify-center flex-shrink-0">
            <i class="iconoir-chat-bubble text-[#23AAC5]"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-medium text-slate-800 truncate group-hover:text-[#115c6c]">${escapeHtml(c.title || 'Sin título')}</div>
            <div class="text-tiny text-slate-400 mt-1">${timeAgo(c.updated_at)}</div>
          </div>
        </div>
      </button>
    `).join('');
    
    recentConvEl.querySelectorAll('[data-conv-id]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = parseInt(btn.dataset.convId);
        const conv = items.find(c => c.id === id);
        if (conv) {
          document.querySelector('[data-nav="chat"]').click();
          openConversation(conv);
        }
      });
    });
  }
  
  sortSelect?.addEventListener('change', loadConversations);

  // ===== CHAT =====
  function showChatMode() {
    chatEmptyState?.classList.add('hidden');
    messagesEl?.classList.remove('hidden');
  }
  
  function showChatEmpty() {
    chatEmptyState?.classList.remove('hidden');
    messagesEl?.classList.add('hidden');
    messagesEl.innerHTML = '';
    updateChatTitle(null);
  }
  
  function updateChatTitle(title) {
    const span = chatTitle?.querySelector('span');
    if (span) span.textContent = title || 'Nueva conversación';
  }
  
  function appendMessage(role, content) {
    showChatMode();
    const initials = currentUser ? `${currentUser.first_name[0]}${currentUser.last_name[0]}` : '?';
    const wrap = document.createElement('div');
    wrap.className = `flex gap-3 ${role === 'user' ? 'justify-end' : 'justify-start'}`;
    
    const avatar = role === 'user'
      ? `<div class="w-9 h-9 rounded-full gradient-brand flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">${initials}</div>`
      : `<div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 text-sm font-semibold flex-shrink-0">E</div>`;
    
    const bubbleClass = role === 'user'
      ? 'gradient-brand text-white rounded-2xl rounded-tr-sm'
      : 'bg-white border border-slate-200 text-slate-800 rounded-2xl rounded-tl-sm shadow-sm';
    
    const contentHtml = role === 'assistant' ? mdToHtml(content) : escapeHtml(content);
    
    wrap.innerHTML = role === 'user'
      ? `<div class="${bubbleClass} px-5 py-3.5 max-w-2xl">${contentHtml}</div>${avatar}`
      : `${avatar}<div class="${bubbleClass} px-5 py-3.5 max-w-2xl">${contentHtml}</div>`;
    
    messagesEl.appendChild(wrap);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
  }
  
  async function openConversation(conv) {
    currentConversationId = conv.id;
    updateChatTitle(conv.title);
    messagesEl.innerHTML = '';
    
    const data = await api(`/api/messages/list.php?conversation_id=${conv.id}`);
    const items = data.items || [];
    
    if (items.length > 0) {
      showChatMode();
      for (const m of items) appendMessage(m.role, m.content);
    } else {
      showChatEmpty();
    }
    
    loadConversations();
  }
  
  document.getElementById('new-conv-btn')?.addEventListener('click', async () => {
    try {
      const res = await api('/api/conversations/create.php', { method: 'POST', body: {} });
      currentConversationId = res.id;
      showChatEmpty();
      loadConversations();
      chatInput?.focus();
    } catch (e) {
      alert('Error: ' + e.message);
    }
  });
  
  async function sendMessage(text, file = null) {
    if (!text && !file) return;
    
    let userMsg = text || '';
    if (file) userMsg += ` 📎 ${file.name}`;
    appendMessage('user', userMsg);
    
    typingIndicator?.classList.remove('hidden');
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    
    try {
      const body = { conversation_id: currentConversationId, message: text || '¿Qué puedes decirme sobre este archivo?' };
      if (file) {
        body.file = { mime_type: file.type, data: await fileToBase64(file), name: file.name };
      }
      
      const data = await api('/api/chat.php', { method: 'POST', body });
      typingIndicator?.classList.add('hidden');
      
      if (!currentConversationId && data.conversation?.id) {
        currentConversationId = data.conversation.id;
        loadConversations();
      }
      
      if (data.conversation?.id === currentConversationId) {
        const convData = await api('/api/conversations/list.php');
        const conv = convData.items?.find(c => c.id === currentConversationId);
        if (conv) updateChatTitle(conv.title);
      }
      
      appendMessage('assistant', data.message.content);
      loadRecentConversations();
    } catch (e) {
      typingIndicator?.classList.add('hidden');
      appendMessage('assistant', 'Error: ' + e.message);
    }
  }
  
  chatForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = chatInput.value.trim();
    if (!text && !currentFile) return;
    chatInput.value = '';
    await sendMessage(text, currentFile);
    currentFile = null;
    document.getElementById('file-preview')?.classList.add('hidden');
  });
  
  // Quick chat from home
  document.getElementById('quick-chat-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = document.getElementById('quick-chat-input');
    const text = input?.value.trim();
    if (!text) return;
    input.value = '';
    
    // Create new conversation and switch to chat view
    const res = await api('/api/conversations/create.php', { method: 'POST', body: {} });
    currentConversationId = res.id;
    document.querySelector('[data-nav="chat"]').click();
    await sendMessage(text);
  });

  // ===== FILE ATTACHMENTS =====
  const fileInput = document.getElementById('file-input');
  const filePreview = document.getElementById('file-preview');
  const attachBtn = document.getElementById('attach-btn');
  
  attachBtn?.addEventListener('click', () => fileInput?.click());
  
  fileInput?.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    if (file.size > 10 * 1024 * 1024) { alert('Máximo 10MB'); return; }
    currentFile = file;
    document.getElementById('file-name').textContent = file.name;
    document.getElementById('file-size').textContent = formatFileSize(file.size);
    filePreview?.classList.remove('hidden');
  });
  
  document.getElementById('remove-file')?.addEventListener('click', () => {
    currentFile = null;
    fileInput.value = '';
    filePreview?.classList.add('hidden');
  });

  // ===== MOVE MODAL =====
  const moveModal = document.getElementById('move-modal');
  const folderOptionsEl = document.getElementById('folder-options');
  
  function openMoveModal(conv) {
    conversationToMove = conv;
    document.getElementById('move-conv-title').textContent = `"${conv.title}"`;
    
    folderOptionsEl.querySelectorAll('.dynamic-folder-option').forEach(el => el.remove());
    
    allFolders.forEach(folder => {
      const btn = document.createElement('button');
      btn.className = 'folder-option dynamic-folder-option w-full p-4 rounded-xl border-2 border-slate-200 hover:border-[#23AAC5] hover:bg-[#23AAC5]/5 transition-smooth text-left flex items-center gap-3';
      btn.innerHTML = `
        <i class="iconoir-folder text-xl text-[#23AAC5]"></i>
        <div class="flex-1">
          <div class="font-medium text-slate-700">${escapeHtml(folder.name)}</div>
          <div class="text-tiny text-slate-400">${folder.conversation_count} conversaciones</div>
        </div>
      `;
      btn.addEventListener('click', () => moveConversation(folder.id));
      folderOptionsEl.appendChild(btn);
    });
    
    folderOptionsEl.querySelector('[data-target-folder="0"]')?.addEventListener('click', () => moveConversation(null));
    moveModal?.classList.remove('hidden');
  }
  
  async function moveConversation(folderId) {
    if (!conversationToMove) return;
    await api('/api/conversations/move_to_folder.php', { method: 'POST', body: { conversation_id: conversationToMove.id, folder_id: folderId } });
    moveModal?.classList.add('hidden');
    conversationToMove = null;
    loadFolders();
    loadConversations();
  }
  
  document.getElementById('close-move-modal')?.addEventListener('click', () => moveModal?.classList.add('hidden'));
  moveModal?.addEventListener('click', (e) => { if (e.target === moveModal) moveModal.classList.add('hidden'); });

  // ===== FAQ MODAL =====
  const faqModal = document.getElementById('faq-modal');
  const faqMessages = document.getElementById('faq-messages');
  const faqInput = document.getElementById('faq-input');
  const faqTyping = document.getElementById('faq-typing');
  let faqHistory = [];
  
  document.getElementById('faq-btn')?.addEventListener('click', () => { faqModal?.classList.remove('hidden'); faqInput?.focus(); });
  document.getElementById('faq-close-btn')?.addEventListener('click', () => faqModal?.classList.add('hidden'));
  faqModal?.addEventListener('click', (e) => { if (e.target === faqModal) faqModal.classList.add('hidden'); });
  
  document.getElementById('faq-clear-btn')?.addEventListener('click', () => {
    faqHistory = [];
    faqMessages.innerHTML = document.getElementById('faq-suggestions')?.outerHTML || '';
    bindFaqSuggestions();
  });
  
  function bindFaqSuggestions() {
    document.querySelectorAll('.faq-suggestion').forEach(btn => {
      btn.addEventListener('click', () => {
        faqInput.value = btn.textContent.trim();
        document.getElementById('faq-form').dispatchEvent(new Event('submit'));
      });
    });
  }
  bindFaqSuggestions();
  
  function appendFaqMessage(role, content) {
    const initials = currentUser ? `${currentUser.first_name[0]}${currentUser.last_name[0]}` : '?';
    const div = document.createElement('div');
    div.className = `flex gap-3 ${role === 'user' ? 'justify-end' : 'justify-start'}`;
    const avatar = role === 'user'
      ? `<div class="w-8 h-8 rounded-full gradient-brand flex items-center justify-center text-white text-xs font-semibold">${initials}</div>`
      : `<div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 text-xs font-semibold">E</div>`;
    const bubbleClass = role === 'user' ? 'gradient-brand text-white' : 'bg-slate-100 text-slate-800';
    const html = role === 'assistant' ? mdToHtml(content) : escapeHtml(content);
    div.innerHTML = role === 'user'
      ? `<div class="${bubbleClass} px-4 py-2.5 rounded-2xl rounded-tr-sm max-w-[80%] text-small">${html}</div>${avatar}`
      : `${avatar}<div class="${bubbleClass} px-4 py-2.5 rounded-2xl rounded-tl-sm max-w-[80%] text-small">${html}</div>`;
    faqMessages.appendChild(div);
    faqMessages.scrollTop = faqMessages.scrollHeight;
  }
  
  document.getElementById('faq-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = faqInput.value.trim();
    if (!msg) return;
    
    document.getElementById('faq-suggestions')?.classList.add('hidden');
    appendFaqMessage('user', msg);
    faqInput.value = '';
    faqHistory.push({ role: 'user', content: msg });
    faqTyping?.classList.remove('hidden');
    
    try {
      const res = await fetch('/api/faq.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ message: msg, history: faqHistory.slice(0, -1) }),
        credentials: 'include'
      });
      const data = await res.json();
      faqTyping?.classList.add('hidden');
      appendFaqMessage('assistant', data.reply || 'Sin respuesta');
      faqHistory.push({ role: 'assistant', content: data.reply });
    } catch (e) {
      faqTyping?.classList.add('hidden');
      appendFaqMessage('assistant', 'Error de conexión');
    }
  });
</script>

</body>
</html>
