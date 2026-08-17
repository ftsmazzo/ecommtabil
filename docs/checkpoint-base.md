# Checkpoint da Base

Este arquivo registra o estado atual da base para retomada futura.
Ele marca o fechamento da fundacao tecnica e a liberação da base para os módulos reais do sistema.

## Estado atual

A base do sistema foi considerada fechada no nucleo.

Neste momento, a plataforma ja possui:

- autenticacao administrativa consolidada
- recuperacao e redefinicao de senha
- politica de senha minima centralizada
- sessoes por dispositivo com revogacao
- historico de login
- cadastro de usuarios
- cadastro de perfis base
- permissoes individuais por usuario
- perfil `Administrador` parametrizado com todas as permissoes base
- tabelas e formulos CRUD padronizados
- DataTables padronizados
- docs principais da base organizadas
- suporte a `.env` para segredos e variacoes por ambiente
- configuracao estruturada mantida em `config/*.php`

## Banco e migrations

As migrations da base foram consolidadas em apenas 2 arquivos:

- [20260418_0001_create_base_core.sql](/c:/laragon/www/sistema/storage/migrations/20260418_0001_create_base_core.sql)
- [20260418_0002_seed_base_core.sql](/c:/laragon/www/sistema/storage/migrations/20260418_0002_seed_base_core.sql)

O README das migrations atualizado esta em:

- [storage/migrations/README.md](/c:/laragon/www/sistema/storage/migrations/README.md)

### Estrutura criada pela base

- `auth_sessions`
- `usuario`
- `usuario_historico`
- `usuario_preferencia`
- `usuario_perfil`
- `usuario_permissao`
- `password_reset_tokens`
- `webpush_subscriptions`

### Seed inicial da base

- usuario `Admin`
- login `admin`
- senha `admin`
- preferencia inicial `light`
- perfil `Administrador`
- 10 permissoes base cadastradas
- usuario principal vinculado ao perfil `Administrador`
- usuario principal populado com as 10 permissoes base

## Ambiente e configuracao

O sistema agora trabalha com os dois mundos de forma organizada:

- [config/*.php](/c:/laragon/www/sistema/config) para estrutura e comportamento
- [`.env`](/c:/laragon/www/sistema/.env) para segredos e variacoes de ambiente

Documentacao da implementacao:

- [env-config.md](/c:/laragon/www/sistema/docs/env-config.md)

## Autenticacao e seguranca

A base ja entrega:

- login por guard
- logout com limpeza de cache da sessao atual
- historico de login/logout/falhas
- reCAPTCHA validado no backend
- throttle de login
- rate limit para recuperacao e redefinicao de senha
- troca de senha autenticada
- reset de senha por token com expiracao
- revogacao de sessoes ao trocar ou redefinir senha
- rotas centralizadas por guard em `auth.php`
- feature flag para habilitar/desabilitar `forgot_password`

Documentacao principal:

- [autenticacao-seguranca.md](/c:/laragon/www/sistema/docs/autenticacao-seguranca.md)
- [cache-autenticacao.md](/c:/laragon/www/sistema/docs/cache-autenticacao.md)

## Usuarios, perfis e permissoes

O modulo base de usuario ja esta pronto para qualquer sistema novo:

- usuarios com permissao individual final
- perfis usados apenas como parametrizacao
- permissao individual continua prevalecendo
- perfil `Administrador` seeded no start da base
- lista, novo, editar, historico e troca de senha revisados

Documentacao principal:

- [base-sistema-usuario.md](/c:/laragon/www/sistema/docs/base-sistema-usuario.md)
- [perfis-acesso.md](/c:/laragon/www/sistema/docs/perfis-acesso.md)

## Frontend base

O frontend da base ficou padronizado em cima dos templates atuais:

- [base.phtml](/c:/laragon/www/sistema/app/Views/app/template/base.phtml)
- [page-header.phtml](/c:/laragon/www/sistema/app/Views/app/template/page-header.phtml)
- [page-buttons.phtml](/c:/laragon/www/sistema/app/Views/app/template/page-buttons.phtml)

### Convencoes consolidadas

- `page[]` vindo do controller
- breadcrumb dinamico no `page-header`
- `page-actions` e `page-buttons`
- `form-validate` como padrao
- `toggle-pass` para exibir senha
- `crud-form-card`, `crud-subsection`, `permission-block`, `crud-table`
- DataTable com padrao global e variante `swapped`
- tooltip Bootstrap com animacao leve
- componentes visuais reaproveitaveis como `info-bar`

Arquivos centrais do JS/CSS:

- [app.js](/c:/laragon/www/sistema/public/assets/app/scripts/app.js)
- [login.js](/c:/laragon/www/sistema/public/assets/app/scripts/login.js)
- [_custom.scss](/c:/laragon/www/sistema/public/assets/app/scss/_custom.scss)
- [_colors.scss](/c:/laragon/www/sistema/public/assets/app/scss/_colors.scss)

## Regras de manutencao ja definidas

- arquivos que comecam com `_` ou `__` servem apenas como referencia do legado
- nao repetir `main__content` e `flash()` em views que usam a base
- nao repetir CSS inline quando o estilo ja estiver no global
- links de tabela que abrem edicao devem usar `class="edit"`
- JS especifico de tela deve ficar em `public/assets/admin/js`

## Proximo passo

A base esta pronta.

O proximo ciclo de trabalho e iniciar os modulos proprios do sistema, reaproveitando:

1. autenticacao consolidada
2. usuario, perfil e permissao como nucleo
3. frontend base e convencoes ja padronizadas
4. configuracao dividida entre `config/*.php` e `.env`

## Resumo executivo

A plataforma agora esta pronta como base real de novos sistemas.
O nucleo de autenticacao, usuario, perfil, permissao, historico, frontend, configuracao e migrations foi consolidado.
A etapa de fundacao pode ser considerada encerrada.
