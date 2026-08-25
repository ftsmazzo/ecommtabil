# Importação: origem → modelo interno

O sistema **não** tenta copiar a planilha do cliente. Ele traduz para o ledger:

`projeto + tipo (DRE/DFC/BP) + conta do plano + período + valor`

## O que é origem e o que é modelo

- **Origem:** qualquer arquivo que o cliente mandar (marketplace, ERP, DRE já pronto, OFX, layout novo).
- **Modelo:** plano de contas e estrutura do demonstrativo **neste sistema**.

Arquivos de exemplo em `modelos/Entradas` são só amostras de *famílias*, não a lista fechada de formatos aceitos.

## Famílias (detectadas por colunas, não por nome de arquivo)

| Família | Como reconhece | Layout |
|---------|----------------|--------|
| `matriz_demonstrativo` | Vários meses (Jan–Dez) + linha de conta | matriz |
| `ledger_marketplace` | Pedido + receita/tarifa de canal | colunar |
| `ledger_operacional` | Poucas colunas tipo Data, Custo, Venda, Tarifa | colunar |
| `extrato_ofx` | extensão `.ofx` | ledger (caixa) |
| `desconhecida` | não casou | de-para genérico + IA nas lacunas |

Um layout novo cai em `desconhecida` e ainda pode ser importado. Depois que o usuário confirma o de-para, o **fingerprint** dos cabeçalhos é gravado em `projeto_origem_perfil` (nomes de conta, não IDs). A próxima planilha com as mesmas colunas reaproveita o mapa em qualquer projeto e em qualquer hospedagem.

## Pacote para o ambiente real (FTP)

Incluir código + `storage/migrations/0.1.5.sql`. Nada nesta feature assume path de EasyPanel ou VPS.

## O que não fazer

- Não criar um mapa exclusivo Shopee vs ML vs “os 5 arquivos”.
- Não persistir `conta_12` como verdade absoluta entre ambientes.
