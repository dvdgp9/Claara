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
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'social-media')) {
    header('Location: /gestos/?error=no_access');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = 'gestures';

// Configuración del header unificado
$headerBackUrl = '/gestos/';
$headerBackText = 'All gestures';
$headerTitle = 'Social media';
$headerIcon = 'iconoir-send-diagonal';
$headerIconColor = 'from-violet-500 to-fuchsia-600';
$headerDrawerId = 'social-history-drawer';
?><!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-mesh text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>
    
    <!-- Sidebar de historial (solo desktop) -->
    <aside id="history-sidebar" class="hidden lg:flex w-72 glass-strong border-r border-slate-200/50 flex-col shrink-0">
      <div class="p-4 border-b border-slate-200/50">
        <div class="flex items-center justify-between">
          <h2 class="font-semibold text-slate-800 flex items-center gap-2">
            <i class="iconoir-clock text-violet-500"></i>
            History
          </h2>
          <button id="new-post-btn" class="p-1.5 text-slate-400 hover:text-violet-500 hover:bg-violet-50 rounded-lg transition-smooth" title="New post">
            <i class="iconoir-plus text-lg"></i>
          </button>
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
    $drawerId = 'social-history-drawer';
    $drawerTitle = 'History';
    $drawerIcon = 'iconoir-clock';
    $drawerIconColor = 'text-violet-500';
    include __DIR__ . '/../includes/mobile-drawer.php'; 
    ?>
    
    <!-- Main content area -->
    <main class="flex-1 flex flex-col overflow-hidden min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <!-- Two-column layout (stacked en móvil) -->
      <div class="flex-1 flex flex-col lg:flex-row overflow-auto lg:overflow-hidden pb-20 lg:pb-0">
        
        <!-- LEFT: Configuration panel -->
        <div class="w-full lg:w-[420px] shrink-0 lg:border-r border-slate-200/50 lg:overflow-auto p-4 lg:p-5">
          <form id="social-media-form" class="space-y-5">
            
            <!-- INPUT DE CONTEXTO -->
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-2">
                What is this post about? <span class="text-red-500">*</span>
              </label>
              <textarea id="post-context" rows="3" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all resize-none bg-white/80 text-sm" placeholder="Ej: Hoy han terminado las obras del nuevo CUBOFIT..."></textarea>
            </div>
            
            <!-- INTENCIÓN PRINCIPAL -->
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-2">Intent</label>
              <div class="grid grid-cols-3 lg:grid-cols-5 gap-1.5">
                <label class="cursor-pointer">
                  <input type="radio" name="intention" value="informar" class="hidden peer" checked />
                  <div class="p-2 border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all text-center">
                    <i class="iconoir-megaphone text-lg text-violet-700 block"></i>
                    <span class="text-[11px] font-medium text-slate-600">Inform</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="intention" value="reforzar-marca" class="hidden peer" />
                  <div class="p-2 border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all text-center">
                    <i class="iconoir-community text-lg text-violet-700 block"></i>
                    <span class="text-[11px] font-medium text-slate-600">Brand</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="intention" value="conectar" class="hidden peer" />
                  <div class="p-2 border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all text-center">
                    <i class="iconoir-chat-bubble text-lg text-violet-700 block"></i>
                    <span class="text-[11px] font-medium text-slate-600">Connect</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="intention" value="activar" class="hidden peer" />
                  <div class="p-2 border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all text-center">
                    <i class="iconoir-flash text-lg text-violet-700 block"></i>
                    <span class="text-[11px] font-medium text-slate-600">Activate</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="intention" value="aportar-valor" class="hidden peer" />
                  <div class="p-2 border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all text-center">
                    <i class="iconoir-light-bulb text-lg text-violet-700 block"></i>
                    <span class="text-[11px] font-medium text-slate-600">Value</span>
                  </div>
                </label>
              </div>
            </div>
            
            <!-- BRAND / CONTEXT -->
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-2">Brand / context</label>
              <div class="grid grid-cols-3 gap-2">
                <label class="cursor-pointer">
                  <input type="radio" name="business-line" value="brand" class="hidden peer" checked />
                  <div class="px-2 py-2 text-xs border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 peer-checked:text-violet-700 hover:border-violet-400 transition-all font-medium text-center">
                    Brand
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="business-line" value="service" class="hidden peer" />
                  <div class="px-2 py-2 text-xs border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 peer-checked:text-violet-700 hover:border-violet-400 transition-all font-medium text-center">
                    Service
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="business-line" value="product" class="hidden peer" />
                  <div class="px-2 py-2 text-xs border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 peer-checked:text-violet-700 hover:border-violet-400 transition-all font-medium text-center">
                    Product
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="business-line" value="team" class="hidden peer" />
                  <div class="px-2 py-2 text-xs border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 peer-checked:text-violet-700 hover:border-violet-400 transition-all font-medium text-center">
                    Team
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="business-line" value="campaign" class="hidden peer" />
                  <div class="px-2 py-2 text-xs border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 peer-checked:text-violet-700 hover:border-violet-400 transition-all font-medium text-center">
                    Campaign
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="business-line" value="community" class="hidden peer" />
                  <div class="px-2 py-2 text-xs border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 peer-checked:text-violet-700 hover:border-violet-400 transition-all font-medium text-center">
                    Community
                  </div>
                </label>
              </div>
            </div>
            
            <!-- CANAL -->
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-2">Channel</label>
              <div class="grid grid-cols-2 gap-2">
                <label class="cursor-pointer">
                  <input type="radio" name="channel" value="instagram" class="hidden peer" checked />
                  <div class="px-3 py-2 text-sm border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 peer-checked:text-violet-700 hover:border-violet-400 transition-all font-medium text-center flex items-center justify-center gap-1">
                    <i class="iconoir-instagram"></i> Instagram
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="channel" value="facebook" class="hidden peer" />
                  <div class="px-3 py-2 text-sm border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 peer-checked:text-violet-700 hover:border-violet-400 transition-all font-medium text-center flex items-center justify-center gap-1">
                    <i class="iconoir-facebook"></i> Facebook
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="channel" value="linkedin" class="hidden peer" />
                  <div class="px-3 py-2 text-sm border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 peer-checked:text-violet-700 hover:border-violet-400 transition-all font-medium text-center flex items-center justify-center gap-1">
                    <i class="iconoir-linkedin"></i> LinkedIn
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="channel" value="transversal" class="hidden peer" />
                  <div class="px-3 py-2 text-sm border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 peer-checked:text-violet-700 hover:border-violet-400 transition-all font-medium text-center flex items-center justify-center gap-1">
                    <i class="iconoir-multi-window"></i> Multi
                  </div>
                </label>
              </div>
            </div>

            <!-- LONGITUD -->
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-2">Length</label>
              <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                <label class="cursor-pointer">
                  <input type="radio" name="length" value="" class="hidden peer" checked />
                  <div class="px-2 py-2 text-xs border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all text-center font-medium text-slate-600">Auto</div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="length" value="corto" class="hidden peer" />
                  <div class="px-2 py-2 text-xs border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all text-center font-medium text-slate-600">Short</div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="length" value="medio" class="hidden peer" />
                  <div class="px-2 py-2 text-xs border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all text-center font-medium text-slate-600">Medium</div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="length" value="largo" class="hidden peer" />
                  <div class="px-2 py-2 text-xs border-2 border-slate-200 rounded-lg peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all text-center font-medium text-slate-600">Largo</div>
                </label>
              </div>
            </div>
            
            <!-- OPCIONES AVANZADAS -->
            <details class="group">
              <summary class="cursor-pointer text-xs font-medium text-slate-500 hover:text-violet-600 transition-colors flex items-center gap-1">
                <i class="iconoir-settings"></i>
                Advanced options
                <i class="iconoir-nav-arrow-down text-[10px] transition-transform group-open:rotate-180"></i>
              </summary>
              
              <div class="mt-3 space-y-3 pt-3 border-t border-slate-200/50">
                <!-- Enfoque narrativo -->
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1.5">Narrative focus</label>
                  <div class="flex flex-wrap gap-1.5">
                    <label class="cursor-pointer">
                      <input type="radio" name="narrative" value="" class="hidden peer" checked />
                      <div class="px-2 py-1 text-xs border border-slate-200 rounded peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all">Auto</div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="narrative" value="personas" class="hidden peer" />
                      <div class="px-2 py-1 text-xs border border-slate-200 rounded peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all">People</div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="narrative" value="proyecto" class="hidden peer" />
                      <div class="px-2 py-1 text-xs border border-slate-200 rounded peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all">Project</div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="narrative" value="detalle" class="hidden peer" />
                      <div class="px-2 py-1 text-xs border border-slate-200 rounded peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all">Detail</div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="narrative" value="impacto" class="hidden peer" />
                      <div class="px-2 py-1 text-xs border border-slate-200 rounded peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all">Impact</div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="narrative" value="vision" class="hidden peer" />
                      <div class="px-2 py-1 text-xs border border-slate-200 rounded peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all">Vision</div>
                    </label>
                  </div>
                </div>
                
                <!-- Cierre -->
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1.5">Closing type</label>
                  <div class="flex flex-wrap gap-1.5">
                    <label class="cursor-pointer">
                      <input type="radio" name="closing" value="" class="hidden peer" checked />
                      <div class="px-2 py-1 text-xs border border-slate-200 rounded peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all">Auto</div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="closing" value="informativo" class="hidden peer" />
                      <div class="px-2 py-1 text-xs border border-slate-200 rounded peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all">Informative</div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="closing" value="inspirador" class="hidden peer" />
                      <div class="px-2 py-1 text-xs border border-slate-200 rounded peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all">Inspirational</div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="closing" value="cta-suave" class="hidden peer" />
                      <div class="px-2 py-1 text-xs border border-slate-200 rounded peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all">Soft CTA</div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="closing" value="cta-claro" class="hidden peer" />
                      <div class="px-2 py-1 text-xs border border-slate-200 rounded peer-checked:border-violet-500 peer-checked:bg-violet-500/10 hover:border-violet-400 transition-all">Clear CTA</div>
                    </label>
                  </div>
                </div>
              </div>
            </details>
            
            <!-- Botón generar -->
            <button type="submit" id="generate-post-btn" class="w-full py-3 bg-gradient-to-r from-violet-500 to-fuchsia-600 hover:from-violet-600 hover:to-fuchsia-700 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
              <i class="iconoir-sparks"></i>
              <span>Generate post</span>
            </button>
            
            <!-- Resumen editorial (colapsado debajo del botón) -->
            <div id="editorial-panel" class="hidden">
              <details class="bg-slate-50/80 rounded-lg border border-slate-200/50 p-3" open>
                <summary class="cursor-pointer text-xs font-semibold text-slate-600 flex items-center gap-1">
                  <i class="iconoir-clipboard-check text-slate-400"></i>
                  Editorial summary
                </summary>
                <div id="editorial-summary" class="mt-2 pt-2 border-t border-slate-200/50 text-xs text-slate-500 space-y-0.5"></div>
              </details>
            </div>
          </form>
        </div>
        
        <!-- RIGHT: Result panel -->
        <div class="flex-1 lg:overflow-auto p-4 lg:p-6 bg-slate-50/30">
          
          <!-- Estado inicial: placeholder -->
          <div id="result-placeholder" class="h-full flex items-center justify-center">
            <div class="text-center max-w-sm">
              <div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-violet-500/20 to-fuchsia-600/20 flex items-center justify-center mx-auto mb-5">
                <i class="iconoir-send-diagonal text-4xl text-violet-400"></i>
              </div>
              <h3 class="text-lg font-semibold text-slate-700 mb-2">Your post will appear here</h3>
              <p class="text-sm text-slate-500">Configure the options on the left and click "Generate post"</p>
            </div>
          </div>
          
          <!-- Loading -->
          <div id="post-loading" class="hidden h-full flex items-center justify-center">
            <div class="text-center">
              <div class="w-16 h-16 rounded-2xl bg-violet-500/10 flex items-center justify-center mx-auto mb-4">
                <div class="w-8 h-8 border-3 border-violet-500 border-t-transparent rounded-full animate-spin"></div>
              </div>
              <p class="text-violet-700 font-medium">Building post...</p>
            </div>
          </div>
          
          <!-- Resultado -->
          <div id="post-result" class="hidden space-y-4">
            
            <!-- Publicación -->
            <div class="glass-strong rounded-2xl border border-slate-200/50 p-5 shadow-sm">
              <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-semibold text-slate-800 flex items-center gap-2">
                  <i class="iconoir-post text-violet-500"></i>
                  Post
                </h2>
                <div class="flex gap-1">
                  <button id="copy-post-btn" class="px-2.5 py-1 text-xs text-slate-500 hover:text-violet-700 hover:bg-violet-50 rounded-lg transition-smooth flex items-center gap-1">
                    <i class="iconoir-copy"></i> Copy
                  </button>
                  <button id="regenerate-post-btn" class="px-2.5 py-1 text-xs text-slate-500 hover:text-violet-700 hover:bg-violet-50 rounded-lg transition-smooth flex items-center gap-1">
                    <i class="iconoir-refresh"></i> Regenerate
                  </button>
                </div>
              </div>
              <div id="post-content" class="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed"></div>
            </div>
            
            <!-- Hashtags -->
            <div class="glass rounded-xl border border-slate-200/50 p-4">
              <div class="flex items-center justify-between mb-2">
                <h3 class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                  <i class="iconoir-hashtag text-violet-500"></i>
                  Hashtags
                </h3>
                <button id="copy-hashtags-btn" class="text-[10px] text-slate-400 hover:text-violet-600 transition-colors flex items-center gap-1">
                  <i class="iconoir-copy"></i> Copy
                </button>
              </div>
              <div id="hashtags-content" class="text-sm text-violet-600 font-medium"></div>
            </div>
            
            <!-- Quick variants -->
            <div class="glass rounded-xl border border-slate-200/50 p-4">
              <h3 class="text-xs font-semibold text-slate-600 mb-3 flex items-center gap-1.5">
                <i class="iconoir-refresh-double text-violet-500"></i>
                Quick variants
              </h3>
              <div class="flex flex-wrap gap-2">
                <button data-variant="cercano" class="variant-btn px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:border-violet-400 hover:bg-violet-50 transition-all">
                  More conversational
                </button>
                <button data-variant="institucional" class="variant-btn px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:border-violet-400 hover:bg-violet-50 transition-all">
                  More formal
                </button>
                <button data-variant="corto" class="variant-btn px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:border-violet-400 hover:bg-violet-50 transition-all">
                  Shorter
                </button>
                <button data-variant="directo" class="variant-btn px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:border-violet-400 hover:bg-violet-50 transition-all">
                  More direct
                </button>
                <button data-variant="emocional" class="variant-btn px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:border-violet-400 hover:bg-violet-50 transition-all">
                  More emotional
                </button>
              </div>
            </div>
            
          </div>
        </div>
        
      </div><!-- /two-column layout -->
    </main>
  </div><!-- /main container -->

  <script src="/assets/js/gesture-social-media.js"></script>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
  
  <script>
    // Sincronizar historial con drawer móvil
    document.addEventListener('DOMContentLoaded', () => {
      const desktopHistory = document.getElementById('history-list');
      const mobileDrawerContent = document.getElementById('social-history-drawer-content');
      
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
                closeMobileDrawer('social-history-drawer');
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
