<?php
require_once __DIR__ . '/init.php';
require_auth();

$message = null; $error = null; $editCompany = null;

if (isset($_GET['edit'])) {
    $cid = (int)$_GET['edit'];
    $c = Company::findById($cid);
    if ($c) { $editCompany = $c; } else { $error = 'Empresa não encontrada.'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $delId = (int)$_POST['delete_id'];
        if (Company::delete($delId)) { $message = 'Empresa excluída com sucesso!'; }
        else { $error = 'Não foi possível excluir: existem centros de custos vinculados.'; }
    } else {
        $name = trim($_POST['name'] ?? '');
        $document = trim($_POST['document'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $action = $_POST['action'] ?? 'create';
        if ($name !== '') {
            if ($action === 'update' && isset($_POST['company_id'])) {
                $cid = (int)$_POST['company_id'];
                if (Company::update($cid, $name, $document ?: null, $city ?: null, $phone ?: null)) { $message = 'Empresa atualizada com sucesso!'; $editCompany = null; }
                else { $error = 'Falha ao atualizar empresa.'; }
            } else {
                if (Company::create($name, $document ?: null, $city ?: null, $phone ?: null)) { $message = 'Empresa criada com sucesso!'; }
                else { $error = 'Falha ao criar empresa.'; }
            }
        } else { $error = 'Informe o nome da empresa.'; }
    }
}

$companies = Company::all();

ob_start();
?>
<div class="flex flex-col gap-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-brand-900">Empresas</h1>
    <a href="dashboard.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">
      <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Voltar ao dashboard
    </a>
  </div>

  <?php if ($message): ?><div class="p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"><?= h($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= h($error) ?></div><?php endif; ?>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?php if ($editCompany): ?>
        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="company_id" value="<?= (int)$editCompany['id'] ?>" />
      <?php else: ?>
        <input type="hidden" name="action" value="create" />
      <?php endif; ?>
      <div>
        <label class="block text-sm font-medium text-brand-800">Nome da empresa</label>
        <input type="text" name="name" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Ex.: Minha Empresa Ltda" value="<?= h($editCompany['name'] ?? '') ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">CNPJ/Documento</label>
        <input type="text" name="document" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Ex.: 00.000.000/0001-00" value="<?= h($editCompany['document'] ?? '') ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Cidade</label>
        <input type="text" name="city" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Ex.: Curitiba" value="<?= h($editCompany['city'] ?? '') ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Telefone</label>
        <input type="text" name="phone" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="(41) 99999-9999" value="<?= h($editCompany['phone'] ?? '') ?>" />
      </div>
      <div class="md:col-span-2 flex items-center justify-end">
        <?php if ($editCompany): ?>
          <a href="companies.php" class="px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Cancelar edição</a>
          <button type="submit" class="ml-2 px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Atualizar empresa</button>
        <?php else: ?>
          <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Cadastrar empresa</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <h2 class="text-brand-800 font-medium mb-3">Lista de empresas</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-brand-700">
            <th class="py-2 pr-4">Nome</th>
            <th class="py-2 pr-4">Documento</th>
            <th class="py-2 pr-4">Cidade</th>
            <th class="py-2 pr-4">Telefone</th>
            <th class="py-2 pr-4">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($companies as $c): ?>
            <tr class="border-t">
              <td class="py-2 pr-4 text-brand-900"><?= h($c['name']) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($c['document']) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($c['city']) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($c['phone']) ?></td>
              <td class="py-2 pr-4">
                <div class="flex items-center gap-2">
                  <a href="companies.php?edit=<?= (int)$c['id'] ?>" class="px-2 py-1 rounded-md border border-brand-300 text-brand-800 hover:bg-brand-50">Editar</a>
                  <form method="post" onsubmit="return confirm('Confirma excluir esta empresa?');">
                    <input type="hidden" name="delete_id" value="<?= (int)$c['id'] ?>" />
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
render_layout('Empresas', $content);
?>