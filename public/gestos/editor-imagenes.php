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
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'image-editor')) {
    header('Location: /gestos/?error=no_access');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = 'gestures';

// Configuración del header unificado
$headerBackUrl = '/gestos/';
$headerBackText = 'Gestos';
$headerTitle = 'Editor de imágenes';
$headerIcon = 'iconoir-media-image';
$headerIconColor = 'from-amber-500 to-orange-600';
$headerDrawerId = 'gesture-history-drawer';
?><!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-mesh text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>
    
    <!-- Main content area -->
    <main class="flex-1 flex flex-col overflow-hidden min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <!-- Two Column Layout -->
      <div class="flex-1 flex overflow-hidden">
        
        <!-- LEFT: Controls Panel -->
        <div class="w-full lg:w-[420px] xl:w-[480px] flex flex-col border-r border-slate-200/50 bg-white/50 overflow-hidden">
          <div class="flex-1 overflow-auto p-4 space-y-4">
            
            <!-- Provider & Mode Toggles -->
            <div class="flex flex-wrap gap-2">
              <div class="flex bg-slate-100 p-1 rounded-lg border border-slate-200 text-xs">
                <button type="button" id="provider-qwen" class="provider-toggle-btn px-2.5 py-1.5 rounded-md font-medium transition-all active">Qwen</button>
                <button type="button" id="provider-nanobanana" class="provider-toggle-btn px-2.5 py-1.5 rounded-md font-medium transition-all">Gemini</button>
                <button type="button" id="provider-flux" class="provider-toggle-btn px-2.5 py-1.5 rounded-md font-medium transition-all">FLUX</button>
                <input type="hidden" id="current-provider" value="qwen" />
              </div>
              
              <div class="flex bg-slate-100 p-1 rounded-lg border border-slate-200 text-xs">
                <button type="button" id="mode-generate" class="mode-toggle-btn px-3 py-1.5 rounded-md font-medium transition-all active">
                  <i class="iconoir-sparks mr-1"></i>Crear
                </button>
                <button type="button" id="mode-edit" class="mode-toggle-btn px-3 py-1.5 rounded-md font-medium transition-all">
                  <i class="iconoir-edit mr-1"></i>Editar
                </button>
                <input type="hidden" id="current-mode" value="generate" />
              </div>
            </div>

            <!-- Edit Mode: Source Image Upload -->
            <div id="edit-images-section" class="hidden">
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1.5">Imagen fuente *</label>
                  <div id="source-image-dropzone" class="relative border-2 border-dashed border-slate-300 rounded-lg p-3 text-center hover:border-amber-400 hover:bg-amber-50/30 transition-all cursor-pointer aspect-square flex flex-col items-center justify-center">
                    <input type="file" id="source-image-input" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                    <div id="source-image-placeholder" class="flex flex-col items-center gap-1">
                      <i class="iconoir-upload text-xl text-slate-400"></i>
                      <span class="text-[10px] text-slate-500">Arrastra o clic</span>
                    </div>
                    <img id="source-image-preview" src="" alt="Fuente" class="hidden w-full h-full object-cover rounded-md" />
                    <button type="button" id="source-image-clear" class="hidden absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full text-[10px] hover:bg-red-600">
                      <i class="iconoir-xmark"></i>
                    </button>
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1.5">Objetivo <span class="text-slate-400">(opcional)</span></label>
                  <div id="target-image-dropzone" class="relative border-2 border-dashed border-slate-300 rounded-lg p-3 text-center hover:border-amber-400 hover:bg-amber-50/30 transition-all cursor-pointer aspect-square flex flex-col items-center justify-center">
                    <input type="file" id="target-image-input" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                    <div id="target-image-placeholder" class="flex flex-col items-center gap-1">
                      <i class="iconoir-upload text-xl text-slate-400"></i>
                      <span class="text-[10px] text-slate-500">Arrastra o clic</span>
                    </div>
                    <img id="target-image-preview" src="" alt="Objetivo" class="hidden w-full h-full object-cover rounded-md" />
                    <button type="button" id="target-image-clear" class="hidden absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full text-[10px] hover:bg-red-600">
                      <i class="iconoir-xmark"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Prompt Input -->
            <div>
              <label id="description-label" class="block text-xs font-medium text-slate-700 mb-1.5">Describe tu imagen</label>
              <textarea id="image-description" rows="3" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500/30 transition-all resize-none bg-white" placeholder="Describe la imagen que necesitas..."></textarea>
            </div>

            <!-- Collapsible Style Options -->
            <details id="style-options-section" class="group border border-slate-200 rounded-lg bg-white">
              <summary class="px-3 py-2 cursor-pointer text-sm font-medium text-slate-700 flex items-center justify-between hover:bg-slate-50 rounded-lg">
                <span class="flex items-center gap-2">
                  <i class="iconoir-settings text-amber-500"></i>
                  Opciones de estilo
                </span>
                <i class="iconoir-nav-arrow-down text-slate-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              
              <div class="p-3 pt-0 space-y-3 border-t border-slate-100">
                <!-- Format -->
                <div>
                  <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1.5">Formato</label>
                  <div class="flex flex-wrap gap-1.5">
                    <label class="cursor-pointer">
                      <input type="radio" name="format" value="1:1" class="hidden peer" checked />
                      <div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md hover:border-amber-400 transition-all">1:1</div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="format" value="3:4" class="hidden peer" />
                      <div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md hover:border-amber-400 transition-all">3:4</div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="format" value="4:3" class="hidden peer" />
                      <div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md hover:border-amber-400 transition-all">4:3</div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="format" value="16:9" class="hidden peer" />
                      <div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md hover:border-amber-400 transition-all">16:9</div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="format" value="9:16" class="hidden peer" />
                      <div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md hover:border-amber-400 transition-all">9:16</div>
                    </label>
                  </div>
                </div>

                <!-- Style -->
                <div>
                  <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1.5">Estilo</label>
                  <div class="flex flex-wrap gap-1.5">
                    <label class="cursor-pointer"><input type="radio" name="style" value="" class="hidden peer" checked /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Ninguno</div></label>
                    <label class="cursor-pointer"><input type="radio" name="style" value="photographic" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Fotográfico</div></label>
                    <label class="cursor-pointer"><input type="radio" name="style" value="corporate" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Corporativo</div></label>
                    <label class="cursor-pointer"><input type="radio" name="style" value="headshot-pro" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Retrato Pro</div></label>
                    <label class="cursor-pointer"><input type="radio" name="style" value="silicon-valley" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Corporativo Pro</div></label>
                    <label class="cursor-pointer"><input type="radio" name="style" value="luxury-product" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Producto Lujo</div></label>
                    <label class="cursor-pointer"><input type="radio" name="style" value="3d-render" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">3D Render</div></label>
                    <label class="cursor-pointer"><input type="radio" name="style" value="minimalist" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Minimalista</div></label>
                  </div>
                </div>

                <!-- Color -->
                <div>
                  <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1.5">Color</label>
                  <div class="flex flex-wrap gap-1.5">
                    <label class="cursor-pointer"><input type="radio" name="color" value="" class="hidden peer" checked /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Auto</div></label>
                    <label class="cursor-pointer"><input type="radio" name="color" value="warm" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Cálidos</div></label>
                    <label class="cursor-pointer"><input type="radio" name="color" value="cool" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Fríos</div></label>
                    <label class="cursor-pointer"><input type="radio" name="color" value="bw" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">B/N</div></label>
                    <label class="cursor-pointer"><input type="radio" name="color" value="vibrant" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Vibrante</div></label>
                  </div>
                </div>

                <!-- Lighting -->
                <div>
                  <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1.5">Iluminación</label>
                  <div class="flex flex-wrap gap-1.5">
                    <label class="cursor-pointer"><input type="radio" name="lighting" value="" class="hidden peer" checked /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Auto</div></label>
                    <label class="cursor-pointer"><input type="radio" name="lighting" value="natural" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Natural</div></label>
                    <label class="cursor-pointer"><input type="radio" name="lighting" value="studio" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Estudio</div></label>
                    <label class="cursor-pointer"><input type="radio" name="lighting" value="dramatic" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Dramática</div></label>
                    <label class="cursor-pointer"><input type="radio" name="lighting" value="golden" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Hora dorada</div></label>
                  </div>
                </div>

                <!-- Composition -->
                <div>
                  <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1.5">Composición</label>
                  <div class="flex flex-wrap gap-1.5">
                    <label class="cursor-pointer"><input type="radio" name="composition" value="" class="hidden peer" checked /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Auto</div></label>
                    <label class="cursor-pointer"><input type="radio" name="composition" value="bokeh" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Bokeh</div></label>
                    <label class="cursor-pointer"><input type="radio" name="composition" value="closeup" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Primer plano</div></label>
                    <label class="cursor-pointer"><input type="radio" name="composition" value="wide" class="hidden peer" /><div class="option-chip peer-checked:active px-2.5 py-1 text-xs border border-slate-200 rounded-md">Plano general</div></label>
                  </div>
                </div>
              </div>
            </details>

            <!-- Summary -->
            <div id="selection-summary" class="p-2.5 bg-amber-50/50 border border-amber-200/50 rounded-lg text-xs text-amber-800">
              <p id="summary-text">Formato 1:1 • Sin estilo específico</p>
            </div>
            
          </div>
          
          <!-- Generate Button (sticky bottom) -->
          <div class="p-4 border-t border-slate-200/50 bg-white/80 backdrop-blur">
            <button type="button" id="generate-image-btn" class="w-full px-4 py-3 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
              <i class="iconoir-sparks"></i>
              <span>Generar imagen</span>
            </button>
          </div>
        </div>

        <!-- RIGHT: Canvas / Result Area -->
        <div class="hidden lg:flex flex-1 flex-col bg-slate-100/50 overflow-hidden">
          
          <!-- Empty State -->
          <div id="canvas-empty" class="flex-1 flex flex-col items-center justify-center text-center p-8">
            <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-amber-100 to-orange-100 flex items-center justify-center mb-4">
              <i class="iconoir-media-image text-4xl text-amber-500"></i>
            </div>
            <h3 class="text-lg font-semibold text-slate-700 mb-1">Tu imagen aparecerá aquí</h3>
            <p class="text-sm text-slate-500 max-w-xs">Describe lo que quieres crear y haz clic en "Generar imagen"</p>
          </div>

          <!-- Loading State -->
          <div id="canvas-loading" class="hidden flex-1 flex flex-col items-center justify-center text-center p-8">
            <div class="relative w-20 h-20 mb-4">
              <div class="absolute inset-0 border-4 border-amber-500/30 rounded-full"></div>
              <div class="absolute inset-0 border-4 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
              <div class="absolute inset-0 flex items-center justify-center text-3xl">🍌</div>
            </div>
            <h3 class="text-lg font-semibold text-slate-700 mb-1">Generando imagen...</h3>
            <p class="text-sm text-slate-500">Esto puede tardar unos segundos</p>
          </div>

          <!-- Result State -->
          <div id="canvas-result" class="hidden flex-1 flex flex-col overflow-hidden">
            <!-- Image Container -->
            <div class="flex-1 flex items-center justify-center p-4 overflow-auto">
              <img id="generated-image" src="" alt="Imagen generada" class="max-w-full max-h-full rounded-xl shadow-2xl cursor-pointer hover:scale-[1.02] transition-transform" />
            </div>
            
            <!-- Actions Bar -->
            <div class="p-4 border-t border-slate-200/50 bg-white/80 backdrop-blur flex items-center justify-between gap-3">
              <div class="flex gap-2">
                <button id="edit-result-btn" class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white text-sm font-medium rounded-lg transition-all flex items-center gap-2 shadow-sm">
                  <i class="iconoir-edit"></i>
                  Editar esta imagen
                </button>
              </div>
              <div class="flex gap-2">
                <button id="regenerate-image-btn" class="px-3 py-2 text-slate-600 hover:text-amber-600 hover:bg-amber-50 text-sm font-medium rounded-lg transition-all flex items-center gap-1.5">
                  <i class="iconoir-refresh"></i>
                  Regenerar
                </button>
                <button id="download-image-btn" class="px-3 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-lg transition-all flex items-center gap-1.5 shadow-sm">
                  <i class="iconoir-download"></i>
                  Descargar
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Mobile Result (shown below form on mobile) -->
        <div id="mobile-result" class="lg:hidden hidden fixed inset-0 z-40 bg-black/90 flex flex-col">
          <div class="flex items-center justify-between p-4 text-white">
            <h3 class="font-medium">Imagen generada</h3>
            <button id="mobile-result-close" class="p-2 hover:bg-white/10 rounded-lg">
              <i class="iconoir-xmark text-xl"></i>
            </button>
          </div>
          <div class="flex-1 flex items-center justify-center p-4 overflow-auto">
            <img id="mobile-generated-image" src="" alt="Imagen generada" class="max-w-full max-h-full rounded-xl" />
          </div>
          <div class="p-4 flex gap-3 bg-black/50">
            <button id="mobile-edit-result-btn" class="flex-1 px-4 py-3 bg-purple-500 text-white font-medium rounded-xl flex items-center justify-center gap-2">
              <i class="iconoir-edit"></i> Editar
            </button>
            <button id="mobile-download-btn" class="flex-1 px-4 py-3 bg-white text-slate-800 font-medium rounded-xl flex items-center justify-center gap-2">
              <i class="iconoir-download"></i> Descargar
            </button>
          </div>
        </div>

      </div>
    </main>
  </div>

  <!-- Lightbox -->
  <div id="image-lightbox" class="fixed inset-0 bg-black/95 z-50 hidden items-center justify-center p-4">
    <button id="lightbox-close" class="absolute top-4 right-4 text-white/80 hover:text-white p-2">
      <i class="iconoir-xmark text-2xl"></i>
    </button>
    <img id="lightbox-image" src="" alt="Imagen ampliada" class="max-w-full max-h-full object-contain rounded-lg" />
  </div>

  <script>window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken); ?>';</script>
  <script src="/assets/js/gesture-image-editor.js"></script>
  
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
  
  <style>
    .provider-toggle-btn { color: #64748b; background: transparent; }
    .provider-toggle-btn.active { 
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important; 
      color: white !important; 
      box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
    }
    .mode-toggle-btn { color: #64748b; background: transparent; }
    .mode-toggle-btn.active { 
      background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%) !important; 
      color: white !important; 
      box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
    }
    .option-chip { transition: all 0.15s; }
    .option-chip.active,
    .option-chip:has(input:checked),
    input:checked + .option-chip {
      border-color: #f59e0b !important;
      background: rgba(245, 158, 11, 0.15);
      color: #b45309;
      font-weight: 600;
    }
  </style>
</body>
</html>
