/**
 * Gesto: Editor de Imágenes con IA (v2 - Nueva UX)
 * Layout: 3 columnas con imagen central
 * Genera y edita imágenes usando Qwen, Nanobanana o FLUX
 * Incluye edición iterativa de imágenes generadas
 */
(function() {
  'use strict';

  const GESTURE_TYPE = 'image-editor';

  // === DOM References ===
  const descriptionField = document.getElementById('image-description');
  const generateBtn = document.getElementById('generate-image-btn');
  const downloadBtn = document.getElementById('download-image-btn');
  const regenerateBtn = document.getElementById('regenerate-image-btn');
  const editThisImageBtn = document.getElementById('edit-this-image-btn');
  const fullscreenBtn = document.getElementById('fullscreen-btn');
  const newImageBtn = document.getElementById('new-image-btn');
  const historyList = document.getElementById('history-list');
  const summaryText = document.getElementById('summary-text');

  // Mode toggles
  const modeGenerateBtn = document.getElementById('mode-generate');
  const modeEditBtn = document.getElementById('mode-edit');
  const currentModeInput = document.getElementById('current-mode');

  // Provider toggles
  const providerQwenBtn = document.getElementById('provider-qwen');
  const providerNanobananaBtn = document.getElementById('provider-nanobanana');
  const providerFluxBtn = document.getElementById('provider-flux');
  const currentProviderInput = document.getElementById('current-provider');

  // UI Sections
  const imagePlaceholder = document.getElementById('image-placeholder');
  const editSourceSection = document.getElementById('edit-source-section');
  const imageResult = document.getElementById('image-result');
  const imageLoading = document.getElementById('image-loading');
  const imageCaption = document.getElementById('image-caption');

  // Image elements
  const generatedImage = document.getElementById('generated-image');
  const sourceImageInput = document.getElementById('source-image-input');
  const sourceImagePreview = document.getElementById('source-image-preview');
  const sourceImagePlaceholder = document.getElementById('source-image-placeholder');
  const sourceImageClear = document.getElementById('source-image-clear');
  const targetImageInput = document.getElementById('target-image-input');
  const targetImagePreview = document.getElementById('target-image-preview');
  const targetImagePlaceholder = document.getElementById('target-image-placeholder');
  const targetImageClear = document.getElementById('target-image-clear');

  // Lightbox
  const lightbox = document.getElementById('image-lightbox');
  const lightboxImage = document.getElementById('lightbox-image');
  const lightboxClose = document.getElementById('lightbox-close');

  // === State ===
  let sourceImageBase64 = null;
  let targetImageBase64 = null;
  let currentImageBase64 = null;
  let lastPrompt = '';
  let lastInputData = {};

  // === Prompt Maps ===
  const styleMap = {
    '': '',
    'photographic': 'Hyper-realistic professional photography, shot on Sony A7R IV with 35mm G Master lens, 8k resolution, extreme detail',
    'digital-art': 'High-end digital art illustration, intricate details, vibrant colors, clean vector lines, trending on ArtStation, 8k',
    'corporate': 'Professional Silicon Valley corporate style, modern business aesthetic, clean and crisp',
    'minimalist': 'Minimalist fine art photography, simple geometric balance, clean lines, vast negative space',
    '3d-render': 'Ultra-realistic 3D octane render, Ray tracing, 8k, Unreal Engine 5 style',
    'flat-design': 'Premium flat design illustration, modern corporate palette, clean shapes',
    'isometric': 'Isometric 3D illustration, professional architectural visualization style',
    'headshot-pro': 'Professional studio headshot, 85mm f/1.8 lens, shallow depth of field, sharp focus on eyes',
    'luxury-product': 'Luxury high-end product photography, commercial advertising style, professional studio lighting'
  };

  const colorMap = {
    '': '',
    'warm': 'Warm cinematic color grading, golden hour hues, soft oranges and ambers',
    'cool': 'Cool professional color palette, crisp teals and blues, modern aesthetic',
    'corporate': 'Ebonia corporate color scheme (#23AAC5 accent), professional blue-teal tones',
    'monochrome': 'Fine art monochromatic scheme, rich tonal range',
    'pastel': 'Soft sophisticated pastel tones, muted and gentle professional colors',
    'bw': 'Classic black and white film photography, high dynamic range',
    'vibrant': 'Vibrant and rich color saturation, bold commercial appeal'
  };

  const lightingMap = {
    '': '',
    'natural': 'Natural diffused daylight, soft window light',
    'studio': 'Classic three-point studio lighting setup, professional key and fill lights',
    'dramatic': 'Cinematic dramatic lighting, high contrast, chiaroscuro effect',
    'soft': 'Bright and airy soft diffused lighting, gentle illumination',
    'backlight': 'Elegant rim lighting, subtle hair light, professional separation',
    'golden': 'Golden hour sunset lighting, warm glow, long soft shadows',
    'volumetric': 'Volumetric lighting with subtle light rays, atmospheric depth'
  };

  const compositionMap = {
    '': '',
    'bokeh': 'Shallow depth of field, exquisite soft bokeh background',
    'closeup': 'Close-up portrait framing, detailed view',
    'wide': 'Cinematic wide-angle shot, expansive professional scene',
    'above': 'Bird\'s eye view, professional top-down perspective',
    'below': 'Low angle heroic shot, dramatic upward perspective',
    'macro': 'Extreme macro photography, hyper-detailed textures',
    'negative-space': 'Fine art composition with vast negative space'
  };

  const formatMap = {
    '1:1': 'Square format (1:1)',
    '3:4': 'Portrait format (3:4)',
    '4:3': 'Landscape format (4:3)',
    '16:9': 'Widescreen format (16:9)',
    '9:16': 'Vertical social media format (9:16)'
  };

  // === Mode Toggle ===
  function setMode(mode) {
    if (!currentModeInput) return;
    currentModeInput.value = mode;

    if (mode === 'generate') {
      modeGenerateBtn?.classList.add('active');
      modeEditBtn?.classList.remove('active');
      if (descriptionField) {
        descriptionField.placeholder = 'Describe la imagen que quieres crear...';
      }
      if (generateBtn) {
        generateBtn.innerHTML = '<i class="iconoir-sparks"></i><span class="hidden sm:inline">Generar</span>';
      }
      // Show appropriate section
      showGenerateUI();
    } else {
      modeEditBtn?.classList.add('active');
      modeGenerateBtn?.classList.remove('active');
      if (descriptionField) {
        descriptionField.placeholder = 'Describe los cambios: "Añade gafas de sol", "Cambia el fondo a playa"...';
      }
      if (generateBtn) {
        generateBtn.innerHTML = '<i class="iconoir-edit"></i><span class="hidden sm:inline">Editar</span>';
      }
      // Show edit UI
      showEditUI();
    }
  }

  function showGenerateUI() {
    imagePlaceholder?.classList.remove('hidden');
    editSourceSection?.classList.add('hidden');
    // Keep image result visible if there's an image
    if (!currentImageBase64) {
      imageResult?.classList.add('hidden');
    }
  }

  function showEditUI() {
    imagePlaceholder?.classList.add('hidden');
    // If we have a source image, show preview, otherwise show upload
    if (sourceImageBase64) {
      editSourceSection?.classList.remove('hidden');
      sourceImagePreview?.classList.remove('hidden');
      sourceImagePlaceholder?.classList.add('hidden');
      sourceImageClear?.classList.remove('hidden');
    } else {
      editSourceSection?.classList.remove('hidden');
    }
    imageResult?.classList.add('hidden');
  }

  modeGenerateBtn?.addEventListener('click', () => setMode('generate'));
  modeEditBtn?.addEventListener('click', () => setMode('edit'));

  // === Provider Toggle ===
  function setProvider(provider) {
    if (!currentProviderInput) return;
    currentProviderInput.value = provider;

    document.querySelectorAll('.provider-toggle-btn').forEach(btn => btn.classList.remove('active'));

    if (provider === 'qwen') providerQwenBtn?.classList.add('active');
    else if (provider === 'nanobanana') providerNanobananaBtn?.classList.add('active');
    else if (provider === 'flux') providerFluxBtn?.classList.add('active');
  }

  providerQwenBtn?.addEventListener('click', () => setProvider('qwen'));
  providerNanobananaBtn?.addEventListener('click', () => setProvider('nanobanana'));
  providerFluxBtn?.addEventListener('click', () => setProvider('flux'));

  // === Image Upload Handler ===
  function setupSourceImageUpload() {
    if (!sourceImageInput) return;

    sourceImageInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;

      if (!file.type.startsWith('image/')) {
        alert('Por favor, selecciona una imagen válida');
        return;
      }

      const reader = new FileReader();
      reader.onload = (ev) => {
        sourceImageBase64 = ev.target.result.split(',')[1];
        if (sourceImagePreview) {
          sourceImagePreview.src = ev.target.result;
          sourceImagePreview.classList.remove('hidden');
        }
        sourceImagePlaceholder?.classList.add('hidden');
        sourceImageClear?.classList.remove('hidden');
      };
      reader.readAsDataURL(file);
    });

    sourceImageClear?.addEventListener('click', (e) => {
      e.stopPropagation();
      clearSourceImage();
    });
  }

  function clearSourceImage() {
    sourceImageBase64 = null;
    if (sourceImageInput) sourceImageInput.value = '';
    if (sourceImagePreview) {
      sourceImagePreview.src = '';
      sourceImagePreview.classList.add('hidden');
    }
    sourceImagePlaceholder?.classList.remove('hidden');
    sourceImageClear?.classList.add('hidden');
  }

  function setupTargetImageUpload() {
    if (!targetImageInput) return;

    targetImageInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;

      if (!file.type.startsWith('image/')) {
        alert('Por favor, selecciona una imagen válida');
        return;
      }

      const reader = new FileReader();
      reader.onload = (ev) => {
        targetImageBase64 = ev.target.result.split(',')[1];
        if (targetImagePreview) {
          targetImagePreview.src = ev.target.result;
          targetImagePreview.classList.remove('hidden');
        }
        targetImagePlaceholder?.classList.add('hidden');
        targetImageClear?.classList.remove('hidden');
      };
      reader.readAsDataURL(file);
    });

    targetImageClear?.addEventListener('click', (e) => {
      e.stopPropagation();
      clearTargetImage();
    });
  }

  function clearTargetImage() {
    targetImageBase64 = null;
    if (targetImageInput) targetImageInput.value = '';
    if (targetImagePreview) {
      targetImagePreview.src = '';
      targetImagePreview.classList.add('hidden');
    }
    targetImagePlaceholder?.classList.remove('hidden');
    targetImageClear?.classList.add('hidden');
  }

  function setSourceImageFromBase64(base64) {
    sourceImageBase64 = base64;
    if (sourceImagePreview) {
      sourceImagePreview.src = `data:image/png;base64,${base64}`;
      sourceImagePreview.classList.remove('hidden');
    }
    sourceImagePlaceholder?.classList.add('hidden');
    sourceImageClear?.classList.remove('hidden');
  }

  setupSourceImageUpload();
  setupTargetImageUpload();

  // === Update Summary ===
  function updateSummary() {
    const format = document.querySelector('input[name="format"]:checked')?.value || '';
    const style = document.querySelector('input[name="style"]:checked')?.value || '';
    const color = document.querySelector('input[name="color"]:checked')?.value || '';
    const lighting = document.querySelector('input[name="lighting"]:checked')?.value || '';
    const composition = document.querySelector('input[name="composition"]:checked')?.value || '';

    const parts = [];

    if (format) parts.push(`Formato: ${format}`);

    const labels = {
      style: { 'photographic': 'Estilo: Foto', 'digital-art': 'Estilo: Digital', 'corporate': 'Estilo: Corp', 'minimalist': 'Estilo: Min', '3d-render': 'Estilo: 3D', 'flat-design': 'Estilo: Flat', 'isometric': 'Estilo: Iso', 'headshot-pro': 'Estilo: Retrato', 'luxury-product': 'Estilo: Producto' },
      color: { 'warm': 'Color: Cálido', 'cool': 'Color: Frío', 'corporate': 'Color: Corp', 'monochrome': 'Color: Mono', 'pastel': 'Color: Pastel', 'bw': 'Color: B/N', 'vibrant': 'Color: Vibr' },
      lighting: { 'natural': 'Luz: Natural', 'studio': 'Luz: Estudio', 'dramatic': 'Luz: Drama', 'soft': 'Luz: Suave', 'backlight': 'Luz: Contra', 'golden': 'Luz: Dorada', 'volumetric': 'Luz: Volum' },
      composition: { 'bokeh': 'Compos: Bokeh', 'closeup': 'Compos: Cerca', 'wide': 'Compos: Amplio', 'above': 'Compos: Cenital', 'below': 'Compos: Bajo', 'macro': 'Compos: Macro', 'negative-space': 'Compos: Neg' }
    };

    if (style && labels.style[style]) parts.push(labels.style[style]);
    if (color && labels.color[color]) parts.push(labels.color[color]);
    if (lighting && labels.lighting[lighting]) parts.push(labels.lighting[lighting]);
    if (composition && labels.composition[composition]) parts.push(labels.composition[composition]);

    if (summaryText) {
      if (parts.length > 0) {
        summaryText.textContent = parts.join(' · ');
      } else {
        summaryText.textContent = 'Decisión del modelo (IA)';
      }
    }
  }

  document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', updateSummary);
  });

  // === Build Prompt ===
  function buildPrompt(description, options) {
    const { format, style, color, lighting, composition } = options;
    const mode = currentModeInput?.value || 'generate';

    let prompt = mode === 'edit'
      ? `Keep the facial features and identity of the person in the input image exactly consistent. ${description}`
      : `Create an ultra-realistic, high-resolution masterpiece image: ${description}`;

    const specs = [];
    if (formatMap[format]) specs.push(formatMap[format]);
    if (style && styleMap[style]) specs.push(styleMap[style]);
    if (color && colorMap[color]) specs.push(colorMap[color]);
    if (lighting && lightingMap[lighting]) specs.push(lightingMap[lighting]);
    if (composition && compositionMap[composition]) specs.push(compositionMap[composition]);

    if (specs.length > 0) {
      prompt += '\n\nTechnical Specifications and Style:\n- ' + specs.join('\n- ');
    }

    prompt += '\n\nFinal Quality: 8k resolution, photorealistic, cinematic color grading, sharp focus, extreme attention to detail, high dynamic range (HDR).';
    prompt += `\n\nInternal Seed: ${Math.floor(Math.random() * 1000000)}`;

    return prompt;
  }

  // === Generate Image ===
  generateBtn?.addEventListener('click', async () => {
    await generateImage();
  });

  // Enter to submit (Cmd/Ctrl + Enter)
  descriptionField?.addEventListener('keydown', async (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      e.preventDefault();
      await generateImage();
    }
  });

  async function generateImage() {
    const description = descriptionField?.value.trim();
    const mode = currentModeInput?.value || 'generate';

    if (!description) {
      alert(mode === 'generate' ? 'Por favor, describe la imagen que quieres crear' : 'Por favor, describe los cambios que quieres hacer');
      descriptionField?.focus();
      return;
    }

    if (mode === 'edit' && !sourceImageBase64) {
      alert('Por favor, sube una imagen fuente para editar');
      return;
    }

    const options = {
      format: document.querySelector('input[name="format"]:checked')?.value || '1:1',
      style: document.querySelector('input[name="style"]:checked')?.value || '',
      color: document.querySelector('input[name="color"]:checked')?.value || '',
      lighting: document.querySelector('input[name="lighting"]:checked')?.value || '',
      composition: document.querySelector('input[name="composition"]:checked')?.value || ''
    };

    let prompt, inputData;

    if (mode === 'generate') {
      prompt = buildPrompt(description, options);
      inputData = { mode: 'generate', description, provider: currentProviderInput?.value || 'qwen', ...options };
    } else {
      prompt = description;
      inputData = { mode: 'edit', description, provider: currentProviderInput?.value || 'qwen', source_image: sourceImageBase64, target_image: targetImageBase64 || null };
    }

    lastPrompt = prompt;
    lastInputData = inputData;

    await sendRequest(prompt, inputData);
  }

  // === Send Request ===
  async function sendRequest(prompt, inputData) {
    // Hide all sections, show loading
    imagePlaceholder?.classList.add('hidden');
    editSourceSection?.classList.add('hidden');
    imageResult?.classList.add('hidden');
    imageLoading?.classList.remove('hidden');
    if (generateBtn) generateBtn.disabled = true;

    try {
      const res = await fetch('/api/gestures/generate-image.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
        body: JSON.stringify({ gesture_type: GESTURE_TYPE, prompt, input_data: inputData }),
        credentials: 'include'
      });

      const data = await res.json();
      imageLoading?.classList.add('hidden');
      if (generateBtn) generateBtn.disabled = false;

      if (!res.ok || !data.image) {
        alert('Error al generar la imagen: ' + (data.error?.message || 'Error desconocido'));
        // Restore UI
        if (currentModeInput?.value === 'generate') {
          imagePlaceholder?.classList.remove('hidden');
        } else {
          editSourceSection?.classList.remove('hidden');
        }
        return;
      }

      currentImageBase64 = data.image;
      if (generatedImage) generatedImage.src = `data:image/png;base64,${data.image}`;
      imageResult?.classList.remove('hidden');

      if (data.text && imageCaption) {
        imageCaption.textContent = data.text;
        imageCaption.classList.remove('hidden');
      } else {
        imageCaption?.classList.add('hidden');
      }

      loadHistory();

    } catch (err) {
      imageLoading?.classList.add('hidden');
      if (generateBtn) generateBtn.disabled = false;
      console.error('Error:', err);
      alert('Error de conexión al generar la imagen');
      // Restore UI
      if (currentModeInput?.value === 'generate') {
        imagePlaceholder?.classList.remove('hidden');
      } else {
        editSourceSection?.classList.remove('hidden');
      }
    }
  }

  // === Edit This Image (Iterative Editing) ===
  editThisImageBtn?.addEventListener('click', () => {
    if (!currentImageBase64) return;

    // Set the generated image as source image
    setSourceImageFromBase64(currentImageBase64);

    // Switch to edit mode
    setMode('edit');

    // Clear description for new edit instructions
    if (descriptionField) {
      descriptionField.value = '';
      descriptionField.focus();
    }
  });

  // === Download ===
  downloadBtn?.addEventListener('click', () => {
    if (!currentImageBase64) return;
    const link = document.createElement('a');
    link.href = `data:image/png;base64,${currentImageBase64}`;
    link.download = `ebonia-image-${Date.now()}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  });

  // === Regenerate ===
  regenerateBtn?.addEventListener('click', () => {
    if (lastPrompt) sendRequest(lastPrompt, lastInputData);
  });

  // === Lightbox ===
  function openLightbox() {
    if (currentImageBase64 && lightbox && lightboxImage) {
      lightboxImage.src = `data:image/png;base64,${currentImageBase64}`;
      lightbox.classList.remove('hidden');
      lightbox.classList.add('flex');
    }
  }

  function closeLightbox() {
    lightbox?.classList.add('hidden');
    lightbox?.classList.remove('flex');
  }

  generatedImage?.addEventListener('click', openLightbox);
  fullscreenBtn?.addEventListener('click', openLightbox);
  lightboxClose?.addEventListener('click', closeLightbox);

  lightbox?.addEventListener('click', (e) => {
    if (e.target === lightbox) closeLightbox();
  });

  // ESC to close lightbox
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && lightbox && !lightbox.classList.contains('hidden')) {
      closeLightbox();
    }
  });

  // === History ===
  async function loadHistory() {
    if (!historyList) return;

    try {
      const res = await fetch(`/api/gestures/history.php?type=${GESTURE_TYPE}`, { credentials: 'include' });
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
    if (!historyList) return;

    if (items.length === 0) {
      historyList.innerHTML = `
        <div class="p-6 text-center">
          <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
            <i class="iconoir-media-image text-xl text-slate-400"></i>
          </div>
          <p class="text-sm text-slate-500">Aún no has generado imágenes</p>
          <p class="text-xs text-slate-400 mt-1">Usa el formulario para empezar</p>
        </div>
      `;
      return;
    }

    historyList.innerHTML = items.map(item => {
      const timeAgo = formatTimeAgo(new Date(item.created_at));
      const description = item.title || 'Imagen generada';
      
      // Default colors if we don't have provider info in the list
      const providerClass = 'bg-slate-100 text-slate-600';
      const providerLabel = 'IA';

      return `
        <div class="history-item w-full p-3 hover:bg-slate-50 border-b border-slate-100 transition-colors group flex items-start gap-2" data-id="${item.id}">
          <i class="iconoir-media-image text-amber-500 mt-0.5"></i>
          <div class="flex-1 min-w-0 cursor-pointer history-item-main">
            <p class="text-sm font-medium text-slate-700 truncate group-hover:text-amber-700">${escapeHtml(description)}</p>
            <div class="flex items-center gap-2 mt-1">
              <span class="text-[10px] px-1.5 py-0.5 rounded ${providerClass}">${providerLabel}</span>
              <span class="text-[10px] text-slate-400">${timeAgo}</span>
            </div>
          </div>
          <button class="history-item-delete opacity-0 group-hover:opacity-100 transition-opacity text-slate-300 hover:text-red-500 p-1 rounded" data-id="${item.id}" title="Eliminar">
            <i class="iconoir-trash"></i>
          </button>
        </div>
      `;
    }).join('');

    // Click to load
    historyList.querySelectorAll('.history-item-main').forEach(el => {
      const id = el.parentElement.dataset.id;
      el.addEventListener('click', () => loadExecution(id));
    });

    // Delete
    historyList.querySelectorAll('.history-item-delete').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = btn.dataset.id;
        deleteExecution(id);
      });
    });
  }

  async function loadExecution(id) {
    try {
      const res = await fetch(`/api/gestures/get.php?id=${id}`, { credentials: 'include' });
      const data = await res.json();

      if (!res.ok || !data.execution) {
        alert('Error al cargar la imagen');
        return;
      }

      const exec = data.execution;
      const outputData = exec.output_data || {};

      if (outputData.image) {
        currentImageBase64 = outputData.image;
        if (generatedImage) generatedImage.src = `data:image/png;base64,${outputData.image}`;
        
        // Hide other sections, show result
        imagePlaceholder?.classList.add('hidden');
        editSourceSection?.classList.add('hidden');
        imageResult?.classList.remove('hidden');

        if (outputData.text && imageCaption) {
          imageCaption.textContent = outputData.text;
          imageCaption.classList.remove('hidden');
        } else {
          imageCaption?.classList.add('hidden');
        }
      }

      const inputData = exec.input_data || {};
      if (inputData.description && descriptionField) descriptionField.value = inputData.description;

      // Restore options
      ['format', 'style', 'color', 'lighting', 'composition'].forEach(field => {
        if (inputData[field] !== undefined) {
          const radio = document.querySelector(`input[name="${field}"][value="${inputData[field]}"]`);
          if (radio) radio.checked = true;
        }
      });

      updateSummary();
      lastInputData = inputData;

      // Set mode to generate (since we're viewing a result)
      setMode('generate');

    } catch (err) {
      alert('Error de conexión');
    }
  }

  async function deleteExecution(id) {
    if (!confirm('¿Eliminar esta imagen del historial?')) return;

    try {
      const res = await fetch('/api/gestures/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
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
  }

  // === New Image ===
  newImageBtn?.addEventListener('click', () => {
    resetUI();
  });

  function resetUI() {
    // Clear state
    currentImageBase64 = null;
    sourceImageBase64 = null;
    targetImageBase64 = null;
    lastPrompt = '';
    lastInputData = {};

    // Clear form
    if (descriptionField) descriptionField.value = '';
    clearSourceImage();
    clearTargetImage();

    // Reset all radios to defaults
    document.querySelectorAll('input[name="format"][value=""]').forEach(r => r.checked = true);
    document.querySelectorAll('input[name="style"][value=""]').forEach(r => r.checked = true);
    document.querySelectorAll('input[name="color"][value=""]').forEach(r => r.checked = true);
    document.querySelectorAll('input[name="lighting"][value=""]').forEach(r => r.checked = true);
    document.querySelectorAll('input[name="composition"][value=""]').forEach(r => r.checked = true);

    updateSummary();

    // Set mode to generate
    setMode('generate');

    // Focus description
    descriptionField?.focus();
  }

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
  updateSummary();
  loadHistory();
  setMode('generate');
})();
