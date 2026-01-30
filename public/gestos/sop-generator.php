<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';

use App\Session;
use Repos\UserFeatureAccessRepo;

$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

// Verificar acceso a este gesto
$accessRepo = new UserFeatureAccessRepo();
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'sop-generator')) {
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
$headerTitle = 'Generador de procesos';
$headerIcon = 'iconoir-clipboard-check';
$headerIconColor = 'from-emerald-500 to-teal-600';
$headerDrawerId = 'sop-history-drawer';
?><!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-mesh text-slate-900 overflow-hidden">
  <style>
    .source-card {
      transition: all 0.2s ease;
      position: relative;
      cursor: pointer;
    }
    .source-card:hover {
      transform: translateY(-2px);
      border-color: #10b981;
    }
    .source-card.has-content {
      border-color: #10b981;
      background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(20, 184, 166, 0.05) 100%);
    }
    .source-card.has-content .source-icon {
      background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%);
      color: white;
    }
    .source-card .check-badge {
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
    .source-card.has-content .check-badge {
      display: flex;
    }
    .source-card.active {
      border-color: #10b981;
      background: rgba(16, 185, 129, 0.05);
      ring: 2px solid #10b981;
    }
    .source-card.active .source-icon {
      background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%);
      color: white;
    }
    .history-item.active {
      background-color: rgba(16, 185, 129, 0.05);
      border-left: 3px solid #10b981;
    }
    .result-tabs {
      display: flex;
      gap: 4px;
      overflow-x: auto;
      padding-bottom: 8px;
      scrollbar-width: none;
    }
    .result-tabs::-webkit-scrollbar {
      display: none;
    }
    .result-tab {
      padding: 8px 16px;
      border-radius: 9999px;
      font-size: 14px;
      font-weight: 500;
      white-space: nowrap;
      border: 1px solid #e2e8f0;
      background: white;
      color: #64748b;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .result-tab:hover {
      background: #f8fafc;
    }
    .result-tab.active {
      background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%);
      color: white;
      border-color: transparent;
    }
    .result-panel {
      display: none;
    }
    .result-panel.active {
      display: block;
    }
    .mermaid-container {
      background: white;
      border-radius: 12px;
      padding: 16px;
      overflow: auto;
    }
    .processing-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 100;
      backdrop-filter: blur(4px);
    }
    .processing-overlay:not(.hidden) {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .processing-card {
      background: white;
      border-radius: 16px;
      padding: 32px;
      text-align: center;
      max-width: 400px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }
    .pulse-ring {
      animation: pulse-ring 1.5s ease-out infinite;
    }
    @keyframes pulse-ring {
      0% { transform: scale(0.9); opacity: 1; }
      100% { transform: scale(1.3); opacity: 0; }
    }
    .file-preview {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px;
      background: #f8fafc;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
    }
    .file-preview .file-icon {
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      font-size: 20px;
    }
    .file-preview .file-icon.audio {
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: white;
    }
    .file-preview .file-icon.image {
      background: linear-gradient(135deg, #8b5cf6, #7c3aed);
      color: white;
    }
    .file-preview .file-icon.pdf {
      background: linear-gradient(135deg, #ef4444, #dc2626);
      color: white;
    }
    .images-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
      gap: 8px;
    }
    .image-thumb {
      aspect-ratio: 1;
      border-radius: 8px;
      overflow: hidden;
      position: relative;
    }
    .image-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .image-thumb .remove-btn {
      position: absolute;
      top: 4px;
      right: 4px;
      width: 20px;
      height: 20px;
      background: rgba(0,0,0,0.6);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      cursor: pointer;
      opacity: 0;
      transition: opacity 0.2s;
    }
    .image-thumb:hover .remove-btn {
      opacity: 1;
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
    $drawerId = 'sop-history-drawer';
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
        <div class="max-w-4xl mx-auto p-4 lg:p-6 space-y-4 lg:space-y-6">
          
          <!-- Intro -->
          <div class="text-center mb-6">
            <h1 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-600 to-teal-600 mb-2">
              Generador de procesos
            </h1>
            <p class="text-slate-500 max-w-lg mx-auto">
              Transforma información desestructurada en procedimientos operativos profesionales. Sube texto, audio, imágenes o PDFs.
            </p>
          </div>

          <!-- Input Section -->
          <section id="input-section" class="glass-strong rounded-2xl p-6 border border-slate-200/50">
            <form id="sop-form" class="space-y-5">
              
              <!-- Título del SOP -->
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                  <i class="iconoir-text text-emerald-500 mr-1"></i>
                  Título del procedimiento <span class="text-slate-400 font-normal">(opcional)</span>
                </label>
                <input type="text" id="sop-title" 
                       class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition-all"
                       placeholder="Ej: Proceso de onboarding de nuevos empleados">
              </div>
              
              <!-- Fuentes de contenido -->
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-3">
                  <i class="iconoir-input-field text-emerald-500 mr-1"></i>
                  Fuentes de contenido <span class="text-slate-400 font-normal">(añade una o varias)</span>
                </label>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                  <!-- Texto -->
                  <div class="source-card p-4 rounded-xl border border-slate-200 bg-white" data-source="text">
                    <div class="check-badge"><i class="iconoir-check"></i></div>
                    <div class="source-icon w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center mb-2">
                      <i class="iconoir-text text-slate-500 text-xl"></i>
                    </div>
                    <div class="text-sm font-medium text-slate-700">Texto</div>
                    <div class="text-xs text-slate-400">Pega contenido</div>
                  </div>
                  
                  <!-- URL -->
                  <div class="source-card p-4 rounded-xl border border-slate-200 bg-white" data-source="url">
                    <div class="check-badge"><i class="iconoir-check"></i></div>
                    <div class="source-icon w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center mb-2">
                      <i class="iconoir-link text-slate-500 text-xl"></i>
                    </div>
                    <div class="text-sm font-medium text-slate-700">URL</div>
                    <div class="text-xs text-slate-400">Extrae de web</div>
                  </div>
                  
                  <!-- Audio -->
                  <div class="source-card p-4 rounded-xl border border-slate-200 bg-white" data-source="audio">
                    <div class="check-badge"><i class="iconoir-check"></i></div>
                    <div class="source-icon w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center mb-2">
                      <i class="iconoir-microphone text-slate-500 text-xl"></i>
                    </div>
                    <div class="text-sm font-medium text-slate-700">Audio</div>
                    <div class="text-xs text-slate-400">Transcribe</div>
                  </div>
                  
                  <!-- Imágenes -->
                  <div class="source-card p-4 rounded-xl border border-slate-200 bg-white" data-source="images">
                    <div class="check-badge"><i class="iconoir-check"></i></div>
                    <div class="source-icon w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center mb-2">
                      <i class="iconoir-media-image text-slate-500 text-xl"></i>
                    </div>
                    <div class="text-sm font-medium text-slate-700">Imágenes</div>
                    <div class="text-xs text-slate-400">Analiza capturas</div>
                  </div>
                </div>
                
                <!-- Paneles de entrada por tipo -->
                <div id="source-panels" class="space-y-4">
                  <!-- Panel Texto -->
                  <div id="panel-text" class="source-panel hidden">
                    <textarea id="input-text" rows="6"
                              class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition-all resize-none"
                              placeholder="Pega aquí el texto con la información del proceso: notas de reuniones, emails, instrucciones informales, etc."></textarea>
                  </div>
                  
                  <!-- Panel URL -->
                  <div id="panel-url" class="source-panel hidden">
                    <input type="url" id="input-url"
                           class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition-all"
                           placeholder="https://ejemplo.com/articulo-con-instrucciones">
                  </div>
                  
                  <!-- Panel Audio -->
                  <div id="panel-audio" class="source-panel hidden">
                    <div class="border-2 border-dashed border-slate-200 rounded-xl p-6 text-center hover:border-emerald-400 transition-colors cursor-pointer" id="audio-dropzone">
                      <input type="file" id="input-audio" accept="audio/*" class="hidden">
                      <div id="audio-placeholder">
                        <i class="iconoir-microphone text-4xl text-slate-300 mb-2"></i>
                        <p class="text-sm text-slate-500">Arrastra un archivo de audio o haz clic para seleccionar</p>
                        <p class="text-xs text-slate-400 mt-1">MP3, WAV, M4A, WebM (máx. 25MB)</p>
                      </div>
                      <div id="audio-preview" class="hidden">
                        <div class="file-preview">
                          <div class="file-icon audio">
                            <i class="iconoir-sound-high"></i>
                          </div>
                          <div class="flex-1 text-left">
                            <div class="font-medium text-slate-700" id="audio-name">archivo.mp3</div>
                            <div class="text-sm text-slate-400" id="audio-size">2.5 MB</div>
                          </div>
                          <button type="button" id="remove-audio" class="text-slate-400 hover:text-red-500 p-2">
                            <i class="iconoir-xmark"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Panel Imágenes -->
                  <div id="panel-images" class="source-panel hidden">
                    <div class="border-2 border-dashed border-slate-200 rounded-xl p-6 hover:border-emerald-400 transition-colors cursor-pointer" id="images-dropzone">
                      <input type="file" id="input-images" accept="image/*" multiple class="hidden">
                      <div id="images-placeholder" class="text-center">
                        <i class="iconoir-media-image-list text-4xl text-slate-300 mb-2"></i>
                        <p class="text-sm text-slate-500">Arrastra imágenes o haz clic para seleccionar</p>
                        <p class="text-xs text-slate-400 mt-1">Capturas de pantalla, diagramas, fotos (múltiples)</p>
                      </div>
                      <div id="images-grid" class="images-grid hidden"></div>
                    </div>
                  </div>
                  
                  <!-- Panel PDF -->
                  <div id="panel-pdf" class="source-panel hidden">
                    <div class="border-2 border-dashed border-slate-200 rounded-xl p-6 text-center hover:border-emerald-400 transition-colors cursor-pointer" id="pdf-dropzone">
                      <input type="file" id="input-pdf" accept=".pdf" class="hidden">
                      <div id="pdf-placeholder">
                        <i class="iconoir-page text-4xl text-slate-300 mb-2"></i>
                        <p class="text-sm text-slate-500">Arrastra un PDF o haz clic para seleccionar</p>
                        <p class="text-xs text-slate-400 mt-1">Documentos, manuales, guías (máx. 20MB)</p>
                      </div>
                      <div id="pdf-preview" class="hidden">
                        <div class="file-preview">
                          <div class="file-icon pdf">
                            <i class="iconoir-page"></i>
                          </div>
                          <div class="flex-1 text-left">
                            <div class="font-medium text-slate-700" id="pdf-name">documento.pdf</div>
                            <div class="text-sm text-slate-400" id="pdf-size">1.2 MB</div>
                          </div>
                          <button type="button" id="remove-pdf" class="text-slate-400 hover:text-red-500 p-2">
                            <i class="iconoir-xmark"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Botón añadir PDF (siempre visible) -->
                <button type="button" id="add-pdf-btn" class="mt-3 text-sm text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                  <i class="iconoir-plus"></i>
                  Añadir PDF
                </button>
              </div>
              
              <!-- Submit -->
              <div class="pt-2">
                <button type="submit" id="generate-btn"
                        class="w-full py-4 px-6 rounded-xl font-semibold text-white transition-all
                               bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600
                               shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40
                               disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none
                               flex items-center justify-center gap-2">
                  <i class="iconoir-clipboard-check text-xl"></i>
                  Generar SOP
                </button>
              </div>
            </form>
          </section>

          <!-- Botón nuevo proceso (visible cuando hay resultado del historial) -->
          <div id="new-process-btn-container" class="hidden">
            <button id="new-process-btn" class="w-full py-3 px-4 rounded-xl border-2 border-dashed border-emerald-300 text-emerald-600 hover:bg-emerald-50 hover:border-emerald-400 transition-all flex items-center justify-center gap-2 font-medium">
              <i class="iconoir-plus"></i>
              Generar nuevo proceso
            </button>
          </div>

          <!-- Results Section (inicialmente oculto) -->
          <section id="results-section" class="hidden glass-strong rounded-2xl border border-slate-200/50 overflow-hidden">
            <!-- Tabs -->
            <div class="px-6 pt-6">
              <div class="result-tabs">
                <button class="result-tab active" data-result="markdown">
                  <i class="iconoir-file-text"></i>
                  Documento
                </button>
                <button class="result-tab" data-result="mermaid">
                  <i class="iconoir-git-fork"></i>
                  Diagrama
                </button>
                <button class="result-tab" data-result="downloads">
                  <i class="iconoir-download"></i>
                  Descargas
                </button>
              </div>
            </div>
            
            <!-- Panel: Markdown -->
            <div id="result-markdown" class="result-panel active p-6">
              <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-slate-700">
                  <i class="iconoir-file-text text-emerald-500 mr-1"></i>
                  Procedimiento estructurado
                </h3>
                <button id="copy-markdown-btn" class="text-sm text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                  <i class="iconoir-copy"></i>
                  Copiar
                </button>
              </div>
              <div id="markdown-content" class="prose prose-slate max-w-none bg-white rounded-xl p-6 border border-slate-100">
                <!-- Contenido renderizado aquí -->
              </div>
            </div>
            
            <!-- Panel: Mermaid -->
            <div id="result-mermaid" class="result-panel p-6">
              <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-slate-700">
                  <i class="iconoir-git-fork text-emerald-500 mr-1"></i>
                  Diagrama de flujo
                </h3>
                <button id="copy-mermaid-btn" class="text-sm text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                  <i class="iconoir-copy"></i>
                  Copiar código
                </button>
              </div>
              <div id="mermaid-container" class="mermaid-container">
                <!-- Diagrama renderizado aquí -->
              </div>
            </div>
            
            <!-- Panel: Descargas -->
            <div id="result-downloads" class="result-panel p-6">
              <h3 class="font-semibold text-slate-700 mb-4">
                <i class="iconoir-download text-emerald-500 mr-1"></i>
                Descargar documento
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- PDF -->
                <a id="download-pdf" href="#" class="flex items-center gap-4 p-4 rounded-xl border border-slate-200 hover:border-emerald-400 hover:bg-emerald-50/50 transition-all">
                  <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center text-white text-2xl">
                    <i class="iconoir-page"></i>
                  </div>
                  <div>
                    <div class="font-semibold text-slate-700">PDF</div>
                    <div class="text-sm text-slate-400">Documento portable</div>
                  </div>
                  <i class="iconoir-download ml-auto text-slate-400"></i>
                </a>
                
                <!-- DOCX -->
                <a id="download-docx" href="#" class="flex items-center gap-4 p-4 rounded-xl border border-slate-200 hover:border-emerald-400 hover:bg-emerald-50/50 transition-all">
                  <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white text-2xl">
                    <i class="iconoir-page-star"></i>
                  </div>
                  <div>
                    <div class="font-semibold text-slate-700">Word (DOCX)</div>
                    <div class="text-sm text-slate-400">Editable en Microsoft Word</div>
                  </div>
                  <i class="iconoir-download ml-auto text-slate-400"></i>
                </a>
              </div>
            </div>
          </section>

        </div>
      </div>
    </main>
  </div>
  
  <!-- Processing Overlay -->
  <div id="processing-overlay" class="processing-overlay hidden">
    <div class="processing-card">
      <div class="relative w-20 h-20 mx-auto mb-4">
        <div class="absolute inset-0 rounded-full bg-emerald-100 pulse-ring"></div>
        <div class="absolute inset-0 flex items-center justify-center">
          <i class="iconoir-clipboard-check text-4xl text-emerald-500 animate-pulse"></i>
        </div>
      </div>
      <h3 class="text-lg font-semibold text-slate-700 mb-2">Generando SOP</h3>
      <p id="processing-status" class="text-slate-500 text-sm">Procesando contenido...</p>
      <div class="mt-4 w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
        <div id="processing-bar" class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all duration-500" style="width: 0%"></div>
      </div>
    </div>
  </div>
  
  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
  <script>
    // Inicializar Mermaid con configuración optimizada para diagramas ordenados
    mermaid.initialize({ 
      startOnLoad: false, 
      theme: 'neutral',
      flowchart: {
        htmlLabels: true,
        curve: 'basis',
        rankSpacing: 50,
        nodeSpacing: 30,
        padding: 15,
        useMaxWidth: true,
        defaultRenderer: 'dagre-wrapper'
      },
      themeVariables: {
        primaryColor: '#e0f2f1',
        primaryBorderColor: '#26a69a',
        primaryTextColor: '#37474f',
        lineColor: '#78909c',
        secondaryColor: '#fff8e1',
        tertiaryColor: '#f3e5f5'
      }
    });
  </script>
  <script src="/assets/js/gesture-sop.js"></script>
  <script>
    window.CSRF_TOKEN = '<?php echo $csrfToken; ?>';
  </script>
</body>
</html>
