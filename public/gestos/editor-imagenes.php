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
$headerBackText = 'Todos los gestos';
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
    
    <!-- Sidebar de historial (solo desktop) -->
    <aside id="history-sidebar" class="hidden lg:flex w-72 glass-strong border-r border-slate-200/50 flex-col shrink-0">
      <div class="p-4 border-b border-slate-200/50">
        <div class="flex items-center justify-between">
          <h2 class="font-bold text-slate-800 flex items-center gap-2 text-base">
            <i class="iconoir-clock text-amber-500"></i>
            Historial
          </h2>
          <button id="new-image-btn" class="p-1.5 text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-smooth" title="Nueva imagen">
            <i class="iconoir-plus text-lg"></i>
          </button>
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
    $drawerId = 'gesture-history-drawer';
    $drawerTitle = 'Historial';
    $drawerIcon = 'iconoir-clock';
    $drawerIconColor = 'text-amber-500';
    include __DIR__ . '/../includes/mobile-drawer.php'; 
    ?>
    
    <!-- Main content area (imagen central + controles superiores/inferiores) -->
    <main class="flex-1 flex flex-col overflow-hidden min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <div class="flex-1 flex overflow-hidden">
        <!-- Zona central: imagen + controles superiores/inferiores -->
        <div class="flex-1 flex flex-col overflow-hidden p-4 lg:p-6">
          
          <!-- Controles superiores: Prompt + Modo + Provider -->
          <div class="shrink-0 mb-4 space-y-3">
            <!-- Fila 1: Modo y Provider -->
            <div class="flex flex-wrap items-center gap-3">
              <!-- Toggle Modo -->
              <div class="flex bg-white p-1 rounded-xl border border-slate-200 shadow-sm">
                <button type="button" id="mode-generate" class="mode-toggle-btn px-3 py-1.5 rounded-lg text-xs font-semibold transition-all active">
                  <i class="iconoir-sparks mr-1"></i>Generar
                </button>
                <button type="button" id="mode-edit" class="mode-toggle-btn px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">
                  <i class="iconoir-edit mr-1"></i>Editar
                </button>
              </div>
              
              <!-- Toggle Provider -->
              <div class="flex bg-white p-1 rounded-xl border border-slate-200 shadow-sm">
                <button type="button" id="provider-qwen" class="provider-toggle-btn px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all active" data-provider="qwen">
                  <span class="w-2 h-2 rounded-full bg-purple-500 inline-block mr-1"></span>Qwen
                </button>
                <button type="button" id="provider-nanobanana" class="provider-toggle-btn px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all" data-provider="nanobanana">
                  <span class="w-2 h-2 rounded-full bg-blue-500 inline-block mr-1"></span>Nanobanana
                </button>
                <button type="button" id="provider-flux" class="provider-toggle-btn px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all" data-provider="flux">
                  <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block mr-1"></span>FLUX
                </button>
              </div>
              <input type="hidden" id="current-mode" value="generate" />
              <input type="hidden" id="current-provider" value="qwen" />
            </div>
            
            <!-- Fila 2: Prompt principal -->
            <div class="flex gap-3 items-stretch">
              <div class="flex-1 relative">
                <textarea id="image-description" rows="2" 
                  class="w-full border-2 border-slate-200 rounded-2xl px-5 py-4 pr-12 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 transition-all resize-none bg-white/90 text-base leading-relaxed"
                  placeholder="Describe la imagen que quieres crear..."></textarea>
              </div>
              <button type="button" id="generate-image-btn" 
                class="shrink-0 px-8 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-bold rounded-2xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all flex items-center justify-center gap-2 min-w-[140px]">
                <i class="iconoir-sparks text-xl"></i>
                <span class="hidden sm:inline">Generar</span>
              </button>
            </div>
          </div>
          
          <!-- Zona imagen central (flex-1 para ocupar espacio disponible) -->
          <div class="flex-1 flex items-center justify-center overflow-hidden relative min-h-0">
            <!-- Placeholder inicial -->
            <div id="image-placeholder" class="flex flex-col items-center justify-center text-center p-8">
              <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-amber-100 to-orange-100 flex items-center justify-center mb-4">
                <i class="iconoir-media-image text-4xl text-amber-500"></i>
              </div>
              <h3 class="text-lg font-semibold text-slate-700 mb-2">Genera tu primera imagen</h3>
              <p class="text-sm text-slate-500 max-w-sm">Describe lo que quieres crear y ajusta los parámetros en el panel derecho</p>
            </div>
            
            <!-- Sección imagen fuente (modo edición) -->
            <div id="edit-source-section" class="hidden absolute inset-0 flex items-center justify-center">
              <div class="text-center">
                <div id="source-image-dropzone" class="relative w-64 h-64 border-2 border-dashed border-slate-300 rounded-2xl flex flex-col items-center justify-center hover:border-amber-400 hover:bg-amber-50/30 transition-all cursor-pointer">
                  <input type="file" id="source-image-input" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                  <div id="source-image-placeholder" class="flex flex-col items-center gap-3">
                    <i class="iconoir-upload text-4xl text-slate-400"></i>
                    <span class="text-sm text-slate-500">Sube la imagen a editar</span>
                    <span class="text-xs text-slate-400">Arrastra o haz clic</span>
                  </div>
                  <img id="source-image-preview" src="" alt="Imagen fuente" class="hidden w-full h-full object-contain rounded-2xl" />
                  <button type="button" id="source-image-clear" class="hidden absolute -top-2 -right-2 p-1.5 bg-red-500 text-white rounded-full text-xs hover:bg-red-600 shadow-lg">
                    <i class="iconoir-xmark"></i>
                  </button>
                </div>
                <p class="mt-3 text-xs text-slate-500">Sube una imagen para editarla con IA</p>
              </div>
            </div>
            
            <!-- Imagen generada -->
            <div id="image-result" class="hidden absolute inset-0 flex items-center justify-center p-4">
              <div class="relative max-w-full max-h-full">
                <img id="generated-image" src="" alt="Imagen generada" 
                  class="max-w-full max-h-[calc(100vh-320px)] object-contain rounded-xl shadow-2xl cursor-pointer hover:shadow-3xl transition-shadow" />
                <!-- Overlay de acciones sobre la imagen -->
                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 bg-black/50 backdrop-blur-md rounded-xl p-2">
                  <button id="edit-this-image-btn" class="px-3 py-2 text-white hover:bg-white/20 rounded-lg transition-all flex items-center gap-1.5 text-sm" title="Editar esta imagen">
                    <i class="iconoir-edit"></i>
                    <span class="hidden sm:inline">Editar</span>
                  </button>
                  <button id="regenerate-image-btn" class="px-3 py-2 text-white hover:bg-white/20 rounded-lg transition-all flex items-center gap-1.5 text-sm" title="Regenerar">
                    <i class="iconoir-refresh"></i>
                  </button>
                  <button id="download-image-btn" class="px-3 py-2 text-white hover:bg-white/20 rounded-lg transition-all flex items-center gap-1.5 text-sm" title="Descargar">
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
                  <div class="absolute inset-0 flex items-center justify-center text-2xl">🍌</div>
                </div>
                <span class="text-amber-700 font-medium">Generando imagen...</span>
                <span class="text-amber-600 text-sm">Esto puede tardar unos segundos</span>
              </div>
            </div>
          </div>
          
          <!-- Caption de imagen (si hay) -->
          <div id="image-caption" class="hidden shrink-0 mt-3 text-center text-sm text-slate-600 px-4"></div>
          
        </div><!-- /zona central -->
        
        <!-- Panel derecho: Controles de estilo (solo desktop) -->
        <aside id="controls-panel" class="hidden lg:flex w-80 glass-strong border-l border-slate-200/50 flex-col shrink-0 overflow-hidden">
          <div class="p-4 border-b border-slate-200/50 bg-slate-50/50">
            <h3 class="font-bold text-slate-800 flex items-center gap-2 text-base">
              <i class="iconoir-settings text-amber-500"></i>
              Parámetros
            </h3>
          </div>

          <!-- Resumen de selección (arriba) -->
          <div class="p-4 border-b border-amber-200/30 bg-amber-50/40">
            <div class="flex flex-col gap-1">
              <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider">Configuración actual</span>
              <span id="summary-text" class="text-xs font-semibold text-amber-800 leading-snug">1:1</span>
            </div>
          </div>
          
          <div class="flex-1 overflow-auto p-4 space-y-4">
            <!-- Acordeón: Formato -->
            <details class="group" open>
              <summary class="flex items-center justify-between cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors">
                <span class="text-sm font-medium text-slate-700 flex items-center gap-2">
                  <i class="iconoir-frame text-amber-500"></i> Formato
                </span>
                <i class="iconoir-nav-arrow-down text-slate-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              <div class="pt-2 pb-1 grid grid-cols-5 gap-1.5">
                <label class="cursor-pointer">
                  <input type="radio" name="format" value="1:1" class="hidden peer" checked />
                  <div class="format-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all text-center">
                    <div class="w-5 h-5 mx-auto border border-current rounded-sm"></div>
                    <span class="text-[10px] mt-1 block">1:1</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="format" value="3:4" class="hidden peer" />
                  <div class="format-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all text-center">
                    <div class="w-4 h-5 mx-auto border border-current rounded-sm"></div>
                    <span class="text-[10px] mt-1 block">3:4</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="format" value="4:3" class="hidden peer" />
                  <div class="format-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all text-center">
                    <div class="w-5 h-4 mx-auto border border-current rounded-sm"></div>
                    <span class="text-[10px] mt-1 block">4:3</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="format" value="16:9" class="hidden peer" />
                  <div class="format-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all text-center">
                    <div class="w-6 h-3 mx-auto border border-current rounded-sm"></div>
                    <span class="text-[10px] mt-1 block">16:9</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="format" value="9:16" class="hidden peer" />
                  <div class="format-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all text-center">
                    <div class="w-3 h-6 mx-auto border border-current rounded-sm"></div>
                    <span class="text-[10px] mt-1 block">9:16</span>
                  </div>
                </label>
              </div>
            </details>
            
            <!-- Acordeón: Estilo -->
            <details class="group">
              <summary class="flex items-center justify-between cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors">
                <span class="text-sm font-medium text-slate-700 flex items-center gap-2">
                  <i class="iconoir-design-pencil text-amber-500"></i> Estilo
                </span>
                <i class="iconoir-nav-arrow-down text-slate-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              <div class="pt-2 pb-1 grid grid-cols-2 gap-1.5">
                <label class="cursor-pointer">
                  <input type="radio" name="style" value="" class="hidden peer" checked />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-prohibition text-sm"></i>
                    <span class="text-[10px] font-medium">Ninguno</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="style" value="photographic" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-camera text-sm"></i>
                    <span class="text-[10px] font-medium">Fotográfico</span>
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
                    <span class="text-[10px] font-medium">Corporativo</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="style" value="minimalist" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-minus text-sm"></i>
                    <span class="text-[10px] font-medium">Minimalista</span>
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
                    <span class="text-[10px] font-medium">Isométrico</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="style" value="headshot-pro" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-user text-sm"></i>
                    <span class="text-[10px] font-medium">Retrato Pro</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="style" value="luxury-product" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-diamond text-sm"></i>
                    <span class="text-[10px] font-medium">Producto Lujo</span>
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
                    <span class="text-[10px] font-medium">Ninguno</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="color" value="warm" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <div class="w-4 h-4 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500"></div>
                    <span class="text-[10px] font-medium">Cálidos</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="color" value="cool" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <div class="w-4 h-4 rounded-full bg-gradient-to-br from-cyan-400 to-blue-500"></div>
                    <span class="text-[10px] font-medium">Fríos</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="color" value="corporate" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <div class="w-4 h-4 rounded-full bg-gradient-to-br from-[#23AAC5] to-[#115c6c]"></div>
                    <span class="text-[10px] font-medium">Corporativo</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="color" value="monochrome" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <div class="w-4 h-4 rounded-full bg-gradient-to-br from-slate-300 to-slate-600"></div>
                    <span class="text-[10px] font-medium">Monocromo</span>
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
                    <div class="w-4 h-4 rounded-full bg-gradient-to-r from-black to-white"></div>
                    <span class="text-[10px] font-medium">B/N</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="color" value="vibrant" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <div class="w-4 h-4 rounded-full bg-gradient-to-br from-red-500 via-yellow-500 to-green-500"></div>
                    <span class="text-[10px] font-medium">Vibrante</span>
                  </div>
                </label>
              </div>
            </details>
            
            <!-- Acordeón: Iluminación -->
            <details class="group">
              <summary class="flex items-center justify-between cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors">
                <span class="text-sm font-medium text-slate-700 flex items-center gap-2">
                  <i class="iconoir-sun-light text-amber-500"></i> Iluminación
                </span>
                <i class="iconoir-nav-arrow-down text-slate-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              <div class="pt-2 pb-1 grid grid-cols-2 gap-1.5">
                <label class="cursor-pointer">
                  <input type="radio" name="lighting" value="" class="hidden peer" checked />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-prohibition text-sm"></i>
                    <span class="text-[10px] font-medium">Ninguno</span>
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
                    <span class="text-[10px] font-medium">Estudio</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="lighting" value="dramatic" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-half-moon text-sm"></i>
                    <span class="text-[10px] font-medium">Dramática</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="lighting" value="soft" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-cloud text-sm"></i>
                    <span class="text-[10px] font-medium">Suave</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="lighting" value="golden" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-sunrise text-sm"></i>
                    <span class="text-[10px] font-medium">Hora dorada</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="lighting" value="backlight" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-lens text-sm"></i>
                    <span class="text-[10px] font-medium">Contraluz</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="lighting" value="volumetric" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-sparks text-sm"></i>
                    <span class="text-[10px] font-medium">Volumétrica</span>
                  </div>
                </label>
              </div>
            </details>
            
            <!-- Acordeón: Composición -->
            <details class="group">
              <summary class="flex items-center justify-between cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors">
                <span class="text-sm font-medium text-slate-700 flex items-center gap-2">
                  <i class="iconoir-frame-select text-amber-500"></i> Composición
                </span>
                <i class="iconoir-nav-arrow-down text-slate-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              <div class="pt-2 pb-1 grid grid-cols-2 gap-1.5">
                <label class="cursor-pointer">
                  <input type="radio" name="composition" value="" class="hidden peer" checked />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-prohibition text-sm"></i>
                    <span class="text-[10px] font-medium">Ninguno</span>
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
                    <span class="text-[10px] font-medium">Primer plano</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="composition" value="wide" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-zoom-out text-sm"></i>
                    <span class="text-[10px] font-medium">Plano general</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="composition" value="above" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-arrow-down text-sm"></i>
                    <span class="text-[10px] font-medium">Cenital</span>
                  </div>
                </label>
                <label class="cursor-pointer">
                  <input type="radio" name="composition" value="below" class="hidden peer" />
                  <div class="style-pill peer-checked:active p-2 border border-slate-200 rounded-lg hover:border-amber-400 transition-all flex items-center gap-1.5">
                    <i class="iconoir-arrow-up text-sm"></i>
                    <span class="text-[10px] font-medium">Contrapicado</span>
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
                    <span class="text-[10px] font-medium">Espacio neg.</span>
                  </div>
                </label>
              </div>
            </details>
            
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
    <img id="lightbox-image" src="" alt="Imagen ampliada" class="max-w-full max-h-full object-contain" />
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
      background: rgba(245, 158, 11, 0.08) !important;
      color: #b45309 !important;
      box-shadow: 0 2px 4px rgba(245, 158, 11, 0.1);
    }
    
    .format-pill, .style-pill {
      transition: all 0.2s ease;
      border-width: 1.5px;
    }

    .format-pill:hover, .style-pill:hover {
      border-color: #fbbf24;
      background: rgba(245, 158, 11, 0.03);
    }
    
    .mode-toggle-btn {
      color: #64748b;
      background: transparent;
    }
    
    .mode-toggle-btn.active {
      background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%) !important;
      color: white !important;
      box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
      transform: translateY(-1px);
    }
    
    .mode-toggle-btn:not(.active):hover {
      background: rgba(245, 158, 11, 0.05);
      color: #d97706;
    }
    
    .provider-toggle-btn {
      color: #64748b;
      background: transparent;
    }
    
    .provider-toggle-btn.active {
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
      color: white !important;
      box-shadow: 0 2px 8px rgba(139, 92, 246, 0.25);
    }
    
    .provider-toggle-btn.active span {
      background: white !important;
    }
  </style>
  
  <script>
    // Sincronizar historial con drawer móvil
    document.addEventListener('DOMContentLoaded', () => {
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
