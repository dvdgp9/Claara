/**
 * Gesture: Podcast desde artículo
 * Convierte artículos en podcasts con dos voces usando Gemini TTS
 * Soporta generación en background con polling
 */
(function() {
  'use strict';

  const GESTURE_TYPE = 'podcast-from-article';
  const POLL_INTERVAL_INITIAL = 3000; // 3s al inicio
  const POLL_INTERVAL_LONG = 8000;    // 8s después de 30s
  const POLL_TIMEOUT = 300000;        // 5 min máximo

  // === DOM Elements ===
  const podcastForm = document.getElementById('podcast-form');
  const tabBtns = document.querySelectorAll('.tab-btn');
  const tabContents = document.querySelectorAll('.tab-content');
  const articleUrl = document.getElementById('article-url');
  const articleText = document.getElementById('article-text');
  const articlePdf = document.getElementById('article-pdf');
  const pdfFilename = document.getElementById('pdf-filename');
  const generateBtn = document.getElementById('generate-btn');
  
  const progressPanel = document.getElementById('progress-panel');
  const errorPanel = document.getElementById('error-panel');
  const podcastInputSection = document.getElementById('podcast-input-section');
  const podcastResult = document.getElementById('podcast-result');
  
  const progressText = document.getElementById('progress-text');
  const progressDetail = document.getElementById('progress-detail');
  const errorMessage = document.getElementById('error-message');
  
  const audioPlayer = document.getElementById('audio-player');
  const podcastTitle = document.getElementById('podcast-title');
  const podcastSummary = document.getElementById('podcast-summary');
  const podcastScript = document.getElementById('podcast-script');
  const downloadBtn = document.getElementById('download-btn');
  const cancelBtn = document.getElementById('cancel-btn');
  
  const historyList = document.getElementById('history-list');
  const newPodcastBtn = document.getElementById('new-podcast-btn');

  let currentTab = 'url';
  let pdfBase64 = null;
  let lastAudioBlob = null;
  let lastAudioUrl = '';
  let lastTitle = '';
  
  // Background job state
  let currentJobId = null;
  let pollTimer = null;
  let pollStartTime = null;

  // === Tab switching ===
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;
      currentTab = tab;
      
      tabBtns.forEach(b => {
        b.classList.remove('bg-orange-100', 'text-orange-700', 'active');
        b.classList.add('bg-slate-100', 'text-slate-600');
      });
      btn.classList.remove('bg-slate-100', 'text-slate-600');
      btn.classList.add('bg-orange-100', 'text-orange-700', 'active');
      
      tabContents.forEach(content => content.classList.add('hidden'));
      document.getElementById(`tab-${tab}`).classList.remove('hidden');
    });
  });

  // === PDF file handling ===
  if (articlePdf) {
    articlePdf.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      
      if (file.type !== 'application/pdf') {
        alert('Por favor, selecciona un archivo PDF');
        return;
      }
      
      const reader = new FileReader();
      reader.onload = (event) => {
        pdfBase64 = event.target.result.split(',')[1];
        pdfFilename.textContent = `📄 ${file.name}`;
        pdfFilename.classList.remove('hidden');
      };
      reader.readAsDataURL(file);
    });
  }

  // === Form submit ===
  if (podcastForm) {
    podcastForm.addEventListener('submit', (e) => {
      e.preventDefault();
      generatePodcast();
    });
  }

  // === Generate podcast (background job) ===
  async function generatePodcast() {
    let sourceType = currentTab;
    let inputData = { source_type: sourceType };
    
    switch (sourceType) {
      case 'url':
        const url = articleUrl.value.trim();
        if (!url) {
          alert('Por favor, introduce una URL');
          return;
        }
        inputData.url = url;
        break;
        
      case 'text':
        const text = articleText.value.trim();
        if (!text) {
          alert('Por favor, introduce el texto del artículo');
          return;
        }
        inputData.text = text;
        break;
        
      case 'pdf':
        if (!pdfBase64) {
          alert('Por favor, selecciona un archivo PDF');
          return;
        }
        inputData.pdf_base64 = pdfBase64;
        break;
    }

    showProgress();
    updateProgress('Creando tarea...', 'Preparando generación del podcast');

    try {
      // Crear job en background
      const response = await fetch('/api/jobs/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          job_type: 'podcast',
          input_data: inputData
        })
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.error?.message || data.message || 'Error al crear la tarea');
      }

      currentJobId = data.job_id;
      try {
        sessionStorage.setItem('podcast_job_id', String(currentJobId));
      } catch (_) {}
      pollStartTime = Date.now();
      
      // Mostrar mensaje de que puede navegar
      updateProgress('Procesando...', 'Estamos creando tu podcast, danos unos minutos.');
      showNavigationHint();
      
      // Disparar procesamiento (trigger) y empezar polling
      triggerProcessing();
      startPolling();

    } catch (error) {
      console.error('Error:', error);
      showError(error.message);
    }
  }

  // Disparar el procesamiento del job (sin esperar respuesta)
  function triggerProcessing() {
    fetch('/api/jobs/process.php', {
      method: 'POST',
      credentials: 'include'
    }).catch(() => {}); // Ignorar errores, el cron también procesará
  }

  // Iniciar polling del estado del job
  function startPolling() {
    if (pollTimer) clearInterval(pollTimer);
    
    const poll = async () => {
      if (!currentJobId) return;
      
      try {
        const res = await fetch(`/api/jobs/status.php?id=${currentJobId}`, {
          credentials: 'include'
        });
        
        if (!res.ok) {
          throw new Error('Error consultando estado');
        }
        
        const data = await res.json();
        const job = data.job;
        
        if (job.status === 'processing' || job.status === 'pending') {
          // Actualizar progreso
          updateProgress(
            job.progress_text || 'Procesando...',
            job.status === 'pending' ? 'Estamos creando tu podcast, danos unos minutos.' : 'Estamos creando tu podcast, danos unos minutos.'
          );
          
          // Ajustar intervalo de polling (más lento después de 30s)
          const elapsed = Date.now() - pollStartTime;
          if (elapsed > 30000 && pollTimer) {
            clearInterval(pollTimer);
            pollTimer = setInterval(poll, POLL_INTERVAL_LONG);
          }
          
          // Timeout después de 5 minutos
          if (elapsed > POLL_TIMEOUT) {
            stopPolling();
            showError('La generación está tardando demasiado. Revisa el historial en unos minutos.');
          }
          
        } else if (job.status === 'completed') {
          // ¡Éxito!
          stopPolling();
          await handleJobCompleted(job.output_data);
          
        } else if (job.status === 'failed') {
          // Error
          stopPolling();
          showError(job.error_message || 'Error al generar el podcast');
        }
        
      } catch (err) {
        console.error('Error polling:', err);
        // No parar el polling por errores de red temporales
      }
    };
    
    // Primera consulta inmediata, luego cada POLL_INTERVAL_INITIAL
    poll();
    pollTimer = setInterval(poll, POLL_INTERVAL_INITIAL);
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
    currentJobId = null;
    try {
      sessionStorage.removeItem('podcast_job_id');
    } catch (_) {}
    hideNavigationHint();
  }

  // Cancelar job en progreso
  async function cancelJob() {
    if (!currentJobId) return;
    
    if (!confirm('¿Estás seguro de que quieres cancelar la generación del podcast?')) {
      return;
    }

    try {
      const res = await fetch('/api/jobs/cancel.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ job_id: currentJobId })
      });

      const data = await res.json();

      if (res.ok && data.success) {
        stopPolling();
        showToast('Generación cancelada', 'info');
        resetUI();
      } else {
        showToast('Error al cancelar: ' + (data.error?.message || 'Error desconocido'), 'error');
      }
    } catch (err) {
      console.error('Error cancelando job:', err);
      showToast('Error de conexión al cancelar', 'error');
    }
  }

  // Manejar job completado
  async function handleJobCompleted(outputData) {
    if (!outputData) {
      showError('No se recibieron datos del podcast');
      return;
    }
    
    const audioUrl = outputData.audio_url;
    if (!audioUrl) {
      showError('No se recibió URL del audio');
      return;
    }
    
    lastAudioUrl = audioUrl;
    lastTitle = outputData.title || 'Podcast';

    // Update UI inmediata
    audioPlayer.src = audioUrl;
    podcastTitle.textContent = outputData.title || 'Podcast generado';
    podcastSummary.textContent = outputData.summary || '';
    podcastScript.innerHTML = mdToHtml(formatScript(outputData.script));

    showResult();
    loadHistory();
    
    // Notificación toast
    showToast('🎙️ ¡Tu podcast está listo!', 'success');

    // Fetch blob para descarga en background (no bloquea la UI)
    if (downloadBtn) {
      downloadBtn.disabled = true;
      downloadBtn.classList.add('opacity-50', 'cursor-wait');
      downloadBtn.innerHTML = '<i class="iconoir-refresh animate-spin text-xs"></i> Preparando...';
    }

    try {
      const blobResp = await fetch(audioUrl, { credentials: 'include' });
      lastAudioBlob = await blobResp.blob();
      if (downloadBtn) {
        downloadBtn.disabled = false;
        downloadBtn.classList.remove('opacity-50', 'cursor-wait');
        downloadBtn.innerHTML = '<i class="iconoir-download"></i> Descargar';
      }
    } catch (e) {
      console.error('Error al precargar blob tras generación:', e);
      lastAudioBlob = null;
      if (downloadBtn) {
        downloadBtn.disabled = false;
        downloadBtn.classList.remove('opacity-50', 'cursor-wait');
        downloadBtn.innerHTML = '<i class="iconoir-download"></i> Descargar';
      }
    }
  }

  // Mostrar hint de que puede navegar
  function showNavigationHint() {
    let hint = document.getElementById('navigation-hint');
    if (!hint) {
      hint = document.createElement('div');
      hint.id = 'navigation-hint';
      hint.className = 'mt-3 p-3 bg-orange-50 border border-orange-200 rounded-lg text-sm text-orange-700 flex items-center gap-2';
      hint.innerHTML = `
        <i class="iconoir-info-circle"></i>
        <span>Puedes navegar por otras secciones y volver en unos minutos.</span>
      `;
      if (progressPanel) {
        progressPanel.appendChild(hint);
      }
    }
    hint.classList.remove('hidden');
  }

  function hideNavigationHint() {
    const hint = document.getElementById('navigation-hint');
    if (hint) hint.classList.add('hidden');
  }

  // Toast notification
  function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'fixed bottom-20 lg:bottom-4 right-4 z-50 flex flex-col gap-2';
      document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-slate-700';
    toast.className = `${bgColor} text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-2 animate-slide-in`;
    toast.innerHTML = `<span>${escapeHtml(message)}</span>`;
    
    container.appendChild(toast);
    
    // Auto-remove after 5s
    setTimeout(() => {
      toast.classList.add('opacity-0', 'transition-opacity');
      setTimeout(() => toast.remove(), 300);
    }, 5000);
  }

  // === Download ===
  if (downloadBtn) {
    downloadBtn.addEventListener('click', () => {
      if (!lastAudioBlob) return;
      
      const url = URL.createObjectURL(lastAudioBlob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `podcast-${slugify(lastTitle)}.wav`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    });
  }

  // === New podcast button ===
  if (newPodcastBtn) {
    newPodcastBtn.addEventListener('click', resetUI);
  }

  // === Cancel button ===
  if (cancelBtn) {
    cancelBtn.addEventListener('click', cancelJob);
  }

  // === UI helpers ===
  function showProgress() {
    if (progressPanel) progressPanel.classList.remove('hidden');
    if (errorPanel) errorPanel.classList.add('hidden');
    if (generateBtn) {
      generateBtn.disabled = true;
      generateBtn.innerHTML = '<i class="iconoir-refresh animate-spin"></i> Generando...';
    }
  }

  function updateProgress(text, detail) {
    if (progressText) progressText.textContent = text;
    if (progressDetail) progressDetail.textContent = detail;
  }

  function showResult() {
    if (progressPanel) progressPanel.classList.add('hidden');
    if (errorPanel) errorPanel.classList.add('hidden');
    if (podcastInputSection) podcastInputSection.classList.add('hidden');
    if (podcastResult) podcastResult.classList.remove('hidden');
    if (generateBtn) {
      generateBtn.disabled = false;
      generateBtn.innerHTML = '<i class="iconoir-sparks"></i> <span>Generar Podcast</span>';
    }
  }

  function showError(message) {
    if (progressPanel) progressPanel.classList.add('hidden');
    if (errorPanel) errorPanel.classList.remove('hidden');
    if (podcastInputSection) podcastInputSection.classList.remove('hidden');
    if (errorMessage) errorMessage.textContent = message;
    if (generateBtn) {
      generateBtn.disabled = false;
      generateBtn.innerHTML = '<i class="iconoir-sparks"></i> <span>Generar Podcast</span>';
    }
  }

  function resetUI() {
    if (progressPanel) progressPanel.classList.add('hidden');
    if (errorPanel) errorPanel.classList.add('hidden');
    if (podcastInputSection) podcastInputSection.classList.remove('hidden');
    if (podcastResult) podcastResult.classList.add('hidden');
    if (generateBtn) {
      generateBtn.disabled = false;
      generateBtn.innerHTML = '<i class="iconoir-sparks"></i> <span>Generar Podcast</span>';
    }

    articleUrl.value = '';
    articleText.value = '';
    if (articlePdf) articlePdf.value = '';
    pdfBase64 = null;
    if (pdfFilename) pdfFilename.classList.add('hidden');
    
    audioPlayer.src = '';
    lastAudioBlob = null;
    lastAudioUrl = '';
    lastTitle = '';

    // Si hay un job activo en servidor, reanudar visualización/progreso
    // Esto cubre el caso de volver desde "crear otro podcast" mientras sigue generando
    try {
      const savedId = sessionStorage.getItem('podcast_job_id');
      if (savedId) {
        // Mostrar panel y reanudar polling vía API
        showProgress();
        updateProgress('Procesando...', 'Recuperando estado del podcast en proceso...');
        checkActiveJobs();
        return;
      }
    } catch (_) {}
    // Si no hay info local, igualmente consultar al servidor
    checkActiveJobs();
  }

  // === HISTORY ===
  loadHistory();
  
  // === CHECK FOR URL PARAMETER (from sidebar navigation) ===
  checkUrlParameter();
  
  // === CHECK FOR ACTIVE JOBS ON PAGE LOAD ===
  checkActiveJobs();
  
  function checkUrlParameter() {
    const urlParams = new URLSearchParams(window.location.search);
    const executionId = urlParams.get('id');
    
    if (executionId) {
      // Cargar el contenido automáticamente
      loadExecution(executionId);
      
      // Limpiar el parámetro de la URL sin recargar (opcional, para mejor UX)
      const newUrl = window.location.pathname;
      window.history.replaceState({}, document.title, newUrl);
    }
  }
  
  async function checkActiveJobs() {
    try {
      const res = await fetch('/api/jobs/active.php', { credentials: 'include' });
      const data = await res.json();
      
      if (data.success && data.jobs && data.jobs.length > 0) {
        // Buscar job de podcast activo
        const podcastJob = data.jobs.find(j => j.job_type === 'podcast');
        if (podcastJob) {
          // Reanudar polling para este job
          currentJobId = podcastJob.id;
          pollStartTime = Date.now() - 30000; // Asumir que ya lleva tiempo
          showProgress();
          updateProgress(
            podcastJob.progress_text || 'Procesando...',
            'Recuperando estado del podcast en proceso...'
          );
          showNavigationHint();
          startPolling();
        }
      }
    } catch (err) {
      // Silencioso si falla
      console.log('No se pudo verificar jobs activos');
    }
  }

  async function loadHistory() {
    try {
      const res = await fetch(`/api/gestures/history.php?type=${GESTURE_TYPE}`, {
        credentials: 'include'
      });
      const data = await res.json();

      if (!res.ok) {
        historyList.innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Error al cargar</div>';
        return;
      }

      renderHistory(data.items || []);
    } catch (err) {
      historyList.innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Error de conexión</div>';
    }
  }

  function renderHistory(items) {
    if (items.length === 0) {
      historyList.innerHTML = `
        <div class="p-6 text-center">
          <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
            <i class="iconoir-podcast text-xl text-orange-400"></i>
          </div>
          <p class="text-sm text-slate-500">Aún no has creado podcasts</p>
          <p class="text-xs text-slate-400 mt-1">Usa el formulario para empezar</p>
        </div>
      `;
      return;
    }

    historyList.innerHTML = items.map(item => {
      const timeAgo = formatTimeAgo(new Date(item.created_at));
      const inputData = item.input_data || {};
      const sourceIcon = inputData.source_type === 'url' ? 'iconoir-link' : 
                         inputData.source_type === 'pdf' ? 'iconoir-page' : 'iconoir-text';

      return `
        <div class="history-item w-full p-3 hover:bg-slate-50 border-b border-slate-100 transition-colors group flex items-start gap-2" data-id="${item.id}">
          <i class="${sourceIcon} text-orange-500 mt-0.5"></i>
          <div class="flex-1 min-w-0 cursor-pointer history-item-main">
            <p class="text-sm font-medium text-slate-700 truncate group-hover:text-orange-600">${escapeHtml(item.title)}</p>
            <div class="flex items-center gap-2 mt-1">
              <span class="text-[10px] text-slate-400">${timeAgo}</span>
            </div>
          </div>
          <button class="history-item-delete opacity-0 group-hover:opacity-100 transition-opacity text-slate-300 hover:text-red-500 p-1 rounded" title="Eliminar">
            <i class="iconoir-trash"></i>
          </button>
        </div>
      `;
    }).join('');

    // Event listeners
    historyList.querySelectorAll('.history-item-main').forEach(el => {
      const id = el.parentElement.dataset.id;
      el.addEventListener('click', () => loadExecution(id));
    });

    historyList.querySelectorAll('.history-item-delete').forEach(btn => {
      const id = btn.parentElement.dataset.id;
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        deleteExecution(id);
      });
    });
  }

  async function loadExecution(id) {
    try {
      const res = await fetch(`/api/gestures/get.php?id=${id}`, {
        credentials: 'include'
      });
      const data = await res.json();

      if (!res.ok || !data.execution) {
        alert('Error al cargar el podcast');
        return;
      }

      const exec = data.execution;
      const outputData = exec.output_data || {};

      // Mostrar resultado
      podcastTitle.textContent = exec.title || 'Podcast';
      podcastSummary.textContent = outputData.summary || '';
      podcastScript.innerHTML = mdToHtml(formatScript(outputData.script || ''));
      
      // Resaltar en historial
      document.querySelectorAll('.history-item').forEach(el => el.classList.remove('active'));
      const activeItem = document.querySelector(`.history-item[data-id="${id}"]`);
      if (activeItem) activeItem.classList.add('active');

      // Audio
      if (outputData.audio_url) {
        audioPlayer.src = outputData.audio_url;
        lastAudioUrl = outputData.audio_url;
        lastTitle = exec.title || 'Podcast';
        
        // Mostrar resultado inmediatamente mientras el audio carga en background
        showResult();

        // Fetch blob para descarga en background (no bloquea la UI)
        if (downloadBtn) {
          downloadBtn.disabled = true;
          downloadBtn.classList.add('opacity-50', 'cursor-wait');
          downloadBtn.innerHTML = '<i class="iconoir-refresh animate-spin text-xs"></i> Preparando...';
        }

        fetch(outputData.audio_url, { credentials: 'include' })
          .then(resp => resp.blob())
          .then(blob => {
            lastAudioBlob = blob;
            if (downloadBtn) {
              downloadBtn.disabled = false;
              downloadBtn.classList.remove('opacity-50', 'cursor-wait');
              downloadBtn.innerHTML = '<i class="iconoir-download"></i> Descargar';
            }
          })
          .catch(e => {
            console.error('Error al precargar blob:', e);
            lastAudioBlob = null;
            if (downloadBtn) {
              downloadBtn.disabled = false;
              downloadBtn.classList.remove('opacity-50', 'cursor-wait');
              downloadBtn.innerHTML = '<i class="iconoir-download"></i> Descargar';
            }
          });
      } else {
        showResult();
      }
    } catch (err) {
      alert('Error al cargar el podcast');
    }
  }

  async function deleteExecution(id) {
    if (!confirm('¿Eliminar este podcast del historial?')) return;

    try {
      const csrfToken = (typeof window !== 'undefined' && window.CSRF_TOKEN) ? window.CSRF_TOKEN : (document.querySelector('meta[name="csrf-token"]')?.content || '');
      const res = await fetch('/api/gestures/delete.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        credentials: 'include',
        body: JSON.stringify({ id, csrf_token: csrfToken })
      });

      if (res.ok) {
        loadHistory();
      }
    } catch (err) {
      alert('Error al eliminar');
    }
  }

  // === Utility functions ===
  function formatScript(script) {
    if (!script) return '';
    // Añadir separación entre intervenciones de los presentadores (admite nombres actuales y anteriores)
    return script.replace(/\n(Ana:|Carlos:|Iris:|Bruno:)/g, '\n\n$1');
  }

  function mdToHtml(text) {
    if (!text) return '';
    // Escape HTML
    const escaped = text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
    // Minimal markdown: **bold** and __bold__
    return escaped
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/__(.+?)__/g, '<strong>$1</strong>')
      // Italic with single * or _ (avoid matching bold)
      .replace(/\*(?!\*)(.+?)\*/g, '<em>$1</em>')
      .replace(/_(?!_)(.+?)_/g, '<em>$1</em>')
      .replace(/\n/g, '\n');
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

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function formatTimeAgo(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Ahora';
    if (diffMins < 60) return `Hace ${diffMins} min`;
    if (diffHours < 24) return `Hace ${diffHours}h`;
    if (diffDays < 7) return `Hace ${diffDays}d`;
    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
  }
  window.resetUI = resetUI;
})();
