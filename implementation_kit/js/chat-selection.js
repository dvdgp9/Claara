/**
 * Chat Selection Module
 * Handles text selection in assistant messages for partial regeneration
 */

import ChatState from './chat-state.js';

let toolbar = null;
let editModal = null;

/**
 * Initialize selection handling on message container
 */
export function initSelection(messagesContainer) {
  // Create floating toolbar if not exists
  if (!toolbar) {
    toolbar = createToolbar();
    document.body.appendChild(toolbar);
  }
  
  // Create edit modal if not exists
  if (!editModal) {
    editModal = createEditModal();
    document.body.appendChild(editModal);
  }
  
  // Listen for selection changes
  document.addEventListener('selectionchange', () => {
    handleSelectionChange(messagesContainer);
  });
  
  // Hide toolbar on click outside
  document.addEventListener('mousedown', (e) => {
    if (!toolbar.contains(e.target) && !editModal.contains(e.target)) {
      hideToolbar();
    }
  });
  
  // Hide on scroll
  messagesContainer.addEventListener('scroll', hideToolbar);
}

/**
 * Create the floating toolbar element
 */
function createToolbar() {
  const el = document.createElement('div');
  el.id = 'selection-toolbar';
  el.className = 'fixed z-50 hidden';
  el.innerHTML = `
    <div class="bg-slate-900 text-white rounded-xl shadow-2xl px-2 py-1.5 flex items-center gap-1 animate-in fade-in zoom-in-95 duration-150">
      <button id="selection-edit-btn" class="flex items-center gap-1.5 px-3 py-1.5 hover:bg-white/10 rounded-lg transition-colors text-sm font-medium">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
        </svg>
        Edit this
      </button>
      <div class="w-px h-5 bg-white/20"></div>
      <button id="selection-regenerate-btn" class="flex items-center gap-1.5 px-3 py-1.5 hover:bg-white/10 rounded-lg transition-colors text-sm font-medium">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Regenerate
      </button>
    </div>
    <div class="absolute left-1/2 -translate-x-1/2 -bottom-1.5 w-3 h-3 bg-slate-900 rotate-45"></div>
  `;
  
  // Bind toolbar button events
  el.querySelector('#selection-edit-btn').addEventListener('click', () => {
    showEditModal('edit');
  });
  
  el.querySelector('#selection-regenerate-btn').addEventListener('click', () => {
    showEditModal('regenerate');
  });
  
  return el;
}

/**
 * Create the edit modal element
 */
function createEditModal() {
  const el = document.createElement('div');
  el.id = 'selection-edit-modal';
  el.className = 'fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm';
  el.innerHTML = `
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-200">
      <div class="p-5 border-b border-slate-200">
        <h3 id="edit-modal-title" class="text-lg font-semibold text-slate-900">Edit Selection</h3>
        <p class="text-sm text-slate-500 mt-1">Tell the AI how you'd like this part changed</p>
      </div>
      
      <div class="p-5 space-y-4">
        <!-- Selected text preview -->
        <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
          <div class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2">Selected text</div>
          <div id="edit-modal-selection" class="text-sm text-slate-700 max-h-24 overflow-y-auto"></div>
        </div>
        
        <!-- Instructions input -->
        <div>
          <label for="edit-modal-instructions" class="block text-sm font-medium text-slate-700 mb-2">
            Your instructions
          </label>
          <textarea 
            id="edit-modal-instructions" 
            class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-all text-sm resize-none"
            rows="3"
            placeholder="e.g., Make this more formal, Add more detail about..., Simplify this explanation..."
          ></textarea>
        </div>
      </div>
      
      <div class="p-5 border-t border-slate-200 flex items-center justify-end gap-3 bg-slate-50">
        <button id="edit-modal-cancel" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors">
          Cancel
        </button>
        <button id="edit-modal-submit" class="px-5 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#23AAC5] to-[#115c6c] rounded-lg shadow-md hover:shadow-lg hover:opacity-90 transition-all flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
          </svg>
          Apply Changes
        </button>
      </div>
    </div>
  `;
  
  // Bind modal events
  el.querySelector('#edit-modal-cancel').addEventListener('click', hideEditModal);
  el.addEventListener('click', (e) => {
    if (e.target === el) hideEditModal();
  });
  
  el.querySelector('#edit-modal-submit').addEventListener('click', submitEdit);
  
  // Submit on Cmd/Ctrl+Enter
  el.querySelector('#edit-modal-instructions').addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      e.preventDefault();
      submitEdit();
    }
  });
  
  return el;
}

/**
 * Handle selection changes
 */
function handleSelectionChange(messagesContainer) {
  const selection = window.getSelection();
  
  if (!selection || selection.isCollapsed || selection.toString().trim() === '') {
    return;
  }
  
  // Check if selection is within an assistant message
  const range = selection.getRangeAt(0);
  const container = range.commonAncestorContainer;
  const messageEl = container.nodeType === Node.TEXT_NODE 
    ? container.parentElement?.closest('[data-message-id][data-role="assistant"]')
    : container.closest?.('[data-message-id][data-role="assistant"]');
  
  if (!messageEl) {
    hideToolbar();
    return;
  }
  
  // Store selection state
  ChatState.selectedText = selection.toString().trim();
  ChatState.selectedMessageId = messageEl.dataset.messageId;
  ChatState.selectionRange = range.cloneRange();
  
  // Position and show toolbar
  const rect = range.getBoundingClientRect();
  positionToolbar(rect);
}

/**
 * Position toolbar above selection
 */
function positionToolbar(rect) {
  if (!toolbar) return;
  
  // Show toolbar temporarily off-screen to measure it
  toolbar.style.visibility = 'hidden';
  toolbar.classList.remove('hidden');
  
  const toolbarRect = toolbar.getBoundingClientRect();
  const padding = 12;
  const toolbarHeight = toolbarRect.height || 44; // Fallback height
  const toolbarWidth = toolbarRect.width || 200; // Fallback width
  
  // Position above the selection, centered
  let top = rect.top - toolbarHeight - padding;
  let left = rect.left + (rect.width / 2) - (toolbarWidth / 2);
  
  // If selection is very small (single word), ensure minimum offset from top
  if (rect.height < 30) {
    top = rect.top - toolbarHeight - 8;
  }
  
  // Keep within viewport - if too high, show below
  if (top < padding) {
    top = rect.bottom + padding;
    // Move the arrow to top
    toolbar.querySelector('.rotate-45')?.classList.add('-top-1.5', '-bottom-1.5');
  }
  
  // Keep within horizontal bounds
  if (left < padding) {
    left = padding;
  }
  if (left + toolbarWidth > window.innerWidth - padding) {
    left = window.innerWidth - toolbarWidth - padding;
  }
  
  toolbar.style.top = `${top + window.scrollY}px`;
  toolbar.style.left = `${left}px`;
  toolbar.style.visibility = 'visible';
}

/**
 * Hide the toolbar
 */
export function hideToolbar() {
  if (toolbar) {
    toolbar.classList.add('hidden');
  }
}

/**
 * Show the edit modal
 */
function showEditModal(mode) {
  if (!editModal || !ChatState.selectedText) return;
  
  hideToolbar();
  
  const title = editModal.querySelector('#edit-modal-title');
  const selectionPreview = editModal.querySelector('#edit-modal-selection');
  const instructions = editModal.querySelector('#edit-modal-instructions');
  
  title.textContent = mode === 'edit' ? 'Edit Selection' : 'Regenerate Selection';
  selectionPreview.textContent = ChatState.selectedText;
  instructions.value = '';
  
  editModal.classList.remove('hidden');
  instructions.focus();
}

/**
 * Hide the edit modal
 */
export function hideEditModal() {
  if (editModal) {
    editModal.classList.add('hidden');
  }
}

/**
 * Submit the edit request
 */
async function submitEdit() {
  const instructions = editModal.querySelector('#edit-modal-instructions').value.trim();
  
  if (!instructions) {
    editModal.querySelector('#edit-modal-instructions').focus();
    return;
  }
  
  const submitBtn = editModal.querySelector('#edit-modal-submit');
  const originalText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...';
  submitBtn.disabled = true;
  
  try {
    // Dispatch custom event for the main app to handle
    const event = new CustomEvent('chat:regenerate-selection', {
      detail: {
        messageId: ChatState.selectedMessageId,
        selectedText: ChatState.selectedText,
        instructions: instructions
      }
    });
    document.dispatchEvent(event);
    
    hideEditModal();
  } catch (error) {
    console.error('Edit error:', error);
    alert('Error applying changes: ' + error.message);
  } finally {
    submitBtn.innerHTML = originalText;
    submitBtn.disabled = false;
  }
}

/**
 * Mark a message element as editable (add data attributes)
 */
export function makeMessageSelectable(messageEl, messageId, role) {
  messageEl.dataset.messageId = messageId;
  messageEl.dataset.role = role;
  
  if (role === 'assistant') {
    messageEl.classList.add('select-text');
  }
}

export default { 
  initSelection, 
  hideToolbar, 
  hideEditModal, 
  makeMessageSelectable 
};
