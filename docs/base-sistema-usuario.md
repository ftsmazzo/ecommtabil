# Base do Sistema

Este documento resume a fundacao atual da plataforma.
Neste momento, a base foi preparada apenas com o modulo de usuario e recursos comuns que servem como ponto de partida para qualquer sistema novo.

## Objetivo desta base

A ideia desta estrutura e evitar recomecar do zero a cada projeto.
Ela entrega o nucleo mais comum de administracao, autenticacao e preferencias, para que os proximos sistemas possam focar apenas no modulo de negocio.

## O que ja existe

### Banco de dados

As migrations consolidadas da base estao em:

- [20260418_0001_create_base_core.sql](/c:/laragon/www/sistema/storage/migrations/20260418_0001_create_base_core.sql)
- [20260418_0002_seed_base_core.sql](/c:/laragon/www/sistema/storage/migrations/20260418_0002_seed_base_core.sql)

### Tabelas base

#### `usuario`

Tabela principal de operadores e administradores do sistema.

Responsavel por guardar:

- nome
- login
- senha
- status
- foto
- email
- token
- dados de acesso e controle

#### `usuario_historico`

Tabela de historico de autenticacao e eventos de acesso.

Serve para registrar:

- login
- logout
- falha de autenticacao
- motivo
- IP
- local
- sistema

#### `usuario_preferencia`

Tabela simples de preferencias do usuario.

No momento, a base ficou reduzida para:

- `id_user`
- `tema`

Ela serve para guardar configuracoes pessoais do painel, comecando pelo tema visual.

#### `usuario_perfil`

Tabela opcional de perfis base.

Serve para:

- guardar colecoes prontas de permissoes
- acelerar cadastro e edicao de usuarios
- funcionar como template de parametrizacao

Importante:

- o perfil nao substitui a permissao individual do usuario
- ele apenas preenche a base inicial
- a colecao final salva em `usuario.permissoes` continua sendo a fonte real de autorizacao

#### `auth_sessions`

Tabela de sessoes autenticadas.

Serve para:

- controlar sessoes por dispositivo
- armazenar `token_hash`
- registrar `last_activity`
- permitir revogacao de sessao

#### `password_reset_tokens`

Tabela de apoio para recuperacao de senha.

Serve para:

- armazenar apenas o `token_hash`, nunca o token puro
- controlar expiracao do link
- marcar uso unico com `used_at`
- registrar IP de origem da solicitacao

#### `webpush_subscriptions`

Tabela de inscricoes para notificacao push.

Serve como base para notificacoes do sistema no navegador.

## Seed inicial

A base tambem possui um seed inicial consolidado em:

- [20260418_0002_seed_base_core.sql](/c:/laragon/www/sistema/storage/migrations/20260418_0002_seed_base_core.sql)

Ele cria:

- usuario `Admin`
- login `admin`
- senha `admin` com hash bcrypt
- preferencia inicial com tema `light`
- perfil `Administrador`
- 10 permissoes base em `usuario_permissao`
- vinculo do admin principal com o perfil `Administrador`
- admin principal com as 10 permissoes base

## O que essa base ja resolve

Com o que ja esta pronto, qualquer sistema novo ja nasce com:

- autenticacao administrativa
- sessao persistente
- controle de atividade da sessao
- revogacao global de sessoes ao trocar ou redefinir senha
- historico de acesso
- perfil base opcional para parametrizacao de permissoes
- excecao individual por usuario preservada
- recuperacao de senha com token expirável
- protecao por reCAPTCHA no backend
- rate limit para login e recuperacao de senha
- preferencias basicas do usuario
- estrutura inicial para notificacoes push
- usuario administrador padrao para bootstrap

## Componentes da aplicacao relacionados

### Models

Ja existem models da base:

- [Usuario.php](/c:/laragon/www/sistema/app/Models/Usuario.php)
- [UsuarioHistorico.php](/c:/laragon/www/sistema/app/Models/UsuarioHistorico.php)
- [UsuarioPreferencia.php](/c:/laragon/www/sistema/app/Models/UsuarioPreferencia.php)
- [WebpushSubscription.php](/c:/laragon/www/sistema/app/Models/WebpushSubscription.php)

### Services

Ja existem services reutilizaveis da base:

- [UsuarioService.php](/c:/laragon/www/sistema/app/Services/UsuarioService.php)
- [UsuarioHistoricoService.php](/c:/laragon/www/sistema/app/Services/UsuarioHistoricoService.php)
- [UsuarioPreferenciaService.php](/c:/laragon/www/sistema/app/Services/UsuarioPreferenciaService.php)

### Core

O nucleo ja suporta:

- autenticacao por guard
- cache
- sessoes
- cookies
- controle de permissoes
- throttle de login
- rate limit de fluxos sensiveis
- politica de senha minima
- invalidacao de sessao por troca de senha
- layout/cenario por modulo

Arquivos centrais:

- [Auth.php](/c:/laragon/www/sistema/app/Core/Auth.php)
- [ControllerAdmin.php](/c:/laragon/www/sistema/app/Core/ControllerAdmin.php)
- [Scenery.php](/c:/laragon/www/sistema/app/Core/Scenery.php)
- [Cache.php](/c:/laragon/www/sistema/app/Core/Cache.php)

## Como enxergar essa base

Pense nela como o "kit inicial" da plataforma.
Ela ainda nao representa um sistema de negocio, mas sim a camada comum que quase todo sistema vai precisar.

Em cima dela, os proximos projetos podem adicionar:

- clientes
- produtos
- financeiro
- pedidos
- PDV
- CRM
- relatorios
- qualquer outro modulo especifico

## Vantagem pratica

Ao iniciar um sistema novo nessa base, voce ja comeca com:

- painel acessivel
- usuario administrador funcional
- sessao controlada
- preferencia visual
- logs de acesso

Isso reduz muito o tempo de setup e deixa o desenvolvimento dos modulos futuros mais consistente.

## Limite atual da base

A base, por enquanto, foi mantida propositalmente enxuta.
Ela nao inclui ainda:

- modulo de clientes
- cadastro de empresas
- perfil de acesso completo por interface
- modulos financeiros
- modulos operacionais

Esses blocos devem ser adicionados depois, conforme a necessidade de cada sistema.

## Resumo

Hoje a plataforma esta pronta para servir como fundacao tecnica de novos sistemas.
O modulo de usuario, autenticacao, preferencias e notificacoes push ja forma um ponto de partida consistente, reutilizavel e suficiente para comecar a construir qualquer novo projeto em cima dela.
