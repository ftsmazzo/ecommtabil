# Checkpoint do Módulo de Cartão

Atualizado em: `2026-04-24 22:00:00`

Este checkpoint resume o estado atual do módulo de cartões, fidelidade, cashback, hub operacional e relatórios.

## Estado geral

Hoje o módulo já cobre:

- cadastro, edição e busca rápida de cartões
- hub operacional do cartão
- recarga
- registro de gasto
- uso de saldo
- ajuste manual
- estorno
- cashback automático
- fidelidade automática
- extrato com exportação
- relatórios gerenciais básicos
- troca de cliente com feedback visual e respeito ao `cartao_cliente_obrigatorio`
- snapshot de `id_cliente` nas movimentações

## Conceitos principais

### Cartão

O cartão trabalha com:

- `codigo_unico`
- `status`
- `saldo`
- `total_vendas`
- `total_gasto`
- `acumulado`
- `valor_acumulado`
- `validade`

### Movimentação

As movimentações são ledger imutável.

A tabela `movimentacao` agora possui a coluna `id_cliente INT NULL` (migration `20260424_0016`).
Ela captura o cliente vinculado ao cartão **no momento da transação**, funcionando como snapshot.
Movimentações antigas ficam com `NULL`; novas preenchem automaticamente via `CartaoService::registrarMovimentacao()`.

Tipos relevantes hoje:

- `RECARGA`
- `CARGA_INICIAL`
- `CASHBACK`
- `FIDELIDADE`
- `DEBITO`
- `AJUSTE_CREDITO`
- `AJUSTE_DEBITO`
- `ESTORNO`

Leitura operacional:

- `Gasto`: é uma movimentação `DEBITO` com `referencia_tipo = 'venda'`
- `Uso de Saldo`: é `DEBITO` real no saldo
- `Gasto` não mexe no saldo
- `Cashback` e `Fidelidade` creditam saldo

## Fluxo de gasto

O fluxo atual de gasto está assim:

1. registra o `Gasto`
2. atualiza `total_vendas`
3. atualiza `total_gasto`
4. atualiza `valor_acumulado`
5. avalia cashback
6. avalia fidelidade

Regras atuais:

- `Gasto` não debita saldo
- `Cashback` gera linha separada
- `Fidelidade` gera linha separada
- `Cashback` e `Fidelidade` ficam vinculados ao gasto por `id_referencia`

Descrições gravadas:

- `Gasto #ID`
- `Cashback do Gasto #ID (R$ X,XX)`
- `Bônus de Fidelidade do Gasto #ID`

## Ordem do extrato

Para novas movimentações:

- `Gasto` grava com `created_at = agora`
- `Cashback` grava com `created_at = agora + 1 segundo`
- `Fidelidade` grava com `created_at = agora + 2 segundos`

No extrato:

- o `Gasto` aparece como `Gasto`
- não mostra valor em vermelho
- fica sem impacto visual de saldo
- `Cashback` e `Fidelidade` aparecem em linhas próprias

O extrato hoje também tem:

- filtro local por tipo e datas
- exportação `XLS`
- exportação `PDF`
- abertura do `PDF` em nova aba

## Regras de cashback

Módulo implementado:

- CRUD completo de regras
- múltiplas regras podem coexistir
- cálculo por:
  - percentual
  - valor fixo
- `valor mínimo da venda`

Relatório disponível:

- `Relatório de Bônus`

## Regras de fidelidade

Módulo implementado:

- CRUD completo de regras
- apenas uma regra vigente por vez
- tela bloqueia criação de outra vigente
- backend também bloqueia

Modelo atual da regra:

- `quantidade_vendas`
- `valor_minimo_venda`
- `valor_acumulado_minimo`
- `valor_saldo`

Leitura da regra:

- `X gastos` com `valor mínimo por gasto`
- `OU`
- `R$ X acumulados`

Detalhes:

- o gatilho por quantidade só considera gastos que respeitam `valor_minimo_venda`
- o gatilho por valor soma todos os gastos
- ao atingir um gatilho:
  - aplica bônus
  - consome só o gatilho usado

Na listagem de fidelidade:

- a regra vigente aparece em 3 linhas:
  - `Regra vigente`
  - `Gatilho`
  - `Bônus`

## Hub do cartão

O hub hoje faz:

- sincronização automática de validade
- reconciliação de indicadores ao abrir
- reconciliação de saldo ao abrir

Recalculado ao entrar no hub:

- `saldo`
- `total_vendas`
- `total_gasto`
- `acumulado`
- `valor_acumulado`

Se houver divergência, o registro do cartão é atualizado.

Também existe:

- link para cartões do cliente ao lado do nome
- badge azul do total de cartões do cliente
- ações:
  - recarga
  - registrar gasto
  - uso de saldo
  - ajuste manual
  - estorno

## Status e validade

Implementado:

- ao abrir o hub, se o cartão venceu, ele vira `VENCIDO`
- cronjob de vencimento também existe

Arquivo/ideia já trabalhada:

- rotina para verificar diariamente cartões vencidos

## Tela de clientes

Implementado:

- lista mostra número de cartões
- tela de cartões do cliente
- novo cartão via modal
- saldo por cartão na tela de cartões do cliente

## Código do cartão

Regra atual:

- código com 6 dígitos
- comportamento visual com zeros à esquerda
- validação de duplicidade via remote
- trava para não aceitar zero puro

## Relatórios existentes

### 1. Relatório de Bônus

Rota:

- `admin.cartao.relatorio.bonus`

Mostra:

- cashback concedido
- fidelidade concedida
- total concedido
- tabela detalhada

Filtros:

- `data inicial`
- `data final`
- `tipo`

### 2. Relatório de Recargas

Rota:

- `admin.cartao.relatorio.recargas`

Mostra:

- total recarregado
- ticket médio
- total de recargas
- total de carga inicial
- tabela detalhada

Filtros:

- `data inicial`
- `data final`

### 3. Ranking Geral

Rota:

- `admin.cartao.relatorio.tops`

Mostra:

- `Top 5 Gastos`
- `Top 5 Saldo`
- `Top 5 Recargas`
- `Top 5 Mais Beneficiado`

Detalhes:

- `Top Saldo` hoje calcula pelo histórico de movimentações, não só por `cartao.saldo`
- `Top Mais Beneficiado` soma `cashback + fidelidade`
- nas linhas aparece:
  - cliente
  - `(#ID_CLIENTE)` após o nome, se houver cliente
  - `CB` com title `Cashback`
  - `FD` com title `Fidelidade`

## Menu

Hoje existe a seção:

- `RELATÓRIOS`

Itens nela:

- `Relatório de Bônus`
- `Relatório de Recargas`
- `Ranking Geral`

## Troca de cliente no cartão (edição)

Implementado em `novo.phtml` + `cartao-form.js`:

- botão **Trocar Cliente** abre modal `#modal-trocar-cliente`
- modal tem selectpicker com lista de clientes
- opção **Sem Cliente** aparece condicionalmente: só se `cartao_cliente_obrigatorio = 0`
- quando `cartao_cliente_obrigatorio = 1` e nenhum cliente está pré-selecionado, o modal auto-seleciona o primeiro cliente da lista ao abrir
- ao confirmar (**Aplicar troca**):
  - atualiza `#id_cliente_hidden` (campo que é submetido no formulário)
  - exibe feedback visual `#troca-cliente-feedback` com o nome do cliente ou "Sem Cliente (vínculo removido)"
  - exibe notify de sucesso/info
  - quando removendo cliente: limpa o selectpicker desabilitado via `val("") + refresh` (sem disparar `noneSelectedText`)
  - a troca é confirmada só ao salvar o formulário principal

Configuração `cartao_cliente_obrigatorio` é passada ao JS via `data-cliente-obrigatorio` no `<form>`, lida como `!!$pageForm.data("clienteObrigatorio")`.

## Correções de formulário (cadastro de cliente)

Corrigido em `public/assets/app/scripts/forms.js` → rebuild via `app/Lib/Minify.php`:

- `syncPessoaLabels()` agora remove **ambas** as classes `d-none` e `none` ao mostrar campos
- corrigia labels/inputs de Pessoa Física ↔ Pessoa Jurídica que não apareciam corretamente ao trocar o tipo

## Ajustes globais já feitos

### Modais

Foi implementado comportamento global:

- ao salvar formulário em modal:
  - loading button
  - desabilita cancelar
  - desabilita fechar
  - bloqueia fechar por `Esc` e backdrop enquanto envia

### Selectpicker

Busca melhorada:

- ignora acento
- ignora máscara de telefone/documento
- usa tokens normalizados

## Arquivos-chave

### Controllers

- `app/Controllers/Admin/CartaoController.php`
- `app/Controllers/Admin/CashbackRegraController.php`
- `app/Controllers/Admin/FidelidadeRegraController.php`
- `app/Controllers/Admin/ClienteController.php`

### Service

- `app/Services/CartaoService.php`

### Views principais

- `app/Views/admin/cartao/hub.phtml`
- `app/Views/admin/cartao/lista.phtml`
- `app/Views/admin/cartao/novo.phtml`
- `app/Views/admin/cartao/relatorio-bonus.phtml`
- `app/Views/admin/cartao/relatorio-recargas.phtml`
- `app/Views/admin/cartao/relatorio-tops.phtml`
- `app/Views/admin/cartao/fidelidade-regra/index.phtml`

### Rotas

- `routes/admin.php`

### Menu

- `app/Services/Menu/AdminMenuDefinition.php`

## Pontos de atenção atuais

1. Ainda existem arquivos antigos na área de cartões com histórico de encoding ruim.
   Os principais arquivos recentes já foram saneados, mas vale cautela ao regravar arquivos antigos.

2. O método `lancarVendaOperacional()` no service ainda tem um pequeno bloco legado morto relacionado a fidelidade antiga.
   Não está interferindo no fluxo atual, mas pode ser limpo depois.

3. O ranking `Top Mais Beneficiado` hoje faz soma por cartão no controller.
   Funciona bem para base pequena/média, mas pode ser otimizado com agregação SQL no futuro.

4. `referencia_tipo = 'venda'` ainda é o valor interno legado que marca o `Gasto`.
   Visualmente já está como `Gasto`, mas o banco ainda usa esse identificador interno.

5. A migration `20260424_0016_alter_movimentacao_add_id_cliente.sql` precisa ser rodada manualmente no banco.
   Movimentações anteriores à migration ficam com `id_cliente = NULL` — isso é esperado.

## Próximos passos naturais

- rodar migration `20260424_0016` no banco de produção/staging
- exportação `XLS/PDF` dos relatórios novos
- filtros adicionais por cliente/cartão nos relatórios (agora viável via `id_cliente` na movimentação)
- limpeza do legado interno `venda` para `gasto`
- revisão final de encoding na área inteira de cartões
- otimização dos relatórios por SQL agregado
