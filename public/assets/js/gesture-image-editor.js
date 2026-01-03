/**
 * Gesto: Editor de Imágenes con Nanobanana 
 * Genera imágenes usando selectores de formato, estilo, color, iluminación y composición
 */
(function() {
  'use strict';

  const GESTURE_TYPE = 'image-editor';

  // === Referencias DOM Studio Mode ===
  const studioForm = document.getElementById('studio-form');
  const studioInput = document.getElementById('studio-prompt-input');
  const studioOptionsPanel = document.getElementById('studio-options-panel');
  const toggleOptionsBtn = document.getElementById('toggle-studio-options');
  const closeOptionsBtn = document.getElementById('close-options');

  const canvasEmpty = document.getElementById('studio-empty');
  const canvasLoading = document.getElementById('studio-loading');
  const canvasResult = document.getElementById('studio-result');
  const mainImage = document.getElementById('main-image');

  const historyStrip = document.getElementById('studio-history');
  const editModeBadge = document.getElementById('edit-mode-badge');

  const providerButtons = document.querySelectorAll('#studio-provider-selector button');
  const currentProviderInput = document.getElementById('current-provider');
  const currentModeInput = document.getElementById('current-mode');

  // Acciones de imagen
  const btnEdit = document.getElementById('btn-edit-this');
  const btnDownload = document.getElementById('btn-download-this');
  const btnRegenerate = document.getElementById('btn-regenerate-this');
  const btnExpand = document.getElementById('btn-expand-this');

  // Lightbox
  const lightbox = document.getElementById('studio-lightbox');
  const lightboxImg = document.getElementById('lightbox-img');
  const closeLightbox = document.getElementById('close-lightbox');

  // Uploads
  const sourceUpload = document.getElementById('source-upload');
  const targetUpload = document.getElementById('target-upload');
  const sourceMini = document.getElementById('source-mini');
  const targetMini = document.getElementById('target-mini');

  // Estado
  let sourceImageBase64 = null;
  let targetImageBase64 = null;
  let lastPrompt = '';
  let lastInputData = {};
  let currentImageBase64 = '';

  // === Mapas de Prompts ===
  const styleMap = {
    '': '',
    'photographic': 'Hyper-realistic professional photography, shot on Sony A7R IV with 35mm G Master lens, 8k resolution, extreme detail, natural skin texture',
    'headshot-pro': 'Professional studio headshot, shot on 85mm f/1.8 lens, shallow depth of field, sharp focus on eyes, soft bokeh',
    'corporate': 'Silicon Valley executive portrait, navy blue business suit, white shirt, solid dark gray studio backdrop, shot on Sony A7III, 85mm lens',
    'luxury-product': 'Luxury high-end product photography, commercial advertising style, professional studio lighting, sophisticated reflections',
    '3d-render': 'Ultra-realistic 3D octane render, Ray tracing, 8k, Unreal Engine 5 style'
  };

  const lightingMap = {
    '': '',
    'natural': 'Natural diffused daylight, soft window light',
    'studio': 'Classic three-point studio lighting setup, professional key and fill lights',
    'dramatic': 'Cinematic dramatic lighting, high contrast, chiaroscuro effect',
    'golden': 'Golden hour sunset lighting, warm glow, long soft shadows'
  };

  const compositionMap = {
    '': '',
    'bokeh': 'Shallow depth of field, exquisite soft bokeh background, sharp focus on subject',
    'closeup': 'Close-up portrait framing, detailed view, professional headshot composition',
    'wide': 'Cinematic wide-angle shot, expansive professional scene'
  };

  // === Lógica de UI Studio ===

  // Toggle opciones
  toggleOptionsBtn?.addEventListener('click', () => {
    studioOptionsPanel.classList.toggle('active');
  });

  closeOptionsBtn?.addEventListener('click', () => {
    studioOptionsPanel.classList.remove('active');
  });

  // Selector de proveedor
  providerButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      providerButtons.forEach(b => b.classList.remove('active', 'bg-amber-500', 'text-white'));
      btn.classList.add('active', 'bg-amber-500', 'text-white');
      currentProviderInput.value = btn.dataset.provider;
    });
  });

  // Manejo de Uploads (miniaturas)
  function setupUpload(input, mini, setB64) {
    input?.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = (ev) => {
        const base64 = ev.target.result.split(',')[1];
        setB64(base64);
        mini.innerHTML = `<img src="${ev.target.result}" class="w-full h-full object-cover rounded">`;
        mini.classList.remove('border-dashed', 'text-white/20');
        mini.classList.add('border-solid', 'border-amber-500');
        setMode('edit');
      };
      reader.readAsDataURL(file);
    });

    mini?.addEventListener('click', () => input.click());
  }

  setupUpload(sourceUpload, sourceMini, (b64) => sourceImageBase64 = b64);
  setupUpload(targetUpload, targetMini, (b64) => targetImageBase64 = b64);

  // Modo Editar
  function setMode(mode) {
    currentModeInput.value = mode;
    if (mode === 'edit') {
      editModeBadge.classList.add('active');
      studioInput.placeholder = "Describe los cambios para la imagen actual...";
    } else {
      editModeBadge.classList.remove('active');
      studioInput.placeholder = "Describe lo que quieres crear...";
    }
  }

  // === Acciones de Imagen ===

  // Editar esta imagen (Seamless)
  btnEdit?.addEventListener('click', () => {
    if (!currentImageBase64) return;
    sourceImageBase64 = currentImageBase64;
    sourceMini.innerHTML = `<img src="data:image/png;base64,${currentImageBase64}" class="w-full h-full object-cover rounded">`;
    sourceMini.classList.remove('border-dashed', 'text-white/20');
    sourceMini.classList.add('border-solid', 'border-amber-500');
    setMode('edit');
    studioInput.focus();
    // Scroll suave a la barra de prompt
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  btnDownload?.addEventListener('click', () => {
    if (!currentImageBase64) return;
    const link = document.createElement('a');
    link.href = `data:image/png;base64,${currentImageBase64}`;
    link.download = `studio-${Date.now()}.png`;
    link.click();
  });

  btnRegenerate?.addEventListener('click', () => {
    if (lastPrompt) generateImage();
  });

  btnExpand?.addEventListener('click', () => {
    if (!currentImageBase64) return;
    lightboxImg.src = `data:image/png;base64,${currentImageBase64}`;
    lightbox.classList.remove('hidden');
    lightbox.classList.add('flex');
  });

  closeLightbox?.addEventListener('click', () => {
    lightbox.classList.add('hidden');
    lightbox.classList.remove('flex');
  });

  // === Core Logic ===

  async function generateImage() {
    const description = studioInput.value.trim();
    if (!description && currentModeInput.value === 'generate') {
      studioInput.focus();
      return;
    }

    const mode = currentModeInput.value;
    const provider = currentProviderInput.value;

    // Obtener opciones del panel
    const format = document.querySelector('input[name="format"]:checked')?.value || '1:1';
    const style = document.querySelector('select[name="style"]')?.value || '';
    const lighting = document.querySelector('select[name="lighting"]')?.value || '';
    const composition = document.querySelector('select[name="composition"]')?.value || '';

    let prompt = '';
    let inputData = {};

    if (mode === 'generate') {
      prompt = `Create an ultra-realistic, high-resolution masterpiece: ${description}`;
      if (styleMap[style]) prompt += `\nStyle: ${styleMap[style]}`;
      if (lightingMap[lighting]) prompt += `\nLighting: ${lightingMap[lighting]}`;
      if (compositionMap[composition]) prompt += `\nComposition: ${compositionMap[composition]}`;
      prompt += `\nFormat: ${format}. 8k resolution, HDR.`;

      inputData = { mode, description, provider, format, style, lighting, composition };
    } else {
      // Modo edición
      prompt = description || "Enhance and refine the image based on the input";
      inputData = {
        mode: 'edit',
        description: prompt,
        provider: provider,
        source_image: sourceImageBase64,
        target_image: targetImageBase64
      };
    }

    lastPrompt = prompt;
    lastInputData = inputData;

    await sendStudioRequest(prompt, inputData);
  }

  async function sendStudioRequest(prompt, inputData) {
    // UI State
    canvasEmpty.classList.add('hidden');
    canvasResult.classList.add('hidden');
    canvasLoading.classList.remove('hidden');
    studioForm.querySelectorAll('button').forEach(b => b.disabled = true);

    try {
      const res = await fetch('/api/gestures/generate-image.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
        body: JSON.stringify({ gesture_type: GESTURE_TYPE, prompt, input_data: inputData }),
        credentials: 'include'
      });

      const data = await res.json();

      if (!res.ok || !data.image) {
        alert('Error: ' + (data.error?.message || 'No se pudo generar la imagen'));
        canvasLoading.classList.add('hidden');
        canvasEmpty.classList.remove('hidden');
      } else {
        currentImageBase64 = data.image;
        mainImage.src = `data:image/png;base64,${data.image}`;
        canvasLoading.classList.add('hidden');
        canvasResult.classList.remove('hidden');
        loadStudioHistory();
      }
    } catch (err) {
      console.error(err);
      alert('Error de conexión');
      canvasLoading.classList.add('hidden');
      canvasEmpty.classList.remove('hidden');
    } finally {
      studioForm.querySelectorAll('button').forEach(b => b.disabled = false);
    }
  }

  // Submit form
  studioForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    generateImage();
  });

  // Cargar Historial (Filmstrip)
  async function loadStudioHistory() {
    try {
      const res = await fetch(`/api/gestures/history.php?type=${GESTURE_TYPE}&limit=15`, { credentials: 'include' });
      const data = await res.json();

      if (!res.ok || !data.items) return;

      historyStrip.innerHTML = data.items.map(item => {
        const img = item.output_data?.image;
        if (!img) return '';
        return `
          <div class="filmstrip-item" data-id="${item.id}" data-image="${img}">
            <img src="data:image/png;base64,${img}" alt="History">
          </div>
        `;
      }).join('');

      // Click en historial
      historyStrip.querySelectorAll('.filmstrip-item').forEach(el => {
        el.addEventListener('click', () => {
          const img = el.dataset.image;
          currentImageBase64 = img;
          mainImage.src = `data:image/png;base64,${img}`;
          canvasEmpty.classList.add('hidden');
          canvasLoading.classList.add('hidden');
          canvasResult.classList.remove('hidden');

          historyStrip.querySelectorAll('.filmstrip-item').forEach(i => i.classList.remove('active'));
          el.classList.add('active');
        });
      });
    } catch (err) {
      console.error('History error:', err);
    }
  }

  // Inicializar
  loadStudioHistory();

})();
