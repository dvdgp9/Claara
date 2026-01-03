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
    /* === STUDIO MODE === */
    .studio-container {
      background: linear-gradient(135deg, #0f0f0f 0%, #1a1a2e 50%, #0f0f0f 100%);
      min-height: 100vh;
      position: relative;
      overflow: hidden;
    }
    
    /* Subtle grid pattern */
    .studio-container::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: 
        linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
      background-size: 50px 50px;
      pointer-events: none;
    }
    
    /* === PROMPT BAR (Spotlight style) === */
    .prompt-bar {
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      width: 90%;
      max-width: 700px;
      z-index: 100;
      background: rgba(255,255,255,0.95);
      backdrop-filter: blur(20px);
      border-radius: 20px;
      box-shadow: 
        0 25px 50px -12px rgba(0,0,0,0.4),
        0 0 0 1px rgba(255,255,255,0.1),
        inset 0 1px 0 rgba(255,255,255,0.2);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .prompt-bar:focus-within {
      transform: translateX(-50%) scale(1.02);
      box-shadow: 
        0 30px 60px -15px rgba(0,0,0,0.5),
        0 0 0 2px rgba(245, 158, 11, 0.5),
        inset 0 1px 0 rgba(255,255,255,0.2);
    }
    
    .prompt-input {
      width: 100%;
      padding: 18px 60px 18px 24px;
      font-size: 17px;
      border: none;
      background: transparent;
      outline: none;
      color: #1a1a2e;
    }
    
    .prompt-input::placeholder {
      color: #94a3b8;
    }
    
    .prompt-actions {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      display: flex;
      gap: 4px;
    }
    
    .prompt-action-btn {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      color: #64748b;
      background: transparent;
      border: none;
      cursor: pointer;
    }
    
    .prompt-action-btn:hover {
      background: #f1f5f9;
      color: #1e293b;
    }
    
    .prompt-action-btn.generate {
      background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
      color: white;
    }
    
    .prompt-action-btn.generate:hover {
      transform: scale(1.05);
      box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
    }
    
    /* === OPTIONS PANEL (slides down from prompt bar) === */
    .options-panel {
      position: fixed;
      top: 85px;
      left: 50%;
      transform: translateX(-50%) translateY(-20px);
      width: 90%;
      max-width: 700px;
      z-index: 99;
      background: rgba(255,255,255,0.95);
      backdrop-filter: blur(20px);
      border-radius: 16px;
      box-shadow: 0 20px 40px -10px rgba(0,0,0,0.3);
      padding: 16px 20px;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .options-panel.visible {
      opacity: 1;
      visibility: visible;
      transform: translateX(-50%) translateY(0);
    }
    
    .option-group {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-bottom: 12px;
    }
    
    .option-group:last-child {
      margin-bottom: 0;
    }
    
    .option-label {
      font-size: 10px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #64748b;
      width: 100%;
      margin-bottom: 4px;
    }
    
    .option-chip {
      padding: 6px 12px;
      font-size: 12px;
      font-weight: 500;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      background: white;
      color: #475569;
      cursor: pointer;
      transition: all 0.15s;
    }
    
    .option-chip:hover {
      border-color: #f59e0b;
      background: #fffbeb;
    }
    
    .option-chip.active {
      background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
      color: white;
      border-color: transparent;
      box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
    }
    
    /* === CANVAS AREA === */
    .canvas-area {
      position: fixed;
      top: 100px;
      left: 80px;
      right: 0;
      bottom: 140px;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px;
    }
    
    @media (max-width: 1023px) {
      .canvas-area {
        left: 0;
        top: 90px;
        bottom: 180px;
        padding: 20px;
      }
    }
    
    /* Empty state */
    .canvas-empty {
      text-align: center;
      color: rgba(255,255,255,0.5);
    }
    
    .canvas-empty-icon {
      width: 120px;
      height: 120px;
      margin: 0 auto 24px;
      background: rgba(255,255,255,0.05);
      border: 2px dashed rgba(255,255,255,0.1);
      border-radius: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 48px;
    }
    
    .canvas-empty h3 {
      font-size: 20px;
      font-weight: 600;
      margin-bottom: 8px;
      color: rgba(255,255,255,0.7);
    }
    
    .canvas-empty p {
      font-size: 14px;
      color: rgba(255,255,255,0.4);
    }
    
    /* Loading state */
    .canvas-loading {
      text-align: center;
    }
    
    .loading-spinner {
      width: 80px;
      height: 80px;
      margin: 0 auto 20px;
      position: relative;
    }
    
    .loading-spinner::before {
      content: '';
      position: absolute;
      inset: 0;
      border: 3px solid rgba(245, 158, 11, 0.2);
      border-radius: 50%;
    }
    
    .loading-spinner::after {
      content: '';
      position: absolute;
      inset: 0;
      border: 3px solid transparent;
      border-top-color: #f59e0b;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    
    .loading-emoji {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
    }
    
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    
    /* Result state */
    .canvas-result {
      position: relative;
      max-width: 100%;
      max-height: 100%;
    }
    
    .result-image {
      max-width: 100%;
      max-height: 100%;
      border-radius: 16px;
      box-shadow: 
        0 50px 100px -20px rgba(0,0,0,0.5),
        0 30px 60px -30px rgba(0,0,0,0.3);
      cursor: zoom-in;
      transition: transform 0.3s ease;
    }
    
    .result-image:hover {
      transform: scale(1.01);
    }
    
    /* Floating actions on image */
    .result-actions {
      position: absolute;
      bottom: -60px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 8px;
      background: rgba(0,0,0,0.8);
      backdrop-filter: blur(10px);
      padding: 8px 12px;
      border-radius: 16px;
      opacity: 0;
      transition: all 0.3s ease;
    }
    
    .canvas-result:hover .result-actions {
      opacity: 1;
      bottom: 16px;
    }
    
    .result-action {
      padding: 10px 16px;
      font-size: 13px;
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
    
    .result-action:hover {
      background: rgba(255,255,255,0.2);
    }
    
    .result-action.primary {
      background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
    }
    
    .result-action.primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
    }
    
    /* === FILMSTRIP (History) === */
    .filmstrip {
      position: fixed;
      bottom: 0;
      left: 80px;
      right: 0;
      height: 130px;
      background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.7) 70%, transparent 100%);
      padding: 16px 24px 20px;
      z-index: 50;
    }
    
    @media (max-width: 1023px) {
      .filmstrip {
        left: 0;
        height: 170px;
        padding-bottom: 80px;
      }
    }
    
    .filmstrip-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }
    
    .filmstrip-title {
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: rgba(255,255,255,0.5);
    }
    
    .filmstrip-scroll {
      display: flex;
      gap: 12px;
      overflow-x: auto;
      padding-bottom: 8px;
      scroll-behavior: smooth;
      scrollbar-width: thin;
      scrollbar-color: rgba(255,255,255,0.2) transparent;
    }
    
    .filmstrip-scroll::-webkit-scrollbar {
      height: 4px;
    }
    
    .filmstrip-scroll::-webkit-scrollbar-track {
      background: transparent;
    }
    
    .filmstrip-scroll::-webkit-scrollbar-thumb {
      background: rgba(255,255,255,0.2);
      border-radius: 2px;
    }
    
    .filmstrip-item {
      flex-shrink: 0;
      width: 70px;
      height: 70px;
      border-radius: 10px;
      overflow: hidden;
      cursor: pointer;
      border: 2px solid transparent;
      transition: all 0.2s;
      position: relative;
    }
    
    .filmstrip-item:hover {
      border-color: rgba(255,255,255,0.3);
      transform: translateY(-4px);
    }
    
    .filmstrip-item.active {
      border-color: #f59e0b;
      box-shadow: 0 0 20px rgba(245, 158, 11, 0.4);
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
      width: 20px;
      height: 20px;
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
      font-size: 10px;
    }
    
    .filmstrip-item:hover .filmstrip-item-delete {
      opacity: 1;
    }
    
    .filmstrip-empty {
      color: rgba(255,255,255,0.3);
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    /* === MODE TOGGLE (floating) === */
    .mode-toggle {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 100;
      display: flex;
      gap: 4px;
      background: rgba(0,0,0,0.6);
      backdrop-filter: blur(10px);
      padding: 4px;
      border-radius: 12px;
    }
    
    .mode-btn {
      padding: 8px 14px;
      font-size: 12px;
      font-weight: 500;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      color: rgba(255,255,255,0.6);
      background: transparent;
      transition: all 0.2s;
    }
    
    .mode-btn:hover {
      color: white;
    }
    
    .mode-btn.active {
      background: rgba(255,255,255,0.15);
      color: white;
    }
    
    /* === PROVIDER TOGGLE (floating) === */
    .provider-toggle {
      position: fixed;
      top: 20px;
      left: 100px;
      z-index: 100;
      display: flex;
      gap: 4px;
      background: rgba(0,0,0,0.6);
      backdrop-filter: blur(10px);
      padding: 4px;
      border-radius: 12px;
    }
    
    @media (max-width: 1023px) {
      .provider-toggle {
        left: 20px;
        top: auto;
        bottom: 190px;
      }
    }
    
    .provider-btn {
      padding: 8px 12px;
      font-size: 11px;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      color: rgba(255,255,255,0.5);
      background: transparent;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    
    .provider-btn:hover {
      color: rgba(255,255,255,0.8);
    }
    
    .provider-btn.active {
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      color: white;
    }
    
    .provider-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
    }
    
    /* === BACK BUTTON === */
    .back-btn {
      position: fixed;
      top: 20px;
      left: 100px;
      z-index: 100;
      padding: 10px 16px;
      font-size: 13px;
      font-weight: 500;
      color: rgba(255,255,255,0.6);
      background: rgba(0,0,0,0.4);
      backdrop-filter: blur(10px);
      border: none;
      border-radius: 10px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
      text-decoration: none;
    }
    
    .back-btn:hover {
      color: white;
      background: rgba(0,0,0,0.6);
    }
    
    @media (max-width: 1023px) {
      .back-btn {
        left: 20px;
      }
    }
    
    /* === EDIT MODE OVERLAY === */
    .edit-overlay {
      position: fixed;
      top: 85px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 98;
      background: rgba(139, 92, 246, 0.95);
      backdrop-filter: blur(10px);
      padding: 12px 20px;
      border-radius: 12px;
      display: none;
      align-items: center;
      gap: 16px;
      box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3);
    }
    
    .edit-overlay.visible {
      display: flex;
    }
    
    .edit-preview {
      display: flex;
      gap: 8px;
    }
    
    .edit-preview-img {
      width: 50px;
      height: 50px;
      border-radius: 8px;
      object-fit: cover;
      border: 2px solid rgba(255,255,255,0.3);
    }
    
    .edit-preview-placeholder {
      width: 50px;
      height: 50px;
      border-radius: 8px;
      border: 2px dashed rgba(255,255,255,0.3);
      display: flex;
      align-items: center;
      justify-content: center;
      color: rgba(255,255,255,0.5);
      font-size: 18px;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .edit-preview-placeholder:hover {
      border-color: white;
      color: white;
    }
    
    .edit-label {
      color: white;
      font-size: 12px;
      font-weight: 500;
    }
    
    .edit-close {
      padding: 6px;
      background: rgba(255,255,255,0.2);
      border: none;
      border-radius: 6px;
      color: white;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }
    
    .edit-close:hover {
      background: rgba(255,255,255,0.3);
    }
    
    /* === LIGHTBOX === */
    .lightbox {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.95);
      z-index: 200;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 40px;
    }
    
    .lightbox.visible {
      display: flex;
    }
    
    .lightbox img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
      border-radius: 8px;
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
      transition: all 0.2s;
    }
    
    .lightbox-close:hover {
      background: rgba(255,255,255,0.2);
    }
    
    /* Hidden file inputs */
    .hidden-input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }
  </style>
</head>
<body>
  <div class="studio-container">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>
    
    <!-- Back Button -->
    <a href="/gestos/" class="back-btn">
      <i class="iconoir-nav-arrow-left"></i>
      Gestos
    </a>
    
    <!-- Provider Toggle -->
    <div class="provider-toggle">
      <button type="button" id="provider-qwen" class="provider-btn active">
        <span class="provider-dot" style="background: #a855f7;"></span>
        Qwen
      </button>
      <button type="button" id="provider-nanobanana" class="provider-btn">
        <span class="provider-dot" style="background: #3b82f6;"></span>
        Gemini
      </button>
      <button type="button" id="provider-flux" class="provider-btn">
        <span class="provider-dot" style="background: #10b981;"></span>
        FLUX
      </button>
    </div>
    
    <!-- Mode Toggle -->
    <div class="mode-toggle">
      <button type="button" id="mode-generate" class="mode-btn active">Crear</button>
      <button type="button" id="mode-edit" class="mode-btn">Editar</button>
    </div>
    
    <!-- Prompt Bar (Spotlight style) -->
    <div class="prompt-bar">
      <input type="text" id="prompt-input" class="prompt-input" placeholder="Describe la imagen que quieres crear..." autocomplete="off" />
      <div class="prompt-actions">
        <button type="button" id="options-toggle" class="prompt-action-btn" title="Opciones de estilo">
          <i class="iconoir-settings"></i>
        </button>
        <button type="button" id="generate-btn" class="prompt-action-btn generate" title="Generar">
          <i class="iconoir-sparks"></i>
        </button>
      </div>
    </div>
    
    <!-- Options Panel -->
    <div id="options-panel" class="options-panel">
      <div class="option-group">
        <span class="option-label">Formato</span>
        <button type="button" class="option-chip active" data-option="format" data-value="1:1">1:1</button>
        <button type="button" class="option-chip" data-option="format" data-value="3:4">3:4</button>
        <button type="button" class="option-chip" data-option="format" data-value="4:3">4:3</button>
        <button type="button" class="option-chip" data-option="format" data-value="16:9">16:9</button>
        <button type="button" class="option-chip" data-option="format" data-value="9:16">9:16</button>
      </div>
      <div class="option-group">
        <span class="option-label">Estilo</span>
        <button type="button" class="option-chip active" data-option="style" data-value="">Auto</button>
        <button type="button" class="option-chip" data-option="style" data-value="photographic">Foto</button>
        <button type="button" class="option-chip" data-option="style" data-value="corporate">Corporativo</button>
        <button type="button" class="option-chip" data-option="style" data-value="headshot-pro">Retrato</button>
        <button type="button" class="option-chip" data-option="style" data-value="3d-render">3D</button>
        <button type="button" class="option-chip" data-option="style" data-value="minimalist">Minimal</button>
      </div>
      <div class="option-group">
        <span class="option-label">Color</span>
        <button type="button" class="option-chip active" data-option="color" data-value="">Auto</button>
        <button type="button" class="option-chip" data-option="color" data-value="warm">Cálido</button>
        <button type="button" class="option-chip" data-option="color" data-value="cool">Frío</button>
        <button type="button" class="option-chip" data-option="color" data-value="bw">B/N</button>
        <button type="button" class="option-chip" data-option="color" data-value="vibrant">Vibrante</button>
      </div>
      <div class="option-group">
        <span class="option-label">Luz</span>
        <button type="button" class="option-chip active" data-option="lighting" data-value="">Auto</button>
        <button type="button" class="option-chip" data-option="lighting" data-value="natural">Natural</button>
        <button type="button" class="option-chip" data-option="lighting" data-value="studio">Estudio</button>
        <button type="button" class="option-chip" data-option="lighting" data-value="dramatic">Drama</button>
        <button type="button" class="option-chip" data-option="lighting" data-value="golden">Dorada</button>
      </div>
    </div>
    
    <!-- Edit Mode Overlay -->
    <div id="edit-overlay" class="edit-overlay">
      <div class="edit-preview">
        <label class="edit-preview-placeholder" id="source-upload-trigger" title="Subir imagen fuente">
          <i class="iconoir-plus"></i>
          <img id="source-preview" class="edit-preview-img" style="display:none;" />
        </label>
        <label class="edit-preview-placeholder" id="target-upload-trigger" title="Imagen objetivo (opcional)">
          <i class="iconoir-plus"></i>
          <img id="target-preview" class="edit-preview-img" style="display:none;" />
        </label>
      </div>
      <span class="edit-label">Sube la imagen a editar →</span>
      <button type="button" id="edit-close" class="edit-close">
        <i class="iconoir-xmark"></i>
      </button>
    </div>
    
    <!-- Hidden file inputs -->
    <input type="file" id="source-input" class="hidden-input" accept="image/*" />
    <input type="file" id="target-input" class="hidden-input" accept="image/*" />
    
    <!-- Canvas Area -->
    <div class="canvas-area">
      <!-- Empty State -->
      <div id="canvas-empty" class="canvas-empty">
        <div class="canvas-empty-icon">🍌</div>
        <h3>Crea algo increíble</h3>
        <p>Escribe lo que quieres y pulsa Enter o el botón ✨</p>
      </div>
      
      <!-- Loading State -->
      <div id="canvas-loading" class="canvas-loading" style="display:none;">
        <div class="loading-spinner">
          <span class="loading-emoji">🍌</span>
        </div>
        <p style="color: rgba(255,255,255,0.6); font-size: 14px;">Generando tu imagen...</p>
      </div>
      
      <!-- Result State -->
      <div id="canvas-result" class="canvas-result" style="display:none;">
        <img id="result-image" class="result-image" src="" alt="Imagen generada" />
        <div class="result-actions">
          <button type="button" id="action-edit" class="result-action primary">
            <i class="iconoir-edit"></i>
            Editar
          </button>
          <button type="button" id="action-regenerate" class="result-action">
            <i class="iconoir-refresh"></i>
            Regenerar
          </button>
          <button type="button" id="action-download" class="result-action">
            <i class="iconoir-download"></i>
            Descargar
          </button>
        </div>
      </div>
    </div>
    
    <!-- Filmstrip (History) -->
    <div class="filmstrip">
      <div class="filmstrip-header">
        <span class="filmstrip-title">Historial</span>
      </div>
      <div id="filmstrip-scroll" class="filmstrip-scroll">
        <div id="filmstrip-empty" class="filmstrip-empty">
          <i class="iconoir-media-image"></i>
          Las imágenes que generes aparecerán aquí
        </div>
      </div>
    </div>
    
    <!-- Lightbox -->
    <div id="lightbox" class="lightbox">
      <button type="button" id="lightbox-close" class="lightbox-close">
        <i class="iconoir-xmark"></i>
      </button>
      <img id="lightbox-image" src="" alt="Imagen ampliada" />
    </div>
  </div>

  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
  
  <script>window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken); ?>';</script>
  <script src="/assets/js/gesture-image-editor.js"></script>
</body>
</html>
