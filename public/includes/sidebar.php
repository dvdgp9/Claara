<?php
// Sidebar - conversation and folder navigation
?>
<aside id="conversations-sidebar" class="w-80 bg-white border-r border-slate-200 flex flex-col shadow-sm">
  <div class="p-5 border-b border-slate-200">
    <div class="flex items-center gap-3 mb-6">
      <img src="/assets/images/logo.png" alt="Claara" class="h-9">
    </div>
    <button id="new-conv-btn" class="w-full py-2.5 px-4 rounded-lg gradient-brand-btn text-[#2F3440] font-medium shadow-md hover:shadow-lg hover:opacity-90 transition-all duration-200 flex items-center justify-center gap-2">
      <span class="text-lg">+</span> <?php echo htmlspecialchars(\I18n\I18n::translate('sidebar.new_conversation')); ?>
    </button>
  </div>
  <div class="flex-1 overflow-y-auto p-3">
    <!-- Folders -->
    <div class="mb-4">
      <div class="flex items-center justify-between mb-2 px-2">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider"><?php echo htmlspecialchars(\I18n\I18n::translate('sidebar.folders')); ?></div>
        <button id="new-folder-btn" class="p-1 text-slate-400 hover:text-[#B7C9F2] hover:bg-[#B7C9F2]/10 rounded transition-colors" title="<?php echo htmlspecialchars(\I18n\I18n::translate('sidebar.new_folder')); ?>">
          <i class="iconoir-folder-plus text-sm"></i>
        </button>
      </div>
      <ul id="folder-list" class="space-y-1">
        <!-- "All" is always visible -->
        <li>
          <button data-folder-id="-1" class="folder-item w-full text-left p-2 rounded-lg transition-all duration-200 flex items-center gap-2 hover:bg-slate-50 group">
            <i class="iconoir-folder text-[#B7C9F2]"></i>
            <span class="flex-1 text-sm text-slate-700"><?php echo htmlspecialchars(\I18n\I18n::translate('sidebar.all')); ?></span>
            <span class="text-xs text-slate-400" id="all-count">0</span>
          </button>
        </li>
        <!-- "No folder" -->
        <li>
          <button data-folder-id="0" class="folder-item w-full text-left p-2 rounded-lg transition-all duration-200 flex items-center gap-2 hover:bg-slate-50 group">
            <i class="iconoir-folder text-[#B7C9F2]"></i>
            <span class="flex-1 text-sm text-slate-700"><?php echo htmlspecialchars(\I18n\I18n::translate('sidebar.no_folder')); ?></span>
            <span class="text-xs text-slate-400" id="root-count">0</span>
          </button>
        </li>
        <!-- Dynamic folders are inserted here -->
      </ul>
    </div>
    
    <!-- Conversations -->
    <div>
      <div class="flex items-center justify-between mb-2 px-2">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider"><?php echo htmlspecialchars(\I18n\I18n::translate('nav.conversations')); ?></div>
        <select id="sort-select" class="text-xs border border-slate-200 rounded px-2 py-1 bg-white focus:outline-none focus:border-[#B7C9F2]">
          <option value="updated_at"><?php echo htmlspecialchars(\I18n\I18n::translate('sidebar.recent')); ?></option>
          <option value="favorite"><?php echo htmlspecialchars(\I18n\I18n::translate('sidebar.favorites')); ?></option>
          <option value="created_at"><?php echo htmlspecialchars(\I18n\I18n::translate('sidebar.created')); ?></option>
          <option value="title"><?php echo htmlspecialchars(\I18n\I18n::translate('sidebar.alphabetical')); ?></option>
        </select>
      </div>
      <ul id="conv-list" class="space-y-1">
        <li class="text-slate-400 text-sm px-3 py-2"><?php echo htmlspecialchars(\I18n\I18n::translate('sidebar.empty')); ?></li>
      </ul>
    </div>
  </div>
</aside>
