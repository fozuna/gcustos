<?php
require_once __DIR__ . '/init.php';
require_auth();

$message = null; $error = null; $editReceipt = null;
$userId = (int)($_SESSION['user_id'] ?? 0);
$clients = Client::all();

// Carregar recebimento para edição
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
        if (!$existing || (int)$existing['user_id'] !== $userId) {
            $error = 'Ação não permitida.';
        } else {
            if (Receipt::delete($delId)) { $message = 'Recebimento excluído com sucesso!'; }
            else { $error = 'Falha ao excluir recebimento.'; }
        }
    } else {
        $client_id = (int)($_POST['client_id'] ?? 0);
        $date = $_POST['receipt_date'] ?? '';
        $amount = (float)str_replace([','], ['.'], $_POST['amount'] ?? '0');
        $notes = trim($_POST['notes'] ?? '');
        $action = $_POST['action'] ?? 'create';
        if ($client_id > 0 && $date !== '' && $amount > 0) {
            if ($action === 'update' && isset($_POST['receipt_id'])) {
                $rid = (int)$_POST['receipt_id'];
                $existing = Receipt::findById($rid);
                if (!$existing || (int)$existing['user_id'] !== $userId) { $error = 'Ação não permitida.'; }
                else {
                    if (Receipt::update($rid, $client_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento atualizado com sucesso!'; $editReceipt = null; }
                    else { $error = 'Falha ao atualizar recebimento.'; }
                }
            } else {
                if (Receipt::create($userId, $client_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento lançado com sucesso!'; }
                else { $error = 'Falha ao lançar recebimento.'; }
            }
        } else { $error = 'Selecione o cliente, informe a data e valor válido.'; }
    }
}

$recent = Receipt::recentForUser($userId, 10);

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
        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="receipt_id" value="<?= (int)$editReceipt['id'] ?>" />
      <?php else: ?>
        <input type="hidden" name="action" value="create" />
      <?php endif; ?>
      <div>
        <label class="block text-sm font-medium text-brand-800">Data</label>
        <input type="date" name="receipt_date" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" value="<?= h($editReceipt['receipt_date'] ?? date('Y-m-d')) ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Cliente</label>
        <select name="client_id" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">Selecione...</option>
          <?php foreach ($clients as $c): $sel = ($editReceipt && (int)$editReceipt['client_id'] === (int)$c['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$c['id'] ?>" <?= $sel ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Valor</label>
        <input type="text" name="amount" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Ex.: 1000,00" value="<?= h(isset($editReceipt['amount']) ? number_format((float)$editReceipt['amount'], 2, ',', '.') : '') ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Observações</label>
        <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Origem do recebimento"><?= h($editReceipt['notes'] ?? '') ?></textarea>
      </div>
      <div class="md:col-span-2 flex items-center justify-end">
        <?php if ($editReceipt): ?>
          <a href="receipts.php" class="px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Cancelar edição</a>
          <button type="submit" class="ml-2 px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Atualizar receita</button>
        <?php else: ?>
          <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Lançar receita</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <h2 class="text-brand-800 font-medium mb-3">Suas últimas receitas</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-brand-700">
            <th class="py-2 pr-4">Data</th>
            <th class="py-2 pr-4">Cliente</th>
            <th class="py-2 pr-4">Observações</th>
            <th class="py-2 pr-4">Valor</th>
            <th class="py-2 pr-4">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
            <tr class="border-t">
              <td class="py-2 pr-4 text-brand-900"><?= h(date('d/m/Y', strtotime($r['receipt_date']))) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($r['client_name']) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($r['notes']) ?></td>
              <td class="py-2 pr-4 font-medium text-brand-900"><?= h(format_currency($r['amount'])) ?></td>
              <td class="py-2 pr-4">
                <div class="flex items-center gap-2">
                  <a href="receipts.php?edit=<?= (int)$r['id'] ?>" class="px-2 py-1 rounded-md border border-brand-300 text-brand-800 hover:bg-brand-50">Editar</a>
                  <form method="post" onsubmit="return confirm('Confirma excluir esta receita?');">
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
render_layout('Receitas', $content);
?>