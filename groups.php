<?php
require_once __DIR__ . '/init.php';
require_auth();

$message = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name) {
        if (CostGroup::create($name)) $message = 'Grupo criado com sucesso!';
        else $error = 'Falha ao criar grupo (talvez já exista).';
    } else {
        $error = 'Informe um nome de grupo.';
    }
}

$groups = CostGroup::all();

ob_start();
?>
<div class="flex flex-col gap-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-brand-900">Grupos de custos</h1>
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
    <form method="post" class="flex items-end gap-3">
      <div class="flex-1">
        <label class="block text-sm font-medium text-brand-800">Nome do grupo</label>
        <input type="text" name="name" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Ex.: materiais" />
      </div>
      <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Adicionar</button>
    </form>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <h2 class="text-brand-800 font-medium mb-3">Lista de grupos</h2>
    <ul class="divide-y">
      <?php foreach ($groups as $g): ?>
        <li class="py-2 flex items-center justify-between">
          <span class="text-brand-900"><?= h($g['name']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
render_layout('Grupos', $content);
?>