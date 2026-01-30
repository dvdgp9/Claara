/**
 * SOP Generator - Generador de Procedimientos Operativos
 */

(function() {
  'use strict';

  // Estado
  const state = {
    text: '',
    url: '',
    audioFile: null,
    audioBase64: '',
    pdfFile: null,
    pdfBase64: '',
    images: [], // [{ file, base64, mime_type }]
    activeSources: new Set(),
    isProcessing: false,
    currentResult: null,
    // Grabación de audio
    mediaRecorder: null,
    audioChunks: [],
    recordedBlob: null,
    recordingTimer: null,
    recordingSeconds: 0
  };

  // Elementos DOM
  const elements = {
    form: document.getElementById('sop-form'),
    titleInput: document.getElementById('sop-title'),
    generateBtn: document.getElementById('generate-btn'),
    resultsSection: document.getElementById('results-section'),
    inputSection: document.getElementById('input-section'),
    newProcessBtnContainer: document.getElementById('new-process-btn-container'),
    newProcessBtn: document.getElementById('new-process-btn'),
    processingOverlay: document.getElementById('processing-overlay'),
    processingStatus: document.getElementById('processing-status'),
    processingBar: document.getElementById('processing-bar'),
    
    // Source cards
    sourceCards: document.querySelectorAll('.source-card'),
    
    // Panels
    panelText: document.getElementById('panel-text'),
    panelUrl: document.getElementById('panel-url'),
    panelAudio: document.getElementById('panel-audio'),
    panelImages: document.getElementById('panel-images'),
    panelPdf: document.getElementById('panel-pdf'),
    
    // Inputs
    inputText: document.getElementById('input-text'),
    inputUrl: document.getElementById('input-url'),
    inputAudio: document.getElementById('input-audio'),
    inputImages: document.getElementById('input-images'),
    inputPdf: document.getElementById('input-pdf'),
    
    // Dropzones
    audioDropzone: document.getElementById('audio-dropzone'),
    imagesDropzone: document.getElementById('images-dropzone'),
    pdfDropzone: document.getElementById('pdf-dropzone'),
    
    // Previews
    audioPlaceholder: document.getElementById('audio-placeholder'),
    audioPreview: document.getElementById('audio-preview'),
    audioName: document.getElementById('audio-name'),
    audioSize: document.getElementById('audio-size'),
    removeAudio: document.getElementById('remove-audio'),
    
    imagesPlaceholder: document.getElementById('images-placeholder'),
    imagesGrid: document.getElementById('images-grid'),
    
    pdfPlaceholder: document.getElementById('pdf-placeholder'),
    pdfPreview: document.getElementById('pdf-preview'),
    pdfName: document.getElementById('pdf-name'),
    pdfSize: document.getElementById('pdf-size'),
    removePdf: document.getElementById('remove-pdf'),
    
    addPdfBtn: document.getElementById('add-pdf-btn'),
    
    // Results
    resultTabs: document.querySelectorAll('.result-tab'),
    markdownContent: document.getElementById('markdown-content'),
    mermaidContainer: document.getElementById('mermaid-container'),
    copyMarkdownBtn: document.getElementById('copy-markdown-btn'),
    copyMermaidBtn: document.getElementById('copy-mermaid-btn'),
    downloadPdf: document.getElementById('download-pdf'),
    downloadDocx: document.getElementById('download-docx'),
    
    // History
    historyList: document.getElementById('history-list'),
    
    // Audio tabs y grabación
    audioTabBtns: document.querySelectorAll('.audio-tab-btn'),
    audioTabUpload: document.getElementById('audio-tab-upload'),
    audioTabRecord: document.getElementById('audio-tab-record'),
    recordReady: document.getElementById('record-ready'),
    recordActive: document.getElementById('record-active'),
    recordDone: document.getElementById('record-done'),
    startRecordBtn: document.getElementById('start-record-btn'),
    stopRecordBtn: document.getElementById('stop-record-btn'),
    recordTimer: document.getElementById('record-timer'),
    recordDuration: document.getElementById('record-duration'),
    playRecordBtn: document.getElementById('play-record-btn'),
    removeRecordBtn: document.getElementById('remove-record-btn'),
    recordPlayback: document.getElementById('record-playback')
  };

  // Inicialización
  function init() {
    setupSourceCards();
    setupInputListeners();
    setupFileUploads();
    setupAudioTabs();
    setupAudioRecording();
    setupFormSubmit();
    setupResultTabs();
    setupCopyButtons();
    setupNewProcessBtn();
    loadHistory();
  }

  // Configurar botón nuevo proceso
  function setupNewProcessBtn() {
    elements.newProcessBtn?.addEventListener('click', () => {
      resetForm();
      elements.inputSection.classList.remove('hidden');
      elements.newProcessBtnContainer.classList.add('hidden');
      elements.resultsSection.classList.add('hidden');
      elements.historyList.querySelectorAll('.history-item').forEach(i => i.classList.remove('active'));
    });
  }

  // Resetear formulario
  function resetForm() {
    state.text = '';
    state.url = '';
    state.audioFile = null;
    state.audioBase64 = '';
    state.pdfFile = null;
    state.pdfBase64 = '';
    state.images = [];
    state.activeSources.clear();
    state.currentResult = null;
    state.recordedBlob = null;
    state.audioChunks = [];
    
    elements.titleInput.value = '';
    elements.inputText.value = '';
    elements.inputUrl.value = '';
    
    // Ocultar todos los paneles y resetear tarjetas
    document.querySelectorAll('.source-panel').forEach(p => p.classList.add('hidden'));
    elements.sourceCards.forEach(c => {
      c.classList.remove('active', 'has-content');
    });
    
    // Resetear previews
    removeAudio({ stopPropagation: () => {} });
    removePdf({ stopPropagation: () => {} });
    renderImagesGrid();
    
    // Resetear grabación
    if (elements.recordReady) {
      elements.recordReady.classList.remove('hidden');
      elements.recordActive?.classList.add('hidden');
      elements.recordDone?.classList.add('hidden');
      if (elements.recordTimer) elements.recordTimer.textContent = '00:00';
    }
  }

  // Configurar tarjetas de fuente
  function setupSourceCards() {
    elements.sourceCards.forEach(card => {
      card.addEventListener('click', () => {
        const source = card.dataset.source;
        toggleSource(source);
      });
    });
    
    // Botón añadir PDF
    elements.addPdfBtn.addEventListener('click', () => {
      toggleSource('pdf');
    });
  }

  function toggleSource(source) {
    const panel = document.getElementById(`panel-${source}`);
    const card = document.querySelector(`[data-source="${source}"]`);
    
    if (state.activeSources.has(source)) {
      // Ocultar
      state.activeSources.delete(source);
      panel.classList.add('hidden');
      card?.classList.remove('active');
    } else {
      // Mostrar
      state.activeSources.add(source);
      panel.classList.remove('hidden');
      card?.classList.add('active');
      
      // Focus en el input correspondiente
      setTimeout(() => {
        if (source === 'text') elements.inputText.focus();
        if (source === 'url') elements.inputUrl.focus();
      }, 100);
    }
    
    updateSourceCard(source);
  }

  function updateSourceCard(source) {
    const card = document.querySelector(`[data-source="${source}"]`);
    if (!card) return;
    
    let hasContent = false;
    
    switch (source) {
      case 'text':
        hasContent = state.text.trim().length > 0;
        break;
      case 'url':
        hasContent = state.url.trim().length > 0;
        break;
      case 'audio':
        hasContent = state.audioFile !== null;
        break;
      case 'images':
        hasContent = state.images.length > 0;
        break;
      case 'pdf':
        hasContent = state.pdfFile !== null;
        break;
    }
    
    card.classList.toggle('has-content', hasContent);
  }

  // Listeners de inputs
  function setupInputListeners() {
    elements.inputText.addEventListener('input', () => {
      state.text = elements.inputText.value;
      updateSourceCard('text');
    });
    
    elements.inputUrl.addEventListener('input', () => {
      state.url = elements.inputUrl.value;
      updateSourceCard('url');
    });
  }

  // Configurar uploads de archivos
  function setupFileUploads() {
    // Audio
    setupDropzone(elements.audioDropzone, elements.inputAudio, handleAudioSelect);
    elements.inputAudio.addEventListener('change', handleAudioSelect);
    elements.removeAudio.addEventListener('click', removeAudio);
    
    // Images
    setupDropzone(elements.imagesDropzone, elements.inputImages, handleImagesSelect);
    elements.inputImages.addEventListener('change', handleImagesSelect);
    
    // PDF
    setupDropzone(elements.pdfDropzone, elements.inputPdf, handlePdfSelect);
    elements.inputPdf.addEventListener('change', handlePdfSelect);
    elements.removePdf.addEventListener('click', removePdf);
  }

  function setupDropzone(dropzone, input, handler) {
    dropzone.addEventListener('click', (e) => {
      if (e.target.closest('button')) return;
      input.click();
    });
    
    dropzone.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropzone.classList.add('border-emerald-400', 'bg-emerald-50/50');
    });
    
    dropzone.addEventListener('dragleave', () => {
      dropzone.classList.remove('border-emerald-400', 'bg-emerald-50/50');
    });
    
    dropzone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropzone.classList.remove('border-emerald-400', 'bg-emerald-50/50');
      
      const files = e.dataTransfer.files;
      if (files.length > 0) {
        // Crear un evento fake para reutilizar el handler
        const fakeEvent = { target: { files } };
        handler(fakeEvent);
      }
    });
  }

  // Tabs de audio (Subir / Grabar)
  function setupAudioTabs() {
    elements.audioTabBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const tab = btn.dataset.audioTab;
        
        // Actualizar botones
        elements.audioTabBtns.forEach(b => {
          b.classList.remove('bg-emerald-100', 'text-emerald-700', 'active');
          b.classList.add('bg-slate-100', 'text-slate-600');
        });
        btn.classList.remove('bg-slate-100', 'text-slate-600');
        btn.classList.add('bg-emerald-100', 'text-emerald-700', 'active');
        
        // Mostrar tab correspondiente
        if (tab === 'upload') {
          elements.audioTabUpload.classList.remove('hidden');
          elements.audioTabRecord.classList.add('hidden');
        } else {
          elements.audioTabUpload.classList.add('hidden');
          elements.audioTabRecord.classList.remove('hidden');
        }
      });
    });
  }

  // Grabación de audio
  function setupAudioRecording() {
    elements.startRecordBtn?.addEventListener('click', startRecording);
    elements.stopRecordBtn?.addEventListener('click', stopRecording);
    elements.playRecordBtn?.addEventListener('click', playRecording);
    elements.removeRecordBtn?.addEventListener('click', removeRecording);
  }

  async function startRecording() {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      
      state.mediaRecorder = new MediaRecorder(stream);
      state.audioChunks = [];
      state.recordingSeconds = 0;
      
      state.mediaRecorder.ondataavailable = (e) => {
        if (e.data.size > 0) {
          state.audioChunks.push(e.data);
        }
      };
      
      state.mediaRecorder.onstop = async () => {
        stream.getTracks().forEach(track => track.stop());
        
        state.recordedBlob = new Blob(state.audioChunks, { type: 'audio/webm' });
        
        // Convertir a base64
        const reader = new FileReader();
        reader.onloadend = () => {
          state.audioBase64 = reader.result.split(',')[1];
          state.audioFile = new File([state.recordedBlob], 'grabacion.webm', { type: 'audio/webm' });
          updateSourceCard('audio');
        };
        reader.readAsDataURL(state.recordedBlob);
        
        // Mostrar estado completado
        elements.recordReady.classList.add('hidden');
        elements.recordActive.classList.add('hidden');
        elements.recordDone.classList.remove('hidden');
        elements.recordDuration.textContent = formatTime(state.recordingSeconds);
        
        // Preparar playback
        elements.recordPlayback.src = URL.createObjectURL(state.recordedBlob);
      };
      
      state.mediaRecorder.start(1000);
      
      // Mostrar estado grabando
      elements.recordReady.classList.add('hidden');
      elements.recordActive.classList.remove('hidden');
      elements.recordDone.classList.add('hidden');
      
      // Timer
      state.recordingTimer = setInterval(() => {
        state.recordingSeconds++;
        elements.recordTimer.textContent = formatTime(state.recordingSeconds);
      }, 1000);
      
    } catch (error) {
      console.error('Error al acceder al micrófono:', error);
      alert('No se pudo acceder al micrófono. Asegúrate de dar permiso.');
    }
  }

  function stopRecording() {
    if (state.mediaRecorder && state.mediaRecorder.state !== 'inactive') {
      state.mediaRecorder.stop();
    }
    if (state.recordingTimer) {
      clearInterval(state.recordingTimer);
      state.recordingTimer = null;
    }
  }

  function playRecording() {
    if (elements.recordPlayback.paused) {
      elements.recordPlayback.play();
      elements.playRecordBtn.innerHTML = '<i class="iconoir-pause"></i>';
    } else {
      elements.recordPlayback.pause();
      elements.playRecordBtn.innerHTML = '<i class="iconoir-play"></i>';
    }
  }

  function removeRecording() {
    state.recordedBlob = null;
    state.audioBase64 = '';
    state.audioFile = null;
    state.audioChunks = [];
    
    elements.recordReady.classList.remove('hidden');
    elements.recordActive.classList.add('hidden');
    elements.recordDone.classList.add('hidden');
    elements.recordTimer.textContent = '00:00';
    
    if (elements.recordPlayback.src) {
      URL.revokeObjectURL(elements.recordPlayback.src);
      elements.recordPlayback.src = '';
    }
    
    updateSourceCard('audio');
  }

  function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  }

  async function handleAudioSelect(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Validar tipo
    const validTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/wave', 'audio/x-wav', 'audio/mp4', 'audio/m4a', 'audio/x-m4a', 'audio/webm', 'audio/ogg'];
    if (!validTypes.includes(file.type) && !file.name.match(/\.(mp3|wav|m4a|webm|ogg)$/i)) {
      alert('Tipo de audio no soportado. Usa MP3, WAV, M4A, WebM u OGG.');
      return;
    }
    
    // Validar tamaño
    if (file.size > 25 * 1024 * 1024) {
      alert('El archivo de audio es demasiado grande. Máximo 25MB.');
      return;
    }
    
    state.audioFile = file;
    state.audioBase64 = await fileToBase64(file);
    
    // Mostrar preview
    elements.audioPlaceholder.classList.add('hidden');
    elements.audioPreview.classList.remove('hidden');
    elements.audioName.textContent = file.name;
    elements.audioSize.textContent = formatFileSize(file.size);
    
    updateSourceCard('audio');
  }

  function removeAudio(e) {
    e.stopPropagation();
    state.audioFile = null;
    state.audioBase64 = '';
    elements.inputAudio.value = '';
    elements.audioPlaceholder.classList.remove('hidden');
    elements.audioPreview.classList.add('hidden');
    updateSourceCard('audio');
  }

  async function handleImagesSelect(e) {
    const files = Array.from(e.target.files);
    if (files.length === 0) return;
    
    for (const file of files) {
      // Validar tipo
      if (!file.type.startsWith('image/')) {
        continue;
      }
      
      // Validar tamaño
      if (file.size > 10 * 1024 * 1024) {
        alert(`La imagen ${file.name} es demasiado grande. Máximo 10MB por imagen.`);
        continue;
      }
      
      const base64 = await fileToBase64(file);
      state.images.push({
        file,
        base64,
        mime_type: file.type
      });
    }
    
    renderImagesGrid();
    updateSourceCard('images');
  }

  function renderImagesGrid() {
    if (state.images.length === 0) {
      elements.imagesPlaceholder.classList.remove('hidden');
      elements.imagesGrid.classList.add('hidden');
      return;
    }
    
    elements.imagesPlaceholder.classList.add('hidden');
    elements.imagesGrid.classList.remove('hidden');
    
    elements.imagesGrid.innerHTML = state.images.map((img, index) => `
      <div class="image-thumb" data-index="${index}">
        <img src="data:${img.mime_type};base64,${img.base64}" alt="Imagen ${index + 1}">
        <button type="button" class="remove-btn" onclick="window.sopRemoveImage(${index})">
          <i class="iconoir-xmark"></i>
        </button>
      </div>
    `).join('') + `
      <div class="image-thumb border-2 border-dashed border-slate-200 flex items-center justify-center cursor-pointer hover:border-emerald-400" onclick="document.getElementById('input-images').click()">
        <i class="iconoir-plus text-slate-400 text-xl"></i>
      </div>
    `;
  }

  // Exponer para el onclick inline
  window.sopRemoveImage = function(index) {
    state.images.splice(index, 1);
    renderImagesGrid();
    updateSourceCard('images');
  };

  async function handlePdfSelect(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Validar tipo
    if (file.type !== 'application/pdf' && !file.name.endsWith('.pdf')) {
      alert('Solo se aceptan archivos PDF.');
      return;
    }
    
    // Validar tamaño
    if (file.size > 20 * 1024 * 1024) {
      alert('El PDF es demasiado grande. Máximo 20MB.');
      return;
    }
    
    state.pdfFile = file;
    state.pdfBase64 = await fileToBase64(file);
    
    // Mostrar preview
    elements.pdfPlaceholder.classList.add('hidden');
    elements.pdfPreview.classList.remove('hidden');
    elements.pdfName.textContent = file.name;
    elements.pdfSize.textContent = formatFileSize(file.size);
    
    // Mostrar panel si no está visible
    if (!state.activeSources.has('pdf')) {
      toggleSource('pdf');
    }
    
    updateSourceCard('pdf');
  }

  function removePdf(e) {
    e.stopPropagation();
    state.pdfFile = null;
    state.pdfBase64 = '';
    elements.inputPdf.value = '';
    elements.pdfPlaceholder.classList.remove('hidden');
    elements.pdfPreview.classList.add('hidden');
    updateSourceCard('pdf');
  }

  // Utilidades de archivos
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

  function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  // Envío del formulario
  function setupFormSubmit() {
    elements.form.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      if (state.isProcessing) return;
      
      // Validar que hay al menos una fuente
      const hasContent = state.text.trim() || state.url.trim() || state.audioFile || state.images.length > 0 || state.pdfFile;
      
      if (!hasContent) {
        alert('Añade al menos una fuente de contenido (texto, URL, audio, imágenes o PDF).');
        return;
      }
      
      await generateSop();
    });
  }

  async function generateSop() {
    state.isProcessing = true;
    showProcessing();
    
    try {
      // Construir payload
      const payload = {
        title: elements.titleInput.value.trim()
      };
      
      if (state.text.trim()) {
        payload.text = state.text.trim();
      }
      
      if (state.url.trim()) {
        payload.url = state.url.trim();
      }
      
      if (state.pdfBase64) {
        payload.pdf_base64 = state.pdfBase64;
        updateProcessingStatus('Extrayendo contenido del PDF...', 10);
      }
      
      if (state.audioBase64) {
        payload.audio_base64 = state.audioBase64;
        payload.audio_mime = state.audioFile.type || 'audio/mpeg';
        payload.audio_filename = state.audioFile.name;
        updateProcessingStatus('Transcribiendo audio (esto puede tardar unos segundos)...', 20);
      }
      
      if (state.images.length > 0) {
        payload.images = state.images.map(img => ({
          base64: img.base64,
          mime_type: img.mime_type
        }));
        updateProcessingStatus('Analizando imágenes...', 30);
      }
      
      updateProcessingStatus('Generando procedimiento estructurado...', 50);
      
      const response = await fetch('/api/gestures/sop.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.CSRF_TOKEN
        },
        body: JSON.stringify(payload)
      });
      
      const data = await response.json();
      
      if (!data.success) {
        throw new Error(data.error || 'Error desconocido');
      }
      
      updateProcessingStatus('Renderizando resultado...', 90);
      
      state.currentResult = data;
      displayResult(data);
      
      // Actualizar historial
      loadHistory();
      
    } catch (error) {
      console.error('Error:', error);
      alert('Error generando SOP: ' + error.message);
    } finally {
      state.isProcessing = false;
      hideProcessing();
    }
  }

  function showProcessing() {
    elements.processingOverlay.classList.remove('hidden');
    elements.generateBtn.disabled = true;
    updateProcessingStatus('Iniciando...', 0);
  }

  function hideProcessing() {
    elements.processingOverlay.classList.add('hidden');
    elements.generateBtn.disabled = false;
  }

  function updateProcessingStatus(text, progress) {
    elements.processingStatus.textContent = text;
    elements.processingBar.style.width = progress + '%';
  }

  // Mostrar resultado
  function displayResult(data) {
    // Mostrar sección de resultados
    elements.resultsSection.classList.remove('hidden');
    
    // Scroll a resultados
    elements.resultsSection.scrollIntoView({ behavior: 'smooth' });
    
    // Renderizar Markdown
    if (data.formats.markdown) {
      elements.markdownContent.innerHTML = marked.parse(data.formats.markdown);
    }
    
    // Renderizar Mermaid
    if (data.formats.mermaid) {
      renderMermaid(data.formats.mermaid);
    } else {
      elements.mermaidContainer.innerHTML = '<p class="text-slate-400 text-center py-8">No se pudo generar el diagrama de flujo</p>';
    }
    
    // Configurar descargas
    if (data.formats.pdf) {
      elements.downloadPdf.href = data.formats.pdf.url;
      elements.downloadPdf.classList.remove('opacity-50', 'pointer-events-none');
    } else {
      elements.downloadPdf.classList.add('opacity-50', 'pointer-events-none');
    }
    
    if (data.formats.docx) {
      elements.downloadDocx.href = data.formats.docx.url;
      elements.downloadDocx.classList.remove('opacity-50', 'pointer-events-none');
    } else {
      elements.downloadDocx.classList.add('opacity-50', 'pointer-events-none');
    }
    
    // Mostrar warnings si hay
    if (data.warnings && data.warnings.length > 0) {
      console.warn('Advertencias:', data.warnings);
    }
  }

  async function renderMermaid(code) {
    try {
      const id = 'mermaid-' + Date.now();
      const { svg } = await mermaid.render(id, code);
      elements.mermaidContainer.innerHTML = svg;
    } catch (error) {
      console.error('Error renderizando Mermaid:', error);
      elements.mermaidContainer.innerHTML = `
        <div class="text-center py-8">
          <p class="text-slate-400 mb-4">No se pudo renderizar el diagrama</p>
          <pre class="text-left text-xs bg-slate-100 p-4 rounded-lg overflow-auto max-h-60">${escapeHtml(code)}</pre>
        </div>
      `;
    }
  }

  // Tabs de resultados
  function setupResultTabs() {
    elements.resultTabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const target = tab.dataset.result;
        
        // Actualizar tabs
        elements.resultTabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        // Actualizar paneles
        document.querySelectorAll('.result-panel').forEach(p => p.classList.remove('active'));
        document.getElementById(`result-${target}`).classList.add('active');
      });
    });
  }

  // Botones de copiar
  function setupCopyButtons() {
    elements.copyMarkdownBtn.addEventListener('click', () => {
      if (state.currentResult?.formats?.markdown) {
        copyToClipboard(state.currentResult.formats.markdown);
        showCopyFeedback(elements.copyMarkdownBtn);
      }
    });
    
    elements.copyMermaidBtn.addEventListener('click', () => {
      if (state.currentResult?.formats?.mermaid) {
        copyToClipboard(state.currentResult.formats.mermaid);
        showCopyFeedback(elements.copyMermaidBtn);
      }
    });
  }

  function copyToClipboard(text) {
    navigator.clipboard.writeText(text).catch(() => {
      // Fallback
      const textarea = document.createElement('textarea');
      textarea.value = text;
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
    });
  }

  function showCopyFeedback(btn) {
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="iconoir-check"></i> Copiado';
    btn.classList.add('text-emerald-600');
    setTimeout(() => {
      btn.innerHTML = originalHtml;
    }, 2000);
  }

  // Historial
  async function loadHistory() {
    try {
      const response = await fetch('/api/gestures/history.php?type=sop-generator&limit=20');
      const data = await response.json();
      
      if (data.success && data.history) {
        renderHistory(data.history);
      }
    } catch (error) {
      console.error('Error cargando historial:', error);
    }
  }

  function renderHistory(items) {
    if (items.length === 0) {
      elements.historyList.innerHTML = `
        <div class="p-6 text-center">
          <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
            <i class="iconoir-clipboard-check text-xl text-emerald-400"></i>
          </div>
          <p class="text-sm text-slate-500">Sin SOPs generados aún</p>
          <p class="text-xs text-slate-400 mt-1">Usa el formulario para empezar</p>
        </div>
      `;
      return;
    }
    
    elements.historyList.innerHTML = items.map(item => `
      <div class="history-item w-full p-3 hover:bg-slate-50 border-b border-slate-100 transition-colors group flex items-start gap-2" data-id="${item.id}">
        <i class="iconoir-clipboard-check text-emerald-500 mt-0.5"></i>
        <div class="flex-1 min-w-0 cursor-pointer history-item-main">
          <p class="text-sm font-medium text-slate-700 truncate group-hover:text-emerald-600">${escapeHtml(item.title || 'SOP sin título')}</p>
          <div class="flex items-center gap-2 mt-1">
            <span class="text-[10px] text-slate-400">${formatDate(item.created_at)}</span>
          </div>
        </div>
        <button class="history-item-edit opacity-0 group-hover:opacity-100 transition-opacity text-slate-300 hover:text-emerald-500 p-1 rounded" title="Editar título">
          <i class="iconoir-edit-pencil"></i>
        </button>
        <button class="history-item-delete opacity-0 group-hover:opacity-100 transition-opacity text-slate-300 hover:text-red-500 p-1 rounded" title="Eliminar">
          <i class="iconoir-trash"></i>
        </button>
      </div>
    `).join('');
    
    // Event listeners para cargar item
    elements.historyList.querySelectorAll('.history-item-main').forEach(el => {
      const id = el.parentElement.dataset.id;
      el.addEventListener('click', () => loadHistoryItem(id));
    });
    
    // Event listeners para editar
    elements.historyList.querySelectorAll('.history-item-edit').forEach(btn => {
      const id = btn.parentElement.dataset.id;
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const titleEl = btn.parentElement.querySelector('.history-item-main p');
        const currentTitle = titleEl?.textContent || 'SOP sin título';
        const nextTitle = prompt('Nuevo título del proceso', currentTitle);
        if (nextTitle !== null) {
          updateHistoryTitle(id, nextTitle.trim());
        }
      });
    });

    // Event listeners para eliminar
    elements.historyList.querySelectorAll('.history-item-delete').forEach(btn => {
      const id = btn.parentElement.dataset.id;
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        deleteHistoryItem(id);
      });
    });
  }

  async function updateHistoryTitle(id, title) {
    if (!title) return;

    try {
      const csrfToken = (typeof window !== 'undefined' && window.CSRF_TOKEN) ? window.CSRF_TOKEN : '';
      const response = await fetch('/api/gestures/update-title.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        credentials: 'include',
        body: JSON.stringify({ id, title, csrf_token: csrfToken })
      });

      if (response.ok) {
        loadHistory();
      } else {
        alert('No se pudo actualizar el título');
      }
    } catch (error) {
      console.error('Error actualizando título:', error);
      alert('Error al actualizar el título');
    }
  }
  
  async function deleteHistoryItem(id) {
    if (!confirm('¿Eliminar este SOP del historial?')) return;
    
    try {
      const csrfToken = (typeof window !== 'undefined' && window.CSRF_TOKEN) ? window.CSRF_TOKEN : '';
      const response = await fetch('/api/gestures/delete.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        credentials: 'include',
        body: JSON.stringify({ id, csrf_token: csrfToken })
      });
      
      if (response.ok) {
        loadHistory();
      }
    } catch (error) {
      console.error('Error eliminando:', error);
      alert('Error al eliminar');
    }
  }

  async function loadHistoryItem(id) {
    try {
      const response = await fetch(`/api/gestures/history.php?id=${id}`);
      const data = await response.json();
      
      if (data.success && data.item) {
        // Marcar como activo
        elements.historyList.querySelectorAll('.history-item').forEach(i => i.classList.remove('active'));
        elements.historyList.querySelector(`[data-id="${id}"]`)?.classList.add('active');
        
        // Mostrar resultado y botón de nuevo proceso
        elements.inputSection.classList.add('hidden');
        elements.newProcessBtnContainer.classList.remove('hidden');
        
        const result = {
          title: data.item.title,
          formats: data.item.output_data
        };
        
        state.currentResult = result;
        displayResult(result);
      }
    } catch (error) {
      console.error('Error cargando item:', error);
    }
  }

  // Utilidades
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function formatDate(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'Hace un momento';
    if (diff < 3600000) return `Hace ${Math.floor(diff / 60000)} min`;
    if (diff < 86400000) return `Hace ${Math.floor(diff / 3600000)} horas`;
    if (diff < 604800000) return `Hace ${Math.floor(diff / 86400000)} días`;
    
    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
  }

  // Iniciar
  document.addEventListener('DOMContentLoaded', init);
})();
