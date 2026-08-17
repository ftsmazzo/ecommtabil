# Planejamento do Sistema

## Objetivo

Construir um modulo transacional para restaurante/churrascaria com:

- painel administrativo web
- PDV rapido para operacao de caixa
- portal do cliente
- cartao NFC com token unico
- fallback por QR Code ou codigo impresso
- cashback configuravel
- trilha completa de auditoria

## Regra central do negocio

- o cartao nao armazena saldo localmente
- NFC, QR Code e codigo impresso servem apenas como identificadores
- saldo e status sempre sao validados no servidor em tempo real
- toda movimentacao precisa gerar extrato
- nunca permitir saldo negativo
- cartao bloqueado nao pode operar debito nem recarga operacional
- ativacao depende de confirmacao da carga inicial

## Leitura do escopo

Este modulo mistura tres naturezas:

- cadastro e relacionamento: cliente, operador, cartao, regras
- operacao transacional: carga inicial, recarga, debito, cashback, estorno, ajuste
- auditoria e inteligencia: extrato, relatorios, rastreabilidade por operador e origem

O ponto mais sensivel nao sera a tela, e sim a integridade das operacoes financeiras.
Por isso, o centro do modulo deve ser um service transacional e nao os controllers.

## Encaixe na arquitetura atual

A base atual ja combina com esse projeto:

- `app/Models` para entidades persistentes
- `app/Services` para regra de negocio
- `app/Controllers/Admin` para painel
- `routes/admin.php` para rotas do painel
- `App\\Core\\DB` e `App\\Core\\Connection` ja possuem suporte a transacao
- `app/Services/Menu/AdminMenuDefinition.php` podera receber a nova area no menu

Direcao recomendada dentro desta base:

- concentrar regra financeira em `CartaoService`
- manter controllers finos, validando entrada e chamando services
- registrar toda operacao com `origem`, `id_operador`, `referencia_tipo` e `id_referencia`
- evitar qualquer logica de saldo no frontend

## Dominio inicial sugerido

### Entidades principais

- `Cliente`
- `Cartao`
- `CartaoMovimentacao`
- `CashbackRegra`
- `CartaoBloqueioHistorico` ou uso de movimentacao/auditoria dedicada

### Entidades de apoio recomendadas

- `CartaoStatusLog`
- `CartaoRecargaPagamento` se houver confirmacao assicrona ou conciliacao posterior
- `CartaoCashbackLancamento` se a regra de cashback ficar mais complexa no futuro

## Regras que precisam nascer no backend

### Cartao

- codigo unico deve ser exclusivo
- token NFC deve ser exclusivo
- QR Code deve ser exclusivo
- status precisa governar o que pode ou nao pode acontecer
- expiracao precisa ser validada em toda operacao financeira

### Movimentacao

- toda operacao grava saldo anterior e saldo posterior
- `valor` sempre positivo; o significado vem do `tipo`
- extrato eh a fonte de auditoria
- ajustes e estornos precisam de descricao obrigatoria

### Cashback

- cashback deve nascer como movimentacao separada
- uso de cashback nao pode quebrar a leitura do saldo total
- idealmente o sistema distingue geracao e utilizacao por tipo de movimentacao, mesmo que o saldo final fique unificado

## Fluxos mais importantes

### Emissao e ativacao

1. cadastrar ou localizar cliente
2. emitir cartao em `pre_cadastro`
3. registrar carga inicial pendente
4. confirmar pagamento
5. ativar cartao
6. gravar movimentacao `carga_inicial`

### Debito no PDV

1. identificar cartao por NFC, QR Code ou codigo
2. consultar cartao no servidor
3. validar status e expiracao
4. validar saldo suficiente
5. debitar em transacao SQL
6. gravar extrato e resposta ao operador
7. aplicar cashback se houver regra valida

### Recarga

1. identificar cartao
2. informar valor
3. confirmar pagamento
4. creditar saldo em transacao SQL
5. gravar extrato

### Bloqueio

1. operador solicita bloqueio
2. sistema altera status
3. registra operador, data/hora e motivo
4. cartao deixa de aceitar operacoes financeiras

## Riscos tecnicos que ja precisamos considerar

- dupla batida no PDV para o mesmo cartao
- tentativa de debito concorrente em dois caixas
- recarga sem confirmacao real de pagamento
- estorno sem referencia clara da operacao original
- uso de cartao expirado ou bloqueado
- operador tentando alterar saldo fora do fluxo autorizado

## Diretriz de implementacao

Ordem ideal de construcao:

1. modelagem SQL
2. models
3. service transacional
4. controllers admin
5. PDV
6. portal do cliente
7. relatorios

## Decisoes que parecem boas desde ja

- usar `DECIMAL(10,2)` ou superior para valores monetarios
- criar enums ou constantes para `status`, `tipo`, `origem` e `referencia_tipo`
- indexar fortemente campos de consulta operacional: `codigo_unico`, `token_nfc`, `qr_code`, `id_cliente`, `status`, `data_operacao`
- adotar estorno por compensacao, nunca por apagar movimentacao
- manter saldo atual no cartao por performance, mas sempre sustentado pelo extrato

## Estrutura alvo inicial

- `app/Models/Cliente.php`
- `app/Models/Cartao.php`
- `app/Models/CartaoMovimentacao.php`
- `app/Models/CashbackRegra.php`
- `app/Services/CartaoService.php`
- `app/Controllers/Admin/ClienteController.php`
- `app/Controllers/Admin/CartaoController.php`
- `app/Controllers/Admin/CartaoPdvController.php`
- `app/Controllers/Admin/CartaoRelatorioController.php`
- `app/Controllers/Site/CartaoPortalController.php`
- `routes/admin.php`
- `routes/web.php` ou `routes/api.php` para portal e leitura rapida

## Resumo estrategico

Vamos construir menos um "cadastro de cartao" e mais um pequeno ledger financeiro.
Se a gente acertar o service transacional, todo o resto fica mais previsivel: admin, PDV, portal e relatorios.
