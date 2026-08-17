# Convencoes Frontend da Base

Este documento registra as convencoes de frontend que ja fazem parte da base da plataforma.
Ele serve para manter os proximos modulos no mesmo padrao, com menos JavaScript repetido e mais comportamento declarativo.

## Arquivos-base

- [app.js](/c:/laragon/www/sistema/public/assets/app/scripts/app.js)
- [helpers.js](/c:/laragon/www/sistema/public/assets/app/scripts/helpers.js)
- [util.js](/c:/laragon/www/sistema/public/assets/app/scripts/util.js)
- [Minify.php](/c:/laragon/www/sistema/app/Lib/Minify.php)

## Responsabilidade de cada arquivo

### `app.js`

Fica responsavel pelo bootstrap global da interface.

Exemplos:

- sidebar
- toggle de senha
- tooltip
- validacao automatica de formularios
- tabelas padrao
- loading button
- logout
- mascaras globais

### `helpers.js`

Fica responsavel por helpers puros e reaproveitaveis.

Exemplos:

- `stripHtmlToText()`
- `formatDate()`
- `string_to_slug()`
- `prependClass()`

### `util.js`

Fica responsavel por utilitarios visuais e comportamento compartilhado de interface.

Exemplos:

- `Delete()`
- `confirmDeleteUrl()`
- `setTheme()`
- `formatRealBr()`
- busca de CEP
- modal AJAX

## Convencoes prontas

### Tooltip

Qualquer elemento com:

```html
data-tooltip="true"
```

tera tooltip Bootstrap inicializado automaticamente.

O texto pode continuar vindo de `title`.

Exemplo:

```html
<button type="button" title="Editar" data-tooltip="true">...</button>
```

### Validacao automatica de formulario

Qualquer formulario com:

```html
class="form-validate"
```

tera inicializacao automatica do `jquery.validate`.

Quando o submit for valido, o core chama `loadingButton()` automaticamente no botao submit visivel do formulario.

Exemplo:

```html
<form class="form-validate" method="post">
```

Observacao:

- regras customizadas ainda podem ser adicionadas manualmente quando o modulo precisar
- o core nao precisa guardar manualmente o ultimo botao clicado; `loadingButton()` ja resolve o submit correto

### Select2

Se o plugin `Select2` estiver carregado na pagina, qualquer campo com:

```html
class="select2"
```

sera inicializado automaticamente com:

- tema `bootstrap-5`
- largura `100%`
- traducoes base em portugues

Atributos opcionais:

- `data-search="false"` para esconder a busca
- `data-dropdown-parent="#modalPadrao"` para renderizar o dropdown dentro de modal

Exemplo:

```html
<select class="select2" data-search="false"></select>
```

```html
<select class="select2" data-dropdown-parent="#modalPadrao"></select>
```

Observacao:

- o core so inicializa automaticamente se o Select2 estiver disponivel
- hoje o plugin ainda precisa estar carregado pelo cenario/modulo que for usa-lo

### Tabelas padrao

#### DataTables

Qualquer tabela com:

```html
class="table-datatable"
```

sera iniciada com:

- busca
- paginacao
- `25` registros por pagina
- info de registros

Exemplo:

```html
<table class="table table-striped table-datatable">
```

#### Tabulator

Qualquer container com:

```html
class="table-tabulator"
```

sera iniciado com:

- busca
- paginacao
- `25` registros por pagina
- info de registros

No caso do Tabulator, o core cria automaticamente a barra com busca e contador acima da tabela.

Exemplo:

```html
<div id="lista-clientes" class="table-tabulator"></div>
```

### Delete declarativo

Qualquer link ou botao com:

```html
data-delete="/rota/de/exclusao"
```

usara a confirmacao padrao de exclusao.

Atributos opcionais:

- `data-delete-title`
- `data-delete-content`
- `data-delete-confirm`
- `data-delete-cancel`

Exemplo:

```html
<a
    href="/admin/clientes/delete/10"
    data-delete="/admin/clientes/delete/10"
    data-delete-title="Confirmacao"
    data-delete-content="Deseja realmente excluir este cliente?"
>
    Excluir
</a>
```

### Modal AJAX declarativo

Qualquer gatilho com:

```html
data-modal-ajax="/rota/do/conteudo"
data-modal-target="#modalPadrao"
```

abrira o modal e carregara o conteudo remoto dentro de `.modal-body`.

Atributos opcionais:

- `data-modal-title`
- `data-modal-method`
- `data-modal-type`

Exemplo:

```html
<button
    type="button"
    data-modal-ajax="/admin/clientes/10/detalhes"
    data-modal-target="#modalPadrao"
    data-modal-title="Detalhes do cliente"
>
    Ver detalhes
</button>
```

Estrutura minima esperada do modal:

```html
<div class="modal fade" id="modalPadrao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>
</div>
```

### Busca de CEP

O campo:

```html
name="cep"
```

ou

```html
id="cep"
```

ja dispara a consulta automatica no `blur`, desde que o formulario tenha campos de endereco compativeis.

Campos suportados:

- `rua`
- `endereco`
- `logradouro`
- `bairro`
- `cidade`
- `uf`
- `estado`
- `pais`
- `numero`

O core:

- limpa quando o CEP estiver vazio
- mostra estado de carregamento
- consulta o ViaCEP
- preenche os campos encontrados
- foca em `numero` ao final, se existir

### Range de datas

Campos com:

```html
data-range-max="#campoFinal"
```

ou

```html
data-range-min="#campoInicial"
```

passam a sincronizar automaticamente `min` e `max` entre inputs de data.

### Campo numerico com virgula

Qualquer campo com:

```html
class="numeric-comma"
```

passa a aceitar apenas numeros, virgula e sinal negativo.

Para limitar casas decimais:

```html
data-limit="3"
```

### Foco no Enter

Campos com:

```html
class="focus-on-enter"
```

movem o foco para o proximo campo ao pressionar `Enter`, sem enviar o formulario.

### Textarea com envio no Enter

Campos com:

```html
class="sendOnEnter"
```

enviam o formulario ao pressionar `Enter`.

Se usar `Shift + Enter`, o core respeita a quebra de linha.

### Pessoa fisica ou juridica

Quando o formulario usa:

- um campo `[name="pessoa"]` com valor `F` ou `J`
- blocos com classe `.pes`
- blocos variantes como `.pes.F` e `.pes.J`

o core mostra e esconde os campos corretos automaticamente.

Tambem ajusta placeholders padrao de:

- `razao`
- `nome`
- `rg_ie`

## Regra pratica

Antes de criar JavaScript novo em um modulo:

1. verificar se o comportamento cabe em uma convencao da base
2. se for compartilhavel entre cenarios, colocar no core
3. se for especifico de negocio, deixar no modulo

## Resumo

A base agora ja tem convencoes suficientes para formularios, tabelas, CEP, tooltip, delete, modal AJAX e varios comportamentos comuns de formulario.
Isso reduz bastante o volume de JS repetido nos proximos modulos reais.
