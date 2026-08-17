# Autenticacao e Seguranca

Este documento consolida como a base atual trata autenticacao, sessao, recuperacao de senha e endurecimento dos fluxos sensiveis.

Ele complementa:

- [base-sistema-usuario.md](/c:/laragon/www/sistema/docs/base-sistema-usuario.md)
- [cache-autenticacao.md](/c:/laragon/www/sistema/docs/cache-autenticacao.md)
- [checkpoint-base.md](/c:/laragon/www/sistema/docs/checkpoint-base.md)

## Objetivo

A ideia da implementacao atual e deixar a autenticacao reutilizavel por guard e, ao mesmo tempo, segura o bastante para servir como base padrao de novos sistemas.

Os pilares sao:

- configuracao central em [config/auth.php](/c:/laragon/www/sistema/config/auth.php)
- regras por guard em [App\Core\Auth](/c:/laragon/www/sistema/app/Core/Auth.php)
- fluxo comum de login em [App\Services\LoginService](/c:/laragon/www/sistema/app/Services/LoginService.php)
- particularidades do admin em [Admin/LoginController.php](/c:/laragon/www/sistema/app/Controllers/Admin/LoginController.php)

## Onde cada coisa mora

### `config/auth.php`

Centraliza:

- tabela de sessoes `auth_sessions`
- configuracoes de timeout e binding
- throttle de login
- rate limits adicionais de recuperacao de senha
- politica minima de senha
- definicao dos guards
- rotas por guard
- features por guard, como `forgot_password`

### `App\Core\Auth`

Responsavel por:

- selecionar guard e colunas corretas
- autenticar credenciais
- criar e validar sessoes em `auth_sessions`
- assinar payload de sessao com HMAC
- aplicar throttle de login
- aplicar rate limit extra em fluxos sensiveis
- revogar sessoes especificas ou todas as sessoes do usuario

### `App\Services\LoginService`

Centraliza comportamento comum do login:

- leitura do request
- validacoes basicas de input
- validacao server-side do reCAPTCHA
- chamada final de `Auth::signIn()`
- flash/log/redirect padrao

### `Admin/LoginController`

Mantem o que e particular do guard `usuario`:

- view data do cenario de login
- acao de login do admin
- fluxo de `esqueci minha senha`
- fluxo de `redefinir senha`
- bloqueio total quando `features.forgot_password` estiver desabilitado

## Fluxo de login

### 1. Tela

A tela principal esta em:

- [app/Views/app/login/index.phtml](/c:/laragon/www/sistema/app/Views/app/login/index.phtml)

Ela recebe:

- `template`
- `logo`
- `background`
- `csrf`
- `recaptcha`
- configuracoes de texto/campos

### 2. Front-end

O JS comum esta em:

- [public/assets/app/scripts/login.js](/c:/laragon/www/sistema/public/assets/app/scripts/login.js)

Ele faz:

- `jquery.validate`
- loading do botao
- emissao do token reCAPTCHA v3 quando habilitado
- envio do token hidden `recaptcha`

### 3. Back-end

O POST passa por:

- [LoginService::authenticate](/c:/laragon/www/sistema/app/Services/LoginService.php)
- [Auth::signIn](/c:/laragon/www/sistema/app/Core/Auth.php)

Regras aplicadas:

- checagem de campos obrigatorios
- blacklist de input
- validacao real do reCAPTCHA no servidor
- throttle anti brute force por `guard + login + ip`
- resposta generica para nao facilitar enumeracao
- criacao de sessao em `auth_sessions`
- payload assinado em sessao/cookie

## Sessao autenticada

Cada login gera:

- `token` aleatorio com `random_bytes`
- `token_hash` salvo no banco
- payload assinado com HMAC

O sistema nunca confia apenas no cookie/sessao local.
Em cada request, `Auth::auth()` tambem confere:

- assinatura do payload
- existencia da sessao no banco
- `revoked = 0`
- timeout de inatividade
- timeout absoluto
- binding de user-agent
- binding de IP, conforme a politica

## Recuperacao de senha

### Habilitacao

O fluxo inteiro responde a:

- `guards.usuario.features.forgot_password`

Quando estiver `false`:

- o link nao aparece no login
- a tela de solicitacao nao abre
- o POST de envio nao executa
- a tela de redefinicao nao abre
- o POST final de troca nao executa

### Rotas dinamicas

As rotas saem do proprio guard:

- `forgot_password`
- `forgot_password_request`
- `reset_password`
- `reset_password_update`

Isso evita nome fixo de rota dentro do controller.

### Solicitar link

Fluxo:

1. o usuario informa o e-mail
2. o backend valida `csrf`, input e reCAPTCHA
3. o backend aplica rate limit dedicado de `password_request`
4. se existir usuario ativo com e-mail, um token novo e gerado
5. apenas o `token_hash` vai para a tabela `password_reset_tokens`
6. o token puro so vai no link enviado por e-mail
7. a resposta final continua generica para evitar enumeracao

### Redefinir senha

Fluxo:

1. o link recebido carrega a tela com o token puro
2. o backend converte o token em `sha256`
3. busca em `password_reset_tokens`
4. exige:
   - mesmo guard
   - `used_at` nulo
   - `expires_at` futuro
5. no POST final, valida senha minima, reCAPTCHA e rate limit `password_reset`
6. atualiza senha e rotaciona a coluna `token` do usuario
7. revoga todas as sessoes ativas do usuario
8. marca o token como usado
9. apaga outros tokens antigos do mesmo usuario

## Troca de senha com usuario autenticado

Fluxo:

- [Admin/UsuarioController::updatePass](/c:/laragon/www/sistema/app/Controllers/Admin/UsuarioController.php)

Regras aplicadas agora no backend:

- exige senha atual
- valida senha atual com hash real no servidor
- exige confirmacao igual
- respeita `security.password_policy.min_length`
- rotaciona a coluna `token`
- revoga todas as sessoes ativas
- executa logout da sessao atual

Isso evita depender apenas da validacao JS da tela.

## Rate limits

Hoje existem 2 camadas diferentes:

### Throttle de login

Configurado em:

- `security.throttle`

Escopo:

- `login + ip`
- `ip`

Uso:

- proteger tentativa de brute force no login

### Rate limits extras

Configurados em:

- `security.rate_limits.password_request`
- `security.rate_limits.password_reset`

Uso:

- limitar flood de e-mail de recuperacao
- limitar abuso do POST de redefinicao
- manter esses contadores separados do login

Os arquivos de cache desses controles sao descritos em:

- [cache-autenticacao.md](/c:/laragon/www/sistema/docs/cache-autenticacao.md)

## reCAPTCHA

Configuracao em:

- [config/recaptcha.php](/c:/laragon/www/sistema/config/recaptcha.php)

Ponto importante:

- o token continua sendo gerado no browser
- a validacao final acontece no servidor

Isso evita bypass por request manual sem o token validado.

## Checklist de robustez atual

Hoje a base ja cobre:

- guard por cenario
- rotas dinamicas por guard
- feature flag para recuperar senha
- sessoes por dispositivo em banco
- payload assinado
- revogacao por sessao
- revogacao global ao trocar/resetar senha
- throttle de login
- rate limit de recuperacao de senha
- reCAPTCHA validado no backend
- expiracao e uso unico de token de reset

## Pontos de manutencao futura

Se a base crescer, os proximos passos naturais seriam:

- auditar mais guards alem do admin usando o mesmo padrao
- mover o fluxo de recuperacao para um service dedicado
- adicionar notificacao de seguranca ao usuario quando a senha for alterada
- expor gerenciamento de sessoes ativas no painel

## Resumo

A base atual saiu de um login funcional para um fluxo de autenticacao mais completo:

- login protegido
- sessao auditavel
- recuperacao de senha controlada
- troca de senha com invalidacao de sessoes
- configuracao central suficiente para reaproveitamento
