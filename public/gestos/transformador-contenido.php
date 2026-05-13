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
$headerBackText = 'All gestures';
$headerTitle = 'Content transformer';
$headerIcon = 'iconoir-refresh-double';
$headerIconColor = 'from-indigo-500 to-purple-600';
$headerDrawerId = 'repurposer-history-drawer';
?><!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../includes/head.php'; ?>
<link rel="stylesheet" href="/assets/css/social-previews.css">
<body class="bg-mesh text-slate-900 overflow-hidden">
  <style>
    .format-card {
      transition: all 0.2s ease;
      position: relative;
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
    .format-card .check-badge {
      position: absolute;
      top: -6px;
      right: -6px;
      width: 20px;
      height: 20px;
      background: #6366f1;
      border-radius: 50%;
      display: none;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 12px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .format-card.selected .check-badge {
      display: flex;
    }
    .history-item.active {
      background-color: rgba(99, 102, 241, 0.05);
      border-left: 3px solid #6366f1;
    }
    .result-tabs {
      display: flex;
      gap: 4px;
      overflow-x: auto;
      padding-bottom: 8px;
      scrollbar-width: none;
    }
    .result-tabs::-webkit-scrollbar {
      display: none;
    }
    .result-tab {
      padding: 8px 16px;
      border-radius: 9999px;
      font-size: 14px;
      font-weight: 500;
      white-space: nowrap;
      border: 1px solid #e2e8f0;
      background: white;
      color: #64748b;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .result-tab:hover {
      background: #f8fafc;
    }
    .result-tab.active {
      background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
      color: white;
      border-color: transparent;
    }
    .result-panel {
      display: none;
    }
    .result-panel.active {
      display: block;
    }
    @media (max-width: 1023px) {
      .result-panel {
        display: block !important;
        margin-bottom: 16px;
      }
      .result-tabs {
        display: none;
      }
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
            History
          </h2>
        </div>
      </div>
      
      <div id="history-list" class="flex-1 overflow-auto">
        <div class="p-4 text-center text-slate-400 text-sm">
          <i class="iconoir-refresh animate-spin"></i>
          Loading...
        </div>
      </div>
    </aside>
    
    <!-- Mobile Drawer para historial -->
    <?php 
    $drawerId = 'repurposer-history-drawer';
    $drawerTitle = 'History';
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
              Transform your content
            </h1>
            <p class="text-slate-500 max-w-lg mx-auto">
              Convert any article, document, or text into the format you need: social posts, blogs, newsletters, landing pages, or FAQs.
            </p>
          </div>

          <!-- Input Section -->
          <section id="input-section" class="glass-strong rounded-2xl p-6 border border-slate-200/50">
            <form id="repurposer-form" class="space-y-5">
              
              <!-- Content source -->
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-3">
                  <i class="iconoir-input-field text-indigo-500 mr-1"></i>
                  Content source
                </label>
                
                <!-- Tabs -->
                <div class="flex gap-2 mb-3">
                  <button type="button" data-tab="url" class="tab-btn active px-4 py-2 text-sm font-medium rounded-lg transition-all bg-indigo-100 text-indigo-700">
                    <i class="iconoir-link mr-1"></i> URL
                  </button>
                  <button type="button" data-tab="text" class="tab-btn px-4 py-2 text-sm font-medium rounded-lg transition-all bg-slate-100 text-slate-600 hover:bg-slate-200">
                    <i class="iconoir-text mr-1"></i> Text
                  </button>
                  <button type="button" data-tab="pdf" class="tab-btn px-4 py-2 text-sm font-medium rounded-lg transition-all bg-slate-100 text-slate-600 hover:bg-slate-200">
                    <i class="iconoir-page mr-1"></i> PDF
                  </button>
                </div>

                <!-- URL Input -->
                <div id="tab-url" class="tab-content">
                  <input type="url" id="source-url" 
                         class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                         placeholder="https://example.com/article" />
                  <p class="text-xs text-slate-500 mt-2">Paste the URL of any web article</p>
                </div>

                <!-- Text Input -->
                <div id="tab-text" class="tab-content hidden">
                  <textarea id="source-text" rows="6"
                            class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all resize-none"
                            placeholder="Paste the text you want to transform here..."></textarea>
                  <p class="text-xs text-slate-500 mt-2">Minimum 20 words</p>
                </div>

                <!-- PDF Input -->
                <div id="tab-pdf" class="tab-content hidden">
                  <label class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed border-slate-300 rounded-xl cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/50 transition-all">
                    <i class="iconoir-upload text-2xl text-slate-400 mb-2"></i>
                    <span class="text-sm text-slate-500">Drag a PDF or click to select</span>
                    <input type="file" id="source-pdf" accept=".pdf" class="hidden" />
                  </label>
                  <p id="pdf-filename" class="text-xs text-slate-500 mt-2 hidden"></p>
                </div>
              </div>

              <!-- Formato de salida (selección múltiple) -->
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                  <i class="iconoir-sparks text-indigo-500 mr-1"></i>
                  What do you want to generate? <span class="font-normal text-slate-400">(select multiple)</span>
                </label>
                <p class="text-xs text-slate-500 mb-3">Click the formats you want to generate. You can select multiple at once.</p>
                
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                  <!-- Redes Sociales -->
                  <button type="button" data-format="instagram" class="format-card selected p-3 rounded-xl border-2 border-slate-200 text-left">
                    <span class="check-badge"><i class="iconoir-check"></i></span>
                    <div class="format-icon w-10 h-10 rounded-lg bg-gradient-to-br from-pink-500 to-orange-500 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-instagram text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">Instagram</div>
                    <div class="text-xs text-slate-500">Post + hashtags</div>
                  </button>
                  
                  <button type="button" data-format="facebook" class="format-card p-3 rounded-xl border-2 border-slate-200 text-left">
                    <span class="check-badge"><i class="iconoir-check"></i></span>
                    <div class="format-icon w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-facebook text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">Facebook</div>
                    <div class="text-xs text-slate-500">Post</div>
                  </button>
                  
                  <button type="button" data-format="linkedin" class="format-card p-3 rounded-xl border-2 border-slate-200 text-left">
                    <span class="check-badge"><i class="iconoir-check"></i></span>
                    <div class="format-icon w-10 h-10 rounded-lg bg-sky-700 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-linkedin text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">LinkedIn</div>
                    <div class="text-xs text-slate-500">Professional</div>
                  </button>
                  
                  <button type="button" data-format="twitter" class="format-card p-3 rounded-xl border-2 border-slate-200 text-left">
                    <span class="check-badge"><i class="iconoir-check"></i></span>
                    <div class="format-icon w-10 h-10 rounded-lg bg-slate-900 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-x text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">X (Twitter)</div>
                    <div class="text-xs text-slate-500">Tweet/Thread</div>
                  </button>
                  
                  <!-- Contenido largo -->
                  <button type="button" data-format="blog" class="format-card p-3 rounded-xl border-2 border-slate-200 text-left">
                    <span class="check-badge"><i class="iconoir-check"></i></span>
                    <div class="format-icon w-10 h-10 rounded-lg bg-emerald-600 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-post text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">Blog</div>
                    <div class="text-xs text-slate-500">SEO article</div>
                  </button>
                  
                  <button type="button" data-format="landing" class="format-card p-3 rounded-xl border-2 border-slate-200 text-left">
                    <span class="check-badge"><i class="iconoir-check"></i></span>
                    <div class="format-icon w-10 h-10 rounded-lg bg-violet-600 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-code text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">Landing</div>
                    <div class="text-xs text-slate-500">HTML/CSS/JS</div>
                  </button>
                  
                  <button type="button" data-format="newsletter" class="format-card p-3 rounded-xl border-2 border-slate-200 text-left">
                    <span class="check-badge"><i class="iconoir-check"></i></span>
                    <div class="format-icon w-10 h-10 rounded-lg bg-amber-600 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-mail text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">Newsletter</div>
                    <div class="text-xs text-slate-500">Email</div>
                  </button>
                  
                  <button type="button" data-format="faq" class="format-card p-3 rounded-xl border-2 border-slate-200 text-left">
                    <span class="check-badge"><i class="iconoir-check"></i></span>
                    <div class="format-icon w-10 h-10 rounded-lg bg-rose-600 flex items-center justify-center text-white mb-2">
                      <i class="iconoir-help-circle text-lg"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">FAQs</div>
                    <div class="text-xs text-slate-500">Questions</div>
                  </button>
                </div>
                
                <p id="selected-count" class="text-xs text-indigo-600 font-medium mt-3">1 format selected</p>
              </div>
              
              <!-- Botón generar -->
              <button type="submit" id="generate-btn" class="w-full py-3 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                <i class="iconoir-sparks"></i>
                <span id="generate-btn-text">Transform content</span>
              </button>
              
              <!-- Progress -->
              <div id="progress-panel" class="hidden bg-indigo-50 rounded-xl p-4 border border-indigo-200">
                <div class="flex items-center gap-3">
                  <div class="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                  <div>
                    <p id="progress-text" class="text-sm font-medium text-indigo-700">Processing...</p>
                    <p id="progress-detail" class="text-xs text-indigo-500">Extracting and transforming content</p>
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
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-6">
              <div class="flex-1 min-w-0">
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2 mb-1">
                  <i class="iconoir-check-circle text-green-500 shrink-0"></i>
                  <span id="result-title" class="truncate">Generated content</span>
                </h2>
                <p id="result-source" class="text-sm text-slate-500">Source: URL</p>
              </div>
              <div class="flex items-center gap-3 shrink-0">
                <button type="button" id="copy-all-btn" class="text-sm font-semibold text-slate-600 hover:text-slate-800 flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl shadow-sm hover:shadow transition-all">
                  <i class="iconoir-copy"></i>
                  <span>Copy all</span>
                </button>
                <button type="button" onclick="resetUI()" class="text-sm font-semibold text-indigo-600 hover:text-white flex items-center gap-2 px-4 py-2 bg-indigo-50 hover:bg-indigo-600 rounded-xl transition-all">
                  <i class="iconoir-plus"></i>
                  <span>New</span>
                </button>
              </div>
            </div>
            
            <!-- Tabs for format navigation (desktop only) -->
            <div id="result-tabs" class="result-tabs hidden lg:flex"></div>
            
            <!-- Contenedor de resultados -->
            <div id="result-panels"></div>
            
          </section>

        </div>
      </div>
    </main>
  </div>

  <script src="/assets/js/gesture-repurposer.js"></script>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
  
  <script>
    // Sync history with mobile drawer
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
