/**
 * Gesture: Transformador de Contenido (Content Repurposer)
 * Transforma contenido en diferentes formatos: posts sociales, blog, landing, newsletter, FAQs
 * Soporta selección múltiple de formatos y previews visuales
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
  const generateBtnText = document.getElementById('generate-btn-text');
  const progressPanel = document.getElementById('progress-panel');
  const progressText = document.getElementById('progress-text');
  const progressDetail = document.getElementById('progress-detail');
  const errorPanel = document.getElementById('error-panel');
  const errorMessage = document.getElementById('error-message');
  const selectedCount = document.getElementById('selected-count');
  
  const inputSection = document.getElementById('input-section');
  const resultSection = document.getElementById('result-section');
  const resultTitle = document.getElementById('result-title');
  const resultSource = document.getElementById('result-source');
  const resultTabs = document.getElementById('result-tabs');
  const resultPanels = document.getElementById('result-panels');
  const copyAllBtn = document.getElementById('copy-all-btn');
  
  const historyList = document.getElementById('history-list');
  
  // === State ===
  let currentTab = 'url';
  let selectedFormats = new Set(['instagram']);
  let pdfBase64 = null;
  let currentResults = null;

  // === Format config ===
  const formatConfig = {
    instagram: { name: 'Instagram', icon: 'iconoir-instagram', color: 'from-pink-500 to-orange-500' },
    facebook: { name: 'Facebook', icon: 'iconoir-facebook', color: 'bg-blue-600' },
    linkedin: { name: 'LinkedIn', icon: 'iconoir-linkedin', color: 'bg-sky-700' },
    twitter: { name: 'X (Twitter)', icon: 'iconoir-x', color: 'bg-slate-900' },
    blog: { name: 'Blog', icon: 'iconoir-post', color: 'bg-emerald-600' },
    landing: { name: 'Landing', icon: 'iconoir-code', color: 'bg-violet-600' },
    newsletter: { name: 'Newsletter', icon: 'iconoir-mail', color: 'bg-amber-600' },
    faq: { name: 'FAQs', icon: 'iconoir-help-circle', color: 'bg-rose-600' }
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

  // === Multiple format selection (toggle) ===
  formatCards.forEach(card => {
    card.addEventListener('click', () => {
      const format = card.dataset.format;
      
      if (selectedFormats.has(format)) {
        if (selectedFormats.size > 1) {
          selectedFormats.delete(format);
          card.classList.remove('selected');
        }
      } else {
        selectedFormats.add(format);
        card.classList.add('selected');
      }
      
      updateSelectedCount();
    });
  });

  function updateSelectedCount() {
    const count = selectedFormats.size;
    selectedCount.textContent = `${count} format${count !== 1 ? 's' : ''} selected`;
    generateBtnText.textContent = count > 1 ? `Generate ${count} formats` : 'Transform content';
  }

  // === PDF handling ===
  if (sourcePdf) {
    sourcePdf.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      
      if (file.type !== 'application/pdf') {
        alert('Please select a PDF file');
        sourcePdf.value = '';
        return;
      }
      
      if (file.size > 20 * 1024 * 1024) {
        alert('The PDF file is too large (maximum 20MB)');
        sourcePdf.value = '';
        return;
      }
      
      const reader = new FileReader();
      reader.onload = (ev) => {
        const base64 = ev.target.result.split(',')[1];
        pdfBase64 = base64;
        pdfFilename.textContent = `File: ${file.name}`;
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
    const formats = Array.from(selectedFormats);
    
    let inputData = { 
      source_type: currentTab,
      output_formats: formats,
      options: {}
    };
    
    switch (currentTab) {
      case 'url':
        const url = sourceUrl.value.trim();
        if (!url) {
          showError('Please enter a URL');
          return;
        }
        inputData.url = url;
        break;
        
      case 'text':
        const text = sourceText.value.trim();
        if (!text) {
          showError('Please enter text');
          return;
        }
        if (text.split(/\s+/).length < 20) {
          showError('The text is too short (minimum 20 words)');
          return;
        }
        inputData.text = text;
        break;
        
      case 'pdf':
        if (!pdfBase64) {
          showError('Please select a PDF file');
          return;
        }
        inputData.pdf_base64 = pdfBase64;
        break;
    }

    const formatCount = formats.length;
    showProgress(`Generating ${formatCount} format${formatCount > 1 ? 's' : ''}...`, 'Extracting and transforming content');
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
        const errorMsg = data.error?.message || data.message || 'Unknown error';
        throw new Error(errorMsg);
      }

      currentResults = data;
      showResults(data);
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

  // === Show multiple results with tabs and previews ===
  function showResults(data) {
    inputSection.classList.add('hidden');
    resultSection.classList.remove('hidden');
    
    resultTitle.textContent = data.title || 'Generated content';
    resultSource.textContent = `Source: ${data.source} · ${data.total_generated} format${data.total_generated > 1 ? 's' : ''} generated`;
    
    const formats = Object.keys(data.results);
    let activeFormat = formats[0];
    
    // Render tabs (desktop)
    resultTabs.innerHTML = formats.map((format, i) => {
      const config = formatConfig[format] || { name: format, icon: 'iconoir-document' };
      return `
        <button class="result-tab ${i === 0 ? 'active' : ''}" data-format="${format}">
          <i class="${config.icon}"></i>
          ${config.name}
        </button>
      `;
    }).join('');
    
    // Render panels
    resultPanels.innerHTML = formats.map((format, i) => {
      const result = data.results[format];
      return `
        <div class="result-panel ${i === 0 ? 'active' : ''}" data-format="${format}">
          ${renderFormatPanel(format, result)}
        </div>
      `;
    }).join('');
    
    // Tab click handlers
    resultTabs.querySelectorAll('.result-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        const format = tab.dataset.format;
        activeFormat = format;
        
        resultTabs.querySelectorAll('.result-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        resultPanels.querySelectorAll('.result-panel').forEach(p => p.classList.remove('active'));
        resultPanels.querySelector(`.result-panel[data-format="${format}"]`)?.classList.add('active');
      });
    });
    
    // Copy buttons
    resultPanels.querySelectorAll('.copy-format-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const format = btn.dataset.format;
        const result = data.results[format];
        const textToCopy = getTextToCopy(format, result);
        
        try {
          await navigator.clipboard.writeText(textToCopy);
          btn.innerHTML = '<i class="iconoir-check"></i> Copied';
          setTimeout(() => {
            btn.innerHTML = '<i class="iconoir-copy"></i> Copy';
          }, 2000);
        } catch (err) {
          console.error('Error copying:', err);
        }
      });
    });
    
    // Download buttons
    resultPanels.querySelectorAll('.download-format-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const format = btn.dataset.format;
        const result = data.results[format];
        downloadFormat(format, result);
      });
    });
  }

  function renderFormatPanel(format, result) {
    const config = formatConfig[format] || { name: format, icon: 'iconoir-document', color: 'bg-slate-600' };
    const parsed = result.parsed || {};
    
    let previewHtml = '';
    let rawHtml = '';
    
    switch (format) {
      case 'instagram':
        previewHtml = renderInstagramPreview(parsed);
        break;
      case 'facebook':
        previewHtml = renderFacebookPreview(parsed);
        break;
      case 'linkedin':
        previewHtml = renderLinkedInPreview(parsed);
        break;
      case 'twitter':
        previewHtml = renderTwitterPreview(parsed);
        break;
      case 'newsletter':
        previewHtml = renderNewsletterPreview(parsed);
        break;
      case 'blog':
        previewHtml = renderBlogPreview(parsed);
        break;
      case 'faq':
        previewHtml = renderFaqPreview(parsed);
        break;
      case 'landing':
        previewHtml = renderLandingPreview(parsed);
        break;
      default:
        previewHtml = `<pre class="text-sm whitespace-pre-wrap">${escapeHtml(result.raw)}</pre>`;
    }
    
    return `
      <div class="glass-strong rounded-2xl border border-slate-200/50 overflow-hidden">
        <div class="p-4 border-b border-slate-200/50 bg-gradient-to-r from-slate-50 to-slate-100">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg ${config.color.includes('from-') ? 'bg-gradient-to-br ' + config.color : config.color} flex items-center justify-center text-white">
                <i class="${config.icon}"></i>
              </div>
              <div>
                <h3 class="font-semibold text-slate-800">${config.name}</h3>
                <p class="text-xs text-slate-500">Model: ${result.model || 'AI'}</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button class="copy-format-btn px-3 py-1.5 text-sm bg-white hover:bg-slate-50 border border-slate-200 rounded-lg transition-colors flex items-center gap-1.5" data-format="${format}">
                <i class="iconoir-copy"></i> Copy
              </button>
              <button class="download-format-btn px-3 py-1.5 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors flex items-center gap-1.5" data-format="${format}">
                <i class="iconoir-download"></i> Download
              </button>
            </div>
          </div>
        </div>
        
        <div class="p-4">
          <div class="preview-toggle mb-4">
            <button class="active" data-view="preview">Preview</button>
            <button data-view="raw">Code</button>
          </div>
          <div class="preview-view">${previewHtml}</div>
          <div class="raw-view hidden">
            <div class="raw-preview"><pre>${escapeHtml(result.raw)}</pre></div>
          </div>
        </div>
      </div>
    `;
  }

  // === Social Preview Renderers ===
  function renderInstagramPreview(parsed) {
    const caption = parsed.caption || parsed.raw || '';
    const hashtags = parsed.hashtags || '';
    const visual = parsed.visual || '';
    
    return `
      <div class="instagram-preview">
        <div class="ig-header">
          <div class="ig-avatar">E</div>
          <span class="ig-username">your_brand</span>
          <i class="iconoir-more-horiz ig-more"></i>
        </div>
        <div class="ig-image">
          <span>${visual ? escapeHtml(visual) : '📷 Suggested image'}</span>
        </div>
        <div class="ig-actions">
          <i class="iconoir-heart ig-action"></i>
          <i class="iconoir-chat-bubble ig-action"></i>
          <i class="iconoir-send ig-action"></i>
        </div>
        <div class="ig-likes">1,234 Likes</div>
        <div class="ig-caption">
          <span class="username">tu_marca</span>
          ${escapeHtml(caption).replace(/\n/g, '<br>')}
        </div>
        ${hashtags ? `<div class="ig-hashtags">${escapeHtml(hashtags)}</div>` : ''}
      </div>
    `;
  }

  function renderFacebookPreview(parsed) {
    const post = parsed.post || parsed.raw || '';
    const suggestions = parsed.suggestions || '';
    
    return `
      <div class="facebook-preview">
        <div class="fb-header">
          <div class="fb-avatar">E</div>
          <div class="fb-info">
            <div class="fb-name">Your Brand</div>
            <div class="fb-meta">Now · <i class="iconoir-globe"></i></div>
          </div>
        </div>
        <div class="fb-content">${escapeHtml(post).replace(/\n/g, '<br>')}</div>
        <div class="fb-reactions">
          <span>👍 ❤️ 42</span>
          <span>12 comments · 5 shares</span>
        </div>
        <div class="fb-actions">
          <div class="fb-action"><i class="iconoir-thumbs-up"></i> Likes</div>
          <div class="fb-action"><i class="iconoir-chat-bubble"></i> Comment</div>
          <div class="fb-action"><i class="iconoir-share-android"></i> Share</div>
        </div>
        ${suggestions ? `<div class="p-3 bg-amber-50 border-t border-amber-200 text-sm text-amber-800"><strong>💡 Suggestions:</strong> ${escapeHtml(suggestions)}</div>` : ''}
      </div>
    `;
  }

  function renderLinkedInPreview(parsed) {
    const post = parsed.post || parsed.raw || '';
    const hashtags = parsed.hashtags || '';
    
    return `
      <div class="linkedin-preview">
        <div class="li-header">
          <div class="li-avatar">E</div>
          <div class="li-info">
            <div class="li-name">Your Name</div>
            <div class="li-title">CEO at Your Company</div>
            <div class="li-meta">Now · <i class="iconoir-globe"></i></div>
          </div>
        </div>
        <div class="li-content">${escapeHtml(post).replace(/\n/g, '<br>')}</div>
        ${hashtags ? `<div class="li-hashtags">${escapeHtml(hashtags)}</div>` : ''}
        <div class="li-engagement">
          <span>👍 💡 ❤️ 156</span>
          <span>23 comments</span>
        </div>
        <div class="li-actions">
          <div class="li-action"><i class="iconoir-thumbs-up"></i> Recommend</div>
          <div class="li-action"><i class="iconoir-chat-bubble"></i> Comment</div>
          <div class="li-action"><i class="iconoir-redo"></i> Share</div>
        </div>
      </div>
    `;
  }

  function renderTwitterPreview(parsed) {
    const tweets = parsed.tweets || [];
    const hashtags = parsed.hashtags || '';
    
    if (tweets.length === 0) {
      return `<div class="twitter-preview"><div class="tweet"><div class="tweet-content">${escapeHtml(parsed.raw || '')}</div></div></div>`;
    }
    
    return `
      <div class="twitter-preview">
        ${tweets.map((tweet, i) => `
          ${i > 0 ? '<div class="thread-line"></div>' : ''}
          <div class="tweet">
            <div class="tweet-header">
              <div class="tweet-avatar">E</div>
              <div class="tweet-info">
                <div class="tweet-author">
                  <span class="tweet-name">Your Brand</span>
                  <span class="tweet-handle">@your_brand · now</span>
                </div>
              </div>
            </div>
            <div class="tweet-content">${escapeHtml(tweet).replace(/\n/g, '<br>')}</div>
            <div class="tweet-actions">
              <span class="tweet-action"><i class="iconoir-chat-bubble"></i> 12</span>
              <span class="tweet-action"><i class="iconoir-redo"></i> 34</span>
              <span class="tweet-action"><i class="iconoir-heart"></i> 156</span>
              <span class="tweet-action"><i class="iconoir-share-android"></i></span>
            </div>
          </div>
        `).join('')}
        ${hashtags ? `<div class="tweet-hashtags">${escapeHtml(hashtags)}</div>` : ''}
      </div>
    `;
  }

  function renderNewsletterPreview(parsed) {
    const subject = parsed.subject || 'Email subject';
    const preheader = parsed.preheader || '';
    const body = parsed.body || parsed.raw || '';
    
    return `
      <div class="newsletter-preview">
        <div class="email-container">
          <div class="email-header">
            <div class="email-logo">📧</div>
            <h2 class="email-subject">${escapeHtml(subject)}</h2>
            ${preheader ? `<p class="email-preheader">${escapeHtml(preheader)}</p>` : ''}
          </div>
          <div class="email-body">${renderMarkdown(body)}</div>
          <div class="email-cta">
            <a href="#" class="email-btn">Read more</a>
          </div>
          <div class="email-footer">
            © 2025 Your Company. All rights reserved.<br>
            <a href="#">Unsubscribe</a>
          </div>
        </div>
      </div>
    `;
  }

  function renderBlogPreview(parsed) {
    const title = parsed.seo_title || 'Article title';
    const description = parsed.meta_description || '';
    const article = parsed.article || parsed.raw || '';
    
    return `
      <div class="blog-preview">
        <div class="blog-header">
          <div class="blog-meta">ARTICLE · ${new Date().toLocaleDateString('en-US', { day: 'numeric', month: 'long', year: 'numeric' })}</div>
          <h1 class="blog-title">${escapeHtml(title)}</h1>
          ${description ? `<p class="blog-description">${escapeHtml(description)}</p>` : ''}
        </div>
        <div class="blog-content">${renderMarkdown(article)}</div>
      </div>
    `;
  }

  function renderFaqPreview(parsed) {
    const faqItems = parsed.faq_items || [];
    const faqs = parsed.faqs || parsed.raw || '';
    
    if (faqItems.length === 0) {
      return `<div class="faq-preview"><div class="p-4">${escapeHtml(faqs).replace(/\n/g, '<br>')}</div></div>`;
    }
    
    return `
      <div class="faq-preview">
        <div class="faq-header">
          <h2 class="faq-title">Frequently Asked Questions</h2>
        </div>
        ${faqItems.map(faq => `
          <div class="faq-item">
            <div class="faq-question">${escapeHtml(faq.question)}</div>
            <div class="faq-answer">${escapeHtml(faq.answer)}</div>
          </div>
        `).join('')}
      </div>
    `;
  }

  function renderLandingPreview(parsed) {
    const html = parsed.html || '';
    if (!html) {
      return `<div class="text-center text-slate-500 py-8">Could not extract HTML from the landing page</div>`;
    }
    
    return `
      <div class="landing-preview">
        <div class="landing-toolbar">
          <div class="landing-dots">
            <span class="landing-dot red"></span>
            <span class="landing-dot yellow"></span>
            <span class="landing-dot green"></span>
          </div>
          <div class="landing-url">https://your-domain.com/landing</div>
        </div>
        <iframe class="landing-iframe" srcdoc="${escapeHtml(html)}"></iframe>
      </div>
    `;
  }

  function renderMarkdown(text) {
    if (!text) return '';
    return text
      .replace(/^### (.*$)/gm, '<h3>$1</h3>')
      .replace(/^## (.*$)/gm, '<h2>$1</h2>')
      .replace(/^# (.*$)/gm, '<h1>$1</h1>')
      .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.*?)\*/g, '<em>$1</em>')
      .replace(/\n/g, '<br>');
  }

  function getTextToCopy(format, result) {
    const parsed = result.parsed || {};
    switch (format) {
      case 'instagram':
        return (parsed.caption || '') + '\n\n' + (parsed.hashtags || '');
      case 'facebook':
      case 'linkedin':
        return parsed.post || result.raw;
      case 'twitter':
        return (parsed.tweets || []).join('\n\n---\n\n');
      case 'landing':
        return parsed.html || result.raw;
      case 'newsletter':
        return parsed.body || result.raw;
      default:
        return result.raw;
    }
  }

  function downloadFormat(format, result) {
    const parsed = result.parsed || {};
    let filename, content, mimeType;
    
    switch (format) {
      case 'landing':
        content = parsed.html || result.raw;
        filename = 'landing-page.html';
        mimeType = 'text/html';
        break;
      case 'blog':
        content = parsed.article || result.raw;
        filename = 'blog-article.md';
        mimeType = 'text/markdown';
        break;
      default:
        content = getTextToCopy(format, result);
        filename = `${format}-content.txt`;
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
  }

  window.resetUI = function() {
    resultSection.classList.add('hidden');
    inputSection.classList.remove('hidden');
    
    // Reset form
    sourceUrl.value = '';
    sourceText.value = '';
    if (sourcePdf) sourcePdf.value = '';
    pdfBase64 = null;
    if (pdfFilename) pdfFilename.classList.add('hidden');
    
    currentResults = null;
    hideError();
  };

  // === Copy All ===
  if (copyAllBtn) {
    copyAllBtn.addEventListener('click', async () => {
      if (!currentResults || !currentResults.results) return;
      
      let allContent = '';
      Object.entries(currentResults.results).forEach(([format, result]) => {
        const config = formatConfig[format] || { name: format };
        allContent += `=== ${config.name.toUpperCase()} ===\n\n`;
        allContent += getTextToCopy(format, result);
        allContent += '\n\n\n';
      });
      
      try {
        await navigator.clipboard.writeText(allContent.trim());
        copyAllBtn.innerHTML = '<i class="iconoir-check"></i> Copied';
        setTimeout(() => {
          copyAllBtn.innerHTML = '<i class="iconoir-copy"></i> Copy all';
        }, 2000);
      } catch (err) {
        console.error('Error copying:', err);
      }
    });
  }

  // === Preview/Raw Toggle ===
  document.addEventListener('click', (e) => {
    const toggleBtn = e.target.closest('.preview-toggle button');
    if (!toggleBtn) return;
    
    const panel = toggleBtn.closest('.result-panel') || toggleBtn.closest('.glass-strong');
    if (!panel) return;
    
    const view = toggleBtn.dataset.view;
    const previewView = panel.querySelector('.preview-view');
    const rawView = panel.querySelector('.raw-view');
    
    toggleBtn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
    toggleBtn.classList.add('active');
    
    if (view === 'preview') {
      previewView?.classList.remove('hidden');
      rawView?.classList.add('hidden');
    } else {
      previewView?.classList.add('hidden');
      rawView?.classList.remove('hidden');
    }
  });

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
          No transformations yet
        </div>
      `;
      return;
    }

    historyList.innerHTML = items.map(item => {
      const outputData = typeof item.output_data === 'string' 
        ? JSON.parse(item.output_data) 
        : item.output_data;
      const formats = outputData?.formats || [outputData?.format] || ['unknown'];
      const formatCount = formats.length;
      const firstFormat = formats[0];
      const config = formatConfig[firstFormat] || { icon: 'iconoir-sparks' };
      const date = new Date(item.created_at).toLocaleDateString('en-US', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
      });

      return `
        <div class="history-item group border-b border-slate-100 last:border-0" data-id="${item.id}">
          <div class="history-item-main p-3 hover:bg-slate-50 cursor-pointer flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
              <i class="${config.icon} text-indigo-600 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-slate-800 truncate">${escapeHtml(item.title || 'Untitled')}</p>
              <p class="text-xs text-slate-500 mt-0.5">${formatCount > 1 ? `${formatCount} formats · ` : ''}${date}</p>
            </div>
            <button class="history-item-delete opacity-0 group-hover:opacity-100 p-1.5 hover:bg-red-50 rounded transition-all" title="Delete">
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
        
        // Handle both old single-format and new multi-format structures
        if (outputData?.results) {
          // New multi-format structure
          currentResults = {
            title: item.title,
            results: outputData.results,
            formats: outputData.formats,
            source: outputData.original_title || 'History',
            total_generated: outputData.total_generated || Object.keys(outputData.results).length
          };
          showResults(currentResults);
        } else {
          // Legacy single-format (convert to multi-format structure)
          const format = outputData?.format || 'unknown';
          currentResults = {
            title: item.title,
            results: {
              [format]: {
                format: format,
                format_name: outputData?.format_name || format,
                raw: item.output_content,
                parsed: {},
                model: 'AI'
              }
            },
            formats: [format],
            source: outputData?.original_title || 'History',
            total_generated: 1
          };
          showResults(currentResults);
        }
        
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
    if (!confirm('Delete this transformation from history?')) return;
    
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
