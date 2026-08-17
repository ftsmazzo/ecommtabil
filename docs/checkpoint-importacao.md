# Checkpoint da Importação de Clientes

Este arquivo registra o estado atual do fluxo de importação de clientes.
Ele fica separado do fluxo principal de cadastro para que os testes e ajustes da planilha não misturem com a tela normal.

## Estado atual

O fluxo de importação de clientes já está funcional e consolidado.

Neste momento, o sistema possui:

- planilha modelo fixa em arquivo estático
- dois modelos neutros:
  - `modelo-importacao-clientes-a.xlsx`
  - `modelo-importacao-clientes-b.xlsx`
- escolha automática do modelo conforme `modulos.cliente.use_ramo`
- download direto da planilha modelo, sem abrir página em branco
- modal de importação na tela de clientes
- upload com visual moderno usando `dizuploader`
- leitura da planilha em PHP
- processamento em lotes para evitar estourar memória
- uso de `insertMany` para inserção em bloco
- aceite de várias formas para o campo `Pessoa`
- validação real de `CPF` e `CNPJ`, incluindo o novo formato alfanumérico
- importação com modo flexível para registros incompletos, marcado por padrão
- `Ramo` resolvido por texto amigável e criado se não existir
- `Situação` resolvida por texto amigável, criando o cadastro informado quando não houver correspondência segura
- retorno final da importação em `alert`, com total de importados, ignorados e ocorrências por linha
- duplicados reportados com a linha da planilha e o documento correspondente
- resumo final compacto, sem excesso de `<br>` e sem espaçamento artificial

## Arquivos principais

- [ClienteController.php](/c:/laragon/www/sistema/app/Controllers/Admin/ClienteController.php)
- [lista.phtml](/c:/laragon/www/sistema/app/Views/admin/cliente/lista.phtml)
- [create_cliente_modelo.php](/c:/laragon/www/sistema/scripts/php/create_cliente_modelo.php)
- [modelo-importacao-clientes-a.xlsx](/c:/laragon/www/sistema/public/assets/docs/modelos/modelo-importacao-clientes-a.xlsx)
- [modelo-importacao-clientes-b.xlsx](/c:/laragon/www/sistema/public/assets/docs/modelos/modelo-importacao-clientes-b.xlsx)

## Regras já definidas

- o modelo é fixo, então qualquer ajuste de coluna deve ser feito no arquivo modelo
- a planilha não precisa de autofiltro
- o campo `Pessoa` aceita variações como:
  - `F`
  - `J`
  - `PF`
  - `PJ`
  - `fisica`
  - `juridica`
- `Ramo` é lido por texto amigável e pode ser criado se não existir
- `Situação` é lida por texto amigável e pode ser criada se não houver correspondência segura
- o modo flexível permite importar mesmo quando a planilha vem com poucos campos
- a importação foi pensada para trabalhar com planilhas grandes sem carregar tudo de uma vez
- os campos em maiúsculo respeitam os `uppers` definidos no model
- campos sem regra de upper gravam exatamente como vieram da planilha

## Ponto atual

O fluxo está pronto para uso e manutenção.

Se houver nova rodada de testes, ela deve focar em:

1. planilhas reais com e sem ramo
2. situações já cadastradas e situações novas vindas da planilha
3. planilhas maiores, para validar o processamento em lote
4. mensagens finais em `alert` e ocorrências por linha

## Próxima retomada

Quando este fluxo for retomado, o ponto de entrada deve ser:

1. testar o upload com uma planilha real
2. validar se o mapeamento de colunas continua estável
3. confirmar se a criação de ramo e situação continua coerente
4. ajustar mensagens, casos de erro e tolerância de leitura, se necessário
