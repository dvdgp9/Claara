/**
 * Gesto: Escribir contenido (artículos, posts de blog, notas de prensa)
 */
(function() {
  'use strict';

  const GESTURE_TYPE = 'write-article';

  // === Referencias DOM ===
  const writeArticleForm = document.getElementById('write-article-form');
  const articleResult = document.getElementById('article-result');
  const articleContent = document.getElementById('article-content');
  const articleLoading = document.getElementById('article-loading');
  const generateArticleBtn = document.getElementById('generate-article-btn');
  const copyArticleBtn = document.getElementById('copy-article-btn');
  const regenerateArticleBtn = document.getElementById('regenerate-article-btn');
  const historyList = document.getElementById('history-list');
  const newContentBtn = document.getElementById('new-content-btn');

  // Campos por tipo
  const fieldsInformativo = document.getElementById('fields-informativo');
  const fieldsBlog = document.getElementById('fields-blog');
  const fieldsNotaPrensa = document.getElementById('fields-nota-prensa');

  // === Mostrar/ocultar campos según tipo de contenido ===
  const contentTypeRadios = document.querySelectorAll('input[name="content-type"]');
  contentTypeRadios.forEach(radio => {
    radio.addEventListener('change', () => {
      fieldsInformativo.classList.add('hidden');
      fieldsBlog.classList.add('hidden');
      fieldsNotaPrensa.classList.add('hidden');
      
      if (radio.value === 'informativo') fieldsInformativo.classList.remove('hidden');
      else if (radio.value === 'blog') fieldsBlog.classList.remove('hidden');
      else if (radio.value === 'nota-prensa') fieldsNotaPrensa.classList.remove('hidden');
    });
  });

  // === Helper para convertir markdown a HTML ===
  function mdToHtml(md) {
    if (!md) return '';
    let s = md
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
    s = s.replace(/^### (.+)$/gm, '<h3 class="text-lg font-semibold mt-4 mb-2">$1</h3>');
    s = s.replace(/^## (.+)$/gm, '<h2 class="text-xl font-semibold mt-6 mb-3">$1</h2>');
    s = s.replace(/^# (.+)$/gm, '<h1 class="text-2xl font-bold mt-6 mb-3">$1</h1>');
    s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/\*(.+?)\*/g, '<em>$1</em>');
    s = s.replace(/\n\n/g, '</p><p class="mb-4">');
    s = '<p class="mb-4">' + s + '</p>';
    return s;
  }

  // === Mapa de líneas de negocio ===
  const businessLineMap = {
    'brand': 'Brand',
    'product': 'Product',
    'team': 'Team'
  };

  // Estado para regenerar
  let lastPrompt = '';
  let lastInputData = {};
  let lastContentType = '';
  let lastBusinessLine = '';

  // === Submit del formulario ===
  if (writeArticleForm) {
    writeArticleForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      await generateContent();
    });
  }

  // === Generar contenido según tipo ===
  async function generateContent() {
    const contentType = document.querySelector('input[name="content-type"]:checked')?.value || 'informativo';
    const businessLine = document.querySelector('input[name="business-line"]:checked')?.value || 'brand';
    const businessName = businessLineMap[businessLine];
    
    let prompt = '';
    let inputData = {}; // Datos para guardar en historial
    
    // === ARTÍCULO INFORMATIVO ===
    if (contentType === 'informativo') {
      const topic = document.getElementById('info-topic').value.trim();
      if (!topic) { alert('Please enter the article topic'); return; }
      
      const category = document.getElementById('info-category').value;
      const length = document.getElementById('info-length').value;
      const details = document.getElementById('info-details').value.trim();
      
      inputData = { topic, category, length, details };
      
      const categoryMap = {
        'general': 'general/current affairs',
        'deportes': 'sports and physical activity',
        'cultura': 'culture and leisure',
        'salud': 'health and wellness',
        'empresa': 'corporate news'
      };
      
      prompt = `Write an informative article for ${businessName}.

TOPIC: ${topic}
CATEGORY: ${categoryMap[category]}
LENGTH: Approximately ${length} words

FORMAT:
- Compelling headline (with #)
- Lead paragraph that summarizes the news
- Body with subtitles (##) when needed
- Objective and informative tone
- No commercial calls to action
${details ? `\nADDITIONAL INSTRUCTIONS: ${details}` : ''}

Important notes:
- Do not invent names, roles, dates, figures, or contact data.
- If a contact email is needed, use a generic placeholder such as contact@example.com unless the user provided a real one.

Write ONLY the article, with no comments or explanations.`;
    }
    
    // === POST DE BLOG ===
    else if (contentType === 'blog') {
      const topic = document.getElementById('blog-topic').value.trim();
      if (!topic) { alert('Please enter the post topic'); return; }
      
      const keywords = document.getElementById('blog-keywords').value.trim();
      const details = document.getElementById('blog-details').value.trim();
      
      inputData = { topic, keywords, details };
      
      prompt = `Write an SEO-optimized blog post for ${businessName}.

TOPIC: ${topic}
${keywords ? `KEYWORDS: ${keywords}` : ''}

MANDATORY SEO REQUIREMENTS:
- Length: 600-1000 words
- Strong H1 title including the main keyword
- Suggested meta description (max 155 characters) at the beginning in brackets [META: ...]
- Engaging introduction including the keyword within the first 100 words
- H2/H3 structure for readability
- Short paragraphs (max 3-4 lines)
- At least one bulleted or numbered list
- Conclusion with a call to action (CTA)
- Close but professional tone
${details ? `\nADDITIONAL INSTRUCTIONS: ${details}` : ''}

Important notes:
- Do not invent names, roles, dates, figures, or contact data.
- If a contact email is needed, use a generic placeholder such as contact@example.com unless the user provided a real one.

Write ONLY the post, with no comments or explanations.`;
    }
    
    // === NOTA DE PRENSA ===
    else if (contentType === 'nota-prensa') {
      const pressType = document.querySelector('input[name="press-type"]:checked')?.value || 'lanzamiento';
      const what = document.getElementById('press-what').value.trim();
      if (!what) { alert('Please enter what happened (the main fact)'); return; }
      
      const who = document.getElementById('press-who').value.trim();
      const when = document.getElementById('press-when').value.trim();
      const where = document.getElementById('press-where').value.trim();
      const why = document.getElementById('press-why').value.trim();
      const purpose = document.getElementById('press-purpose').value.trim();
      const quoteAuthor = document.getElementById('press-quote-author').value.trim();
      const quoteText = document.getElementById('press-quote-text').value.trim();
      
      inputData = { pressType, what, who, when, where, why, purpose, quoteAuthor, quoteText };
      
      const pressTypeMap = {
        'lanzamiento': 'project or service launch',
        'evento': 'event',
        'nombramiento': 'appointment or onboarding',
        'convenio': 'institutional partnership or collaboration',
        'premio': 'award, achievement, or recognition'
      };
      
      let dataSection = `WHAT HAPPENED: ${what}`;
      if (who) dataSection += `\nWHO: ${who}`;
      if (when) dataSection += `\nWHEN: ${when}`;
      if (where) dataSection += `\nWHERE: ${where}`;
      if (why) dataSection += `\nWHY: ${why}`;
      if (purpose) dataSection += `\nADDITIONAL INFORMATION (confirmed, no assumptions): ${purpose}`;
      if (quoteText) dataSection += `\nSTATEMENT${quoteAuthor ? ` (${quoteAuthor})` : ''}: "${quoteText}"`;
      
      prompt = `Write a professional press release for ${businessName}.

ANNOUNCEMENT TYPE: ${pressTypeMap[pressType]}

DATA:
${dataSection}

PRESS RELEASE FORMAT:
- Impactful headline (with #)
- Subtitle/deck that expands the key information
- Location and date at the start of the body: "[City], [date] –"
- First paragraph: answer the 5Ws (what, who, when, where, why) concisely
- Body: expand details in decreasing order of importance (inverted pyramid)
- If there is a statement, include it in quotes with attribution
- Closing: background context about ${businessName}
- "###" at the end (standard press release end marker)
- "For more information:" section with a contact placeholder

If data is missing, adapt using available information **without ever inventing** dates, names, roles, places, figures, or other sensitive data. If something is not provided, do not assume it.

Write ONLY the press release, with no comments or explanations.`;
    }
    
    // Guardar datos para regenerar
    lastPrompt = prompt;
    lastInputData = inputData;
    lastContentType = contentType;
    lastBusinessLine = businessLine;
    
    await sendPrompt(prompt, inputData, contentType, businessLine);
  }

  // === Enviar prompt a la API ===
  async function sendPrompt(prompt, inputData, contentType, businessLine) {
    // Mostrar loading
    articleResult.classList.add('hidden');
    articleLoading.classList.remove('hidden');
    generateArticleBtn.disabled = true;
    
    try {
      const res = await fetch('/api/gestures/generate.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.CSRF_TOKEN
        },
        body: JSON.stringify({
          gesture_type: GESTURE_TYPE,
          prompt: prompt,
          input_data: inputData,
          content_type: contentType,
          business_line: businessLine
        }),
        credentials: 'include'
      });
      
      const data = await res.json();
      articleLoading.classList.add('hidden');
      generateArticleBtn.disabled = false;
      
      if (!res.ok) {
        alert('Error generating content: ' + (data.error?.message || 'Unknown error'));
        return;
      }
      
      // Mostrar resultado
      articleContent.innerHTML = mdToHtml(data.content);
      articleResult.classList.remove('hidden');
      
      // Scroll al resultado
      articleResult.scrollIntoView({ behavior: 'smooth', block: 'start' });
      
      // Recargar historial
      loadHistory();
      
    } catch (err) {
      articleLoading.classList.add('hidden');
      generateArticleBtn.disabled = false;
      alert('Connection error while generating content');
    }
  }

  // === Copiar contenido ===
  if (copyArticleBtn) {
    copyArticleBtn.addEventListener('click', () => {
      const text = articleContent.innerText;
      navigator.clipboard.writeText(text).then(() => {
        const originalText = copyArticleBtn.innerHTML;
        copyArticleBtn.innerHTML = '<i class="iconoir-check"></i> Copied';
        setTimeout(() => {
          copyArticleBtn.innerHTML = originalText;
        }, 2000);
      });
    });
  }

  // === Regenerar contenido ===
  if (regenerateArticleBtn) {
    regenerateArticleBtn.addEventListener('click', () => {
      if (lastPrompt) {
        sendPrompt(lastPrompt, lastInputData, lastContentType, lastBusinessLine);
      }
    });
  }

  // === HISTORIAL ===
  
  // Cargar historial al iniciar
  loadHistory();
  
  // === CHECK FOR URL PARAMETER (from sidebar navigation) ===
  checkUrlParameter();
  
  function checkUrlParameter() {
    const urlParams = new URLSearchParams(window.location.search);
    const executionId = urlParams.get('id');
    
    if (executionId) {
      // Cargar el contenido automáticamente
      loadExecution(executionId);
      
      // Limpiar el parámetro de la URL sin recargar
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
        historyList.innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Could not load</div>';
        return;
      }
      
      renderHistory(data.items || []);
    } catch (err) {
      historyList.innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Connection error</div>';
    }
  }
  
  function renderHistory(items) {
    if (items.length === 0) {
      historyList.innerHTML = `
        <div class="p-6 text-center">
          <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
            <i class="iconoir-page-edit text-xl text-slate-400"></i>
          </div>
          <p class="text-sm text-slate-500">You have not generated content yet</p>
          <p class="text-xs text-slate-400 mt-1">Usa el formulario para empezar</p>
        </div>
      `;
      return;
    }
    
    const contentTypeIcons = {
      'informativo': 'iconoir-journal-page',
      'blog': 'iconoir-post',
      'nota-prensa': 'iconoir-megaphone'
    };
    
    const businessColors = {
      'brand': 'bg-blue-100 text-blue-700',
      'product': 'bg-orange-100 text-orange-700',
      'team': 'bg-purple-100 text-purple-700'
    };
    
    historyList.innerHTML = items.map(item => {
      const icon = contentTypeIcons[item.content_type] || 'iconoir-page-edit';
      const businessClass = businessColors[item.business_line] || 'bg-slate-100 text-slate-600';
      const businessLabel = businessLineMap[item.business_line] || item.business_line || '';
      const timeAgo = formatTimeAgo(new Date(item.created_at));
      
      return `
        <div class="history-item w-full p-3 hover:bg-slate-50 border-b border-slate-100 transition-colors group flex items-start gap-2" data-id="${item.id}">
          <i class="${icon} text-[#B7C9F2] mt-0.5"></i>
          <div class="flex-1 min-w-0 cursor-pointer history-item-main">
            <p class="text-sm font-medium text-slate-700 truncate group-hover:text-[#2F3440]">${escapeHtml(item.title)}</p>
            <div class="flex items-center gap-2 mt-1">
              ${businessLabel ? `<span class="text-[10px] px-1.5 py-0.5 rounded ${businessClass}">${businessLabel}</span>` : ''}
              <span class="text-[10px] text-slate-400">${timeAgo}</span>
            </div>
          </div>
          <button class="history-item-delete opacity-0 group-hover:opacity-100 transition-opacity text-slate-300 hover:text-red-500 p-1 rounded" title="Delete">
            <i class="iconoir-trash"></i>
          </button>
        </div>
      `;
    }).join('');
    
    // Añadir event listeners
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
        alert('Error loading content');
        return;
      }
      
      const exec = data.execution;
      
      // Mostrar contenido
      articleContent.innerHTML = mdToHtml(exec.output_content);
      articleResult.classList.remove('hidden');
      
      // Guardar para regenerar
      lastInputData = exec.input_data || {};
      lastContentType = exec.content_type || '';
      lastBusinessLine = exec.business_line || '';
      
      // Scroll al resultado
      articleResult.scrollIntoView({ behavior: 'smooth', block: 'start' });
      
    } catch (err) {
      alert('Connection error');
    }
  }

  async function deleteExecution(id) {
    if (!confirm('Delete this content from history?')) return;
    
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
        alert('Could not delete the item');
        return;
      }
      
      // Recargar historial tras borrar
      loadHistory();
    } catch (err) {
      alert('Connection error while deleting');
    }
  }
  
  // Botón nuevo contenido - resetear formulario
  if (newContentBtn) {
    newContentBtn.addEventListener('click', () => {
      writeArticleForm.reset();
      articleResult.classList.add('hidden');
      // Mostrar campos del tipo por defecto
      fieldsInformativo.classList.remove('hidden');
      fieldsBlog.classList.add('hidden');
      fieldsNotaPrensa.classList.add('hidden');
      // Scroll arriba
      writeArticleForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
  
  // === Utilidades ===
  
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
})();
