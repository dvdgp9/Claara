/**
 * Gesture: Transformador de Contenido (Content Repurposer)
 * Transforma contenido en diferentes formatos: posts sociales, blog, landing, newsletter, FAQs
 */

document.addEventListener('DOMContentLoaded', () => {
  // === DOM Elements ===
  const form = document.getElementById('repurposer-form');
  const tabBtns = document.querySelectorAll('.tab-btn');
  const tabContents = document.querySelectorAll('.tab-content');
  const formatCards = document.querySelectorAll('.format-card');
  
  const sourceUrl = document.getElementById('source-url');
  const sourceText = document.getElementById('source-text');
  const sourcePdf = document.getElementById('source-pdf');
  const pdfFilename = document.getElementById('pdf-filename');
  
  const generateBtn = document.getElementById('generate-btn');
  const progressPanel = document.getElementById('progress-panel');
  const progressText = document.getElementById('progress-text');
  const progressDetail = document.getElementById('progress-detail');
  const errorPanel = document.getElementById('error-panel');
  const errorMessage = document.getElementById('error-message');
  
  const inputSection = document.getElementById('input-section');
  const resultSection = document.getElementById('result-section');
  const resultFormatName = document.getElementById('result-format-name');
  const resultIcon = document.getElementById('result-icon');
  const resultTitle = document.getElementById('result-title');
  const resultSource = document.getElementById('result-source');
  const resultOutput = document.getElementById('result-output');
  const copyBtn = document.getElementById('copy-btn');
  const downloadBtn = document.getElementById('download-btn');
  const landingPreview = document.getElementById('landing-preview');
  const previewIframe = document.getElementById('preview-iframe');
  const togglePreview = document.getElementById('toggle-preview');
  
  const historyList = document.getElementById('history-list');
  
  // === State ===
  let currentTab = 'url';
  let currentFormat = 'instagram';
  let pdfBase64 = null;
  let currentResult = null;

  // === Format icons mapping ===
  const formatIcons = {
    instagram: 'iconoir-instagram',
    facebook: 'iconoir-facebook',
    linkedin: 'iconoir-linkedin',
    twitter: 'iconoir-x',
    blog: 'iconoir-post',
    landing: 'iconoir-code',
    newsletter: 'iconoir-mail',
    faq: 'iconoir-help-circle'
  };

  const formatNames = {
    instagram: 'Post Instagram',
    facebook: 'Post Facebook',
    linkedin: 'Post LinkedIn',
    twitter: 'Post X (Twitter)',
    blog: 'Entrada de blog',
    landing: 'Landing Page',
    newsletter: 'Newsletter',
    faq: 'Preguntas Frecuentes'
  };

  // === Tab switching ===
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;
      currentTab = tab;
      
      tabBtns.forEach(b => {
        b.classList.remove('active', 'bg-indigo-100', 'text-indigo-700');
        b.classList.add('bg-slate-100', 'text-slate-600');
      });
      btn.classList.add('active', 'bg-indigo-100', 'text-indigo-700');
      btn.classList.remove('bg-slate-100', 'text-slate-600');
      
      tabContents.forEach(c => c.classList.add('hidden'));
      document.getElementById(`tab-${tab}`).classList.remove('hidden');
    });
  });

  // === Format selection ===
  formatCards.forEach(card => {
    card.addEventListener('click', () => {
      const format = card.dataset.format;
      currentFormat = format;
      
      formatCards.forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
    });
  });

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
        pdfFilename.textContent = `Archivo: ${file.name}`;
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
    let inputData = { 
      source_type: currentTab,
      output_format: currentFormat,
      options: {}
    };
    
    switch (currentTab) {
      case 'url':
        const url = sourceUrl.value.trim();
        if (!url) {
          showError('Por favor, introduce una URL');
          return;
        }
        inputData.url = url;
        break;
        
      case 'text':
        const text = sourceText.value.trim();
        if (!text) {
          showError('Por favor, introduce el texto');
          return;
        }
        if (text.split(/\s+/).length < 20) {
          showError('El texto es demasiado corto (mínimo 20 palabras)');
          return;
        }
        inputData.text = text;
        break;
        
      case 'pdf':
        if (!pdfBase64) {
          showError('Por favor, selecciona un archivo PDF');
          return;
        }
        inputData.pdf_base64 = pdfBase64;
        break;
    }

    showProgress('Procesando contenido...', 'Extrayendo y transformando');
    hideError();

    try {
      const response = await fetch('/api/gestures/repurposer.php', {
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

      currentResult = data;
      showResult(data);
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

  function showResult(data) {
    inputSection.classList.add('hidden');
    resultSection.classList.remove('hidden');
    
    resultFormatName.textContent = data.format_name || formatNames[data.format];
    resultTitle.textContent = data.title || 'Contenido transformado';
    resultSource.textContent = `Fuente: ${data.source}`;
    resultOutput.textContent = data.output;
    
    // Update icon
    const iconClass = formatIcons[data.format] || 'iconoir-sparks';
    resultIcon.innerHTML = `<i class="${iconClass}"></i>`;
    
    // Show landing preview if applicable
    if (data.format === 'landing') {
      showLandingPreview(data.output);
    } else {
      landingPreview.classList.add('hidden');
    }
  }

  function showLandingPreview(htmlContent) {
    // Extract HTML between ---HTML--- and ---FIN_HTML---
    const match = htmlContent.match(/---HTML---\s*([\s\S]*?)\s*---FIN_HTML---/i);
    if (match && match[1]) {
      const html = match[1].trim();
      landingPreview.classList.remove('hidden');
      previewIframe.srcdoc = html;
    }
  }

  window.resetUI = function() {
    resultSection.classList.add('hidden');
    inputSection.classList.remove('hidden');
    landingPreview.classList.add('hidden');
    
    // Reset form
    sourceUrl.value = '';
    sourceText.value = '';
    sourcePdf.value = '';
    pdfBase64 = null;
    pdfFilename.classList.add('hidden');
    
    currentResult = null;
    hideError();
  };

  // === Copy and Download ===
  copyBtn.addEventListener('click', async () => {
    if (!currentResult) return;
    
    try {
      await navigator.clipboard.writeText(currentResult.output);
      const originalText = copyBtn.innerHTML;
      copyBtn.innerHTML = '<i class="iconoir-check"></i> Copiado';
      setTimeout(() => {
        copyBtn.innerHTML = originalText;
      }, 2000);
    } catch (err) {
      console.error('Error copying:', err);
    }
  });

  downloadBtn.addEventListener('click', () => {
    if (!currentResult) return;
    
    let filename, content, mimeType;
    
    if (currentResult.format === 'landing') {
      const match = currentResult.output.match(/---HTML---\s*([\s\S]*?)\s*---FIN_HTML---/i);
      content = match ? match[1].trim() : currentResult.output;
      filename = 'landing-page.html';
      mimeType = 'text/html';
    } else {
      content = currentResult.output;
      filename = `${currentResult.format}-content.txt`;
      mimeType = 'text/plain';
    }
    
    const blob = new Blob([content], { type: mimeType + ';charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  });

  // === Toggle preview ===
  if (togglePreview) {
    togglePreview.addEventListener('click', () => {
      const iframe = document.getElementById('preview-iframe');
      if (iframe.classList.contains('hidden')) {
        iframe.classList.remove('hidden');
        togglePreview.textContent = 'Ocultar';
      } else {
        iframe.classList.add('hidden');
        togglePreview.textContent = 'Mostrar';
      }
    });
  }

  // === History ===
  async function loadHistory() {
    try {
      const response = await fetch('/api/gestures/history.php?type=content-repurposer&limit=20', {
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
          Sin transformaciones todavía
        </div>
      `;
      return;
    }

    historyList.innerHTML = items.map(item => {
      const outputData = typeof item.output_data === 'string' 
        ? JSON.parse(item.output_data) 
        : item.output_data;
      const format = outputData?.format || 'unknown';
      const iconClass = formatIcons[format] || 'iconoir-sparks';
      const date = new Date(item.created_at).toLocaleDateString('es-ES', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
      });

      return `
        <div class="history-item group border-b border-slate-100 last:border-0" data-id="${item.id}">
          <div class="history-item-main p-3 hover:bg-slate-50 cursor-pointer flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
              <i class="${iconClass} text-indigo-600 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-slate-800 truncate">${escapeHtml(item.title || 'Sin título')}</p>
              <p class="text-xs text-slate-500 mt-0.5">${date}</p>
            </div>
            <button class="history-item-delete opacity-0 group-hover:opacity-100 p-1.5 hover:bg-red-50 rounded transition-all" title="Eliminar">
              <i class="iconoir-trash text-slate-400 hover:text-red-500 text-sm"></i>
            </button>
          </div>
        </div>
      `;
    }).join('');

    // Event listeners for history items
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
        
        currentResult = {
          title: item.title,
          output: item.output_content,
          format: outputData?.format || 'unknown',
          format_name: outputData?.format_name || 'Contenido',
          source: outputData?.original_title || 'Historial'
        };
        
        showResult(currentResult);
        
        // Mark as active in history
        historyList.querySelectorAll('.history-item').forEach(el => {
          el.classList.remove('active');
        });
        historyList.querySelector(`.history-item[data-id="${id}"]`)?.classList.add('active');
      }
    } catch (err) {
      console.error('Error loading history item:', err);
    }
  }

  async function deleteHistoryItem(id) {
    if (!confirm('¿Eliminar esta transformación del historial?')) return;
    
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
});
