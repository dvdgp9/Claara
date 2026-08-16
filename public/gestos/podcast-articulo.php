<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Repos/UserFeatureAccessRepo.php';

use App\Session;
use I18n\I18n;
use Repos\UserFeatureAccessRepo;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

// Verificar acceso a este gesto
$accessRepo = new UserFeatureAccessRepo();
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'podcast-from-article')) {
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
$headerBackText = I18n::translate('podcast_ui.all_gestures');
$headerTitle = I18n::translate('podcast_ui.title');
$headerIcon = 'iconoir-podcast';
$headerIconColor = 'from-red-500 to-orange-500';
$headerDrawerId = 'podcast-history-drawer';
?><!DOCTYPE html>
<html lang="<?= htmlspecialchars(I18n::htmlLang()) ?>">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="podcast-page bg-mesh text-slate-900 overflow-hidden">
  <div class="min-h-[100dvh] flex h-[100dvh]">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>
    
    <!-- Sidebar de historial (solo desktop) -->
    <aside id="history-sidebar" class="hidden lg:flex w-72 glass-strong border-r border-slate-200/50 flex-col shrink-0">
      <div class="p-4 border-b border-slate-200/50">
        <div class="flex items-center justify-between">
          <h2 class="font-semibold text-slate-800 flex items-center gap-2">
            <i class="iconoir-clock text-orange-500"></i>
            <?= htmlspecialchars(I18n::translate('podcast_ui.history')) ?>
          </h2>
        </div>
      </div>
      
      <div id="history-list" class="flex-1 overflow-auto">
        <div class="p-4 text-center text-slate-400 text-sm">
          <i class="iconoir-refresh animate-spin"></i>
          <?= htmlspecialchars(I18n::translate('podcast_ui.loading')) ?>
        </div>
      </div>
    </aside>
    
    <!-- Mobile Drawer para historial -->
    <?php 
    $drawerId = 'podcast-history-drawer';
    $drawerTitle = I18n::translate('podcast_ui.history');
    $drawerIcon = 'iconoir-clock';
    $drawerIconColor = 'text-orange-500';
    include __DIR__ . '/../includes/mobile-drawer.php'; 
    ?>
    
    <!-- Main content area -->
    <main class="flex-1 flex flex-col overflow-hidden min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <!-- Single column layout (contenido) -->
      <div class="flex-1 overflow-auto pb-16 lg:pb-0">
        <div class="max-w-2xl mx-auto p-4 lg:p-6 space-y-4 lg:space-y-6">
          
          <!-- Intro -->
          <div class="text-center mb-6">
            <h1 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-orange-600 to-red-600 mb-2">
              <?= htmlspecialchars(I18n::translate('podcast_ui.heading')) ?>
            </h1>
            <p class="text-slate-500 max-w-lg mx-auto">
              <?= htmlspecialchars(I18n::translate('podcast_ui.subtitle')) ?>
            </p>
          </div>

          <!-- Input Section -->
          <section id="podcast-input-section" class="glass-strong rounded-2xl p-6 border border-slate-200/50">
            <form id="podcast-form" class="space-y-5">
              
              <!-- Fuente del artículo -->
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-3">
                  <i class="iconoir-link text-orange-500 mr-1"></i>
                  <?= htmlspecialchars(I18n::translate('podcast_ui.source')) ?>
                </label>
                
                <!-- Tabs -->
                <div class="flex gap-2 mb-3">
                  <button type="button" data-tab="url" class="tab-btn active px-4 py-2 text-sm font-medium rounded-lg transition-all bg-orange-100 text-orange-700">
                    <i class="iconoir-link mr-1"></i> URL
                  </button>
                  <button type="button" data-tab="text" class="tab-btn px-4 py-2 text-sm font-medium rounded-lg transition-all bg-slate-100 text-slate-600 hover:bg-slate-200">
                    <i class="iconoir-text mr-1"></i> <?= htmlspecialchars(I18n::translate('podcast_ui.text')) ?>
                  </button>
                  <button type="button" data-tab="pdf" class="tab-btn px-4 py-2 text-sm font-medium rounded-lg transition-all bg-slate-100 text-slate-600 hover:bg-slate-200">
                    <i class="iconoir-page mr-1"></i> PDF
                  </button>
                </div>

                <!-- URL Input -->
                <div id="tab-url" class="tab-content">
                  <input type="url" id="article-url" 
                         class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-all"
                         placeholder="https://example.com/interesting-article" />
                  <p class="text-xs text-slate-500 mt-2"><?= htmlspecialchars(I18n::translate('podcast_ui.url_help')) ?></p>
                </div>

                <!-- Text Input -->
                <div id="tab-text" class="tab-content hidden">
                  <textarea id="article-text" rows="6"
                            class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-all resize-none"
                            placeholder="<?= htmlspecialchars(I18n::translate('podcast_ui.text_placeholder')) ?>"></textarea>
                  <p class="text-xs text-slate-500 mt-2"><?= htmlspecialchars(I18n::translate('podcast_ui.text_help')) ?></p>
                </div>

                <!-- PDF Input -->
                <div id="tab-pdf" class="tab-content hidden">
                  <label class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed border-slate-300 rounded-xl cursor-pointer hover:border-orange-400 hover:bg-orange-50/50 transition-all">
                    <i class="iconoir-upload text-2xl text-slate-400 mb-2"></i>
                    <span class="text-sm text-slate-500"><?= htmlspecialchars(I18n::translate('podcast_ui.pdf_drop')) ?></span>
                    <input type="file" id="article-pdf" accept=".pdf" class="hidden" />
                  </label>
                  <p id="pdf-filename" class="text-xs text-slate-500 mt-2 hidden"></p>
                </div>
              </div>
              
              <!-- Botón generar -->
              <button type="submit" id="generate-btn" class="w-full py-3 bg-gradient-to-r from-red-500 to-orange-500 hover:from-red-600 hover:to-orange-600 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                <i class="iconoir-sparks"></i>
                <span><?= htmlspecialchars(I18n::translate('podcast_ui.generate')) ?></span>
              </button>
              
              <!-- Progress -->
              <div id="progress-panel" class="hidden bg-orange-50 rounded-xl p-4 border border-orange-200">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex items-center gap-3">
                    <div class="flex gap-0.5">
                      <div class="w-1 h-6 bg-orange-500 rounded-full wave-bar"></div>
                      <div class="w-1 h-6 bg-orange-500 rounded-full wave-bar"></div>
                      <div class="w-1 h-6 bg-orange-500 rounded-full wave-bar"></div>
                      <div class="w-1 h-6 bg-orange-500 rounded-full wave-bar"></div>
                      <div class="w-1 h-6 bg-orange-500 rounded-full wave-bar"></div>
                    </div>
                    <div>
                      <p id="progress-text" class="text-sm font-medium text-orange-700"><?= htmlspecialchars(I18n::translate('podcast_ui.processing')) ?></p>
                      <p id="progress-detail" class="text-xs text-orange-500"><?= htmlspecialchars(I18n::translate('podcast_ui.processing_help')) ?></p>
                    </div>
                  </div>
                  <button type="button" id="cancel-btn" class="px-3 py-1.5 text-xs bg-white hover:bg-red-50 text-slate-600 hover:text-red-600 border border-slate-200 hover:border-red-300 rounded-lg transition-colors flex items-center gap-1.5" title="<?= htmlspecialchars(I18n::translate('podcast_ui.cancel_title')) ?>">
                    <i class="iconoir-xmark text-sm"></i>
                    <span><?= htmlspecialchars(I18n::translate('podcast_ui.cancel')) ?></span>
                  </button>
                </div>
              </div>
              
              <!-- Error -->
              <div id="error-panel" class="hidden bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="flex items-start gap-2">
                  <i class="iconoir-warning-triangle text-red-500"></i>
                  <div>
                    <p class="text-sm font-medium text-red-800"><?= htmlspecialchars(I18n::translate('podcast_ui.error')) ?></p>
                    <p id="error-message" class="text-xs text-red-600 mt-0.5"></p>
                  </div>
                </div>
              </div>
            </form>
          </section>

          <!-- Result Section -->
          <section id="podcast-result" class="hidden space-y-4">
            
            <!-- Result Header -->
            <div class="flex items-center justify-between mb-2">
              <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i class="iconoir-check-circle text-green-500"></i>
                <?= htmlspecialchars(I18n::translate('podcast_ui.generated')) ?>
              </h2>
              <button type="button" id="new-podcast-btn" class="text-sm font-medium text-orange-600 hover:text-orange-700 flex items-center gap-1.5 px-3 py-1.5 bg-orange-50 rounded-lg transition-colors">
                <i class="iconoir-plus"></i>
                <span><?= htmlspecialchars(I18n::translate('podcast_ui.new')) ?></span>
              </button>
            </div>
            
            <!-- Audio Player -->
            <div class="audio-player-warm rounded-2xl p-6 text-white shadow-xl shadow-orange-900/10">
              <div class="flex items-start gap-4 mb-6">
                <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center shrink-0 shadow-inner">
                  <i class="iconoir-podcast text-3xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <h3 id="podcast-title" class="font-bold text-xl truncate tracking-tight"><?= htmlspecialchars(I18n::translate('podcast_ui.generated')) ?></h3>
                  <p id="podcast-summary" class="text-sm text-white/80 line-clamp-2 mt-1 font-medium leading-relaxed"></p>
                </div>
              </div>
              
              <audio id="audio-player" controls class="w-full mb-6 custom-audio-player"></audio>
              
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-xs text-white/60">
                  <i class="iconoir-voice-square"></i>
                  <span><?= htmlspecialchars(I18n::translate('podcast_ui.hosted_by')) ?></span>
                </div>
                <button id="download-btn" class="px-5 py-2.5 bg-white/20 hover:bg-white/30 backdrop-blur-md rounded-xl transition-all flex items-center gap-2 font-semibold text-sm shadow-sm border border-white/10">
                  <i class="iconoir-download"></i> <?= htmlspecialchars(I18n::translate('podcast_ui.download')) ?>
                </button>
              </div>
            </div>

            <!-- Script Section -->
            <details class="glass-strong rounded-2xl border border-slate-200/50 overflow-hidden shadow-sm">
              <summary class="px-6 py-4 cursor-pointer hover:bg-slate-50 transition-colors flex items-center justify-between group">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                    <i class="iconoir-page text-orange-600"></i>
                  </div>
                  <span class="font-bold text-slate-700"><?= htmlspecialchars(I18n::translate('podcast_ui.transcript')) ?></span>
                </div>
                <i class="iconoir-nav-arrow-down text-slate-400 group-hover:text-orange-500 transition-transform duration-300"></i>
              </summary>
              <div class="px-6 pb-6 pt-2">
                <div class="bg-white/50 rounded-xl p-4 border border-slate-100">
                  <pre id="podcast-script" class="text-sm text-slate-600 whitespace-pre-wrap font-sans leading-relaxed max-h-[500px] overflow-y-auto"></pre>
                </div>
              </div>
            </details>
          </section>

        </div>
      </div>
    </main>
  </div>

  <script>
    window.CSRF_TOKEN = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.CLAARA_PODCAST_I18N = <?= I18n::javascriptCatalogPrefixJson('podcast_ui.') ?>;
  </script>
  <script src="/assets/js/gesture-podcast.js"></script>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
  
  <script>
    // Sincronizar historial con drawer móvil
    document.addEventListener('DOMContentLoaded', () => {
      const desktopHistory = document.getElementById('history-list');
      const mobileDrawerContent = document.getElementById('podcast-history-drawer-content');
      
      function syncDrawerContent() {
        if (desktopHistory && mobileDrawerContent) {
          mobileDrawerContent.innerHTML = desktopHistory.innerHTML;
          // Forzar visibilidad de acciones en móvil (no hay hover)
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
        
        // Event delegation para clics en el drawer móvil
        mobileDrawerContent.addEventListener('click', (e) => {
          // Clic en el botón de eliminar
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
          
          // Clic en el item principal (cargar contenido)
          const historyItemMain = e.target.closest('.history-item-main');
          if (historyItemMain) {
            const historyItem = historyItemMain.closest('.history-item');
            if (historyItem) {
              const id = historyItem.dataset.id;
              const desktopItemMain = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-main`);
              if (desktopItemMain) {
                closeMobileDrawer('podcast-history-drawer');
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
