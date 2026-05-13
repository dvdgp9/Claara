<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/App/Session.php';

use App\Session;
use App\DB;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

// Verificar si es superadmin
$isSuperadmin = in_array('admin', $user['roles'] ?? [], true);
if (!$isSuperadmin) {
    header('Location: /');
    exit;
}

$pdo = DB::pdo();

// === FILTRO DE FECHAS ===
$range = $_GET['range'] ?? '30';
$intervalSql = match($range) {
    '7' => 'INTERVAL 7 DAY',
    '30' => 'INTERVAL 30 DAY',
    'all' => null,
    default => 'INTERVAL 30 DAY'
};
// Asegurar que range sea válido para la UI
if (!in_array($range, ['7', '30', 'all'])) $range = '30';

function dateCond($prefix = 'WHERE', $col = 'created_at', $alias = '') {
    global $intervalSql;
    if (!$intervalSql) return '';
    $column = $alias ? "$alias.$col" : $col;
    return "$prefix $column >= DATE_SUB(NOW(), $intervalSql)";
}

// === ESTADÍSTICAS GENERALES ===
// Nota: Usuarios siempre mostramos total histórico
// Usamos usage_log para estadísticas persistentes (no se borran cuando se eliminan mensajes)
$generalStats = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
        (SELECT COALESCE(SUM(count), 0) FROM usage_log WHERE action_type = 'conversation' " . dateCond('AND') . ") as total_conversations,
        (SELECT COALESCE(SUM(count), 0) FROM usage_log WHERE action_type = 'message' " . dateCond('AND') . ") as total_messages,
        (SELECT COUNT(*) FROM messages WHERE role = 'assistant' " . dateCond('AND') . ") as assistant_messages,
        (SELECT COALESCE(SUM(count), 0) FROM usage_log WHERE action_type = 'image' " . dateCond('AND') . ") as total_images,
        (SELECT COALESCE(SUM(count), 0) FROM usage_log WHERE action_type = 'gesture' " . dateCond('AND') . ") as total_gestures,
        (SELECT COALESCE(SUM(count), 0) FROM usage_log WHERE action_type = 'voice' " . dateCond('AND') . ") as total_voices
")->fetch();

// === USO POR USUARIO ===
// Usamos usage_log para estadísticas persistentes
$userStats = $pdo->query("
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.last_login_at,
        (SELECT COALESCE(SUM(count), 0) FROM usage_log ul WHERE ul.user_id = u.id AND ul.action_type = 'conversation' " . dateCond('AND', 'created_at', 'ul') . ") as conversations,
        (SELECT COALESCE(SUM(count), 0) FROM usage_log ul WHERE ul.user_id = u.id AND ul.action_type = 'message' " . dateCond('AND', 'created_at', 'ul') . ") as messages,
        (SELECT COALESCE(SUM(count), 0) FROM usage_log ul WHERE ul.user_id = u.id AND ul.action_type = 'image' " . dateCond('AND', 'created_at', 'ul') . ") as images,
        (SELECT COALESCE(SUM(count), 0) FROM usage_log ul WHERE ul.user_id = u.id AND ul.action_type = 'gesture' " . dateCond('AND', 'created_at', 'ul') . ") as gestures,
        (SELECT COALESCE(SUM(count), 0) FROM usage_log ul WHERE ul.user_id = u.id AND ul.action_type = 'voice' " . dateCond('AND', 'created_at', 'ul') . ") as voices
    FROM users u
    ORDER BY messages DESC
")->fetchAll();

// === USO POR MODELO (desde usage_log metadata.model) ===
$modelStats = $pdo->query("
    SELECT 
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.model')), 'Sin especificar') as model_name,
        SUM(count) as usage_count
    FROM usage_log
    WHERE action_type = 'message' " . dateCond('AND') . "
    GROUP BY model_name
    ORDER BY usage_count DESC
    LIMIT 20
")->fetchAll();

// === USO POR DÍA (solo mensajes) ===
// Si el rango es 'all', limitamos a últimos 90 días para que el gráfico no sea ilegible
$dailyInterval = $range === 'all' ? 'INTERVAL 90 DAY' : ($intervalSql ?: 'INTERVAL 90 DAY');
$dailyStats = $pdo->query("
    SELECT 
        DATE(created_at) as date,
        SUM(count) as messages
    FROM usage_log
    WHERE action_type = 'message'
      AND created_at >= DATE_SUB(NOW(), $dailyInterval)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// === GESTOS MÁS USADOS (desde usage_log) ===
$gestureStats = $pdo->query("
    SELECT 
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.gesture_type')), 'sin_tipo') as gesture_type,
        SUM(count) as usage_count,
        COUNT(DISTINCT user_id) as unique_users
    FROM usage_log
    WHERE action_type = 'gesture' " . dateCond('AND') . "
    GROUP BY gesture_type
    ORDER BY usage_count DESC
")->fetchAll();

// === VOCES MÁS USADAS (desde usage_log) ===
$voiceStats = $pdo->query("
    SELECT 
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.voice_id')), 'sin_voz') as voice_id,
        SUM(count) as usage_count,
        COUNT(DISTINCT user_id) as unique_users
    FROM usage_log
    WHERE action_type = 'voice' " . dateCond('AND') . "
    GROUP BY voice_id
    ORDER BY usage_count DESC
")->fetchAll();

// Preparar datos para gráfico
$chartLabels = array_map(fn($d) => date('d/m', strtotime($d['date'])), $dailyStats);
$chartData = array_map(fn($d) => (int)$d['messages'], $dailyStats);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard — iaiaPRO</title>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/assets/images/isotipo.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* Estilos base para el layout */
    .gradient-brand { background: linear-gradient(135deg, #23AAC5 0%, #115c6c 100%); }
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
  </style>
</head>
<body class="bg-slate-50 text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php 
    $activeTab = 'admin';
    $pageTitle = 'Dashboard';
    include __DIR__ . '/../includes/left-tabs.php'; 
    ?>

    <main class="flex-1 flex flex-col overflow-auto bg-slate-50">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <div class="flex-1 overflow-auto bg-slate-50 pb-16 lg:pb-0">
        <div class="max-w-7xl mx-auto p-4 lg:p-6">
          <!-- Header -->
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6 lg:mb-8 mt-4 lg:mt-6">
            <div>
              <h1 class="text-2xl lg:text-3xl font-bold text-slate-800">Dashboard</h1>
              <p class="text-slate-600 text-sm lg:text-base mt-1">iaiaPRO usage statistics</p>
            </div>
            <div class="flex gap-2 lg:gap-3">
              <!-- Filtro de rango -->
              <div class="flex bg-white rounded-lg border border-slate-200 p-1 shadow-sm">
                <a href="?range=7" class="px-3 py-1 text-sm rounded-md transition-all <?= $range === '7' ? 'bg-[#23AAC5] text-white font-medium shadow-sm' : 'text-slate-600 hover:bg-slate-50' ?>">7 días</a>
                <a href="?range=30" class="px-3 py-1 text-sm rounded-md transition-all <?= $range === '30' ? 'bg-[#23AAC5] text-white font-medium shadow-sm' : 'text-slate-600 hover:bg-slate-50' ?>">30 días</a>
                <a href="?range=all" class="px-3 py-1 text-sm rounded-md transition-all <?= $range === 'all' ? 'bg-[#23AAC5] text-white font-medium shadow-sm' : 'text-slate-600 hover:bg-slate-50' ?>">Todo</a>
              </div>

              <a href="/admin/users.php" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-all flex items-center gap-2 bg-white shadow-sm">
                <i class="iconoir-group"></i>
                <span>Gestión de usuarios</span>
              </a>
            </div>
          </div>

          <!-- Tarjetas resumen -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-4 mb-8">
      <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-lg bg-blue-100 flex items-center justify-center">
            <i class="iconoir-group text-blue-600"></i>
          </div>
          <div>
            <div class="text-2xl font-bold text-slate-800"><?= number_format($generalStats['total_users']) ?></div>
            <div class="text-xs text-slate-500">Usuarios (Total)</div>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-lg bg-green-100 flex items-center justify-center">
            <i class="iconoir-check-circle text-green-600"></i>
          </div>
          <div>
            <div class="text-2xl font-bold text-slate-800"><?= number_format($generalStats['active_users']) ?></div>
            <div class="text-xs text-slate-500">Activos (Total)</div>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-lg bg-purple-100 flex items-center justify-center">
            <i class="iconoir-chat-bubble text-purple-600"></i>
          </div>
          <div>
            <div class="text-2xl font-bold text-slate-800"><?= number_format($generalStats['total_conversations']) ?></div>
            <div class="text-xs text-slate-500">Conversaciones</div>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-lg bg-amber-100 flex items-center justify-center">
            <i class="iconoir-message-text text-amber-600"></i>
          </div>
          <div>
            <div class="text-2xl font-bold text-slate-800"><?= number_format($generalStats['total_messages']) ?></div>
            <div class="text-xs text-slate-500">Mensajes</div>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-lg bg-cyan-100 flex items-center justify-center">
            <i class="iconoir-sparks text-cyan-600"></i>
          </div>
          <div>
            <div class="text-2xl font-bold text-slate-800"><?= number_format($generalStats['assistant_messages']) ?></div>
            <div class="text-xs text-slate-500">Respuestas IA</div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-lg bg-orange-100 flex items-center justify-center">
            <i class="iconoir-media-image text-orange-600"></i>
          </div>
          <div>
            <div class="text-2xl font-bold text-slate-800"><?= number_format($generalStats['total_images']) ?></div>
            <div class="text-xs text-slate-500">Imágenes</div>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-lg bg-rose-100 flex items-center justify-center">
            <i class="iconoir-flash text-rose-600"></i>
          </div>
          <div>
            <div class="text-2xl font-bold text-slate-800"><?= number_format($generalStats['total_gestures']) ?></div>
            <div class="text-xs text-slate-500">Gestos</div>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-lg bg-indigo-100 flex items-center justify-center">
            <i class="iconoir-microphone text-indigo-600"></i>
          </div>
          <div>
            <div class="text-2xl font-bold text-slate-800"><?= number_format($generalStats['total_voices']) ?></div>
            <div class="text-xs text-slate-500">Voces</div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Gráfico de actividad -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <i class="iconoir-graph-up text-[#23AAC5]"></i>
          Actividad (<?= $range === 'all' ? 'histórico' : ($range === '7' ? 'últimos 7 días' : 'últimos 30 días') ?>)
        </h2>
        <div class="h-64">
          <canvas id="activityChart"></canvas>
        </div>
      </div>

      <!-- Uso por modelo -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <i class="iconoir-cpu text-[#23AAC5]"></i>
          Uso por Modelo
        </h2>
        <div class="space-y-3 max-h-64 overflow-y-auto">
          <?php if (empty($modelStats)): ?>
            <p class="text-slate-500 text-sm">Sin datos de modelos aún</p>
          <?php else: ?>
            <?php 
            $maxModel = max(array_column($modelStats, 'usage_count'));
            foreach ($modelStats as $model): 
              $percent = $maxModel > 0 ? ($model['usage_count'] / $maxModel) * 100 : 0;
            ?>
            <div>
              <div class="flex justify-between text-sm mb-1">
                <span class="text-slate-700 font-medium truncate"><?= htmlspecialchars($model['model_name']) ?></span>
                <span class="text-slate-500"><?= number_format($model['usage_count']) ?></span>
              </div>
              <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-[#23AAC5] to-[#115c6c] rounded-full" style="width: <?= $percent ?>%"></div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Gestos más usados -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <i class="iconoir-flash text-rose-500"></i>
          Gestos más usados
        </h2>
        <?php if (empty($gestureStats)): ?>
          <p class="text-slate-500 text-sm">Sin ejecuciones de gestos aún</p>
        <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Gesto</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">Usos</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">Usuarios</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($gestureStats as $g): ?>
              <tr>
                <td class="px-4 py-2 text-slate-700"><?= htmlspecialchars($g['gesture_type']) ?></td>
                <td class="px-4 py-2 text-right text-slate-600"><?= number_format($g['usage_count']) ?></td>
                <td class="px-4 py-2 text-right text-slate-600"><?= number_format($g['unique_users']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- Voces más usadas -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
          <i class="iconoir-microphone text-indigo-500"></i>
          Voces más usadas
        </h2>
        <?php if (empty($voiceStats)): ?>
          <p class="text-slate-500 text-sm">Sin ejecuciones de voces aún</p>
        <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Voz</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">Usos</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">Usuarios</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($voiceStats as $v): ?>
              <tr>
                <td class="px-4 py-2 text-slate-700 capitalize"><?= htmlspecialchars($v['voice_id']) ?></td>
                <td class="px-4 py-2 text-right text-slate-600"><?= number_format($v['usage_count']) ?></td>
                <td class="px-4 py-2 text-right text-slate-600"><?= number_format($v['unique_users']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tabla de uso por usuario -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
      <div class="p-6 border-b border-slate-200">
        <h2 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
          <i class="iconoir-user text-[#23AAC5]"></i>
          Uso por Usuario
        </h2>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Usuario</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Email</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Conversaciones</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Mensajes</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Imágenes</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Gestos</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Voces</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Último acceso</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <?php foreach ($userStats as $u): ?>
            <tr class="hover:bg-slate-50 transition-colors">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="h-9 w-9 rounded-full bg-gradient-to-br from-[#23AAC5] to-[#115c6c] flex items-center justify-center text-white font-semibold text-sm">
                    <?= strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1)) ?>
                  </div>
                  <span class="font-medium text-slate-800"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></span>
                </div>
              </td>
              <td class="px-6 py-4 text-sm text-slate-600"><?= htmlspecialchars($u['email']) ?></td>
              <td class="px-6 py-4 text-sm text-slate-800 text-right font-medium"><?= number_format($u['conversations']) ?></td>
              <td class="px-6 py-4 text-sm text-slate-800 text-right font-medium"><?= number_format($u['messages']) ?></td>
              <td class="px-6 py-4 text-sm text-slate-800 text-right font-medium"><?= number_format($u['images']) ?></td>
              <td class="px-6 py-4 text-sm text-slate-800 text-right font-medium"><?= number_format($u['gestures']) ?></td>
              <td class="px-6 py-4 text-sm text-slate-800 text-right font-medium"><?= number_format($u['voices']) ?></td>
              <td class="px-6 py-4 text-sm text-slate-600">
                <?php if ($u['last_login_at']): ?>
                  <?= date('d/m/Y H:i', strtotime($u['last_login_at'])) ?>
                <?php else: ?>
                  <span class="text-slate-400">Nunca</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>
</main>
</div>

  <script>
    // Gráfico de actividad
    const ctx = document.getElementById('activityChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
          label: 'Mensajes',
          data: <?= json_encode($chartData) ?>,
          borderColor: '#23AAC5',
          backgroundColor: 'rgba(35, 170, 197, 0.1)',
          fill: true,
          tension: 0.4,
          pointRadius: 3,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0 }
          }
        }
      }
    });
  </script>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
</body>
</html>
