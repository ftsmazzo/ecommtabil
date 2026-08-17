# Modais com formulário — padrão do sistema

Guia passo a passo para criar modais de cadastro/edição seguindo o padrão já adotado no sistema.

---

## Como funciona

O sistema usa **Bootstrap 5 Modal** com carregamento de conteúdo via AJAX.

Quando o usuário clica no gatilho (botão ou link), a função `openAjaxModal` abre o modal, exibe um spinner, faz um `GET` (ou `POST`) na URL informada e injeta o HTML retornado dentro de `.modal-body`. Ao submeter o formulário, o `submitHandler` do jQuery Validate bloqueia o modal (overlay "Salvando…") e dispara o `form.submit()` normalmente.

**Arquivos envolvidos:**
- `public/assets/app/scripts/util.js` → `openAjaxModal`, `setModalLoading`, `ensureModalLoading`
- `public/assets/app/scripts/app.js` → `submitHandler`, `setModalInteractionLocked`

---

## 1. Estrutura do modal na página (shell)

Cole o HTML do modal **uma vez** na view que lista os registros. O `modal-body` fica vazio — o conteúdo é injetado via AJAX.

```html
<div class="modal fade" id="modal-NOME" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" form="form-NOME">
                    <i class="uil uil-check me-1"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>
```

**Pontos obrigatórios:**

| Elemento | Regra |
|---|---|
| `id` do modal | único na página, ex: `modal-cliente-ramo` |
| `data-bs-backdrop="static"` | impede fechar clicando fora enquanto salva |
| `.modal-title` | vazio — preenchido dinamicamente pelo gatilho |
| `.modal-body` | vazio — conteúdo injetado via AJAX |
| `form="form-NOME"` no botão Salvar | deve bater com o `id` do `<form>` do partial |

> O modal pode ter `modal-dialog-scrollable` e `modal-lg` / `modal-xl` quando necessário.

---

## 2. Gatilho (botão ou link)

Use atributos `data-*` declarativos — nenhum JS extra é necessário.

```html
<!-- Botão novo -->
<button type="button" class="btn btn-primary"
        data-modal-ajax="<?= $router->route('admin.ramo.novo') ?>"
        data-modal-target="#modal-NOME"
        data-modal-title="Novo Registro">
    <i class="uil uil-plus me-1"></i> Novo
</button>

<!-- Link de edição na tabela -->
<a href="<?= $router->route('admin.ramo.editar', ['id' => $d->id]) ?>"
   data-modal-ajax="<?= $router->route('admin.ramo.editar', ['id' => $d->id]) ?>"
   data-modal-target="#modal-NOME"
   data-modal-title="Editar Registro">
    <?= $d->nome ?>
</a>
```

**Atributos disponíveis:**

| Atributo | Obrigatório | Descrição |
|---|---|---|
| `data-modal-ajax` | ✔ | URL que retorna o HTML do formulário |
| `data-modal-target` | ✔ | Seletor do modal shell (`#modal-NOME`) |
| `data-modal-title` | — | Texto do `.modal-title` |
| `data-modal-method` | — | Método HTTP (padrão: `GET`) |

---

## 3. Partial do formulário (view carregada via AJAX)

O controller retorna **apenas** o HTML do formulário, sem layout.

```php
<!-- app/Views/admin/modulo/form.phtml -->
<form id="form-NOME" class="form-validate" action="<?= $url_action ?>" method="POST">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">

    <?php if (!empty($registro->id)) : ?>
        <input type="hidden" name="id" value="<?= $registro->hash() ?>">
    <?php endif ?>

    <div class="mb-3">
        <label for="campo" class="form-label">
            Nome <span class="text-danger">*</span>
        </label>
        <input type="text"
               id="campo"
               name="campo"
               class="form-control"
               value="<?= htmlspecialchars($registro->campo ?? '', ENT_QUOTES) ?>"
               required>
    </div>

    <!-- Mais campos... -->
</form>
```

**Regras do partial:**

- O `id` do `<form>` **deve ser idêntico** ao `form="..."` do botão Salvar no shell.
- A classe `form-validate` ativa o jQuery Validate automaticamente.
- A `action` aponta para a rota de gravação (POST).
- Não incluir `<html>`, layout ou scripts — só o `<form>`.

---

## 4. Controller — método que retorna o partial

```php
// Exibe o formulário dentro do modal
public function novo(Request $request): void
{
    $this->authorize('modulo_inserir');

    echo $this->view->render('admin/modulo/form', [
        'registro'   => (object) [],
        'url_action' => $this->router->route('admin.modulo.salvar'),
        'csrf'       => $this->csrf->generate(),
    ]);
}

public function editar(Request $request): void
{
    $this->authorize('modulo_editar');

    $id = (int) ($request->params['id'] ?? 0);
    $registro = Modulo::find($id);

    if (!$registro) {
        http_response_code(404);
        echo '<div class="p-3 text-danger">Registro não encontrado.</div>';
        return;
    }

    echo $this->view->render('admin/modulo/form', [
        'registro'   => $registro,
        'url_action' => $this->router->route('admin.modulo.salvar'),
        'csrf'       => $this->csrf->generate(),
    ]);
}

// Processa o POST e redireciona (fluxo normal de formulário)
public function salvar(Request $request): void
{
    $this->authorize('modulo_inserir');
    // … validação e persistência …
    $this->message->success('Salvo com sucesso.');
    $this->router->redirect('admin.modulo.index');
}
```

> O retorno do método de exibição é sempre `echo $this->view->render(...)` sem layout,
> pois o `openAjaxModal` injeta diretamente no `.modal-body`.

---

## 5. Rotas

```php
// routes/admin.php
$router->get('/modulo/novo',        'ModuloController:novo',    'admin.modulo.novo');
$router->get('/modulo/{id}/editar', 'ModuloController:editar',  'admin.modulo.editar');
$router->post('/modulo/salvar',     'ModuloController:salvar',  'admin.modulo.salvar');
```

---

## 6. Comportamento automático

Após seguir os passos acima, o sistema já faz automaticamente:

| Evento | O que acontece |
|---|---|
| Clique no gatilho | Modal abre, spinner aparece, AJAX busca o formulário |
| AJAX completo | Formulário é injetado, máscaras e validações são inicializadas |
| AJAX com erro | Mensagem de erro no body, overlay some |
| Submit do formulário | Overlay "Salvando…" aparece, botões Salvar/Cancelar/X ficam desabilitados |
| Modal fechado | Overlay reseta, bloqueio removido |

---

## 7. Inicializador personalizado (opcional)

Se o formulário carregado precisar de JS extra (select2, datepicker, etc.), registre um inicializador que é executado após o AJAX completar:

```javascript
// No endScripts da página de listagem
window.registerAjaxModalInitializer(function ($modal, response, modalEl, modalInstance) {
    // Inicializa select2 apenas nos campos do modal
    $modal.find('.select2').select2({
        dropdownParent: $modal,
        width: '100%'
    });

    // Foca o primeiro campo
    $modal.find('input:not([type=hidden])').first().trigger('focus');
});
```

O callback recebe:
- `$modal` — jQuery do elemento modal
- `response` — HTML retornado pelo AJAX
- `modalEl` — elemento DOM do modal
- `modalInstance` — instância `bootstrap.Modal`

---

## 8. Abertura programática (JS)

Quando precisar abrir o modal via JS (sem atributo `data-modal-ajax`):

```javascript
openAjaxModal({
    modal: '#modal-NOME',
    url: '/admin/modulo/42/editar',
    title: 'Editar Registro',
    onLoaded: function (response, modalEl, modalInstance) {
        // executado após o conteúdo ser injetado
    }
});
```

---

## Checklist rápido

```
[ ] Shell do modal na view de listagem (id único, modal-body vazio)
[ ] Botão Salvar no footer com form="form-NOME"
[ ] Gatilho com data-modal-ajax, data-modal-target, data-modal-title
[ ] Partial com <form id="form-NOME" class="form-validate">
[ ] csrf, action e campos do formulário no partial
[ ] Rota GET para o partial e POST para o salvar
[ ] Controller retorna echo $this->view->render(...) sem layout
```
