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
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'project-admin')) {
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
$headerTitle = 'Admin Proyectos';
$headerIcon = 'iconoir-folder-settings';
$headerIconColor = 'from-emerald-500 to-teal-600';
$headerDrawerId = 'project-admin-history-drawer';
?><!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-mesh text-slate-900 overflow-hidden">
  <style>
    .action-card {
      transition: all 0.2s ease;
      position: relative;
    }
    .action-card:hover {
      transform: translateY(-2px);
    }
    .action-card.selected {
      border-color: #10b981;
      background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(20, 184, 166, 0.1) 100%);
      box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
    }
    .action-card.selected .action-icon {
      background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%);
      color: white;
    }
    .action-card .check-badge {
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
      font-size: 12px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .action-card.selected .check-badge {
      display: flex;
    }
    .history-item.active {
      background-color: rgba(16, 185, 129, 0.05);
      border-left: 3px solid #10b981;
    }
    .dropzone {
      border: 2px dashed #cbd5e1;
      transition: all 0.2s ease;
    }
    .dropzone.dragover {
      border-color: #10b981;
      background: rgba(16, 185, 129, 0.05);
    }
    .file-item {
      animation: slideIn 0.2s ease;
    }
    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .result-table {
      width: 100%;
      border-collapse: collapse;
    }
    .result-table th,
    .result-table td {
      padding: 10px 12px;
      text-align: left;
      border-bottom: 1px solid #e2e8f0;
    }
    .result-table th {
      background: #f8fafc;
      font-weight: 600;
      color: #475569;
      font-size: 0.875rem;
    }
    .result-table tr:hover {
      background: #f8fafc;
    }
    .result-table .total-row {
      background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(20, 184, 166, 0.1) 100%);
      font-weight: 600;
    }
    .result-table .subtotal-row {
      background: #f1f5f9;
      font-weight: 500;
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
    $drawerId = 'project-admin-history-drawer';
    $drawerTitle = 'Historial';
    $drawerIcon = 'iconoir-clock';
    $drawerIconColor = 'text-emerald-500';
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
            <h1 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-600 to-teal-600 mb-2">
              Análisis de Pliegos
            </h1>
            <p class="text-slate-500 max-w-lg mx-auto">
              Sube pliegos de concursos públicos y extrae automáticamente gastos no personales, horas de trabajo y otra información clave.
            </p>
          </div>

          <!-- Input Section -->
          <section id="input-section" class="glass-strong rounded-2xl p-6 border border-slate-200/50">
            <form id="project-admin-form" class="space-y-5">
              
              <!-- Subida de documentos -->
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-3">
                  <i class="iconoir-page text-emerald-500 mr-1"></i>
                  Documentos del pliego
                </label>
                
                <div id="dropzone" class="dropzone flex flex-col items-center justify-center w-full h-32 rounded-xl cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/50 transition-all">
                  <i class="iconoir-upload text-2xl text-slate-400 mb-2"></i>
                  <span class="text-sm text-slate-500">Arrastra PDFs aquí o haz clic para seleccionar</span>
                  <span class="text-xs text-slate-400 mt-1">Máximo 20MB por archivo</span>
                  <input type="file" id="file-input" accept=".pdf" multiple class="hidden" />
                </div>
                
                <!-- Lista de archivos -->
                <div id="files-list" class="mt-3 space-y-2 hidden"></div>
              </div>

              <!-- Instrucciones adicionales -->
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                  <i class="iconoir-edit-pencil text-emerald-500 mr-1"></i>
                  Instrucciones adicionales <span class="font-normal text-slate-400">(opcional)</span>
                </label>
                <textarea id="additional-instructions" rows="3"
                          class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-all resize-none"
                          placeholder="Ej: Céntrate en los requisitos de equipamiento informático..."></textarea>
              </div>

              <!-- Tipo de análisis -->
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                  <i class="iconoir-sparks text-emerald-500 mr-1"></i>
                  ¿Qué quieres analizar? <span class="font-normal text-slate-400">(selecciona uno o varios)</span>
                </label>
                
                <div class="grid grid-cols-2 gap-3">
                  <button type="button" data-action="expenses" class="action-card selected p-4 rounded-xl border-2 border-slate-200 text-left">
                    <span class="check-badge"><i class="iconoir-check"></i></span>
                    <div class="action-icon w-12 h-12 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-500 flex items-center justify-center text-white mb-3">
                      <i class="iconoir-wallet text-xl"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">Gastos no personales</div>
                    <div class="text-xs text-slate-500 mt-1">Equipamiento, materiales, licencias, seguros...</div>
                  </button>
                  
                  <button type="button" data-action="hours" class="action-card p-4 rounded-xl border-2 border-slate-200 text-left">
                    <span class="check-badge"><i class="iconoir-check"></i></span>
                    <div class="action-icon w-12 h-12 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600 mb-3">
                      <i class="iconoir-clock text-xl"></i>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">Conteo de horas</div>
                    <div class="text-xs text-slate-500 mt-1">Horas de servicio, formación, coordinación...</div>
                  </button>
                </div>
                
                <p id="selected-count" class="text-xs text-emerald-600 font-medium mt-3">1 análisis seleccionado</p>
              </div>
              
              <!-- Botón analizar -->
              <button type="submit" id="analyze-btn" disabled class="w-full py-3 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="iconoir-sparks"></i>
                <span id="analyze-btn-text">Analizar pliego</span>
              </button>
              
              <!-- Progress -->
              <div id="progress-panel" class="hidden bg-emerald-50 rounded-xl p-4 border border-emerald-200">
                <div class="flex items-center gap-3">
                  <div class="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
                  <div>
                    <p id="progress-text" class="text-sm font-medium text-emerald-700">Analizando...</p>
                    <p id="progress-detail" class="text-xs text-emerald-500">Extrayendo información del pliego</p>
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
                  <span id="result-title" class="truncate">Análisis completado</span>
                </h2>
                <p id="result-source" class="text-sm text-slate-500">Pliego analizado</p>
              </div>
              <div class="flex items-center gap-3 shrink-0">
                <button type="button" id="copy-result-btn" class="text-sm font-semibold text-slate-600 hover:text-slate-800 flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl shadow-sm hover:shadow transition-all">
                  <i class="iconoir-copy"></i>
                  <span>Copiar</span>
                </button>
                <button type="button" id="new-analysis-btn" class="text-sm font-semibold text-emerald-600 hover:text-white flex items-center gap-2 px-4 py-2 bg-emerald-50 hover:bg-emerald-600 rounded-xl transition-all">
                  <i class="iconoir-plus"></i>
                  <span>Nuevo</span>
                </button>
              </div>
            </div>
            
            <!-- Contenedor de resultados -->
            <div id="result-panels" class="space-y-4"></div>
            
          </section>

        </div>
      </div>
    </main>
  </div>

  <script>
    window.CSRF_TOKEN = '<?php echo $csrfToken; ?>';
    window.GESTURE_TYPE = 'project-admin';
  </script>
  <script src="/assets/js/gesture-admin-proyectos.js"></script>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
  
  <script>
    // Sincronizar historial con drawer móvil
    document.addEventListener('DOMContentLoaded', () => {
      const desktopHistory = document.getElementById('history-list');
      const mobileDrawerContent = document.getElementById('project-admin-history-drawer-content');
      
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
          const historyItem = e.target.closest('.history-item-main');
          if (historyItem) {
            const itemId = historyItem.closest('.history-item')?.dataset.id;
            if (itemId && window.loadHistoryItem) {
              window.loadHistoryItem(itemId);
              // Cerrar drawer
              document.getElementById('project-admin-history-drawer')?.classList.add('hidden');
            }
          }
        });
      }
    });
  </script>
</body>
</html>
