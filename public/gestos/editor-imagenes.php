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
$headerBackText = 'All gestures';
$headerTitle = 'Image editor';
$headerIcon = 'iconoir-media-image';
$headerIconColor = 'from-amber-500 to-orange-600';
$headerDrawerId = 'gesture-history-drawer';
?><!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-mesh text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>
    
    <!-- Sidebar de historial (solo desktop) -->
    <aside id="history-sidebar" class="hidden lg:flex w-64 glass-strong border-r border-slate-200/50 flex-col shrink-0">
      <div class="p-4 border-b border-slate-200/50">
        <div class="flex items-center justify-between">
          <h2 class="font-semibold text-slate-800 flex items-center gap-2">
            <i class="iconoir-clock text-amber-500"></i>
            History
          </h2>
          <button id="new-image-btn" class="p-1.5 text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-smooth" title="New image">
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
    $drawerId = 'gesture-history-drawer';
    $drawerTitle = 'History';
    $drawerIcon = 'iconoir-clock';
    $drawerIconColor = 'text-amber-500';
    include __DIR__ . '/../includes/mobile-drawer.php'; 
    ?>
    
    <!-- Main content area (imagen central + controles superiores/inferiores) -->
    <main class="flex-1 flex flex-col overflow-hidden min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <div class="flex-1 flex overflow-hidden">
        <!-- Zona central: imagen + controles superiores/inferiores -->
        <div class="flex-1 flex flex-col overflow-hidden p-3 lg:p-4">
          
          <!-- Controles superiores: intención + prompt + acciones -->
          <div class="shrink-0 mb-3 space-y-2.5 rounded-2xl border border-slate-200/70 bg-white/85 p-3 shadow-sm">
            <!-- Selector de intención -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-1.5" id="intent-grid">
              <button type="button" class="intent-card p-2.5 text-left bg-white border border-slate-200 rounded-xl hover:border-amber-400 transition-all active" data-intent="from-scratch">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                  <i class="iconoir-sparks text-amber-500"></i>
                  Create from scratch
                </div>
                <div class="text-[11px] text-slate-500 mt-0.5 pl-5 truncate">Open concept</div>
              </button>
              <button type="button" class="intent-card p-2.5 text-left bg-white border border-slate-200 rounded-xl hover:border-amber-400 transition-all" data-intent="edit-image">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                  <i class="iconoir-edit text-amber-500"></i>
                  Edit image
                </div>
                <div class="text-[11px] text-slate-500 mt-0.5 pl-5 truncate">Edits from source</div>
              </button>
              <button type="button" class="intent-card p-2.5 text-left bg-white border border-slate-200 rounded-xl hover:border-amber-400 transition-all" data-intent="corporate-image">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                  <i class="iconoir-building text-amber-500"></i>
                  Corporate
                </div>
                <div class="text-[11px] text-slate-500 mt-0.5 pl-5 truncate">Brand and communications</div>
              </button>
              <button type="button" class="intent-card p-2.5 text-left bg-white border border-slate-200 rounded-xl hover:border-amber-400 transition-all" data-intent="product-mockup">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                  <i class="iconoir-box text-amber-500"></i>
                  Product
                </div>
                <div class="text-[11px] text-slate-500 mt-0.5 pl-5 truncate">Commercial mockup</div>
              </button>
              <button type="button" class="intent-card p-2.5 text-left bg-white border border-slate-200 rounded-xl hover:border-amber-400 transition-all" data-intent="poster-logos">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                  <i class="iconoir-media-image text-amber-500"></i>
                  Poster + logos
                </div>
                <div class="text-[11px] text-slate-500 mt-0.5 pl-5 truncate">Up to 4 references</div>
              </button>
            </div>

            <div id="image-error" class="hidden px-3 py-2 text-sm bg-red-50 border border-red-200 text-red-700 rounded-xl"></div>

            <!-- Prompt principal con botón integrado -->
            <div class="relative">
              <textarea id="image-description" rows="3"
                class="w-full border-2 border-slate-200 rounded-xl pl-4 pr-4 pt-3 pb-14 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all resize-none bg-white text-sm"
                placeholder="Describe the image you want to create..."></textarea>

              <!-- Barra inferior dentro del textarea: resumen params + botón generar -->
              <div class="absolute left-2 right-2 bottom-2 flex items-center gap-2 pointer-events-none">
                <div class="flex-1 min-w-0 flex items-center gap-1.5 text-[11px] text-slate-500 pointer-events-auto">
                  <button type="button" id="open-params-mobile" class="lg:hidden px-2 py-1 text-[11px] font-medium text-amber-700 bg-amber-100 hover:bg-amber-200 rounded-lg transition-colors flex items-center gap-1 shrink-0">
                    <i class="iconoir-settings"></i> Adjust
                  </button>
                  <i class="iconoir-frame shrink-0 hidden lg:inline text-slate-400"></i>
                  <span id="summary-text" class="truncate hidden lg:inline">Automatic settings</span>
                </div>
                <button type="button" id="generate-image-btn"
                  class="pointer-events-auto px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-1.5 shrink-0">
                  <i class="iconoir-sparks"></i>
                  <span>Generate</span>
                </button>
              </div>

              <input type="hidden" id="current-mode" value="generate" />
              <input type="hidden" id="current-provider" value="nanobanana" />
              <input type="hidden" id="current-intent" value="from-scratch" />
            </div>

            <!-- Referencias para modo generar (colapsable) -->
            <details id="generate-references-section" class="group rounded-xl border border-slate-200 bg-slate-50">
              <summary class="flex items-center justify-between gap-2 px-3 py-2 cursor-pointer list-none">
                <div class="flex items-center gap-1.5 min-w-0">
                  <i class="iconoir-media-image text-amber-500 shrink-0"></i>
                  <span class="text-xs font-semibold text-slate-700">Visual references</span>
                  <span id="generate-reference-count" class="text-[11px] text-slate-500 truncate">(optional · max 4)</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <span id="add-generate-reference-btn" class="px-2 py-0.5 text-[11px] font-medium text-amber-700 bg-amber-100 hover:bg-amber-200 rounded-md transition-colors">Add</span>
                  <i class="iconoir-nav-arrow-down text-slate-400 group-open:rotate-180 transition-transform"></i>
                </div>
              </summary>
              <input type="file" id="generate-reference-input" accept="image/*" multiple class="hidden" />
              <div id="generate-reference-list" class="grid grid-cols-2 sm:grid-cols-4 gap-2 px-3 pb-3"></div>
            </details>

            <!-- Acciones rápidas para modo edición -->
            <div id="edit-quick-actions" class="hidden rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
              <div class="flex flex-wrap gap-1.5">
                <button type="button" class="edit-quick-chip px-2.5 py-1 text-xs text-slate-600 bg-white border border-slate-200 hover:border-amber-400 hover:text-amber-700 rounded-lg transition-colors" data-text="Replace the background with a more professional one">Change background</button>
                <button type="button" class="edit-quick-chip px-2.5 py-1 text-xs text-slate-600 bg-white border border-slate-200 hover:border-amber-400 hover:text-amber-700 rounded-lg transition-colors" data-text="Add a logo naturally and with proper proportions">Add logo</button>
                <button type="button" class="edit-quick-chip px-2.5 py-1 text-xs text-slate-600 bg-white border border-slate-200 hover:border-amber-400 hover:text-amber-700 rounded-lg transition-colors" data-text="Remove the foreground object and fill the background">Remove object</button>
                <button type="button" class="edit-quick-chip px-2.5 py-1 text-xs text-slate-600 bg-white border border-slate-200 hover:border-amber-400 hover:text-amber-700 rounded-lg transition-colors" data-text="Improve lighting and contrast while keeping realism">Improve lighting</button>
                <button type="button" class="edit-quick-chip px-2.5 py-1 text-xs text-slate-600 bg-white border border-slate-200 hover:border-amber-400 hover:text-amber-700 rounded-lg transition-colors" data-text="Adjust the color palette while preserving composition">Change colors</button>
                <button type="button" class="edit-quick-chip px-2.5 py-1 text-xs text-slate-600 bg-white border border-slate-200 hover:border-amber-400 hover:text-amber-700 rounded-lg transition-colors" data-text="Extend the framing with more space around the subject">Extend framing</button>
              </div>
            </div>
          </div>
          
          <!-- Zona imagen central (flex-1 para ocupar espacio disponible) -->
          <div class="flex-1 flex items-center justify-center overflow-hidden relative min-h-0">
            <!-- Placeholder inicial -->
            <div id="image-placeholder" class="flex flex-col items-center justify-center text-center p-8">
              <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-amber-100 to-orange-100 flex items-center justify-center mb-4">
                <i class="iconoir-media-image text-4xl text-amber-500"></i>
              </div>
              <h3 class="text-lg font-semibold text-slate-700 mb-2">Generate your first image</h3>
              <p class="text-sm text-slate-500 max-w-sm">Describe what you want to create and tune the settings</p>
            </div>
            
            <!-- Sección imágenes (modo edición) -->
            <div id="edit-source-section" class="hidden absolute inset-0 flex items-center justify-center p-4">
              <div class="flex flex-row gap-3 sm:gap-6 items-start">
                <!-- Imagen fuente -->
                <div class="text-center flex-1 max-w-[140px] sm:max-w-[200px]">
                  <div id="source-image-dropzone" class="relative aspect-square w-full border-2 border-dashed border-slate-300 rounded-xl flex flex-col items-center justify-center hover:border-amber-400 hover:bg-amber-50/30 transition-all cursor-pointer">
                    <input type="file" id="source-image-input" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                    <div id="source-image-placeholder" class="flex flex-col items-center gap-2 p-2">
                      <i class="iconoir-upload text-2xl sm:text-3xl text-slate-400"></i>
                      <span class="text-xs sm:text-sm font-medium text-slate-700">Imagen fuente</span>
                      <span class="text-[10px] sm:text-xs text-slate-400">Drag or click</span>
                    </div>
                    <img id="source-image-preview" src="" alt="Imagen fuente" class="hidden w-full h-full object-contain rounded-xl" />
                    <button type="button" id="source-image-clear" class="hidden absolute -top-2 -right-2 p-1 bg-red-500 text-white rounded-full text-xs hover:bg-red-600 shadow-lg">
                      <i class="iconoir-xmark text-xs"></i>
                    </button>
                  </div>
                  <p class="mt-1.5 text-[10px] sm:text-xs font-medium text-slate-600">Imagen a editar</p>
                  <p class="text-[10px] text-slate-400">(requerida)</p>
                </div>
                
                <!-- Imagen objetivo (opcional) -->
                <div class="text-center flex-1 max-w-[140px] sm:max-w-[200px]">
                  <div id="target-image-dropzone" class="relative aspect-square w-full border-2 border-dashed border-slate-300 rounded-xl flex flex-col items-center justify-center hover:border-purple-400 hover:bg-purple-50/30 transition-all cursor-pointer">
                    <input type="file" id="target-image-input" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                    <div id="target-image-placeholder" class="flex flex-col items-center gap-2 p-2">
                      <i class="iconoir-add-media-image text-2xl sm:text-3xl text-slate-400"></i>
                      <span class="text-xs sm:text-sm font-medium text-slate-700">Referencia</span>
                      <span class="text-[10px] sm:text-xs text-slate-400">Drag or click</span>
                    </div>
                    <img id="target-image-preview" src="" alt="Imagen referencia" class="hidden w-full h-full object-contain rounded-xl" />
                    <button type="button" id="target-image-clear" class="hidden absolute -top-2 -right-2 p-1 bg-red-500 text-white rounded-full text-xs hover:bg-red-600 shadow-lg">
                      <i class="iconoir-xmark text-xs"></i>
                    </button>
                  </div>
                  <p class="mt-1.5 text-[10px] sm:text-xs font-medium text-slate-600">Imagen objetivo</p>
                  <p class="text-[10px] text-slate-400">(opcional)</p>
                </div>
              </div>
            </div>
            
            <!-- Imagen generada -->
            <div id="image-result" class="hidden absolute inset-0 flex items-center justify-center p-4">
              <div class="relative max-w-full max-h-full">
                <img id="generated-image" src="" alt="Imagen generada" 
                  class="max-w-full max-h-[calc(100vh-320px)] object-contain rounded-xl shadow-2xl cursor-pointer hover:shadow-3xl transition-shadow" />
                <!-- Overlay de acciones sobre la imagen -->
                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 bg-black/50 backdrop-blur-md rounded-xl p-2">
                  <button id="edit-this-image-btn" class="px-3 py-2 text-white hover:bg-white/20 rounded-lg transition-all flex items-center gap-1.5 text-sm" title="Usar como base">
                    <i class="iconoir-edit"></i>
                    <span class="hidden sm:inline">Usar como base</span>
                  </button>
                  <button id="regenerate-image-btn" class="px-3 py-2 text-white hover:bg-white/20 rounded-lg transition-all flex items-center gap-1.5 text-sm" title="Regenerate">
                    <i class="iconoir-refresh"></i>
                  </button>
                  <button id="download-image-btn" class="px-3 py-2 text-white hover:bg-white/20 rounded-lg transition-all flex items-center gap-1.5 text-sm" title="Download">
                    <i class="iconoir-download"></i>
                  </button>
                  <button id="fullscreen-btn" class="px-3 py-2 text-white hover:bg-white/20 rounded-lg transition-all flex items-center gap-1.5 text-sm" title="Ver grande">
                    <i class="iconoir-expand"></i>
                  </button>
                </div>
              </div>
            </div>
            
            <!-- Loading -->
            <div id="image-loading" class="hidden absolute inset-0 flex items-center justify-center bg-white/80 backdrop-blur-sm">
              <div class="flex flex-col items-center gap-4 px-8 py-6 bg-amber-500/10 rounded-2xl">
                <div class="relative w-16 h-16">
                  <div class="absolute inset-0 border-4 border-amber-500/30 rounded-full"></div>
                  <div class="absolute inset-0 border-4 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
                  <div class="absolute inset-0 flex items-center justify-center text-amber-600"><i class="iconoir-sparks text-xl"></i></div>
                </div>
                <span id="loading-title" class="text-amber-700 font-medium">Generating image...</span>
                <span id="loading-detail" class="text-amber-600 text-sm">This may take a few seconds</span>
                <span id="loading-meta" class="text-amber-500 text-xs"></span>
              </div>
            </div>
          </div>
          
          <!-- Caption de imagen (si hay) -->
          <div id="image-caption" class="hidden shrink-0 mt-3 text-center text-sm text-slate-600 px-4"></div>
          
        </div><!-- /zona central -->
        
        <!-- Panel derecho: Controles de estilo (solo desktop) -->
        <aside id="controls-panel" class="hidden lg:flex w-72 glass-strong border-l border-slate-200/50 flex-col shrink-0 overflow-hidden">
          <div class="p-4 border-b border-slate-200/50">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2">
              <i class="iconoir-settings text-amber-500"></i>
              Parameters
            </h3>
          </div>
          
          <div class="flex-1 overflow-auto p-4 space-y-3">
            <!-- Acordeón: Formato -->
            <details class="group" open>
              <summary class="flex items-center justify-between cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors">
                <span class="text-sm font-medium text-slate-700 flex items-center gap-2">
                  <i class="iconoir-frame text-amber-500"></i> Format
                </span>
                <i class="iconoir-nav-arrow-down text-slate-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              <div class="pt-2 pb-1 grid grid-cols-3 gap-1.5">
                <label class="cursor-pointer">
                  <input type="radio" name="format" value="" class="hidden peer" checked />
                  <div class="format-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex flex-col items-center justify-center h-full min-h-[52px]">
                    <i class="iconoir-prohibition text-lg"></i>
                    <span class="text-[10px] mt-1 font-medium">None</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="format" value="1:1" class="hidden peer" />
                  <div class="format-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex flex-col items-center justify-center h-full min-h-[52px]">
                    <div class="w-4 h-4 border border-current rounded-sm mb-1"></div>
                    <span class="text-[10px] font-medium">1:1</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="format" value="3:4" class="hidden peer" />
                  <div class="format-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex flex-col items-center justify-center h-full min-h-[52px]">
                    <div class="w-3 h-4 border border-current rounded-sm mb-1"></div>
                    <span class="text-[10px] font-medium">3:4</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="format" value="4:3" class="hidden peer" />
                  <div class="format-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex flex-col items-center justify-center h-full min-h-[52px]">
                    <div class="w-4 h-3 border border-current rounded-sm mb-1"></div>
                    <span class="text-[10px] font-medium">4:3</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="format" value="16:9" class="hidden peer" />
                  <div class="format-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex flex-col items-center justify-center h-full min-h-[52px]">
                    <div class="w-5 h-3 border border-current rounded-sm mb-1"></div>
                    <span class="text-[10px] font-medium">16:9</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="format" value="9:16" class="hidden peer" />
                  <div class="format-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex flex-col items-center justify-center h-full min-h-[52px]">
                    <div class="w-2.5 h-5 border border-current rounded-sm mb-1"></div>
                    <span class="text-[10px] font-medium">9:16</span>
                  </div>
                </label>
              </div>
            </details>

            <!-- Acordeón: Composición -->
            <details class="group">
              <summary class="flex items-center justify-between cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors">
                <span class="text-sm font-medium text-slate-700 flex items-center gap-2">
                  <i class="iconoir-frame-select text-amber-500"></i> Composition
                </span>
                <i class="iconoir-nav-arrow-down text-slate-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              <div class="pt-2 pb-1 grid grid-cols-2 gap-1.5">
                <label class="cursor-pointer">
                  <input type="radio" name="composition" value="" class="hidden peer" checked />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-prohibition text-sm"></i>
                    <span class="text-[10px] font-medium">None</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="composition" value="bokeh" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-focus text-sm"></i>
                    <span class="text-[10px] font-medium">Bokeh</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="composition" value="closeup" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-zoom-in text-sm"></i>
                    <span class="text-[10px] font-medium">Close-up</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="composition" value="wide" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-zoom-out text-sm"></i>
                    <span class="text-[10px] font-medium">Wide shot</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="composition" value="above" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-arrow-down text-sm"></i>
                    <span class="text-[10px] font-medium">Top-down</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="composition" value="below" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-arrow-up text-sm"></i>
                    <span class="text-[10px] font-medium">Low angle</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="composition" value="macro" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-eye-alt text-sm"></i>
                    <span class="text-[10px] font-medium">Macro</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="composition" value="negative-space" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-square text-sm"></i>
                    <span class="text-[10px] font-medium">Neg. space</span>
                  </div>
                </label>
              </div>
            </details>
            
            <!-- Acordeón: Estilo -->
            <details class="group">
              <summary class="flex items-center justify-between cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors">
                <span class="text-sm font-medium text-slate-700 flex items-center gap-2">
                  <i class="iconoir-design-pencil text-amber-500"></i> Style
                </span>
                <i class="iconoir-nav-arrow-down text-slate-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              <div class="pt-2 pb-1 grid grid-cols-2 gap-1.5">
                <label class="cursor-pointer">
                  <input type="radio" name="style" value="" class="hidden peer" checked />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-prohibition text-sm"></i>
                    <span class="text-[10px] font-medium">None</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="style" value="photographic" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-camera text-sm"></i>
                    <span class="text-[10px] font-medium">Photographic</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="style" value="digital-art" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-design-pencil text-sm"></i>
                    <span class="text-[10px] font-medium">Digital Art</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="style" value="corporate" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-building text-sm"></i>
                    <span class="text-[10px] font-medium">Corporate</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="style" value="minimalist" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-minus text-sm"></i>
                    <span class="text-[10px] font-medium">Minimalist</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="style" value="3d-render" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-3d-select-face text-sm"></i>
                    <span class="text-[10px] font-medium">3D Render</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="style" value="flat-design" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-crop text-sm"></i>
                    <span class="text-[10px] font-medium">Flat Design</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="style" value="isometric" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-cube text-sm"></i>
                    <span class="text-[10px] font-medium">Isometric</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="style" value="luxury-product" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-diamond text-sm"></i>
                    <span class="text-[10px] font-medium">Luxury product</span>
                  </div>
                </label>
              </div>
            </details>

            <!-- Acordeón: Iluminación -->
            <details class="group">
              <summary class="flex items-center justify-between cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors">
                <span class="text-sm font-medium text-slate-700 flex items-center gap-2">
                  <i class="iconoir-sun-light text-amber-500"></i> Lighting
                </span>
                <i class="iconoir-nav-arrow-down text-slate-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              <div class="pt-2 pb-1 grid grid-cols-2 gap-1.5">
                <label class="cursor-pointer">
                  <input type="radio" name="lighting" value="" class="hidden peer" checked />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-prohibition text-sm"></i>
                    <span class="text-[10px] font-medium">None</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="lighting" value="natural" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-sun-light text-sm"></i>
                    <span class="text-[10px] font-medium">Natural</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="lighting" value="studio" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-flash text-sm"></i>
                    <span class="text-[10px] font-medium">Studio</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="lighting" value="dramatic" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-half-moon text-sm"></i>
                    <span class="text-[10px] font-medium">Dramatic</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="lighting" value="soft" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-cloud text-sm"></i>
                    <span class="text-[10px] font-medium">Soft</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="lighting" value="golden" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-sunrise text-sm"></i>
                    <span class="text-[10px] font-medium">Golden hour</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="lighting" value="backlight" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-lens text-sm"></i>
                    <span class="text-[10px] font-medium">Backlight</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="lighting" value="volumetric" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-sparks text-sm"></i>
                    <span class="text-[10px] font-medium">Volumetric</span>
                  </div>
                </label>
              </div>
            </details>

            <!-- Acordeón: Color -->
            <details class="group">
              <summary class="flex items-center justify-between cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors">
                <span class="text-sm font-medium text-slate-700 flex items-center gap-2">
                  <i class="iconoir-color-filter text-amber-500"></i> Color
                </span>
                <i class="iconoir-nav-arrow-down text-slate-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              <div class="pt-2 pb-1 grid grid-cols-2 gap-1.5">
                <label class="cursor-pointer">
                  <input type="radio" name="color" value="" class="hidden peer" checked />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-prohibition text-sm"></i>
                    <span class="text-[10px] font-medium">None</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="color" value="warm" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <div class="w-4 h-4 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500"></div>
                    <span class="text-[10px] font-medium">Warm</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="color" value="cool" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <div class="w-4 h-4 rounded-full bg-gradient-to-br from-cyan-400 to-blue-500"></div>
                    <span class="text-[10px] font-medium">Cool</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="color" value="corporate" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <div class="w-4 h-4 rounded-full bg-gradient-to-br from-[#23AAC5] to-[#115c6c]"></div>
                    <span class="text-[10px] font-medium">Corporate</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="color" value="monochrome" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <div class="w-4 h-4 rounded-full bg-gradient-to-br from-slate-300 to-slate-600"></div>
                    <span class="text-[10px] font-medium">Monochrome</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="color" value="pastel" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <div class="w-4 h-4 rounded-full bg-gradient-to-br from-pink-200 to-purple-200"></div>
                    <span class="text-[10px] font-medium">Pastel</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="color" value="bw" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <div class="w-4 h-4 rounded-full bg-gradient-r from-black to-white"></div>
                    <span class="text-[10px] font-medium">B/N</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="color" value="vibrant" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <div class="w-4 h-4 rounded-full bg-gradient-to-br from-red-500 via-yellow-500 to-green-500"></div>
                    <span class="text-[10px] font-medium">Vibrant</span>
                  </div>
                </label>
              </div>
            </details>
          </div>  
          </div><!-- /controles scroll -->
        </aside>
        
      </div><!-- /flex principal -->
    </main>
  </div><!-- /main container -->

  <!-- Lightbox para ver imagen en grande -->
  <div id="image-lightbox" class="fixed inset-0 bg-black/95 z-50 hidden items-center justify-center p-4">
    <button id="lightbox-close" class="absolute top-4 right-4 text-white/80 hover:text-white p-2 z-10">
      <i class="iconoir-xmark text-3xl"></i>
    </button>
    <img id="lightbox-image" src="" alt="Enlarged image" class="max-w-full max-h-full object-contain" />
  </div>

  <!-- Modal de parámetros para móvil -->
  <div id="params-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4 lg:hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[80vh] flex flex-col">
      <!-- Header -->
      <div class="p-4 border-b border-slate-200 flex items-center justify-between">
        <h3 class="font-semibold text-slate-800 flex items-center gap-2">
          <i class="iconoir-settings text-amber-500"></i>
          Image parameters
        </h3>
        <button id="close-params-modal" class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-all">
          <i class="iconoir-xmark text-xl"></i>
        </button>
      </div>
      
      <!-- Content (scroll) -->
      <div id="params-modal-content" class="flex-1 overflow-auto p-4 space-y-3">
        <!-- Content will be synced from desktop params panel -->
      </div>
    </div>
  </div>

  <script>window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken); ?>';</script>
  <script src="/assets/js/gesture-image-editor.js"></script>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
  
  <!-- Estilos adicionales -->
  <style>
    .format-pill.active, .format-pill:has(input:checked),
    .style-pill.active, .style-pill:has(input:checked) {
      border-color: #f59e0b !important;
      background: rgba(245, 158, 11, 0.1);
      color: #b45309;
    }
    
    .intent-card.active {
      border-color: #f59e0b;
      background: rgba(245, 158, 11, 0.08);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35);
    }

    .intent-card {
      cursor: pointer;
      transform: translateY(0);
      transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease, background-color .18s ease;
    }

    .intent-card:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 24px -22px rgba(2, 6, 23, .55);
    }
    
  </style>
  
  <script>
    // === Parámetros Modal (Móvil) ===
    document.addEventListener('DOMContentLoaded', () => {
      const openParamsBtn = document.getElementById('open-params-mobile');
      const paramsModal = document.getElementById('params-modal');
      const closeParamsBtn = document.getElementById('close-params-modal');
      const paramsModalContent = document.getElementById('params-modal-content');
      const desktopParamsPanel = document.querySelector('#controls-panel .flex-1.overflow-auto');
      
      // Sincronizar contenido del panel desktop al modal móvil
      let modalInitialized = false;
      
      // Función para sincronizar el estado visual de todos los radios checked
      function syncAllRadioStates() {
        // Obtener todos los radios checked del desktop
        const checkedRadios = desktopParamsPanel.querySelectorAll('input[type="radio"]:checked');
        
        checkedRadios.forEach(desktopRadio => {
          const modalRadio = paramsModalContent.querySelector(`input[name="${desktopRadio.name}"][value="${desktopRadio.value}"]`);
          if (modalRadio) {
            modalRadio.checked = true;
          }
        });
      }
      
      function syncParamsContent() {
        if (desktopParamsPanel && paramsModalContent) {
          // Solo copiar HTML la primera vez
          if (!modalInitialized) {
            paramsModalContent.innerHTML = desktopParamsPanel.innerHTML;
            modalInitialized = true;
            
            // Añadir listeners a los radios del modal para sincronizar con desktop
            paramsModalContent.querySelectorAll('input[type="radio"]').forEach(radio => {
              radio.addEventListener('change', () => {
                // Encontrar el radio correspondiente en desktop y marcarlo
                const desktopRadio = desktopParamsPanel.querySelector(`input[name="${radio.name}"][value="${radio.value}"]`);
                if (desktopRadio) {
                  desktopRadio.checked = true;
                  // Disparar evento change en desktop para que se actualice el resumen
                  desktopRadio.dispatchEvent(new Event('change', { bubbles: true }));
                }
              });
            });
          }
          
          // SIEMPRE sincronizar el estado de todos los radios al abrir
          syncAllRadioStates();
        }
      }
      
      // Abrir modal
      if (openParamsBtn && paramsModal) {
        openParamsBtn.addEventListener('click', () => {
          syncParamsContent();
          paramsModal.classList.remove('hidden');
          paramsModal.classList.add('flex');
        });
      }
      
      // Cerrar modal
      if (closeParamsBtn && paramsModal) {
        closeParamsBtn.addEventListener('click', () => {
          paramsModal.classList.add('hidden');
          paramsModal.classList.remove('flex');
        });
      }
      
      // Cerrar al hacer clic fuera
      if (paramsModal) {
        paramsModal.addEventListener('click', (e) => {
          if (e.target === paramsModal) {
            paramsModal.classList.add('hidden');
            paramsModal.classList.remove('flex');
          }
        });
      }
      
      // === Sincronizar historial con drawer móvil ===
      const desktopHistory = document.getElementById('history-list');
      const mobileDrawerContent = document.getElementById('gesture-history-drawer-content');
      
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
