/**
 * Gesto: Editor de Imágenes v2 - Layout de dos columnas
 * Con flujo iterativo de edición y canvas siempre visible
 */
(function() {
  'use strict';

  const GESTURE_TYPE = 'image-editor';

  // === Referencias DOM ===
  const generateBtn = document.getElementById('generate-image-btn');
  const downloadBtn = document.getElementById('download-image-btn');
  const regenerateBtn = document.getElementById('regenerate-image-btn');
  const editResultBtn = document.getElementById('edit-result-btn');
  const descriptionField = document.getElementById('image-description');
  const descriptionLabel = document.getElementById('description-label');
  const summaryText = document.getElementById('summary-text');

  // Canvas states
  const canvasEmpty = document.getElementById('canvas-empty');
  const canvasLoading = document.getElementById('canvas-loading');
  const canvasResult = document.getElementById('canvas-result');
  const generatedImage = document.getElementById('generated-image');

  // Mobile result
  const mobileResult = document.getElementById('mobile-result');
  const mobileGeneratedImage = document.getElementById('mobile-generated-image');
  const mobileResultClose = document.getElementById('mobile-result-close');
  const mobileEditResultBtn = document.getElementById('mobile-edit-result-btn');
  const mobileDownloadBtn = document.getElementById('mobile-download-btn');

  // Mode toggles
  const modeGenerateBtn = document.getElementById('mode-generate');
  const modeEditBtn = document.getElementById('mode-edit');
  const currentModeInput = document.getElementById('current-mode');
  const providerQwenBtn = document.getElementById('provider-qwen');
  const providerNanobananaBtn = document.getElementById('provider-nanobanana');
  const providerFluxBtn = document.getElementById('provider-flux');
  const currentProviderInput = document.getElementById('current-provider');

  const editImagesSection = document.getElementById('edit-images-section');
  const styleOptionsSection = document.getElementById('style-options-section');
  const selectionSummary = document.getElementById('selection-summary');

  // Image uploads
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

  // State
  let sourceImageBase64 = null;
  let targetImageBase64 = null;
  let currentImageBase64 = null;
  let lastPrompt = '';
  let lastInputData = {};

  // === Prompt Maps ===
  const styleMap = {
    '': '',
    'photographic': 'Hyper-realistic professional photography, shot on Sony A7R IV with 35mm G Master lens, 8k resolution, extreme detail, natural skin texture',
    'corporate': 'Professional Silicon Valley corporate style, modern business aesthetic, clean and crisp, high-end commercial look',
    'headshot-pro': 'Professional studio headshot, shot on 85mm f/1.8 lens, shallow depth of field, sharp focus on eyes, soft bokeh, neutral studio background',
    'silicon-valley': 'Silicon Valley executive portrait, navy blue business suit, solid dark gray studio backdrop with subtle vignette, shot on Sony A7III',
    'luxury-product': 'Luxury high-end product photography, commercial advertising style, professional studio lighting, sophisticated reflections',
    '3d-render': 'Ultra-realistic 3D octane render, Ray tracing, 8k, Unreal Engine 5 style, professional CGI',
    'minimalist': 'Minimalist fine art photography, simple geometric balance, clean lines, vast negative space, high contrast'
  };

  const colorMap = {
    '': '',
    'warm': 'Warm cinematic color grading, golden hour hues, soft oranges and ambers',
    'cool': 'Cool professional color palette, crisp teals and blues, clean and modern',
    'bw': 'Classic black and white film photography, high dynamic range, deep blacks and crisp whites',
    'vibrant': 'Vibrant and rich color saturation, bold commercial appeal, eye-catching'
  };

  const lightingMap = {
    '': '',
    'natural': 'Natural diffused daylight, soft window light, realistic illumination',
    'studio': 'Classic three-point studio lighting, professional key and fill lights',
    'dramatic': 'Cinematic dramatic lighting, high contrast, chiaroscuro effect',
    'golden': 'Golden hour sunset lighting, warm glow, long soft shadows'
  };

  const compositionMap = {
    '': '',
    'bokeh': 'Shallow depth of field, exquisite soft bokeh background, sharp focus on subject',
    'closeup': 'Close-up portrait framing, detailed view, professional headshot composition',
    'wide': 'Cinematic wide-angle shot, expansive professional scene'
  };

  const formatMap = {
    '1:1': 'Square aspect ratio (1:1)',
    '3:4': 'Portrait aspect ratio (3:4)',
    '4:3': 'Landscape aspect ratio (4:3)',
    '16:9': 'Widescreen cinematic format (16:9)',
    '9:16': 'Vertical social media format (9:16)'
  };

  // === Mode Toggle ===
  function setMode(mode) {
    if (currentModeInput) currentModeInput.value = mode;
    
    document.querySelectorAll('.mode-toggle-btn').forEach(btn => btn.classList.remove('active'));
    
    if (mode === 'generate') {
      modeGenerateBtn?.classList.add('active');
      editImagesSection?.classList.add('hidden');
      if (descriptionLabel) descriptionLabel.textContent = 'Describe tu imagen';
      if (descriptionField) descriptionField.placeholder = 'Describe la imagen que necesitas...';
      if (generateBtn) generateBtn.querySelector('span').textContent = 'Generar imagen';
    } else {
      modeEditBtn?.classList.add('active');
      editImagesSection?.classList.remove('hidden');
      if (descriptionLabel) descriptionLabel.textContent = '¿Qué cambios quieres hacer?';
      if (descriptionField) descriptionField.placeholder = 'Describe la edición: "Cambia el fondo", "Añade gafas"...';
      if (generateBtn) generateBtn.querySelector('span').textContent = 'Editar imagen';
    }
  }

  modeGenerateBtn?.addEventListener('click', () => setMode('generate'));
  modeEditBtn?.addEventListener('click', () => setMode('edit'));

  // === Provider Toggle ===
  function setProvider(provider) {
    if (currentProviderInput) currentProviderInput.value = provider;
    document.querySelectorAll('.provider-toggle-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`provider-${provider}`)?.classList.add('active');
  }

  providerQwenBtn?.addEventListener('click', () => setProvider('qwen'));
  providerNanobananaBtn?.addEventListener('click', () => setProvider('nanobanana'));
  providerFluxBtn?.addEventListener('click', () => setProvider('flux'));

  // === Image Upload Handlers ===
  function handleImageUpload(input, preview, placeholder, clearBtn, setBase64) {
    if (!input) return;
    
    input.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file || !file.type.startsWith('image/')) return;
      
      const reader = new FileReader();
      reader.onload = (ev) => {
        const base64 = ev.target.result.split(',')[1];
        setBase64(base64);
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
      setBase64(null);
      input.value = '';
      if (preview) {
        preview.src = '';
        preview.classList.add('hidden');
      }
      placeholder?.classList.remove('hidden');
      clearBtn?.classList.add('hidden');
    });
  }

  handleImageUpload(sourceImageInput, sourceImagePreview, sourceImagePlaceholder, sourceImageClear, (b64) => { sourceImageBase64 = b64; });
  handleImageUpload(targetImageInput, targetImagePreview, targetImagePlaceholder, targetImageClear, (b64) => { targetImageBase64 = b64; });

  // === Update Summary ===
  function updateSummary() {
    const format = document.querySelector('input[name="format"]:checked')?.value || '1:1';
    const style = document.querySelector('input[name="style"]:checked')?.value || '';
    const color = document.querySelector('input[name="color"]:checked')?.value || '';
    const lighting = document.querySelector('input[name="lighting"]:checked')?.value || '';
    const composition = document.querySelector('input[name="composition"]:checked')?.value || '';

    const labels = {
      style: { '': '', 'photographic': 'Fotográfico', 'corporate': 'Corporativo', 'headshot-pro': 'Retrato Pro', 'silicon-valley': 'Corp. Pro', 'luxury-product': 'Producto Lujo', '3d-render': '3D', 'minimalist': 'Minimalista' },
      color: { '': '', 'warm': 'Cálido', 'cool': 'Frío', 'bw': 'B/N', 'vibrant': 'Vibrante' },
      lighting: { '': '', 'natural': 'Natural', 'studio': 'Estudio', 'dramatic': 'Dramático', 'golden': 'Dorado' },
      composition: { '': '', 'bokeh': 'Bokeh', 'closeup': 'Primer plano', 'wide': 'General' }
    };

    const parts = [`${format}`];
    if (style) parts.push(labels.style[style]);
    if (color) parts.push(labels.color[color]);
    if (lighting) parts.push(labels.lighting[lighting]);
    if (composition) parts.push(labels.composition[composition]);

    if (summaryText) summaryText.textContent = parts.join(' • ');
  }

  document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', updateSummary);
  });

  // === Build Prompt ===
  function buildPrompt(description, options) {
    const { format, style, color, lighting, composition } = options;
    let prompt = `Create an ultra-realistic, high-resolution masterpiece image: ${description}`;
    
    const specs = [];
    if (formatMap[format]) specs.push(formatMap[format]);
    if (style && styleMap[style]) specs.push(styleMap[style]);
    if (color && colorMap[color]) specs.push(colorMap[color]);
    if (lighting && lightingMap[lighting]) specs.push(lightingMap[lighting]);
    if (composition && compositionMap[composition]) specs.push(compositionMap[composition]);

    if (specs.length > 0) {
      prompt += '\n\nTechnical Specifications:\n- ' + specs.join('\n- ');
    }

    prompt += '\n\nFinal Quality: 8k resolution, photorealistic, cinematic color grading, sharp focus, HDR.';
    prompt += `\n\nSeed: ${Math.floor(Math.random() * 1000000)}`;

    return prompt;
  }

  // === Canvas State Management ===
  function showCanvasState(state) {
    canvasEmpty?.classList.add('hidden');
    canvasLoading?.classList.add('hidden');
    canvasResult?.classList.add('hidden');
    
    if (state === 'empty') canvasEmpty?.classList.remove('hidden');
    else if (state === 'loading') canvasLoading?.classList.remove('hidden');
    else if (state === 'result') canvasResult?.classList.remove('hidden');
  }

  // === Generate Image ===
  async function generateImage() {
    const description = descriptionField?.value.trim();
    const mode = currentModeInput?.value || 'generate';
    
    if (!description) {
      alert(mode === 'generate' ? 'Por favor, describe la imagen' : 'Por favor, describe los cambios');
      descriptionField?.focus();
      return;
    }

    if (mode === 'edit' && !sourceImageBase64) {
      alert('Por favor, sube una imagen fuente para editar');
      return;
    }

    let prompt, inputData;

    if (mode === 'generate') {
      const options = {
        format: document.querySelector('input[name="format"]:checked')?.value || '1:1',
        style: document.querySelector('input[name="style"]:checked')?.value || '',
        color: document.querySelector('input[name="color"]:checked')?.value || '',
        lighting: document.querySelector('input[name="lighting"]:checked')?.value || '',
        composition: document.querySelector('input[name="composition"]:checked')?.value || ''
      };
      prompt = buildPrompt(description, options);
      inputData = { mode: 'generate', description, provider: currentProviderInput?.value || 'qwen', ...options };
    } else {
      prompt = description;
      inputData = {
        mode: 'edit',
        description,
        provider: currentProviderInput?.value || 'qwen',
        source_image: sourceImageBase64,
        target_image: targetImageBase64 || null
      };
    }

    lastPrompt = prompt;
    lastInputData = inputData;

    await sendRequest(prompt, inputData);
  }

  // === Send Request ===
  async function sendRequest(prompt, inputData) {
    showCanvasState('loading');
    if (generateBtn) generateBtn.disabled = true;

    try {
      const res = await fetch('/api/gestures/generate-image.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
        body: JSON.stringify({ gesture_type: GESTURE_TYPE, prompt, input_data: inputData }),
        credentials: 'include'
      });

      const data = await res.json();
      if (generateBtn) generateBtn.disabled = false;

      if (!res.ok || !data.image) {
        showCanvasState('empty');
        alert('Error: ' + (data.error?.message || 'No se pudo generar la imagen'));
        return;
      }

      currentImageBase64 = data.image;
      const imgSrc = `data:image/png;base64,${data.image}`;
      
      if (generatedImage) generatedImage.src = imgSrc;
      if (mobileGeneratedImage) mobileGeneratedImage.src = imgSrc;
      
      showCanvasState('result');
      
      // On mobile, show the modal
      if (window.innerWidth < 1024 && mobileResult) {
        mobileResult.classList.remove('hidden');
      }

    } catch (err) {
      if (generateBtn) generateBtn.disabled = false;
      showCanvasState('empty');
      console.error('Error:', err);
      alert('Error de conexión');
    }
  }

  // === Edit Result (Iterative Flow) ===
  function useResultAsSource() {
    if (!currentImageBase64) return;
    
    // Switch to edit mode
    setMode('edit');
    
    // Set the generated image as source
    sourceImageBase64 = currentImageBase64;
    if (sourceImagePreview) {
      sourceImagePreview.src = `data:image/png;base64,${currentImageBase64}`;
      sourceImagePreview.classList.remove('hidden');
    }
    sourceImagePlaceholder?.classList.add('hidden');
    sourceImageClear?.classList.remove('hidden');
    
    // Clear prompt for new edit
    if (descriptionField) {
      descriptionField.value = '';
      descriptionField.focus();
    }
    
    // Close mobile modal if open
    mobileResult?.classList.add('hidden');
  }

  editResultBtn?.addEventListener('click', useResultAsSource);
  mobileEditResultBtn?.addEventListener('click', useResultAsSource);

  // === Download ===
  function downloadImage() {
    if (!currentImageBase64) return;
    const link = document.createElement('a');
    link.href = `data:image/png;base64,${currentImageBase64}`;
    link.download = `imagen-${Date.now()}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  downloadBtn?.addEventListener('click', downloadImage);
  mobileDownloadBtn?.addEventListener('click', downloadImage);

  // === Regenerate ===
  regenerateBtn?.addEventListener('click', () => {
    if (lastPrompt) sendRequest(lastPrompt, lastInputData);
  });

  // === Generate Button ===
  generateBtn?.addEventListener('click', generateImage);

  // === Mobile Result Close ===
  mobileResultClose?.addEventListener('click', () => {
    mobileResult?.classList.add('hidden');
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

  // === Initial state ===
  updateSummary();
  showCanvasState('empty');

})();
