<?php
require_once __DIR__ . '/init.php';
require_auth();

$userId = (int)($_SESSION['user_id'] ?? 0);
$message = null; $error = null;

// Presets de período
$preset = $_GET['preset'] ?? 'this_month';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

// Define intervalo padrão com base no preset
function firstDayOfMonth($ts) { return date('Y-m-01', $ts); }
function lastDayOfMonth($ts) { return date('Y-m-t', $ts); }

$today = date('Y-m-d');
$nowTs = time();
switch ($preset) {
  case 'last_7':
    $startDate = date('Y-m-d', strtotime('-6 days'));
    $endDate = $today;
    break;
  case 'last_30':
    $startDate = date('Y-m-d', strtotime('-29 days'));
    $endDate = $today;
    break;
  case 'last_month':
    $lastMonthTs = strtotime('first day of last month', $nowTs);
    $startDate = firstDayOfMonth($lastMonthTs);
    $endDate = lastDayOfMonth($lastMonthTs);
    break;
  case 'this_year':
    $startDate = date('Y-01-01');
    $endDate = date('Y-12-31');
    break;
  case 'custom':
    $startDate = ($start && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) ? $start : $today;
    $endDate = ($end && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) ? $end : $today;
    break;
  default: // this_month
    $startDate = firstDayOfMonth($nowTs);
    $endDate = lastDayOfMonth($nowTs);
}

// Buscar resumos diários
$receiptsDaily = Receipt::dailySummaryForRangeUser($userId, $startDate, $endDate);
$costsDaily = Cost::dailySummaryForRangeUser($userId, $startDate, $endDate);

// Mapear por dia
$mapReceipts = []; foreach ($receiptsDaily as $r) { $mapReceipts[$r['day']] = (float)$r['total']; }
$mapCosts = []; foreach ($costsDaily as $c) { $mapCosts[$c['day']] = (float)$c['total']; }

// Construir lista de dias do intervalo
$days = [];
$cur = strtotime($startDate);
$endTs = strtotime($endDate);
while ($cur <= $endTs) { $days[] = date('Y-m-d', $cur); $cur = strtotime('+1 day', $cur); }

// Saldo inicial: acumulado antes do início do período
$openingReceipts = Receipt::totalUntilUser($userId, $startDate);
$openingCosts = Cost::totalUntilUser($userId, $startDate);
$openingBalance = $openingReceipts - $openingCosts;

$rows = [];
$totalReceipts = 0.0; $totalCosts = 0.0; $totalMovement = 0.0; $running = $openingBalance;
foreach ($days as $d) {
  $r = $mapReceipts[$d] ?? 0.0;
  $c = $mapCosts[$d] ?? 0.0;
  $movement = ($r - $c);
  $running += $movement; // saldo do dia (acumulado)
  $rows[] = [ 'day' => $d, 'receipts' => $r, 'costs' => $c, 'balance' => $running ];
  $totalReceipts += $r; $totalCosts += $c; $totalMovement += $movement;
}

// Exportação para Excel (CSV com BOM, delimitador ';')
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $filename = 'fluxo_caixa_' . str_replace('-', '', $startDate) . '_a_' . str_replace('-', '', $endDate) . '.csv';
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename=' . $filename);
  echo "\xEF\xBB\xBF"; // BOM para Excel/UTF-8
  $out = fopen('php://output', 'wb');
  // Cabeçalho
  fputcsv($out, ['Data', 'Receitas (+)', 'Custos (-)', 'Saldo do dia'], ';');
  foreach ($rows as $row) {
    fputcsv($out, [
      $row['day'],
      number_format($row['receipts'], 2, '.', ''),
      number_format($row['costs'], 2, '.', ''),
      number_format($row['balance'], 2, '.', ''),
    ], ';');
  }
  // Totais
  $finalBalance = $openingBalance + $totalMovement;
  fputcsv($out, ['TOTAL', number_format($totalReceipts, 2, '.', ''), number_format($totalCosts, 2, '.', ''), number_format($finalBalance, 2, '.', '')], ';');
  fclose($out);
  exit;
}

// URL de exportação preservando filtros
$exportParams = [ 'preset' => $preset, 'start' => $startDate, 'end' => $endDate, 'export' => 'csv' ];
$exportUrl = 'cashflow.php?' . http_build_query($exportParams);

ob_start();
?>
<div class="flex flex-col gap-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-brand-900">Fluxo de caixa</h1>
    <a href="dashboard.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">
      <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Voltar ao dashboard
    </a>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
      <div>
        <label class="block text-sm font-medium text-brand-800">Período</label>
        <select name="preset" class="mt-1 w-full rounded-lg border border-brand-300 bg-white text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <?php $p = $preset; ?>
          <option value="this_month" <?= $p==='this_month'?'selected':''; ?>>Mês atual</option>
          <option value="last_month" <?= $p==='last_month'?'selected':''; ?>>Mês anterior</option>
          <option value="last_7" <?= $p==='last_7'?'selected':''; ?>>Últimos 7 dias</option>
          <option value="last_30" <?= $p==='last_30'?'selected':''; ?>>Últimos 30 dias</option>
          <option value="this_year" <?= $p==='this_year'?'selected':''; ?>>Ano atual</option>
          <option value="custom" <?= $p==='custom'?'selected':''; ?>>Personalizado</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Início</label>
        <input type="date" name="start" value="<?= h($startDate) ?>" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Fim</label>
        <input type="date" name="end" value="<?= h($endDate) ?>" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" />
      </div>
      <div class="md:col-span-2 flex items-center gap-2 no-print">
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Aplicar</button>
        <a href="cashflow.php" class="px-4 py-2 rounded-lg border border-brand-300 text-brand-800">Limpar</a>
        <a href="<?= h($exportUrl) ?>" class="ml-auto inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
          <i data-lucide="file-down" class="w-5 h-5"></i>
          Exportar Excel
        </a>
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 text-white hover:bg-rose-700">
          <i data-lucide="file-text" class="w-5 h-5"></i>
          Exportar PDF
        </button>
      </div>
    </form>
    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
      <div class="p-3 rounded-lg bg-brand-50 border border-brand-200">
        <div class="text-sm text-brand-700">Receitas no período</div>
        <div class="text-lg font-semibold text-brand-900"><?= h(format_currency($totalReceipts)) ?></div>
      </div>
      <div class="p-3 rounded-lg bg-red-50 border border-red-200">
        <div class="text-sm text-red-700">Custos no período</div>
        <div class="text-lg font-semibold text-red-700">-<?= h(format_currency($totalCosts)) ?></div>
      </div>
      <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200">
        <div class="text-sm text-emerald-700">Saldo do período</div>
        <div class="text-lg font-semibold text-emerald-800"><?= h(format_currency($totalMovement)) ?></div>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <h2 class="text-brand-800 font-medium mb-1">Fluxo diário</h2>
    <p class="text-xs text-brand-600 mb-3">Saldo do dia considera o saldo anterior ao período e o movimento do próprio dia (acumulado).</p>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-brand-700">
            <th class="py-2 pr-4">Data</th>
            <th class="py-2 pr-4">Receitas (+)</th>
            <th class="py-2 pr-4">Custos (-)</th>
            <th class="py-2 pr-4">Saldo do dia (acumulado)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr class="border-t odd:bg-brand-50 hover:bg-brand-100/50">
              <td class="py-2 pr-4 text-brand-900"><?= h(date('d/m/Y', strtotime($row['day']))) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h(format_currency($row['receipts'])) ?></td>
              <td class="py-2 pr-4 text-red-700">-<?= h(format_currency($row['costs'])) ?></td>
              <td class="py-2 pr-4 font-medium <?= ($row['balance']>=0)?'text-emerald-800':'text-red-700' ?>"><?= h(format_currency($row['balance'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Gráficos -->
  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <h2 class="text-brand-800 font-medium mb-3">Gráficos do período</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <h3 class="text-sm text-brand-700 mb-2">Saldo diário</h3>
        <canvas id="balanceChart"></canvas>
      </div>
      <div>
        <h3 class="text-sm text-brand-700 mb-2">Receitas vs Custos</h3>
        <canvas id="rcChart"></canvas>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
render_layout('Fluxo de caixa', $content);
?>

<script>
  // Dados para gráficos
  const rows = <?= json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const labels = rows.map(r => new Date(r.day).toLocaleDateString('pt-BR'));
  const dataReceipts = rows.map(r => Number(r.receipts || 0));
  const dataCosts = rows.map(r => Number(r.costs || 0));
  const dataBalance = rows.map(r => Number(r.balance || 0));

  // Cores
  const colorReceipt = '#10b981'; // emerald 500
  const colorReceiptBg = 'rgba(16,185,129,0.15)';
  const colorCost = '#ef4444'; // red 500
  const colorCostBg = 'rgba(239,68,68,0.15)';
  const colorBalance = '#2563eb'; // brand 600
  const colorBalanceBg = 'rgba(37,99,235,0.15)';

  // Saldo diário (linha)
  const ctxBal = document.getElementById('balanceChart').getContext('2d');
  new Chart(ctxBal, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Saldo diário',
        data: dataBalance,
        borderColor: colorBalance,
        backgroundColor: colorBalanceBg,
        tension: 0.3,
        fill: true,
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: { ticks: { callback: v => 'R$ ' + Number(v).toLocaleString('pt-BR') } }
      },
      plugins: { legend: { display: true } }
    }
  });

  // Receitas vs Custos (barras)
  const ctxRc = document.getElementById('rcChart').getContext('2d');
  new Chart(ctxRc, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label: 'Receitas (+)', data: dataReceipts, backgroundColor: colorReceiptBg, borderColor: colorReceipt, borderWidth: 1 },
        { label: 'Custos (-)', data: dataCosts.map(v => -v), backgroundColor: colorCostBg, borderColor: colorCost, borderWidth: 1 }
      ]
    },
    options: {
      responsive: true,
      scales: {
        y: { ticks: { callback: v => 'R$ ' + Number(v).toLocaleString('pt-BR') } }
      },
      plugins: { legend: { display: true } }
    }
  });
</script>

<style>
  @media print {
    aside, nav, footer { display: none !important; }
    .ml-64 { margin-left: 0 !important; }
    .no-print { display: none !important; }
    body, .bg-brand-50 { background: #ffffff !important; }
    .shadow, .border { box-shadow: none !important; border: none !important; }
  }
</style>