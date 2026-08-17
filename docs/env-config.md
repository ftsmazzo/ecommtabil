# Variaveis de Ambiente

Esta base passou a suportar um arquivo `.env` simples e proprio, carregado no bootstrap antes das configuracoes da aplicacao.

## Objetivo

Separar o que e estrutural do que e sensivel ou muda por ambiente.

## Regra geral

- `config/*.php` continua sendo o local das regras, defaults e configuracoes estruturais
- `.env` fica para segredos e variacoes por ambiente

## Como funciona

O carregamento acontece em [App\Core\Bootstrap](/c:/laragon/www/sistema/app/Core/Bootstrap.php) atraves de [App\Core\Env](/c:/laragon/www/sistema/app/Core/Env.php).

Fluxo:

1. o bootstrap tenta ler `.env` na raiz do projeto
2. os valores carregados vao para `$_ENV`, `$_SERVER` e `putenv()`
3. o helper global `env()` passa a estar disponivel
4. os arquivos em `config/*.php` podem usar `env("CHAVE", $default)`

## Tipos aceitos

O loader faz cast simples de valores comuns:

- `true` e `false` viram boolean
- `null` vira `null`
- numeros viram `int` ou `float`
- strings entre aspas sao normalizadas

## Arquivos relacionados

- [app/Core/Env.php](/c:/laragon/www/sistema/app/Core/Env.php)
- [app/Core/Bootstrap.php](/c:/laragon/www/sistema/app/Core/Bootstrap.php)
- [public/libs/helpers.php](/c:/laragon/www/sistema/public/libs/helpers.php)
- [config/app.php](/c:/laragon/www/sistema/config/app.php)
- [config/database.php](/c:/laragon/www/sistema/config/database.php)
- [config/mail.php](/c:/laragon/www/sistema/config/mail.php)
- [config/recaptcha.php](/c:/laragon/www/sistema/config/recaptcha.php)
- [config/push.php](/c:/laragon/www/sistema/config/push.php)
- [config/cookie.php](/c:/laragon/www/sistema/config/cookie.php)
- [config/jwt.php](/c:/laragon/www/sistema/config/jwt.php)
- [config/auth.php](/c:/laragon/www/sistema/config/auth.php)

## Quando usar `.env`

Use `.env` para:

- credenciais de banco
- SMTP
- chaves privadas
- chaves publicas/secretas por ambiente
- nome da aplicacao
- timezone
- limites ou politicas que possam variar entre ambientes

## Quando usar `config/*.php`

Use `config/*.php` para:

- regras de negocio de infraestrutura
- rotas configuraveis
- permissões e features
- tabelas e colunas
- defaults da aplicacao

