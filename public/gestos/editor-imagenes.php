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
  <!-- Estilos Studio Mode (Out of the box) -->
  <style>
    :root {
      --studio-bg: #0f172a;
      --studio-panel: rgba(15, 23, 42, 0.8);
      --studio-accent: #f59e0b;
    }

    .studio-layout {
      height: 100vh;
      background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
      color: white;
      overflow: hidden;
      display: flex;
    }

    /* Barra de Herramientas Flotante (Prompt) */
    .studio-prompt-container {
      position: fixed;
      top: 24px;
      left: 50%;
      transform: translateX(-50%);
      width: 90%;
      max-width: 800px;
      z-index: 50;
    }

    .studio-prompt-bar {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 24px;
      padding: 8px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.5);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .studio-input {
      flex: 1;
      background: transparent;
      border: none;
      color: white;
      padding: 12px 16px;
      font-size: 16px;
      outline: none;
    }

    .studio-input::placeholder {
      color: rgba(255,255,255,0.4);
    }

    /* Panel de Opciones Flotante */
    .studio-options-trigger {
      width: 44px;
      height: 44px;
      border-radius: 16px;
      background: rgba(255,255,255,0.05);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }

    .studio-options-trigger:hover {
      background: rgba(255,255,255,0.15);
    }

    .studio-options-panel {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      margin-top: 12px;
      background: rgba(15, 23, 42, 0.95);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 24px;
      padding: 24px;
      box-shadow: 0 25px 60px rgba(0,0,0,0.6);
      display: none;
      z-index: 49;
    }

    .studio-options-panel.active {
      display: block;
      animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Canvas Central */
    .studio-canvas {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 100px 40px 160px;
      position: relative;
    }

    .canvas-image-wrapper {
      max-width: 100%;
      max-height: 100%;
      position: relative;
      border-radius: 24px;
      overflow: hidden;
      box-shadow: 0 0 100px rgba(245, 158, 11, 0.15);
      transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .canvas-image-wrapper:hover {
      transform: scale(1.02);
      box-shadow: 0 0 120px rgba(245, 158, 11, 0.25);
    }

    .canvas-image-wrapper img {
      max-width: 100%;
      max-height: 80vh;
      object-fit: contain;
      display: block;
    }

    /* Controles de Imagen */
    .image-quick-actions {
      position: absolute;
      bottom: 24px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 12px;
      background: rgba(0,0,0,0.6);
      backdrop-filter: blur(10px);
      padding: 8px;
      border-radius: 20px;
      opacity: 0;
      transition: opacity 0.3s;
    }

    .canvas-image-wrapper:hover .image-quick-actions {
      opacity: 1;
    }

    .quick-action-btn {
      width: 48px;
      height: 48px;
      border-radius: 14px;
      background: rgba(255,255,255,0.1);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }

    .quick-action-btn:hover {
      background: var(--studio-accent);
      color: white;
      transform: translateY(-2px);
    }

    /* Filmstrip (Historial) Inferior */
    .studio-filmstrip {
      position: fixed;
      bottom: 24px;
      left: 50%;
      transform: translateX(-50%);
      width: 90%;
      max-width: 1000px;
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.05);
      border-radius: 28px;
      padding: 12px;
      z-index: 40;
    }

    .filmstrip-items {
      display: flex;
      gap: 12px;
      overflow-x: auto;
      padding: 4px;
      scrollbar-width: none;
    }

    .filmstrip-items::-webkit-scrollbar {
      display: none;
    }

    .filmstrip-item {
      width: 80px;
      height: 80px;
      border-radius: 16px;
      overflow: hidden;
      flex-shrink: 0;
      cursor: pointer;
      border: 2px solid transparent;
      transition: all 0.3s;
      position: relative;
    }

    .filmstrip-item:hover {
      transform: translateY(-8px) scale(1.1);
      z-index: 10;
    }

    .filmstrip-item.active {
      border-color: var(--studio-accent);
      box-shadow: 0 0 20px rgba(245, 158, 11, 0.4);
    }

    .filmstrip-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* Overlay de Edición */
    .edit-mode-indicator {
      position: fixed;
      top: 100px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(245, 158, 11, 0.2);
      border: 1px solid var(--studio-accent);
      padding: 6px 16px;
      border-radius: 100px;
      font-size: 12px;
      font-weight: 600;
      color: var(--studio-accent);
      display: none;
      z-index: 45;
    }

    .edit-mode-indicator.active {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Ajustes para Left Tabs */
    .studio-layout #left-sidebar {
      background: var(--studio-panel);
      border-right: 1px solid rgba(255,255,255,0.05);
    }
  </style>

<body class="studio-layout">
  <?php include __DIR__ . '/../includes/left-tabs.php'; ?>

  <main class="flex-1 flex flex-col relative overflow-hidden">
    
    <!-- MODO EDICIÓN INDICATOR -->
    <div id="edit-mode-badge" class="edit-mode-indicator">
      <span class="relative flex h-2 w-2">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
        <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
      </span>
      Modo Edición Activo: Modificando imagen actual
    </div>

    <!-- PROMPT BAR & OPTIONS -->
    <div class="studio-prompt-container">
      <form id="studio-form" class="studio-prompt-bar">
        <button type="button" id="toggle-studio-options" class="studio-options-trigger" title="Opciones">
          <i class="iconoir-settings"></i>
        </button>
        
        <input type="text" id="studio-prompt-input" class="studio-input" placeholder="Describe lo que quieres crear o editar..." autocomplete="off">
        
        <div class="flex items-center gap-2 pr-2">
          <!-- Selector de motor compacto -->
          <div class="hidden sm:flex bg-white/5 rounded-xl p-1 border border-white/10" id="studio-provider-selector">
            <button type="button" data-provider="qwen" class="p-2 rounded-lg text-xs font-bold transition-all active" title="Qwen">Q</button>
            <button type="button" data-provider="nanobanana" class="p-2 rounded-lg text-xs font-bold transition-all" title="Nanobanana">NB</button>
            <button type="button" data-provider="flux" class="p-2 rounded-lg text-xs font-bold transition-all" title="FLUX">FX</button>
          </div>

          <button type="submit" id="studio-generate-btn" class="bg-amber-500 hover:bg-amber-600 text-white w-12 h-12 rounded-xl flex items-center justify-center transition-all shadow-lg shadow-amber-500/20">
            <i class="iconoir-sparks text-xl"></i>
          </button>
        </div>

        <input type="hidden" id="current-provider" value="qwen">
        <input type="hidden" id="current-mode" value="generate">
        
        <!-- PANEL DE OPCIONES DESPLEGABLE -->
        <div id="studio-options-panel" class="studio-options-panel">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Formatos -->
            <div>
              <label class="text-[10px] font-bold text-white/40 uppercase tracking-widest mb-3 block">Formato</label>
              <div class="flex flex-wrap gap-2">
                <?php foreach(['1:1', '3:4', '4:3', '16:9', '9:16'] as $f): ?>
                <label class="cursor-pointer">
                  <input type="radio" name="format" value="<?php echo $f; ?>" class="hidden peer" <?php echo $f=='1:1'?'checked':''; ?>>
                  <span class="px-3 py-1.5 rounded-lg border border-white/10 bg-white/5 text-xs peer-checked:bg-amber-500 peer-checked:text-white transition-all block"><?php echo $f; ?></span>
                </label>
                <?php endforeach; ?>
              </div>
            </div>
            
            <!-- Estilos -->
            <div>
              <label class="text-[10px] font-bold text-white/40 uppercase tracking-widest mb-3 block">Estilo</label>
              <select name="style" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-xs text-white outline-none">
                <option value="">Automático</option>
                <option value="photographic">Fotográfico</option>
                <option value="headshot-pro">Retrato Pro</option>
                <option value="corporate">Silicon Valley</option>
                <option value="luxury-product">Producto Lujo</option>
                <option value="3d-render">3D Render</option>
              </select>
            </div>

            <!-- Iluminación -->
            <div>
              <label class="text-[10px] font-bold text-white/40 uppercase tracking-widest mb-3 block">Luz</label>
              <select name="lighting" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-xs text-white outline-none">
                <option value="">Automática</option>
                <option value="natural">Natural</option>
                <option value="studio">Estudio</option>
                <option value="dramatic">Dramática</option>
                <option value="golden">Golden Hour</option>
              </select>
            </div>

            <!-- Composición -->
            <div>
              <label class="text-[10px] font-bold text-white/40 uppercase tracking-widest mb-3 block">Composición</label>
              <select name="composition" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-xs text-white outline-none">
                <option value="">Automática</option>
                <option value="bokeh">Bokeh Suave</option>
                <option value="closeup">Primer Plano</option>
                <option value="wide">Plano General</option>
              </select>
            </div>
          </div>
          
          <div class="mt-6 pt-6 border-t border-white/5 flex items-center justify-between">
             <div class="flex items-center gap-4">
                <div class="text-[10px] text-white/30">Imágenes cargadas para edición:</div>
                <div id="studio-upload-preview" class="flex gap-2">
                   <!-- Aquí irán miniaturas de las imágenes subidas -->
                   <div id="source-mini" class="w-8 h-8 rounded border border-dashed border-white/20 flex items-center justify-center text-[10px] text-white/20">S</div>
                   <div id="target-mini" class="w-8 h-8 rounded border border-dashed border-white/20 flex items-center justify-center text-[10px] text-white/20">T</div>
                </div>
             </div>
             <button type="button" id="close-options" class="text-xs text-white/40 hover:text-white">Cerrar Opciones</button>
          </div>
        </div>
      </form>
    </div>

    <!-- CANVAS CENTRAL -->
    <div class="studio-canvas">
      <!-- Empty State -->
      <div id="studio-empty" class="text-center opacity-30">
        <div class="text-6xl mb-6">🎨</div>
        <h2 class="text-2xl font-bold tracking-tight">Studio Mode</h2>
        <p class="text-sm">Describe tu visión en la barra superior</p>
      </div>

      <!-- Loading State -->
      <div id="studio-loading" class="hidden flex-col items-center gap-6">
        <div class="relative w-24 h-24">
          <div class="absolute inset-0 border-4 border-amber-500/20 rounded-full"></div>
          <div class="absolute inset-0 border-4 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
          <div class="absolute inset-0 flex items-center justify-center text-3xl animate-pulse">🍌</div>
        </div>
        <p class="text-amber-500 font-medium tracking-widest text-xs uppercase">Procesando imagen...</p>
      </div>

      <!-- Result State -->
      <div id="studio-result" class="hidden canvas-image-wrapper">
        <img id="main-image" src="" alt="Resultado">
        
        <!-- Acciones Rápidas -->
        <div class="image-quick-actions">
          <button type="button" id="btn-edit-this" class="quick-action-btn" title="Editar esta imagen">
            <i class="iconoir-edit"></i>
          </button>
          <button type="button" id="btn-download-this" class="quick-action-btn" title="Descargar">
            <i class="iconoir-download"></i>
          </button>
          <button type="button" id="btn-regenerate-this" class="quick-action-btn" title="Variación">
            <i class="iconoir-refresh"></i>
          </button>
          <button type="button" id="btn-expand-this" class="quick-action-btn" title="Ver Pantalla Completa">
            <i class="iconoir-expand"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- FILMSTRIP (HISTORIAL) -->
    <div class="studio-filmstrip">
      <div class="flex items-center justify-between px-2 mb-2">
        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-white/20">Timeline de Creaciones</span>
        <button id="clear-studio-session" class="text-[10px] text-white/20 hover:text-red-400 transition-colors">Limpiar</button>
      </div>
      <div id="studio-history" class="filmstrip-items">
        <!-- Los items se cargarán vía JS -->
      </div>
    </div>

  </main>

  <!-- LIGHTBOX -->
  <div id="studio-lightbox" class="fixed inset-0 bg-black/95 z-[100] hidden items-center justify-center p-8">
    <button id="close-lightbox" class="absolute top-8 right-8 text-white/50 hover:text-white transition-colors">
      <i class="iconoir-xmark text-4xl"></i>
    </button>
    <img id="lightbox-img" src="" class="max-w-full max-h-full object-contain shadow-2xl">
  </div>

  <!-- INPUTS OCULTOS PARA UPLOADS -->
  <input type="file" id="source-upload" class="hidden" accept="image/*">
  <input type="file" id="target-upload" class="hidden" accept="image/*">

  <script>window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken); ?>';</script>
  <script src="/assets/js/gesture-image-editor.js"></script>
</body>
</html>
