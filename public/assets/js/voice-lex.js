/**
 * Voice: Lex - Legal Assistant
 * Handles the specialized legal-context chat.
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
    
    // Blockquotes before other elements.
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
    
    // Lists
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
    
    // Line breaks, but not inside lists.
    s = s.replace(/\n(?!<\/?(ul|li|h[1-3]|blockquote)>)/g, '<br>');
    
    return s;
  }

  function timeAgo(date) {
    const now = new Date();
    const diff = Math.floor((now - new Date(date)) / 1000);
    if (diff < 60) return 'now';
    if (diff < 3600) return `${Math.floor(diff/60)} min ago`;
    if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
    return new Date(date).toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
  }

  // ===== DOCUMENTS =====
  async function loadDocuments() {
    try {
      const res = await fetch(`/api/voices/docs.php?voice_id=${VOICE_ID}`, {
        credentials: 'include',
        headers: { 'X-CSRF-Token': window.CSRF_TOKEN }
      });
      
      if (!res.ok) {
        docsList.innerHTML = '<div class="p-4 text-center text-slate-400 text-sm">Error loading</div>';
        return;
      }
      
      const data = await res.json();
      const docs = data.documents || [];
      
      if (docs.length === 0) {
        docsList.innerHTML = '<div class="p-4 text-center text-slate-400 text-sm">No documents</div>';
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
      docsList.innerHTML = '<div class="p-4 text-center text-slate-400 text-sm">Error loading</div>';
    }
  }
  
  async function openDocViewer(docId) {
    docViewerModal?.classList.remove('hidden');
    docViewerTitle.textContent = 'Loading...';
    docViewerContent.innerHTML = '<div class="text-center text-slate-400 py-8"><i class="iconoir-refresh animate-spin text-2xl mb-2"></i><p>Loading document...</p></div>';
    
    try {
      const res = await fetch(`/api/voices/doc.php?voice_id=${VOICE_ID}&doc_id=${encodeURIComponent(docId)}`, {
        credentials: 'include',
        headers: { 'X-CSRF-Token': window.CSRF_TOKEN }
      });
      
      if (!res.ok) {
        docViewerContent.innerHTML = '<div class="text-center text-red-600 py-8"><i class="iconoir-warning-circle text-2xl mb-2"></i><p>Error loading document</p></div>';
        return;
      }
      
      const data = await res.json();
      docViewerTitle.textContent = data.document.name;
      
      // If this is a binary file (PDF), show a message with download/open option.
      if (data.document.isBinary) {
        const downloadUrl = `/api/voices/doc.php?voice_id=${VOICE_ID}&doc_id=${encodeURIComponent(docId)}&download=1`;
        docViewerContent.innerHTML = `
          <div class="text-center py-12 px-6">
            <i class="iconoir-page text-6xl text-gray-400 mb-4"></i>
            <h3 class="text-xl font-semibold mb-2">PDF file</h3>
            <p class="text-gray-600 mb-4">${data.document.message}</p>
            <a href="${downloadUrl}" target="_blank" 
               class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium mb-4">
              <i class="iconoir-download"></i>
              <span>Open PDF in a new window</span>
            </a>
            <div class="flex flex-col gap-2 max-w-md mx-auto mt-6">
              <p class="text-sm text-gray-500">You can ask Lex about this document and it will answer using the indexed information.</p>
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
      docViewerContent.innerHTML = '<div class="text-center text-red-600 py-8"><i class="iconoir-warning-circle text-2xl mb-2"></i><p>Connection error</p></div>';
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
        window.LEX_DOCS = data.documents; // Cache for search.
        renderDocs(data.documents);
      }
    } catch (e) {
      console.error('Error loading docs:', e);
    }
  }

  function renderDocs(docs) {
    if (!docsList) return;
    
    if (docs.length === 0) {
      docsList.innerHTML = '<div class="text-center text-slate-400 py-8 text-sm">No documents found</div>';
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
            <div class="text-[10px] text-slate-400 uppercase tracking-wider">${doc.type === 'rag' ? 'Agreement' : 'Reference'}</div>
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
  // Expose document viewer for external calls, such as the mobile drawer.
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
        historyList.innerHTML = '<div class="p-4 text-center text-slate-400 text-sm">No history</div>';
        return;
      }
      
      const data = await res.json();
      const items = data.items || [];
      
      if (items.length === 0) {
        historyList.innerHTML = '<div class="p-4 text-center text-slate-400 text-sm">No previous queries</div>';
        return;
      }
      
      historyList.innerHTML = items.map(item => `
        <div class="history-item w-full p-3 hover:bg-rose-50/50 border-b border-slate-100 transition-smooth cursor-pointer group flex items-start gap-2" data-id="${item.id}">
          <i class="iconoir-book text-rose-400 mt-0.5"></i>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-slate-700 truncate group-hover:text-rose-600">${escapeHtml(item.title)}</p>
            <span class="text-xs text-slate-400">${timeAgo(item.created_at)}</span>
          </div>
          <button class="history-delete opacity-0 group-hover:opacity-100 p-1 text-slate-300 hover:text-red-500 rounded transition-smooth" title="Delete">
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
      historyList.innerHTML = '<div class="p-4 text-center text-slate-400 text-sm">Error loading</div>';
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
        appendMessage(msg.role, msg.content, false, msg.meta || null);
      }
      
    } catch (e) {
      console.error('Error loading execution:', e);
    }
  }

  async function deleteExecution(id) {
    if (!confirm('Delete this query from history?')) return;
    
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

  function renderMeta(meta) {
    if (!meta || typeof meta !== 'object') return '';

    const parts = [];

    // Source-match badge (objective: how well docs matched the question).
    const sm = meta.source_match;
    if (sm && typeof sm.percent === 'number') {
      const bandStyles = {
        high: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        medium: 'bg-amber-50 text-amber-700 border-amber-200',
        low: 'bg-rose-50 text-rose-700 border-rose-200'
      };
      const bandLabel = { high: 'High', medium: 'Medium', low: 'Low' };
      const cls = bandStyles[sm.band] || bandStyles.low;
      const label = bandLabel[sm.band] || '';
      const tip = 'Indicates how much of the answer is backed by retrieved source text. It is not a guarantee of factual accuracy.';
      parts.push(
        `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs font-medium ${cls}" title="${escapeHtml(tip)}">` +
        `<i class="iconoir-database-check text-[13px]"></i>Evidence match: ${sm.percent}%${label ? ' · ' + label : ''}</span>`
      );
    }

    // Sources used (from the model).
    if (Array.isArray(meta.sources) && meta.sources.length) {
      const chips = meta.sources.map(s =>
        `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200 text-xs">` +
        `<i class="iconoir-page text-[13px]"></i>${escapeHtml(s)}</span>`
      ).join('');
      parts.push(chips);
    }

    let html = '';
    if (parts.length) {
      html += `<div class="flex flex-wrap items-center gap-1.5 mt-2">${parts.join('')}</div>`;
    }

    const conflictSummary = meta.conflict_summary;
    if (conflictSummary && Array.isArray(conflictSummary.positions) && conflictSummary.positions.length > 1) {
      const topic = conflictSummary.topic ? ` · ${escapeHtml(conflictSummary.topic)}` : '';
      const docCount = Number.isFinite(conflictSummary.documents_considered)
        ? `${conflictSummary.documents_considered} docs considered` : 'Multiple docs considered';
      const positions = conflictSummary.positions.map(p => {
        const count = Number.isFinite(p.document_count) ? `${p.document_count} doc${p.document_count === 1 ? '' : 's'}` : '';
        const srcs = Array.isArray(p.sources) && p.sources.length
          ? `<div class="mt-0.5 text-amber-600">${p.sources.map(escapeHtml).join(', ')}</div>` : '';
        return `<li><span class="font-medium">${escapeHtml(p.claim || '')}</span>${count ? ` <span class="text-amber-600">(${count})</span>` : ''}${srcs}</li>`;
      }).join('');
      const recent = conflictSummary.most_recent && conflictSummary.most_recent.claim
        ? `<div class="mt-2 text-amber-700"><span class="font-medium">Most recent:</span> ${escapeHtml(conflictSummary.most_recent.claim)}${conflictSummary.most_recent.date ? ` <span class="text-amber-600">(${escapeHtml(conflictSummary.most_recent.date)})</span>` : ''}</div>` : '';
      const official = conflictSummary.official_source_note
        ? `<div class="mt-1 text-amber-700">${escapeHtml(conflictSummary.official_source_note)}</div>` : '';
      html += `<div class="mt-2 px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs">` +
        `<div class="flex items-center gap-1 font-semibold mb-1"><i class="iconoir-warning-triangle"></i>Potential source conflict<span class="font-normal">${topic}</span></div>` +
        `<div class="mb-1 text-amber-700">${escapeHtml(docCount)}</div>` +
        `<ul class="list-disc pl-4 space-y-1">${positions}</ul>${recent}${official}</div>`;
    } else if (Array.isArray(meta.conflicts) && meta.conflicts.length) {
      const items = meta.conflicts.map(c => {
        const srcs = Array.isArray(c.sources) && c.sources.length
          ? ` <span class="text-amber-600">(${c.sources.map(escapeHtml).join(' vs ')})</span>` : '';
        const topic = c.topic ? `<strong>${escapeHtml(c.topic)}:</strong> ` : '';
        return `<li>${topic}${escapeHtml(c.note || '')}${srcs}</li>`;
      }).join('');
      html += `<div class="mt-2 px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs">` +
        `<div class="flex items-center gap-1 font-semibold mb-1"><i class="iconoir-warning-triangle"></i>Sources disagree</div>` +
        `<ul class="list-disc pl-4 space-y-0.5">${items}</ul></div>`;
    }

    return html;
  }

  function appendMessage(role, content, scroll = true, meta = null) {
    const initials = currentUser ? `${currentUser.first_name[0]}${currentUser.last_name[0]}` : '?';
    const wrap = document.createElement('div');
    wrap.className = `flex gap-3 ${role === 'user' ? 'justify-end' : 'justify-start'}`;
    
    const avatar = role === 'user'
      ? `<div class="w-9 h-9 rounded-xl gradient-brand flex items-center justify-center text-[#2F3440] text-sm font-semibold flex-shrink-0">${initials}</div>`
      : `<div class="w-9 h-9 rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">L</div>`;
    
    const bubbleClass = role === 'user'
      ? 'gradient-brand text-white rounded-2xl rounded-tr-sm'
      : 'glass border border-slate-200/50 text-slate-800 rounded-2xl rounded-tl-sm shadow-sm';
    
    const contentHtml = role === 'assistant' ? mdToHtml(content) : escapeHtml(content);
    const metaHtml = role === 'assistant' ? renderMeta(meta) : '';

    wrap.innerHTML = role === 'user'
      ? `<div class="${bubbleClass} px-5 py-3.5 max-w-2xl">${contentHtml}</div>${avatar}`
      : `${avatar}<div class="${bubbleClass} px-5 py-3.5 max-w-2xl prose prose-sm">${contentHtml}${metaHtml}</div>`;
    
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
          history: messageHistory.slice(0, -1), // Without the current message.
          execution_id: currentExecutionId
        }),
        credentials: 'include'
      });
      
      typingIndicator?.classList.add('hidden');
      
      if (!res.ok) {
        const err = await res.json();
        appendMessage('assistant', 'Error: ' + (err.error?.message || 'Could not process the query'));
        return;
      }
      
      const data = await res.json();
      
      // Update execution ID
      if (data.execution_id) {
        currentExecutionId = data.execution_id;
      }
      
      // Add response
      const reply = data.reply || data.message?.content || 'No response';
      const meta = data.meta || null;
      messageHistory.push({ role: 'assistant', content: reply, meta });
      appendMessage('assistant', reply, true, meta);
      
      // Refresh history
      loadHistory();
      
    } catch (e) {
      typingIndicator?.classList.add('hidden');
      appendMessage('assistant', 'Connection error. Please try again.');
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
