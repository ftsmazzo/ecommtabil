# Cache da Autenticacao

Este documento explica os arquivos mais comuns criados em `storage/cache/` pelo sistema de autenticacao e pelos dados carregados junto com o usuario logado.

## Visao geral

Nem todo arquivo dessa pasta e "bug" ou "lixo".
Boa parte deles existe para:

- reduzir consultas repetidas ao banco
- acelerar carregamento de menu e preferencias
- controlar throttle de login
- registrar atividade recente da sessao

Em ambiente de desenvolvimento, e normal essa pasta crescer conforme voce faz login, logout e navega pelo painel.

## Arquivos mais comuns

### `preferences_usuario_1.txt`

Guarda as preferencias carregadas do usuario.

Exemplo:

- tema do painel, como `light` ou `dark`

Origem:

- `App\Services\UsuarioPreferenciaService`
- `App\Core\ControllerAdmin`
- `App\Core\ControllerCliente`

Observacao:

- se esse cache for gravado quando a preferencia ainda nao existe no banco, ele pode segurar um valor antigo ou `null`
- limpar esse arquivo faz o sistema buscar novamente a preferencia no banco

### `menu_admin_usuario_1.txt`

Cache do menu do painel administrativo para o usuario logado.

Origem:

- `App\Services\MenuService`

Serve para:

- evitar reconstruir o menu completo a cada request
- reutilizar estrutura de navegacao e permissoes ja processadas

Quando limpar:

- ao alterar permissoes
- ao mudar estrutura do menu
- quando um item novo nao aparece para o usuario

Limpeza:

- no logout, o cache do menu do usuario atual tambem e removido
- isso faz o menu ser montado do zero no proximo login

### `dcf_admin_usuario_permissoes_1.txt`

Cache das permissoes resolvidas do usuario.

Origem:

- `App\Core\Auth`

Serve para:

- evitar consultar permissao no banco em toda checagem de `allow()`

Quando limpar:

- ao trocar permissoes do usuario
- ao editar perfis/grupos de acesso

### `auth_last_activity_usuario_1_<hash>.txt`

Marca a ultima atividade recente de uma sessao autenticada.

Origem:

- `App\Core\Auth`

Serve para:

- reduzir escrita excessiva no banco em `auth_sessions.last_activity`
- controlar ping de atividade da sessao atual

Sobre o `<hash>`:

- ele representa o token da sessao autenticada
- por isso pode existir mais de um arquivo desse tipo para o mesmo usuario, em sessoes diferentes

Limpeza:

- no logout da sessao atual, o sistema remove automaticamente esse cache
- isso evita acumulo indefinido de arquivos por token em `storage/cache/`

### `authThrottle__login_ip__usuario__<hash>__count.txt`
### `authThrottle__login_ip__usuario__<hash>__lock_level.txt`
### `authThrottle__login_ip__usuario__<hash>__lock_until.txt`
### `authThrottle__login_ip__usuario__<hash>__start.txt`

Arquivos de throttle por combinacao de:

- guard
- login informado
- IP

Origem:

- `App\Core\Auth`

Servem para:

- limitar tentativas repetidas de login
- bloquear brute force
- aplicar bloqueio progressivo por tempo

Significado:

- `__count`: quantidade de tentativas
- `__lock_level`: nivel atual do bloqueio progressivo
- `__lock_until`: ate quando a chave esta bloqueada
- `__start`: inicio da janela de contagem

Limpeza:

- apos login bem-sucedido para aquela combinacao, esses arquivos sao removidos
- o sistema nao apenas zera o conteudo, ele apaga os artefatos do throttle
- a limpeza e restrita ao `login + ip` daquela tentativa, sem mexer na chave global por IP

### `authThrottle__ip__usuario__<hash>__count.txt`
### `authThrottle__ip__usuario__<hash>__lock_level.txt`
### `authThrottle__ip__usuario__<hash>__lock_until.txt`
### `authThrottle__ip__usuario__<hash>__start.txt`

Mesma ideia do bloco acima, mas controlando por IP, independentemente do login informado.

Serve para:

- impedir ataque distribuido em varios logins a partir do mesmo IP

Limpeza:

- essa chave e mantida ate expirar, porque pode representar tentativas de varios usuarios atras do mesmo IP

### `authRateLimit__password_request__subject_ip__usuario__<hash>__*.txt`
### `authRateLimit__password_request__ip__usuario__<hash>__*.txt`

Arquivos de rate limit do fluxo `esqueci minha senha`.

Origem:

- `App\Core\Auth`
- `App\Controllers\Admin\LoginController`

Servem para:

- limitar repeticao de solicitacoes por e-mail + IP
- limitar flood de solicitacoes por IP
- aplicar bloqueio progressivo mesmo quando o e-mail nao existe

Limpeza:

- quando o fluxo termina com sucesso, apenas a chave `subject_ip` daquela combinacao e removida
- a chave global por IP e mantida ate expirar para nao interferir em outros usuarios da mesma rede

### `authRateLimit__password_reset__subject_ip__usuario__<hash>__*.txt`
### `authRateLimit__password_reset__ip__usuario__<hash>__*.txt`

Arquivos de rate limit do fluxo de redefinicao por token.

Servem para:

- reduzir tentativa repetida de uso/forca sobre links de reset
- limitar abuso por IP no POST final da nova senha
- separar esse controle do throttle do login tradicional

## O que pode existir mais de uma vez

Estes arquivos podem se repetir com hashes diferentes sem ser erro:

- `auth_last_activity_*`
- `authThrottle__*`
- `authRateLimit__*`

Isso acontece porque o sistema diferencia:

- sessao/token
- IP
- login informado
- guard

## Quando limpar o cache

### Pode limpar com seguranca em desenvolvimento

- toda a pasta `storage/cache/`

Efeitos esperados:

- o sistema recompõe menu, preferencias e permissoes
- o throttle volta ao estado inicial
- o painel pode fazer mais consultas no primeiro carregamento

### Em producao, prefira limpar por motivo

- preferencia errada: limpar `preferences_usuario_*`
- menu desatualizado: limpar `menu_*`
- permissao desatualizada: limpar `*_permissoes_*`
- bloqueio de login indevido: limpar `authThrottle__*`

## Relacao com o banco

Esses caches nao substituem as tabelas principais.
Eles apenas evitam reprocessamento.

As fontes reais continuam sendo:

- `usuario`
- `usuario_preferencia`
- `auth_sessions`
- tabelas de permissao

## Resumo pratico

Se aparecer comportamento estranho apos mudar usuario, permissao ou tema:

1. limpar `storage/cache/`
2. fazer login novamente
3. testar de novo

Na sua base atual, isso e o procedimento mais simples e seguro para descartar inconsistencias de cache local.
