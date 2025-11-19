<?php
require_once __DIR__ . '/init.php';
require_auth();

// Filtros
$rawMonth = $_GET['month'] ?? null;
$isAllMonths = ($rawMonth === 'all');
$month = $isAllMonths ? (int)date('n') : (isset($rawMonth) ? max(1, min(12, (int)$rawMonth)) : (int)date('n'));
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$groupId = isset($_GET['group_id']) && $_GET['group_id'] !== '' ? (int)$_GET['group_id'] : null;

$groups = CostGroup::all();
$monthNames = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
// Usuário atual (para receitas)
$userId = (int)($_SESSION['user_id'] ?? 0);

// Dados conforme filtros
if ($isAllMonths) {
    if ($groupId) {
        $groupTotal = Cost::groupTotalForYear($groupId, $year);
        $summary = [$groupTotal];
    } else {
        $summary = Cost::summaryByGroupForYear($year);
    }
    $daily = []; // não usado em modo anual
    $recent = Cost::recentForMonthGroup($month, $year, $groupId, 10); // mantém últimos lançamentos recentes
} else {
    if ($groupId) {
        $groupTotal = Cost::groupTotalForMonth($groupId, $month, $year);
        $summary = [$groupTotal];
        $daily = Cost::dailySummaryForMonth($month, $year, $groupId);
        $recent = Cost::recentForMonthGroup($month, $year, $groupId, 10);
    } else {
        $summary = Cost::summaryByGroupForMonth($month, $year);
        $daily = Cost::dailySummaryForMonth($month, $year);
        $recent = Cost::recentForMonthGroup($month, $year, null, 10);
    }
}

$annual = Cost::monthlyTotalsForYear($year, $groupId);
$totalValue = $isAllMonths ? Cost::totalForYear($year, $groupId) : Cost::totalForMonth($month, $year, $groupId);

// Total de receitas para o mesmo período (por usuário atual)
$pdo = Database::connection();
if ($isAllMonths) {
    $stmtR = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM receipts WHERE user_id = ? AND YEAR(receipt_date) = ?');
    $stmtR->execute([$userId, $year]);
    $receiptsTotal = (float)$stmtR->fetchColumn();
} else {
    $stmtR = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM receipts WHERE user_id = ? AND MONTH(receipt_date) = ? AND YEAR(receipt_date) = ?');
    $stmtR->execute([$userId, $month, $year]);
    $receiptsTotal = (float)$stmtR->fetchColumn();
}
$saldo = $receiptsTotal - (float)$totalValue;

ob_start();
?>
<div class="flex flex-col gap-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-brand-900">Resumo Financeiro</h1>
    <form method="get" class="flex items-end gap-3">
      <div>
        <label class="block text-xs font-medium text-brand-700">Mês</label>
        <select name="month" class="mt-1 rounded-md border border-brand-300 bg-white text-brand-900 px-2 py-1 focus:border-brand-500 focus:ring-brand-500">
          <?php for ($m=1;$m<=12;$m++): $sel = $m===$month?'selected':''; ?>
            <option value="<?= $m ?>" <?= $sel ?>><?= $monthNames[$m-1] ?></option>
          <?php endfor; ?>
          <option value="all" <?= $isAllMonths ? 'selected' : '' ?>>Todos os meses</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-brand-700">Ano</label>
        <input type="number" name="year" value="<?= h($year) ?>" class="mt-1 w-24 rounded-md border border-brand-300 bg-white text-brand-900 px-2 py-1 focus:border-brand-500 focus:ring-brand-500" />
      </div>
      <div>
        <label class="block text-xs font-medium text-brand-700">Grupo</label>
        <select name="group_id" class="mt-1 rounded-md border border-brand-300 bg-white text-brand-900 px-2 py-1 focus:border-brand-500 focus:ring-brand-500">
          <option value="">Todos</option>
          <?php foreach ($groups as $g): $sel = ($groupId && $groupId===(int)$g['id'])?'selected':''; ?>
            <option value="<?= (int)$g['id'] ?>" <?= $sel ?>><?= h($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="h-9 px-3 rounded-md bg-brand-700 text-white hover:bg-brand-800">Filtrar</button>
    </form>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
    <!-- Totalizador de Custos -->
    <div class="bg-white rounded-lg shadow border border-brand-100 p-4">
      <h2 class="text-brand-800 font-medium mb-3">Custos (<?= $isAllMonths ? 'Ano' : 'Mês' ?>)</h2>
      <div class="space-y-1">
        <?php if (!empty($summary)): ?>
          <?php foreach ($summary as $row): ?>
            <div class="flex items-center justify-between py-1">
              <span class="text-brand-800 capitalize"><?= h($row['group_name']) ?></span>
              <span class="text-brand-900 font-medium"><?= h(format_currency((float)($row['total'] ?? 0))) ?></span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-sm text-brand-700">Sem dados para o período selecionado.</div>
        <?php endif; ?>
      </div>
      <div class="mt-3 pt-3 border-t border-brand-100 flex items-center justify-between">
        <span class="text-brand-700">Total de custos (<?= $isAllMonths ? 'Ano' : 'Mês' ?>)</span>
        <span class="text-2xl font-semibold text-brand-900"><?= h(format_currency($totalValue)) ?></span>
      </div>
      <div class="text-sm text-brand-700 mt-1"><?= $groupId ? 'Grupo selecionado' : 'Todos os grupos' ?></div>
    </div>

    <!-- Totalizador de Receitas -->
    <div class="bg-white rounded-lg shadow border border-brand-100 p-4">
      <h2 class="text-brand-800 font-medium mb-3">Receitas (<?= $isAllMonths ? 'Ano' : 'Mês' ?>)</h2>
      <div class="space-y-2">
        <div class="flex items-center justify-between py-1">
          <span class="text-brand-800">Total de receitas</span>
          <span class="text-brand-900 font-semibold"><?= h(format_currency($receiptsTotal)) ?></span>
        </div>
        <div class="flex items-center justify-between py-1">
          <span class="text-brand-800">Saldo (Receitas − Custos)</span>
          <span class="font-semibold" style="color: <?= $saldo >= 0 ? '#166534' : '#991b1b' ?>; "><?= h(format_currency($saldo)) ?></span>
        </div>
      </div>
    </div>

    <!-- Comparativo Receitas x Custos -->
    <div class="bg-white rounded-lg shadow border border-brand-100 p-4">
      <h2 class="text-brand-800 font-medium mb-2">Receitas x Custos (<?= $isAllMonths ? 'Ano' : 'Mês' ?>)</h2>
      <div style="height:240px">
        <canvas id="rcCompareChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Gráfico diário/mensal abaixo -->
  <div class="col-span-1 xl:col-span-3 bg-white rounded-lg shadow border border-brand-100 p-4">
    <h2 class="text-brand-800 font-medium mb-2"><?= $isAllMonths ? 'Totais mensais (ano selecionado)' : 'Gastos diários (mês selecionado)' ?></h2>
    <div style="height:300px">
      <canvas id="dailyChart"></canvas>
    </div>
  </div>

  <div class="bg-white rounded-lg shadow border border-brand-100 p-4">
    <h2 class="text-brand-800 font-medium mb-2">Posição anual (<?= $groupId ? 'Grupo selecionado' : 'Todos os grupos' ?>)</h2>
    <div style="height:300px">
      <canvas id="annualChart"></canvas>
    </div>
  </div>

  <div class="bg-white rounded-lg shadow border border-brand-100 p-4">
    <h2 class="text-brand-800 font-medium mb-3">Últimos lançamentos</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-brand-700">
            <th class="py-2 pr-4">Data</th>
            <th class="py-2 pr-4">Grupo</th>
            <th class="py-2 pr-4">Descrição</th>
            <th class="py-2 pr-4">Valor</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
            <tr class="border-t">
              <td class="py-2 pr-4 text-brand-900"><?= h(date('d/m/Y', strtotime($r['cost_date']))) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($r['group_name']) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($r['description']) ?></td>
              <td class="py-2 pr-4 font-medium text-brand-900"><?= h(format_currency($r['amount'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  const summaryData = <?= json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const labels = summaryData.map(s => s.group_name);
  const values = summaryData.map(s => Number(s.total || 0));
  function renderChart(id, type, data, options) {
    if (typeof Chart === 'undefined') return;
    const el = document.getElementById(id);
    if (!el) return;
    const ctx = el.getContext('2d');
    return new Chart(ctx, { type, data, options });
  }

  renderChart('groupChart', 'bar', {
    labels,
    datasets: [{ label: 'R$ por grupo', data: values, backgroundColor: labels.map(() => '#3b82f6'), borderRadius: 4 }]
  }, { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } });

  const isAllMonths = <?= json_encode($isAllMonths) ?>;
  const hasDaily = document.getElementById('dailyChart');
  if (!isAllMonths) {
    const dailyData = <?= json_encode($daily, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const dayLabels = dailyData.map(d => new Date(d.day).toLocaleDateString('pt-BR'));
    const dayValues = dailyData.map(d => Number(d.total || 0));
    if (hasDaily) renderChart('dailyChart', 'line', { labels: dayLabels, datasets: [{ label: 'R$ diário', data: dayValues, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.15)', tension: 0.3 }] }, { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } });
  } else {
    const annualDataForDaily = <?= json_encode($annual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const monthLabelsDaily = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    const monthValuesDaily = annualDataForDaily.map(d => Number(d.total || 0));
    if (hasDaily) renderChart('dailyChart', 'line', { labels: monthLabelsDaily, datasets: [{ label: 'R$ mensal', data: monthValuesDaily, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.15)', tension: 0.3 }] }, { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } });
  }

  const annualData = <?= json_encode($annual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const monthLabels = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
  const monthValues = annualData.map(d => Number(d.total || 0));
  renderChart('annualChart', 'bar', { labels: monthLabels, datasets: [{ label: 'R$ por mês', data: monthValues, backgroundColor: '#60a5fa', borderRadius: 4 }] }, { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } });

  // Comparativo Receitas x Custos
  const receiptsTotal = Number(<?= json_encode($receiptsTotal) ?>);
  const costsTotal = Number(<?= json_encode($totalValue) ?>);
  renderChart('rcCompareChart', 'bar', { labels: ['Receitas', 'Custos'], datasets: [{ data: [receiptsTotal, costsTotal], backgroundColor: ['#2563eb','#ef4444'], borderRadius: 6 }] }, { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } });
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
render_layout('Dashboard', $content);
?>