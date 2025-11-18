# Guia de Deploy (GCustos)

Este documento descreve como publicar o GCustos em produção e as verificações recomendadas.

## Requisitos
- PHP 8.x com `pdo_mysql` habilitado
- MySQL/MariaDB
- Apache (com suporte a `.htaccess`)

## Configuração de banco
NÃO commite credenciais reais. Use `config.example.php` como base e mantenha `config.php` fora do Git.

Passos:
1. Copie `config.example.php` para `config.php`.
2. Edite `config.php` com suas credenciais de produção (em Hostinger, `DB_HOST` costuma ser `localhost`). NUNCA coloque credenciais reais no Git. Use variáveis de ambiente ou um arquivo `.env` não versionado.

### Ambientes e variáveis
- O `config.example.php` detecta automaticamente o ambiente:
  - `development` quando o host contém `localhost` ou `.local`.
  - `production` caso contrário.
- Você pode forçar com `APP_ENV` e definir `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.

Opção A: `.env` (não versionado)

```
APP_ENV=production
DB_HOST=localhost
DB_NAME=SEU_DB_NAME
DB_USER=SEU_DB_USER
DB_PASS=SUA_SENHA_SEGURA
```

Opção B: Apache `SetEnv` (no servidor)

```
SetEnv APP_ENV production
SetEnv DB_HOST localhost
SetEnv DB_NAME SEU_DB_NAME
SetEnv DB_USER SEU_DB_USER
SetEnv DB_PASS SUA_SENHA_SEGURA
```

Opção C: `config.php` local ao servidor (não versionado)

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'SEU_DB_NAME');
define('DB_USER', 'SEU_DB_USER');
define('DB_PASS', 'SUA_SENHA_SEGURA');
```

## Publicação
1. Faça upload dos arquivos para `public_html` (ou subpasta).
2. Ajuste `BASE_URL` em `config.php` conforme raiz ou subpasta.
3. Acesse `/index.php` e troque a senha do usuário admin criado automaticamente (se aplicável).
4. Valide:
   - Dashboard e filtros respondendo
   - Lançar custo, Grupos e Importar CSV funcionando
   - Usuários listando e criando corretamente

## Segurança
- `.htaccess` na raiz desabilita listagem de diretórios e bloqueia acesso a arquivos sensíveis.
- `.htaccess` em `classes/` impede acesso HTTP aos arquivos da camada de dados.
- Páginas protegidas checam sessão via `require_auth()` em `init.php`.
 - `config.php` está ignorado no Git via `.gitignore`. Versione apenas `config.example.php`.
 - Para produção, defina variáveis de ambiente via painel do host ou `SetEnv`. Evite versionar segredos.

### HTTPS (opcional)
Recomendado forçar HTTPS no servidor. Caso necessário, adicione redirecionamento em `.htaccess`.

## Troubleshooting
- Verifique `display_errors` desabilitado em produção e logs do Apache/PHP.
- Certifique o timezone (`date.timezone`) adequado (ex.: America/Sao_Paulo).