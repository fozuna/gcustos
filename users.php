<?php
require_once __DIR__ . '/init.php';
// Permite criar o primeiro usuário sem estar logado
$firstRun = (User::countAll() === 0);
if (!$firstRun) { require_auth(); }

$message = null; $error = null; $editUser = null;

// Carregar usuário para edição (somente quando não for firstRun)
if (!$firstRun && isset($_GET['edit'])) {
    $uid = (int)$_GET['edit'];
    $u = User::findById($uid);
    if ($u) { $editUser = $u; }
    else { $error = 'Usuário não encontrado.'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Exclusão
    if (!$firstRun && isset($_POST['delete_id'])) {
        $delId = (int)$_POST['delete_id'];
        if ($delId === (int)($_SESSION['user_id'] ?? 0)) {
            $error = 'Você não pode excluir o próprio usuário logado.';
        } else {
            if (User::delete($delId)) { $message = 'Usuário excluído com sucesso!'; }
            else { $error = 'Falha ao excluir usuário.'; }
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $action = $_POST['action'] ?? 'create';
        if ($name && $email && ($action === 'create' ? ($password !== '') : true)) {
            if ($action === 'update' && !$firstRun && isset($_POST['user_id'])) {
                $uid = (int)$_POST['user_id'];
                if (User::update($uid, $name, $email, $password !== '' ? $password : null)) {
                    $message = 'Usuário atualizado com sucesso!';
                    $editUser = null;
                } else { $error = 'Falha ao atualizar usuário.'; }
            } else {
                if (User::create($name, $email, $password)) $message = 'Usuário criado com sucesso!';
                else $error = 'Falha ao criar usuário (e-mail pode já estar cadastrado).';
            }
        } else {
            $error = 'Preencha os campos obrigatórios.';
        }
    }
}

$users = User::all();

ob_start();
?>
<div class="flex flex-col gap-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-brand-900">Usuários</h1>
    <a href="dashboard.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">
      <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Voltar ao dashboard
    </a>
  </div>

  <?php if ($firstRun): ?>
    <div class="p-3 bg-brand-50 border border-brand-200 text-brand-800 rounded-lg text-sm">
      Primeiro acesso: crie o usuário inicial. Esta página está acessível sem login apenas enquanto não houver usuários cadastrados.
    </div>
  <?php endif; ?>

  <?php if ($message): ?>
    <div class="p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"><?= h($message) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <?php if ($editUser): ?>
        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>" />
      <?php else: ?>
        <input type="hidden" name="action" value="create" />
      <?php endif; ?>
      <div>
        <label class="block text-sm font-medium text-brand-800">Nome</label>
        <input type="text" name="name" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="Ex.: João da Silva" value="<?= h($editUser['name'] ?? '') ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">E-mail</label>
        <input type="email" name="email" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="seu@email.com" value="<?= h($editUser['email'] ?? '') ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Senha</label>
        <input type="password" name="password" <?= $editUser ? '' : 'required' ?> class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="<?= $editUser ? 'Deixe vazio para manter' : '••••••••' ?>" />
      </div>
      <div class="md:col-span-3 flex items-center justify-end">
        <?php if ($editUser): ?>
          <a href="users.php" class="px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Cancelar edição</a>
          <button type="submit" class="ml-2 px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Atualizar usuário</button>
        <?php else: ?>
          <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Criar usuário</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <h2 class="text-brand-800 font-medium mb-3">Lista de usuários</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-brand-700">
            <th class="py-2 pr-4">Nome</th>
            <th class="py-2 pr-4">E-mail</th>
            <th class="py-2 pr-4">Criado em</th>
            <?php if (!$firstRun): ?><th class="py-2 pr-4">Ações</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr class="border-t">
              <td class="py-2 pr-4 text-brand-900"><?= h($u['name']) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h($u['email']) ?></td>
              <td class="py-2 pr-4 text-brand-800"><?= h(date('d/m/Y H:i', strtotime($u['created_at']))) ?></td>
              <?php if (!$firstRun): ?>
              <td class="py-2 pr-4">
                <div class="flex items-center gap-2">
                  <a href="users.php?edit=<?= (int)$u['id'] ?>" class="px-2 py-1 rounded-md border border-brand-300 text-brand-800 hover:bg-brand-50">Editar</a>
                  <form method="post" onsubmit="return confirm('Confirma excluir este usuário?');">
                    <input type="hidden" name="delete_id" value="<?= (int)$u['id'] ?>" />
                    <button type="submit" class="px-2 py-1 rounded-md border border-red-300 text-red-700 hover:bg-red-50">Excluir</button>
                  </form>
                </div>
              </td>
              <?php endif; ?>
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
render_layout('Usuários', $content);
?>