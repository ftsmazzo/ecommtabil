# Checkpoint do Módulo de Cliente

Este arquivo registra o estado final da base de clientes.
Ele serve como ponto de retorno para futuras evoluções, sem misturar o fluxo principal com a importação.

## Estado atual

O módulo de cliente foi fechado como base funcional reutilizável.

Neste momento, o sistema possui:

- cadastro principal de clientes pronto
- cadastro de situações de cliente
- cadastro de ramos de cliente
- permissões separadas para gerenciar, inserir, editar e excluir cada parte
- listagem de clientes com `leftJoin` para situação e ramo
- formulário principal padronizado com os mesmos blocos visuais do módulo de usuário
- comportamento global de `PF` e `PJ` reaproveitável em outros cadastros
- campos obrigatórios e campos em maiúsculo controlados pelo model
- busca de CEP e busca de CNPJ no fluxo do formulário
- seletor de pessoa configurável por módulo
- uso opcional de ramo por módulo
- logos parametrizados para login, sidebar e mobile
- helpers globais para required, upper e validações de formulário
- importação separada e documentada em [checkpoint-importacao.md](/c:/laragon/www/sistema/docs/checkpoint-importacao.md)

## Banco e migrations

As migrations do módulo de cliente continuam sendo:

- [20260421_0003_create_cliente_base.sql](/c:/laragon/www/sistema/storage/migrations/20260421_0003_create_cliente_base.sql)
- [20260421_0004_seed_cliente_situacoes.sql](/c:/laragon/www/sistema/storage/migrations/20260421_0004_seed_cliente_situacoes.sql)

Estruturas criadas:

- `cliente`
- `cliente_situacao`
- `cliente_ramo`

## Permissões do módulo

As permissões já cadastradas em `usuario_permissao` para o módulo de cliente são:

- `cliente_gerenciar`
- `cliente_inserir`
- `cliente_editar`
- `cliente_excluir`
- `cliente_situacao_gerenciar`
- `cliente_situacao_inserir`
- `cliente_situacao_editar`
- `cliente_situacao_excluir`
- `cliente_ramo_gerenciar`
- `cliente_ramo_inserir`
- `cliente_ramo_editar`
- `cliente_ramo_excluir`

## Telas e componentes montados

### Cliente

- [lista.phtml](/c:/laragon/www/sistema/app/Views/admin/cliente/lista.phtml)
- [novo.phtml](/c:/laragon/www/sistema/app/Views/admin/cliente/novo.phtml)
- [editar.phtml](/c:/laragon/www/sistema/app/Views/admin/cliente/editar.phtml)
- [cliente-form.js](/c:/laragon/www/sistema/public/assets/admin/js/cliente-form.js)

### Situações

- [index.phtml](/c:/laragon/www/sistema/app/Views/admin/cliente/situacao/index.phtml)
- [form.phtml](/c:/laragon/www/sistema/app/Views/admin/cliente/situacao/form.phtml)
- [cliente-situacao-form.js](/c:/laragon/www/sistema/public/assets/admin/js/cliente-situacao-form.js)

### Ramos

- [index.phtml](/c:/laragon/www/sistema/app/Views/admin/cliente/ramo/index.phtml)
- [form.phtml](/c:/laragon/www/sistema/app/Views/admin/cliente/ramo/form.phtml)
- [cliente-ramo-form.js](/c:/laragon/www/sistema/public/assets/admin/js/cliente-ramo-form.js)

## Fluxos já consolidados

- delete aceitando `id` inteiro na URL e fallback para hash
- modais AJAX carregando o formulário correto
- footer do modal fora do HTML carregado por AJAX
- validação base aplicada no carregamento do modal
- uso de `ajaxFormBound` para evitar bind duplicado
- helper `colorContrast()` disponível globalmente
- PF/PJ centralizado em `forms.js`
- `required` e `uppers` vindos do model
- `page-actions` padronizados com `btn-white`

## Resumo executivo

O módulo de cliente já pode ser tratado como base final da aplicação.
O que vier depois tende a ser reaproveitamento ou variação desse padrão, não reinvenção da estrutura.
