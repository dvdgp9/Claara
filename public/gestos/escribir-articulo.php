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
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'write-article')) {
    header('Location: /gestos/?error=no_access');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = 'gestures';

// Configuración del header unificado
$headerBackUrl = '/gestos/';
$headerBackText = 'All gestures';
$headerTitle = 'Write content';
$headerIcon = 'iconoir-page-edit';
$headerIconColor = 'from-cyan-500 to-teal-600';
$headerDrawerId = 'gesture-history-drawer';
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
            <i class="iconoir-clock text-cyan-500"></i>
            History
          </h2>
          <button id="new-content-btn" class="p-1.5 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition-smooth" title="New content">
            <i class="iconoir-plus text-lg"></i>
          </button>
        </div>
      </div>
      
      <div id="history-list" class="flex-1 overflow-auto">
        <!-- Se carga dinámicamente -->
        <div class="p-4 text-center text-slate-400 text-sm">
          <i class="iconoir-refresh animate-spin"></i>
          Loading...
        </div>
      </div>
    </aside>
    
    <!-- Mobile Drawer para historial -->
    <?php 
    $drawerId = 'gesture-history-drawer';
    $drawerTitle = 'History';
    $drawerIcon = 'iconoir-clock';
    $drawerIconColor = 'text-cyan-500';
    include __DIR__ . '/../includes/mobile-drawer.php'; 
    ?>
    
    <!-- Main content area -->
    <main class="flex-1 flex flex-col overflow-hidden min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <!-- Scrollable content -->
      <div class="flex-1 overflow-auto p-4 lg:p-6 pb-20 lg:pb-6">
        <div class="max-w-4xl mx-auto">
          <!-- Header del gesto -->
          <div class="flex items-center gap-4 mb-6">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-cyan-500 to-teal-600 flex items-center justify-center text-white shadow-lg">
              <i class="iconoir-page-edit text-xl"></i>
            </div>
            <div>
              <h1 class="text-xl font-bold text-slate-900">Write content</h1>
              <p class="text-sm text-slate-600">Generate articles, blog posts, or press releases</p>
            </div>
          </div>
    
    <!-- Formulario del gesto -->
    <form id="write-article-form" class="space-y-6 glass-strong rounded-2xl border border-slate-200/50 p-6 shadow-sm">
      
      <!-- PASO 1: Tipo de contenido -->
      <div>
        <label class="block text-sm font-semibold text-slate-700 mb-3">What type of content do you need?</label>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <label class="cursor-pointer">
            <input type="radio" name="content-type" value="informativo" class="hidden peer" checked />
            <div class="p-4 border-2 border-slate-200 rounded-xl peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 hover:border-cyan-400 transition-all h-full">
              <div class="flex items-center gap-2 mb-2">
                <i class="iconoir-journal-page text-xl text-cyan-700"></i>
                <span class="font-semibold text-slate-800">Informative article</span>
              </div>
              <p class="text-xs text-slate-500">News, current affairs, sports, culture. Objective and direct content.</p>
            </div>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="content-type" value="blog" class="hidden peer" />
            <div class="p-4 border-2 border-slate-200 rounded-xl peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 hover:border-cyan-400 transition-all h-full">
              <div class="flex items-center gap-2 mb-2">
                <i class="iconoir-post text-xl text-cyan-700"></i>
                <span class="font-semibold text-slate-800">Blog post</span>
              </div>
              <p class="text-xs text-slate-500">SEO-optimized, with keywords and web structure.</p>
            </div>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="content-type" value="nota-prensa" class="hidden peer" />
            <div class="p-4 border-2 border-slate-200 rounded-xl peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 hover:border-cyan-400 transition-all h-full">
              <div class="flex items-center gap-2 mb-2">
                <i class="iconoir-megaphone text-xl text-cyan-700"></i>
                <span class="font-semibold text-slate-800">Press release</span>
              </div>
              <p class="text-xs text-slate-500">Official announcements with professional journalistic structure.</p>
            </div>
          </label>
        </div>
      </div>
      
      <!-- Brand / context (always visible) -->
      <div class="flex gap-4 items-center p-3 bg-slate-50/80 rounded-xl border border-slate-200/50">
        <label class="text-sm font-medium text-slate-700 whitespace-nowrap">Brand / context:</label>
        <div class="flex flex-wrap gap-2">
          <label class="cursor-pointer">
            <input type="radio" name="business-line" value="brand" class="hidden peer" checked />
            <div class="px-3 py-1.5 text-sm border-2 border-slate-200 rounded-lg peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 peer-checked:text-cyan-700 hover:border-cyan-400 transition-all font-medium">
              Brand
            </div>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="business-line" value="product" class="hidden peer" />
            <div class="px-3 py-1.5 text-sm border-2 border-slate-200 rounded-lg peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 peer-checked:text-cyan-700 hover:border-cyan-400 transition-all font-medium">
              Product
            </div>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="business-line" value="team" class="hidden peer" />
            <div class="px-3 py-1.5 text-sm border-2 border-slate-200 rounded-lg peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 peer-checked:text-cyan-700 hover:border-cyan-400 transition-all font-medium">
              Team
            </div>
          </label>
        </div>
      </div>
      
      <!-- ========== CAMPOS ARTÍCULO INFORMATIVO ========== -->
      <div id="fields-informativo" class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2">Article topic</label>
          <input type="text" id="info-topic" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all bg-white/80" placeholder="Ex: New season of aquatic activities at sports centers" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Category</label>
            <select id="info-category" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 transition-all bg-white/80">
              <option value="general">General</option>
              <option value="deportes">Sports</option>
              <option value="cultura">Culture</option>
              <option value="salud">Health and wellness</option>
              <option value="empresa">Corporate</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Length</label>
            <select id="info-length" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 transition-all bg-white/80">
              <option value="300">Short (~300 words)</option>
              <option value="500" selected>Medium (~500 words)</option>
              <option value="800">Long (~800 words)</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2">Additional details <span class="font-normal text-slate-400">(optional)</span></label>
          <textarea id="info-details" rows="2" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all resize-none bg-white/80" placeholder="Extra information, specific data, desired angle..."></textarea>
        </div>
      </div>
      
      <!-- ========== CAMPOS POST DE BLOG ========== -->
      <div id="fields-blog" class="hidden space-y-4">
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2">Post topic</label>
          <input type="text" id="blog-topic" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all bg-white/80" placeholder="Ex: 5 benefits of exercising in the morning" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2">SEO keywords <span class="font-normal text-slate-400">(comma-separated)</span></label>
          <input type="text" id="blog-keywords" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all bg-white/80" placeholder="Ex: morning workout, fitness routine, health, wellness" />
        </div>
        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl">
          <div class="flex items-center gap-2 text-emerald-700">
            <i class="iconoir-check-circle"></i>
            <span class="text-sm font-medium">Automatic SEO setup</span>
          </div>
          <p class="text-xs text-emerald-600 mt-1">600-1000 words • H2/H3 structure • Meta description • Intro with keyword • Final CTA</p>
        </div>
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2">Additional instructions <span class="font-normal text-slate-400">(optional)</span></label>
          <textarea id="blog-details" rows="2" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all resize-none bg-white/80" placeholder="Specific tone, key data to include, call to action..."></textarea>
        </div>
      </div>
      
      <!-- ========== CAMPOS NOTA DE PRENSA ========== -->
      <div id="fields-nota-prensa" class="hidden space-y-4">
        <!-- Tipo de anuncio -->
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2">Announcement type</label>
          <div class="flex flex-wrap gap-2">
            <label class="cursor-pointer">
              <input type="radio" name="press-type" value="lanzamiento" class="hidden peer" checked />
              <div class="px-3 py-2 text-sm border-2 border-slate-200 rounded-lg peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 hover:border-cyan-400 transition-all flex items-center gap-1">
                <i class="iconoir-send-diagonal text-sm text-cyan-700"></i>
                <span>Launch</span>
              </div>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="press-type" value="evento" class="hidden peer" />
              <div class="px-3 py-2 text-sm border-2 border-slate-200 rounded-lg peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 hover:border-cyan-400 transition-all flex items-center gap-1">
                <i class="iconoir-calendar text-sm text-cyan-700"></i>
                <span>Event</span>
              </div>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="press-type" value="nombramiento" class="hidden peer" />
              <div class="px-3 py-2 text-sm border-2 border-slate-200 rounded-lg peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 hover:border-cyan-400 transition-all flex items-center gap-1">
                <i class="iconoir-user-star text-sm text-cyan-700"></i>
                <span>Appointment</span>
              </div>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="press-type" value="convenio" class="hidden peer" />
              <div class="px-3 py-2 text-sm border-2 border-slate-200 rounded-lg peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 hover:border-cyan-400 transition-all flex items-center gap-1">
                <i class="iconoir-community text-sm text-cyan-700"></i>
                <span>Partnership</span>
              </div>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="press-type" value="premio" class="hidden peer" />
              <div class="px-3 py-2 text-sm border-2 border-slate-200 rounded-lg peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 hover:border-cyan-400 transition-all flex items-center gap-1">
                <i class="iconoir-medal text-sm text-cyan-700"></i>
                <span>Award/Recognition</span>
              </div>
            </label>
          </div>
        </div>
        
        <!-- Datos básicos con placeholders informativos -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">What happened? <span class="text-red-500">*</span></label>
            <input type="text" id="press-what" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all bg-white/80" placeholder="The key fact or main news" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Who is involved?</label>
            <input type="text" id="press-who" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all bg-white/80" placeholder="Person, company, organization..." />
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">When?</label>
            <input type="text" id="press-when" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all bg-white/80" placeholder="Date, period, moment..." />
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Where?</label>
            <input type="text" id="press-where" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all bg-white/80" placeholder="Location, place, scope..." />
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Why?</label>
            <textarea id="press-why" rows="2" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all resize-none bg-white/80" placeholder="Reason, cause, context (only verified and reliable information)"></textarea>
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Additional information <span class="font-normal text-slate-400">(optional)</span></label>
            <textarea id="press-purpose" rows="2" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all resize-none bg-white/80" placeholder="Confirmed complementary data. Do not add anything uncertain."></textarea>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2">Statement or direct quote <span class="font-normal text-slate-400">(optional)</span></label>
          <div class="flex gap-2">
            <input type="text" id="press-quote-author" class="w-1/3 border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 transition-all bg-white/80" placeholder="Quote author" />
            <input type="text" id="press-quote-text" class="flex-1 border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500 transition-all bg-white/80" placeholder="Quote text..." />
          </div>
        </div>
        <p class="text-xs text-slate-500 italic">If you leave fields empty, the system will generate the release with available information. Required fields are marked with *. AI must not invent data (dates, names, roles, figures, etc.); always review for accuracy.</p>
      </div>
      
      <!-- Botón generar -->
      <div class="flex justify-end pt-2 border-t border-slate-200/50">
        <button type="submit" id="generate-article-btn" class="px-6 py-3 bg-gradient-to-r from-cyan-500 to-teal-600 hover:from-cyan-600 hover:to-teal-700 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-all flex items-center gap-2">
          <i class="iconoir-sparks"></i>
          <span>Generate content</span>
        </button>
      </div>
    </form>
    
    <!-- Resultado (oculto inicialmente) -->
    <div id="article-result" class="hidden mt-8 glass-strong rounded-2xl border border-slate-200/50 p-6 shadow-sm">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-800">Generated content</h2>
        <div class="flex gap-2">
          <button id="copy-article-btn" class="px-3 py-1.5 text-sm text-slate-600 hover:text-cyan-700 hover:bg-cyan-50 rounded-lg transition-smooth flex items-center gap-1.5">
            <i class="iconoir-copy"></i> Copy
          </button>
          <button id="regenerate-article-btn" class="px-3 py-1.5 text-sm text-slate-600 hover:text-cyan-700 hover:bg-cyan-50 rounded-lg transition-smooth flex items-center gap-1.5">
            <i class="iconoir-refresh"></i> Regenerate
          </button>
        </div>
      </div>
      <div id="article-content" class="prose prose-slate max-w-none"></div>
    </div>
    
    <!-- Loading -->
    <div id="article-loading" class="hidden mt-8 text-center py-12">
      <div class="inline-flex items-center gap-3 px-6 py-4 bg-cyan-500/10 rounded-xl">
        <div class="w-5 h-5 border-2 border-cyan-500 border-t-transparent rounded-full animate-spin"></div>
        <span class="text-cyan-700 font-medium">Generating content...</span>
      </div>
    </div>
        </div><!-- /max-w-4xl -->
      </div><!-- /scrollable content -->
    </main>
  </div><!-- /main container -->

  <script src="/assets/js/gesture-write-article.js"></script>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
  
  <script>
    // Sincronizar historial con drawer móvil
    document.addEventListener('DOMContentLoaded', () => {
      const desktopHistory = document.getElementById('history-list');
      const mobileDrawerContent = document.getElementById('gesture-history-drawer-content');
      
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
                closeMobileDrawer('gesture-history-drawer');
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
