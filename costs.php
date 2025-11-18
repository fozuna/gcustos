<?php
require_once __DIR__ . '/init.php';
require_auth();

$message = null;
$error = null;
$editCost = null;

$userId = (int)($_SESSION['user_id'] ?? 0);
$groups = CostGroup::all();
$suppliers = Supplier::all();
$centers = CostCenter::all();

// Carregar item para edição
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $c = Cost::findById($id);
    if ($c) {
        if ((int)$c['user_id'] === $userId) { $editCost = $c; }
        else { $error = 'Você só pode editar seus próprios lançamentos.'; }
    } else {
        $error = 'Lançamento não encontrado.';
    }
}

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $delId = (int)$_POST['delete_id'];
        $c = Cost::findById($delId);
        if ($c && (int)$c['user_id'] === $userId) {
            if (Cost::delete($delId)) { $message = 'Lançamento excluído.'; }
            else { $error = 'Falha ao excluir lançamento.'; }
        } else { $error = 'Você só pode excluir seus próprios lançamentos.'; }
    } else {
        $action = $_POST['action'] ?? 'create';
        $date = trim($_POST['cost_date'] ?? '');
        $groupId = (int)($_POST['group_id'] ?? 0);
        $supplierIdRaw = $_POST['supplier_id'] ?? '';
        $supplierId = ($supplierIdRaw !== '' && (int)$supplierIdRaw > 0) ? (int)$supplierIdRaw : null;
        $centerIdRaw = $_POST['center_id'] ?? '';
        $centerId = ($centerIdRaw !== '' && (int)$centerIdRaw > 0) ? (int)$centerIdRaw : null;
        $description = trim($_POST['description'] ?? '');

        // Normalizar valor brasileiro: "R$ 1.234,56" -> 1234.56
        $amountRaw = trim($_POST['amount'] ?? '0');
        $amt = str_replace(['R$', 'r$', ' '], ['', '', ''], $amountRaw);
        $amt = str_replace('.', '', $amt);
        $amt = str_replace(',', '.', $amt);
        $amount = is_numeric($amt) ? (float)$amt : 0.0;

        if ($date && $groupId > 0 && $description !== '' && $amount > 0) {
            if ($action === 'update' && isset($_POST['cost_id'])) {
                $cid = (int)$_POST['cost_id'];
                $c = Cost::findById($cid);
                if ($c && (int)$c['user_id'] === $userId) {
                    if (Cost::update($cid, $userId, $groupId, $supplierId, $centerId, $date, $description, $amount)) {
                        $message = 'Lançamento atualizado com sucesso!';
                        $editCost = null;
                    } else { $error = 'Falha ao atualizar lançamento.'; }
                } else { $error = 'Você só pode editar seus próprios lançamentos.'; }
            } else {
                if (Cost::create($userId, $groupId, $supplierId, $centerId, $date, $description, $amount)) {
                    $message = 'Custo lançado com sucesso!';
                } else { $error = 'Falha ao lançar custo.'; }
            }
        } else {
            $error = 'Preencha todos os campos corretamente.';
        }
    }
}

// Filtros
$filterStart = $_GET['start'] ?? date('Y-m-01');
$filterEnd = $_GET['end'] ?? date('Y-m-t');
$filterSupplierId = isset($_GET['supplier']) && (int)$_GET['supplier'] > 0 ? (int)$_GET['supplier'] : null;
$filterCenterId = isset($_GET['center']) && (int)$_GET['center'] > 0 ? (int)$_GET['center'] : null;

$filteredRows = [];
$filteredTotal = 0.0;

if ($filterStart && $filterEnd) {
    $pdo = Database::connection();
    $sql = 'SELECT c.id, c.cost_date, c.description, c.amount,
                   g.name AS group_name, s.name AS supplier_name, cc.name AS center_name, cc.nickname AS center_nickname
            FROM costs c
            JOIN cost_groups g ON c.group_id = g.id
            LEFT JOIN suppliers s ON c.supplier_id = s.id
            LEFT JOIN cost_centers cc ON c.cost_center_id = cc.id
            WHERE c.user_id = ? AND c.cost_date BETWEEN ? AND ?';
    $params = [ $userId, $filterStart, $filterEnd ];
    if ($filterSupplierId) { $sql .= ' AND c.supplier_id = ?'; $params[] = $filterSupplierId; }
    if ($filterCenterId) { $sql .= ' AND c.cost_center_id = ?'; $params[] = $filterCenterId; }
    $sql .= ' ORDER BY c.cost_date DESC, c.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filteredRows = $stmt->fetchAll();
    foreach ($filteredRows as $fr) { $filteredTotal += (float)$fr['amount']; }
}

ob_start();
?>
<div class="flex flex-col gap-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-brand-900">Lançar custo</h1>
    <a href="dashboard.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">
      <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Voltar ao dashboard
    </a>
  </div>

  <?php if ($message): ?><div class="p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"><?= h($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= h($error) ?></div><?php endif; ?>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?php if ($editCost): ?>
        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="cost_id" value="<?= (int)$editCost['id'] ?>" />
      <?php else: ?>
        <input type="hidden" name="action" value="create" />
      <?php endif; ?>
      <div>
        <label class="block text-sm font-medium text-brand-800">Data</label>
        <input type="date" name="cost_date" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" value="<?= h($editCost ? $editCost['cost_date'] : date('Y-m-d')) ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Grupo de custos</label>
        <select name="group_id" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2 capitalize">
          <option value="">Selecione um grupo</option>
          <?php foreach ($groups as $g): $sel = ($editCost && (int)$editCost['group_id'] === (int)$g['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$g['id'] ?>" <?= $sel ?>><?= h($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Fornecedor</label>
        <select name="supplier_id" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">(opcional)</option>
          <?php foreach ($suppliers as $s): $sel = ($editCost && isset($editCost['supplier_id']) && (int)$editCost['supplier_id'] === (int)$s['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$s['id'] ?>" <?= $sel ?>><?= h($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="mt-1 text-xs"><a href="suppliers.php" class="text-brand-700 hover:underline">Gerenciar fornecedores</a></div>
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center_id" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">(opcional)</option>
          <?php foreach ($centers as $cc): $selc = ($editCost && isset($editCost['cost_center_id']) && (int)$editCost['cost_center_id'] === (int)$cc['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $selc ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-brand-800">Descrição</label>
        <input type="text" name="description" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Ex.: Compra de materiais" value="<?= h($editCost['description'] ?? '') ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Valor</label>
        <input type="text" name="amount" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Ex.: 1234,56" value="<?= h(isset($editCost['amount']) ? number_format((float)$editCost['amount'], 2, ',', '.') : '') ?>" />
      </div>
      <div class="md:col-span-2 flex items-center justify-end gap-3">
        <?php if ($editCost): ?>
          <a href="costs.php" class="px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Cancelar edição</a>
          <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Atualizar lançamento</button>
        <?php else: ?>
          <button type="reset" class="px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Limpar</button>
          <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Salvar lançamento</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <h2 class="text-brand-800 font-medium mb-4">Filtrar lançamentos por período, fornecedor e centro de custos</h2>
    <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-4">
      <div>
        <label class="block text-sm font-medium text-brand-800">Início</label>
        <input type="date" name="start" value="<?= h($filterStart) ?>" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Fim</label>
        <input type="date" name="end" value="<?= h($filterEnd) ?>" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Fornecedor</label>
        <select name="supplier" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">Todos</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= $filterSupplierId === (int)$s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">Todos</option>
          <?php foreach ($centers as $cc): ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $filterCenterId === (int)$cc['id'] ? 'selected' : '' ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex items-end justify-end gap-2">
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Aplicar</button>
        <a href="costs.php" class="px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Limpar</a>
      </div>
    </form>

    <?php if (!empty($filteredRows)): ?>
      <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="rounded-lg border border-brand-200 bg-brand-50 p-4">
          <div class="text-sm text-brand-700">Período</div>
          <div class="text-brand-900 font-semibold"><?= h(date('d/m/Y', strtotime($filterStart))) ?> — <?= h(date('d/m/Y', strtotime($filterEnd))) ?></div>
        </div>
        <div class="rounded-lg border border-brand-200 bg-brand-50 p-4">
          <div class="text-sm text-brand-700">Fornecedor</div>
          <div class="text-brand-900 font-semibold"><?= h($filterSupplierId ? (function($suppliers, $id){foreach($suppliers as $sx){if((int)$sx['id']===$id)return $sx['name'];} return 'Selecionado';})($suppliers,$filterSupplierId) : 'Todos') ?></div>
        </div>
        <div class="rounded-lg border border-brand-200 bg-brand-50 p-4">
          <div class="text-sm text-brand-700">Centro de custos</div>
          <div class="text-brand-900 font-semibold"><?= h($filterCenterId ? (function($centers, $id){foreach($centers as $cx){if((int)$cx['id']===$id)return $cx['name'];} return 'Selecionado';})($centers,$filterCenterId) : 'Todos') ?></div>
        </div>
        <div class="rounded-lg border border-brand-200 bg-brand-50 p-4">
          <div class="text-sm text-brand-700">Total do período</div>
          <div class="text-brand-900 font-semibold"><?= h(format_currency($filteredTotal)) ?></div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <h2 class="text-brand-800 font-medium mb-3"><?= !empty($filteredRows) ? 'Lançamentos filtrados' : 'Seus últimos lançamentos' ?></h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-brand-700">
            <th class="py-2 pr-4">Data</th>
            <th class="py-2 pr-4">Grupo</th>
            <th class="py-2 pr-4">Fornecedor</th>
            <th class="py-2 pr-4">Centro</th>
            <th class="py-2 pr-4">Descrição</th>
            <th class="py-2 pr-4">Valor</th>
            <th class="py-2 pr-4">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php $rows = !empty($filteredRows) ? $filteredRows : Cost::recentForUser($userId, 20); ?>
          <?php foreach ($rows as $r): ?>
            <tr class="border-t">
              <td class="py-2 pr-4 text-brand-900"><?= h(date('d/m/Y', strtotime($r['cost_date']))) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($r['group_name']) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($r['supplier_name'] ?? '') ?></td>
              <td class="py-2 pr-4 text-brand-800">
                <?= h(isset($r['center_name']) ? $r['center_name'] : '') ?>
                <?= isset($r['center_nickname']) && $r['center_nickname'] ? ' — ' . h($r['center_nickname']) : '' ?>
              </td>
              <td class="py-2 pr-4 text-brand-800"><?= h($r['description']) ?></td>
              <td class="py-2 pr-4 font-medium text-brand-900"><?= h(format_currency($r['amount'])) ?></td>
              <td class="py-2 pr-4">
                <div class="flex items-center gap-2">
                  <a href="costs.php?edit=<?= (int)$r['id'] ?>" class="px-2 py-1 rounded-md border border-brand-300 text-brand-800 hover:bg-brand-50">Editar</a>
                  <form method="post" onsubmit="return confirm('Confirma excluir este lançamento?');">
                    <input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>" />
                    <button type="submit" class="px-2 py-1 rounded-md border border-red-300 text-red-700 hover:bg-red-50">Excluir</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
render_layout('Lançar custo', $content);
?>