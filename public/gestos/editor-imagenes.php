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
          <h2 class="font-semibold text-slate-800 flex items-center gap-2">
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
    
    <!-- Main content area -->
    <main class="flex-1 flex flex-col overflow-hidden min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <!-- Scrollable content -->
      <div class="flex-1 overflow-auto p-4 lg:p-6 pb-20 lg:pb-6">
        <div class="max-w-4xl mx-auto">
          <!-- Header del gesto -->
          <div class="flex items-center gap-4 mb-6">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center text-white shadow-lg">
              <i class="iconoir-media-image text-xl"></i>
            </div>
            <div>
              <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                Editor de imágenes
                <span class="text-lg">🍌</span>
              </h1>
              <p class="text-sm text-slate-600">Genera imágenes con Nanobanana</p>
            </div>
          </div>
    
          <!-- Formulario del gesto -->
          <form id="image-editor-form" class="space-y-6 glass-strong rounded-2xl border border-slate-200/50 p-6 shadow-sm">
            
            <!-- Toggle Generar / Editar -->
            <div class="flex gap-2 p-1 bg-slate-100 rounded-xl">
              <button type="button" id="mode-generate" class="mode-btn active flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all flex items-center justify-center gap-2">
                <i class="iconoir-sparks"></i>
                Generar
              </button>
              <button type="button" id="mode-edit" class="mode-btn flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all flex items-center justify-center gap-2">
                <i class="iconoir-edit"></i>
                Editar
              </button>
            </div>
            <input type="hidden" id="current-mode" value="generate" />

            <!-- Sección de imágenes para modo Editar (oculta por defecto) -->
            <div id="edit-images-section" class="hidden space-y-4">
              <div class="p-4 bg-purple-50/50 border border-purple-200/50 rounded-xl">
                <p class="text-sm text-purple-700 mb-3 flex items-center gap-2">
                  <i class="iconoir-info-circle"></i>
                  <span><strong>Modo edición:</strong> Sube una imagen fuente para editarla. Opcionalmente, añade una imagen objetivo para fusionar elementos.</span>
                </p>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <!-- Imagen Fuente -->
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-2">Imagen fuente <span class="text-red-500">*</span></label>
                    <div id="source-image-dropzone" class="relative border-2 border-dashed border-slate-300 rounded-xl p-4 text-center hover:border-amber-400 hover:bg-amber-50/30 transition-all cursor-pointer min-h-[120px] flex flex-col items-center justify-center">
                      <input type="file" id="source-image-input" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                      <div id="source-image-placeholder" class="flex flex-col items-center gap-2">
                        <i class="iconoir-upload text-2xl text-slate-400"></i>
                        <span class="text-xs text-slate-500">Arrastra o haz clic</span>
                      </div>
                      <img id="source-image-preview" src="" alt="Fuente" class="hidden max-h-24 rounded-lg object-contain" />
                      <button type="button" id="source-image-clear" class="hidden absolute top-2 right-2 p-1 bg-red-500 text-white rounded-full text-xs hover:bg-red-600">
                        <i class="iconoir-xmark"></i>
                      </button>
                    </div>
                  </div>
                  
                  <!-- Imagen Objetivo (opcional) -->
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-2">Imagen objetivo <span class="text-slate-400">(opcional)</span></label>
                    <div id="target-image-dropzone" class="relative border-2 border-dashed border-slate-300 rounded-xl p-4 text-center hover:border-amber-400 hover:bg-amber-50/30 transition-all cursor-pointer min-h-[120px] flex flex-col items-center justify-center">
                      <input type="file" id="target-image-input" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                      <div id="target-image-placeholder" class="flex flex-col items-center gap-2">
                        <i class="iconoir-upload text-2xl text-slate-400"></i>
                        <span class="text-xs text-slate-500">Arrastra o haz clic</span>
                      </div>
                      <img id="target-image-preview" src="" alt="Objetivo" class="hidden max-h-24 rounded-lg object-contain" />
                      <button type="button" id="target-image-clear" class="hidden absolute top-2 right-2 p-1 bg-red-500 text-white rounded-full text-xs hover:bg-red-600">
                        <i class="iconoir-xmark"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Campo principal: Descripción -->
            <div>
              <label id="description-label" class="block text-sm font-semibold text-slate-700 mb-2">¿Qué imagen quieres crear?</label>
              <textarea id="image-description" rows="3" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all resize-none bg-white/80" placeholder="Describe la imagen que necesitas. Sé específico: objetos, escena, ambiente, colores..."></textarea>
            </div>

            <!-- Selectores en tabs horizontales (solo para modo generar) -->
            <div id="style-options-section">
            <div class="border border-slate-200/50 rounded-xl overflow-hidden">
              <!-- Tab headers -->
              <div class="flex border-b border-slate-200/50 bg-slate-50/50 overflow-x-auto">
                <button type="button" data-tab="format" class="option-tab active flex-shrink-0 px-4 py-3 text-sm font-medium text-slate-600 hover:text-amber-600 border-b-2 border-transparent transition-all flex items-center gap-2">
                  <i class="iconoir-frame"></i>
                  <span>Formato</span>
                </button>
                <button type="button" data-tab="style" class="option-tab flex-shrink-0 px-4 py-3 text-sm font-medium text-slate-600 hover:text-amber-600 border-b-2 border-transparent transition-all flex items-center gap-2">
                  <i class="iconoir-design-pencil"></i>
                  <span>Estilo</span>
                </button>
                <button type="button" data-tab="color" class="option-tab flex-shrink-0 px-4 py-3 text-sm font-medium text-slate-600 hover:text-amber-600 border-b-2 border-transparent transition-all flex items-center gap-2">
                  <i class="iconoir-color-filter"></i>
                  <span>Color</span>
                </button>
                <button type="button" data-tab="lighting" class="option-tab flex-shrink-0 px-4 py-3 text-sm font-medium text-slate-600 hover:text-amber-600 border-b-2 border-transparent transition-all flex items-center gap-2">
                  <i class="iconoir-sun-light"></i>
                  <span>Luz</span>
                </button>
                <button type="button" data-tab="composition" class="option-tab flex-shrink-0 px-4 py-3 text-sm font-medium text-slate-600 hover:text-amber-600 border-b-2 border-transparent transition-all flex items-center gap-2">
                  <i class="iconoir-frame-select"></i>
                  <span>Composición</span>
                </button>
              </div>

              <!-- Tab contents -->
              <div class="p-4">
                <!-- FORMATO -->
                <div id="tab-format" class="tab-content">
                  <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                    <label class="cursor-pointer">
                      <input type="radio" name="format" value="1:1" class="hidden peer" checked />
                      <div class="option-pill peer-checked:active p-3 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all text-center">
                        <div class="w-8 h-8 mx-auto mb-1 border-2 border-current rounded flex items-center justify-center">
                          <div class="w-5 h-5 bg-current/20 rounded-sm"></div>
                        </div>
                        <span class="text-xs font-medium">1:1</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="format" value="3:4" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-3 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all text-center">
                        <div class="w-6 h-8 mx-auto mb-1 border-2 border-current rounded flex items-center justify-center">
                          <div class="w-4 h-6 bg-current/20 rounded-sm"></div>
                        </div>
                        <span class="text-xs font-medium">3:4</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="format" value="4:3" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-3 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all text-center">
                        <div class="w-8 h-6 mx-auto mb-1 border-2 border-current rounded flex items-center justify-center">
                          <div class="w-6 h-4 bg-current/20 rounded-sm"></div>
                        </div>
                        <span class="text-xs font-medium">4:3</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="format" value="16:9" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-3 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all text-center">
                        <div class="w-10 h-6 mx-auto mb-1 border-2 border-current rounded flex items-center justify-center">
                          <div class="w-8 h-4 bg-current/20 rounded-sm"></div>
                        </div>
                        <span class="text-xs font-medium">16:9</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="format" value="9:16" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-3 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all text-center">
                        <div class="w-5 h-9 mx-auto mb-1 border-2 border-current rounded flex items-center justify-center">
                          <div class="w-3 h-7 bg-current/20 rounded-sm"></div>
                        </div>
                        <span class="text-xs font-medium">9:16</span>
                      </div>
                    </label>
                  </div>
                </div>

                <!-- ESTILO -->
                <div id="tab-style" class="tab-content hidden">
                  <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                    <label class="cursor-pointer">
                      <input type="radio" name="style" value="" class="hidden peer" checked />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-prohibition text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Ninguno</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="style" value="photographic" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-camera text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Fotográfico</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="style" value="digital-art" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-design-pencil text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Digital Art</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="style" value="corporate" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-building text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Corporativo</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="style" value="minimalist" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-minus text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Minimalista</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="style" value="3d-render" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-3d-select-face text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">3D Render</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="style" value="flat-design" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-crop text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Flat Design</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="style" value="isometric" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-cube text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Isométrico</span>
                      </div>
                    </label>
                  </div>
                </div>

                <!-- COLOR -->
                <div id="tab-color" class="tab-content hidden">
                  <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                    <label class="cursor-pointer">
                      <input type="radio" name="color" value="" class="hidden peer" checked />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-prohibition text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Ninguno</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="color" value="warm" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <div class="w-5 h-5 shrink-0 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500"></div>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Cálidos</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="color" value="cool" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <div class="w-5 h-5 shrink-0 rounded-full bg-gradient-to-br from-cyan-400 to-blue-500"></div>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Fríos</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="color" value="corporate" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <div class="w-5 h-5 shrink-0 rounded-full bg-gradient-to-br from-[#23AAC5] to-[#115c6c]"></div>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Corporativo</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="color" value="monochrome" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <div class="w-5 h-5 shrink-0 rounded-full bg-gradient-to-br from-slate-300 to-slate-600"></div>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Monocromo</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="color" value="pastel" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <div class="w-5 h-5 shrink-0 rounded-full bg-gradient-to-br from-pink-200 to-purple-200"></div>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Pastel</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="color" value="bw" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <div class="w-5 h-5 shrink-0 rounded-full bg-gradient-r from-black to-white"></div>
                        <span class="text-[10px] sm:text-xs font-medium truncate">B/N</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="color" value="vibrant" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <div class="w-5 h-5 shrink-0 rounded-full bg-gradient-to-br from-red-500 via-yellow-500 to-green-500"></div>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Vibrante</span>
                      </div>
                    </label>
                  </div>
                </div>

                <!-- ILUMINACIÓN -->
                <div id="tab-lighting" class="tab-content hidden">
                  <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                    <label class="cursor-pointer">
                      <input type="radio" name="lighting" value="" class="hidden peer" checked />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-prohibition text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Ninguno</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="lighting" value="natural" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-sun-light text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Natural</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="lighting" value="studio" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-flash text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Estudio</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="lighting" value="dramatic" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-half-moon text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Dramática</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="lighting" value="soft" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-cloud text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Suave</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="lighting" value="backlight" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-lens text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Contraluz</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="lighting" value="golden" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-sunrise text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Hora dorada</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="lighting" value="volumetric" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-sparks text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Volumétrica</span>
                      </div>
                    </label>
                  </div>
                </div>

                <!-- COMPOSICIÓN -->
                <div id="tab-composition" class="tab-content hidden">
                  <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                    <label class="cursor-pointer">
                      <input type="radio" name="composition" value="" class="hidden peer" checked />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-prohibition text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Ninguno</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="composition" value="bokeh" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-focus text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Bokeh</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="composition" value="closeup" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-zoom-in text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Primer plano</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="composition" value="wide" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-zoom-out text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Plano general</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="composition" value="above" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-arrow-down text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Cenital</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="composition" value="below" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-arrow-up text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Contrapicado</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="composition" value="macro" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-eye-alt text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Macro</span>
                      </div>
                    </label>
                    <label class="cursor-pointer">
                      <input type="radio" name="composition" value="negative-space" class="hidden peer" />
                      <div class="option-pill peer-checked:active p-2.5 border-2 border-slate-200 rounded-xl hover:border-amber-400 transition-all flex items-center gap-2">
                        <i class="iconoir-square text-lg shrink-0"></i>
                        <span class="text-[10px] sm:text-xs font-medium truncate">Espacio negativo</span>
                      </div>
                    </label>
                  </div>
                </div>
              </div>
            </div>
            </div><!-- /style-options-section -->

            <!-- Resumen de selección actual -->
            <div id="selection-summary" class="p-3 bg-amber-50/50 border border-amber-200/50 rounded-xl text-sm text-amber-800">
              <div class="flex items-center gap-2 mb-1">
                <i class="iconoir-info-circle"></i>
                <span class="font-medium">Configuración actual</span>
              </div>
              <p id="summary-text" class="text-xs text-amber-700">Formato 1:1 • Sin estilo específico</p>
            </div>
            
            <!-- Botón generar -->
            <div class="flex justify-end pt-2 border-t border-slate-200/50">
              <button type="submit" id="generate-image-btn" class="px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-all flex items-center gap-2">
                <i class="iconoir-sparks"></i>
                <span>Generar imagen</span>
              </button>
            </div>
          </form>
    
          <!-- Resultado (oculto inicialmente) -->
          <div id="image-result" class="hidden mt-8 glass-strong rounded-2xl border border-slate-200/50 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-lg font-semibold text-slate-800">Imagen generada</h2>
              <div class="flex gap-2">
                <button id="download-image-btn" class="px-3 py-1.5 text-sm text-slate-600 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-smooth flex items-center gap-1.5">
                  <i class="iconoir-download"></i> Descargar
                </button>
                <button id="regenerate-image-btn" class="px-3 py-1.5 text-sm text-slate-600 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-smooth flex items-center gap-1.5">
                  <i class="iconoir-refresh"></i> Regenerar
                </button>
              </div>
            </div>
            <div id="image-container" class="flex justify-center">
              <img id="generated-image" src="" alt="Imagen generada" class="max-w-full rounded-xl shadow-lg cursor-pointer hover:shadow-xl transition-shadow" />
            </div>
            <div id="image-caption" class="mt-4 text-sm text-slate-600 text-center hidden"></div>
          </div>
    
          <!-- Loading -->
          <div id="image-loading" class="hidden mt-8 text-center py-12">
            <div class="inline-flex flex-col items-center gap-4 px-8 py-6 bg-amber-500/10 rounded-xl">
              <div class="relative w-16 h-16">
                <div class="absolute inset-0 border-4 border-amber-500/30 rounded-full"></div>
                <div class="absolute inset-0 border-4 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
                <div class="absolute inset-0 flex items-center justify-center text-2xl">🍌</div>
              </div>
              <span class="text-amber-700 font-medium">Generando imagen con Nanobanana...</span>
              <span class="text-amber-600 text-sm">Esto puede tardar unos segundos</span>
            </div>
          </div>
        </div><!-- /max-w-4xl -->
      </div><!-- /scrollable content -->
    </main>
  </div><!-- /main container -->

  <!-- Lightbox para ver imagen en grande -->
  <div id="image-lightbox" class="fixed inset-0 bg-black/90 z-50 hidden items-center justify-center p-4">
    <button id="lightbox-close" class="absolute top-4 right-4 text-white/80 hover:text-white p-2">
      <i class="iconoir-xmark text-2xl"></i>
    </button>
    <img id="lightbox-image" src="" alt="Imagen ampliada" class="max-w-full max-h-full object-contain rounded-lg" />
  </div>

  <script>window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken); ?>';</script>
  <script src="/assets/js/gesture-image-editor.js"></script>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
  
  <!-- Estilos adicionales para los selectores -->
  <style>
    .option-tab.active {
      color: #f59e0b;
      border-bottom-color: #f59e0b;
      background: rgba(245, 158, 11, 0.05);
    }
    .option-pill.active,
    .option-pill:has(input:checked) {
      border-color: #f59e0b !important;
      background: rgba(245, 158, 11, 0.1);
      color: #b45309;
    }
    .mode-btn {
      color: #64748b;
      background: transparent;
    }
    .mode-btn.active {
      color: #fff;
      background: linear-gradient(to right, #f59e0b, #ea580c);
      box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
    }
    .mode-btn:not(.active):hover {
      background: rgba(245, 158, 11, 0.1);
      color: #f59e0b;
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
