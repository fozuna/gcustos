<?php
require_once __DIR__ . '/init.php';
require_auth();

$message = null; $error = null; $report = [];

function parse_date_cell(string $s): ?string {
    $s = trim($s);
    if ($s === '') return null;
    // Tenta ISO
    $dt = \DateTime::createFromFormat('Y-m-d', $s);
    if ($dt) return $dt->format('Y-m-d');
    // Tenta BR
    $dt = \DateTime::createFromFormat('d/m/Y', $s);
    if ($dt) return $dt->format('Y-m-d');
    // Excel pode exportar como 44556 (número de dias): converte
    if (is_numeric($s)) {
        $base = new \DateTime('1899-12-30'); // base Excel
        $base->modify('+' . (int)$s . ' days');
        return $base->format('Y-m-d');
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $delimiter = ($_POST['delimiter'] ?? ';') === ',' ? ',' : ';';
    $hasHeader = isset($_POST['has_header']);
    $autoCreateGroups = isset($_POST['auto_groups']);

    if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
        $error = 'Arquivo não enviado.';
    } else {
        $tmp = $_FILES['file']['tmp_name'];
        $handle = fopen($tmp, 'r');
        if (!$handle) {
            $error = 'Não foi possível ler o arquivo.';
        } else {
            $line = 0; $imported = 0; $skipped = 0; $failed = 0;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;
                if ($hasHeader && $line === 1) continue;
                // Esperado: Data;Grupo;Descrição;Valor
                if (count($row) < 4) { $failed++; $report[] = "Linha $line: colunas insuficientes"; continue; }
                [$dateCell, $groupName, $description, $amountCell] = $row;
                $date = parse_date_cell((string)$dateCell);
                $groupName = trim((string)$groupName);
                $description = trim((string)$description);
                $amountStr = str_replace(['R$',' ','\t'], '', (string)$amountCell);
                $amountStr = str_replace(['.', ','], ['', '.'], $amountStr); // trata 1.234,56 -> 1234.56
                $amount = (float)$amountStr;

                if (!$date || !$groupName || $description === '' || $amount <= 0) {
                    $skipped++; $report[] = "Linha $line: dados inválidos"; continue;
                }

                $group = CostGroup::findByName($groupName);
                if (!$group && $autoCreateGroups) {
                    CostGroup::create($groupName);
                    $group = CostGroup::findByName($groupName);
                }
                if (!$group) { $failed++; $report[] = "Linha $line: grupo '$groupName' inexistente"; continue; }

                $ok = Cost::create($_SESSION['user_id'], (int)$group['id'], $date, $description, $amount);
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
    <h1 class="text-2xl font-semibold text-brand-900">Importar custos (CSV)</h1>
    <a href="dashboard.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">
      <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Voltar ao dashboard
    </a>
  </div>

  <div class="bg-white rounded-xl shadow border border-brand-100 p-6">
    <div class="prose prose-sm text-brand-800 mb-3">
      <p>Exporte sua planilha do Excel como CSV e importe aqui. Formato esperado por linha: <strong>Data;Grupo;Descrição;Valor</strong>.</p>
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
      <div>
        <label class="inline-flex items-center gap-2 text-sm text-brand-800">
          <input type="checkbox" name="auto_groups" class="rounded border-brand-300" checked> Criar grupos inexistentes automaticamente
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
render_layout('Importar', $content);
?>