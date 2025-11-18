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

ob_start();
?>
<div class="flex flex-col gap-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-brand-900">Resumo de Custos</h1>
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

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
    <div class="bg-white rounded-xl shadow border border-brand-100 p-4">
      <h2 class="text-brand-800 font-medium mb-3">Totalizador (<?= $isAllMonths ? 'Ano' : 'Mês' ?>)</h2>
      <div class="space-y-1">
        <?php if (!empty($summary)): ?>
          <?php foreach ($summary as $row): ?>
            <div class="flex items-center justify-between py-1">
              <span class="text-brand-800"><?= h($row['group_name']) ?></span>
              <span class="text-brand-900 font-medium"><?= h(format_currency((float)($row['total'] ?? 0))) ?></span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-sm text-brand-700">Sem dados para o período selecionado.</div>
        <?php endif; ?>
      </div>
      <div class="mt-3 pt-3 border-t border-brand-100 flex items-center justify-between">
        <span class="text-brand-700">Total geral (<?= $isAllMonths ? 'Ano' : 'Mês' ?>)</span>
        <span class="text-2xl font-semibold text-brand-900"><?= h(format_currency($totalValue)) ?></span>
      </div>
      <div class="text-sm text-brand-700 mt-1"><?= $groupId ? 'Grupo selecionado' : 'Todos os grupos' ?></div>
    </div>
    <div class="col-span-1 xl:col-span-2 bg-white rounded-xl shadow border border-brand-100 p-4">
      <h2 class="text-brand-800 font-medium mb-2">Total por grupo (<?= $isAllMonths ? 'ano selecionado' : 'mês selecionado' ?>)</h2>
      <div class="aspect-[2/1]">
        <canvas id="groupChart"></canvas>
      </div>
    </div>
    <div class="col-span-1 xl:col-span-3 bg-white rounded-xl shadow border border-brand-100 p-4">
      <h2 class="text-brand-800 font-medium mb-2"><?= $isAllMonths ? 'Totais mensais (ano selecionado)' : 'Gastos diários (mês selecionado)' ?></h2>
      <div class="aspect-[2/1]">
        <canvas id="dailyChart"></canvas>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-4">
    <h2 class="text-brand-800 font-medium mb-2">Posição anual (<?= $groupId ? 'Grupo selecionado' : 'Todos os grupos' ?>)</h2>
    <div class="aspect-[2/1]">
      <canvas id="annualChart"></canvas>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-4">
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

  const ctxGroup = document.getElementById('groupChart').getContext('2d');
  new Chart(ctxGroup, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'R$ por grupo',
        data: values,
        backgroundColor: labels.map(() => '#3b82f6'),
        borderRadius: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (i) => `R$ ${i.parsed.y.toFixed(2)}` } }
      },
      scales: {
        y: { beginAtZero: true }
      }
    }
  });

  const isAllMonths = <?= json_encode($isAllMonths) ?>;
  const ctxDaily = document.getElementById('dailyChart').getContext('2d');
  if (!isAllMonths) {
    const dailyData = <?= json_encode($daily, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const dayLabels = dailyData.map(d => new Date(d.day).toLocaleDateString('pt-BR'));
    const dayValues = dailyData.map(d => Number(d.total || 0));
    new Chart(ctxDaily, {
      type: 'line',
      data: { labels: dayLabels, datasets: [{ label: 'R$ diário', data: dayValues, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.15)', tension: 0.3 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
  } else {
    const annualDataForDaily = <?= json_encode($annual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const monthLabelsDaily = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    const monthValuesDaily = annualDataForDaily.map(d => Number(d.total || 0));
    new Chart(ctxDaily, {
      type: 'line',
      data: { labels: monthLabelsDaily, datasets: [{ label: 'R$ mensal', data: monthValuesDaily, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.15)', tension: 0.3 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
  }

  const annualData = <?= json_encode($annual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const monthLabels = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
  const monthValues = annualData.map(d => Number(d.total || 0));
  const ctxAnnual = document.getElementById('annualChart').getContext('2d');
  new Chart(ctxAnnual, {
    type: 'bar',
    data: {
      labels: monthLabels,
      datasets: [{ label: 'R$ por mês', data: monthValues, backgroundColor: '#60a5fa', borderRadius: 4 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
render_layout('Dashboard', $content);
?>