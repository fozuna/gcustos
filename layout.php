<?php
// Componente simples de layout com sidebar à esquerda
function render_layout(string $title, string $content): void {
    $version = defined('APP_VERSION') ? APP_VERSION : '1.0.0';
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h(APP_NAME) ?> · <?= h($title) ?></title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',300:'#93c5fd',400:'#60a5fa',500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',800:'#1e40af',900:'#1e3a8a'}
          }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style> body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; } </style>
</head>
<body class="min-h-screen bg-brand-50">
  <!-- Sidebar fixo -->
  <aside class="fixed inset-y-0 left-0 w-64 bg-brand-900 text-brand-100">
      <div class="p-4 border-b border-brand-800">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-brand-700 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3h18v6H3z"/><path d="M3 9h18v12H3z"/><path d="M8 13h8"/></svg>
          </div>
          <div>
            <div class="font-semibold text-white"><?= h(APP_NAME) ?></div>
            <div class="text-xs text-brand-200">Olá, <?= h($_SESSION['user_name'] ?? 'Usuário') ?></div>
          </div>
        </div>
      </div>
      <nav class="p-4 space-y-2">
        <a href="dashboard.php" class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-brand-800">
          <i data-lucide="layout-dashboard" class="w-5 h-5"></i> <span>Dashboard</span>
        </a>
        <a href="costs.php" class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-brand-800">
          <i data-lucide="plus-circle" class="w-5 h-5"></i> <span>Lançar custo</span>
        </a>
        <a href="receipts.php" class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-brand-800">
          <i data-lucide="banknote" class="w-5 h-5"></i> <span>Lançar receita</span>
        </a>
        <a href="cashflow.php" class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-brand-800">
          <i data-lucide="wallet" class="w-5 h-5"></i> <span>Fluxo de caixa</span>
        </a>
        <a href="clients.php" class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-brand-800">
          <i data-lucide="contact" class="w-5 h-5"></i> <span>Clientes</span>
        </a>
        <a href="suppliers.php" class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-brand-800">
          <i data-lucide="truck" class="w-5 h-5"></i> <span>Fornecedores</span>
        </a>
        <a href="groups.php" class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-brand-800">
          <i data-lucide="folder-cog" class="w-5 h-5"></i> <span>Grupos</span>
        </a>
        <a href="import.php" class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-brand-800">
          <i data-lucide="file-input" class="w-5 h-5"></i> <span>Importar custos</span>
        </a>
        <a href="users.php" class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-brand-800">
          <i data-lucide="users" class="w-5 h-5"></i> <span>Usuários</span>
        </a>
        <a href="logout.php" class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-brand-800">
          <i data-lucide="log-out" class="w-5 h-5"></i> <span>Sair</span>
        </a>
      </nav>
    </aside>

  <!-- Área de conteúdo deslocada pela sidebar fixa -->
  <div class="ml-64 min-h-screen flex flex-col">
    <main class="flex-1">
      <div class="p-6">
        <?= $content ?>
      </div>
    </main>
    <footer class="border-t border-brand-200 bg-white text-brand-800 text-sm px-6 py-3">
      <div class="flex items-center justify-between">
        <span><?= h(APP_NAME) ?></span>
        <span>Versão <?= h($version) ?></span>
      </div>
    </footer>
  </div>
  <script>lucide.createIcons();</script>
</body>
</html>
<?php }
?>