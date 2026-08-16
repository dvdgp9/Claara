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

// Verify access to this voice
$voiceResolver = new \Voices\VoiceAccessResolver();
if (!$voiceResolver->canAccessSlug((int)$user['id'], 'lex')) {
    header('Location: /app/?error=no_access');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = 'voices';
$userName = htmlspecialchars($user['first_name'] ?? 'there');

// Shared header configuration
$headerBackUrl = '/app/';
$headerBackText = I18n::translate('voice_ui.home');
$headerTitle = 'Lex';
$headerSubtitle = I18n::translate('voice.legal_assistant');
$headerIconText = 'L';
$headerIconColor = 'from-rose-500 to-pink-600';
$headerCustomButtons = '
  <button onclick="openMobileDrawer(\'lex-docs-drawer\')" class="lg:hidden flex items-center gap-2 px-3 py-1.5 text-sm text-slate-600 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-smooth">
    <i class="iconoir-folder"></i>
    <span>' . htmlspecialchars(I18n::translate('voice_ui.docs')) . '</span>
  </button>
  <button id="toggle-docs-panel" class="hidden lg:flex items-center gap-2 px-3 py-1.5 text-sm text-slate-600 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-smooth">
    <i class="iconoir-folder"></i>
    <span>' . htmlspecialchars(I18n::translate('voice_ui.documents')) . '</span>
    <i class="iconoir-nav-arrow-right text-xs" id="docs-arrow"></i>
  </button>';
$headerDrawerId = 'lex-history-drawer';
$voiceJs = I18n::javascriptCatalogPrefixJson('voice_ui.');
?><!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::htmlLang()); ?>">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-mesh text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>
    
    <!-- History sidebar (desktop only) -->
    <aside id="history-sidebar" class="hidden lg:flex w-72 glass-strong border-r border-slate-200/50 flex-col shrink-0">
      <div class="p-4 border-b border-slate-200/50">
        <div class="flex items-center justify-between">
          <h2 class="font-semibold text-slate-800 flex items-center gap-2">
            <i class="iconoir-clock text-rose-500"></i>
            <?php echo htmlspecialchars(I18n::translate('voice_ui.history')); ?>
          </h2>
          <button id="new-chat-btn" class="p-1.5 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-smooth" title="<?php echo htmlspecialchars(I18n::translate('voice_ui.new_query')); ?>">
            <i class="iconoir-plus text-lg"></i>
          </button>
        </div>
      </div>
      
      <div id="history-list" class="flex-1 overflow-auto">
        <!-- Loaded dynamically -->
        <div class="p-4 text-center text-slate-400 text-sm">
          <i class="iconoir-refresh animate-spin"></i>
          <?php echo htmlspecialchars(I18n::translate('voice_ui.loading')); ?>
        </div>
      </div>
    </aside>
    
    <!-- Mobile drawer for history -->
    <?php 
    $drawerId = 'lex-history-drawer';
    $drawerTitle = I18n::translate('voice_ui.history');
    $drawerIcon = 'iconoir-clock';
    $drawerIconColor = 'text-rose-500';
    $drawerShowNewButton = true;
    $drawerNewButtonId = 'mobile-new-chat-btn';
    $drawerNewButtonText = I18n::translate('voice_ui.new_query');
    include __DIR__ . '/../includes/mobile-drawer.php'; 
    ?>

    <!-- Mobile drawer for documents -->
    <?php 
    $drawerId = 'lex-docs-drawer';
    $drawerTitle = I18n::translate('voice_ui.documents');
    $drawerIcon = 'iconoir-folder';
    $drawerIconColor = 'text-rose-500';
    $drawerShowNewButton = false;
    include __DIR__ . '/../includes/mobile-drawer.php'; 
    ?>
    
    <!-- Main content area -->
    <main class="flex-1 flex flex-col overflow-hidden min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <!-- Content area with optional docs panel -->
      <div class="flex-1 flex overflow-hidden pb-16 lg:pb-0">
        
        <!-- Chat area -->
        <div class="flex-1 flex flex-col bg-mesh min-w-0 overflow-hidden">
          
          <!-- Messages -->
          <div id="messages-container" class="flex-1 overflow-auto p-4 lg:p-6 pb-[140px] lg:pb-0">
            <!-- Empty state -->
            <div id="empty-state" class="h-full flex items-center justify-center">
              <div class="text-center max-w-lg">
                <div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-rose-500 to-pink-600 flex items-center justify-center mx-auto mb-6 shadow-xl animate-float">
                  <span class="text-4xl font-bold text-white">L</span>
                </div>
                <h2 class="text-2xl font-bold text-slate-900 mb-3"><?php echo htmlspecialchars(I18n::translate('voice_ui.greeting', ['name' => html_entity_decode($userName)])); ?></h2>
                <p class="text-slate-600 mb-6">
                  <?php echo htmlspecialchars(I18n::translate('voice_ui.lex_intro')); ?>
                </p>
                
                <!-- Suggestions -->
                <div class="space-y-2">
                  <p class="text-xs text-slate-400 uppercase tracking-wider mb-3"><?php echo htmlspecialchars(I18n::translate('voice_ui.try_asking')); ?></p>
                  <button class="suggestion-btn w-full p-3 glass border border-slate-200/50 hover:border-rose-300 rounded-xl text-left transition-smooth group">
                    <span class="text-sm text-slate-700 group-hover:text-rose-600"><?php echo htmlspecialchars(I18n::translate('voice_ui.lex_question_vacation')); ?></span>
                  </button>
                  <button class="suggestion-btn w-full p-3 glass border border-slate-200/50 hover:border-rose-300 rounded-xl text-left transition-smooth group">
                    <span class="text-sm text-slate-700 group-hover:text-rose-600"><?php echo htmlspecialchars(I18n::translate('voice_ui.lex_question_leave')); ?></span>
                  </button>
                  <button class="suggestion-btn w-full p-3 glass border border-slate-200/50 hover:border-rose-300 rounded-xl text-left transition-smooth group">
                    <span class="text-sm text-slate-700 group-hover:text-rose-600"><?php echo htmlspecialchars(I18n::translate('voice_ui.lex_question_overtime')); ?></span>
                  </button>
                </div>
              </div>
            </div>
            
            <!-- Messages list (hidden initially) -->
            <div id="messages" class="hidden space-y-6 max-w-4xl mx-auto"></div>
            
            <!-- Typing indicator -->
            <div id="typing-indicator" class="hidden max-w-4xl mx-auto">
              <div class="flex gap-3 items-start">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">L</div>
                <div class="glass border border-slate-200/50 px-5 py-3.5 rounded-2xl rounded-tl-sm shadow-sm">
                  <div class="flex gap-1.5">
                    <div class="w-2 h-2 bg-rose-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                    <div class="w-2 h-2 bg-rose-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                    <div class="w-2 h-2 bg-rose-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Input area -->
          <footer class="fixed lg:relative bottom-16 lg:bottom-0 left-0 right-0 p-3 lg:p-6 bg-white border-t border-slate-200 shadow-lg z-40">
            <form id="chat-form" class="max-w-4xl mx-auto">
              <div class="flex gap-2 lg:gap-3 items-center">
                <textarea 
                  id="chat-input" rows="1"
                  class="flex-1 min-w-0 border-2 border-slate-200 rounded-xl px-3 lg:px-4 py-2.5 input-focus transition-smooth bg-white/80 resize-none overflow-hidden"
                  placeholder="<?php echo htmlspecialchars(I18n::translate('voice_ui.lex_placeholder')); ?>"
                  style="min-height: 40px; max-height: 120px;"
                ></textarea>
                <button type="submit" class="h-11 p-3 lg:px-6 lg:py-[10px] bg-gradient-to-r from-rose-500 to-pink-600 text-white rounded-xl font-medium shadow-md hover:shadow-lg hover:opacity-90 transition-all duration-200 flex items-center justify-center gap-2">
                  <span class="hidden lg:inline"><?php echo htmlspecialchars(I18n::translate('voice_ui.send')); ?></span>
                  <i class="iconoir-send-diagonal text-base"></i>
                </button>
              </div>
            </form>
          </footer>
        </div>
        
        <!-- Documents panel (collapsible) -->
        <aside id="docs-panel" class="hidden w-80 glass-strong border-l border-slate-200/50 flex flex-col shrink-0">
          <div class="p-4 border-b border-slate-200/50">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2">
              <i class="iconoir-folder text-rose-500"></i>
              <?php echo htmlspecialchars(I18n::translate('voice_ui.available_documents')); ?>
            </h3>
            <p class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars(I18n::translate('voice_ui.knowledge_sources')); ?></p>
            
            <!-- Document search -->
            <div class="mt-3 relative">
              <i class="iconoir-search text-xs text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
              <input type="text" id="docs-search" 
                class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-rose-300 transition-colors" 
                placeholder="<?php echo htmlspecialchars(I18n::translate('voice_ui.search_documents')); ?>">
            </div>
          </div>
          
          <div id="docs-list" class="flex-1 overflow-auto p-4 space-y-2">
            <!-- Loaded dynamically -->
            <div class="p-4 text-center text-slate-400 text-sm">
              <i class="iconoir-refresh animate-spin"></i>
              <?php echo htmlspecialchars(I18n::translate('voice_ui.loading_documents')); ?>
            </div>
          </div>
          
          <div class="p-4 border-t border-slate-200/50">
            <p class="text-xs text-slate-400 text-center">
              <i class="iconoir-info-circle"></i>
              <?php echo htmlspecialchars(I18n::translate('voice_ui.uses_documents')); ?>
            </p>
          </div>
        </aside>
        
      </div>
    </main>
  </div>

  <!-- Document viewer modal -->
  <div id="doc-viewer-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="glass-strong rounded-3xl shadow-2xl w-full max-w-4xl max-h-[85vh] flex flex-col border border-slate-200/50">
      <!-- Header -->
      <div class="p-5 border-b border-slate-200/50 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center">
            <i class="iconoir-page text-xl text-rose-600"></i>
          </div>
          <div>
            <h3 id="doc-viewer-title" class="text-lg font-semibold text-slate-900"><?php echo htmlspecialchars(I18n::translate('voice_ui.document')); ?></h3>
            <p class="text-xs text-slate-500"><?php echo htmlspecialchars(I18n::translate('voice_ui.reference_document')); ?></p>
          </div>
        </div>
        <button id="close-doc-viewer" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-smooth">
          <i class="iconoir-xmark text-xl"></i>
        </button>
      </div>
      
      <!-- Content -->
      <div id="doc-viewer-content" class="flex-1 overflow-y-auto p-6">
        <div class="prose prose-slate max-w-none">
          <div class="text-center text-slate-400 py-8">
            <i class="iconoir-refresh animate-spin text-2xl mb-2"></i>
            <p><?php echo htmlspecialchars(I18n::translate('voice_ui.loading_document')); ?></p>
          </div>
        </div>
      </div>
      
      <!-- Footer -->
      <div class="p-4 border-t border-slate-200/50 flex justify-end flex-shrink-0">
        <button id="close-doc-viewer-btn" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-smooth">
          <?php echo htmlspecialchars(I18n::translate('voice_ui.close')); ?>
        </button>
      </div>
    </div>
  </div>

  <script>window.CLAARA_VOICE_I18N = <?php echo $voiceJs; ?>;</script>
  <script src="/assets/js/voice-lex.js"></script>
  
  <!-- Bottom Navigation (mobile) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
  
  <script>
    // Sync history with mobile drawer.
    document.addEventListener('DOMContentLoaded', () => {
      const desktopHistory = document.getElementById('history-list');
      const mobileDrawerContent = document.getElementById('lex-history-drawer-content');
      
      if (desktopHistory && mobileDrawerContent) {
        mobileDrawerContent.innerHTML = desktopHistory.innerHTML;
        
        const observer = new MutationObserver(() => {
          mobileDrawerContent.innerHTML = desktopHistory.innerHTML;
        });
        observer.observe(desktopHistory, { childList: true, subtree: true });
        
        // Event delegation para clics en el drawer móvil
        mobileDrawerContent.addEventListener('click', (e) => {
          const historyItem = e.target.closest('[data-history-id], [data-id], [data-conv-id], .history-item, button');
          if (historyItem) {
            const allMobileItems = mobileDrawerContent.querySelectorAll('[data-history-id], [data-id], [data-conv-id], .history-item, button[class*="history"]');
            const allDesktopItems = desktopHistory.querySelectorAll('[data-history-id], [data-id], [data-conv-id], .history-item, button[class*="history"]');
            const index = Array.from(allMobileItems).indexOf(historyItem);
            if (index >= 0 && allDesktopItems[index]) {
              closeMobileDrawer('lex-history-drawer');
              allDesktopItems[index].click();
            }
          }
        });
      }
      
      // Sincronizar botón nueva consulta móvil
      const mobileNewBtn = document.getElementById('mobile-new-chat-btn');
      const desktopNewBtn = document.getElementById('new-chat-btn');
      if (mobileNewBtn && desktopNewBtn) {
        mobileNewBtn.addEventListener('click', () => {
          closeMobileDrawer('lex-history-drawer');
          desktopNewBtn.click();
        });
      }

      // Auto-expand textarea de entrada
      const input = document.getElementById('chat-input');
      function autoResize(el){
        if(!el) return;
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 120) + 'px';
      }
      if (input) {
        autoResize(input);
        input.addEventListener('input', () => autoResize(input));
      }

      // Sincronizar documentos con drawer móvil
      const docsPanelList = document.getElementById('docs-list');
      const docsDrawerContent = document.getElementById('lex-docs-drawer-content');
      if (docsPanelList && docsDrawerContent) {
        // Clonar contenido inicial cuando cargue
        const syncDocs = () => { docsDrawerContent.innerHTML = docsPanelList.innerHTML; };
        syncDocs();
        const obs = new MutationObserver(syncDocs);
        obs.observe(docsPanelList, { childList: true, subtree: true });

        // Delegación de clics para abrir visor de documentos desde el drawer
        docsDrawerContent.addEventListener('click', (e) => {
          const btn = e.target.closest('.doc-item');
          if (!btn) return;
          const docId = btn.getAttribute('data-doc-id');
          if (docId) {
            closeMobileDrawer('lex-docs-drawer');
            if (window.lexOpenDocViewer) {
              window.lexOpenDocViewer(docId);
            }
          }
        });
      }
    });
  </script>
</body>
</html>
