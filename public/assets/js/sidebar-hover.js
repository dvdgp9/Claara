/**
 * Sidebar Hover Menu - JavaScript
 * Carga dinámica de contenido para los menús hover del sidebar
 */
(function() {
  'use strict';

  // Cache de datos para evitar llamadas repetidas
  const cache = {
    conversations: null,
    gestures: {},
    voices: null,
    lastFetch: {}
  };

  const CACHE_TTL = 30000; // 30 segundos

  // Inicializar cuando el DOM esté listo
  document.addEventListener('DOMContentLoaded', initSidebarHover);

  function initSidebarHover() {
    const containers = document.querySelectorAll('.sidebar-tab-container');
    
    containers.forEach(container => {
      const tabType = container.dataset.tabType;
      if (!tabType) return;

      container.addEventListener('mouseenter', () => {
        positionPanel(container);
        loadPanelContent(container, tabType);
      });
    });

    // Inicializar submenús de gestos (delegación de eventos)
    // Usamos mouseover en lugar de mouseenter para capturar antes
    document.addEventListener('mouseover', (e) => {
      if (!e.target || !e.target.closest) return;
      const item = e.target.closest('.hover-panel-item-expandable');
      if (item && item.dataset.gestureType) {
        // Posicionar inmediatamente antes de que CSS lo haga visible
        positionSubmenu(item);
        loadGestureHistory(item, item.dataset.gestureType);
      }
    }, true);

    // Pre-posicionar submenús cuando se abre el panel principal
    containers.forEach(container => {
      const tabType = container.dataset.tabType;
      if (tabType === 'gestures') {
        container.addEventListener('mouseenter', () => {
          // Pre-posicionar todos los submenús del panel de gestos
          requestAnimationFrame(() => {
            const expandableItems = container.querySelectorAll('.hover-panel-item-expandable');
            expandableItems.forEach(item => positionSubmenu(item));
          });
        });
      }
    });
  }

  function positionPanel(container) {
    const panel = container.querySelector('.sidebar-hover-panel');
    if (!panel) return;

    const rect = container.getBoundingClientRect();
    const panelHeight = panel.offsetHeight || 400;
    const viewportHeight = window.innerHeight;
    const margin = 20;

    // Calcular posición top óptima
    let top = rect.top;
    
    // Si el panel se sale por abajo, ajustar hacia arriba
    if (top + panelHeight > viewportHeight - margin) {
      top = Math.max(margin, viewportHeight - panelHeight - margin);
    }

    panel.style.top = top + 'px';
  }

  function positionSubmenu(item) {
    const submenu = item.querySelector('.hover-submenu');
    if (!submenu) return;

    const panel = item.closest('.sidebar-hover-panel');
    if (!panel) return;

    const itemRect = item.getBoundingClientRect();
    const panelRect = panel.getBoundingClientRect();
    const submenuHeight = submenu.offsetHeight || 300;
    const viewportHeight = window.innerHeight;
    const margin = 20;

    // El sidebar tiene 70px, el panel hover tiene 280-320px de ancho
    // Calcular la posición correcta basándose en valores conocidos
    // El panel está a left: 78px cuando está visible, con max-width: 320px
    const sidebarWidth = 70;
    const panelMaxWidth = 320;
    const gap = 8;
    
    // Usar el valor calculado del panel si es válido, sino usar posición fija
    let left;
    if (panelRect.right > sidebarWidth + 100) {
      // El panel está correctamente posicionado
      left = panelRect.right + gap;
    } else {
      // Fallback: calcular basándose en posiciones conocidas
      left = sidebarWidth + panelMaxWidth + gap;
    }
    
    let top = itemRect.top - 8;

    // Si se sale por abajo, ajustar
    if (top + submenuHeight > viewportHeight - margin) {
      top = Math.max(margin, viewportHeight - submenuHeight - margin);
    }
    
    // Asegurar que top sea válido
    if (top < margin || isNaN(top)) {
      top = margin;
    }

    submenu.style.left = left + 'px';
    submenu.style.top = top + 'px';
  }

  async function loadPanelContent(container, type) {
    const panel = container.querySelector('.hover-panel-content');
    if (!panel) return;

    // Gestos y apps son estáticos (renderizados en PHP), no necesitan carga
    if (type === 'gestures' || type === 'apps') {
      return;
    }

    // Verificar cache para contenido dinámico
    if (isCacheValid(type)) {
      return; // Ya tenemos datos frescos
    }

    // Mostrar loading solo para contenido dinámico
    panel.innerHTML = `
      <div class="hover-panel-loading">
        <i class="iconoir-refresh"></i>
      </div>
    `;

    try {
      switch (type) {
        case 'conversations':
          await loadConversations(panel);
          break;
        case 'voices':
          await loadVoices(panel);
          break;
      }
    } catch (error) {
      console.error('Error loading panel content:', error);
      panel.innerHTML = `
        <div class="hover-panel-empty">
          <i class="iconoir-warning-triangle"></i>
          <p class="hover-panel-empty-text">Error al cargar</p>
        </div>
      `;
    }
  }

  async function loadConversations(panel) {
    const response = await fetch('/api/conversations/list.php', {
      credentials: 'include'
    });
    const data = await response.json();

    if (!response.ok || !data.items) {
      throw new Error('Failed to load conversations');
    }

    cache.conversations = data.items;
    cache.lastFetch.conversations = Date.now();

    const items = data.items.slice(0, 6); // Últimas 6

    if (items.length === 0) {
      panel.innerHTML = `
        <div class="hover-panel-empty">
          <i class="iconoir-chat-bubble"></i>
          <p class="hover-panel-empty-text">Sin conversaciones aún</p>
        </div>
      `;
      return;
    }

    panel.innerHTML = items.map(conv => `
      <a href="/?c=${conv.id}" class="hover-panel-item">
        <div class="hover-panel-item-icon">
          <i class="iconoir-chat-bubble"></i>
        </div>
        <div class="hover-panel-item-info">
          <div class="hover-panel-item-title">${escapeHtml(conv.title || 'Conversación sin título')}</div>
          <div class="hover-panel-item-meta">${formatTimeAgo(conv.updated_at || conv.created_at)}</div>
        </div>
      </a>
    `).join('');
  }

  async function loadVoices(panel) {
    // Por ahora las voces son estáticas, pero preparado para API
    const voices = [
      { id: 'lex', name: 'Lex', description: 'Abogado experto', icon: 'iconoir-book-stack', href: '/voices/lex.php' }
      // Añadir más voces aquí cuando estén disponibles
    ];

    cache.voices = voices;
    cache.lastFetch.voices = Date.now();

    if (voices.length === 0) {
      panel.innerHTML = `
        <div class="hover-panel-empty">
          <i class="iconoir-voice-square"></i>
          <p class="hover-panel-empty-text">No hay voces disponibles</p>
        </div>
      `;
      return;
    }

    panel.innerHTML = voices.map(voice => `
      <a href="${voice.href}" class="hover-panel-item">
        <div class="hover-panel-item-icon">
          <i class="${voice.icon}"></i>
        </div>
        <div class="hover-panel-item-info">
          <div class="hover-panel-item-title">${escapeHtml(voice.name)}</div>
          <div class="hover-panel-item-meta">${escapeHtml(voice.description)}</div>
        </div>
      </a>
    `).join('');
  }

  async function loadGestureHistory(item, gestureType) {
    const submenu = item.querySelector('.hover-submenu-content');
    if (!submenu) return;

    // Verificar cache
    if (cache.gestures[gestureType] && 
        cache.lastFetch[`gesture_${gestureType}`] && 
        Date.now() - cache.lastFetch[`gesture_${gestureType}`] < CACHE_TTL) {
      return;
    }

    submenu.innerHTML = `
      <div class="hover-panel-loading">
        <i class="iconoir-refresh"></i>
      </div>
    `;

    try {
      const response = await fetch(`/api/gestures/history.php?type=${gestureType}&limit=5`, {
        credentials: 'include'
      });
      const data = await response.json();

      if (!response.ok) {
        throw new Error('Failed to load history');
      }

      cache.gestures[gestureType] = data.items || [];
      cache.lastFetch[`gesture_${gestureType}`] = Date.now();

      const items = data.items || [];

      if (items.length === 0) {
        submenu.innerHTML = `
          <div class="hover-panel-empty" style="padding: 16px;">
            <i class="iconoir-clock"></i>
            <p class="hover-panel-empty-text">Sin historial aún</p>
          </div>
        `;
        return;
      }

      submenu.innerHTML = items.map(item => {
        const title = item.title || 'Sin título';
        const truncatedTitle = title.length > 50 ? title.substring(0, 50) + '...' : title;
        return `
          <a href="${getGestureUrl(gestureType)}?id=${item.id}" class="hover-submenu-item">
            <span class="hover-submenu-item-title">${escapeHtml(truncatedTitle)}</span>
            <span class="hover-submenu-item-time">${formatTimeAgo(item.created_at)}</span>
          </a>
        `;
      }).join('');

    } catch (error) {
      console.error('Error loading gesture history:', error);
      submenu.innerHTML = `
        <div class="hover-panel-empty" style="padding: 16px;">
          <i class="iconoir-warning-triangle"></i>
          <p class="hover-panel-empty-text">Error al cargar</p>
        </div>
      `;
    }
  }

  function getGestureUrl(type) {
    const urls = {
      'podcast-from-article': '/gestos/podcast-articulo.php',
      'write-article': '/gestos/escribir-articulo.php',
      'social-media': '/gestos/redes-sociales.php'
    };
    return urls[type] || '/gestos/';
  }

  function isCacheValid(type) {
    const lastFetch = cache.lastFetch[type];
    return lastFetch && (Date.now() - lastFetch < CACHE_TTL);
  }

  function formatTimeAgo(dateStr) {
    if (!dateStr) return '';
    
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Ahora';
    if (diffMins < 60) return `${diffMins} min`;
    if (diffHours < 24) return `${diffHours}h`;
    if (diffDays < 7) return `${diffDays}d`;
    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
  }

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
})();
