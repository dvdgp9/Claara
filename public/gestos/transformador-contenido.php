<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Repos/UserFeatureAccessRepo.php';

use App\Session;
use Repos\UserFeatureAccessRepo;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

// Verificar acceso a este gesto
$accessRepo = new UserFeatureAccessRepo();
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'content-repurposer')) {
    header('Location: /gestos/?error=no_access');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
if (!$csrfToken) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    } catch (\Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16));
    }
    $csrfToken = $_SESSION['csrf_token'];
}
$activeTab = 'gestures';

// Configuración del header unificado
$headerBackUrl = '/gestos/';
$headerBackText = 'Todos los gestos';
$headerTitle = 'Transformador de contenido';
$headerIcon = 'iconoir-refresh-double';
$headerIconColor = 'from-indigo-500 to-purple-600';
$headerDrawerId = 'repurposer-history-drawer';
?><!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-mesh text-slate-900 overflow-hidden">
  <style>
    .format-card {
      transition: all 0.2s ease;
    }
    .format-card:hover {
      transform: translateY(-2px);
    }
    .format-card.selected {
      border-color: #6366f1;
      background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(168, 85, 247, 0.1) 100%);
      box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.3);
    }
    .format-card.selected .format-icon {
      background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
      color: white;
    }
    .history-item.active {
      background-color: rgba(99, 102, 241, 0.05);
      border-left: 3px solid #6366f1;
    }
    .output-content {
      white-space: pre-wrap;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
    .output-content h1, .output-content h2, .output-content h3 {
      font-family: inherit;
      font-weight: 700;
      margin-top: 1em;
      margin-bottom: 0.5em;
    }
  </style>
  <div class="min-h-screen flex h-screen">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>
    
    <!-- Sidebar de historial (solo desktop) -->
    <aside id="history-sidebar" class="hidden lg:flex w-72 glass-strong border-r border-slate-200/50 flex-col shrink-0">
      <div class="p-4 border-b border-slate-200/50">
        <div class="flex items-center justify-between">
          <h2 class="font-semibold text-slate-800 flex items-center gap-2">
            <i class="iconoir-clock text-indigo-500"></i>
            Historial
          </h2>
        </div>
      </div>
      
      <div id="history-list" class="flex-1 overflow-auto">
        <div class="p-4 text-center text-slate-400 text-sm">
          <i class="iconoir-refresh animate-spin"></i>
          Cargando...
        </div>
      </div>
    </aside>
    
    <!-- Mobile Drawer para historial -->
    <?php 
    $drawerId = 'repurposer-history-drawer';
    $drawerTitle = 'Historial';
    $drawerIcon = 'iconoir-clock';
    $drawerIconColor = 'text-indigo-500';
    include __DIR__ . '/../includes/mobile-drawer.php'; 
    ?>
    
    <!-- Main content area -->
    <main class="flex-1 flex flex-col overflow-hidden min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <!-- Single column layout -->
      <div class="flex-1 overflow-auto pb-16 lg:pb-0">
        <div class="max-w-3xl mx-auto p-4 lg:p-6 space-y-4 lg:space-y-6">
          
          <!-- Intro -->
          <div class="text-center mb-6">
            <h1 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-purple-600 mb-2">
              Transforma tu contenido
            </h1>
            <p class="text-slate-500 max-w-lg mx-auto">
              Convierte cualquier artículo, documento o texto en el formato que necesites: posts para redes, blogs, newsletters, landing pages o FAQs.
            </p>
          </div>

          <!-- Input Section -->
          <section id="input-section" class="glass-strong rounded-2xl p-6 border border-slate-200/50">
            <form id="repurposer-form" class="space-y-5">
              
              <!-- Fuente del contenido -->
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-3">
                  <i class="iconoir-input-field text-indigo-500 mr-1"></i>
                  Fuente del contenido
                </label>
                
                <!-- Tabs -->
                <div class="flex gap-2 mb-3">
                  <button type="button" data-tab="url" class="tab-btn active px-4 py-2 text-sm font-medium rounded-lg transition-all bg-indigo-100 text-indigo-700">
                    <i class="iconoir-link mr-1"></i> URL
                  </button>
                  <button type="button" data-tab="text" class="tab-btn px-4 py-2 text-sm font-medium rounded-lg transition-all bg-slate-100 text-slate-600 hover:bg-slate-200">
                    <i class="iconoir-text mr-1"></i> Texto
                  </button>
                  <button type="button" data-tab="pdf" class="tab-btn px-4 py-2 text-sm font-medium rounded-lg transition-all bg-slate-100 text-slate-600 hover:bg-slate-200">
                    <i class="iconoir-page mr-1"></i> PDF
                  </button>
                </div>

                <!-- URL Input -->
                <div id="tab-url" class="tab-content">
                  <input type="url" id="source-url" 
                         class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                         placeholder="https://ejemplo.com/articulo" />
                  <p class="text-xs text-slate-500 mt-2">Pega la URL de cualquier artículo web</p>
                </div>

                <!-- Text Input -->
                <div id="tab-text" class="tab-content hidden">
                  <textarea id="source-text" rows="6"
                            class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all resize-none"
                            placeholder="Pega aquí el texto que quieres transformar..."></textarea>
                  <p class="text-xs text-slate-500 mt-2">Mínimo 20 palabras</p>
                </div>

                <!-- PDF Input -->
                <div id="tab-pdf" class="tab-content hidden">
                  <label class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed border-slate-300 rounded-xl cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/50 transition-all">
                    <i class="iconoir-upload text-2xl text-slate-400 mb-2"></i>
                    <span class="text-sm text-slate-500">Arrastra un PDF o haz clic para seleccionar</span>
                    <input type="file" id="source-pdf" accept=".pdf" class="hidden" />
                  </label>
                  <p id="pdf-filename" class="text-xs text-slate-500 mt-2 hidden"></p>
                </div>
              </div>

              <!-- Formato de salida -->
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-3">
                  <i class="iconoir-sparks text-indigo-500 mr-1"></i>
                  ¿Qué quieres generar?
                </label>
                
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                  <!-- Redes Sociales -->
                  <button type="button" data-format="instagram" class="format-card selected p-3 rounded-xl border-2 border-slate-200 text-left">
                    <div class="format-icon w-10 h-10 rounded-lg bg-gradient-to-br from-pink-500 to-orange-500 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-instagram text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">Instagram</div>
                    <div class="text-xs text-slate-500">Post + hashtags</div>
                  </button>
                  
                  <button type="button" data-format="facebook" class="format-card p-3 rounded-xl border-2 border-slate-200 text-left">
                    <div class="format-icon w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-facebook text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">Facebook</div>
                    <div class="text-xs text-slate-500">Publicación</div>
                  </button>
                  
                  <button type="button" data-format="linkedin" class="format-card p-3 rounded-xl border-2 border-slate-200 text-left">
                    <div class="format-icon w-10 h-10 rounded-lg bg-sky-700 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-linkedin text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">LinkedIn</div>
                    <div class="text-xs text-slate-500">Profesional</div>
                  </button>
                  
                  <button type="button" data-format="twitter" class="format-card p-3 rounded-xl border-2 border-slate-200 text-left">
                    <div class="format-icon w-10 h-10 rounded-lg bg-slate-900 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-x text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">X (Twitter)</div>
                    <div class="text-xs text-slate-500">Tweet/Hilo</div>
                  </button>
                  
                  <!-- Contenido largo -->
                  <button type="button" data-format="blog" class="format-card p-3 rounded-xl border-2 border-slate-200 text-left">
                    <div class="format-icon w-10 h-10 rounded-lg bg-emerald-600 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-post text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">Blog</div>
                    <div class="text-xs text-slate-500">Artículo SEO</div>
                  </button>
                  
                  <button type="button" data-format="landing" class="format-card p-3 rounded-xl border-2 border-slate-200 text-left">
                    <div class="format-icon w-10 h-10 rounded-lg bg-violet-600 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-code text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">Landing</div>
                    <div class="text-xs text-slate-500">HTML/CSS/JS</div>
                  </button>
                  
                  <button type="button" data-format="newsletter" class="format-card p-3 rounded-xl border-2 border-slate-200 text-left">
                    <div class="format-icon w-10 h-10 rounded-lg bg-amber-600 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-mail text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">Newsletter</div>
                    <div class="text-xs text-slate-500">Email</div>
                  </button>
                  
                  <button type="button" data-format="faq" class="format-card p-3 rounded-xl border-2 border-slate-200 text-left">
                    <div class="format-icon w-10 h-10 rounded-lg bg-rose-600 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-help-circle text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">FAQs</div>
                    <div class="text-xs text-slate-500">Preguntas</div>
                  </button>
                </div>
              </div>
              
              <!-- Botón generar -->
              <button type="submit" id="generate-btn" class="w-full py-3 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                <i class="iconoir-sparks"></i>
                <span>Transformar contenido</span>
              </button>
              
              <!-- Progress -->
              <div id="progress-panel" class="hidden bg-indigo-50 rounded-xl p-4 border border-indigo-200">
                <div class="flex items-center gap-3">
                  <div class="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                  <div>
                    <p id="progress-text" class="text-sm font-medium text-indigo-700">Procesando...</p>
                    <p id="progress-detail" class="text-xs text-indigo-500">Extrayendo y transformando contenido</p>
                  </div>
                </div>
              </div>
              
              <!-- Error -->
              <div id="error-panel" class="hidden bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="flex items-start gap-2">
                  <i class="iconoir-warning-triangle text-red-500"></i>
                  <div>
                    <p class="text-sm font-medium text-red-800">Error</p>
                    <p id="error-message" class="text-xs text-red-600 mt-0.5"></p>
                  </div>
                </div>
              </div>
            </form>
          </section>

          <!-- Result Section -->
          <section id="result-section" class="hidden space-y-4">
            
            <!-- Result Header -->
            <div class="flex items-center justify-between mb-2">
              <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i class="iconoir-check-circle text-green-500"></i>
                <span id="result-format-name">Contenido generado</span>
              </h2>
              <button type="button" onclick="resetUI()" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 rounded-lg transition-colors">
                <i class="iconoir-plus"></i>
                <span>Nueva transformación</span>
              </button>
            </div>
            
            <!-- Output Card -->
            <div class="glass-strong rounded-2xl border border-slate-200/50 overflow-hidden">
              <div class="p-4 border-b border-slate-200/50 bg-gradient-to-r from-indigo-50 to-purple-50">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div id="result-icon" class="w-10 h-10 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white">
                      <i class="iconoir-instagram"></i>
                    </div>
                    <div>
                      <h3 id="result-title" class="font-semibold text-slate-800">Título del contenido</h3>
                      <p id="result-source" class="text-xs text-slate-500">Fuente: URL</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <button id="copy-btn" class="px-3 py-1.5 text-sm bg-white hover:bg-slate-50 border border-slate-200 rounded-lg transition-colors flex items-center gap-1.5">
                      <i class="iconoir-copy"></i>
                      <span>Copiar</span>
                    </button>
                    <button id="download-btn" class="px-3 py-1.5 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors flex items-center gap-1.5">
                      <i class="iconoir-download"></i>
                      <span>Descargar</span>
                    </button>
                  </div>
                </div>
              </div>
              
              <div class="p-4 max-h-[500px] overflow-y-auto">
                <pre id="result-output" class="output-content text-sm text-slate-700 whitespace-pre-wrap"></pre>
              </div>
            </div>
            
            <!-- Preview para Landing (iframe) -->
            <div id="landing-preview" class="hidden glass-strong rounded-2xl border border-slate-200/50 overflow-hidden">
              <div class="p-4 border-b border-slate-200/50 flex items-center justify-between">
                <span class="font-semibold text-slate-700 flex items-center gap-2">
                  <i class="iconoir-eye text-indigo-500"></i>
                  Vista previa
                </span>
                <button id="toggle-preview" class="text-sm text-indigo-600 hover:text-indigo-700">Ocultar</button>
              </div>
              <iframe id="preview-iframe" class="w-full h-[400px] bg-white"></iframe>
            </div>
          </section>

        </div>
      </div>
    </main>
  </div>

  <script src="/assets/js/gesture-repurposer.js"></script>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
  
  <script>
    // Sincronizar historial con drawer móvil
    document.addEventListener('DOMContentLoaded', () => {
      const desktopHistory = document.getElementById('history-list');
      const mobileDrawerContent = document.getElementById('repurposer-history-drawer-content');
      
      function syncDrawerContent() {
        if (desktopHistory && mobileDrawerContent) {
          mobileDrawerContent.innerHTML = desktopHistory.innerHTML;
          mobileDrawerContent.querySelectorAll('.opacity-0, .lg\\:opacity-0').forEach(el => {
            el.classList.remove('opacity-0', 'lg:opacity-0');
            el.classList.add('opacity-100');
          });
        }
      }
      
      if (desktopHistory && mobileDrawerContent) {
        syncDrawerContent();
        
        const observer = new MutationObserver(syncDrawerContent);
        observer.observe(desktopHistory, { childList: true, subtree: true });
        
        mobileDrawerContent.addEventListener('click', (e) => {
          const deleteBtn = e.target.closest('.history-item-delete');
          if (deleteBtn) {
            const historyItem = deleteBtn.closest('.history-item');
            if (historyItem) {
              const id = historyItem.dataset.id;
              const desktopItem = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-delete`);
              if (desktopItem) {
                e.stopPropagation();
                desktopItem.click();
              }
            }
            return;
          }
          
          const historyItemMain = e.target.closest('.history-item-main');
          if (historyItemMain) {
            const historyItem = historyItemMain.closest('.history-item');
            if (historyItem) {
              const id = historyItem.dataset.id;
              const desktopItemMain = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-main`);
              if (desktopItemMain) {
                closeMobileDrawer('repurposer-history-drawer');
                desktopItemMain.click();
              }
            }
            return;
          }
        });
      }
    });
  </script>
</body>
</html>
