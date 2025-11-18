<?php
require_once __DIR__ . '/init.php';
require_auth();

$message = null; $error = null; $editReceipt = null;
$userId = (int)($_SESSION['user_id'] ?? 0);
$clients = Client::all();
$centers = CostCenter::all();// c:\xampp\htdocs\gcustos\gcustos\classes\CostCenter.php

class CostCenter {
    public static function all(): array {
        $pdo = Database::connection();
        return $pdo->query('SELECT id, name, nickname FROM cost_centers ORDER BY name')->fetchAll();
    }

    public static function findById(int $id): ?array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name, nickname, notes FROM cost_centers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, ?string $nickname, ?string $notes): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO cost_centers (name, nickname, notes) VALUES (?, ?, ?)');
        try { return $stmt->execute([trim($name), $nickname ?: null, $notes ?: null]); } catch (\PDOException $e) { return false; }
    }

    public static function update(int $id, string $name, ?string $nickname, ?string $notes): bool {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE cost_centers SET name = ?, nickname = ?, notes = ? WHERE id = ?');
        try { return $stmt->execute([trim($name), $nickname ?: null, $notes ?: null, $id]); } catch (\PDOException $e) { return false; }
    }

    public static function delete(int $id): bool {
        $pdo = Database::connection();
        $stmtC = $pdo->prepare('SELECT COUNT(*) FROM costs WHERE cost_center_id = ?');
        $stmtC->execute([$id]);
        if ((int)$stmtC->fetchColumn() > 0) { return false; }

        $stmtR = $pdo->prepare('SELECT COUNT(*) FROM receipts WHERE cost_center_id = ?');
        $stmtR->execute([$id]);
        if ((int)$stmtR->fetchColumn() > 0) { return false; }

        $stmt = $pdo->prepare('DELETE FROM cost_centers WHERE id = ?');
        try { return $stmt->execute([$id]); } catch (\PDOException $e) { return false; }
    }
}
?>
$filterCenterId = isset($_GET['center']) && (int)$_GET['center'] > 0 ? (int)$_GET['center'] : null;

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
            if (Receipt::delete($delId)) {
                $message = 'Recebimento excluído com sucesso!';
            } else {
                $error = 'Falha ao excluir recebimento.';
            }
        }
    } else {

        $client_id = (int)($_POST['client_id'] ?? 0);

        $centerIdRaw = $_POST['center_id'] ?? '';
        $center_id = ($centerIdRaw !== '' && (int)$centerIdRaw > 0)
            ? (int)$centerIdRaw
            : null;

        $date = trim($_POST['receipt_date'] ?? '');

        // Normalização do valor (igual ao do costs.php)
        $amountRaw = trim($_POST['amount'] ?? '0');

        // Remover R$, espaços e manter apenas números/virgulas/pontos
        $amt = str_replace(['R$', 'r$', ' '], ['', '', ''], $amountRaw);

        // Remover pontos de milhar e trocar vírgula decimal por ponto
        $amt = str_replace('.', '', $amt);
        $amt = str_replace(',', '.', $amt);

        // Garantir formato numérico
        if ($amt === '' || !is_numeric($amt)) {
            $amount = 0.0;
        } else {
            $amount = (float)$amt;
        }

        $notes = trim($_POST['notes'] ?? '');

        // Validação mínima
        if ($client_id > 0 && $date && $amount > 0) {

            if (isset($_POST['receipt_id'])) {
                // Atualização
                $rid = (int)$_POST['receipt_id'];
                $r = Receipt::findById($rid);

                if ($r && (int)$r['user_id'] === $userId) {
                    if (Receipt::update($rid, $client_id, $center_id, $date, $amount, $notes)) {
                        $message = 'Recebimento atualizado com sucesso!';
                        $editReceipt = null;
                    } else {
                        $error = 'Falha ao atualizar recebimento.';
                    }
                } else {
                    $error = 'Você só pode editar seus próprios recebimentos.';
                }
            } else {
                // Criação
                if (Receipt::create($userId, $client_id, $center_id, $date, $amount, $notes)) {
                    $message = 'Recebimento lançado com sucesso!';
                } else {
                    $error = 'Falha ao lançar recebimento.';
                }
            }

        } else {
            $error = 'Preencha todos os campos obrigatórios corretamente.';
        }
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
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center_id" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">(opcional)</option>
          <?php foreach ($centers as $cc): $selc = ($editReceipt && isset($editReceipt['cost_center_id']) && (int)$editReceipt['cost_center_id'] === (int)$cc['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $selc ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
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
    <form method="get" class="mb-4 flex items-end gap-3">
      <div>
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center" class="mt-1 w-64 rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">Todos</option>
          <?php foreach ($centers as $cc): ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $filterCenterId === (int)$cc['id'] ? 'selected' : '' ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Aplicar</button>
        <a href="receipts.php" class="ml-2 px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Limpar</a>
      </div>
    </form>
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
        $notes = trim($_POST['notes'] ?? '');
        $action = $_POST['action'] ?? 'create';
        if ($client_id > 0 && $date !== '' && $amount > 0) {
            if ($action === 'update' && isset($_POST['receipt_id'])) {
                $rid = (int)$_POST['receipt_id'];
                $existing = Receipt::findById($rid);
                if (!$existing || (int)$existing['user_id'] !== $userId) { $error = 'Ação não permitida.'; }
                else {
                    if (Receipt::update($rid, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento atualizado com sucesso!'; $editReceipt = null; }
                    else { $error = 'Falha ao atualizar recebimento.'; }
                }
            } else {
                if (Receipt::create($userId, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento lançado com sucesso!'; }
                else { $error = 'Falha ao lançar recebimento.'; }
            }
        } else { $error = 'Selecione o cliente, informe a data e valor válido.'; }
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
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center_id" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">(opcional)</option>
          <?php foreach ($centers as $cc): $selc = ($editReceipt && isset($editReceipt['cost_center_id']) && (int)$editReceipt['cost_center_id'] === (int)$cc['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $selc ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
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
    <form method="get" class="mb-4 flex items-end gap-3">
      <div>
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center" class="mt-1 w-64 rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">Todos</option>
          <?php foreach ($centers as $cc): ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $filterCenterId === (int)$cc['id'] ? 'selected' : '' ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Aplicar</button>
        <a href="receipts.php" class="ml-2 px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Limpar</a>
      </div>
    </form>
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
        $amount = (float)$amountNorm;
        $notes = trim($_POST['notes'] ?? '');
        $action = $_POST['action'] ?? 'create';
        if ($client_id > 0 && $date !== '' && $amount > 0) {
            if ($action === 'update' && isset($_POST['receipt_id'])) {
                $rid = (int)$_POST['receipt_id'];
                $existing = Receipt::findById($rid);
                if (!$existing || (int)$existing['user_id'] !== $userId) { $error = 'Ação não permitida.'; }
                else {
                    if (Receipt::update($rid, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento atualizado com sucesso!'; $editReceipt = null; }
                    else { $error = 'Falha ao atualizar recebimento.'; }
                }
            } else {
                if (Receipt::create($userId, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento lançado com sucesso!'; }
                else { $error = 'Falha ao lançar recebimento.'; }
            }
        } else { $error = 'Selecione o cliente, informe a data e valor válido.'; }
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
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center_id" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">(opcional)</option>
          <?php foreach ($centers as $cc): $selc = ($editReceipt && isset($editReceipt['cost_center_id']) && (int)$editReceipt['cost_center_id'] === (int)$cc['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $selc ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
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
    <form method="get" class="mb-4 flex items-end gap-3">
      <div>
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center" class="mt-1 w-64 rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">Todos</option>
          <?php foreach ($centers as $cc): ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $filterCenterId === (int)$cc['id'] ? 'selected' : '' ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Aplicar</button>
        <a href="receipts.php" class="ml-2 px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Limpar</a>
      </div>
    </form>
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
        $amount = (float)$amountNorm;
        $notes = trim($_POST['notes'] ?? '');
        $action = $_POST['action'] ?? 'create';
        if ($client_id > 0 && $date !== '' && $amount > 0) {
            if ($action === 'update' && isset($_POST['receipt_id'])) {
                $rid = (int)$_POST['receipt_id'];
                $existing = Receipt::findById($rid);
                if (!$existing || (int)$existing['user_id'] !== $userId) { $error = 'Ação não permitida.'; }
                else {
                    if (Receipt::update($rid, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento atualizado com sucesso!'; $editReceipt = null; }
                    else { $error = 'Falha ao atualizar recebimento.'; }
                }
            } else {
                if (Receipt::create($userId, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento lançado com sucesso!'; }
                else { $error = 'Falha ao lançar recebimento.'; }
            }
        } else { $error = 'Selecione o cliente, informe a data e valor válido.'; }
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
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center_id" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">(opcional)</option>
          <?php foreach ($centers as $cc): $selc = ($editReceipt && isset($editReceipt['cost_center_id']) && (int)$editReceipt['cost_center_id'] === (int)$cc['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $selc ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
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
    <form method="get" class="mb-4 flex items-end gap-3">
      <div>
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center" class="mt-1 w-64 rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">Todos</option>
          <?php foreach ($centers as $cc): ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $filterCenterId === (int)$cc['id'] ? 'selected' : '' ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Aplicar</button>
        <a href="receipts.php" class="ml-2 px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Limpar</a>
      </div>
    </form>
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
        $amount = (float)$amountNorm;
        $notes = trim($_POST['notes'] ?? '');
        $action = $_POST['action'] ?? 'create';
        if ($client_id > 0 && $date !== '' && $amount > 0) {
            if ($action === 'update' && isset($_POST['receipt_id'])) {
                $rid = (int)$_POST['receipt_id'];
                $existing = Receipt::findById($rid);
                if (!$existing || (int)$existing['user_id'] !== $userId) { $error = 'Ação não permitida.'; }
                else {
                    if (Receipt::update($rid, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento atualizado com sucesso!'; $editReceipt = null; }
                    else { $error = 'Falha ao atualizar recebimento.'; }
                }
            } else {
                if (Receipt::create($userId, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento lançado com sucesso!'; }
                else { $error = 'Falha ao lançar recebimento.'; }
            }
        } else { $error = 'Selecione o cliente, informe a data e valor válido.'; }
    }
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
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center_id" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">(opcional)</option>
          <?php foreach ($centers as $cc): $selc = ($editReceipt && isset($editReceipt['cost_center_id']) && (int)$editReceipt['cost_center_id'] === (int)$cc['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $selc ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
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
    <form method="get" class="mb-4 flex items-end gap-3">
      <div>
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center" class="mt-1 w-64 rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">Todos</option>
          <?php foreach ($centers as $cc): ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $filterCenterId === (int)$cc['id'] ? 'selected' : '' ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Aplicar</button>
        <a href="receipts.php" class="ml-2 px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Limpar</a>
      </div>
    </form>
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
        $amount = (float)$amountNorm;
        $notes = trim($_POST['notes'] ?? '');
        $action = $_POST['action'] ?? 'create';
        if ($client_id > 0 && $date !== '' && $amount > 0) {
            if ($action === 'update' && isset($_POST['receipt_id'])) {
                $rid = (int)$_POST['receipt_id'];
                $existing = Receipt::findById($rid);
                if (!$existing || (int)$existing['user_id'] !== $userId) { $error = 'Ação não permitida.'; }
                else {
                    if (Receipt::update($rid, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento atualizado com sucesso!'; $editReceipt = null; }
                    else { $error = 'Falha ao atualizar recebimento.'; }
                }
            } else {
                if (Receipt::create($userId, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento lançado com sucesso!'; }
                else { $error = 'Falha ao lançar recebimento.'; }
            }
        } else { $error = 'Selecione o cliente, informe a data e valor válido.'; }
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
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center_id" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">(opcional)</option>
          <?php foreach ($centers as $cc): $selc = ($editReceipt && isset($editReceipt['cost_center_id']) && (int)$editReceipt['cost_center_id'] === (int)$cc['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $selc ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
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
    <form method="get" class="mb-4 flex items-end gap-3">
      <div>
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center" class="mt-1 w-64 rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">Todos</option>
          <?php foreach ($centers as $cc): ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $filterCenterId === (int)$cc['id'] ? 'selected' : '' ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Aplicar</button>
        <a href="receipts.php" class="ml-2 px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Limpar</a>
      </div>
    </form>
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
        $amount = (float)$amountNorm;
        $notes = trim($_POST['notes'] ?? '');
        $action = $_POST['action'] ?? 'create';
        if ($client_id > 0 && $date !== '' && $amount > 0) {
            if ($action === 'update' && isset($_POST['receipt_id'])) {
                $rid = (int)$_POST['receipt_id'];
                $existing = Receipt::findById($rid);
                if (!$existing || (int)$existing['user_id'] !== $userId) { $error = 'Ação não permitida.'; }
                else {
                    if (Receipt::update($rid, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento atualizado com sucesso!'; $editReceipt = null; }
                    else { $error = 'Falha ao atualizar recebimento.'; }
                }
            } else {
                if (Receipt::create($userId, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento lançado com sucesso!'; }
                else { $error = 'Falha ao lançar recebimento.'; }
            }
        } else { $error = 'Selecione o cliente, informe a data e valor válido.'; }
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
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center_id" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">(opcional)</option>
          <?php foreach ($centers as $cc): $selc = ($editReceipt && isset($editReceipt['cost_center_id']) && (int)$editReceipt['cost_center_id'] === (int)$cc['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $selc ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
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
    <form method="get" class="mb-4 flex items-end gap-3">
      <div>
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center" class="mt-1 w-64 rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">Todos</option>
          <?php foreach ($centers as $cc): ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $filterCenterId === (int)$cc['id'] ? 'selected' : '' ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Aplicar</button>
        <a href="receipts.php" class="ml-2 px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Limpar</a>
      </div>
    </form>
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
        $amount = (float)$amountNorm;
        $notes = trim($_POST['notes'] ?? '');
        $action = $_POST['action'] ?? 'create';
        if ($client_id > 0 && $date !== '' && $amount > 0) {
            if ($action === 'update' && isset($_POST['receipt_id'])) {
                $rid = (int)$_POST['receipt_id'];
                $existing = Receipt::findById($rid);
                if (!$existing || (int)$existing['user_id'] !== $userId) { $error = 'Ação não permitida.'; }
                else {
                    if (Receipt::update($rid, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento atualizado com sucesso!'; $editReceipt = null; }
                    else { $error = 'Falha ao atualizar recebimento.'; }
                }
            } else {
                if (Receipt::create($userId, $client_id, $center_id, $date, $amount, $notes ?: null)) { $message = 'Recebimento lançado com sucesso!'; }
                else { $error = 'Falha ao lançar recebimento.'; }
            }
        } else { $error = 'Selecione o cliente, informe a data e valor válido.'; }
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
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center_id" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">(opcional)</option>
          <?php foreach ($centers as $cc): $selc = ($editReceipt && isset($editReceipt['cost_center_id']) && (int)$editReceipt['cost_center_id'] === (int)$cc['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $selc ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
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
    <form method="get" class="mb-4 flex items-end gap-3">
      <div>
        <label class="block text-sm font-medium text-brand-800">Centro de custos</label>
        <select name="center" class="mt-1 w-64 rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value="">Todos</option>
          <?php foreach ($centers as $cc): ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $filterCenterId === (int)$cc['id'] ? 'selected' : '' ?>><?= h($cc['name']) ?><?= $cc['nickname'] ? ' — ' . h($cc['nickname']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Aplicar</button>
        <a href="receipts.php" class="ml-2 px-3 py-2 rounded-lg border border-brand-300 text-brand-800">Limpar</a>
      </div>
    </form>
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