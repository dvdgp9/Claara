/**
 * Gesture: Admin Proyectos (Análisis de Pliegos)
 * Analiza pliegos de concursos públicos para extraer gastos y horas
 */
(function() {
  'use strict';

  const GESTURE_TYPE = window.GESTURE_TYPE || 'project-admin';
  const CSRF_TOKEN = window.CSRF_TOKEN || '';

  // === DOM References ===
  const form = document.getElementById('project-admin-form');
  const dropzone = document.getElementById('dropzone');
  const fileInput = document.getElementById('file-input');
  const filesList = document.getElementById('files-list');
  const additionalInstructions = document.getElementById('additional-instructions');
  const analyzeBtn = document.getElementById('analyze-btn');
  const analyzeBtnText = document.getElementById('analyze-btn-text');
  const progressPanel = document.getElementById('progress-panel');
  const progressText = document.getElementById('progress-text');
  const progressDetail = document.getElementById('progress-detail');
  const errorPanel = document.getElementById('error-panel');
  const errorMessage = document.getElementById('error-message');
  const inputSection = document.getElementById('input-section');
  const resultSection = document.getElementById('result-section');
  const resultTitle = document.getElementById('result-title');
  const resultSource = document.getElementById('result-source');
  const resultPanels = document.getElementById('result-panels');
  const copyResultBtn = document.getElementById('copy-result-btn');
  const newAnalysisBtn = document.getElementById('new-analysis-btn');
  const historyList = document.getElementById('history-list');
  const selectedCount = document.getElementById('selected-count');

  // === State ===
  let uploadedFiles = []; // { file: File, base64: string, name: string }
  let selectedActions = new Set(['expenses']);
  let currentResults = null;

  // === Action Cards ===
  const actionCards = document.querySelectorAll('.action-card');
  actionCards.forEach(card => {
    card.addEventListener('click', () => {
      const action = card.dataset.action;
      if (selectedActions.has(action)) {
        if (selectedActions.size > 1) {
          selectedActions.delete(action);
          card.classList.remove('selected');
        }
      } else {
        selectedActions.add(action);
        card.classList.add('selected');
      }
      updateSelectedCount();
    });
  });

  function updateSelectedCount() {
    const count = selectedActions.size;
    selectedCount.textContent = `${count} análisis seleccionado${count !== 1 ? 's' : ''}`;
  }

  // === File Upload ===
  dropzone.addEventListener('click', () => fileInput.click());
  
  dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('dragover');
  });
  
  dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('dragover');
  });
  
  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    handleFiles(e.dataTransfer.files);
  });
  
  fileInput.addEventListener('change', (e) => {
    handleFiles(e.target.files);
  });

  async function handleFiles(files) {
    for (const file of files) {
      if (file.type !== 'application/pdf') {
        showError('Solo se permiten archivos PDF');
        continue;
      }
      
      if (file.size > 20 * 1024 * 1024) {
        showError(`${file.name} es demasiado grande (máximo 20MB)`);
        continue;
      }
      
      // Check if already added
      if (uploadedFiles.some(f => f.name === file.name)) {
        continue;
      }
      
      try {
        const base64 = await fileToBase64(file);
        uploadedFiles.push({ file, base64, name: file.name });
        renderFilesList();
        updateAnalyzeButton();
      } catch (err) {
        showError(`Error al procesar ${file.name}`);
      }
    }
  }

  function fileToBase64(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => {
        const base64 = reader.result.split(',')[1];
        resolve(base64);
      };
      reader.onerror = reject;
      reader.readAsDataURL(file);
    });
  }

  function renderFilesList() {
    if (uploadedFiles.length === 0) {
      filesList.classList.add('hidden');
      return;
    }
    
    filesList.classList.remove('hidden');
    filesList.innerHTML = uploadedFiles.map((f, idx) => `
      <div class="file-item flex items-center gap-3 p-3 bg-white rounded-lg border border-slate-200 shadow-sm">
        <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
          <i class="iconoir-page text-red-500"></i>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-slate-800 truncate">${escapeHtml(f.name)}</p>
          <p class="text-xs text-slate-500">${formatFileSize(f.file.size)}</p>
        </div>
        <button type="button" class="remove-file-btn p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" data-idx="${idx}">
          <i class="iconoir-xmark"></i>
        </button>
      </div>
    `).join('');
    
    // Add remove listeners
    filesList.querySelectorAll('.remove-file-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.idx);
        uploadedFiles.splice(idx, 1);
        renderFilesList();
        updateAnalyzeButton();
      });
    });
  }

  function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function updateAnalyzeButton() {
    analyzeBtn.disabled = uploadedFiles.length === 0;
  }

  // === Form Submission ===
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    await analyzeDocuments();
  });

  async function analyzeDocuments() {
    if (uploadedFiles.length === 0) {
      showError('Por favor, sube al menos un documento');
      return;
    }

    const actions = Array.from(selectedActions);
    const instructions = additionalInstructions.value.trim();

    showProgress('Analizando documentos...', `Procesando ${uploadedFiles.length} archivo${uploadedFiles.length > 1 ? 's' : ''}`);
    hideError();

    try {
      const response = await fetch('/api/gestures/admin-proyectos.php', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': CSRF_TOKEN
        },
        credentials: 'include',
        body: JSON.stringify({
          gesture_type: GESTURE_TYPE,
          files: uploadedFiles.map(f => ({
            name: f.name,
            data: f.base64,
            mime_type: 'application/pdf'
          })),
          actions: actions,
          instructions: instructions
        })
      });

      const data = await response.json();

      if (!data.success) {
        throw new Error(data.error?.message || data.message || 'Error desconocido');
      }

      currentResults = data;
      showResults(data);
      loadHistory();

    } catch (err) {
      showError(err.message);
    } finally {
      hideProgress();
    }
  }

  // === UI Functions ===
  function showProgress(text, detail) {
    progressPanel.classList.remove('hidden');
    progressText.textContent = text;
    progressDetail.textContent = detail;
    analyzeBtn.disabled = true;
  }

  function hideProgress() {
    progressPanel.classList.add('hidden');
    updateAnalyzeButton();
  }

  function showError(msg) {
    errorPanel.classList.remove('hidden');
    errorMessage.textContent = msg;
  }

  function hideError() {
    errorPanel.classList.add('hidden');
  }

  // === Show Results ===
  function showResults(data) {
    inputSection.classList.add('hidden');
    resultSection.classList.remove('hidden');
    
    resultTitle.textContent = data.title || 'Análisis completado';
    const fileNames = data.files?.map(f => f.name).join(', ') || 'Pliego';
    resultSource.textContent = `Archivos: ${fileNames}`;
    
    let html = '';
    
    if (data.results?.expenses) {
      html += renderExpensesResult(data.results.expenses);
    }
    
    if (data.results?.hours) {
      html += renderHoursResult(data.results.hours);
    }
    
    resultPanels.innerHTML = html;
  }

  function renderExpensesResult(expenses) {
    return `
      <div class="glass-strong rounded-2xl border border-slate-200/50 overflow-hidden">
        <div class="p-4 border-b border-slate-200/50 bg-gradient-to-r from-emerald-50 to-teal-50">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-500 flex items-center justify-center text-white">
              <i class="iconoir-wallet"></i>
            </div>
            <div>
              <h3 class="font-semibold text-slate-800">Gastos no personales</h3>
              <p class="text-xs text-slate-500">Equipamiento, materiales, licencias, etc.</p>
            </div>
          </div>
        </div>
        <div class="p-4">
          <div class="prose prose-sm max-w-none">
            ${renderMarkdown(expenses.content || expenses)}
          </div>
        </div>
      </div>
    `;
  }

  function renderHoursResult(hours) {
    return `
      <div class="glass-strong rounded-2xl border border-slate-200/50 overflow-hidden">
        <div class="p-4 border-b border-slate-200/50 bg-gradient-to-r from-blue-50 to-indigo-50">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-500 flex items-center justify-center text-white">
              <i class="iconoir-clock"></i>
            </div>
            <div>
              <h3 class="font-semibold text-slate-800">Conteo de horas</h3>
              <p class="text-xs text-slate-500">Horas de servicio, formación, coordinación, etc.</p>
            </div>
          </div>
        </div>
        <div class="p-4">
          <div class="prose prose-sm max-w-none">
            ${renderMarkdown(hours.content || hours)}
          </div>
        </div>
      </div>
    `;
  }

  function renderMarkdown(text) {
    if (!text) return '';
    if (typeof text !== 'string') text = JSON.stringify(text, null, 2);
    
    // Procesar tablas primero (antes de otros reemplazos)
    text = processMarkdownTables(text);
    
    // Procesar el resto del markdown
    let html = text
      // Horizontal rules
      .replace(/^---$/gm, '<hr class="my-6 border-slate-200">')
      // Headers con emojis (mantener emojis)
      .replace(/^#### (.*$)/gm, '<h4 class="text-base font-semibold text-slate-700 mt-5 mb-2 flex items-center gap-2">$1</h4>')
      .replace(/^### (.*$)/gm, '<h3 class="text-lg font-semibold text-slate-800 mt-5 mb-3 flex items-center gap-2">$1</h3>')
      .replace(/^## (.*$)/gm, '<h2 class="text-xl font-bold text-slate-800 mt-6 mb-3 pb-2 border-b border-slate-200 flex items-center gap-2">$1</h2>')
      .replace(/^# (.*$)/gm, '<h1 class="text-2xl font-bold text-slate-900 mt-6 mb-4">$1</h1>')
      // Bold and italic
      .replace(/\*\*\*(.*?)\*\*\*/g, '<strong class="text-slate-900"><em>$1</em></strong>')
      .replace(/\*\*(.*?)\*\*/g, '<strong class="text-slate-900">$1</strong>')
      .replace(/\*(.*?)\*/g, '<em class="text-slate-600">$1</em>')
      // Inline code
      .replace(/`([^`]+)`/g, '<code class="px-1.5 py-0.5 bg-slate-100 text-slate-700 rounded text-sm font-mono">$1</code>')
      // Lists con indentación
      .replace(/^  - (.*$)/gm, '<li class="ml-8 text-slate-600 text-sm list-disc">$1</li>')
      .replace(/^- (.*$)/gm, '<li class="ml-4 text-slate-700 list-disc">$1</li>')
      .replace(/^\d+\. (.*$)/gm, '<li class="ml-4 text-slate-700 list-decimal">$1</li>')
      // Párrafos
      .replace(/\n\n/g, '</p><p class="text-slate-700 mb-3 leading-relaxed">')
      .replace(/\n/g, '<br>');
    
    // Envolver en párrafo si no empieza con elemento de bloque
    if (!html.match(/^<(h[1-6]|ul|ol|table|div|hr)/)) {
      html = '<p class="text-slate-700 mb-3 leading-relaxed">' + html + '</p>';
    }
    
    // Agrupar <li> consecutivos en <ul>
    html = html.replace(/(<li class="ml-4[^"]*list-disc">[^<]*<\/li>(\s*<br>)?)+/g, (match) => {
      const items = match.replace(/<br>/g, '');
      return `<ul class="my-3 space-y-1">${items}</ul>`;
    });
    
    html = html.replace(/(<li class="ml-8[^"]*">[^<]*<\/li>(\s*<br>)?)+/g, (match) => {
      const items = match.replace(/<br>/g, '');
      return `<ul class="my-2 space-y-0.5">${items}</ul>`;
    });
    
    // Limpiar <br> innecesarios después de elementos de bloque
    html = html.replace(/<\/(h[1-6]|table|ul|ol|hr)><br>/g, '</$1>');
    html = html.replace(/<br><(h[1-6]|table|ul|ol|hr)/g, '<$1');
    
    return html;
  }

  function processMarkdownTables(text) {
    const lines = text.split('\n');
    let result = [];
    let inTable = false;
    let tableLines = [];
    
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i].trim();
      
      // Detectar inicio de tabla
      if (line.startsWith('|') && line.endsWith('|')) {
        if (!inTable) {
          inTable = true;
          tableLines = [];
        }
        tableLines.push(line);
      } else {
        // Si estábamos en tabla, renderizarla
        if (inTable && tableLines.length > 0) {
          result.push(renderTable(tableLines));
          inTable = false;
          tableLines = [];
        }
        result.push(lines[i]);
      }
    }
    
    // Cerrar tabla si quedó abierta
    if (inTable && tableLines.length > 0) {
      result.push(renderTable(tableLines));
    }
    
    return result.join('\n');
  }

  function renderTable(lines) {
    if (lines.length < 2) return lines.join('\n');
    
    let html = '<div class="overflow-x-auto my-4"><table class="w-full text-sm border-collapse bg-white rounded-lg overflow-hidden shadow-sm border border-slate-200">';
    
    // Primera fila = header
    const headerCells = lines[0].split('|').filter(c => c.trim());
    html += '<thead class="bg-gradient-to-r from-slate-50 to-slate-100"><tr>';
    headerCells.forEach(cell => {
      html += `<th class="px-4 py-3 text-left font-semibold text-slate-700 border-b border-slate-200">${cell.trim()}</th>`;
    });
    html += '</tr></thead>';
    
    // Resto = body (saltar línea de separación ---)
    html += '<tbody>';
    for (let i = 1; i < lines.length; i++) {
      const line = lines[i].trim();
      // Saltar línea separadora
      if (line.match(/^\|[\s\-:]+\|$/)) continue;
      
      const cells = line.split('|').filter(c => c.trim());
      const isLastRow = i === lines.length - 1;
      const hasTotal = cells.some(c => c.toLowerCase().includes('total'));
      
      const rowClass = hasTotal 
        ? 'bg-gradient-to-r from-emerald-50 to-teal-50 font-semibold' 
        : (i % 2 === 0 ? 'bg-white' : 'bg-slate-50/50');
      
      html += `<tr class="${rowClass} hover:bg-slate-100/50 transition-colors">`;
      cells.forEach((cell, idx) => {
        const cellContent = cell.trim();
        const isNumeric = /^[\d.,€%\s]+$/.test(cellContent) || cellContent.includes('€');
        const alignment = isNumeric && idx > 0 ? 'text-right' : 'text-left';
        const borderClass = isLastRow ? '' : 'border-b border-slate-100';
        html += `<td class="px-4 py-3 ${alignment} ${borderClass} text-slate-700">${cellContent}</td>`;
      });
      html += '</tr>';
    }
    html += '</tbody></table></div>';
    
    return html;
  }

  // === Copy Results ===
  copyResultBtn?.addEventListener('click', async () => {
    if (!currentResults) return;
    
    let textToCopy = '';
    
    if (currentResults.results?.expenses) {
      textToCopy += '=== GASTOS NO PERSONALES ===\n\n';
      textToCopy += currentResults.results.expenses.content || currentResults.results.expenses;
      textToCopy += '\n\n';
    }
    
    if (currentResults.results?.hours) {
      textToCopy += '=== CONTEO DE HORAS ===\n\n';
      textToCopy += currentResults.results.hours.content || currentResults.results.hours;
    }
    
    try {
      await navigator.clipboard.writeText(textToCopy.trim());
      copyResultBtn.innerHTML = '<i class="iconoir-check"></i> Copiado';
      setTimeout(() => {
        copyResultBtn.innerHTML = '<i class="iconoir-copy"></i> Copiar';
      }, 2000);
    } catch (err) {
      console.error('Error copying:', err);
    }
  });

  // === New Analysis ===
  newAnalysisBtn?.addEventListener('click', resetUI);

  function resetUI() {
    resultSection.classList.add('hidden');
    inputSection.classList.remove('hidden');
    
    uploadedFiles = [];
    renderFilesList();
    additionalInstructions.value = '';
    currentResults = null;
    hideError();
    updateAnalyzeButton();
    
    // Reset file input
    fileInput.value = '';
  }

  // === History ===
  async function loadHistory() {
    try {
      const response = await fetch(`/api/gestures/history.php?type=${GESTURE_TYPE}&limit=20`, {
        credentials: 'include'
      });
      const data = await response.json();
      
      if (data.items) {
        renderHistory(data.items);
      }
    } catch (err) {
      console.error('Error loading history:', err);
    }
  }

  function renderHistory(items) {
    if (!items || items.length === 0) {
      historyList.innerHTML = `
        <div class="p-4 text-center text-slate-400 text-sm">
          <i class="iconoir-archive text-2xl mb-2 block"></i>
          Sin análisis todavía
        </div>
      `;
      return;
    }

    historyList.innerHTML = items.map(item => {
      const date = new Date(item.created_at).toLocaleDateString('es-ES', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
      });

      return `
        <div class="history-item group border-b border-slate-100 last:border-0" data-id="${item.id}">
          <div class="history-item-main p-3 hover:bg-slate-50 cursor-pointer flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
              <i class="iconoir-folder-settings text-emerald-600 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-slate-800 truncate">${escapeHtml(item.title || 'Análisis')}</p>
              <p class="text-xs text-slate-500 mt-0.5">${date}</p>
            </div>
            <button class="history-item-delete opacity-0 group-hover:opacity-100 p-1.5 hover:bg-red-50 rounded transition-all" title="Eliminar">
              <i class="iconoir-trash text-slate-400 hover:text-red-500 text-sm"></i>
            </button>
          </div>
        </div>
      `;
    }).join('');

    // Event listeners
    historyList.querySelectorAll('.history-item-main').forEach(el => {
      el.addEventListener('click', () => {
        const id = el.closest('.history-item').dataset.id;
        loadHistoryItem(id);
      });
    });

    historyList.querySelectorAll('.history-item-delete').forEach(el => {
      el.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = el.closest('.history-item').dataset.id;
        deleteHistoryItem(id);
      });
    });
  }

  window.loadHistoryItem = async function(id) {
    try {
      const response = await fetch(`/api/gestures/get.php?id=${id}`, {
        credentials: 'include'
      });
      const data = await response.json();
      
      if (data.execution) {
        const item = data.execution;
        const outputData = typeof item.output_data === 'string' 
          ? JSON.parse(item.output_data) 
          : item.output_data;
        
        currentResults = {
          title: item.title,
          results: outputData?.results || {},
          files: outputData?.files || []
        };
        
        showResults(currentResults);
        
        // Mark as active
        historyList.querySelectorAll('.history-item').forEach(el => {
          el.classList.remove('active');
        });
        historyList.querySelector(`.history-item[data-id="${id}"]`)?.classList.add('active');
      }
    } catch (err) {
      console.error('Error loading history item:', err);
    }
  };

  async function deleteHistoryItem(id) {
    if (!confirm('¿Eliminar este análisis del historial?')) return;
    
    try {
      const response = await fetch('/api/gestures/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ id })
      });
      
      const data = await response.json();
      if (data.success) {
        loadHistory();
      }
    } catch (err) {
      console.error('Error deleting:', err);
    }
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // === Init ===
  loadHistory();
  updateAnalyzeButton();
})();
