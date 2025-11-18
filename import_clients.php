<?php
require_once __DIR__ . '/init.php';
require_auth();

$message = null; $error = null; $report = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $delimiter = ($_POST['delimiter'] ?? ';') === ',' ? ',' : ';';
    $hasHeader = isset($_POST['has_header']);

    if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
        $error = 'Arquivo não enviado.';
    } else {
        $tmp = $_FILES['file']['tmp_name'];
        $handle = fopen($tmp, 'r');
        if (!$handle) { $error = 'Não foi possível ler o arquivo.'; }
        else {
            $line = 0; $imported = 0; $skipped = 0; $failed = 0;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;
                if ($hasHeader && $line === 1) continue;
                // Esperado: Nome;Contato;Cidade;Telefone
                if (count($row) < 1) { $failed++; $report[] = "Linha $line: colunas insuficientes"; continue; }
                $name = trim((string)($row[0] ?? ''));
                $contact = trim((string)($row[1] ?? ''));
                $city = trim((string)($row[2] ?? ''));
                $phone = trim((string)($row[3] ?? ''));
                if ($name === '') { $skipped++; $report[] = "Linha $line: nome vazio"; continue; }
                $ok = Client::create($name, $contact ?: null, $city ?: null, $phone ?: null);
                if ($ok) $imported++; else { $failed++; $report[] = "Linha $line: erro ao salvar"; }
            }
            fclose($handle);
            $message = "Importação concluída: $imported inseridos, $skipped ignorados, $failed falharam.";
        }
    }
}

ob_start();
?>
<div class="flex flex-col gap-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-brand-900">Importar clientes (CSV)</h1>
    <a href="clients.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">
      <i data-lucide="contact" class="w-5 h-5"></i> Voltar a Clientes
    </a>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <div class="prose prose-sm text-brand-800 mb-3">
      <p>Formato esperado por linha: <strong>Nome;Contato;Cidade;Telefone</strong>.</p>
    </div>
    <form method="post" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-brand-800">Arquivo CSV</label>
        <input type="file" name="file" accept=".csv,text/csv" required class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm font-medium text-brand-800">Delimitador</label>
        <select name="delimiter" class="mt-1 w-full rounded-lg border border-brand-300 bg-brand-50 text-brand-900 focus:border-brand-500 focus:ring-brand-500 px-3 py-2">
          <option value=";">Ponto e vírgula (;)</option>
          <option value=",">Vírgula (,)</option>
        </select>
      </div>
      <div>
        <label class="inline-flex items-center gap-2 text-sm text-brand-800">
          <input type="checkbox" name="has_header" class="rounded border-brand-300"> Primeira linha é cabeçalho
        </label>
      </div>
      <div class="md:col-span-3 flex items-center justify-end gap-3">
        <button type="submit" class="px-4 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Importar</button>
      </div>
    </form>
  </div>

  <?php if ($message): ?>
    <div class="p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"><?= h($message) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= h($error) ?></div>
  <?php endif; ?>
  <?php if (!empty($report)): ?>
    <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
      <h2 class="text-brand-800 font-medium mb-3">Relatório</h2>
      <ul class="list-disc ml-5 text-sm text-brand-800">
        <?php foreach ($report as $r): ?><li><?= h($r) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
render_layout('Importar clientes', $content);
?>