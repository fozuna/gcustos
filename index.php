<?php
require_once __DIR__ . '/init.php';

// Se já estiver logado, vai para dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if (Auth::login($email, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Credenciais inválidas. Tente novamente.';
    }
}

$hasUsers = User::countAll() > 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= h(APP_NAME) ?> · Login</title>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              brand: {
                50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a'
              }
            }
          }
        }
      }
    </script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
      body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
      .bg-brand-50{background-color:#eff6ff}
      .text-brand-700{color:#1d4ed8}
      .text-brand-800{color:#1e40af}
      .text-brand-900{color:#1e3a8a}
      .border-brand-100{border-color:#dbeafe}
      .border-brand-300{border-color:#93c5fd}
      .bg-brand-700{background-color:#1d4ed8}
      .hover\:bg-brand-800:hover{background-color:#1e40af}
    </style>
  </head>
<body class="min-h-screen bg-brand-50 flex items-center justify-center">
  <div class="w-full max-w-md bg-white shadow-xl rounded-xl p-8 border border-brand-100">
    <div class="mb-6 text-center">
      <div class="inline-flex items-center justify-center w-12 h-12 rounded-lg bg-brand-100 text-brand-700">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3h18v6H3z"/><path d="M3 9h18v12H3z"/><path d="M8 13h8"/></svg>
      </div>
      <h1 class="mt-2 text-2xl font-semibold text-brand-800"><?= h(APP_NAME) ?></h1>
      <p class="text-brand-700 text-sm">Controle de custos simples e objetivo</p>
    </div>

    <?php if (!$hasUsers): ?>
      <div class="mb-4 p-3 rounded-lg bg-brand-50 border border-brand-200 text-brand-800 text-sm">
        Nenhum usuário cadastrado. <a class="text-brand-700 font-medium underline" href="users.php">Crie o primeiro usuário</a> para acessar.
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-brand-800">E-mail</label>
        <input type="email" name="email" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="seu@email.com" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Senha</label>
        <input type="password" name="password" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 placeholder-brand-400 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" placeholder="••••••••" />
      </div>
      <button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-brand-700 hover:bg-brand-800 text-white font-medium rounded-lg py-2.5">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
        Entrar
      </button>
    </form>
  </div>

  <script>lucide.createIcons();</script>
</body>
</html>