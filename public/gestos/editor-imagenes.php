<?php
session_start();
require_once __DIR__ . '/../../src/App/Env.php';
require_once __DIR__ . '/../../src/App/DB.php';
require_once __DIR__ . '/../../src/Repos/UserFeatureAccessRepo.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Verificar acceso a este gesto
$accessRepo = new UserFeatureAccessRepo();
if (!$accessRepo->hasGestureAccess((int)$_SESSION['user_id'], 'image-editor')) {
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
  <title>Editor de Imágenes - Ebonia</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/iconoir@7.9.0/css/iconoir.min.css">
  <style>
    * { box-sizing: border-box; }
    
    body {
      margin: 0;
      padding: 0;
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
      color: #f1f5f9;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      overflow: hidden;
      height: 100vh;
    }
    
    /* === STUDIO LAYOUT === */
    .studio-app {
      display: grid;
      grid-template-columns: 280px 1fr 340px;
      grid-template-rows: 56px 1fr;
      height: 100vh;
      gap: 0;
    }
    
    /* === HEADER === */
    .studio-header {
      grid-column: 1 / -1;
      background: rgba(15, 23, 42, 0.95);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(234, 179, 8, 0.2);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 20px;
      z-index: 100;
    }
    
    .header-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    
    .header-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 700;
      font-size: 18px;
      background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    
    .header-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .header-btn {
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
      border: 1px solid rgba(234, 179, 8, 0.3);
      background: rgba(234, 179, 8, 0.1);
      color: #fcd34d;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    
    .header-btn:hover {
      background: rgba(234, 179, 8, 0.2);
      border-color: rgba(234, 179, 8, 0.5);
    }
    
    .header-btn.primary {
      background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
      border-color: transparent;
      color: white;
    }
    
    .header-btn.primary:hover {
      box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
      transform: translateY(-1px);
    }
    
    /* === SIDEBAR (HISTORY) === */
    .studio-sidebar {
      background: rgba(15, 23, 42, 0.8);
      border-right: 1px solid rgba(234, 179, 8, 0.1);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    
    .sidebar-header {
      padding: 16px;
      border-bottom: 1px solid rgba(234, 179, 8, 0.1);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    .sidebar-title {
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: #94a3b8;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    
    .sidebar-content {
      flex: 1;
      overflow-y: auto;
      padding: 8px;
    }
    
    .history-item {
      display: flex;
      gap: 10px;
      padding: 10px;
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.2s;
      border: 1px solid transparent;
      margin-bottom: 4px;
    }
    
    .history-item:hover {
      background: rgba(234, 179, 8, 0.1);
      border-color: rgba(234, 179, 8, 0.2);
    }
    
    .history-item.active {
      background: rgba(234, 179, 8, 0.15);
      border-color: rgba(234, 179, 8, 0.4);
    }
    
    .history-thumb {
      width: 48px;
      height: 48px;
      border-radius: 8px;
      object-fit: cover;
      flex-shrink: 0;
      background: rgba(30, 41, 59, 0.8);
      border: 1px solid rgba(234, 179, 8, 0.2);
    }
    
    .history-info {
      flex: 1;
      min-width: 0;
    }
    
    .history-desc {
      font-size: 12px;
      font-weight: 500;
      color: #f1f5f9;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-bottom: 4px;
    }
    
    .history-meta {
      font-size: 10px;
      color: #64748b;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .history-delete {
      opacity: 0;
      padding: 4px;
      color: #64748b;
      transition: all 0.2s;
    }
    
    .history-item:hover .history-delete {
      opacity: 1;
    }
    
    .history-delete:hover {
      color: #ef4444;
    }
    
    /* === CANVAS (CENTER) === */
    .studio-canvas {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      background: 
        radial-gradient(circle at 50% 50%, rgba(234, 179, 8, 0.03) 0%, transparent 50%),
        linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      overflow: hidden;
    }
    
    .canvas-content {
      position: relative;
      max-width: 90%;
      max-height: 90%;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 16px;
    }
    
    .canvas-image {
      max-width: 100%;
      max-height: calc(100vh - 200px);
      border-radius: 16px;
      box-shadow: 
        0 25px 50px -12px rgba(0, 0, 0, 0.5),
        0 0 0 1px rgba(234, 179, 8, 0.2);
      cursor: zoom-in;
      transition: all 0.3s ease;
    }
    
    .canvas-image:hover {
      box-shadow: 
        0 30px 60px -15px rgba(0, 0, 0, 0.6),
        0 0 0 2px rgba(234, 179, 8, 0.4);
    }
    
    .canvas-empty {
      text-align: center;
      color: #64748b;
    }
    
    .canvas-empty-icon {
      width: 120px;
      height: 120px;
      margin: 0 auto 20px;
      background: rgba(234, 179, 8, 0.05);
      border: 2px dashed rgba(234, 179, 8, 0.2);
      border-radius: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 48px;
    }
    
    .canvas-empty h3 {
      font-size: 20px;
      font-weight: 600;
      color: #f1f5f9;
      margin-bottom: 8px;
    }
    
    .canvas-empty p {
      font-size: 14px;
      color: #64748b;
    }
    
    /* Quick Actions Bar */
    .quick-actions {
      display: flex;
      gap: 8px;
      padding: 8px 16px;
      background: rgba(15, 23, 42, 0.9);
      backdrop-filter: blur(20px);
      border-radius: 16px;
      border: 1px solid rgba(234, 179, 8, 0.2);
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
      margin-top: 8px;
    }
    
    .quick-action {
      padding: 10px 16px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 500;
      border: 1px solid rgba(234, 179, 8, 0.3);
      background: rgba(234, 179, 8, 0.1);
      color: #fcd34d;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    
    .quick-action:hover {
      background: rgba(234, 179, 8, 0.2);
      border-color: rgba(234, 179, 8, 0.5);
      transform: translateY(-2px);
    }
    
    .quick-action.primary {
      background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
      border-color: transparent;
      color: white;
    }
    
    .quick-action.primary:hover {
      box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
    }
    
    /* === PANEL (RIGHT) === */
    .studio-panel {
      background: rgba(15, 23, 42, 0.9);
      border-left: 1px solid rgba(234, 179, 8, 0.1);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    
    .panel-tabs {
      display: flex;
      border-bottom: 1px solid rgba(234, 179, 8, 0.1);
      padding: 8px;
      gap: 4px;
    }
    
    .panel-tab {
      flex: 1;
      padding: 10px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      border: 1px solid transparent;
      background: transparent;
      color: #64748b;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    
    .panel-tab:hover {
      background: rgba(234, 179, 8, 0.1);
      color: #fcd34d;
    }
    
    .panel-tab.active {
      background: rgba(234, 179, 8, 0.15);
      border-color: rgba(234, 179, 8, 0.3);
      color: #fcd34d;
    }
    
    .panel-content {
      flex: 1;
      overflow-y: auto;
      padding: 16px;
    }
    
    .panel-section {
      margin-bottom: 20px;
    }
    
    .panel-section-title {
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: #94a3b8;
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    
    .provider-selector {
      display: flex;
      gap: 6px;
      background: rgba(30, 41, 59, 0.8);
      padding: 6px;
      border-radius: 12px;
      border: 1px solid rgba(234, 179, 8, 0.2);
    }
    
    .provider-option {
      flex: 1;
      padding: 8px 12px;
      border-radius: 8px;
      font-size: 11px;
      font-weight: 600;
      border: 1px solid transparent;
      background: transparent;
      color: #64748b;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    
    .provider-option:hover {
      background: rgba(234, 179, 8, 0.1);
      color: #fcd34d;
    }
    
    .provider-option.active {
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      border-color: transparent;
      color: white;
    }
    
    .provider-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
    }
    
    .prompt-textarea {
      width: 100%;
      min-height: 100px;
      padding: 14px;
      border-radius: 12px;
      border: 1px solid rgba(234, 179, 8, 0.3);
      background: rgba(30, 41, 59, 0.8);
      color: #f1f5f9;
      font-size: 14px;
      resize: vertical;
      outline: none;
      transition: all 0.2s;
    }
    
    .prompt-textarea:focus {
      border-color: rgba(234, 179, 8, 0.6);
      box-shadow: 0 0 0 3px rgba(234, 179, 8, 0.1);
    }
    
    .prompt-textarea::placeholder {
      color: #64748b;
    }
    
    .options-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 8px;
    }
    
    .option-card {
      padding: 10px;
      border-radius: 10px;
      border: 1px solid rgba(234, 179, 8, 0.2);
      background: rgba(30, 41, 59, 0.6);
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .option-card:hover {
      background: rgba(234, 179, 8, 0.1);
      border-color: rgba(234, 179, 8, 0.4);
    }
    
    .option-card.active {
      background: rgba(234, 179, 8, 0.15);
      border-color: rgba(234, 179, 8, 0.5);
    }
    
    .option-card-icon {
      font-size: 20px;
      margin-bottom: 6px;
    }
    
    .option-card-label {
      font-size: 11px;
      font-weight: 600;
      color: #f1f5f9;
    }
    
    .option-card-desc {
      font-size: 9px;
      color: #64748b;
      margin-top: 2px;
    }
    
    .generate-btn {
      width: 100%;
      padding: 14px;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 600;
      border: none;
      background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
      color: white;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
    }
    
    .generate-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
    }
    
    .generate-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }
    
    /* Edit Mode Upload */
    .upload-zone {
      border: 2px dashed rgba(234, 179, 8, 0.3);
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s;
      background: rgba(30, 41, 59, 0.4);
    }
    
    .upload-zone:hover {
      border-color: rgba(234, 179, 8, 0.6);
      background: rgba(234, 179, 8, 0.1);
    }
    
    .upload-zone-icon {
      font-size: 32px;
      margin-bottom: 8px;
      color: #64748b;
    }
    
    .upload-zone-text {
      font-size: 12px;
      color: #94a3b8;
    }
    
    /* Loading State */
    .loading-overlay {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.9);
      backdrop-filter: blur(10px);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 16px;
      z-index: 50;
    }
    
    .loading-spinner {
      width: 60px;
      height: 60px;
      border: 3px solid rgba(234, 179, 8, 0.2);
      border-top-color: #f59e0b;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    
    .loading-text {
      font-size: 14px;
      color: #fcd34d;
      font-weight: 500;
    }
    
    /* Lightbox */
    .lightbox {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.95);
      z-index: 1000;
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
      border-radius: 12px;
    }
    
    .lightbox-close {
      position: absolute;
      top: 20px;
      right: 20px;
      width: 44px;
      height: 44px;
      background: rgba(255, 255, 255, 0.1);
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
      background: rgba(255, 255, 255, 0.2);
    }
    
    /* Scrollbar */
    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    
    ::-webkit-scrollbar-track {
      background: rgba(30, 41, 59, 0.5);
    }
    
    ::-webkit-scrollbar-thumb {
      background: rgba(234, 179, 8, 0.3);
      border-radius: 3px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
      background: rgba(234, 179, 8, 0.5);
    }
    
    /* Mobile Responsive */
    @media (max-width: 1024px) {
      .studio-app {
        grid-template-columns: 1fr;
        grid-template-rows: 56px 1fr auto;
      }
      
      .studio-sidebar,
      .studio-panel {
        display: none;
      }
      
      .studio-canvas {
        grid-column: 1;
        grid-row: 2;
      }
    }
  </style>
</head>
<body>
  <div class="studio-app">
    <!-- Header -->
    <header class="studio-header">
      <div class="header-left">
        <a href="/gestos/" style="color: #64748b; text-decoration: none; display: flex; align-items: center; gap: 6px; font-size: 13px;">
          <i class="iconoir-nav-arrow-left"></i>
          Gestos
        </a>
        <div class="header-logo">
          <span style="font-size: 24px;">🍌</span>
          Editor de Imágenes
        </div>
      </div>
      <div class="header-right">
        <button class="header-btn" id="new-image-btn">
          <i class="iconoir-plus"></i>
          Nueva
        </button>
        <button class="header-btn primary" id="download-header-btn">
          <i class="iconoir-download"></i>
          Descargar
        </button>
      </div>
    </header>
    
    <!-- Sidebar: Historial -->
    <aside class="studio-sidebar">
      <div class="sidebar-header">
        <div class="sidebar-title">
          <i class="iconoir-clock"></i>
          Historial
        </div>
        <span id="history-count" style="font-size: 10px; color: #64748b;">0</span>
      </div>
      <div class="sidebar-content" id="history-list">
        <div class="canvas-empty" style="padding: 40px 20px;">
          <div class="canvas-empty-icon" style="width: 60px; height: 60px; font-size: 24px;">
            <i class="iconoir-media-image"></i>
          </div>
          <h3 style="font-size: 14px;">Sin historial</h3>
          <p style="font-size: 12px;">Las imágenes aparecerán aquí</p>
        </div>
      </div>
    </aside>
    
    <!-- Canvas Central -->
    <main class="studio-canvas" id="canvas-area">
      <div class="canvas-content" id="canvas-content">
        <!-- Empty State -->
        <div class="canvas-empty" id="canvas-empty">
          <div class="canvas-empty-icon">🍌</div>
          <h3>Crea algo increíble</h3>
          <p>Usa el panel derecho para generar tu primera imagen</p>
        </div>
        
        <!-- Image (hidden initially) -->
        <img id="canvas-image" class="canvas-image" src="" alt="" style="display: none;" />
        
        <!-- Loading (hidden initially) -->
        <div class="loading-overlay" id="canvas-loading" style="display: none;">
          <div class="loading-spinner"></div>
          <div class="loading-text">Generando imagen...</div>
        </div>
        
        <!-- Quick Actions (hidden initially) -->
        <div class="quick-actions" id="quick-actions" style="display: none;">
          <button class="quick-action primary" id="action-edit">
            <i class="iconoir-edit"></i>
            Editar
          </button>
          <button class="quick-action" id="action-regenerate">
            <i class="iconoir-refresh"></i>
            Regenerar
          </button>
          <button class="quick-action" id="action-variation">
            <i class="iconoir-magic-wand"></i>
            Variar
          </button>
          <button class="quick-action" id="action-download">
            <i class="iconoir-download"></i>
            Descargar
          </button>
        </div>
      </div>
    </main>
    
    <!-- Panel Derecho -->
    <aside class="studio-panel">
      <div class="panel-tabs">
        <button class="panel-tab active" data-tab="generate" id="tab-generate">
          <i class="iconoir-sparks"></i>
          Generar
        </button>
        <button class="panel-tab" data-tab="edit" id="tab-edit">
          <i class="iconoir-edit"></i>
          Editar
        </button>
      </div>
      
      <div class="panel-content">
        <!-- Generate Tab -->
        <div id="panel-generate">
          <!-- Provider Selector -->
          <div class="panel-section">
            <div class="panel-section-title">
              <i class="iconoir-3d-cube"></i>
              Motor
            </div>
            <div class="provider-selector">
              <button class="provider-option active" data-provider="qwen">
                <span class="provider-dot" style="background: #a855f7;"></span>
                Qwen
              </button>
              <button class="provider-option" data-provider="nanobanana">
                <span class="provider-dot" style="background: #3b82f6;"></span>
                Nanobanana
              </button>
              <button class="provider-option" data-provider="flux">
                <span class="provider-dot" style="background: #10b981;"></span>
                FLUX
              </button>
            </div>
            <input type="hidden" id="current-provider" value="qwen" />
          </div>
          
          <!-- Prompt -->
          <div class="panel-section">
            <div class="panel-section-title">
              <i class="iconoir-edit-pencil"></i>
              Descripción
            </div>
            <textarea 
              id="prompt-input" 
              class="prompt-textarea" 
              placeholder="Describe la imagen que quieres crear... Sé específico con objetos, escena, ambiente, colores..."
            ></textarea>
          </div>
          
          <!-- Style Options -->
          <div class="panel-section">
            <div class="panel-section-title">
              <i class="iconoir-design-pencil"></i>
              Estilo
            </div>
            <div class="options-grid" id="style-options">
              <div class="option-card active" data-style="">
                <div class="option-card-icon">🎨</div>
                <div class="option-card-label">Auto</div>
                <div class="option-card-desc">El mejor estilo</div>
              </div>
              <div class="option-card" data-style="photographic">
                <div class="option-card-icon">📷</div>
                <div class="option-card-label">Fotográfico</div>
                <div class="option-card-desc">Ultra realista</div>
              </div>
              <div class="option-card" data-style="digital-art">
                <div class="option-card-icon">✨</div>
                <div class="option-card-label">Digital Art</div>
                <div class="option-card-desc">Ilustración moderna</div>
              </div>
              <div class="option-card" data-style="corporate">
                <div class="option-card-icon">💼</div>
                <div class="option-card-label">Corporativo</div>
                <div class="option-card-desc">Profesional</div>
              </div>
              <div class="option-card" data-style="headshot-pro">
                <div class="option-card-icon">👤</div>
                <div class="option-card-label">Retrato Pro</div>
                <div class="option-card-desc">Estudio profesional</div>
              </div>
              <div class="option-card" data-style="silicon-valley">
                <div class="option-card-icon">🏢</div>
                <div class="option-card-label">Corp Pro</div>
                <div class="option-card-desc">Ejecutivo</div>
              </div>
            </div>
            <input type="hidden" id="current-style" value="" />
          </div>
          
          <!-- Format Options -->
          <div class="panel-section">
            <div class="panel-section-title">
              <i class="iconoir-frame"></i>
              Formato
            </div>
            <div class="options-grid" id="format-options">
              <div class="option-card active" data-format="1:1">
                <div class="option-card-icon">⬜</div>
                <div class="option-card-label">1:1</div>
                <div class="option-card-desc">Cuadrado</div>
              </div>
              <div class="option-card" data-format="3:4">
                <div class="option-card-icon">📱</div>
                <div class="option-card-label">3:4</div>
                <div class="option-card-desc">Portrait</div>
              </div>
              <div class="option-card" data-format="4:3">
                <div class="option-card-icon">🖼️</div>
                <div class="option-card-label">4:3</div>
                <div class="option-card-desc">Landscape</div>
              </div>
              <div class="option-card" data-format="16:9">
                <div class="option-card-icon">📺</div>
                <div class="option-card-label">16:9</div>
                <div class="option-card-desc">Widescreen</div>
              </div>
            </div>
            <input type="hidden" id="current-format" value="1:1" />
          </div>
          
          <!-- Generate Button -->
          <button class="generate-btn" id="generate-btn">
            <i class="iconoir-sparks"></i>
            Generar Imagen
          </button>
        </div>
        
        <!-- Edit Tab -->
        <div id="panel-edit" style="display: none;">
          <div class="panel-section">
            <div class="panel-section-title">
              <i class="iconoir-info-circle"></i>
              Modo Edición
            </div>
            <p style="font-size: 12px; color: #94a3b8; line-height: 1.6;">
              Sube una imagen desde el historial o desde tu dispositivo y describe los cambios que quieres hacer.
            </p>
          </div>
          
          <!-- Upload Zone -->
          <div class="panel-section">
            <div class="panel-section-title">
              <i class="iconoir-upload"></i>
              Imagen Fuente
            </div>
            <div class="upload-zone" id="edit-upload-zone">
              <input type="file" id="edit-file-input" accept="image/*" style="display: none;" />
              <div id="edit-upload-placeholder">
                <div class="upload-zone-icon">
                  <i class="iconoir-upload"></i>
                </div>
                <div class="upload-zone-text">
                  Arrastra o haz clic<br>para subir
                </div>
              </div>
              <img id="edit-preview" src="" alt="" style="max-width: 100%; max-height: 150px; border-radius: 8px; display: none;" />
            </div>
            <input type="hidden" id="edit-image-base64" value="" />
          </div>
          
          <!-- Edit Prompt -->
          <div class="panel-section">
            <div class="panel-section-title">
              <i class="iconoir-edit-pencil"></i>
              Cambios
            </div>
            <textarea 
              id="edit-prompt-input" 
              class="prompt-textarea" 
              placeholder="Describe los cambios: 'Cambia el fondo por una playa', 'Añade gafas de sol', 'Haz el estilo más dramático'..."
            ></textarea>
          </div>
          
          <!-- Edit Button -->
          <button class="generate-btn" id="edit-btn">
            <i class="iconoir-edit"></i>
            Aplicar Edición
          </button>
        </div>
      </div>
    </aside>
  </div>
  
  <!-- Lightbox -->
  <div class="lightbox" id="lightbox">
    <button class="lightbox-close" id="lightbox-close">
      <i class="iconoir-xmark"></i>
    </button>
    <img id="lightbox-image" src="" alt="" />
  </div>
  
  <script>window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken); ?>';</script>
  <script src="/assets/js/gesture-image-editor.js"></script>
</body>
</html>
