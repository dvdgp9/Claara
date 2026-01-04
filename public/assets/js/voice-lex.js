/**
 * Voice: Lex - Asistente Legal de Ebone
 * Maneja el chat especializado con contexto legal
 */

(function() {
  'use strict';

  // ===== STATE =====
  const VOICE_ID = 'lex';
  let currentUser = null;
  let currentExecutionId = null;
  let messageHistory = [];

  // ===== DOM REFS =====
  const chatForm = document.getElementById('chat-form');
  const chatInput = document.getElementById('chat-input');
  const messagesContainer = document.getElementById('messages-container');
  const messagesEl = document.getElementById('messages');
  const emptyState = document.getElementById('empty-state');
  const typingIndicator = document.getElementById('typing-indicator');
  const historyList = document.getElementById('history-list');
  const newChatBtn = document.getElementById('new-chat-btn');
  const toggleDocsBtn = document.getElementById('toggle-docs-panel');
  const docsPanel = document.getElementById('docs-panel');
  const docsArrow = document.getElementById('docs-arrow');
  const docsList = document.getElementById('docs-list');
  const docViewerModal = document.getElementById('doc-viewer-modal');
  const docViewerTitle = document.getElementById('doc-viewer-title');
  const docViewerContent = document.getElementById('doc-viewer-content');
  const closeDocViewerBtn = document.getElementById('close-doc-viewer');
  const closeDocViewerBtn2 = document.getElementById('close-doc-viewer-btn');
  const docsSearchInput = document.getElementById('docs-search');

  // ===== HELPERS =====
  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  }

  function mdToHtml(md) {
    if (!md) return '';
    let s = escapeHtml(md);
    
    // Blockquotes (antes de otros elementos)
    s = s.replace(/^&gt;\s*(.+)$/gm, '<blockquote>$1</blockquote>');
    
    // Headers
    s = s.replace(/^###\s+(.+)$/gm, '<h3>$1</h3>');
    s = s.replace(/^##\s+(.+)$/gm, '<h2>$1</h2>');
    s = s.replace(/^#\s+(.+)$/gm, '<h1>$1</h1>');
    
    // Bold and italic
    s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/\*(.+?)\*/g, '<em>$1</em>');
    
    // Code
    s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
    
    // Lists (detectar bloques de listas)
    const lines = s.split('\n');
    let inList = false;
    let result = [];
    
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      
      if (line.match(/^-\s+(.+)$/)) {
        if (!inList) {
          result.push('<ul>');
          inList = true;
        }
        result.push('<li>' + line.replace(/^-\s+/, '') + '</li>');
      } else {
        if (inList) {
          result.push('</ul>');
          inList = false;
        }
        result.push(line);
      }
    }
    
    if (inList) {
      result.push('</ul>');
    }
    
    s = result.join('\n');
    
    // Line breaks (pero no dentro de listas)
    s = s.replace(/\n(?!<\/?(ul|li|h[1-3]|blockquote)>)/g, '<br>');
    
    return s;
  }

  function timeAgo(date) {
    const now = new Date();
    const diff = Math.floor((now - new Date(date)) / 1000);
    if (diff < 60) return 'ahora';
    if (diff < 3600) return `hace ${Math.floor(diff/60)} min`;
    if (diff < 86400) return `hace ${Math.floor(diff/3600)}h`;
    return new Date(date).toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
  }

  // ===== DOCUMENTS =====
  async function loadDocuments() {
    try {
      const res = await fetch(`/api/voices/docs.php?voice_id=${VOICE_ID}`, {
        credentials: 'include',
        headers: { 'X-CSRF-Token': window.CSRF_TOKEN }
      });
      
      if (!res.ok) {
        docsList.innerHTML = '<div class="p-4 text-center text-slate-400 text-sm">Error al cargar</div>';
        return;
      }
      
      const data = await res.json();
      const docs = data.documents || [];
      
      if (docs.length === 0) {
        docsList.innerHTML = '<div class="p-4 text-center text-slate-400 text-sm">Sin documentos</div>';
        return;
      }
      
      // Render documents
      docsList.innerHTML = docs.map(doc => {
        const sizeKb = (doc.size / 1024).toFixed(1);
        return `
          <button class="doc-item w-full p-3 bg-white/60 border border-slate-200/80 rounded-xl hover:border-rose-300 transition-smooth text-left group hover:shadow-md" data-doc-id="${escapeHtml(doc.id)}">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-lg bg-rose-100 flex items-center justify-center flex-shrink-0">
                <i class="iconoir-page text-lg text-rose-600"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-medium text-sm text-slate-800 group-hover:text-rose-600 transition-smooth">${escapeHtml(doc.name)}</div>
                <div class="text-xs text-slate-400 mt-0.5">${sizeKb} KB</div>
              </div>
              <i class="iconoir-eye text-slate-300 group-hover:text-rose-500 transition-smooth"></i>
            </div>
          </button>
        `;
      }).join('');
      
      // Bind click events
      docsList.querySelectorAll('.doc-item').forEach(btn => {
        const docId = btn.dataset.docId;
        btn.addEventListener('click', () => openDocViewer(docId));
      });
      
    } catch (e) {
      console.error('Error loading documents:', e);
      docsList.innerHTML = '<div class="p-4 text-center text-slate-400 text-sm">Error al cargar</div>';
    }
  }
  
  async function openDocViewer(docId) {
    docViewerModal?.classList.remove('hidden');
    docViewerTitle.textContent = 'Cargando...';
    docViewerContent.innerHTML = '<div class="text-center text-slate-400 py-8"><i class="iconoir-refresh animate-spin text-2xl mb-2"></i><p>Cargando documento...</p></div>';
    
    try {
      const res = await fetch(`/api/voices/doc.php?voice_id=${VOICE_ID}&doc_id=${encodeURIComponent(docId)}`, {
        credentials: 'include',
        headers: { 'X-CSRF-Token': window.CSRF_TOKEN }
      });
      
      if (!res.ok) {
        docViewerContent.innerHTML = '<div class="text-center text-red-600 py-8"><i class="iconoir-warning-circle text-2xl mb-2"></i><p>Error al cargar el documento</p></div>';
        return;
      }
      
      const data = await res.json();
      docViewerTitle.textContent = data.document.name;
      
      // Si es archivo binario (PDF), mostrar mensaje con opción de descarga
      if (data.document.isBinary) {
        const downloadUrl = `/api/voices/doc.php?voice_id=${VOICE_ID}&doc_id=${encodeURIComponent(docId)}&download=1`;
        docViewerContent.innerHTML = `
          <div class="text-center py-12 px-6">
            <i class="iconoir-page text-6xl text-gray-400 mb-4"></i>
            <h3 class="text-xl font-semibold mb-2">Archivo PDF</h3>
            <p class="text-gray-600 mb-4">${data.document.message}</p>
            <a href="${downloadUrl}" target="_blank" 
               class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium mb-4">
              <i class="iconoir-download"></i>
              <span>Abrir PDF en nueva ventana</span>
            </a>
            <div class="flex flex-col gap-2 max-w-md mx-auto mt-6">
              <p class="text-sm text-gray-500">💡 Puedes preguntarle a Lex sobre el contenido de este convenio y él te responderá con la información indexada.</p>
            </div>
          </div>
        `;
      } else {
        // Render markdown as HTML
        const html = mdToHtml(data.document.content);
        docViewerContent.innerHTML = `<div class="prose prose-slate max-w-none">${html}</div>`;
      }
      
    } catch (e) {
      console.error('Error loading document:', e);
      docViewerContent.innerHTML = '<div class="text-center text-red-600 py-8"><i class="iconoir-warning-circle text-2xl mb-2"></i><p>Error de conexión</p></div>';
    }
  }
  
  function closeDocViewer() {
    docViewerModal?.classList.add('hidden');
  }

  async function loadDocs() {
    try {
      const res = await fetch(`/api/voices/list_docs_ajax.php?voice_id=${VOICE_ID}`, {
        credentials: 'include'
      });
      if (!res.ok) return;
      
      const data = await res.json();
      if (data.success) {
        window.LEX_DOCS = data.documents; // Guardar en caché para búsqueda
        renderDocs(data.documents);
      }
    } catch (e) {
      console.error('Error loading docs:', e);
    }
  }

  function renderDocs(docs) {
    if (!docsList) return;
    
    if (docs.length === 0) {
      docsList.innerHTML = '<div class="text-center text-slate-400 py-8 text-sm">No se encontraron documentos</div>';
      return;
    }

    docsList.innerHTML = docs.map(doc => `
      <button class="doc-item w-full p-3 bg-white/50 hover:bg-white border border-slate-200/50 hover:border-rose-300 rounded-xl transition-smooth text-left group" data-doc-id="${doc.id}">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-rose-50 flex items-center justify-center text-rose-600 group-hover:bg-rose-100 transition-smooth">
            <i class="${doc.type === 'rag' ? 'iconoir-page' : 'iconoir-journal'}"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-slate-700 truncate group-hover:text-rose-600 transition-smooth">${doc.name}</div>
            <div class="text-[10px] text-slate-400 uppercase tracking-wider">${doc.type === 'rag' ? 'Convenio' : 'Referencia'}</div>
          </div>
        </div>
      </button>
    `).join('');

    // Re-attach listeners
    docsList.querySelectorAll('.doc-item').forEach(btn => {
      btn.addEventListener('click', () => openDocViewer(btn.dataset.docId));
    });
  }

  function filterDocs(query) {
    if (!window.LEX_DOCS) return;
    const q = query.toLowerCase().trim();
    const filtered = window.LEX_DOCS.filter(doc => 
      doc.name.toLowerCase().includes(q)
    );
    renderDocs(filtered);
  }

  // ===== INIT =====
  // Exponer visor de documentos para llamadas externas (p. ej., drawer móvil)
  window.lexOpenDocViewer = openDocViewer;

  async function init() {
    try {
      // Get user info
      const res = await fetch('/api/auth/me.php', { credentials: 'include' });
      if (res.status === 401) {
        window.location.href = '/login.php';
        return;
      }
      const data = await res.json();
      currentUser = data.user;
      
      // Load history and documents
      await loadHistory();
      await loadDocs();
      
      // Focus input
      chatInput?.focus();
    } catch (e) {
      console.error('Init error:', e);
    }
  }

  // ===== HISTORY =====
  async function loadHistory() {
    try {
      const res = await fetch(`/api/voices/history.php?voice_id=${VOICE_ID}`, {
        credentials: 'include',
        headers: { 'X-CSRF-Token': window.CSRF_TOKEN }
      });
      
      if (!res.ok) {
        historyList.innerHTML = '<div class="p-4 text-center text-slate-400 text-sm">Sin historial</div>';
        return;
      }
      
      const data = await res.json();
      const items = data.items || [];
      
      if (items.length === 0) {
        historyList.innerHTML = '<div class="p-4 text-center text-slate-400 text-sm">Sin consultas anteriores</div>';
        return;
      }
      
      historyList.innerHTML = items.map(item => `
        <div class="history-item w-full p-3 hover:bg-rose-50/50 border-b border-slate-100 transition-smooth cursor-pointer group flex items-start gap-2" data-id="${item.id}">
          <i class="iconoir-book text-rose-400 mt-0.5"></i>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-slate-700 truncate group-hover:text-rose-600">${escapeHtml(item.title)}</p>
            <span class="text-xs text-slate-400">${timeAgo(item.created_at)}</span>
          </div>
          <button class="history-delete opacity-0 group-hover:opacity-100 p-1 text-slate-300 hover:text-red-500 rounded transition-smooth" title="Eliminar">
            <i class="iconoir-trash text-sm"></i>
          </button>
        </div>
      `).join('');
      
      // Bind click events
      historyList.querySelectorAll('.history-item').forEach(el => {
        const id = el.dataset.id;
        el.addEventListener('click', (e) => {
          if (!e.target.closest('.history-delete')) {
            loadExecution(id);
          }
        });
      });
      
      historyList.querySelectorAll('.history-delete').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const id = btn.closest('.history-item').dataset.id;
          deleteExecution(id);
        });
      });
      
    } catch (e) {
      console.error('Error loading history:', e);
      historyList.innerHTML = '<div class="p-4 text-center text-slate-400 text-sm">Error al cargar</div>';
    }
  }

  async function loadExecution(id) {
    try {
      const res = await fetch(`/api/voices/get.php?id=${id}`, {
        credentials: 'include',
        headers: { 'X-CSRF-Token': window.CSRF_TOKEN }
      });
      
      if (!res.ok) return;
      
      const data = await res.json();
      currentExecutionId = data.id;
      
      // Restore messages from saved data
      const inputData = typeof data.input_data === 'string' ? JSON.parse(data.input_data) : data.input_data;
      messageHistory = inputData.history || [];
      
      // Show messages
      showChatMode();
      messagesEl.innerHTML = '';
      
      for (const msg of messageHistory) {
        appendMessage(msg.role, msg.content, false);
      }
      
    } catch (e) {
      console.error('Error loading execution:', e);
    }
  }

  async function deleteExecution(id) {
    if (!confirm('¿Eliminar esta consulta del historial?')) return;
    
    try {
      const res = await fetch('/api/voices/delete.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.CSRF_TOKEN
        },
        body: JSON.stringify({ id: Number(id) }),
        credentials: 'include'
      });
      
      if (res.ok) {
        if (currentExecutionId == id) {
          startNewChat();
        }
        loadHistory();
      }
    } catch (e) {
      console.error('Error deleting:', e);
    }
  }

  // ===== CHAT =====
  function showChatMode() {
    emptyState?.classList.add('hidden');
    messagesEl?.classList.remove('hidden');
  }

  function showEmptyState() {
    emptyState?.classList.remove('hidden');
    messagesEl?.classList.add('hidden');
    messagesEl.innerHTML = '';
  }

  function startNewChat() {
    currentExecutionId = null;
    messageHistory = [];
    showEmptyState();
  }

  function appendMessage(role, content, scroll = true) {
    const initials = currentUser ? `${currentUser.first_name[0]}${currentUser.last_name[0]}` : '?';
    const wrap = document.createElement('div');
    wrap.className = `flex gap-3 ${role === 'user' ? 'justify-end' : 'justify-start'}`;
    
    const avatar = role === 'user'
      ? `<div class="w-9 h-9 rounded-xl gradient-brand flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">${initials}</div>`
      : `<div class="w-9 h-9 rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">L</div>`;
    
    const bubbleClass = role === 'user'
      ? 'gradient-brand text-white rounded-2xl rounded-tr-sm'
      : 'glass border border-slate-200/50 text-slate-800 rounded-2xl rounded-tl-sm shadow-sm';
    
    const contentHtml = role === 'assistant' ? mdToHtml(content) : escapeHtml(content);
    
    wrap.innerHTML = role === 'user'
      ? `<div class="${bubbleClass} px-5 py-3.5 max-w-2xl">${contentHtml}</div>${avatar}`
      : `${avatar}<div class="${bubbleClass} px-5 py-3.5 max-w-2xl prose prose-sm">${contentHtml}</div>`;
    
    messagesEl.appendChild(wrap);
    
    if (scroll) {
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
  }

  async function sendMessage(text) {
    if (!text.trim()) return;
    
    showChatMode();
    appendMessage('user', text);
    messageHistory.push({ role: 'user', content: text });
    
    // Show typing
    typingIndicator?.classList.remove('hidden');
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    
    try {
      const res = await fetch('/api/voices/chat.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.CSRF_TOKEN
        },
        body: JSON.stringify({
          voice_id: VOICE_ID,
          message: text,
          history: messageHistory.slice(0, -1), // Sin el mensaje actual
          execution_id: currentExecutionId
        }),
        credentials: 'include'
      });
      
      typingIndicator?.classList.add('hidden');
      
      if (!res.ok) {
        const err = await res.json();
        appendMessage('assistant', 'Error: ' + (err.error?.message || 'No se pudo procesar la consulta'));
        return;
      }
      
      const data = await res.json();
      
      // Update execution ID
      if (data.execution_id) {
        currentExecutionId = data.execution_id;
      }
      
      // Add response
      const reply = data.reply || data.message?.content || 'Sin respuesta';
      messageHistory.push({ role: 'assistant', content: reply });
      appendMessage('assistant', reply);
      
      // Refresh history
      loadHistory();
      
    } catch (e) {
      typingIndicator?.classList.add('hidden');
      appendMessage('assistant', 'Error de conexión. Por favor, inténtalo de nuevo.');
    }
  }

  // ===== EVENT LISTENERS =====
  chatForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = chatInput.value.trim();
    if (!text) return;
    chatInput.value = '';
    await sendMessage(text);
  });

  newChatBtn?.addEventListener('click', startNewChat);

  // Docs search
  docsSearchInput?.addEventListener('input', (e) => {
    filterDocs(e.target.value);
  });

  // Suggestion buttons
  document.querySelectorAll('.suggestion-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const text = btn.querySelector('span').textContent;
      chatInput.value = text;
      chatForm.dispatchEvent(new Event('submit'));
    });
  });

  // Toggle docs panel
  toggleDocsBtn?.addEventListener('click', () => {
    docsPanel?.classList.toggle('hidden');
    if (docsPanel?.classList.contains('hidden')) {
      docsArrow.className = 'iconoir-nav-arrow-right text-xs';
    } else {
      docsArrow.className = 'iconoir-nav-arrow-left text-xs';
    }
  });

  // Close doc viewer
  closeDocViewerBtn?.addEventListener('click', closeDocViewer);
  closeDocViewerBtn2?.addEventListener('click', closeDocViewer);
  
  // Close modal on backdrop click
  docViewerModal?.addEventListener('click', (e) => {
    if (e.target === docViewerModal) {
      closeDocViewer();
    }
  });
  
  // Close modal on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !docViewerModal?.classList.contains('hidden')) {
      closeDocViewer();
    }
  });

  // ===== INIT =====
  init();
})();
