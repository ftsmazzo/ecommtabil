# Fluxograma do PDV de Cartão

## Fluxo principal

1. Operador lê o cartão.
2. O sistema tenta localizar o cartão pelo `token_nfc`.
3. Se não encontrar:
   - abre o fluxo de emissão
   - vincula cliente novo ou existente
   - gera `codigo_unico`, `token_nfc` e `token_qr`
   - salva o cartão como `ATIVO`
4. Se encontrar:
   - carrega os dados do cartão
   - carrega o cliente vinculado
   - exibe o painel de ações

## Ações principais quando o cartão existe

- Recarga
- Inserir cashback, se houver regra válida
- Bloquear cartão
- Desbloquear cartão, se permitido
- Ver extrato rápido
- Ver dados do cliente

## Ações opcionais que podem entrar depois

- Estorno
- Ajuste manual com permissão restrita
- Troca de cartão
- Cancelamento
- Consulta rápida de saldo

## Regra de negócio resumida

- leitura NFC é o caminho principal
- `codigo_unico` é o identificador interno
- `token_nfc` e `token_qr` são meios de acesso separados
- cartão não encontrado abre emissão
- cartão encontrado abre painel
- ações dependem do status e da permissão do operador
