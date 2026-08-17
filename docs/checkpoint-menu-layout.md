# Checkpoint: Menu Layout Dinamico

Data: 2026-06-12

## Objetivo

Implementar layout de menu dinamico com suporte a:

- menu vertical
- menu horizontal
- escolha por configuracao
- mobile preservado no fluxo atual
- controle de largura do content

## Estado Atual

- `config/app.php` agora aceita `menu => 'vertical'|'horizontal'` por cenario.
- A `base.phtml` continua unica e decide o layout com poucos `if`s centralizados.
- No modo `horizontal`, o menu horizontal aparece apenas em `lg+`.
- No mobile, o fluxo atual de `sidebar + topbar` foi preservado.
- O renderer de menu suporta os modos `vertical` e `horizontal`.
- O `activeMenu()` foi adaptado para marcar corretamente ambos os layouts.

## Content Container

O content agora aceita:

- `container`
- `container-fluid`
- `container-extra`

Regras:

- valor padrao via `layout['content_container']`
- fallback automatico:
  - horizontal: `container-extra`
  - vertical: `container-fluid`
- override por pagina via:

```php
echo $this->view->render('alguma/view', [
    'content_container' => 'container',
]);
```

## Arquivos Principais Alterados

- `config/app.php`
- `app/Core/Scenery.php`
- `app/Services/Menu/MenuRenderer.php`
- `app/Views/app/template/base.phtml`
- `app/Views/app/template/topbar.phtml`
- `app/Views/app/template/sidebar.phtml`
- `app/Views/app/template/horizontal-header.phtml`
- `app/Views/app/template/notifications-bell.phtml`
- `app/Views/app/template/avatar.phtml`
- `public/assets/app/scripts/app.js`
- `public/assets/app/scss/_custom.scss`

## Observacao Importante

Os arquivos compilados `public/assets/app/css/app.min.css` e `public/assets/app/js/app.min.js` estavam bloqueados no ambiente durante a sessao. Por isso, em ambiente de desenvolvimento/sandbox a view foi ajustada para carregar os fontes atualizados diretamente.

## Validacoes Feitas

- lint PHP nos templates alterados
- lint PHP no `MenuRenderer`
- recompilacao de `public/assets/app/css/style.min.css`

