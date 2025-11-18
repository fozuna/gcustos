<?php
require_once __DIR__ . '/init.php';
require_auth();

$message = null; $error = null; $editReceipt = null;
$userId = (int)($_SESSION['user_id'] ?? 0);
$clients = Client::all();
$centers = CostCenter::all();

$filterCenterId = isset($_GET['center']) && (int)$_GET['center'] > 0 ? (int)$_GET['center'] : null;

if (isset($_GET['edit'])) {
    $rid = (int)$_GET['edit'];
    $r = Receipt::findById($rid);
    if ($r) {
        if ((int)$r['user_id'] === $userId) { $editReceipt = $r; }
        else { $error = 'Você só pode editar seus próprios recebimentos.'; }
    } else { $error = 'Recebimento não encontrado.'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $delId = (int)$_POST['delete_id'];
        $existing = Receipt::findById($delId);
        if (!$existing || (int)$existing['user_id'] !== $userId) { $error = 'Ação não permitida.'; }
        else { if (Receipt::delete($delId)) { $message = 'Recebimento excluído com sucesso!'; } else { $error = 'Falha ao excluir recebimento.'; } }
    } else {
        $client_id = (int)($_POST['client_id'] ?? 0);
        $centerIdRaw = $_POST['center_id'] ?? '';
        $center_id = ($centerIdRaw !== '' && (int)$centerIdRaw > 0) ? (int)$centerIdRaw : null;
        $date = trim($_POST['receipt_date'] ?? '');
        $amountRaw = trim($_POST['amount'] ?? '0');
        $amt = str_replace(['R$', 'r$', ' '], ['', '', ''], $amountRaw);
        $amt = str_replace('.', '', $amt);
        $amt = str_replace(',', '.', $amt);
        $amount = ($amt !== '' && is_numeric($amt)) ? (float)$amt : 0.0;
        $notes = trim($_POST['notes'] ?? '');

        if ($client_id > 0 && $date !== '' && $amount > 0) {
            if (isset($_POST['receipt_id'])) {
                $rid = (int)$_POST['receipt_id'];
                $r = Receipt::findById($rid);
                if ($r && (int)$r['user_id'] === $userId) {
                    if (Receipt::update($rid, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento atualizado com sucesso!'; $editReceipt = null; }
                    else { $error = 'Falha ao atualizar recebimento.'; }
                } else { $error = 'Você só pode editar seus próprios recebimentos.'; }
            } else {
                if (Receipt::create($userId, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento lançado com sucesso!'; }
                else { $error = 'Falha ao lançar recebimento.'; }
            }
        } else { $error = 'Preencha todos os campos obrigatórios corretamente.'; }
    }
}

$recent = Receipt::recentForUser($userId, 10);
if ($filterCenterId) {
    $pdo = Database::connection();
    $stmt = $pdo->prepare('SELECT r.id, r.receipt_date, r.amount, r.notes, c.name AS client_name FROM receipts r JOIN clients c ON c.id = r.client_id WHERE r.user_id = ? AND r.cost_center_id = ? ORDER BY r.receipt_date DESC, r.id DESC LIMIT 50');
    $stmt->execute([$userId, $filterCenterId]);
    $recent = $stmt->fetchAll();
}

ob_start();
?>
<div class="flex flex-col gap-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-brand-900">Lançar receita</h1>
    <a href="dashboard.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">
      <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Voltar ao dashboard
    </a>
  </div>

  <?php if ($message): ?><div class="p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"><?= h($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= h($error) ?></div><?php endif; ?>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?php if ($editReceipt): ?>
        <input type="hidden" name="receipt_id" value="<?= (int)$editReceipt['id'] ?>" />
      <?php endif; ?>
      <div>
        <label class="block text-sm font-medium text-brand-800">Data</label>
        <input type="date" name="receipt_date" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 px-3 py-2" value="<?= h($editReceipt['receipt_date'] ?? date('Y-m-d')) ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Cliente</label>
        <select name="client_id" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 px-3 py-2">
          <option value="">Selecione...</option>
          <?php foreach ($clients as $c): $sel = ($editReceipt && (int)$editReceipt['client_id'] === (int)$c['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$c['id'] ?>" <?= $sel ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center_id" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 px-3 py-2">
          <option value="">(opcional)</option>
          <?php foreach ($centers as $cc): $selc = ($editReceipt && isset($editReceipt['cost_center_id']) && (int)$editReceipt['cost_center_id'] === (int)$cc['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $selc ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Valor</label>
        <input type="text" name="amount" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 px-3 py-2" placeholder="Ex.: 1000,00" value="<?= h(isset($editReceipt['amount']) ? number_format((float)$editReceipt['amount'], 2, ',', '.') : '') ?>" />
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-brand-800">Observações</label>
        <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 px-3 py-2" placeholder="Origem do recebimento"><?= h($editReceipt['notes'] ?? '') ?></textarea>
      </div>
      <div class="md:col-span-2 flex items-center justify-end gap-3">
        <?php if ($editReceipt): ?>
          <a href="receipts.php" class="px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Cancelar edição</a>
          <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Atualizar</button>
        <?php else: ?>
          <button type="reset" class="px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Limpar</button>
          <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Salvar recebimento</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <div class="flex items-center justify-between mb-2">
      <h2 class="text-brand-800 font-medium">Seus últimos lançamentos</h2>
      <form method="get" class="flex items-center gap-2">
        <label class="text-sm text-brand-700">Centro:</label>
        <select name="center" class="rounded-md border border-brand-300 bg-brand-50 text-brand-900 px-2 py-1">
          <option value="">Todos</option>
          <?php foreach ($centers as $cc): ?>
            <option value="<?= (int)$cc['id'] ?>" <?= ($filterCenterId === (int)$cc['id']) ? 'selected' : '' ?>><?= h($cc['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="px-3 py-1 rounded-md bg-brand-700 text-white">Filtrar</button>
      </form>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-brand-700">
            <th class="py-2 pr-4">Data</th>
            <th class="py-2 pr-4">Cliente</th>
            <th class="py-2 pr-4">Centro</th>
            <th class="py-2 pr-4">Valor</th>
            <th class="py-2 pr-4">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
            <tr class="border-t">
              <td class="py-2 pr-4 text-brand-900"><?= h(date('d/m/Y', strtotime($r['receipt_date']))) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($r['client_name'] ?? '') ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($r['cost_center_id'] ?? '') ?></td>
              <td class="py-2 pr-4 font-medium text-brand-900"><?= h(format_currency((float)$r['amount'])) ?></td>
              <td class="py-2 pr-4">
                <a href="receipts.php?edit=<?= (int)$r['id'] ?>" class="px-3 py-1 rounded-md border border-brand-300 text-brand-800">Editar</a>
                <form method="post" style="display:inline-block" onsubmit="return confirm('Excluir este recebimento?');">
                  <input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>" />
                  <button type="submit" class="ml-2 px-3 py-1 rounded-md bg-red-600 text-white">Excluir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>lucide.createIcons();</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
render_layout('Receitas', $content);
?>