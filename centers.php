<?php
require_once __DIR__ . '/init.php';
require_auth();

$message = null; $error = null; $editCenter = null;
$centers = CostCenter::all();

if (isset($_GET['edit'])) {
    $cid = (int)$_GET['edit'];
    $c = CostCenter::findById($cid);
    if ($c) { $editCenter = $c; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $delId = (int)$_POST['delete_id'];
        if (CostCenter::delete($delId)) { $message = 'Centro de custos excluído com sucesso!'; }
        else { $error = 'Não foi possível excluir. Verifique vínculos com custos ou receitas.'; }
        $centers = CostCenter::all();
        $editCenter = null;
    } else {
        $name = trim($_POST['name'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($name !== '') {
            if (isset($_POST['center_id'])) {
                $cid = (int)$_POST['center_id'];
                if (CostCenter::update($cid, $name, $nickname !== '' ? $nickname : null, $notes !== '' ? $notes : null)) {
                    $message = 'Centro de custos atualizado com sucesso!';
                    $editCenter = null;
                    $centers = CostCenter::all();
                } else { $error = 'Falha ao atualizar centro de custos.'; }
            } else {
                if (CostCenter::create($name, $nickname !== '' ? $nickname : null, $notes !== '' ? $notes : null)) {
                    $message = 'Centro de custos criado com sucesso!';
                    $centers = CostCenter::all();
                } else { $error = 'Falha ao criar centro de custos.'; }
            }
        } else { $error = 'Informe o nome do centro de custos.'; }
    }
}

ob_start();
?>
<div class="flex flex-col gap-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-brand-900">Centros de custos</h1>
    <a href="dashboard.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">
      <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Voltar ao dashboard
    </a>
  </div>

  <?php if ($message): ?><div class="p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"><?= h($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= h($error) ?></div><?php endif; ?>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <?php if ($editCenter): ?>
        <input type="hidden" name="center_id" value="<?= (int)$editCenter['id'] ?>" />
      <?php endif; ?>
      <div>
        <label class="block text-sm font-medium text-brand-800">Nome</label>
        <input type="text" name="name" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 px-3 py-2" value="<?= h($editCenter['name'] ?? '') ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Apelido</label>
        <input type="text" name="nickname" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 px-3 py-2" value="<?= h($editCenter['nickname'] ?? '') ?>" />
      </div>
      <div class="md:col-span-3">
        <label class="block text-sm font-medium text-brand-800">Observações</label>
        <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 px-3 py-2"><?= h($editCenter['notes'] ?? '') ?></textarea>
      </div>
      <div class="md:col-span-3">
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800"><?= $editCenter ? 'Salvar alterações' : 'Criar centro de custos' ?></button>
        <?php if ($editCenter): ?>
          <a href="centers.php" class="ml-2 px-4 py-2 rounded-lg border border-brand-300 text-brand-800">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <h2 class="text-brand-800 font-medium mb-3">Lista</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-brand-700">
            <th class="py-2 pr-4">Nome</th>
            <th class="py-2 pr-4">Apelido</th>
            <th class="py-2 pr-4">Observações</th>
            <th class="py-2 pr-4">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($centers as $cc): ?>
            <tr class="border-t">
              <td class="py-2 pr-4 text-brand-900"><?= h($cc['name']) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($cc['nickname'] ?? '') ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($cc['notes'] ?? '') ?></td>
              <td class="py-2 pr-4">
                <a href="centers.php?edit=<?= (int)$cc['id'] ?>" class="px-3 py-1 rounded-md border border-brand-300 text-brand-800">Editar</a>
                <form method="post" style="display:inline-block" onsubmit="return confirm('Excluir este centro?');">
                  <input type="hidden" name="delete_id" value="<?= (int)$cc['id'] ?>" />
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
render_layout('Centros de custos', $content);
?>