# Checklist da Base

Este arquivo serve como referencia rapida para subir a base da plataforma antes de comecar um novo modulo de negocio.

## O que precisa existir

- banco configurado em [config/database.php](/c:/laragon/www/sistema/config/database.php)
- tabelas base aplicadas de `storage/migrations`
- usuario administrador inicial criado
- pasta `storage/cache/` com permissao de escrita
- assets principais compilando normalmente

## Migrations base

Executar na ordem:

1. [20260416_0001_create_usuario_module_base.sql](/c:/laragon/www/sistema/storage/migrations/20260416_0001_create_usuario_module_base.sql)
2. [20260416_0002_create_webpush_subscriptions.sql](/c:/laragon/www/sistema/storage/migrations/20260416_0002_create_webpush_subscriptions.sql)
3. [20260416_0003_seed_admin_user.sql](/c:/laragon/www/sistema/storage/migrations/20260416_0003_seed_admin_user.sql)

## Resultado esperado no banco

Tabelas:

- `auth_sessions`
- `usuario`
- `usuario_historico`
- `usuario_preferencia`
- `webpush_subscriptions`

Registro inicial:

- usuario `Admin`
- login `admin`
- senha `admin`
- preferencia do usuario `1` com tema `light`

## Fluxos que precisam funcionar

- abrir tela de login
- autenticar com `admin`
- entrar no painel
- carregar menu lateral
- alterar senha
- sair do sistema com confirmacao

## Arquivos importantes da base

Backend:

- [Auth.php](/c:/laragon/www/sistema/app/Core/Auth.php)
- [ControllerAdmin.php](/c:/laragon/www/sistema/app/Core/ControllerAdmin.php)
- [Scenery.php](/c:/laragon/www/sistema/app/Core/Scenery.php)
- [Cache.php](/c:/laragon/www/sistema/app/Core/Cache.php)

Models:

- [Usuario.php](/c:/laragon/www/sistema/app/Models/Usuario.php)
- [UsuarioHistorico.php](/c:/laragon/www/sistema/app/Models/UsuarioHistorico.php)
- [UsuarioPreferencia.php](/c:/laragon/www/sistema/app/Models/UsuarioPreferencia.php)
- [WebpushSubscription.php](/c:/laragon/www/sistema/app/Models/WebpushSubscription.php)

Frontend base:

- [app.js](/c:/laragon/www/sistema/public/assets/app/scripts/app.js)
- [helpers.js](/c:/laragon/www/sistema/public/assets/app/scripts/helpers.js)
- [util.js](/c:/laragon/www/sistema/public/assets/app/scripts/util.js)
- [login.js](/c:/laragon/www/sistema/public/assets/app/scripts/login.js)
- [_custom.scss](/c:/laragon/www/sistema/public/assets/app/scss/_custom.scss)
- [_utils.scss](/c:/laragon/www/sistema/public/assets/app/scss/_utils.scss)
- [style.scss](/c:/laragon/www/sistema/public/assets/app/scss/style.scss)

Documentacao:

- [convencoes-frontend-base.md](/c:/laragon/www/sistema/docs/convencoes-frontend-base.md)

Menu:

- [AdminMenuDefinition.php](/c:/laragon/www/sistema/app/Services/Menu/AdminMenuDefinition.php)

## Se algo parecer quebrado

Passos mais comuns:

1. conferir se a migration base foi aplicada
2. conferir se a seed do admin foi aplicada
3. limpar `storage/cache/`
4. fazer login novamente
5. recarregar assets minificados se houver diferenca entre fonte e arquivo gerado

## O que esta fora da base

Ainda nao faz parte da fundacao:

- modulo de clientes
- modulo financeiro
- modulo comercial
- modulo operacional
- relatorios de negocio
- regras especificas de qualquer projeto

## Regra de ouro

Antes de criar qualquer novo modulo, preservar esta base enxuta.
Tudo que for comum a qualquer sistema pode ficar aqui.
Tudo que for regra de negocio deve entrar depois, em modulo proprio.
