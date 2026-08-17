# Perfis de Acesso

Este documento explica a ideia de perfil na base atual do admin.

## Filosofia

O perfil existe apenas para parametrizacao.

Ele ajuda a:

- acelerar o cadastro
- reaplicar um conjunto comum de permissoes
- reduzir trabalho manual em usuarios parecidos

Mas ele nao e a fonte final de autorizacao.

## O que prevalece

A fonte real de permissao continua sendo:

- `usuario.permissoes`

Ou seja:

- o perfil sugere
- o usuario individual decide

Esse desenho preserva o principal diferencial da base:

- perfis ajudam no padrao
- excecoes continuam simples
- nao existe engessamento por role fechada

## Estrutura

### Tabela de perfil

- `usuario_perfil`

Campos principais:

- `nome`
- `descricao`
- `permissoes`

### Vinculo no usuario

- `usuario.id_perfil`

Esse campo e opcional.

Ele serve para registrar qual perfil foi usado como base no usuario.

## Como funciona no formulario

Ao selecionar um perfil na tela de usuario:

1. o sistema busca as permissoes do perfil
2. desmarca os checkboxes atuais
3. marca os checkboxes do perfil
4. o operador ainda pode ajustar qualquer permissao manualmente

No salvamento:

- o que vai para o banco e a colecao final marcada no usuario
- o perfil continua sendo apenas referencia/base

## Consequencia pratica

Se um perfil for alterado depois:

- isso nao altera automaticamente usuarios ja salvos

Isso foi feito de proposito para manter previsibilidade e preservar excecoes individuais.

## Onde esta implementado

- [UsuarioPerfilController.php](/c:/laragon/www/sistema/app/Controllers/Admin/UsuarioPerfilController.php)
- [UsuarioPerfil.php](/c:/laragon/www/sistema/app/Models/UsuarioPerfil.php)
- [UsuarioPermissaoService.php](/c:/laragon/www/sistema/app/Services/UsuarioPermissaoService.php)
- [UsuarioController.php](/c:/laragon/www/sistema/app/Controllers/Admin/UsuarioController.php)
- [routes/admin.php](/c:/laragon/www/sistema/routes/admin.php)
- [20260417_0005_create_usuario_perfil_base.sql](/c:/laragon/www/sistema/storage/migrations/20260417_0005_create_usuario_perfil_base.sql)

## Resumo

Na base atual:

- perfil e template
- usuario e a autorizacao final

Esse modelo combina padrao e flexibilidade sem perder a capacidade de tratar excecoes.
