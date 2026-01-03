/**
 * Studio Mode - Editor de Imágenes
 * Layout de 3 columnas: Historial + Canvas + Panel
 */
(function() {
  'use strict';

  const GESTURE_TYPE = 'image-editor';

  // === DOM References ===
  const canvasEmpty = document.getElementById('canvas-empty');
  const canvasImage = document.getElementById('canvas-image');
  const canvasLoading = document.getElementById('canvas-loading');
  const quickActions = document.getElementById('quick-actions');
  const historyList = document.getElementById('history-list');
  const historyCount = document.getElementById('history-count');
  const lightbox = document.getElementById('lightbox');
  const lightboxImage = document.getElementById('lightbox-image');
  const lightboxClose = document.getElementById('lightbox-close');

  // Panel Tabs
  const tabGenerate = document.getElementById('tab-generate');
  const tabEdit = document.getElementById('tab-edit');
  const panelGenerate = document.getElementById('panel-generate');
  const panelEdit = document.getElementById('panel-edit');

  // Generate Panel
  const promptInput = document.getElementById('prompt-input');
  const generateBtn = document.getElementById('generate-btn');
  const currentProvider = document.getElementById('current-provider');
  const currentStyle = document.getElementById('current-style');
  const currentFormat = document.getElementById('current-format');

  // Edit Panel
  const editUploadZone = document.getElementById('edit-upload-zone');
  const editFileInput = document.getElementById('edit-file-input');
  const editUploadPlaceholder = document.getElementById('edit-upload-placeholder');
  const editPreview = document.getElementById('edit-preview');
  const editImageBase64 = document.getElementById('edit-image-base64');
  const editPromptInput = document.getElementById('edit-prompt-input');
  const editBtn = document.getElementById('edit-btn');

  // Quick Actions
  const actionEdit = document.getElementById('action-edit');
  const actionRegenerate = document.getElementById('action-regenerate');
  const actionVariation = document.getElementById('action-variation');
  const actionDownload = document.getElementById('action-download');
  const downloadHeaderBtn = document.getElementById('download-header-btn');
  const newImageBtn = document.getElementById('new-image-btn');

  // === State ===
  let currentImageBase64 = null;
  let currentExecutionId = null;
  let lastPrompt = '';
  let lastInputData = {};

  // === Prompt Maps ===
  const styleMap = {
    '': '',
    'photographic': 'Hyper-realistic professional photography, shot on Sony A7R IV with 35mm G Master lens, 8k resolution, extreme detail, natural skin texture with visible pores',
    'digital-art': 'High-end digital art illustration, intricate details, vibrant colors, clean vector lines, trending on ArtStation, 8k',
    'corporate': 'Professional Silicon Valley corporate style, modern business aesthetic, clean and crisp, high-end commercial look',
    'headshot-pro': 'Professional studio headshot, shot on 85mm f/1.8 lens, shallow depth of field, sharp focus on eyes, soft bokeh, premium charcoal smart casual blazer, neutral studio background',
    'silicon-valley': 'Silicon Valley executive portrait, navy blue business suit, white shirt, solid dark gray studio backdrop with subtle vignette, shot on Sony A7III, 85mm lens'
  };

  const formatMap = {
    '1:1': 'Square format (1:1)',
    '3:4': 'Portrait format (3:4)',
    '4:3': 'Landscape format (4:3)',
    '16:9': 'Widescreen format (16:9)'
  };

  // === Panel Tabs ===
  function switchTab(tab) {
    if (!tabGenerate || !tabEdit || !panelGenerate || !panelEdit) return;
    
    if (tab === 'generate') {
      tabGenerate.classList.add('active');
      tabEdit.classList.remove('active');
      panelGenerate.style.display = 'block';
      panelEdit.style.display = 'none';
    } else {
      tabGenerate.classList.remove('active');
      tabEdit.classList.add('active');
      panelGenerate.style.display = 'none';
      panelEdit.style.display = 'block';
      
      // If there's a current image, auto-fill the edit panel
      if (currentImageBase64 && editImageBase64 && !editImageBase64.value) {
        editImageBase64.value = currentImageBase64;
        if (editPreview) {
          editPreview.src = `data:image/png;base64,${currentImageBase64}`;
          editPreview.style.display = 'block';
        }
        if (editUploadPlaceholder) {
          editUploadPlaceholder.style.display = 'none';
        }
      }
    }
  }

  if (tabGenerate) {
    tabGenerate.addEventListener('click', () => switchTab('generate'));
  }
  if (tabEdit) {
    tabEdit.addEventListener('click', () => switchTab('edit'));
  }

  // === Provider Selector ===
  document.querySelectorAll('.provider-option').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.provider-option').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentProvider.value = btn.dataset.provider;
    });
  });

  // === Style Options ===
  document.querySelectorAll('#style-options .option-card').forEach(card => {
    card.addEventListener('click', () => {
      document.querySelectorAll('#style-options .option-card').forEach(c => c.classList.remove('active'));
      card.classList.add('active');
      currentStyle.value = card.dataset.style;
    });
  });

  // === Format Options ===
  document.querySelectorAll('#format-options .option-card').forEach(card => {
    card.addEventListener('click', () => {
      document.querySelectorAll('#format-options .option-card').forEach(c => c.classList.remove('active'));
      card.classList.add('active');
      currentFormat.value = card.dataset.format;
    });
  });

  // === Edit Upload ===
  if (editUploadZone) {
    editUploadZone.addEventListener('click', () => editFileInput && editFileInput.click());
    
    // Drag & Drop for edit zone
    editUploadZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      editUploadZone.style.borderColor = 'rgba(234, 179, 8, 0.6)';
      editUploadZone.style.background = 'rgba(234, 179, 8, 0.1)';
    });

    editUploadZone.addEventListener('dragleave', (e) => {
      e.preventDefault();
      editUploadZone.style.borderColor = '';
      editUploadZone.style.background = '';
    });

    editUploadZone.addEventListener('drop', (e) => {
      e.preventDefault();
      editUploadZone.style.borderColor = '';
      editUploadZone.style.background = '';
      
      const file = e.dataTransfer.files[0];
      if (!file || !file.type.startsWith('image/')) {
        alert('Por favor, arrastra una imagen válida');
        return;
      }
      
      const reader = new FileReader();
      reader.onload = (ev) => {
        const base64 = ev.target.result.split(',')[1];
        if (editImageBase64) editImageBase64.value = base64;
        if (editPreview) {
          editPreview.src = ev.target.result;
          editPreview.style.display = 'block';
        }
        if (editUploadPlaceholder) editUploadPlaceholder.style.display = 'none';
      };
      reader.readAsDataURL(file);
    });
  }
  
  if (editFileInput) {
    editFileInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      
      if (!file.type.startsWith('image/')) {
        alert('Por favor, selecciona una imagen válida');
        return;
      }
      
      const reader = new FileReader();
      reader.onload = (ev) => {
        const base64 = ev.target.result.split(',')[1];
        if (editImageBase64) editImageBase64.value = base64;
        if (editPreview) {
          editPreview.src = ev.target.result;
          editPreview.style.display = 'block';
        }
        if (editUploadPlaceholder) editUploadPlaceholder.style.display = 'none';
      };
      reader.readAsDataURL(file);
    });
  }

  // === Build Prompt ===
  function buildPrompt(description) {
    let prompt = `Create an ultra-realistic, high-resolution masterpiece image: ${description}`;
    
    const specs = [];
    
    if (styleMap[currentStyle.value]) {
      specs.push(styleMap[currentStyle.value]);
    }
    
    if (formatMap[currentFormat.value]) {
      specs.push(formatMap[currentFormat.value]);
    }
    
    if (specs.length > 0) {
      prompt += '\n\nTechnical Specifications and Style:\n- ' + specs.join('\n- ');
    }
    
    prompt += '\n\nFinal Quality: 8k resolution, photorealistic, cinematic color grading, sharp focus, extreme attention to detail, high dynamic range (HDR).';
    
    const seed = Math.floor(Math.random() * 1000000);
    prompt += `\n\nInternal Seed: ${seed}`;
    
    return prompt;
  }

  // === Generate Image ===
  async function generateImage() {
    const description = promptInput.value.trim();
    
    if (!description) {
      alert('Por favor, describe la imagen que quieres crear');
      promptInput.focus();
      return;
    }

    const prompt = buildPrompt(description);
    const inputData = {
      mode: 'generate',
      description,
      provider: currentProvider.value,
      style: currentStyle.value,
      format: currentFormat.value
    };

    lastPrompt = prompt;
    lastInputData = inputData;

    await sendRequest(prompt, inputData);
  }

  // === Edit Image ===
  async function editImage() {
    const description = editPromptInput.value.trim();
    const sourceImage = editImageBase64.value;
    
    if (!description) {
      alert('Por favor, describe los cambios que quieres hacer');
      editPromptInput.focus();
      return;
    }
    
    if (!sourceImage) {
      alert('Por favor, sube una imagen para editar');
      return;
    }

    const inputData = {
      mode: 'edit',
      description,
      provider: currentProvider.value,
      source_image: sourceImage,
      target_image: null
    };

    lastPrompt = description;
    lastInputData = inputData;

    await sendRequest(description, inputData);
  }

  // === Send Request ===
  async function sendRequest(prompt, inputData) {
    canvasEmpty.style.display = 'none';
    canvasImage.style.display = 'none';
    canvasLoading.style.display = 'flex';
    quickActions.style.display = 'none';
    generateBtn.disabled = true;
    editBtn.disabled = true;

    try {
      const res = await fetch('/api/gestures/generate-image.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.CSRF_TOKEN
        },
        body: JSON.stringify({
          gesture_type: GESTURE_TYPE,
          prompt: prompt,
          input_data: inputData
        }),
        credentials: 'include'
      });

      const data = await res.json();
      canvasLoading.style.display = 'none';
      generateBtn.disabled = false;
      editBtn.disabled = false;

      if (!res.ok) {
        alert('Error al generar la imagen: ' + (data.error?.message || 'Error desconocido'));
        canvasEmpty.style.display = 'block';
        return;
      }

      if (!data.image) {
        alert('No se pudo generar la imagen. Intenta con otra descripción.');
        canvasEmpty.style.display = 'block';
        return;
      }

      currentImageBase64 = data.image;
      currentExecutionId = data.id;
      canvasImage.src = `data:image/png;base64,${data.image}`;
      canvasImage.style.display = 'block';
      quickActions.style.display = 'flex';

      loadHistory();

    } catch (err) {
      canvasLoading.style.display = 'none';
      generateBtn.disabled = false;
      editBtn.disabled = false;
      console.error('Error:', err);
      alert('Error de conexión al generar la imagen');
      canvasEmpty.style.display = 'block';
    }
  }

  // === Quick Actions ===
  if (actionEdit) {
    actionEdit.addEventListener('click', () => {
      if (!currentImageBase64) return;
      switchTab('edit');
      if (editImageBase64) editImageBase64.value = currentImageBase64;
      if (editPreview) {
        editPreview.src = `data:image/png;base64,${currentImageBase64}`;
        editPreview.style.display = 'block';
      }
      if (editUploadPlaceholder) editUploadPlaceholder.style.display = 'none';
    });
  }

  if (actionRegenerate) {
    actionRegenerate.addEventListener('click', () => {
      if (lastPrompt) sendRequest(lastPrompt, lastInputData);
    });
  }

  if (actionVariation) {
    actionVariation.addEventListener('click', () => {
      if (!currentImageBase64 || !lastInputData.description) return;
      
      // Create a variation by slightly modifying the prompt
      const variationPrompt = lastInputData.description + ' (create a variation with different composition)';
      const inputData = { ...lastInputData, description: variationPrompt };
      
      sendRequest(variationPrompt, inputData);
    });
  }

  // === Download ===
  function downloadImage() {
    if (!currentImageBase64) return;
    
    const link = document.createElement('a');
    link.href = `data:image/png;base64,${currentImageBase64}`;
    link.download = `ebonia-image-${Date.now()}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  if (actionDownload) actionDownload.addEventListener('click', downloadImage);
  if (downloadHeaderBtn) downloadHeaderBtn.addEventListener('click', downloadImage);

  // === New Image ===
  if (newImageBtn) {
    newImageBtn.addEventListener('click', () => {
      currentImageBase64 = null;
      currentExecutionId = null;
      lastPrompt = '';
      lastInputData = {};
      
      if (promptInput) promptInput.value = '';
      if (editPromptInput) editPromptInput.value = '';
      if (editImageBase64) editImageBase64.value = '';
      if (editPreview) {
        editPreview.src = '';
        editPreview.style.display = 'none';
      }
      if (editUploadPlaceholder) editUploadPlaceholder.style.display = 'block';
      
      if (canvasImage) canvasImage.style.display = 'none';
      if (quickActions) quickActions.style.display = 'none';
      if (canvasEmpty) canvasEmpty.style.display = 'block';
      
      switchTab('generate');
    });
  }

  // === History ===
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
        <div class="canvas-empty" style="padding: 40px 20px;">
          <div class="canvas-empty-icon" style="width: 60px; height: 60px; font-size: 24px;">
            <i class="iconoir-media-image"></i>
          </div>
          <h3 style="font-size: 14px;">Sin historial</h3>
          <p style="font-size: 12px;">Las imágenes aparecerán aquí</p>
        </div>
      `;
      historyCount.textContent = '0';
      return;
    }

    historyCount.textContent = items.length;
    historyList.innerHTML = items.map(item => {
      const timeAgo = formatTimeAgo(new Date(item.created_at));
      const description = item.input_data?.description || item.title || 'Imagen generada';
      const truncatedDesc = description.length > 40 ? description.substring(0, 40) + '...' : description;
      const outputData = item.output_data || {};
      const imageData = outputData.image;

      return `
        <div class="history-item ${currentExecutionId === item.id ? 'active' : ''}" data-id="${item.id}" data-image="${imageData || ''}">
          <img class="history-thumb" src="${imageData ? `data:image/png;base64,${imageData}` : ''}" alt="" />
          <div class="history-info">
            <div class="history-desc">${escapeHtml(truncatedDesc)}</div>
            <div class="history-meta">
              <span>${timeAgo}</span>
              <span>${item.input_data?.provider || 'qwen'}</span>
            </div>
          </div>
          <button class="history-delete" data-id="${item.id}" title="Eliminar">
            <i class="iconoir-trash"></i>
          </button>
        </div>
      `;
    }).join('');

    // Event listeners
    historyList.querySelectorAll('.history-item').forEach(item => {
      item.addEventListener('click', (e) => {
        if (e.target.closest('.history-delete')) return;
        
        const imageData = item.dataset.image;
        if (imageData) {
          currentImageBase64 = imageData;
          currentExecutionId = item.dataset.id;
          canvasImage.src = `data:image/png;base64,${imageData}`;
          canvasImage.style.display = 'block';
          quickActions.style.display = 'flex';
          canvasEmpty.style.display = 'none';
          
          // Update active state
          historyList.querySelectorAll('.history-item').forEach(i => i.classList.remove('active'));
          item.classList.add('active');
        }
      });
    });

    historyList.querySelectorAll('.history-delete').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const id = btn.dataset.id;
        
        if (!confirm('¿Eliminar esta imagen del historial?')) return;

        try {
          const res = await fetch('/api/gestures/delete.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.CSRF_TOKEN
            },
            body: JSON.stringify({ id: Number(id) }),
            credentials: 'include'
          });

          const data = await res.json();
          if (!res.ok || !data.success) {
            alert('No se ha podido eliminar');
            return;
          }

          loadHistory();
        } catch (err) {
          alert('Error de conexión al eliminar');
        }
      });
    });
  }

  // === Lightbox ===
  if (canvasImage) {
    canvasImage.addEventListener('click', () => {
      if (currentImageBase64 && lightboxImage && lightbox) {
        lightboxImage.src = `data:image/png;base64,${currentImageBase64}`;
        lightbox.classList.add('visible');
        lightbox.style.display = 'flex';
      }
    });
  }

  if (lightboxClose) {
    lightboxClose.addEventListener('click', () => {
      if (lightbox) {
        lightbox.classList.remove('visible');
        lightbox.style.display = 'none';
      }
    });
  }

  if (lightbox) {
    lightbox.addEventListener('click', (e) => {
      if (e.target === lightbox) {
        lightbox.classList.remove('visible');
        lightbox.style.display = 'none';
      }
    });
  }

  // === Event Listeners ===
  if (generateBtn) generateBtn.addEventListener('click', generateImage);
  if (editBtn) editBtn.addEventListener('click', editImage);

  // === Utilities ===
  function formatTimeAgo(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'ahora';
    if (diffMins < 60) return `hace ${diffMins} min`;
    if (diffHours < 24) return `hace ${diffHours}h`;
    if (diffDays === 1) return 'ayer';
    if (diffDays < 7) return `hace ${diffDays} días`;
    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // === Initialize ===
  loadHistory();
})();
