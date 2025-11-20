<?php
require_once __DIR__ . '/init.php';
require_auth();

$message = null; $error = null; $editGroup = null;
$companies = Company::all();

// Carregar grupo para edição
if (isset($_GET['edit'])) {
    $gid = (int)$_GET['edit'];
    $g = CostGroup::findById($gid);
    if ($g) { $editGroup = $g; } else { $error = 'Grupo não encontrado.'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Exclusão
    if (isset($_POST['delete_id'])) {
        $delId = (int)$_POST['delete_id'];
        if (CostGroup::delete($delId)) { $message = 'Grupo excluído com sucesso!'; }
        else { $error = 'Falha ao excluir grupo (há custos vinculados ou erro de banco).'; }
    } else {
        $name = trim($_POST['name'] ?? '');
        $companyIdRaw = $_POST['company_id'] ?? '';
        $companyId = ($companyIdRaw !== '' && (int)$companyIdRaw > 0) ? (int)$companyIdRaw : null;
        $action = $_POST['action'] ?? 'create';
        if ($name) {
            if ($action === 'update' && isset($_POST['group_id'])) {
                $gid = (int)$_POST['group_id'];
                if (CostGroup::update($gid, $name, $companyId)) { $message = 'Grupo atualizado com sucesso!'; $editGroup = null; }
                else { $error = 'Falha ao atualizar grupo.'; }
            } else {
                if (CostGroup::create($name, $companyId)) $message = 'Grupo criado com sucesso!';
                else $error = 'Falha ao criar grupo (talvez já exista).';
            }
        } else {
            $error = 'Informe um nome de grupo.';
        }
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
      <?php if ($editGroup): ?>
        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="group_id" value="<?= (int)$editGroup['id'] ?>" />
      <?php else: ?>
        <input type="hidden" name="action" value="create" />
      <?php endif; ?>
      <div class="flex-1">
        <label class="block text-sm font-medium text-brand-800">Nome do grupo</label>
        <input type="text" name="name" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Ex.: materiais" value="<?= h($editGroup['name'] ?? '') ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Empresa</label>
        <select name="company_id" class="mt-1 w-64 rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">Selecione</option>
          <?php foreach ($companies as $c): $sel = ($editGroup && isset($editGroup['company_id']) && (int)$editGroup['company_id'] === (int)$c['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$c['id'] ?>" <?= $sel ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($editGroup): ?>
        <a href="groups.php" class="px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Cancelar</a>
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Atualizar</button>
      <?php else: ?>
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Adicionar</button>
      <?php endif; ?>
    </form>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <h2 class="text-brand-800 font-medium mb-3">Lista de grupos</h2>
    <ul class="divide-y">
      <?php foreach ($groups as $g): ?>
        <li class="py-2 flex items-center justify-between">
          <span class="text-brand-900"><?= h($g['name']) ?></span>
          <span class="text-xs text-brand-700"><?= h($g['company_name'] ?? '') ?></span>
          <div class="flex items-center gap-2">
            <a href="groups.php?edit=<?= (int)$g['id'] ?>" class="px-2 py-1 rounded-md border border-brand-300 text-brand-800 hover:bg-brand-50">Editar</a>
            <form method="post" onsubmit="return confirm('Confirma excluir este grupo?');">
              <input type="hidden" name="delete_id" value="<?= (int)$g['id'] ?>" />
              <button type="submit" class="px-2 py-1 rounded-md border border-red-300 text-red-700 hover:bg-red-50">Excluir</button>
            </form>
          </div>
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