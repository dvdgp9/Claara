/**
 * Chat UI Module
 * Handles UI rendering for chat messages
 */

import ChatState from './chat-state.js';
import { makeMessageSelectable } from './chat-selection.js';

/**
 * Escape HTML entities
 */
export function escapeHtml(str) {
  return str.replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
}

/**
 * Convert markdown to HTML
 */
export function mdToHtml(md) {
  let s = escapeHtml(md);
  // headings
  s = s.replace(/^###\s+(.+)$/gm, '<h3 class="font-semibold text-base mb-1">$1</h3>');
  s = s.replace(/^##\s+(.+)$/gm, '<h2 class="font-semibold text-lg mb-1">$1</h2>');
  s = s.replace(/^#\s+(.+)$/gm, '<h1 class="font-semibold text-xl mb-1">$1</h1>');
  // bold and italics
  s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  s = s.replace(/\*(.+?)\*/g, '<em>$1</em>');
  // inline code
  s = s.replace(/`([^`]+)`/g, '<code class="px-1 py-0.5 bg-slate-100 rounded">$1</code>');
  // tables
  s = s.replace(/((?:\n|^)\|[^\n]+\|\r?\n\|[ :\-|]+\|\r?\n(?:\|[^\n]+\|(?:\r?\n|$))+)/g, function(match) {
    const lines = match.trim().split(/\r?\n/);
    let html = '<div class="table-container"><table class="md-table">';
    let hasHeader = false;
    lines.forEach((line) => {
      if (line.match(/^\|[ :\-|]+\|$/)) return;
      const cells = line.split('|').filter((c, i, a) => i > 0 && i < a.length - 1);
      if (cells.length === 0) return;
      const tag = !hasHeader ? 'th' : 'td';
      const row = '<tr>' + cells.map(c => `<${tag}>${c.trim()}</${tag}>`).join('') + '</tr>';
      if (!hasHeader) {
        html += '<thead>' + row + '</thead><tbody>';
        hasHeader = true;
      } else {
        html += row;
      }
    });
    html += '</tbody></table></div>';
    return html;
  });
  // Markdown links
  s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-cyan-600 hover:text-cyan-700 hover:underline">$1</a>');
  // Auto-link plain URLs
  s = s.replace(/(?<!href=")(https?:\/\/[^\s<]+[^\s<.,;:!?\)\]'"])/g, function(url) {
    let cleanUrl = url.replace(/[.,;:!?\)\]]+$/, '');
    let trailing = url.slice(cleanUrl.length);
    return '<a href="' + cleanUrl + '" target="_blank" rel="noopener noreferrer" class="text-cyan-600 hover:text-cyan-700 hover:underline">' + cleanUrl + '</a>' + trailing;
  });
  // line breaks
  s = s.replace(/\n/g, '<br>');
  return s;
}

/**
 * Create a message element
 */
export function createMessageElement(role, content, options = {}) {
  const { messageId, file, images, annotations, isStreaming } = options;
  
  const wrap = document.createElement('div');
  wrap.className = 'mb-6 flex flex-col ' + (role === 'user' ? 'items-end' : 'items-start');
  if (messageId) wrap.dataset.messageWrap = messageId;
  
  const msgContainer = document.createElement('div');
  msgContainer.className = 'flex gap-3 max-w-3xl ' + (role === 'user' ? 'flex-row-reverse' : 'flex-row');
  
  // Avatar
  const avatar = document.createElement('div');
  avatar.className = role === 'user'
    ? 'w-9 h-9 rounded-full gradient-brand flex items-center justify-center text-white text-sm font-semibold flex-shrink-0 shadow-sm'
    : 'w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 text-sm font-semibold flex-shrink-0';
  avatar.textContent = role === 'user' 
    ? (ChatState.currentUser ? ChatState.currentUser.first_name[0] + ChatState.currentUser.last_name[0] : '?')
    : 'E';
  
  // Bubble
  const bubble = document.createElement('div');
  bubble.className = role === 'user' 
    ? 'gradient-brand text-white px-5 py-3.5 rounded-2xl rounded-tr-sm shadow-md text-conversation' 
    : 'bg-white/30 backdrop-blur-sm border border-slate-200 text-slate-800 px-5 py-3.5 rounded-2xl rounded-tl-sm shadow-sm text-conversation';
  bubble.style.wordBreak = 'break-word';
  
  // Make assistant messages selectable
  if (messageId) {
    makeMessageSelectable(bubble, messageId, role);
  }
  
  // Content
  if (role === 'assistant') {
    bubble.innerHTML = mdToHtml(content);
    
    // Add streaming indicator if streaming
    if (isStreaming) {
      const indicator = document.createElement('span');
      indicator.className = 'streaming-indicator';
      indicator.innerHTML = '<span></span><span></span><span></span>';
      bubble.appendChild(indicator);
    }
  } else {
    bubble.textContent = content;
  }
  
  // Add file attachment if exists
  if (file && role === 'user') {
    const fileEl = document.createElement('div');
    fileEl.className = 'mt-2 flex items-center gap-2 text-sm opacity-90';
    const icon = file.mime_type === 'application/pdf' 
      ? '<i class="iconoir-page"></i>' 
      : '<i class="iconoir-media-image"></i>';
    if (file.expired) {
      fileEl.innerHTML = `${icon} <span class="line-through">${escapeHtml(file.name)}</span> <span class="text-xs">(expired)</span>`;
    } else {
      fileEl.innerHTML = `${icon} <a href="${file.url}" target="_blank" class="underline hover:no-underline">${escapeHtml(file.name)}</a>`;
    }
    bubble.appendChild(fileEl);
  }
  
  // Add generated images
  if (images && images.length > 0 && role === 'assistant') {
    const imagesContainer = createImagesContainer(images);
    bubble.appendChild(imagesContainer);
  }
  
  // Add web citations
  if (annotations && annotations.length > 0 && role === 'assistant') {
    const citationsContainer = createCitationsContainer(annotations);
    bubble.appendChild(citationsContainer);
  }
  
  msgContainer.appendChild(avatar);
  msgContainer.appendChild(bubble);
  
  // Timestamp
  const timestamp = document.createElement('div');
  timestamp.className = 'text-xs text-slate-400 mt-1 px-3';
  const now = new Date();
  timestamp.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
  
  wrap.appendChild(msgContainer);
  wrap.appendChild(timestamp);
  
  return { wrap, bubble };
}

/**
 * Create images container for generated images
 */
function createImagesContainer(images) {
  const container = document.createElement('div');
  container.className = 'mt-3 space-y-3';
  
  images.forEach((img, idx) => {
    const imgUrl = img.image_url?.url || img.imageUrl?.url || '';
    if (!imgUrl) return;
    
    const imgWrap = document.createElement('div');
    imgWrap.className = 'relative group';
    
    const imgEl = document.createElement('img');
    imgEl.src = imgUrl;
    imgEl.alt = 'Generated image ' + (idx + 1);
    imgEl.className = 'max-w-full rounded-xl shadow-md cursor-pointer hover:shadow-lg transition-shadow';
    imgEl.style.maxHeight = '400px';
    imgEl.addEventListener('click', () => {
      // Dispatch event for lightbox
      document.dispatchEvent(new CustomEvent('chat:open-lightbox', { detail: { url: imgUrl } }));
    });
    
    const actionsEl = document.createElement('div');
    actionsEl.className = 'mt-2 flex gap-2';
    
    const downloadBtn = document.createElement('a');
    downloadBtn.href = imgUrl;
    downloadBtn.download = `nanobanana-${Date.now()}-${idx + 1}.png`;
    downloadBtn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-lg transition-colors';
    downloadBtn.innerHTML = '<i class="iconoir-download"></i> Download';
    
    actionsEl.appendChild(downloadBtn);
    imgWrap.appendChild(imgEl);
    imgWrap.appendChild(actionsEl);
    container.appendChild(imgWrap);
  });
  
  return container;
}

/**
 * Create citations container for web search results
 */
function createCitationsContainer(annotations) {
  const container = document.createElement('div');
  container.className = 'mt-4 pt-3 border-t border-slate-200';
  
  const title = document.createElement('div');
  title.className = 'text-xs font-semibold text-slate-500 mb-2 flex items-center gap-1';
  title.innerHTML = '<i class="iconoir-globe"></i> Sources';
  container.appendChild(title);
  
  const list = document.createElement('div');
  list.className = 'space-y-1';
  
  annotations.forEach(ann => {
    const citation = ann.url_citation || ann;
    const url = citation.url || '';
    const citationTitle = citation.title || url;
    if (!url) return;
    
    const domain = url.replace(/^https?:\/\//, '').split('/')[0];
    
    const link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.className = 'block text-xs text-cyan-600 hover:text-cyan-700 hover:underline truncate';
    link.innerHTML = `<span class="font-medium">${escapeHtml(citationTitle)}</span> <span class="text-slate-400">— ${escapeHtml(domain)}</span>`;
    list.appendChild(link);
  });
  
  container.appendChild(list);
  return container;
}

/**
 * Update streaming message content
 */
export function updateStreamingMessage(bubble, content) {
  // Remove streaming indicator
  const indicator = bubble.querySelector('.streaming-indicator');
  
  // Update content
  bubble.innerHTML = mdToHtml(content);
  
  // Re-add streaming indicator
  if (indicator) {
    bubble.appendChild(indicator);
  }
}

/**
 * Finalize streaming message (remove indicator)
 */
export function finalizeStreamingMessage(bubble, content, images, annotations) {
  bubble.innerHTML = mdToHtml(content);
  
  if (images && images.length > 0) {
    const imagesContainer = createImagesContainer(images);
    bubble.appendChild(imagesContainer);
  }
  
  if (annotations && annotations.length > 0) {
    const citationsContainer = createCitationsContainer(annotations);
    bubble.appendChild(citationsContainer);
  }
}

/**
 * Create stop button element
 */
export function createStopButton() {
  const btn = document.createElement('button');
  btn.id = 'stop-generation-btn';
  btn.className = 'fixed bottom-32 left-1/2 -translate-x-1/2 z-40 px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-full shadow-lg flex items-center gap-2 transition-all hover:scale-105 animate-in fade-in slide-in-from-bottom-4 duration-200';
  btn.innerHTML = `
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <rect x="6" y="6" width="12" height="12" rx="2" stroke-width="2"/>
    </svg>
    Stop generating
  `;
  return btn;
}

export default {
  escapeHtml,
  mdToHtml,
  createMessageElement,
  updateStreamingMessage,
  finalizeStreamingMessage,
  createStopButton
};
