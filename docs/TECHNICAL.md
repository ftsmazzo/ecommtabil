# Sistema — Guia de Layout e Navegação

## Modos de menu

O sistema suporta dois modos de menu por cenário (`admin`, `cliente`). A configuração fica em `config/app.php`, dentro de `sceneries.{cenário}`:

```php
'menu' => 'horizontal', // ou 'vertical'
```

### Vertical
- Sidebar fixa à esquerda
- O conteúdo recebe `margin-left: 260px` automaticamente via `volt.css`
- Sidebar pode ser recolhida (modo *contracted*) — estado salvo em `localStorage`

### Horizontal
- Header fixo no topo com duas faixas: **top** (logo + ações) e **nav** (itens do menu)
- A sidebar ainda é renderizada, mas com a classe `app-sidebar-mobile-only` — visível apenas em mobile
- O conteúdo **não recebe** `margin-left` (sobrescrito por `.app-layout-horizontal .content { margin-left: 0 }` em `_custom.scss`)
- O template `app/Views/app/template/horizontal-header.phtml` é incluído automaticamente pelo `base.phtml` quando o modo é `horizontal`

---

## Container do conteúdo

O container que envolve o conteúdo de cada página é configurável em três níveis (do mais específico para o mais geral):

### 1. Por página (view ou controller)
Passe a variável `content_container` para a view:

```php
// no controller
$this->render('minha-view', [
    'content_container' => 'container',
]);
```

### 2. Global por cenário
Em `config/app.php`, dentro de `sceneries.{cenário}.layout`:

```php
'content_container' => 'container-fluid', // padrão
```

### 3. Fallback automático
Se nenhum dos dois acima for definido, `Scenery::getLayout()` aplica:
- `container-extra` quando o menu é `horizontal`
- `container-fluid` quando o menu é `vertical`

### Valores aceitos

| Valor | Comportamento |
|---|---|
| `container-fluid` | Largura total da viewport com gutter padrão |
| `container` | Container Bootstrap responsivo centralizado |
| `container-extra` | Container intermediário definido em `_custom.scss` (máx. 1480px em telas grandes) |

---

## Estrutura de arquivos relevantes

```
config/app.php                              — configuração de cenários (menu, container, logos)
app/Core/Scenery.php                        — resolve layout, container e preferências por cenário
app/Views/app/template/base.phtml           — template base; decide qual menu renderizar
app/Views/app/template/sidebar.phtml        — menu vertical
app/Views/app/template/horizontal-header.phtml — menu horizontal (desktop)
app/Views/app/template/topbar.phtml         — topbar (mobile e modo vertical)
app/Services/Menu/MenuRenderer.php          — renderiza HTML do menu (vertical e horizontal)
app/Services/MenuService.php                — gera array de itens do menu por cenário
public/assets/app/scss/_custom.scss         — estilos do layout (horizontal, sidebar, containers)
public/assets/app/scripts/app.js            — comportamento do menu (activeMenu, toggle ícones, sidebar)
```

---

## Menu horizontal — comportamentos

### Item ativo
A função `activeMenu(slug)` é chamada no `$(document).ready` de cada página. No modo horizontal ela:
- Marca o link com `.active`
- **Não abre** o dropdown pai (comportamento invertido em relação ao vertical, onde o submenu colapso era expandido)
- Fecha qualquer dropdown que esteja aberto via Bootstrap API

### Toggle de ícones
Botão no header horizontal (ícone `⊞`) adiciona/remove a classe `.hide-icons` no `<ul>` do menu.  
Preferência salva em `localStorage` com a chave `horizontal-menu-hide-icons`.

### Separadores (`type: 'title'`)
No modo horizontal, separadores são **ocultados no nível raiz** e exibidos normalmente dentro de dropdowns (`level > 1`).

---

## Compilação de assets

O projeto usa **Sass** para compilar os estilos. Em ambiente `sandbox`/`local`/`dev` os arquivos são carregados separadamente:

```
public/assets/dist/bootstrap/css/bootstrap.css
public/assets/app/css/volt.css
public/assets/app/css/style.min.css        ← gerado a partir de scss/style.scss
public/assets/app/css/util.min.css
```

Para recompilar após alterar qualquer `.scss`:

```bash
sass --style=compressed public/assets/app/scss/style.scss public/assets/app/css/style.min.css
```

Em produção (`APP_ENV=production`) é carregado apenas `app/css/app.min.css` (bundle único).
