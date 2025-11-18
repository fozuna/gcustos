<?php
require_once __DIR__ . '/init.php';
require_auth();

$version = defined('APP_VERSION') ? APP_VERSION : '1.0.0';
$year = date('Y');

ob_start();
?>
<div class="flex flex-col gap-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-brand-900">Sobre</h1>
    <a href="dashboard.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">
      <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Voltar ao dashboard
    </a>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <h2 class="text-brand-800 font-medium mb-3">Informações do sistema</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
      <div class="rounded-lg border border-brand-200 bg-brand-50 p-4">
        <div class="text-brand-700">Sistema</div>
        <div class="text-brand-900 font-semibold"><?= h(APP_NAME) ?></div>
      </div>
      <div class="rounded-lg border border-brand-200 bg-brand-50 p-4">
        <div class="text-brand-700">Versão atual</div>
        <div class="text-brand-900 font-semibold"><?= h($version) ?></div>
      </div>
      <div class="rounded-lg border border-brand-200 bg-brand-50 p-4">
        <div class="text-brand-700">Desenvolvido por</div>
        <div class="text-brand-900 font-semibold">Fabio Ozuna</div>
      </div>
      <div class="rounded-lg border border-brand-200 bg-brand-50 p-4">
        <div class="text-brand-700">Empresa</div>
        <div class="text-brand-900 font-semibold">Traxter</div>
      </div>
    </div>
    <div class="mt-4 text-xs text-brand-700">© <?= h($year) ?> Traxter. Todos os direitos reservados.</div>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <h2 class="text-brand-800 font-medium mb-3">Manual de uso (resumo)</h2>
    <div class="prose prose-sm text-brand-800">
      <ul class="list-disc ml-5">
        <li>Clientes: cadastre, edite e exclua seus clientes; importe via CSV.</li>
        <li>Fornecedores: cadastre, edite e exclua fornecedores; importe via CSV.</li>
        <li>Lançar custo: informe data, grupo, fornecedor (opcional), descrição e valor.</li>
        <li>Lançar receita: informe data, cliente, descrição e valor.</li>
        <li>Fluxo de caixa: saldo diário acumulado por período; exporte Excel/PDF.</li>
        <li>Filtros de custos: filtre por período e fornecedor; veja o total do período.</li>
        <li>Importar custos: faça upload de CSV; criação automática de grupos (opção).</li>
        <li>Grupos: gerencie grupos de custos para organizar seus lançamentos.</li>
        <li>Dashboard: visão geral com gráficos e totais por período.</li>
      </ul>
      <p class="mt-3">Dica: utilize os botões de <strong>Exportar</strong> nas páginas para gerar relatórios rapidamente.</p>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
render_layout('Sobre', $content);
?>