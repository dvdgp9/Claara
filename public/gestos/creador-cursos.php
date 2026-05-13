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
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'course-creator')) {
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
$headerTitle = 'Course creator';
$headerIcon = 'iconoir-graduation-cap';
$headerIconColor = 'from-emerald-500 to-teal-600';
$headerDrawerId = 'course-history-drawer';
?><!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-mesh text-slate-900 overflow-hidden">
  <style>
    .format-card {
      transition: all 0.2s ease;
      position: relative;
    }
    .format-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .format-card.selected {
      border-color: #10b981;
      background: linear-gradient(135deg, rgba(16,185,129,0.05) 0%, rgba(20,184,166,0.08) 100%);
    }
    .format-card.selected .format-icon {
      transform: scale(1.1);
    }
    .format-card .check-badge {
      position: absolute;
      top: -6px;
      right: -6px;
      width: 20px;
      height: 20px;
      background: #10b981;
      border-radius: 50%;
      display: none;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 10px;
      box-shadow: 0 2px 4px rgba(16,185,129,0.3);
    }
    .format-card.selected .check-badge {
      display: flex;
    }
    .config-option {
      transition: all 0.2s ease;
    }
    .config-option:hover {
      border-color: #10b981;
    }
    .config-option.selected {
      border-color: #10b981;
      background-color: rgba(16,185,129,0.1);
    }
    .result-tab {
      padding: 0.5rem 1rem;
      font-size: 0.875rem;
      font-weight: 500;
      border-radius: 0.5rem;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .result-tab:hover {
      background: rgba(16,185,129,0.1);
    }
    .result-tab.active {
      background: #10b981;
      color: white;
    }
    .result-panel {
      display: none;
    }
    .result-panel.active {
      display: block;
    }
    .preview-toggle {
      display: flex;
      gap: 0.5rem;
      padding: 0.25rem;
      background: #f1f5f9;
      border-radius: 0.5rem;
      width: fit-content;
    }
    .preview-toggle button {
      padding: 0.375rem 0.75rem;
      font-size: 0.75rem;
      border-radius: 0.375rem;
      transition: all 0.2s;
    }
    .preview-toggle button.active {
      background: white;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .content-preview {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 0.75rem;
      padding: 1.5rem;
      max-height: 600px;
      overflow-y: auto;
    }
    .content-preview h1, .content-preview h2, .content-preview h3 {
      margin-top: 1.5rem;
      margin-bottom: 0.75rem;
    }
    .content-preview h1 { font-size: 1.5rem; font-weight: 700; }
    .content-preview h2 { font-size: 1.25rem; font-weight: 600; }
    .content-preview h3 { font-size: 1.1rem; font-weight: 600; }
    .content-preview p { margin-bottom: 1rem; }
    .content-preview ul, .content-preview ol { margin-left: 1.5rem; margin-bottom: 1rem; }
    .content-preview li { margin-bottom: 0.5rem; }
    .content-preview strong { font-weight: 600; }
    .content-preview table {
      width: 100%;
      border-collapse: collapse;
      margin: 1rem 0;
    }
    .content-preview th, .content-preview td {
      border: 1px solid #e2e8f0;
      padding: 0.5rem;
      text-align: left;
    }
    .content-preview th {
      background: #f8fafc;
      font-weight: 600;
    }
    .raw-preview {
      background: #1e293b;
      color: #e2e8f0;
      border-radius: 0.75rem;
      padding: 1rem;
      max-height: 600px;
      overflow: auto;
    }
    .raw-preview pre {
      white-space: pre-wrap;
      word-break: break-word;
      font-family: 'Fira Code', 'Monaco', monospace;
      font-size: 0.8rem;
      line-height: 1.6;
    }
  </style>
  <div class="min-h-screen flex h-screen">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>
    
    <!-- Sidebar de historial (solo desktop) -->
    <aside id="history-sidebar" class="hidden lg:flex w-72 glass-strong border-r border-slate-200/50 flex-col shrink-0">
      <div class="p-4 border-b border-slate-200/50">
        <div class="flex items-center justify-between">
          <h2 class="font-semibold text-slate-800 flex items-center gap-2">
            <i class="iconoir-clock text-emerald-500"></i>
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
    $drawerId = 'course-history-drawer';
    $drawerTitle = 'History';
    $drawerIcon = 'iconoir-clock';
    $drawerIconColor = 'text-emerald-500';
    include __DIR__ . '/../includes/mobile-drawer.php'; 
    ?>
    
    <!-- Main content area -->
    <main class="flex-1 flex flex-col overflow-hidden min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <div class="flex-1 overflow-auto pb-16 lg:pb-0">
        <div class="max-w-4xl mx-auto p-4 lg:p-6 space-y-6">
          
          <!-- Header del gesto -->
          <div class="text-center mb-6">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white shadow-lg mx-auto mb-4">
              <i class="iconoir-graduation-cap text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-600 to-teal-600 mb-2">
              Course Creator
            </h1>
            <p class="text-slate-500 max-w-lg mx-auto">
              Upload a PDF or paste handbook text. Generate an editable learning outline and build the full content for each module.
            </p>
          </div>

          <!-- Input Section -->
          <section id="input-section" class="glass-strong rounded-2xl p-6 border border-slate-200/50">
            <form id="course-form" class="space-y-6">
              
              <!-- Fuente del contenido -->
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-3">
                  <i class="iconoir-page text-emerald-500 mr-1"></i>
                  Material de origen
                </label>
                
                <!-- Tabs -->
                <div class="flex gap-2 mb-3">
                  <button type="button" data-tab="pdf" class="tab-btn active px-4 py-2 text-sm font-medium rounded-lg transition-all bg-emerald-100 text-emerald-700">
                    <i class="iconoir-page mr-1"></i> PDF
                  </button>
                  <button type="button" data-tab="text" class="tab-btn px-4 py-2 text-sm font-medium rounded-lg transition-all bg-slate-100 text-slate-600 hover:bg-slate-200">
                    <i class="iconoir-text mr-1"></i> Text
                  </button>
                </div>

                <!-- PDF Input -->
                <div id="tab-pdf" class="tab-content">
                  <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-slate-300 rounded-xl cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/50 transition-all">
                    <i class="iconoir-upload text-3xl text-slate-400 mb-2"></i>
                    <span class="text-sm text-slate-500">Drag a PDF or click to select</span>
                    <span class="text-xs text-slate-400 mt-1">Handbook, syllabus, theory... (max 20MB)</span>
                    <input type="file" id="source-pdf" accept=".pdf" class="hidden" />
                  </label>
                  <p id="pdf-filename" class="text-sm text-emerald-600 mt-2 hidden flex items-center gap-2">
                    <i class="iconoir-check-circle"></i>
                    <span></span>
                  </p>
                </div>

                <!-- Text Input -->
                <div id="tab-text" class="tab-content hidden">
                  <textarea id="source-text" rows="8"
                            class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-all resize-none"
                            placeholder="Paste the handbook, theory, or source material you want to turn into a course..."></textarea>
                  <p class="text-xs text-slate-500 mt-2">Minimum 100 words to generate quality content</p>
                </div>
              </div>

              <!-- Configuración del curso -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Duración -->
                <div>
                  <label class="block text-sm font-semibold text-slate-700 mb-2">
                    <i class="iconoir-clock text-emerald-500 mr-1"></i>
                    Duration
                  </label>
                  <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer">
                      <input type="radio" name="duration" value="4h" class="hidden peer" />
                      <div class="config-option p-2 border-2 border-slate-200 rounded-lg text-center peer-checked:border-emerald-500 peer-checked:bg-emerald-50 transition-all">
                        <span class="text-sm font-medium">4h</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="duration" value="8h" class="hidden peer" checked />
                      <div class="config-option p-2 border-2 border-slate-200 rounded-lg text-center peer-checked:border-emerald-500 peer-checked:bg-emerald-50 transition-all">
                        <span class="text-sm font-medium">8h</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="duration" value="16h" class="hidden peer" />
                      <div class="config-option p-2 border-2 border-slate-200 rounded-lg text-center peer-checked:border-emerald-500 peer-checked:bg-emerald-50 transition-all">
                        <span class="text-sm font-medium">16h</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="duration" value="40h" class="hidden peer" />
                      <div class="config-option p-2 border-2 border-slate-200 rounded-lg text-center peer-checked:border-emerald-500 peer-checked:bg-emerald-50 transition-all">
                        <span class="text-sm font-medium">40h</span>
                      </div>
                    </label>
                  </div>
                </div>

                <!-- Nivel -->
                <div>
                  <label class="block text-sm font-semibold text-slate-700 mb-2">
                    <i class="iconoir-learning text-emerald-500 mr-1"></i>
                    Level
                  </label>
                  <div class="space-y-2">
                    <label class="cursor-pointer block">
                      <input type="radio" name="level" value="basico" class="hidden peer" />
                      <div class="config-option p-2 border-2 border-slate-200 rounded-lg peer-checked:border-emerald-500 peer-checked:bg-emerald-50 transition-all">
                        <span class="text-sm font-medium">🌱 Basic</span>
                      </div>
                    </label>
                    <label class="cursor-pointer block">
                      <input type="radio" name="level" value="intermedio" class="hidden peer" checked />
                      <div class="config-option p-2 border-2 border-slate-200 rounded-lg peer-checked:border-emerald-500 peer-checked:bg-emerald-50 transition-all">
                        <span class="text-sm font-medium">🌿 Intermediate</span>
                      </div>
                    </label>
                    <label class="cursor-pointer block">
                      <input type="radio" name="level" value="avanzado" class="hidden peer" />
                      <div class="config-option p-2 border-2 border-slate-200 rounded-lg peer-checked:border-emerald-500 peer-checked:bg-emerald-50 transition-all">
                        <span class="text-sm font-medium">🌳 Advanced</span>
                      </div>
                    </label>
                  </div>
                </div>

                <!-- Modalidad -->
                <div>
                  <label class="block text-sm font-semibold text-slate-700 mb-2">
                    <i class="iconoir-community text-emerald-500 mr-1"></i>
                    Format
                  </label>
                  <div class="space-y-2">
                    <label class="cursor-pointer block">
                      <input type="radio" name="course_format" value="presencial" class="hidden peer" />
                      <div class="config-option p-2 border-2 border-slate-200 rounded-lg peer-checked:border-emerald-500 peer-checked:bg-emerald-50 transition-all">
                        <span class="text-sm font-medium">🏫 On-site</span>
                      </div>
                    </label>
                    <label class="cursor-pointer block">
                      <input type="radio" name="course_format" value="online" class="hidden peer" checked />
                      <div class="config-option p-2 border-2 border-slate-200 rounded-lg peer-checked:border-emerald-500 peer-checked:bg-emerald-50 transition-all">
                        <span class="text-sm font-medium">💻 Online</span>
                      </div>
                    </label>
                    <label class="cursor-pointer block">
                      <input type="radio" name="course_format" value="hibrido" class="hidden peer" />
                      <div class="config-option p-2 border-2 border-slate-200 rounded-lg peer-checked:border-emerald-500 peer-checked:bg-emerald-50 transition-all">
                        <span class="text-sm font-medium">🔄 Hybrid</span>
                      </div>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Botón generar índice -->
              <button type="submit" id="generate-btn" class="w-full py-3 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                <i class="iconoir-list"></i>
                <span id="generate-btn-text">Generate course outline</span>
              </button>
              
              <p class="text-xs text-slate-500 text-center">
                <i class="iconoir-info-circle mr-1"></i>
                Step 1 of 2: Generate an outline you can edit before building the content
              </p>
              
              <!-- Progress -->
              <div id="progress-panel" class="hidden bg-emerald-50 rounded-xl p-4 border border-emerald-200">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center">
                    <i class="iconoir-refresh animate-spin text-emerald-600"></i>
                  </div>
                  <div>
                    <p id="progress-text" class="text-sm font-medium text-emerald-700">Processing...</p>
                    <p id="progress-detail" class="text-xs text-emerald-500">This may take a few minutes depending on selected formats</p>
                  </div>
                </div>
              </div>
              
              <!-- Error -->
              <div id="error-panel" class="hidden bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="flex items-start gap-2">
                  <i class="iconoir-warning-triangle text-red-500 mt-0.5"></i>
                  <div>
                    <p class="text-sm font-medium text-red-800">Error</p>
                    <p id="error-message" class="text-xs text-red-600 mt-0.5"></p>
                  </div>
                </div>
              </div>
            </form>
          </section>

          <!-- Outline Section (Fase 1 resultado) -->
          <section id="outline-section" class="hidden">
            <div class="mb-4 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                  <i class="iconoir-list text-emerald-600 text-xl"></i>
                </div>
                <div>
                  <h2 class="text-lg font-bold text-slate-800">Course outline</h2>
                  <p class="text-xs text-slate-500">Step 2 of 2: Review and edit the outline, then generate the modules</p>
                </div>
              </div>
            </div>
            
            <div id="outline-editor">
              <!-- Se llena dinámicamente con el editor de índice -->
            </div>
          </section>

          <!-- Result Section (Fase 2 resultado: módulos desarrollados) -->
          <section id="result-section" class="hidden space-y-4">
            
            <!-- Result Header -->
            <div class="flex items-center justify-between mb-4">
              <div>
                <h2 id="result-title" class="text-xl font-bold text-slate-800">Course generated</h2>
                <p id="result-source" class="text-sm text-slate-500"></p>
              </div>
              <button type="button" id="new-course-btn" class="text-sm font-medium text-emerald-600 hover:text-emerald-700 flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 rounded-lg transition-colors">
                <i class="iconoir-plus"></i>
                <span>New course</span>
              </button>
            </div>
            
            <!-- Modules Container -->
            <div id="modules-container">
              <!-- Se llenan dinámicamente con los módulos desarrollados -->
            </div>
          </section>

        </div>
      </div>
    </main>
  </div>

  <script>window.CSRF_TOKEN = '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>';</script>
  <script src="/assets/js/gesture-course-creator.js"></script>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
  
  <script>
    // Sincronizar historial con drawer móvil
    document.addEventListener('DOMContentLoaded', () => {
      const desktopHistory = document.getElementById('history-list');
      const mobileDrawerContent = document.getElementById('course-history-drawer-content');
      
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
                closeMobileDrawer('course-history-drawer');
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
