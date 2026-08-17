# Roadmap do Sistema

## Contexto atual desta base

Esta instalacao ja encerrou a fase de fundacao tecnica.

O fluxo atual de trabalho ficou assim:

1. base encerrada e documentada em `checkpoint-base.md`
2. modulo de cliente iniciado e documentado em `checkpoint-cliente.md`
3. proximos ajustes seguem no cadastro principal de cliente
4. depois disso, novos modulos de negocio entram sobre a base pronta

## Roadmap historico

## Fase 1

Fundacao transacional do modulo.

- definir tabelas e constraints
- definir enums de status, tipo e origem
- modelar `Cliente`, `Cartao`, `CartaoMovimentacao` e `CashbackRegra`
- desenhar contratos do `CartaoService`

## Fase 2

Operacao administrativa minima.

- cadastro de clientes
- emissao de cartao
- ativacao com carga inicial
- consulta de saldo
- bloqueio e desbloqueio
- extrato detalhado

## Fase 3

PDV rapido.

- busca por token NFC
- fallback por QR Code
- fallback por digitacao manual
- debito de compra
- recarga com confirmacao
- retorno rapido de saldo final

## Fase 4

Cashback e regras comerciais.

- CRUD de regras de cashback
- aplicacao automatica pos-compra
- extrato especifico de cashback
- filtros por periodo e regra

## Fase 5

Portal do cliente.

- acesso por CPF + codigo
- consulta de saldo
- historico simplificado
- visualizacao de cashback

## Fase 6

Relatorios gerenciais.

- carga inicial
- recargas
- consumo
- saldo em aberto
- cashback gerado e usado
- clientes e cartoes com maior uso
- cartoes sem movimentacao

## Regras inegociaveis

- nenhuma operacao financeira fora de transacao SQL
- nenhuma operacao sem operador ou origem quando aplicavel
- nenhum debito com saldo insuficiente
- nenhum ajuste sem motivo registrado
- nenhuma exclusao fisica de extrato como forma de correcao

## Primeira entrega tecnica ideal

Quando a gente comecar a implementar, a primeira entrega forte deve incluir:

- SQL completo
- models basicos
- `CartaoService` com emitir, ativar, consultar, recarregar, debitar, bloquear e estornar
- controllers administrativos minimos
- rotas do painel

## Observacao

O PDV so deve entrar depois que o nucleo financeiro estiver confiavel.
Neste projeto, velocidade operacional depende primeiro de consistencia transacional.
