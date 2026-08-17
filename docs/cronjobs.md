# Cronjobs do sistema

Tarefas agendadas que devem ser executadas diariamente pelo servidor de hospedagem.

---

## Endpoints disponíveis

| Nome | Método | URL |
|---|---|---|
| Vencimento de cartões | GET | `/cronjobs/cartoes/vencer` |
| Expiração de créditos | GET | `/cronjobs/creditos/expirar` |

Ambos retornam JSON e não exigem autenticação de usuário — o acesso é protegido por restrição de IP (ver seção [Segurança](#segurança)).

---

## 1. Vencimento de cartões

**Rota:** `GET /cronjobs/cartoes/vencer`
**Nome:** `cronjob.cartoes.vencer`
**Controller:** `App\Controllers\Servicos\CronjobController::vencerCartoes`

### O que faz

Varre todos os cartões que possuem `validade` preenchida e cujo vencimento é **anterior a hoje**. Para cada um que ainda não esteja com status `VENCIDO` ou `CANCELADO`, atualiza o status para `VENCIDO`.

### Regras

- Só altera cartões com `validade < hoje`
- Não reprocessa cartões já `VENCIDO`
- Não toca em cartões `CANCELADO`

### Resposta

```json
{
  "success": true,
  "date": "2026-05-01",
  "updated": 3
}
```

`updated` = quantidade de cartões alterados na execução.

### Frequência recomendada

**1× por dia**, preferencialmente às 00:05 (logo após a virada do dia).

### Exemplo de crontab (Linux)

```cron
5 0 * * * curl -s "https://seusite.com/cronjobs/cartoes/vencer" >> /var/log/cron-cartoes.log 2>&1
```

---

## 2. Expiração de créditos

**Rota:** `GET /cronjobs/creditos/expirar`
**Nome:** `cronjob.creditos.expirar`
**Controller:** `App\Controllers\Servicos\CronjobController::vencerCreditosExpirados`

### O que faz

Varre todas as movimentações de crédito (`sentido = CREDITO`) com `saldo_disponivel > 0` e `expira_em < hoje`. Para cada uma:

1. Zera o `saldo_disponivel` da movimentação de crédito original
2. Lança um débito do tipo `EXPIRACAO` no extrato do cartão, reduzindo o saldo
3. A movimentação de expiração referencia a movimentação de crédito original via `id_referencia`

### Dependência de configuração

Os créditos só terão `expira_em` preenchido se as configurações abaixo estiverem com valor > 0:

| Chave | Onde configurar | Efeito |
|---|---|---|
| `recarga_expira_dias` | Configurações do sistema | Validade das recargas (em dias) |
| `cashback_expira_dias` | Configurações do sistema | Validade dos cashbacks (em dias) |
| `fidelidade_expira_dias` | Configurações do sistema | Validade dos bônus de fidelidade (em dias) |

Se a chave estiver **vazia ou zero**, os créditos gerados daquele tipo **não expiram**.

### Resposta

```json
{
  "success": true,
  "date": "2026-05-01",
  "expired": 5
}
```

`expired` = quantidade de créditos expirados na execução.

### Frequência recomendada

**1× por dia**, preferencialmente às 00:10 — após o job de vencimento de cartões.

### Exemplo de crontab (Linux)

```cron
10 0 * * * curl -s "https://seusite.com/cronjobs/creditos/expirar" >> /var/log/cron-creditos.log 2>&1
```

---

## Checagem no hub do cartão

Além do cronjob diário, a expiração de créditos é checada **automaticamente** sempre que o hub do cartão é acessado (`/admin/cartoes/{id}/hub`). Isso garante que créditos vencidos sejam expirados em tempo real ao visualizar o cartão, sem depender exclusivamente da execução agendada.

---

## Segurança

O acesso aos endpoints é controlado por restrição de IP em `config/cronjob.php`:

```php
// config/cronjob.php
return [
    'allowed_ip' => '89.117.57.72', // IP do servidor de agendamento
];
```

A verificação no controller está preparada — para ativar, descomentar o bloco no `__construct` de `CronjobController`:

```php
// app/Controllers/Servicos/CronjobController.php
public function __construct()
{
    parent::__construct();

    $config = Config::get('cronjob');

    // Descomentar para restringir acesso por IP:
    if (!empty($config['allowed_ip']) && $config['allowed_ip'] != $_SERVER['REMOTE_ADDR']) {
        http_response_code(403);
        exit('Forbidden');
    }
}
```

> Em produção, recomenda-se ativar a restrição de IP e configurar o IP correto do provedor de agendamento (ex: EasyCron, cron-job.org, servidor próprio).

---

## Crontab completo recomendado

```cron
# Vencer cartões com validade expirada
5  0 * * * curl -s "https://seusite.com/cronjobs/cartoes/vencer"   >> /var/log/cron-cartoes.log   2>&1

# Expirar créditos vencidos
10 0 * * * curl -s "https://seusite.com/cronjobs/creditos/expirar" >> /var/log/cron-creditos.log  2>&1
```

---

## Testando manualmente

Acesse as URLs diretamente no navegador (com o IP liberado ou a restrição desativada):

```
https://seusite.com/cronjobs/cartoes/vencer
https://seusite.com/cronjobs/creditos/expirar
```

A resposta JSON confirma sucesso e a quantidade de registros processados. Se `updated` / `expired` for `0`, significa que não havia registros pendentes na data da execução — comportamento normal.
