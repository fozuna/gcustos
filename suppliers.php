<?php
require_once __DIR__ . '/init.php';
require_auth();

$message = null; $error = null; $editSupplier = null;

// Carregar fornecedor para edição
if (isset($_GET['edit'])) {
    $sid = (int)$_GET['edit'];
    $s = Supplier::findById($sid);
    if ($s) { $editSupplier = $s; } else { $error = 'Fornecedor não encontrado.'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $delId = (int)$_POST['delete_id'];
        if (Supplier::delete($delId)) { $message = 'Fornecedor excluído com sucesso!'; }
        else { $error = 'Não foi possível excluir: existem custos vinculados.'; }
    } else {
        $name = trim($_POST['name'] ?? '');
        $contact_name = trim($_POST['contact_name'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $action = $_POST['action'] ?? 'create';
        if ($name !== '') {
            if ($action === 'update' && isset($_POST['supplier_id'])) {
                $sid = (int)$_POST['supplier_id'];
                if (Supplier::update($sid, $name, $contact_name ?: null, $city ?: null, $phone ?: null)) { $message = 'Fornecedor atualizado com sucesso!'; $editSupplier = null; }
                else { $error = 'Falha ao atualizar fornecedor.'; }
            } else {
                if (Supplier::create($name, $contact_name ?: null, $city ?: null, $phone ?: null)) { $message = 'Fornecedor criado com sucesso!'; }
                else { $error = 'Falha ao criar fornecedor.'; }
            }
        } else { $error = 'Informe o nome do fornecedor.'; }
    }
}

$suppliers = Supplier::all();

ob_start();
?>
<div class="flex flex-col gap-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-brand-900">Fornecedores</h1>
    <div class="flex items-center gap-2">
      <a href="import_suppliers.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-brand-600 text-white hover:bg-brand-700">
        <i data-lucide="file-input" class="w-5 h-5"></i> Importar
      </a>
      <a href="dashboard.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">
        <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Voltar ao dashboard
      </a>
    </div>
  </div>

  <?php if ($message): ?><div class="p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"><?= h($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= h($error) ?></div><?php endif; ?>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?php if ($editSupplier): ?>
        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="supplier_id" value="<?= (int)$editSupplier['id'] ?>" />
      <?php else: ?>
        <input type="hidden" name="action" value="create" />
      <?php endif; ?>
      <div>
        <label class="block text-sm font-medium text-brand-800">Nome do fornecedor</label>
        <input type="text" name="name" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Ex.: ABC Materiais" value="<?= h($editSupplier['name'] ?? '') ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Nome do contato</label>
        <input type="text" name="contact_name" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Ex.: João Silva" value="<?= h($editSupplier['contact_name'] ?? '') ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Cidade</label>
        <input type="text" name="city" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Ex.: São Paulo" value="<?= h($editSupplier['city'] ?? '') ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Telefone</label>
        <input type="text" name="phone" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="(11) 99999-9999" value="<?= h($editSupplier['phone'] ?? '') ?>" />
      </div>
      <div class="md:col-span-2 flex items-center justify-end">
        <?php if ($editSupplier): ?>
          <a href="suppliers.php" class="px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Cancelar edição</a>
          <button type="submit" class="ml-2 px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Atualizar fornecedor</button>
        <?php else: ?>
          <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Cadastrar fornecedor</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <h2 class="text-brand-800 font-medium mb-3">Lista de fornecedores</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-brand-700">
            <th class="py-2 pr-4">Nome</th>
            <th class="py-2 pr-4">Contato</th>
            <th class="py-2 pr-4">Cidade</th>
            <th class="py-2 pr-4">Telefone</th>
            <th class="py-2 pr-4">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($suppliers as $s): ?>
            <tr class="border-t">
              <td class="py-2 pr-4 text-brand-900"><?= h($s['name']) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($s['contact_name']) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($s['city']) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($s['phone']) ?></td>
              <td class="py-2 pr-4">
                <div class="flex items-center gap-2">
                  <a href="suppliers.php?edit=<?= (int)$s['id'] ?>" class="px-2 py-1 rounded-md border border-brand-300 text-brand-800 hover:bg-brand-50">Editar</a>
                  <form method="post" onsubmit="return confirm('Confirma excluir este fornecedor?');">
                    <input type="hidden" name="delete_id" value="<?= (int)$s['id'] ?>" />
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
render_layout('Fornecedores', $content);
?>