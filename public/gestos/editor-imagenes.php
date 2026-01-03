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

$accessRepo = new UserFeatureAccessRepo();
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'image-editor')) {
    header('Location: /gestos/?error=no_access');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = 'gestures';
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Studio · Editor de Imágenes</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/iconoir@7.9.0/css/iconoir.min.css">
  <style>
    /* === STUDIO LAYOUT === */
    .studio-body {
      margin: 0;
      padding: 0;
      background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
      min-height: 100vh;
      overflow: hidden;
    }
    
    .studio-container {
      display: flex;
      height: 100vh;
      position: relative;
    }
    
    /* === FILMSTRIP (Left History) === */
    .filmstrip {
      width: 200px;
      background: rgba(15, 23, 42, 0.8);
      backdrop-filter: blur(20px);
      border-right: 1px solid rgba(255,255,255,0.1);
      display: flex;
      flex-direction: column;
      z-index: 10;
    }
    
    @media (max-width: 1023px) {
      .filmstrip { display: none; }
    }
    
    .filmstrip-header {
      padding: 16px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    .filmstrip-title {
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: rgba(255,255,255,0.5);
    }
    
    .filmstrip-count {
      background: rgba(245, 158, 11, 0.2);
      color: #f59e0b;
      font-size: 10px;
      font-weight: 600;
      padding: 2px 8px;
      border-radius: 10px;
    }
    
    .filmstrip-scroll {
      flex: 1;
      overflow-y: auto;
      padding: 8px;
    }
    
    .filmstrip-item {
      width: 100%;
      aspect-ratio: 1;
      border-radius: 8px;
      overflow: hidden;
      margin-bottom: 8px;
      cursor: pointer;
      border: 2px solid transparent;
      transition: all 0.2s;
      position: relative;
    }
    
    .filmstrip-item:hover {
      border-color: rgba(255,255,255,0.3);
      transform: scale(1.02);
    }
    
    .filmstrip-item.active {
      border-color: #f59e0b;
      box-shadow: 0 0 20px rgba(245, 158, 11, 0.3);
    }
    
    .filmstrip-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .filmstrip-item-delete {
      position: absolute;
      top: 4px;
      right: 4px;
      width: 24px;
      height: 24px;
      background: rgba(0,0,0,0.7);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.2s;
      border: none;
      cursor: pointer;
      color: white;
      font-size: 12px;
    }
    
    .filmstrip-item:hover .filmstrip-item-delete { opacity: 1; }
    
    .filmstrip-empty {
      text-align: center;
      padding: 40px 16px;
      color: rgba(255,255,255,0.3);
    }
    
    .filmstrip-empty i {
      font-size: 32px;
      display: block;
      margin-bottom: 8px;
    }
    
    .filmstrip-empty span {
      font-size: 12px;
    }
    
    /* === CANVAS AREA (Center) === */
    .canvas-area {
      flex: 1;
      display: flex;
      flex-direction: column;
      position: relative;
      min-width: 0;
    }
    
    /* === PROMPT BAR (Top) === */
    .prompt-bar {
      padding: 16px 24px;
      background: rgba(0,0,0,0.3);
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .prompt-bar-inner {
      max-width: 800px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      gap: 12px;
      background: rgba(255,255,255,0.95);
      border-radius: 16px;
      padding: 6px 6px 6px 16px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    }
    
    .prompt-providers {
      display: flex;
      gap: 4px;
      padding-right: 12px;
      border-right: 1px solid #e2e8f0;
    }
    
    .provider-chip {
      padding: 6px 10px;
      font-size: 11px;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      color: #64748b;
      background: transparent;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    .provider-chip:hover { background: #f1f5f9; }
    
    .provider-chip.active {
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      color: white;
    }
    
    .provider-chip.active .provider-dot { background: white !important; }
    
    .provider-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
    }
    
    .prompt-input {
      flex: 1;
      border: none;
      background: transparent;
      font-size: 15px;
      color: #1e293b;
      outline: none;
      min-width: 200px;
    }
    
    .prompt-input::placeholder { color: #94a3b8; }
    
    .prompt-generate-btn {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      border: none;
      background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
      color: white;
      font-size: 18px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }
    
    .prompt-generate-btn:hover {
      transform: scale(1.05);
      box-shadow: 0 4px 20px rgba(245, 158, 11, 0.4);
    }
    
    .prompt-generate-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
    }
    
    /* === CANVAS CONTAINER === */
    .canvas-container {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      position: relative;
    }
    
    .canvas-empty {
      text-align: center;
      color: rgba(255,255,255,0.5);
    }
    
    .canvas-empty-icon {
      font-size: 64px;
      margin-bottom: 16px;
    }
    
    .canvas-empty h3 {
      font-size: 20px;
      font-weight: 600;
      color: rgba(255,255,255,0.7);
      margin: 0 0 8px 0;
    }
    
    .canvas-empty p {
      font-size: 14px;
      color: rgba(255,255,255,0.4);
      margin: 0;
    }
    
    .canvas-loading {
      text-align: center;
      color: rgba(255,255,255,0.7);
    }
    
    .loading-spinner {
      width: 80px;
      height: 80px;
      margin: 0 auto 16px;
      position: relative;
      border: 3px solid rgba(245, 158, 11, 0.2);
      border-radius: 50%;
    }
    
    .loading-spinner::after {
      content: '';
      position: absolute;
      inset: -3px;
      border: 3px solid transparent;
      border-top-color: #f59e0b;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    
    .loading-banana {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
    }
    
    @keyframes spin { to { transform: rotate(360deg); } }
    
    .canvas-loading p {
      font-size: 14px;
      margin: 0;
    }
    
    /* === CANVAS IMAGE === */
    .canvas-image-wrapper {
      position: relative;
      max-width: 100%;
      max-height: 100%;
    }
    
    .canvas-image {
      max-width: 100%;
      max-height: calc(100vh - 200px);
      border-radius: 12px;
      box-shadow: 0 30px 60px rgba(0,0,0,0.5);
      cursor: zoom-in;
      transition: transform 0.3s;
    }
    
    .canvas-image:hover { transform: scale(1.01); }
    
    /* === CANVAS ACTIONS (Floating) === */
    .canvas-actions {
      position: absolute;
      bottom: -24px;
      left: 50%;
      transform: translateX(-50%) translateY(20px);
      display: flex;
      gap: 8px;
      background: rgba(0,0,0,0.85);
      backdrop-filter: blur(10px);
      padding: 8px 12px;
      border-radius: 16px;
      opacity: 0;
      transition: all 0.3s;
    }
    
    .canvas-image-wrapper:hover .canvas-actions {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }
    
    .canvas-action-btn {
      padding: 10px 14px;
      font-size: 12px;
      font-weight: 500;
      border-radius: 10px;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
      color: white;
      background: rgba(255,255,255,0.1);
    }
    
    .canvas-action-btn:hover { background: rgba(255,255,255,0.2); }
    
    .canvas-action-btn.primary {
      background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
    }
    
    .canvas-action-btn.primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
    }
    
    /* === EDIT OVERLAY === */
    .edit-overlay {
      position: absolute;
      bottom: 100px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 20;
      width: 90%;
      max-width: 600px;
    }
    
    .edit-overlay-content {
      background: rgba(139, 92, 246, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 16px;
      padding: 16px;
      box-shadow: 0 20px 40px rgba(139, 92, 246, 0.3);
    }
    
    .edit-overlay-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 12px;
    }
    
    .edit-overlay-title {
      color: white;
      font-size: 13px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .edit-cancel-btn {
      width: 28px;
      height: 28px;
      border-radius: 8px;
      border: none;
      background: rgba(255,255,255,0.2);
      color: white;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .edit-cancel-btn:hover { background: rgba(255,255,255,0.3); }
    
    .edit-prompt-input {
      width: 100%;
      padding: 12px 16px;
      border: none;
      border-radius: 10px;
      font-size: 14px;
      background: white;
      margin-bottom: 12px;
      outline: none;
    }
    
    .edit-apply-btn {
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: 10px;
      background: white;
      color: #7c3aed;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.2s;
    }
    
    .edit-apply-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(255,255,255,0.3);
    }
    
    /* === OPTIONS PANEL (Right) === */
    .options-panel {
      width: 220px;
      background: rgba(15, 23, 42, 0.8);
      backdrop-filter: blur(20px);
      border-left: 1px solid rgba(255,255,255,0.1);
      display: flex;
      flex-direction: column;
      transition: width 0.3s;
    }
    
    @media (max-width: 1023px) {
      .options-panel { display: none; }
    }
    
    .options-panel.collapsed {
      width: 48px;
    }
    
    .options-panel.collapsed .options-content { display: none; }
    .options-panel.collapsed .options-title { display: none; }
    
    .options-header {
      padding: 16px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    .options-title {
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: rgba(255,255,255,0.5);
    }
    
    .options-toggle-btn {
      width: 24px;
      height: 24px;
      border-radius: 6px;
      border: none;
      background: rgba(255,255,255,0.1);
      color: rgba(255,255,255,0.5);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
    }
    
    .options-toggle-btn:hover {
      background: rgba(255,255,255,0.2);
      color: white;
    }
    
    .options-content {
      flex: 1;
      overflow-y: auto;
      padding: 16px;
    }
    
    .option-group {
      margin-bottom: 20px;
    }
    
    .option-label {
      font-size: 10px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: rgba(255,255,255,0.4);
      margin-bottom: 8px;
      display: block;
    }
    
    .option-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
    }
    
    .option-chip {
      padding: 6px 10px;
      font-size: 11px;
      font-weight: 500;
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 6px;
      background: rgba(255,255,255,0.05);
      color: rgba(255,255,255,0.6);
      cursor: pointer;
      transition: all 0.15s;
    }
    
    .option-chip:hover {
      border-color: rgba(245, 158, 11, 0.5);
      color: white;
    }
    
    .option-chip.active {
      background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
      color: white;
      border-color: transparent;
    }
    
    /* === LIGHTBOX === */
    .lightbox {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.95);
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px;
    }
    
    .lightbox-close {
      position: absolute;
      top: 20px;
      right: 20px;
      width: 44px;
      height: 44px;
      background: rgba(255,255,255,0.1);
      border: none;
      border-radius: 12px;
      color: white;
      font-size: 20px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .lightbox-close:hover { background: rgba(255,255,255,0.2); }
    
    .lightbox-image {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
      border-radius: 8px;
    }
    
    /* === BACK BUTTON === */
    .back-btn {
      position: fixed;
      top: 16px;
      left: 96px;
      z-index: 50;
      padding: 8px 14px;
      font-size: 12px;
      font-weight: 500;
      color: rgba(255,255,255,0.6);
      background: rgba(0,0,0,0.4);
      backdrop-filter: blur(10px);
      border: none;
      border-radius: 8px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      transition: all 0.2s;
    }
    
    .back-btn:hover {
      color: white;
      background: rgba(0,0,0,0.6);
    }
    
    @media (max-width: 1023px) {
      .back-btn { left: 16px; top: 16px; }
    }
    
    /* === UTILITIES === */
    .hidden { display: none !important; }
  </style>
</head>
<body class="studio-body">
  <div class="studio-container">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>
    
    <!-- Back Button -->
    <a href="/gestos/" class="back-btn">
      <i class="iconoir-nav-arrow-left"></i>
      Gestos
    </a>
    
    <!-- ========== FILMSTRIP (Left History) ========== -->
    <aside class="filmstrip" id="filmstrip">
      <div class="filmstrip-header">
        <span class="filmstrip-title">Historial</span>
        <span class="filmstrip-count" id="history-count">0</span>
      </div>
      <div class="filmstrip-scroll" id="history-list">
        <div class="filmstrip-empty">
          <i class="iconoir-media-image"></i>
          <span>Sin imágenes</span>
        </div>
      </div>
    </aside>

    <!-- ========== CANVAS AREA (Center) ========== -->
    <main class="canvas-area">
      <!-- Prompt Bar -->
      <div class="prompt-bar">
        <div class="prompt-bar-inner">
          <div class="prompt-providers">
            <button type="button" class="provider-chip active" data-provider="qwen">
              <span class="provider-dot" style="background: #a855f7;"></span>
              Qwen
            </button>
            <button type="button" class="provider-chip" data-provider="nanobanana">
              <span class="provider-dot" style="background: #3b82f6;"></span>
              Gemini
            </button>
            <button type="button" class="provider-chip" data-provider="flux">
              <span class="provider-dot" style="background: #10b981;"></span>
              FLUX
            </button>
          </div>
          <input type="text" id="prompt-input" class="prompt-input" placeholder="Describe la imagen que quieres crear..." autocomplete="off" />
          <button type="button" id="generate-btn" class="prompt-generate-btn" title="Generar">
            <i class="iconoir-sparks"></i>
          </button>
        </div>
      </div>

      <!-- Canvas Container -->
      <div class="canvas-container">
        <!-- Empty State -->
        <div class="canvas-empty" id="canvas-empty">
          <div class="canvas-empty-icon">🍌</div>
          <h3>Crea algo increíble</h3>
          <p>Escribe una descripción arriba y pulsa ✨</p>
        </div>

        <!-- Loading State -->
        <div class="canvas-loading hidden" id="canvas-loading">
          <div class="loading-spinner">
            <div class="loading-banana">🍌</div>
          </div>
          <p>Generando tu imagen...</p>
        </div>

        <!-- Image Result -->
        <div class="canvas-image-wrapper hidden" id="canvas-image-wrapper">
          <img id="canvas-image" src="" alt="Imagen generada" class="canvas-image" />
          
          <!-- Floating Actions -->
          <div class="canvas-actions" id="canvas-actions">
            <button type="button" class="canvas-action-btn primary" id="action-edit" title="Editar esta imagen">
              <i class="iconoir-edit"></i>
              <span>Editar</span>
            </button>
            <button type="button" class="canvas-action-btn" id="action-variation" title="Crear variación">
              <i class="iconoir-copy"></i>
              <span>Variación</span>
            </button>
            <button type="button" class="canvas-action-btn" id="action-regenerate" title="Regenerar">
              <i class="iconoir-refresh"></i>
            </button>
            <button type="button" class="canvas-action-btn" id="action-download" title="Descargar">
              <i class="iconoir-download"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Edit Mode Overlay -->
      <div class="edit-overlay hidden" id="edit-overlay">
        <div class="edit-overlay-content">
          <div class="edit-overlay-header">
            <span class="edit-overlay-title">
              <i class="iconoir-edit"></i>
              Modo Edición
            </span>
            <button type="button" id="edit-cancel" class="edit-cancel-btn">
              <i class="iconoir-xmark"></i>
            </button>
          </div>
          <input type="text" id="edit-prompt" class="edit-prompt-input" placeholder="Describe los cambios: 'Añade gafas de sol', 'Cambia el fondo a playa'..." />
          <button type="button" id="edit-apply" class="edit-apply-btn">
            <i class="iconoir-check"></i>
            Aplicar cambios
          </button>
        </div>
      </div>
    </main>

    <!-- ========== OPTIONS PANEL (Right) ========== -->
    <aside class="options-panel" id="options-panel">
      <div class="options-header">
        <span class="options-title">Opciones</span>
        <button type="button" id="options-toggle" class="options-toggle-btn">
          <i class="iconoir-nav-arrow-right"></i>
        </button>
      </div>
      
      <div class="options-content">
        <div class="option-group">
          <label class="option-label">Formato</label>
          <div class="option-chips">
            <button type="button" class="option-chip active" data-option="format" data-value="1:1">1:1</button>
            <button type="button" class="option-chip" data-option="format" data-value="3:4">3:4</button>
            <button type="button" class="option-chip" data-option="format" data-value="4:3">4:3</button>
            <button type="button" class="option-chip" data-option="format" data-value="16:9">16:9</button>
            <button type="button" class="option-chip" data-option="format" data-value="9:16">9:16</button>
          </div>
        </div>

        <div class="option-group">
          <label class="option-label">Estilo</label>
          <div class="option-chips">
            <button type="button" class="option-chip active" data-option="style" data-value="">Auto</button>
            <button type="button" class="option-chip" data-option="style" data-value="photographic">Foto</button>
            <button type="button" class="option-chip" data-option="style" data-value="corporate">Corp</button>
            <button type="button" class="option-chip" data-option="style" data-value="headshot-pro">Retrato</button>
            <button type="button" class="option-chip" data-option="style" data-value="3d-render">3D</button>
            <button type="button" class="option-chip" data-option="style" data-value="minimalist">Minimal</button>
          </div>
        </div>

        <div class="option-group">
          <label class="option-label">Color</label>
          <div class="option-chips">
            <button type="button" class="option-chip active" data-option="color" data-value="">Auto</button>
            <button type="button" class="option-chip" data-option="color" data-value="warm">Cálido</button>
            <button type="button" class="option-chip" data-option="color" data-value="cool">Frío</button>
            <button type="button" class="option-chip" data-option="color" data-value="bw">B/N</button>
            <button type="button" class="option-chip" data-option="color" data-value="vibrant">Vibrante</button>
          </div>
        </div>

        <div class="option-group">
          <label class="option-label">Luz</label>
          <div class="option-chips">
            <button type="button" class="option-chip active" data-option="lighting" data-value="">Auto</button>
            <button type="button" class="option-chip" data-option="lighting" data-value="natural">Natural</button>
            <button type="button" class="option-chip" data-option="lighting" data-value="studio">Estudio</button>
            <button type="button" class="option-chip" data-option="lighting" data-value="dramatic">Drama</button>
            <button type="button" class="option-chip" data-option="lighting" data-value="golden">Dorada</button>
          </div>
        </div>
      </div>
    </aside>
  </div>

  <!-- Lightbox -->
  <div class="lightbox hidden" id="lightbox">
    <button type="button" class="lightbox-close" id="lightbox-close">
      <i class="iconoir-xmark"></i>
    </button>
    <img id="lightbox-image" src="" alt="" class="lightbox-image" />
  </div>

  <!-- Hidden inputs -->
  <input type="hidden" id="current-provider" value="qwen" />
  <input type="hidden" id="current-format" value="1:1" />
  <input type="hidden" id="current-style" value="" />
  <input type="hidden" id="current-color" value="" />
  <input type="hidden" id="current-lighting" value="" />

  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>

  <script>window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken); ?>';</script>
  <script src="/assets/js/gesture-image-editor.js"></script>
</body>
</html>
