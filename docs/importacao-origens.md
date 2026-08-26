# Importação: origem → modelo interno

O sistema **não** copia a planilha do cliente. Ele preenche o demonstrativo:

`projeto + tipo (DRE/DFC/BP) + conta do plano + período + valor`

A esquerda do de-para é o **plano daquele tipo**. A planilha só aponta colunas. Configuração “Planilha modelo” é Excel para download, não alimenta o import.

## Origem vs modelo

- **Origem:** arquivo do cliente (marketplace, ERP, DRE em matriz, OFX).
- **Modelo:** contas analíticas deste sistema para o tipo do upload.

`modelos/Entradas` são amostras de famílias, não o schema.

## Motor

Para cada slot do plano, escolhe 0 ou 1 coluna (tipo data/texto/dinheiro). Coluna sem slot é ignorada. Perfil por fingerprint (v5) reusa mapa confirmado (nome de conta, não ID).

Data: prioriza pagamento / criação / venda. **Nunca** data prevista de envio, prazo ou frete. Se a célula principal vier vazia (`-`), tenta as outras colunas boas de data.

Matriz (DRE/DFC/BP prontos): linha de conta × meses. Aba com ano (2026) vira ano-base. DFC sem cabeçalho Jan–Dez ganha meses sintéticos por posição.

A IA, se usada, **revisa o mapa** e pode corrigir o motor; não fica limitada a lacunas.

## Famílias (só layout)

| Família | Layout |
|---------|--------|
| `matriz_demonstrativo` | meses + linha de conta |
| `ledger_marketplace` / `ledger_operacional` | colunar |
| `extrato_ofx` | ledger |
| `desconhecida` | de-para pelo plano |

## Pacote

`docker-entrypoint.sh` → `scripts/php/migrate.php`. Não depende de path de VPS.
