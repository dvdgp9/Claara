/**
 * Gesto: Editor de Imágenes con IA
 * Genera y edita imágenes usando Qwen, Nanobanana o FLUX
 */
(function() {
  'use strict';

  const GESTURE_TYPE = 'image-editor';

  // === DOM References (matching current HTML IDs) ===
  const form = document.getElementById('image-editor-form');
  const descriptionField = document.getElementById('image-description');
  const descriptionLabel = document.getElementById('description-label');
  const generateBtn = document.getElementById('generate-image-btn');
  const downloadBtn = document.getElementById('download-image-btn');
  const regenerateBtn = document.getElementById('regenerate-image-btn');
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

  // Edit section
  const editImagesSection = document.getElementById('edit-images-section');
  const styleOptionsSection = document.getElementById('style-options-section');
  const selectionSummary = document.getElementById('selection-summary');

  // Image uploads for edit mode
  const sourceImageInput = document.getElementById('source-image-input');
  const sourceImagePreview = document.getElementById('source-image-preview');
  const sourceImagePlaceholder = document.getElementById('source-image-placeholder');
  const sourceImageClear = document.getElementById('source-image-clear');
  const targetImageInput = document.getElementById('target-image-input');
  const targetImagePreview = document.getElementById('target-image-preview');
  const targetImagePlaceholder = document.getElementById('target-image-placeholder');
  const targetImageClear = document.getElementById('target-image-clear');

  // Results
  const imageResult = document.getElementById('image-result');
  const generatedImage = document.getElementById('generated-image');
  const imageCaption = document.getElementById('image-caption');
  const imageLoading = document.getElementById('image-loading');

  // Lightbox
  const lightbox = document.getElementById('image-lightbox');
  const lightboxImage = document.getElementById('lightbox-image');
  const lightboxClose = document.getElementById('lightbox-close');

  // Tabs
  const optionTabs = document.querySelectorAll('.option-tab');
  const tabContents = document.querySelectorAll('.tab-content');

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
    'silicon-valley': 'Silicon Valley executive portrait, navy blue business suit, professional studio backdrop',
    'luxury-product': 'Luxury high-end product photography, commercial advertising style, professional studio lighting'
  };

  const colorMap = {
    '': '',
    'warm': 'Warm cinematic color grading, golden hour hues, soft oranges and ambers',
    'cool': 'Cool professional color palette, crisp teals and blues, modern aesthetic',
    'corporate': 'Claara corporate color scheme (#B7C9F2 accent), professional blue-teal tones',
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
      editImagesSection?.classList.add('hidden');
      if (descriptionLabel) descriptionLabel.textContent = 'What image do you want to create?';
      if (descriptionField) descriptionField.placeholder = 'Describe the image you need. Be specific: objects, scene, atmosphere, colors...';
      if (generateBtn) generateBtn.innerHTML = '<i class="iconoir-sparks"></i><span>Generate image</span>';
    } else {
      modeEditBtn?.classList.add('active');
      modeGenerateBtn?.classList.remove('active');
      editImagesSection?.classList.remove('hidden');
      if (descriptionLabel) descriptionLabel.textContent = 'What changes do you want to make?';
      if (descriptionField) descriptionField.placeholder = 'Describe the edit: "Change the background", "Add sunglasses"...';
      if (generateBtn) generateBtn.innerHTML = '<i class="iconoir-edit"></i><span>Edit image</span>';
    }
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

  // === Image Upload Handlers ===
  function setupImageUpload(input, preview, placeholder, clearBtn, setBase64Fn) {
    if (!input) return;

    input.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;

      if (!file.type.startsWith('image/')) {
        alert('Please select a valid image');
        return;
      }

      const reader = new FileReader();
      reader.onload = (ev) => {
        const base64 = ev.target.result.split(',')[1];
        setBase64Fn(base64);
        if (preview) {
          preview.src = ev.target.result;
          preview.classList.remove('hidden');
        }
        placeholder?.classList.add('hidden');
        clearBtn?.classList.remove('hidden');
      };
      reader.readAsDataURL(file);
    });

    clearBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      setBase64Fn(null);
      input.value = '';
      if (preview) {
        preview.src = '';
        preview.classList.add('hidden');
      }
      placeholder?.classList.remove('hidden');
      clearBtn?.classList.add('hidden');
    });
  }

  setupImageUpload(sourceImageInput, sourceImagePreview, sourceImagePlaceholder, sourceImageClear, (b64) => { sourceImageBase64 = b64; });
  setupImageUpload(targetImageInput, targetImagePreview, targetImagePlaceholder, targetImageClear, (b64) => { targetImageBase64 = b64; });

  // === Tab Navigation ===
  optionTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const targetTab = tab.dataset.tab;

      optionTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      tabContents.forEach(content => content.classList.add('hidden'));
      document.getElementById(`tab-${targetTab}`)?.classList.remove('hidden');
    });
  });

  // === Update Summary ===
  function updateSummary() {
    const format = document.querySelector('input[name="format"]:checked')?.value || '1:1';
    const style = document.querySelector('input[name="style"]:checked')?.value || '';
    const color = document.querySelector('input[name="color"]:checked')?.value || '';
    const lighting = document.querySelector('input[name="lighting"]:checked')?.value || '';
    const composition = document.querySelector('input[name="composition"]:checked')?.value || '';

    const parts = [`Formato ${format}`];

    const labels = {
      style: { 'photographic': 'Photographic', 'digital-art': 'Digital Art', 'corporate': 'Corporate', 'minimalist': 'Minimalist', '3d-render': '3D Render', 'flat-design': 'Flat Design', 'isometric': 'Isometric', 'headshot-pro': 'Pro Portrait', 'silicon-valley': 'Pro Corporate', 'luxury-product': 'Luxury Product' },
      color: { 'warm': 'Warm', 'cool': 'Cool', 'corporate': 'Corporate', 'monochrome': 'Monochrome', 'pastel': 'Pastel', 'bw': 'B/W', 'vibrant': 'Vibrant' },
      lighting: { 'natural': 'Natural light', 'studio': 'Studio', 'dramatic': 'Dramatic', 'soft': 'Soft', 'backlight': 'Backlight', 'golden': 'Golden hour', 'volumetric': 'Volumetric' },
      composition: { 'bokeh': 'Bokeh', 'closeup': 'Primer plano', 'wide': 'Plano general', 'above': 'Cenital', 'below': 'Contrapicado', 'macro': 'Macro', 'negative-space': 'Espacio negativo' }
    };

    if (style && labels.style[style]) parts.push(labels.style[style]);
    if (color && labels.color[color]) parts.push(labels.color[color]);
    if (lighting && labels.lighting[lighting]) parts.push(labels.lighting[lighting]);
    if (composition && labels.composition[composition]) parts.push(labels.composition[composition]);

    if (summaryText) summaryText.textContent = parts.join(' • ');
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

  // === Form Submit ===
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    await generateImage();
  });

  // === Generate Image ===
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

      imageResult?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      loadHistory();

    } catch (err) {
      imageLoading?.classList.add('hidden');
      if (generateBtn) generateBtn.disabled = false;
      console.error('Error:', err);
      alert('Connection error while generating the image');
    }
  }

  // === Download ===
  downloadBtn?.addEventListener('click', () => {
    if (!currentImageBase64) return;
    const link = document.createElement('a');
    link.href = `data:image/png;base64,${currentImageBase64}`;
    link.download = `claara-image-${Date.now()}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  });

  // === Regenerate ===
  regenerateBtn?.addEventListener('click', () => {
    if (lastPrompt) sendRequest(lastPrompt, lastInputData);
  });

  // === Lightbox ===
  generatedImage?.addEventListener('click', () => {
    if (currentImageBase64 && lightbox && lightboxImage) {
      lightboxImage.src = `data:image/png;base64,${currentImageBase64}`;
      lightbox.classList.remove('hidden');
      lightbox.classList.add('flex');
    }
  });

  lightboxClose?.addEventListener('click', () => {
    lightbox?.classList.add('hidden');
    lightbox?.classList.remove('flex');
  });

  lightbox?.addEventListener('click', (e) => {
    if (e.target === lightbox) {
      lightbox.classList.add('hidden');
      lightbox.classList.remove('flex');
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
      historyList.innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Connection error</div>';
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
          <p class="text-sm text-slate-500">You have not generated images yet</p>
          <p class="text-xs text-slate-400 mt-1">Usa el formulario para empezar</p>
        </div>
      `;
      return;
    }

    historyList.innerHTML = items.map(item => {
      const timeAgo = formatTimeAgo(new Date(item.created_at));
      const description = item.input_data?.description || item.title || 'Imagen generada';
      const truncatedDesc = description.length > 40 ? description.substring(0, 40) + '...' : description;

      return `
        <div class="history-item w-full p-3 hover:bg-slate-50 border-b border-slate-100 transition-colors group flex items-start gap-2 cursor-pointer" data-id="${item.id}">
          <i class="iconoir-media-image text-amber-500 mt-0.5"></i>
          <div class="flex-1 min-w-0 history-item-main">
            <p class="text-sm font-medium text-slate-700 truncate group-hover:text-amber-700">${escapeHtml(truncatedDesc)}</p>
            <div class="flex items-center gap-2 mt-1">
              <span class="text-[10px] text-slate-400">${timeAgo}</span>
            </div>
          </div>
          <button class="history-item-delete opacity-0 group-hover:opacity-100 transition-opacity text-slate-300 hover:text-red-500 p-1 rounded" data-id="${item.id}" title="Delete">
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
        deleteExecution(btn.dataset.id);
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

      ['format', 'style', 'color', 'lighting', 'composition'].forEach(field => {
        if (inputData[field] !== undefined) {
          const radio = document.querySelector(`input[name="${field}"][value="${inputData[field]}"]`);
          if (radio) radio.checked = true;
        }
      });

      updateSummary();
      lastInputData = inputData;
      imageResult?.scrollIntoView({ behavior: 'smooth', block: 'start' });

    } catch (err) {
      alert('Connection error');
    }
  }

  async function deleteExecution(id) {
    if (!confirm('Delete this image from history?')) return;

    try {
      const res = await fetch('/api/gestures/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
        body: JSON.stringify({ id: Number(id) }),
        credentials: 'include'
      });

      const data = await res.json();
      if (!res.ok || !data.success) {
        alert('Could not delete');
        return;
      }

      loadHistory();
    } catch (err) {
      alert('Connection error while deleting');
    }
  }

  // === New Image ===
  newImageBtn?.addEventListener('click', () => {
    form?.reset();
    imageResult?.classList.add('hidden');
    currentImageBase64 = null;
    sourceImageBase64 = null;
    targetImageBase64 = null;
    updateSummary();
    descriptionField?.focus();
    form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  // === Utilities ===
  function formatTimeAgo(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'now';
    if (diffMins < 60) return `${diffMins} min ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays === 1) return 'yesterday';
    if (diffDays < 7) return `${diffDays} days ago`;
    return date.toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // === Initialize ===
  updateSummary();
  loadHistory();
})();
