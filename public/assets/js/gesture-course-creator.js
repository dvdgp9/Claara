/**
 * Gesto: Creador de Cursos
 * Genera material formativo a partir de PDFs o texto
 */

(function() {
  'use strict';

  const GESTURE_TYPE = 'course-creator';

  // === DOM Elements ===
  const form = document.getElementById('course-form');
  const tabBtns = document.querySelectorAll('.tab-btn');
  const tabContents = document.querySelectorAll('.tab-content');
  const formatCards = document.querySelectorAll('.format-card');
  
  const sourcePdf = document.getElementById('source-pdf');
  const sourceText = document.getElementById('source-text');
  const pdfFilename = document.getElementById('pdf-filename');
  
  const generateBtn = document.getElementById('generate-btn');
  const generateBtnText = document.getElementById('generate-btn-text');
  const progressPanel = document.getElementById('progress-panel');
  const progressText = document.getElementById('progress-text');
  const progressDetail = document.getElementById('progress-detail');
  const errorPanel = document.getElementById('error-panel');
  const errorMessage = document.getElementById('error-message');
  const selectedCount = document.getElementById('selected-count');
  
  const inputSection = document.getElementById('input-section');
  const resultSection = document.getElementById('result-section');
  const resultTitle = document.getElementById('result-title');
  const resultSource = document.getElementById('result-source');
  const resultTabs = document.getElementById('result-tabs');
  const resultPanels = document.getElementById('result-panels');
  const copyAllBtn = document.getElementById('copy-all-btn');
  
  const historyList = document.getElementById('history-list');

  // === State ===
  let currentTab = 'pdf';
  let selectedFormats = new Set(['syllabus']);
  let pdfBase64 = null;
  let currentResults = null;

  // === Format config ===
  const formatConfig = {
    syllabus: { name: 'Temario', icon: 'iconoir-book-stack', color: 'from-emerald-500 to-teal-600' },
    content_cards: { name: 'Fichas', icon: 'iconoir-journal-page', color: 'from-blue-500 to-indigo-600' },
    quiz: { name: 'Autoevaluación', icon: 'iconoir-check-circle', color: 'from-amber-500 to-orange-600' },
    flashcards: { name: 'Microlearning', icon: 'iconoir-brain', color: 'from-purple-500 to-pink-600' },
    podcast: { name: 'Podcast', icon: 'iconoir-podcast', color: 'from-red-500 to-orange-500' },
    final_exam: { name: 'Examen Final', icon: 'iconoir-clipboard-check', color: 'from-slate-600 to-slate-800' }
  };

  // === Tab switching ===
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;
      currentTab = tab;
      
      tabBtns.forEach(b => {
        b.classList.remove('active', 'bg-emerald-100', 'text-emerald-700');
        b.classList.add('bg-slate-100', 'text-slate-600');
      });
      btn.classList.add('active', 'bg-emerald-100', 'text-emerald-700');
      btn.classList.remove('bg-slate-100', 'text-slate-600');
      
      tabContents.forEach(c => c.classList.add('hidden'));
      document.getElementById(`tab-${tab}`).classList.remove('hidden');
    });
  });

  // === Multiple format selection ===
  formatCards.forEach(card => {
    card.addEventListener('click', () => {
      const format = card.dataset.format;
      
      if (selectedFormats.has(format)) {
        if (selectedFormats.size > 1) {
          selectedFormats.delete(format);
          card.classList.remove('selected');
        }
      } else {
        selectedFormats.add(format);
        card.classList.add('selected');
      }
      
      updateSelectedCount();
    });
  });

  function updateSelectedCount() {
    const count = selectedFormats.size;
    selectedCount.textContent = `${count} formato${count !== 1 ? 's' : ''} seleccionado${count !== 1 ? 's' : ''}`;
    generateBtnText.textContent = count > 1 ? `Generar ${count} materiales` : 'Generar material del curso';
  }

  // === PDF handling ===
  if (sourcePdf) {
    sourcePdf.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      
      if (file.type !== 'application/pdf') {
        alert('Por favor, selecciona un archivo PDF');
        sourcePdf.value = '';
        return;
      }
      
      if (file.size > 20 * 1024 * 1024) {
        alert('El PDF es demasiado grande (máximo 20MB)');
        sourcePdf.value = '';
        return;
      }
      
      const reader = new FileReader();
      reader.onload = (ev) => {
        const base64 = ev.target.result.split(',')[1];
        pdfBase64 = base64;
        pdfFilename.querySelector('span').textContent = file.name;
        pdfFilename.classList.remove('hidden');
      };
      reader.readAsDataURL(file);
    });
  }

  // === Form submission ===
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    await generateContent();
  });

  async function generateContent() {
    const formats = Array.from(selectedFormats);
    
    // Obtener configuración
    const duration = document.querySelector('input[name="duration"]:checked')?.value || '8h';
    const level = document.querySelector('input[name="level"]:checked')?.value || 'intermedio';
    const courseFormat = document.querySelector('input[name="course_format"]:checked')?.value || 'online';
    
    let inputData = { 
      source_type: currentTab,
      output_formats: formats,
      duration: duration,
      level: level,
      course_format: courseFormat
    };
    
    switch (currentTab) {
      case 'text':
        const text = sourceText.value.trim();
        if (!text) {
          showError('Por favor, introduce el texto del material');
          return;
        }
        if (text.split(/\s+/).length < 50) {
          showError('El texto es demasiado corto (mínimo 50 palabras para generar un curso)');
          return;
        }
        inputData.text = text;
        break;
        
      case 'pdf':
      default:
        if (!pdfBase64) {
          showError('Por favor, selecciona un archivo PDF');
          return;
        }
        inputData.pdf_base64 = pdfBase64;
        break;
    }

    const formatCount = formats.length;
    showProgress(`Generando ${formatCount} material${formatCount > 1 ? 'es' : ''}...`, 'Extrayendo contenido y creando material formativo');
    hideError();

    try {
      const response = await fetch('/api/gestures/course-creator.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(inputData)
      });

      const data = await response.json();

      if (!data.success) {
        const errorMsg = data.error?.message || data.message || 'Error desconocido';
        throw new Error(errorMsg);
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
    generateBtn.disabled = true;
    generateBtn.classList.add('opacity-50', 'cursor-not-allowed');
  }

  function hideProgress() {
    progressPanel.classList.add('hidden');
    generateBtn.disabled = false;
    generateBtn.classList.remove('opacity-50', 'cursor-not-allowed');
  }

  function showError(msg) {
    errorPanel.classList.remove('hidden');
    errorMessage.textContent = msg;
  }

  function hideError() {
    errorPanel.classList.add('hidden');
  }

  // === Show results with tabs ===
  function showResults(data) {
    inputSection.classList.add('hidden');
    resultSection.classList.remove('hidden');
    
    resultTitle.textContent = data.title || 'Material del curso generado';
    
    const configLabels = {
      duration: data.config?.duration || '8h',
      level: { basico: 'Básico', intermedio: 'Intermedio', avanzado: 'Avanzado' }[data.config?.level] || 'Intermedio',
      format: { presencial: 'Presencial', online: 'Online', hibrido: 'Híbrido' }[data.config?.course_format] || 'Online'
    };
    resultSource.textContent = `${configLabels.duration} · ${configLabels.level} · ${configLabels.format} · ${data.total_generated} material${data.total_generated > 1 ? 'es' : ''} generado${data.total_generated > 1 ? 's' : ''}`;
    
    const formats = Object.keys(data.results);
    
    // Render tabs
    resultTabs.innerHTML = formats.map((format, i) => {
      const config = formatConfig[format] || { name: format, icon: 'iconoir-document' };
      return `
        <button class="result-tab ${i === 0 ? 'active' : ''}" data-format="${format}">
          <i class="${config.icon}"></i>
          ${config.name}
        </button>
      `;
    }).join('');
    
    // Render panels
    resultPanels.innerHTML = formats.map((format, i) => {
      const result = data.results[format];
      return `
        <div class="result-panel ${i === 0 ? 'active' : ''}" data-format="${format}">
          ${renderFormatPanel(format, result)}
        </div>
      `;
    }).join('');
    
    // Tab click handlers
    resultTabs.querySelectorAll('.result-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        const format = tab.dataset.format;
        
        resultTabs.querySelectorAll('.result-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        resultPanels.querySelectorAll('.result-panel').forEach(p => p.classList.remove('active'));
        resultPanels.querySelector(`.result-panel[data-format="${format}"]`)?.classList.add('active');
      });
    });
    
    // Copy buttons
    resultPanels.querySelectorAll('.copy-format-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const format = btn.dataset.format;
        const result = data.results[format];
        
        try {
          await navigator.clipboard.writeText(result.raw);
          btn.innerHTML = '<i class="iconoir-check"></i> Copiado';
          setTimeout(() => {
            btn.innerHTML = '<i class="iconoir-copy"></i> Copiar';
          }, 2000);
        } catch (err) {
          console.error('Error copying:', err);
        }
      });
    });
    
    // Download buttons
    resultPanels.querySelectorAll('.download-format-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const format = btn.dataset.format;
        const result = data.results[format];
        downloadFormat(format, result, data.title);
      });
    });
    
    // Preview/Raw toggle
    resultPanels.querySelectorAll('.preview-toggle button').forEach(btn => {
      btn.addEventListener('click', () => {
        const panel = btn.closest('.result-panel') || btn.closest('.glass-strong');
        const view = btn.dataset.view;
        const previewView = panel.querySelector('.preview-view');
        const rawView = panel.querySelector('.raw-view');
        
        btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        if (view === 'preview') {
          previewView?.classList.remove('hidden');
          rawView?.classList.add('hidden');
        } else {
          previewView?.classList.add('hidden');
          rawView?.classList.remove('hidden');
        }
      });
    });
  }

  function renderFormatPanel(format, result) {
    const config = formatConfig[format] || { name: format, icon: 'iconoir-document', color: 'bg-slate-600' };
    const previewHtml = renderMarkdownPreview(result.raw);
    
    return `
      <div class="glass-strong rounded-2xl border border-slate-200/50 overflow-hidden">
        <div class="p-4 border-b border-slate-200/50 bg-gradient-to-r from-slate-50 to-slate-100">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-gradient-to-br ${config.color} flex items-center justify-center text-white">
                <i class="${config.icon}"></i>
              </div>
              <div>
                <h3 class="font-semibold text-slate-800">${config.name}</h3>
                <p class="text-xs text-slate-500">Modelo: ${result.model || 'AI'}</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button class="copy-format-btn px-3 py-1.5 text-sm bg-white hover:bg-slate-50 border border-slate-200 rounded-lg transition-colors flex items-center gap-1.5" data-format="${format}">
                <i class="iconoir-copy"></i> Copiar
              </button>
              <button class="download-format-btn px-3 py-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors flex items-center gap-1.5" data-format="${format}">
                <i class="iconoir-download"></i> Descargar
              </button>
            </div>
          </div>
        </div>
        
        <div class="p-4">
          <div class="preview-toggle mb-4">
            <button class="active" data-view="preview">Vista previa</button>
            <button data-view="raw">Código</button>
          </div>
          <div class="preview-view content-preview">${previewHtml}</div>
          <div class="raw-view hidden">
            <div class="raw-preview"><pre>${escapeHtml(result.raw)}</pre></div>
          </div>
        </div>
      </div>
    `;
  }

  function renderMarkdownPreview(text) {
    if (!text) return '<p class="text-slate-400">Sin contenido</p>';
    
    // Escape HTML first
    let html = escapeHtml(text);
    
    // Headers
    html = html.replace(/^### (.*$)/gm, '<h3>$1</h3>');
    html = html.replace(/^## (.*$)/gm, '<h2>$1</h2>');
    html = html.replace(/^# (.*$)/gm, '<h1>$1</h1>');
    
    // Bold and italic
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
    
    // Lists
    html = html.replace(/^- (.*$)/gm, '<li>$1</li>');
    html = html.replace(/^(\d+)\. (.*$)/gm, '<li>$2</li>');
    
    // Tables (basic)
    html = html.replace(/\|(.+)\|/g, (match, content) => {
      const cells = content.split('|').map(c => c.trim());
      if (cells.every(c => /^[-:]+$/.test(c))) {
        return ''; // Skip separator rows
      }
      const cellHtml = cells.map(c => `<td>${c}</td>`).join('');
      return `<tr>${cellHtml}</tr>`;
    });
    
    // Wrap consecutive table rows
    html = html.replace(/(<tr>.*<\/tr>\n?)+/g, '<table>$&</table>');
    
    // Paragraphs (double newlines)
    html = html.replace(/\n\n/g, '</p><p>');
    html = '<p>' + html + '</p>';
    
    // Clean up empty paragraphs
    html = html.replace(/<p><\/p>/g, '');
    html = html.replace(/<p>(\s*<h[1-3]>)/g, '$1');
    html = html.replace(/(<\/h[1-3]>)\s*<\/p>/g, '$1');
    
    // Line breaks within paragraphs
    html = html.replace(/\n/g, '<br>');
    
    return html;
  }

  function downloadFormat(format, result, courseTitle) {
    const config = formatConfig[format] || { name: format };
    const filename = `${slugify(courseTitle || 'curso')}-${format}.md`;
    
    const blob = new Blob([result.raw], { type: 'text/markdown;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function resetUI() {
    resultSection.classList.add('hidden');
    inputSection.classList.remove('hidden');
    
    // Reset form
    if (sourceText) sourceText.value = '';
    if (sourcePdf) sourcePdf.value = '';
    pdfBase64 = null;
    if (pdfFilename) pdfFilename.classList.add('hidden');
    
    currentResults = null;
    hideError();
  }

  // === Copy All ===
  if (copyAllBtn) {
    copyAllBtn.addEventListener('click', async () => {
      if (!currentResults || !currentResults.results) return;
      
      let allContent = '';
      Object.entries(currentResults.results).forEach(([format, result]) => {
        const config = formatConfig[format] || { name: format };
        allContent += `${'='.repeat(50)}\n`;
        allContent += `${config.name.toUpperCase()}\n`;
        allContent += `${'='.repeat(50)}\n\n`;
        allContent += result.raw;
        allContent += '\n\n\n';
      });
      
      try {
        await navigator.clipboard.writeText(allContent.trim());
        copyAllBtn.innerHTML = '<i class="iconoir-check"></i> Copiado';
        setTimeout(() => {
          copyAllBtn.innerHTML = '<i class="iconoir-copy"></i> Copiar todo';
        }, 2000);
      } catch (err) {
        console.error('Error copying:', err);
      }
    });
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
        <div class="p-6 text-center">
          <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
            <i class="iconoir-graduation-cap text-xl text-emerald-400"></i>
          </div>
          <p class="text-sm text-slate-500">Aún no has creado cursos</p>
          <p class="text-xs text-slate-400 mt-1">Sube un PDF para empezar</p>
        </div>
      `;
      return;
    }

    const levelIcons = { basico: '🌱', intermedio: '🌿', avanzado: '🌳' };

    historyList.innerHTML = items.map(item => {
      const inputData = typeof item.input_data === 'string' ? JSON.parse(item.input_data) : item.input_data;
      const config = inputData?.config || {};
      const formatCount = (inputData?.output_formats || []).length;
      const date = new Date(item.created_at).toLocaleDateString('es-ES', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
      });
      const levelIcon = levelIcons[config.level] || '📚';

      return `
        <div class="history-item group border-b border-slate-100 last:border-0" data-id="${item.id}">
          <div class="history-item-main p-3 hover:bg-slate-50 cursor-pointer flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
              <i class="iconoir-graduation-cap text-emerald-600 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-slate-800 truncate">${escapeHtml(item.title || 'Sin título')}</p>
              <p class="text-xs text-slate-500 mt-0.5">
                ${levelIcon} ${config.duration || '8h'} · ${formatCount} material${formatCount !== 1 ? 'es' : ''} · ${date}
              </p>
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

  async function loadHistoryItem(id) {
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
        const inputData = typeof item.input_data === 'string'
          ? JSON.parse(item.input_data)
          : item.input_data;
        
        if (outputData?.results) {
          currentResults = {
            title: item.title,
            results: outputData.results,
            formats: outputData.formats,
            config: inputData?.config || outputData.config,
            total_generated: outputData.total_generated || Object.keys(outputData.results).length
          };
          showResults(currentResults);
        }
        
        // Mark as active
        historyList.querySelectorAll('.history-item').forEach(el => {
          el.classList.remove('bg-emerald-50');
        });
        historyList.querySelector(`.history-item[data-id="${id}"]`)?.classList.add('bg-emerald-50');
      }
    } catch (err) {
      console.error('Error loading history item:', err);
    }
  }

  async function deleteHistoryItem(id) {
    if (!confirm('¿Eliminar este curso del historial?')) return;
    
    try {
      const response = await fetch('/api/gestures/delete.php', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.CSRF_TOKEN
        },
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

  // === Utilities ===
  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function slugify(text) {
    return text
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '')
      .substring(0, 50);
  }

  // === Init ===
  loadHistory();

  // Export for external access
  window.courseCreator = { resetUI };
})();
