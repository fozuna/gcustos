<?php
// Exemplo de configuração (copie para config.php e ajuste os valores)
// NUNCA commite credenciais reais em repositórios públicos.

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'SEU_DB_NAME');
define('DB_USER', 'SEU_DB_USER');
define('DB_PASS', 'SUA_SENHA_SEGURA');

// Configurações gerais
define('APP_NAME', 'GCustos');
// Se a aplicação estiver na raiz do subdomínio, mantenha '/'
// Ex.: gcustos.traxter.com.br => '/'
// Se estiver em subpasta, ajuste para '/minha-pasta/'
define('BASE_URL', '/');
?>