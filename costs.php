<?php
require_once __DIR__ . '/init.php';
require_auth();

$groups = CostGroup::all();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['cost_date'] ?? '';
    $groupId = (int)($_POST['group_id'] ?? 0);
    $description = $_POST['description'] ?? '';
    $amount = (float)str_replace([',', 'R$', ' '], ['', '', ''], $_POST['amount'] ?? '0');

    if ($date && $groupId && $description && $amount > 0) {
        if (Cost::create($_SESSION['user_id'], $groupId, $date, $description, $amount)) {
            $message = 'Custo lançado com sucesso!';
        } else {
            $error = 'Falha ao lançar custo.';
        }
    } else {
        $error = 'Preencha todos os campos corretamente.';
    }
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

  <?php if ($message): ?>
    <div class="p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"><?= h($message) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-brand-800">Data</label>
        <input type="date" name="cost_date" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" value="<?= h(date('Y-m-d')) ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Grupo de custos</label>
        <select name="group_id" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">Selecione um grupo</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= (int)$g['id'] ?>"><?= h($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-brand-800">Descrição</label>
        <input type="text" name="description" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Ex.: Compra de materiais" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Valor</label>
        <input type="text" name="amount" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Ex.: 1234,56" />
      </div>
      <div class="md:col-span-2 flex items-center justify-end gap-3">
        <button type="reset" class="px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Limpar</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Salvar lançamento</button>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
render_layout('Lançar custo', $content);
?>