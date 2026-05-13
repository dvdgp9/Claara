<?php
/**
 * Mobile Drawer - Sidebar deslizable para móvil
 * 
 * Variables opcionales:
 * - $drawerId: ID único del drawer (default: 'mobile-drawer')
 * - $drawerTitle: Título del drawer
 * - $drawerIcon: Icono del título
 * - $drawerIconColor: Color del icono (default: 'text-[#23AAC5]')
 * - $drawerContent: Contenido HTML del drawer (si no se usa slot)
 * - $drawerShowNewButton: Si mostrar botón "New" (default: false)
 * - $drawerNewButtonId: ID del botón nuevo
 * - $drawerNewButtonText: Texto del botón nuevo
 */
$drawerId = $drawerId ?? 'mobile-drawer';
$drawerTitle = $drawerTitle ?? 'History';
$drawerIcon = $drawerIcon ?? 'iconoir-clock';
$drawerIconColor = $drawerIconColor ?? 'text-[#23AAC5]';
$drawerShowNewButton = $drawerShowNewButton ?? false;
$drawerNewButtonId = $drawerNewButtonId ?? 'drawer-new-btn';
$drawerNewButtonText = $drawerNewButtonText ?? 'New';
?>
<!-- Mobile Drawer Overlay -->
<div id="<?php echo $drawerId; ?>-overlay" 
     class="lg:hidden fixed inset-0 z-40 bg-black/50 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300"
     onclick="closeMobileDrawer('<?php echo $drawerId; ?>')">
</div>

<!-- Mobile Drawer Panel -->
<aside id="<?php echo $drawerId; ?>" 
       class="lg:hidden fixed top-0 left-0 bottom-0 z-50 w-[85vw] max-w-[320px] bg-white shadow-2xl transform -translate-x-full transition-transform duration-300 ease-out flex flex-col">
  
  <!-- Drawer Header -->
  <div class="flex items-center justify-between p-4 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-white">
    <div class="flex items-center gap-2">
      <i class="<?php echo $drawerIcon; ?> <?php echo $drawerIconColor; ?> text-lg"></i>
      <h2 class="font-semibold text-slate-800"><?php echo htmlspecialchars($drawerTitle); ?></h2>
    </div>
    <button onclick="closeMobileDrawer('<?php echo $drawerId; ?>')" 
            class="p-2 -mr-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
      <i class="iconoir-xmark text-xl"></i>
    </button>
  </div>
  
  <?php if ($drawerShowNewButton): ?>
  <!-- New Button -->
  <div class="p-4 border-b border-slate-100">
    <button id="<?php echo $drawerNewButtonId; ?>" 
            class="w-full py-2.5 px-4 rounded-xl gradient-brand text-white font-medium shadow-md hover:shadow-lg hover:opacity-90 transition-all flex items-center justify-center gap-2">
      <i class="iconoir-plus text-lg"></i>
      <?php echo htmlspecialchars($drawerNewButtonText); ?>
    </button>
  </div>
  <?php endif; ?>
  
  <!-- Drawer Content (scrollable) -->
  <div id="<?php echo $drawerId; ?>-content" class="flex-1 overflow-y-auto px-3 py-4">
    <?php if (isset($drawerContent)): ?>
      <?php echo $drawerContent; ?>
    <?php else: ?>
      <!-- Contenido se insertará dinámicamente o via slot -->
      <div class="p-4 text-center text-slate-400 text-sm">
        <i class="iconoir-refresh animate-spin"></i>
        <span class="ml-2">Loading...</span>
      </div>
    <?php endif; ?>
  </div>
</aside>

<script>
// Funciones globales para manejar el drawer móvil
function openMobileDrawer(drawerId) {
  const drawer = document.getElementById(drawerId);
  const overlay = document.getElementById(drawerId + '-overlay');
  
  if (drawer && overlay) {
    // Mostrar overlay
    overlay.classList.remove('opacity-0', 'pointer-events-none');
    overlay.classList.add('opacity-100', 'pointer-events-auto');
    
    // Mostrar drawer
    drawer.classList.remove('-translate-x-full');
    drawer.classList.add('translate-x-0');
    
    // Bloquear scroll del body
    document.body.style.overflow = 'hidden';
  }
}

function closeMobileDrawer(drawerId) {
  const drawer = document.getElementById(drawerId);
  const overlay = document.getElementById(drawerId + '-overlay');
  
  if (drawer && overlay) {
    // Ocultar drawer
    drawer.classList.remove('translate-x-0');
    drawer.classList.add('-translate-x-full');
    
    // Ocultar overlay
    overlay.classList.remove('opacity-100', 'pointer-events-auto');
    overlay.classList.add('opacity-0', 'pointer-events-none');
    
    // Restaurar scroll del body
    document.body.style.overflow = '';
  }
}

function toggleMobileDrawer(drawerId) {
  const drawer = document.getElementById(drawerId);
  if (drawer && drawer.classList.contains('-translate-x-full')) {
    openMobileDrawer(drawerId);
  } else {
    closeMobileDrawer(drawerId);
  }
}

// Cerrar drawer con tecla Escape
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    // Cerrar todos los drawers abiertos
    document.querySelectorAll('[id$="-overlay"].opacity-100').forEach(overlay => {
      const drawerId = overlay.id.replace('-overlay', '');
      closeMobileDrawer(drawerId);
    });
  }
});
</script>
