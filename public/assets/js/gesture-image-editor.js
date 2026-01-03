/**
 * Gesto: Editor de Imágenes con Nanobanana 🍌
 * Genera imágenes usando selectores de formato, estilo, color, iluminación y composición
 */
(function() {
  'use strict';

  const GESTURE_TYPE = 'image-editor';

  // === Referencias DOM ===
  const form = document.getElementById('image-editor-form');
  const imageResult = document.getElementById('image-result');
  const imageContainer = document.getElementById('image-container');
  const generatedImage = document.getElementById('generated-image');
  const imageCaption = document.getElementById('image-caption');
  const imageLoading = document.getElementById('image-loading');
  const generateBtn = document.getElementById('generate-image-btn');
  const downloadBtn = document.getElementById('download-image-btn');
  const regenerateBtn = document.getElementById('regenerate-image-btn');
  const historyList = document.getElementById('history-list');
  const newImageBtn = document.getElementById('new-image-btn');
  const descriptionField = document.getElementById('image-description');
  const descriptionLabel = document.getElementById('description-label');
  const summaryText = document.getElementById('summary-text');

  // Modo Generar/Editar
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

  // Uploads de imágenes para edición
  const sourceImageInput = document.getElementById('source-image-input');
  const sourceImagePreview = document.getElementById('source-image-preview');
  const sourceImagePlaceholder = document.getElementById('source-image-placeholder');
  const sourceImageClear = document.getElementById('source-image-clear');
  const targetImageInput = document.getElementById('target-image-input');
  const targetImagePreview = document.getElementById('target-image-preview');
  const targetImagePlaceholder = document.getElementById('target-image-placeholder');
  const targetImageClear = document.getElementById('target-image-clear');

  // Estado de imágenes para edición
  let sourceImageBase64 = null;
  let targetImageBase64 = null;

  // Lightbox
  const lightbox = document.getElementById('image-lightbox');
  const lightboxImage = document.getElementById('lightbox-image');
  const lightboxClose = document.getElementById('lightbox-close');

  // Tabs
  const optionTabs = document.querySelectorAll('.option-tab');
  const tabContents = document.querySelectorAll('.tab-content');

  // === Mapas de opciones para construir prompts ===
  const styleMap = {
    '': '',
    'photographic': 'Hyper-realistic professional photography, shot on Sony A7R IV with 35mm G Master lens, 8k resolution, extreme detail, natural skin texture with visible pores',
    'digital-art': 'High-end digital art illustration, intricate details, vibrant colors, clean vector lines, trending on ArtStation, 8k',
    'corporate': 'Professional Silicon Valley corporate style, modern business aesthetic, clean and crisp, high-end commercial look',
    'minimalist': 'Minimalist fine art photography, simple geometric balance, clean lines, vast negative space, high contrast, elegant',
    '3d-render': 'Ultra-realistic 3D octane render, Ray tracing, 8k, Unreal Engine 5 style, professional CGI, masterpiece textures',
    'flat-design': 'Premium flat design illustration, modern corporate palette, clean shapes, no gradients, sophisticated minimalism',
    'isometric': 'Isometric 3D illustration, professional architectural visualization style, clean and detailed',
    // Nuevos estilos Pro basados en la biblioteca
    'headshot-pro': 'Professional studio headshot, shot on 85mm f/1.8 lens, shallow depth of field, sharp focus on eyes, soft bokeh, premium charcoal smart casual blazer, neutral studio background',
    'silicon-valley': 'Silicon Valley executive portrait, navy blue business suit, white shirt, solid dark gray studio backdrop with subtle vignette, shot on Sony A7III, 85mm lens',
    'luxury-product': 'Luxury high-end product photography, commercial advertising style, professional studio lighting, sophisticated reflections, elegant composition'
  };

  const colorMap = {
    '': '',
    'warm': 'Warm cinematic color grading, golden hour hues, soft oranges and ambers, inviting atmosphere',
    'cool': 'Cool professional color palette, crisp teals and blues, clean and modern aesthetic',
    'corporate': 'Ebonia corporate color scheme (#23AAC5 accent), professional blue-teal tones, sophisticated business palette',
    'monochrome': 'Fine art monochromatic scheme, rich tonal range, elegant single-color depth',
    'pastel': 'Soft sophisticated pastel tones, muted and gentle professional colors, airy feel',
    'bw': 'Classic black and white film photography, high dynamic range, deep blacks and crisp whites, timeless',
    'vibrant': 'Vibrant and rich color saturation, bold commercial appeal, eye-catching and sharp'
  };

  const lightingMap = {
    '': '',
    'natural': 'Natural diffused daylight, soft window light, realistic environment illumination',
    'studio': 'Classic three-point studio lighting setup, professional key and fill lights, soft defining shadows',
    'dramatic': 'Cinematic dramatic lighting, high contrast, chiaroscuro effect, strong highlights and deep shadows',
    'soft': 'Bright and airy soft diffused lighting, gentle illumination, flattering and clean',
    'backlight': 'Elegant rim lighting, subtle hair light, professional separation from background',
    'golden': 'Golden hour sunset lighting, warm glow, long soft shadows, nostalgic atmosphere',
    'volumetric': 'Volumetric lighting with subtle light rays, atmospheric depth, cinematic mist'
  };

  const compositionMap = {
    '': '',
    'bokeh': 'Shallow depth of field, exquisite soft bokeh background, sharp focus on subject',
    'closeup': 'Close-up portrait framing, detailed view, professional headshot composition',
    'wide': 'Cinematic wide-angle shot, expansive professional scene, storytelling perspective',
    'above': 'Bird\'s eye view, professional top-down perspective, clean architectural composition',
    'below': 'Low angle heroic shot, dramatic upward perspective, powerful composition',
    'macro': 'Extreme macro photography, hyper-detailed textures, professional scientific-style focus',
    'negative-space': 'Fine art composition with vast negative space, subject isolated for impact, minimalist balance'
  };

  const formatMap = {
    '1:1': 'Square aspect ratio (1:1)',
    '3:4': 'Portrait aspect ratio (3:4)',
    '4:3': 'Landscape aspect ratio (4:3)',
    '16:9': 'Widescreen cinematic format (16:9)',
    '9:16': 'Vertical social media format (9:16)'
  };

  // Estado para regenerar
  let lastPrompt = '';
  let lastInputData = {};
  let currentImageBase64 = '';

  // === Gestión de modo Generar/Editar ===
  function setMode(mode) {
    currentModeInput.value = mode;
    
    if (mode === 'generate') {
      modeGenerateBtn.classList.add('active');
      modeEditBtn.classList.remove('active');
      editImagesSection.classList.add('hidden');
      styleOptionsSection.classList.remove('hidden');
      selectionSummary.classList.remove('hidden');
      descriptionLabel.textContent = '¿Qué imagen quieres crear?';
      descriptionField.placeholder = 'Describe la imagen que necesitas. Sé específico: objetos, escena, ambiente, colores...';
      generateBtn.innerHTML = '<i class="iconoir-sparks"></i><span>Generar imagen</span>';
    } else {
      modeEditBtn.classList.add('active');
      modeGenerateBtn.classList.remove('active');
      editImagesSection.classList.remove('hidden');
      styleOptionsSection.classList.remove('hidden'); // Ahora lo mostramos en ambos
      selectionSummary.classList.remove('hidden');   // Ahora lo mostramos en ambos
      descriptionLabel.textContent = '¿Qué cambios quieres hacer?';
      descriptionField.placeholder = 'Describe la edición: "Cambia el fondo por una playa", "Añade gafas de sol", "Fusiona el estilo de la imagen objetivo"...';
      generateBtn.innerHTML = '<i class="iconoir-edit"></i><span>Editar imagen</span>';
    }
  }

  if (modeGenerateBtn) {
    modeGenerateBtn.addEventListener('click', () => setMode('generate'));
  }
  if (modeEditBtn) {
    modeEditBtn.addEventListener('click', () => setMode('edit'));
  }

  // === Gestión de Selector de Motor ===
  function setProvider(provider) {
    currentProviderInput.value = provider;
    
    // Remover clase active de todos los botones de proveedor
    document.querySelectorAll('.provider-toggle-btn').forEach(btn => {
      btn.classList.remove('active');
    });
    
    // Añadir clase active al botón seleccionado
    if (provider === 'qwen' && providerQwenBtn) {
      providerQwenBtn.classList.add('active');
    } else if (provider === 'nanobanana' && providerNanobananaBtn) {
      providerNanobananaBtn.classList.add('active');
    } else if (provider === 'flux' && providerFluxBtn) {
      providerFluxBtn.classList.add('active');
    }
  }

  if (providerQwenBtn) {
    providerQwenBtn.addEventListener('click', () => setProvider('qwen'));
  }
  if (providerNanobananaBtn) {
    providerNanobananaBtn.addEventListener('click', () => setProvider('nanobanana'));
  }
  if (providerFluxBtn) {
    providerFluxBtn.addEventListener('click', () => setProvider('flux'));
  }

  // === Gestión de uploads de imágenes ===
  function handleImageUpload(input, preview, placeholder, clearBtn, setBase64) {
    if (!input) return;
    
    input.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      
      if (!file.type.startsWith('image/')) {
        alert('Por favor, selecciona una imagen válida');
        return;
      }
      
      const reader = new FileReader();
      reader.onload = (ev) => {
        const base64 = ev.target.result.split(',')[1];
        setBase64(base64);
        preview.src = ev.target.result;
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');
        clearBtn.classList.remove('hidden');
      };
      reader.readAsDataURL(file);
    });
    
    clearBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      setBase64(null);
      input.value = '';
      preview.src = '';
      preview.classList.add('hidden');
      placeholder.classList.remove('hidden');
      clearBtn.classList.add('hidden');
    });
  }

  handleImageUpload(
    sourceImageInput, sourceImagePreview, sourceImagePlaceholder, sourceImageClear,
    (b64) => { sourceImageBase64 = b64; }
  );
  handleImageUpload(
    targetImageInput, targetImagePreview, targetImagePlaceholder, targetImageClear,
    (b64) => { targetImageBase64 = b64; }
  );

  // === Gestión de tabs ===
  optionTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const targetTab = tab.dataset.tab;
      
      // Actualizar tabs activas
      optionTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      
      // Mostrar contenido correspondiente
      tabContents.forEach(content => {
        content.classList.add('hidden');
      });
      document.getElementById(`tab-${targetTab}`).classList.remove('hidden');
    });
  });

  // === Actualizar resumen de selección ===
  function updateSummary() {
    const format = document.querySelector('input[name="format"]:checked')?.value || '1:1';
    const style = document.querySelector('input[name="style"]:checked')?.value || '';
    const color = document.querySelector('input[name="color"]:checked')?.value || '';
    const lighting = document.querySelector('input[name="lighting"]:checked')?.value || '';
    const composition = document.querySelector('input[name="composition"]:checked')?.value || '';

    const parts = [`Formato ${format}`];
    
    const styleLabels = {
      '': 'Sin estilo', 'photographic': 'Fotográfico', 'digital-art': 'Digital Art',
      'corporate': 'Corporativo', 'minimalist': 'Minimalista', '3d-render': '3D Render',
      'flat-design': 'Flat Design', 'isometric': 'Isométrico',
      'headshot-pro': 'Retrato Pro', 'silicon-valley': 'Corporativo Pro', 'luxury-product': 'Producto Lujo'
    };
    const colorLabels = {
      '': '', 'warm': 'Cálidos', 'cool': 'Fríos', 'corporate': 'Corporativo',
      'monochrome': 'Monocromo', 'pastel': 'Pastel', 'bw': 'B/N', 'vibrant': 'Vibrante'
    };
    const lightingLabels = {
      '': '', 'natural': 'Luz natural', 'studio': 'Estudio', 'dramatic': 'Dramática',
      'soft': 'Suave', 'backlight': 'Contraluz', 'golden': 'Hora dorada', 'volumetric': 'Volumétrica'
    };
    const compositionLabels = {
      '': '', 'bokeh': 'Bokeh', 'closeup': 'Primer plano', 'wide': 'Plano general',
      'above': 'Cenital', 'below': 'Contrapicado', 'macro': 'Macro', 'negative-space': 'Espacio negativo'
    };

    if (style) parts.push(styleLabels[style]);
    if (color) parts.push(colorLabels[color]);
    if (lighting) parts.push(lightingLabels[lighting]);
    if (composition) parts.push(compositionLabels[composition]);

    summaryText.textContent = parts.join(' • ');
  }

  // Escuchar cambios en todos los radios
  document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', updateSummary);
  });

  // === Construir prompt profesional ===
  function buildPrompt(description, options) {
    const { format, style, color, lighting, composition } = options;

    // Prefijo de alta calidad según modo
    let prompt = `Create an ultra-realistic, high-resolution masterpiece image: ${description}`;
    
    // Instrucciones específicas de preservación si es edición (aunque aquí estamos en buildPrompt general)
    if (currentModeInput.value === 'edit') {
        prompt = `Keep the facial features and identity of the person in the input image exactly consistent. ${description}`;
    }
    
    // Añadir especificaciones técnicas
    const specs = [];
    
    if (formatMap[format]) {
      specs.push(formatMap[format]);
    }
    
    if (style && styleMap[style]) {
      specs.push(styleMap[style]);
    }
    
    if (color && colorMap[color]) {
      specs.push(colorMap[color]);
    }
    
    if (lighting && lightingMap[lighting]) {
      specs.push(lightingMap[lighting]);
    }
    
    if (composition && compositionMap[composition]) {
      specs.push(compositionMap[composition]);
    }

    if (specs.length > 0) {
      prompt += '\n\nTechnical Specifications and Style:\n- ' + specs.join('\n- ');
    }

    // Añadir calidad suprema
    prompt += '\n\nFinal Quality: 8k resolution, photorealistic, cinematic color grading, sharp focus, extreme attention to detail, high dynamic range (HDR).';

    // Semilla aleatoria para evitar caché
    const seed = Math.floor(Math.random() * 1000000);
    prompt += `\n\nInternal Seed: ${seed}`;

    return prompt;
  }

  // === Submit del formulario ===
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      await generateImage();
    });
  }

  // === Generar imagen ===
  async function generateImage() {
    const description = descriptionField.value.trim();
    const mode = currentModeInput?.value || 'generate';
    
    if (!description) {
      alert(mode === 'generate' ? 'Por favor, describe la imagen que quieres crear' : 'Por favor, describe los cambios que quieres hacer');
      descriptionField.focus();
      return;
    }

    // Validar imagen fuente en modo edición
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
      inputData = { 
        mode: 'generate', 
        description, 
        provider: currentProviderInput?.value || 'qwen',
        ...options 
      };
    } else {
      // Modo edición: prompt directo + imágenes
      prompt = description;
      inputData = {
        mode: 'edit',
        description,
        provider: currentProviderInput?.value || 'qwen',
        source_image: sourceImageBase64,
        target_image: targetImageBase64 || null
      };
    }

    // Guardar para regenerar
    lastPrompt = prompt;
    lastInputData = inputData;

    await sendRequest(prompt, inputData);
  }

  // === Enviar request a la API ===
  async function sendRequest(prompt, inputData) {
    // Mostrar loading
    imageResult.classList.add('hidden');
    imageLoading.classList.remove('hidden');
    generateBtn.disabled = true;

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
      imageLoading.classList.add('hidden');
      generateBtn.disabled = false;

      if (!res.ok) {
        alert('Error al generar la imagen: ' + (data.error?.message || 'Error desconocido'));
        return;
      }

      if (!data.image) {
        alert('No se pudo generar la imagen. Intenta con otra descripción.');
        return;
      }

      // Mostrar imagen
      currentImageBase64 = data.image;
      generatedImage.src = `data:image/png;base64,${data.image}`;
      imageResult.classList.remove('hidden');

      // Mostrar caption si hay texto
      if (data.text) {
        imageCaption.textContent = data.text;
        imageCaption.classList.remove('hidden');
      } else {
        imageCaption.classList.add('hidden');
      }

      // Scroll al resultado
      imageResult.scrollIntoView({ behavior: 'smooth', block: 'start' });

      // Recargar historial
      loadHistory();

    } catch (err) {
      imageLoading.classList.add('hidden');
      generateBtn.disabled = false;
      console.error('Error:', err);
      alert('Error de conexión al generar la imagen');
    }
  }

  // === Descargar imagen ===
  if (downloadBtn) {
    downloadBtn.addEventListener('click', () => {
      if (!currentImageBase64) return;

      const link = document.createElement('a');
      link.href = `data:image/png;base64,${currentImageBase64}`;
      link.download = `nanobanana-${Date.now()}.png`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    });
  }

  // === Regenerar imagen ===
  if (regenerateBtn) {
    regenerateBtn.addEventListener('click', () => {
      if (lastPrompt) {
        sendRequest(lastPrompt, lastInputData);
      }
    });
  }

  // === Lightbox ===
  if (generatedImage) {
    generatedImage.addEventListener('click', () => {
      if (currentImageBase64) {
        lightboxImage.src = `data:image/png;base64,${currentImageBase64}`;
        lightbox.classList.remove('hidden');
        lightbox.classList.add('flex');
      }
    });
  }

  if (lightboxClose) {
    lightboxClose.addEventListener('click', () => {
      lightbox.classList.add('hidden');
      lightbox.classList.remove('flex');
    });
  }

  if (lightbox) {
    lightbox.addEventListener('click', (e) => {
      if (e.target === lightbox) {
        lightbox.classList.add('hidden');
        lightbox.classList.remove('flex');
      }
    });
  }

  // === HISTORIAL ===
  loadHistory();
  checkUrlParameter();

  function checkUrlParameter() {
    const urlParams = new URLSearchParams(window.location.search);
    const executionId = urlParams.get('id');
    
    if (executionId) {
      loadExecution(executionId);
      const newUrl = window.location.pathname;
      window.history.replaceState({}, document.title, newUrl);
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
      const description = item.input_data?.description || item.title || 'Imagen generada';
      const truncatedDesc = description.length > 40 ? description.substring(0, 40) + '...' : description;

      return `
        <div class="history-item w-full p-3 hover:bg-slate-50 border-b border-slate-100 transition-colors group flex items-start gap-2" data-id="${item.id}">
          <i class="iconoir-media-image text-amber-500 mt-0.5"></i>
          <div class="flex-1 min-w-0 cursor-pointer history-item-main">
            <p class="text-sm font-medium text-slate-700 truncate group-hover:text-amber-700">${escapeHtml(truncatedDesc)}</p>
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
        alert('Error al cargar la imagen');
        return;
      }

      const exec = data.execution;
      const outputData = exec.output_data || {};

      // Mostrar imagen
      if (outputData.image) {
        currentImageBase64 = outputData.image;
        generatedImage.src = `data:image/png;base64,${outputData.image}`;
        imageResult.classList.remove('hidden');

        if (outputData.text) {
          imageCaption.textContent = outputData.text;
          imageCaption.classList.remove('hidden');
        } else {
          imageCaption.classList.add('hidden');
        }
      }

      // Restaurar inputs
      const inputData = exec.input_data || {};
      if (inputData.description) {
        descriptionField.value = inputData.description;
      }
      if (inputData.format) {
        const radio = document.querySelector(`input[name="format"][value="${inputData.format}"]`);
        if (radio) radio.checked = true;
      }
      if (inputData.style !== undefined) {
        const radio = document.querySelector(`input[name="style"][value="${inputData.style}"]`);
        if (radio) radio.checked = true;
      }
      if (inputData.color !== undefined) {
        const radio = document.querySelector(`input[name="color"][value="${inputData.color}"]`);
        if (radio) radio.checked = true;
      }
      if (inputData.lighting !== undefined) {
        const radio = document.querySelector(`input[name="lighting"][value="${inputData.lighting}"]`);
        if (radio) radio.checked = true;
      }
      if (inputData.composition !== undefined) {
        const radio = document.querySelector(`input[name="composition"][value="${inputData.composition}"]`);
        if (radio) radio.checked = true;
      }

      updateSummary();
      lastInputData = inputData;

      // Scroll
      imageResult.scrollIntoView({ behavior: 'smooth', block: 'start' });

    } catch (err) {
      alert('Error de conexión');
    }
  }

  async function deleteExecution(id) {
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
  }

  // Botón nueva imagen
  if (newImageBtn) {
    newImageBtn.addEventListener('click', () => {
      form.reset();
      imageResult.classList.add('hidden');
      currentImageBase64 = '';
      updateSummary();
      descriptionField.focus();
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  // === Utilidades ===
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

  // Inicializar
  updateSummary();
})();
