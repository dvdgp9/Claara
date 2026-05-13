<?php
// Sidebar - conversation and folder navigation
?>
<aside id="conversations-sidebar" class="w-80 bg-white border-r border-slate-200 flex flex-col shadow-sm">
  <div class="p-5 border-b border-slate-200">
    <div class="flex items-center gap-3 mb-6">
      <img src="/assets/images/logo.png" alt="iaiaPRO" class="h-9">
    </div>
    <button id="new-conv-btn" class="w-full py-2.5 px-4 rounded-lg gradient-brand-btn text-white font-medium shadow-md hover:shadow-lg hover:opacity-90 transition-all duration-200 flex items-center justify-center gap-2">
      <span class="text-lg">+</span> New conversation
    </button>
  </div>
  <div class="flex-1 overflow-y-auto p-3">
    <!-- Folders -->
    <div class="mb-4">
      <div class="flex items-center justify-between mb-2 px-2">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Folders</div>
        <button id="new-folder-btn" class="p-1 text-slate-400 hover:text-[#23AAC5] hover:bg-[#23AAC5]/10 rounded transition-colors" title="New folder">
          <i class="iconoir-folder-plus text-sm"></i>
        </button>
      </div>
      <ul id="folder-list" class="space-y-1">
        <!-- "All" is always visible -->
        <li>
          <button data-folder-id="-1" class="folder-item w-full text-left p-2 rounded-lg transition-all duration-200 flex items-center gap-2 hover:bg-slate-50 group">
            <i class="iconoir-folder text-[#23AAC5]"></i>
            <span class="flex-1 text-sm text-slate-700">All</span>
            <span class="text-xs text-slate-400" id="all-count">0</span>
          </button>
        </li>
        <!-- "No folder" -->
        <li>
          <button data-folder-id="0" class="folder-item w-full text-left p-2 rounded-lg transition-all duration-200 flex items-center gap-2 hover:bg-slate-50 group">
            <i class="iconoir-folder text-[#23AAC5]"></i>
            <span class="flex-1 text-sm text-slate-700">No folder</span>
            <span class="text-xs text-slate-400" id="root-count">0</span>
          </button>
        </li>
        <!-- Dynamic folders are inserted here -->
      </ul>
    </div>
    
    <!-- Conversations -->
    <div>
      <div class="flex items-center justify-between mb-2 px-2">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Conversations</div>
        <select id="sort-select" class="text-xs border border-slate-200 rounded px-2 py-1 bg-white focus:outline-none focus:border-[#23AAC5]">
          <option value="updated_at">Recent</option>
          <option value="favorite">Favorites</option>
          <option value="created_at">Created</option>
          <option value="title">Alphabetical</option>
        </select>
      </div>
      <ul id="conv-list" class="space-y-1">
        <li class="text-slate-400 text-sm px-3 py-2">(empty)</li>
      </ul>
    </div>
  </div>
</aside>
