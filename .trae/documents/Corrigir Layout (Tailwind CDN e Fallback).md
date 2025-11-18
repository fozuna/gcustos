## Diagnóstico
- O layout aparece sem estilização, indicando que o Tailwind via CDN não está aplicando classes.
- As páginas usam classes customizadas `brand-*` definidas em `tailwind.config`, e se o config não for aplicado a tempo ou o CDN falhar, essas classes ficam sem efeito.
- O `layout.php` injeta Tailwind após definir `tailwind.config`; porém o carregamento de CDN pode estar bloqueado/instável, quebrando o estilo.

## Correções Propostas
1. Reordenar carregamento do Tailwind
- Em `layout.php` e `index.php`, mover a definição `tailwind.config` para antes da tag de script do CDN, garantindo que a paleta `brand` esteja ativa quando o Tailwind inicializar.
- Manter os plugins: `forms,typography,aspect-ratio`.

2. Fallback CSS mínimo para brand
- Adicionar um `<style>` com regras básicas para `.bg-brand-50`, `.text-brand-800`, `.text-brand-900`, `.border-brand-100`, `.border-brand-200`, `.bg-brand-700`, `.bg-brand-800` usando as mesmas cores já definidas no config. Isso assegura legibilidade mesmo se o CDN falhar.
- Não substituir o Tailwind; apenas garantir uma experiência aceitável offline.

3. Conferências complementares
- Confirmar `lucide` e `chart.js` continuam carregando (sem `defer` desnecessário).
- Não alterar `.htaccess` (o 403 em `/classes/*` é correto e não interfere).

## Alterações de Arquivo
- `layout.php`: ajustar ordem de scripts (config antes do CDN) e incluir fallback CSS curto para `.brand-*`.
- `index.php`: mesmo ajuste de ordem e fallback CSS curto.

## Validação
- Abrir `dashboard.php` e verificar:
  - Sidebar visível e estilizada.
  - Cards com fundo `bg-white`, bordas `border-brand-*`, tipografia e cores `text-brand-*` aplicadas.
  - Gráficos renderizando normalmente.
- Abrir `index.php` e confirmar o cartão de login estilizado.

## Deploy
- Commit das mudanças e push para `main` do repositório `fozuna/gcustos` após confirmação.