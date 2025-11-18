<?php
// Exemplo de configuração (copie para config.php e ajuste os valores)
// NUNCA commite credenciais reais em repositórios públicos.

// Carrega variáveis de ambiente de um arquivo .env (opcional)
// .env já está ignorado no .gitignore
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) { continue; }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        // remove aspas envolventes
        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }
        putenv("$k=$v");
        $_ENV[$k] = $v; $_SERVER[$k] = $v;
    }
}

// Detecta ambiente: prioriza APP_ENV, senão infere por host
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$APP_ENV = getenv('APP_ENV') ?: ((str_contains($host, 'localhost') || str_contains($host, '.local')) ? 'development' : 'production');
define('APP_ENV', $APP_ENV);

// Configurações do banco de dados (podem ser sobrescritas por variáveis de ambiente)
if (APP_ENV === 'development') {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('DB_NAME') ?: 'gcustos');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
} else { // production
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('DB_NAME') ?: 'SEU_DB_NAME');
    define('DB_USER', getenv('DB_USER') ?: 'SEU_DB_USER');
    define('DB_PASS', getenv('DB_PASS') ?: 'SUA_SENHA_SEGURA');
}

// Configurações gerais
define('APP_NAME', 'GCustos');
define('APP_VERSION', getenv('APP_VERSION') ?: '1.0.0');

// BASE_URL automático conforme subpasta
$script = $_SERVER['SCRIPT_NAME'] ?? '/';
$base = rtrim(dirname($script), '/\\');
define('BASE_URL', ($base === '' || $base === '.') ? '/' : ($base . '/'));
?>