<?php
namespace App\Core;

use App\Core\Password;
use App\Core\Session;
use App\Core\Cookie;
use App\Core\Config;
use App\Core\Message;
use App\Core\Cache;
use App\Core\DB;
use App\Core\Jwt;
use App\Core\BasicAuth;
use App\Core\ApiKey;
use App\Services\AuthHistoricoService;

/**
 * Class Auth
 *
 * Módulo de autenticação com suporte a múltiplos guards, sessões por dispositivo
 * e políticas configuráveis de "check_online".
 *
 * Políticas para check_online (definidas em config/auth.php por guard):
 *  - 'deny'           : permite apenas uma sessão ativa por user+guard (revoga outras no login)
 *  - 'allow_other_ip' : permite múltiplas sessões em IPs diferentes; revoga se houver mudança de user-agent
 *  - 'allow_all'      : permite múltiplas sessões sem restrições automáticas
 *
 * Segurança implementada:
 *  - tokens por sessão com random_bytes + token_hash (SHA-256) armazenado na tabela auth_sessions
 *  - payload de sessão assinado com HMAC-SHA256 (sem serialize/unserialize)
 *  - session_regenerate_id(true) no login (mitiga Session Fixation)
 *  - comparação constante com hash_equals
 *  - revogação por sessão (campo revoked na tabela auth_sessions)
 *  - rate-limit/throttle (anti brute-force) com chaves hash-hmac (não expõe login/ip no cache)
 *  - timeouts (idle/absolute) e binding (UA/IP) via config/security
 *
 * Requisitos:
 *  - Config::get('cookie.cookie_secret') forte
 *  - Tabela `auth_sessions` com colunas:
 *      id, guard, user_id, token_hash, ip, user_agent, created_at, last_activity, revoked, extra_data (opcional)
 */
class Auth
{
    /** @var array */
    protected $config;

    /** @var string|null */
    protected $guard;

    /** @var string|null */
    protected $driver;

    /** @var string|null */
    protected $table;

    /** @var Session */
    protected $session;

    /** @var Cookie */
    protected $cookie;

    /** @var Message */
    protected $message;

    /** @var Redirect */
    protected $redirect;

    /** @var Cache */
    protected $cache;

    /** @var string|null */
    protected $id;

    /** @var string|null */
    protected $authname;

    /** @var string|null */
    protected $route_login;

    /** @var string|null */
    protected $route_signin;

    /** @var string|null */
    protected $route_logout;

    /** @var string|null */
    protected $route_redirect;

    /** @var array */
    protected $routes = [];

    /** @var array */
    protected $features = [];

    /** @var string|null */
    protected $check_online;

    /** @var bool */
    protected $history;

    /** @var string|null */
    protected $table_history;

    /** @var string|null */
    protected $mediapath;

    /** @var string|null */
    protected $name;

    /** @var string|false|null */
    protected $nickname;

    /** @var string|false|null */
    protected $photo;

    /** @var string|null */
    protected $login;

    /** @var string|null */
    protected $token;

    /** @var string|null */
    protected $password;

    /** @var string|false|null */
    protected $status;

    /** @var string|false|null */
    protected $validate;

    /** @var string|false|null */
    protected $permissao;

    /** @var array|false */
    protected $permissions;

    /** @var int|null */
    protected $erro;

    /** @var string|null */
    protected $type_login;

    /** @var string Nome da tabela de sessões */
    protected $authSessionsTable;

    /**
     * Construtor: inicializa dependências e carrega config/auth.php
     */
    public function __construct()
    {
        $this->session  = new Session();
        $this->cookie   = new Cookie();
        $this->message  = new Message();
        $this->redirect = new Redirect();
        $this->cache    = new Cache();

        $this->config = Config::get("auth");

        $this->authSessionsTable = $this->config["auth_sessions_table"] ?? "auth_sessions";
    }

    /**
     * Define o guard atual a partir da configuração.
     *
     * @param string $guard Nome do guard no config/auth.php
     * @throws \InvalidArgumentException se guard não existir
     */
    public function setGuard(string $guard): void
    {
        $guards = $this->config["guards"] ?? [];

        if (!isset($guards[$guard])) {
            throw new \InvalidArgumentException("❌ Guard '{$guard}' não encontrado em config/auth.php");
        }

        $this->guard = $guard;
        $guardConfig = $guards[$guard];

        $this->driver         = $guardConfig["driver"];
        $this->table          = $guardConfig["table"];
        $this->authname       = $guardConfig["authname"];
        $this->routes         = $guardConfig["routes"] ?? [];
        $this->features       = $guardConfig["features"] ?? [];
        $this->route_login    = $this->routes["login"] ?? null;
        $this->route_signin   = $this->routes["signin"] ?? null;
        $this->route_redirect = $this->routes["redirect"] ?? null;
        $this->route_logout   = $this->routes["logout"] ?? null;
        $this->check_online   = $guardConfig["check_online"];
        $this->history        = $guardConfig["history"];
        $this->table_history  = $guardConfig["table_history"];
        $this->mediapath      = $guardConfig["mediapath"];
        $this->type_login     = $guardConfig["types"]["login"];
        $this->permissions    = $guardConfig["permissions"] ?? false;

        $columns         = $guardConfig["columns"];
        $this->id        = $columns["id"];
        $this->login     = $columns["login"];
        $this->password  = $columns["password"];
        $this->token     = $columns["token"];
        $this->name      = $columns["name"];
        $this->nickname  = $columns["nickname"];
        $this->photo     = $columns["photo"];
        $this->status    = $columns["status"];
        $this->validate  = $columns["validate"];
        $this->permissao = $columns["permissao"];
    }

    /**
     * Retorna o guard atual.
     * @return string|null
     */
    public function getGuard()
    {
        return $this->guard;
    }

    /**
     * Realiza o processo de login (verifica credenciais, validações e registra a sessão).
     *
     * - Resposta genérica para mitigar enumeração (usuário inexistente vs senha incorreta)
     * - Throttle por guard + ip + login (via Cache)
     * - Armazena sessão em auth_sessions com token_hash (sha256)
     * - Assina payload em sessão/cookie com HMAC
     *
     * @param string $login
     * @param string $pass
     * @param int $remember (1 para lembrar)
     * @param bool $permissoes
     * @return array
     */
    public function signIn($login, $pass, $remember, $permissoes = true)
    {
        $this->requireGuard();

        // valida tipo de login (se for email)
        if ($this->type_login === "email") {
            if (Validator::make($login)->email()->validate() == false) {
                return [
                    "error" => true,
                    "message" => "Usuário/Senha inválidos",
                    "motivo" => "Login não é um e-mail válido",
                    "uid" => null
                ];
            }
        }

        $login = is_string($login) ? trim($login) : $login;
        $pass  = is_string($pass) ? trim($pass) : $pass;

        $login = filter_var($login, FILTER_DEFAULT);
        $pass  = filter_var($pass, FILTER_DEFAULT);

        if (!$login || !$pass) {
            return [
                "error" => true,
                "message" => "Digite seu usuário e senha",
                "motivo" => "Usuário/Senha ausentes",
                "uid" => null
            ];
        }

        // ✅ Rate-limit anti brute-force (por guard + IP + login)
        $th = $this->throttleCheck((string)$login);
        if (empty($th['ok'])) {
            return [
                "error" => true,
                "message" => "Muitas tentativas. Tente novamente em alguns minutos.",
                "motivo" => "Throttle ativo",
                "uid" => null
            ];
        }

        // Busca usuário
        $User = DB::table($this->table)->where($this->login, "=", $login)->first();

        // fake password (timing attack mitigation)
        $defaultPassword = Password::hash("1234567890");
        if ($User && !empty($User->{$this->password})) {
            $defaultPassword = $User->{$this->password};
        }

        $passwordOk = (new Password())->verify($pass, $defaultPassword);

        // se usuário não existe OU senha incorreta => resposta genérica
        if (!$User || !$passwordOk) {

            if ($this->history) {
                AuthHistoricoService::registrar($this->table_history, [
                    "id_usuario" => $User ? $User->{$this->id} : null,
                    "login"      => $login,
                    "acao"       => "login",
                    "status"     => "error",
                    "motivo"     => $User ? "Senha incorreta" : "Usuário inexistente",
                    "ip"         => getip(),
                    "sistema"    => ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
                ]);
            }

            $this->throttleHit((string)$login);

            return [
                "error" => true,
                "message" => "Usuário/Senha inválidos",
                "motivo" => "Credenciais inválidas",
                "uid" => $User ? $User->{$this->id} : null,
            ];
        }

        // ====== STATUS ======
        if ($this->status) {
            $active_val = $this->config["guards"][$this->guard]["types"]["status"]["active_val"];
            if ((string)$User->{$this->status} !== (string)$active_val) {

                if ($this->history) {
                    AuthHistoricoService::registrar($this->table_history, [
                        "id_usuario" => $User->{$this->id},
                        "login"      => $User->{$this->login},
                        "acao"       => "login",
                        "status"     => "error",
                        "motivo"     => "Usuário não ativo",
                        "ip"         => getip(),
                        "sistema"    => ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
                    ]);
                }

                $this->throttleHit((string)$login);

                return [
                    "error" => true,
                    "message" => 2,
                    "motivo" => "Usuário não ativo",
                    "uid" => $User->{$this->id}
                ];
            }
        }

        // ====== VALIDATE ======
        if ($this->validate) {
            $valid_val = $this->config["guards"][$this->guard]["types"]["validate"]["valid_val"];
            if ((string)$User->{$this->validate} !== (string)$valid_val) {

                if ($this->history) {
                    AuthHistoricoService::registrar($this->table_history, [
                        "id_usuario" => $User->{$this->id},
                        "login"      => $login,
                        "acao"       => "login",
                        "status"     => "error",
                        "motivo"     => "Cadastro não confirmado",
                        "ip"         => getip(),
                        "sistema"    => ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
                    ]);
                }

                $this->throttleHit((string)$login);

                return [
                    "error" => true,
                    "message" => 21,
                    "motivo" => "Cadastro não confirmado",
                    "uid" => $User->{$this->id},
                ];
            }
        }

        // ====== HISTORY SUCCESS ======
        if ($this->history) {
            AuthHistoricoService::registrar($this->table_history, [
                "id_usuario" => $User->{$this->id},
                "login"      => $login,
                "acao"       => "login",
                "status"     => "success",
                "ip"         => getip(),
                "sistema"    => ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
            ]);
        }

        // ====== SESSION DRIVER ======
        if ($this->driver === "session") {

            // sessão ativa + anti session fixation
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            session_regenerate_id(true);

            $uid = $User->{$this->id};

            // token por sessão
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);

            // policy deny: revoga sessões antigas ANTES de inserir a nova
            if (!empty($this->check_online) && $this->check_online === 'deny') {
                DB::table($this->authSessionsTable)
                    ->where('guard', '=', $this->guard)
                    ->where('user_id', '=', $uid)
                    ->update(['revoked' => 1]);
            }

            // registra sessão atual no banco
            DB::table($this->authSessionsTable)->insert([
                'guard'         => $this->guard,
                'user_id'       => $uid,
                'token_hash'    => $token_hash,
                'ip'            => getip(),
                'user_agent'    => ($_SERVER['HTTP_USER_AGENT'] ?? null),
                'extra_data'    => null,
                'revoked'       => 0,
                'created_at'    => date('Y-m-d H:i:s'),
                'last_activity' => date('Y-m-d H:i:s'),
            ]);

            // monta payload assinado
            $payload = [
                'uid'    => $uid,
                'id'     => $uid, // compat
                'guard'  => $this->guard,
                'logado' => true,
                'token'  => $token,
                'iat'    => time(),
            ];

            if ($permissoes && $this->permissao) {
                $payload['permissoes'] = $User->{$this->permissao} ?? null;
            }

            $signed = $this->signPayload($payload);

            // sessão sempre
            $this->session->set($this->authname, $signed);

            // cookie só se "manter conectado"
            if ((int)$remember === 1) {
                $this->cookie->set($this->authname, $signed);
            } else {
                $this->cookie->unset($this->authname);
            }

            // resolve permissões por usuário (cache + session)
            if ($permissoes && $this->permissao) {
                $this->setPermissoes($payload['permissoes']);
            }

            // ✅ sucesso: zera throttle
            $this->throttleClear((string)$login);

            return [
                "error" => false,
                "uid"   => $uid
            ];
        }

        return ["error" => false, "user" => $User];
    }

    /**
     * Registra de forma segura a sessão do usuário autenticado.
     * Cria registro em auth_sessions e define payload assinado em sessão/cookie.
     *
     * OBS:
     * - Mantido para compatibilidade (alguns pontos do seu sistema podem chamar register()).
     * - signIn() já faz login + registro; register() é um "atalho" para registrar sessão para um User já autenticado.
     *
     * @param object $User
     * @param int $remember
     * @param array|null $extraData
     * @return void
     */
    public function register($User, $remember = 0, $extraData = null)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_regenerate_id(true);

        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);

        $sessionRow = [
            'guard'         => $this->guard,
            'user_id'       => $User->{$this->id},
            'token_hash'    => $token_hash,
            'ip'            => getip(),
            'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'extra_data'    => $extraData ? json_encode($extraData, JSON_UNESCAPED_UNICODE) : null,
            'created_at'    => date('Y-m-d H:i:s'),
            'last_activity' => date('Y-m-d H:i:s'),
            'revoked'       => 0,
        ];

        $insert = DB::table($this->authSessionsTable)->insert($sessionRow);

        // Checar sessões simultâneas (deny)
        // (Seu DB wrapper pode retornar obj com ->id, int, bool... então tentamos extrair com segurança)
        $insertId = null;
        if (is_object($insert) && isset($insert->id)) $insertId = $insert->id;
        elseif (is_numeric($insert)) $insertId = (int)$insert;

        $this->checkOnline($User, $insertId);

        $payload = [
            'id'     => $User->{$this->id},
            'guard'  => $this->guard,
            'logado' => true,
            'token'  => $token,
        ];

        if ($this->permissao) {
            $permissoes = $User->{$this->permissao};
            $payload['permissoes'] = $permissoes;
            $this->setPermissoes($permissoes);
        }

        if ($extraData) {
            $payload['extra'] = $extraData;
        }

        $signed = $this->signPayload($payload);

        $this->session->set($this->authname, $signed);

        if ((int)$remember === 1) {
            $this->cookie->set($this->authname, $signed);
        } else {
            $this->cookie->unset($this->authname);
        }

        // Atualiza dados básicos do usuário (se existirem as colunas)
        $ups = [
            'current_ip' => getip(),
            'last_login' => date('Y-m-d H:i:s'),
        ];

        DB::table($this->table)
            ->where($this->id, '=', $User->{$this->id})
            ->update($ups);

        // cache local do extra_data
        if ($extraData) {
            $this->session->set($this->authname . '_extra_data', $extraData);
        }
    }

    /**
     * Registra uma linha de sessão na tabela auth_sessions.
     *
     * Útil para centralizar criação de sessão (evita duplicar array de colunas).
     * Retorna o resultado do insert do seu DB wrapper (obj/int/bool).
     *
     * @param object $User
     * @param string $token_hash sha256(token)
     * @param array|null $extraData
     * @return mixed
     */
    private function registerSessionRow($User, string $token_hash, ?array $extraData = null)
    {
        $sessionRow = [
            'guard'         => $this->guard,
            'user_id'       => $User->{$this->id},
            'token_hash'    => $token_hash,
            'ip'            => getip(),
            'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at'    => date('Y-m-d H:i:s'),
            'last_activity' => date('Y-m-d H:i:s'),
            'revoked'       => 0,
        ];

        if (!empty($extraData)) {
            $sessionRow['extra_data'] = json_encode($extraData, JSON_UNESCAPED_UNICODE);
        }

        return DB::table($this->authSessionsTable)->insert($sessionRow);
    }

    /**
     * Atualiza payload assinado (sessão/cookie) e sincroniza extra_data/last_activity no banco.
     *
     * - NÃO chama setGuard(null) (isso quebrava se $guard não fosse passado)
     * - Identifica user por uid ?? id (compatibilidade)
     * - Atualiza também last_activity
     *
     * @param array|null $extraData
     * @param string|null $guard
     * @return void
     */
    public function refresh($extraData = null, $guard = null)
    {
        // se vier guard, troca; se não vier, mantém o atual
        if ($guard !== null) {
            $this->setGuard($guard);
        } else {
            $this->requireGuard();
        }

        if (!$this->session->has($this->authname)) {
            return;
        }

        $signed  = $this->session->get($this->authname);
        $payload = $this->verifyPayload($signed);

        if (!$payload) {
            $this->logout();
            return;
        }

        // pega identificadores com compat
        $userId = $payload['uid'] ?? $payload['id'] ?? null;
        $token  = $payload['token'] ?? null;
        $g      = $payload['guard'] ?? $this->guard;

        if (!$userId || !$token) {
            $this->logout();
            return;
        }

        $token_hash = hash('sha256', $token);

        // Atualiza payload local
        if ($extraData !== null) {
            $payload['extra'] = $extraData;

            $signed = $this->signPayload($payload);
            $this->session->set($this->authname, $signed);

            if ($this->cookie->has($this->authname)) {
                $this->cookie->set($this->authname, $signed);
            }

            // cache local
            $this->session->set($this->authname . '_extra_data', $extraData);

            // Atualiza no banco
            DB::table($this->authSessionsTable)
                ->where('guard', '=', $g)
                ->where('user_id', '=', $userId)
                ->where('token_hash', '=', $token_hash)
                ->where('revoked', '=', 0)
                ->update([
                    'extra_data'    => json_encode($extraData, JSON_UNESCAPED_UNICODE),
                    'last_activity' => date('Y-m-d H:i:s'),
                ]);

        } else {
            // sem extra: só ping no banco
            DB::table($this->authSessionsTable)
                ->where('guard', '=', $g)
                ->where('user_id', '=', $userId)
                ->where('token_hash', '=', $token_hash)
                ->where('revoked', '=', 0)
                ->update(['last_activity' => date('Y-m-d H:i:s')]);
        }

        // recarrega user (mantém seu comportamento)
        $this->user();
    }

    /**
     * Renova o payload assinado no storage de sessão (server-side).
     * (Usado quando cookie existe e você quer rehidratar a sessão PHP.)
     *
     * @param array $data
     * @return void
     */
    private function renewSession($data)
    {
        $signed = $this->signPayload($data);
        $this->session->set($this->authname, $signed);

        if (isset($data['permissoes'])) {
            $this->setPermissoes($data['permissoes']);
        }
    }

    /**
     * Registra login social em sessão/cookie (payload mínimo).
     * Mantido como estava.
     *
     * @param object $user
     * @return void
     */
    public function registerSocial($user)
    {
        $payload = [
            'id'     => $user->uid,
            'guard'  => $this->guard,
            'logado' => true,
        ];

        $signed = $this->signPayload($payload);
        $this->session->set($this->authname, $signed);
        $this->cookie->set($this->authname, $signed);
    }

    /**
     * Autentica a sessão/cookie do usuário.
     *
     * Fluxo do driver "session":
     *  1) Lê signed payload (session > cookie), e restaura sessão a partir do cookie
     *  2) Verifica assinatura (HMAC)
     *  3) Valida token contra auth_sessions (revoked=0)
     *  4) Aplica timeouts (idle/absolute)
     *  5) Aplica binding (UA/IP)
     *  6) Atualiza last_activity (ping limitado via Cache)
     *
     * @return object|false
     */
    public function auth()
    {
        if (strtolower($this->driver) == "session") {

            if (!$this->session->has($this->authname) && !$this->cookie->has($this->authname)) {
                $this->erro = 5;
                return false;
            }

            // 1) pega signed payload (session > cookie)
            if ($this->session->has($this->authname)) {
                $signed = $this->session->get($this->authname);
            } else {
                $signed = $this->cookie->{$this->authname};

                // tenta restaurar sessão
                $payloadTmp = $this->verifyPayload($signed);
                if ($payloadTmp) {
                    $this->renewSession($payloadTmp);
                }
            }

            if (!$signed) return false;

            // 2) valida assinatura
            $payload = $this->verifyPayload($signed);
            if (!$payload) {
                $this->logout();
                return false;
            }

            // 3) valida campos mínimos
            if (empty($payload['token']) || empty($payload['id'])) {
                $this->logout();
                return false;
            }

            $token_hash      = hash('sha256', $payload['token']);
            $userIdentifier  = $payload['uid'] ?? $payload['id'];
            $guard           = $payload['guard'] ?? $this->guard;

            // 4) valida sessão no banco
            $sessionRow = DB::table($this->authSessionsTable)
                ->where('guard', '=', $guard)
                ->where('user_id', '=', $userIdentifier)
                ->where('token_hash', '=', $token_hash)
                ->where('revoked', '=', 0)
                ->first();

            if (!$sessionRow) {
                $this->logout();
                return false;
            }

            // =========================
            // TIMEOUTS (config)
            // =========================
            $idleTimeoutSec     = (int)$this->securityConfig('session.idle_timeout_sec', 1800);
            $absoluteTimeoutSec = (int)$this->securityConfig('session.absolute_timeout_sec', 28800);
            $pingSec            = (int)$this->securityConfig('session.activity_ping_sec', 60);

            $now      = time();
            $createdAt = !empty($sessionRow->created_at) ? strtotime($sessionRow->created_at) : null;
            $lastAct   = !empty($sessionRow->last_activity) ? strtotime($sessionRow->last_activity) : null;

            if ($absoluteTimeoutSec > 0 && $createdAt && ($now - $createdAt) > $absoluteTimeoutSec) {
                $this->revokeSessionRow($sessionRow->id, 'absolute_timeout');
                $this->logout();
                return false;
            }

            if ($idleTimeoutSec > 0 && $lastAct && ($now - $lastAct) > $idleTimeoutSec) {
                $this->revokeSessionRow($sessionRow->id, 'idle_timeout');
                $this->logout();
                return false;
            }

            // =========================
            // BINDING (UA/IP)
            // =========================
            $bindUA = (bool)$this->securityConfig('binding.bind_user_agent', true);
            $ipMode = (string)$this->securityConfig('binding.ip_mode', 'policy'); // policy|strict|off

            $currentIp = getip();
            $storedIp  = $sessionRow->ip ?? null;
            $storedUA  = $sessionRow->user_agent ?? null;
            $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? null;

            if ($bindUA && $storedUA && $currentUA && $storedUA !== $currentUA) {
                $this->revokeSessionRow($sessionRow->id, 'ua_mismatch');
                $this->logout();
                return false;
            }

            $mustCheckIp = false;
            if ($ipMode === 'strict') {
                $mustCheckIp = true;
            } elseif ($ipMode === 'off') {
                $mustCheckIp = false;
            } else {
                // policy
                if (!empty($this->check_online) && $this->check_online === 'deny') {
                    $mustCheckIp = true;
                }
            }

            if ($mustCheckIp && $storedIp && $currentIp && !$this->compareIp($storedIp, $currentIp)) {
                $this->revokeSessionRow($sessionRow->id, 'ip_mismatch');
                $this->logout();
                return false;
            }

            // =========================
            // PING last_activity (limitado)
            // =========================
            if ($pingSec > 0) {
                $pingKey  = "auth_last_activity_" . $guard . "_" . $userIdentifier . "_" . $token_hash;
                $lastPing = $this->cache->get($pingKey);

                if (!$lastPing || (int)$lastPing < ($now - $pingSec)) {
                    DB::table($this->authSessionsTable)
                        ->where('id', '=', $sessionRow->id)
                        ->update(['last_activity' => date('Y-m-d H:i:s')]);

                    $this->cache->set($pingKey, $now);
                }
            }

            return (object)$payload;
        }

        // --- drivers restantes (apikey/jwt/basic) ---
        if (strtolower($this->driver) == "apikey") {
            return ApiKey::validate();
        }

        $headers = getallheaders();
        if (!isset($headers["Authorization"])) return false;

        $parts = explode(" ", $headers["Authorization"], 2);
        if (count($parts) !== 2) return false;

        [$type, $token] = $parts;
        if (!$type || !$token) return false;

        if (strtolower($this->driver) == "jwt") {
            if (strtolower($type) !== "bearer") return false;
            return Jwt::validate($token) ? Jwt::decode($token) : false;
        }

        if (strtolower($this->driver) == "basic") {
            if (strtolower($type) !== "basic") return false;
            return BasicAuth::validate($token);
        }

        return false;
    }

    /**
     * Garante que o guard autenticado seja o esperado.
     * @param string $guard
     * @return void
     */
    public function checkGuard($guard)
    {
        $auth = $this->auth();

        if ($auth && $auth->guard !== $guard) {
            unauthorized();
        }
    }

    /**
     * Logout revogando sessão atual no banco e removendo session/cookie.
     *
     * - Revoga token_hash atual no banco (revoked=1)
     * - Remove chaves da sessão
     * - Remove cookie
     * - Limpa CSRF tokens (Csrf::clear)
     *
     * @return bool
     */
    public function logout()
    {
        $payload = null;

        if ($this->session->has($this->authname)) {

            $signed  = $this->session->get($this->authname);
            $payload = $this->verifyPayload($signed);

            if ($payload && isset($payload['token']) && (isset($payload['id']) || isset($payload['uid']))) {

                $token_hash      = hash('sha256', $payload['token']);
                $userIdentifier  = $payload['uid'] ?? $payload['id'];
                $guard           = $payload['guard'] ?? $this->guard;

                DB::table($this->authSessionsTable)
                    ->where('guard', '=', $guard)
                    ->where('user_id', '=', $userIdentifier)
                    ->where('token_hash', '=', $token_hash)
                    ->update(['revoked' => 1]);
            }
        }

        $this->clearCurrentSessionCaches($payload);

        $keys = [
            $this->authname . "__user",
            $this->authname . "__token_session",
            $this->authname . "_extra_data",
            $this->authname,
            "permissoes",
            "csrf"
        ];

        foreach ($keys as $key) {
            $this->session->unset($key);
        }

        $this->cookie->unset($this->authname);

        Csrf::clear();

        return true;
    }

    /**
     * Limpa caches vinculados apenas à sessão atual.
     *
     * O alvo principal é o ping de atividade por token (`auth_last_activity_*`),
     * que pode se acumular bastante em ambiente file cache. Também removemos
     * caches locais derivados da sessão atual quando existir contexto suficiente.
     *
     * @param array|false|null $payload
     * @return void
     */
    private function clearCurrentSessionCaches($payload = null): void
    {
        if (!$payload || empty($payload['token']) || empty($this->authname)) {
            return;
        }

        $guard = $payload['guard'] ?? $this->guard;
        $userIdentifier = $payload['uid'] ?? $payload['id'] ?? null;

        if (!$guard || !$userIdentifier) {
            return;
        }

        $tokenHash = hash('sha256', $payload['token']);

        // Limpa o marcador de ping da sessão/token atual.
        $this->cache->clear("auth_last_activity_" . $guard . "_" . $userIdentifier . "_" . $tokenHash);

        // O cache de permissões é por usuário, não por token, então ele não
        // cresce por logout. Ainda assim limpamos quando houver permissões no
        // payload para evitar resíduos após mudanças frequentes de perfil.
        if (array_key_exists('permissoes', $payload)) {
            $this->cache->clear($this->authname . "_permissoes_" . $userIdentifier);
        }

        // O menu cacheado por usuário deve ser reconstruído no próximo login
        // para refletir mudanças recentes de permissão sem depender de limpeza manual.
        $this->cache->clear("menu_admin_usuario_" . $userIdentifier);
    }

    /**
     * Retorna o usuário autenticado (dados do banco) e injeta:
     *  - uid/login/token/name/nickname/avatar
     *  - extra_data (da sessão atual em auth_sessions.extra_data)
     *  - campos de $auth->extra (se tiver) no objeto do usuário
     *
     * @return object|null
     */
    public function user()
    {
        $auth = $this->auth();

        if (!$auth) {
            return null;
        }

        $fields = array_filter([
            $this->id,
            $this->login,
            $this->token,
            $this->name,
            $this->nickname,
            $this->photo,
            $this->permissao,
        ]);

        $User = DB::table($this->table)
            ->select($fields)
            ->where($this->id, '=', $auth->id)
            ->first();

        if (!$User) {
            $this->logout();
            return null;
        }

        $User->uid      = $User->{$this->id};
        $User->login    = $User->{$this->login};
        $User->token    = $User->{$this->token};
        $User->name     = $User->{$this->name};
        $User->nickname = $this->nickname ? $User->{$this->nickname} : false;
        $User->avatar   = !empty($User->{$this->photo})
            ? storage() . "/media/" . $this->mediapath . "/" . $User->{$this->photo}
            : asset("common/images/no-photo.png");

        $User->extra_data = $this->getSessionExtraData($auth);

        if (isset($auth->extra) && is_array($auth->extra)) {
            foreach ($auth->extra as $key => $value) {
                $User->{$key} = $value;
            }
        }

        unset(
            $User->{$this->id},
            $User->{$this->name},
            $User->{$this->nickname},
            $User->{$this->photo},
            $User->{$this->password},
            $User->{$this->permissao}
        );

        return $User;
    }

    /**
     * Atualiza extra_data da sessão ativa (no banco e cache local).
     * @param array $data
     * @param string|null $guard
     * @return bool
     */
    public function setExtraData(array $data, ?string $guard = null): bool
    {
        $guard = $guard ?? $this->guard;

        if (!$guard) {
            throw new \InvalidArgumentException("Guard não definido ao definir extra_data");
        }

        $this->setGuard($guard);

        if (!$this->session->has($this->authname)) {
            return false;
        }

        $signed  = $this->session->get($this->authname);
        $payload = $this->verifyPayload($signed);

        if (!$payload || empty($payload['id']) || empty($payload['token'])) {
            return false;
        }

        $token_hash = hash('sha256', $payload['token']);

        $updated = DB::table($this->authSessionsTable)
            ->where('guard', '=', ($payload['guard'] ?? $this->guard))
            ->where('user_id', '=', ($payload['uid'] ?? $payload['id']))
            ->where('token_hash', '=', $token_hash)
            ->where('revoked', '=', 0)
            ->update(['extra_data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);

        $cacheKey = $this->authname . '_extra_data';
        $this->session->set($cacheKey, $data);

        return (bool)$updated;
    }

    /**
     * Busca extra_data da sessão atual (com cache local em Session).
     *
     * @param object|null $auth
     * @return array|null
     */
    public function getSessionExtraData($auth = null)
    {
        if (!$auth) {
            $auth = $this->auth();
        }

        if (!$auth || !isset($auth->token) || !isset($auth->id)) {
            return null;
        }

        if ($this->session->has($this->authname . '_extra_data')) {
            $cached = $this->session->get($this->authname . '_extra_data');
            if (!empty($cached)) {
                return is_string($cached) ? json_decode($cached, true) : $cached;
            }
        }

        $token_hash     = hash('sha256', $auth->token);
        $userIdentifier = $auth->uid ?? $auth->id;

        $sessionRow = DB::table($this->authSessionsTable)
            ->select('extra_data')
            ->where('guard', '=', ($auth->guard ?? $this->guard))
            ->where('user_id', '=', $userIdentifier)
            ->where('token_hash', '=', $token_hash)
            ->where('revoked', '=', 0)
            ->first();

        if (!$sessionRow || empty($sessionRow->extra_data)) {
            return null;
        }

        $extraData = is_string($sessionRow->extra_data)
            ? json_decode($sessionRow->extra_data, true)
            : $sessionRow->extra_data;

        $this->session->set($this->authname . '_extra_data', $extraData);

        return $extraData;
    }

    /**
     * Retorna extra_data associado à sessão atual.
     *
     * ✅ Correção importante:
     * - Antes: chamava setGuard($guard) mesmo quando $guard era null, o que quebraria.
     * - Agora: só troca guard quando vier explicitamente; caso contrário mantém o atual.
     *
     * @param string|null $guard
     * @param bool $forceReload
     * @return array|null
     */
    public function getExtraData($guard = null, $forceReload = false)
    {
        if ($guard !== null) {
            $this->setGuard($guard);
        } else {
            $this->requireGuard();
        }

        if (!$this->session->has($this->authname)) {
            return null;
        }

        $cacheKey = $this->authname . '_extra_data';

        if (!$forceReload && $this->session->has($cacheKey)) {
            return $this->session->get($cacheKey);
        }

        $signed  = $this->session->get($this->authname);
        $payload = $this->verifyPayload($signed);

        if (!$payload || empty($payload['id']) || empty($payload['token'])) {
            return null;
        }

        $token_hash     = hash('sha256', $payload['token']);
        $userIdentifier = $payload['uid'] ?? $payload['id'];
        $guardReal      = $payload['guard'] ?? $this->guard;

        $sessionData = DB::table($this->authSessionsTable)
            ->select('extra_data')
            ->where('guard', '=', $guardReal)
            ->where('user_id', '=', $userIdentifier)
            ->where('token_hash', '=', $token_hash)
            ->where('revoked', '=', 0)
            ->first();

        if ($sessionData && !empty($sessionData->extra_data)) {
            $extraData = json_decode($sessionData->extra_data, true);
            $this->session->set($cacheKey, $extraData);
            return $extraData;
        }

        return null;
    }

    /**
     * Retorna permissões resolvidas (via cache/session).
     * @return array|false
     */
    public function permissions()
    {
        $session = $this->auth();
        if (!$session || !isset($session->permissoes)) return false;

        $uid = $session->uid ?? $session->id;
        $key = $this->authname . "_permissoes_" . $uid;

        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        if ($this->session->has($key)) {
            return $this->session->get($key);
        }

        $this->setPermissoes($session->permissoes);

        if ($this->cache->has($key)) return $this->cache->get($key);
        return $this->session->get($key) ?? [];
    }

    /**
     * Checa se usuário tem permissão $name.
     * @param string $name
     * @param string|null $redirect
     * @return bool
     */
    public function allow($name, $redirect = null)
    {
        if (!$this->session->has($this->authname)) return false;

        $raw     = $this->session->get($this->authname);
        $payload = $this->verifyPayload($raw);

        if (!$payload || !isset($payload['permissoes'])) {
            if ($redirect) { $this->message->flash(7); $this->redirect->go($redirect); }
            return false;
        }

        $uid = $payload['uid'] ?? $payload['id'];
        $key = $this->authname . "_permissoes_" . $uid;

        if ($this->cache->has($key)) {
            $permissoes = $this->cache->get($key);
        } elseif ($this->session->has($key)) {
            $permissoes = $this->session->get($key);
        } else {
            $this->setPermissoes($payload['permissoes']);
            $permissoes = $this->cache->has($key) ? $this->cache->get($key) : $this->session->get($key);
        }

        if (in_array($name, (array)$permissoes, true)) return true;

        if ($redirect) { $this->message->flash(7); $this->redirect->go($redirect); }
        return false;
    }

    /**
     * Resolve permissões e guarda em cache/session (sem redis).
     *
     * Regras:
     * - Se ids vazios ou config permissions ausente => grava [] para evitar consultar tabela inteira
     * - Grava tanto no Cache quanto na Session para compatibilidade (independe do engine)
     *
     * @param array|string $permissions
     * @return void
     */
    private function setPermissoes($permissions)
    {
        $session = $this->auth();
        $uid = $session->uid ?? $session->id ?? null;

        $key = $this->authname . "_permissoes_" . $uid;

        $ids = $this->normalizePermissionIds($permissions);

        if (empty($ids) || !$this->permissions) {
            $this->cache->set($key, []);
            $this->session->set($key, []);
            return;
        }

        $permissoes = DB::table($this->permissions["table"])
            ->whereIn("id", $ids)
            ->pluck($this->permissions["column"]);

        $this->cache->set($key, $permissoes);
        $this->session->set($key, $permissoes);
    }

    /**
     * Renova payload (cookie+sessão) com novas permissões.
     * @param array|string $permissions
     * @return void
     */
    public function renew($permissions)
    {
        $user = $this->user();
        $auth = $this->auth();

        $payload = [
            'id'        => $user->uid,
            'uid'       => $user->uid,
            'guard'     => $this->guard,
            'logado'    => true,
            'permissoes'=> $permissions,
            'token'     => $auth->token ?? null,
        ];

        $signed = $this->signPayload($payload);

        if ($this->cookie->has($this->authname)) {
            $this->cookie->set($this->authname, $signed);
        }

        $this->session->set($this->authname, $signed);
        $this->setPermissoes($permissions);
    }

    /**
     * Checa se o token da sessão do usuário ainda existe no banco (revoked=0).
     * @param object|null $user
     * @return bool
     */
    public function checkToken($user = null)
    {
        if (!isset($user)) return false;

        $signed  = $this->session->get($this->authname) ?? null;
        $payload = $this->verifyPayload($signed);

        if (!$payload || !isset($payload['token'])) {
            return false;
        }

        $token_hash = hash('sha256', $payload['token']);

        $sessionRow = DB::table($this->authSessionsTable)
            ->where('guard', '=', ($payload['guard'] ?? $this->guard))
            ->where('user_id', '=', ($payload['uid'] ?? $payload['id']))
            ->where('token_hash', '=', $token_hash)
            ->where('revoked', '=', 0)
            ->first();

        if (!$sessionRow) {
            if ($this->history) {
                $hist = [
                    "id_usuario" => $user->uid,
                    "login"      => $user->{$this->login} ?? null,
                    "acao"       => "logout",
                    "status"     => "error",
                    "motivo"     => "Token de sessão não correspondente",
                ];
                DB::table($this->table_history)->insert($hist);
            }

            return false;
        }

        return true;
    }

    // --- signing/verifying payloads ---------------------------------------------------------

    /**
     * Retorna o segredo usado para assinar payload.
     * @return string
     */
    private function getSecret()
    {
        $secret = Config::get('cookie.cookie_secret');
        if (empty($secret)) {
            // fallback (funciona, mas recomendo setar cookie_secret no config/.env)
            $secret = hash('sha256', SECRET_KEY ?? 'default_app_key');
        }
        return $secret;
    }

    /**
     * Assina payload: base64(json) + "." + hmac_sha256(json, secret)
     *
     * Observação:
     * - Mantido no formato atual para não quebrar cookies antigos.
     *
     * @param array $payload
     * @return string
     */
    private function signPayload(array $payload)
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $sig  = hash_hmac('sha256', $json, $this->getSecret());
        return base64_encode($json) . '.' . $sig;
    }

    /**
     * Verifica assinatura do payload.
     *
     * @param string|null $signed
     * @return array|false
     */
    private function verifyPayload($signed)
    {
        if (!$signed || !is_string($signed)) return false;

        $parts = explode('.', $signed, 2);
        if (count($parts) !== 2) return false;

        $json = base64_decode($parts[0], true);
        if ($json === false || $json === '') return false;

        $sig = $parts[1];
        if (!is_string($sig) || $sig === '') return false;

        $calc = hash_hmac('sha256', $json, $this->getSecret());
        if (!hash_equals($calc, $sig)) return false;

        $payload = json_decode($json, true);
        return is_array($payload) ? $payload : false;
    }

    // --- sessões (admin) -------------------------------------------------------------------

    /**
     * Revoga outras sessões do mesmo user/guard (opcionalmente preservando token_hash).
     * @param int|string $userId
     * @param string|null $exceptTokenHash
     * @return void
     */
    private function revokeOtherSessions($userId, $exceptTokenHash = null)
    {
        $query = DB::table($this->authSessionsTable)
            ->where('guard', '=', $this->guard)
            ->where('user_id', '=', $userId)
            ->where('revoked', '=', 0);

        if ($exceptTokenHash) {
            $query = $query->where('token_hash', '!=', $exceptTokenHash);
        }

        $query->update(['revoked' => 1]);
    }

    /**
     * Revoga uma sessão específica por id e opcionalmente registra no histórico.
     * @param int $sessionId
     * @param string|null $reason
     * @return void
     */
    private function revokeSessionRow($sessionId, $reason = null)
    {
        DB::table($this->authSessionsTable)
            ->where('id', '=', $sessionId)
            ->update(['revoked' => 1]);

        if ($this->history) {
            DB::table($this->table_history)->insert([
                'id_usuario' => null,
                'login'      => null,
                'acao'       => 'session_revoked',
                'status'     => 'info',
                'motivo'     => $reason ?? 'manual',
            ]);
        }
    }

    /**
     * Lista sessões ativas do usuário para o guard atual.
     * @param int|string|null $userId
     * @return array
     */
    public function listSessions($userId = null)
    {
        $userId = $userId ?: ($this->user() ? $this->user()->uid : null);
        if (!$userId) return [];

        return DB::table($this->authSessionsTable)
            ->where('guard', '=', $this->guard)
            ->where('user_id', '=', $userId)
            ->where('revoked', '=', 0)
            ->get();
    }

    /**
     * Revoga uma sessão por id (aplicável ao guard atual).
     * @param int $sessionId
     * @return void
     */
    public function revokeSessionById($sessionId)
    {
        DB::table($this->authSessionsTable)
            ->where('id', '=', $sessionId)
            ->where('guard', '=', $this->guard)
            ->update([
                'revoked'       => 1,
                'last_activity' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Revoga todas as sessões ativas de um usuário no guard atual.
     *
     * Útil quando a senha é alterada ou redefinida, pois invalida imediatamente
     * sessões antigas que ainda estavam válidas em outros dispositivos.
     *
     * @param int|string|null $userId
     * @param bool $keepCurrent
     * @param string|null $reason
     * @return void
     */
    public function revokeAllSessionsForUser($userId = null, bool $keepCurrent = false, ?string $reason = null): void
    {
        $userId = $userId ?: ($this->user() ? $this->user()->uid : null);
        if (!$userId) {
            return;
        }

        $exceptTokenHash = null;

        if ($keepCurrent) {
            $signed = $this->session->get($this->authname) ?? null;
            $payload = $this->verifyPayload($signed);

            if (
                $payload
                && isset($payload['token'])
                && (string)($payload['uid'] ?? $payload['id'] ?? '') === (string)$userId
                && ($payload['guard'] ?? $this->guard) === $this->guard
            ) {
                $exceptTokenHash = hash('sha256', $payload['token']);
            }
        }

        $query = DB::table($this->authSessionsTable)
            ->where('guard', '=', $this->guard)
            ->where('user_id', '=', $userId)
            ->where('revoked', '=', 0);

        if ($exceptTokenHash) {
            $query = $query->where('token_hash', '!=', $exceptTokenHash);
        }

        $query->update([
            'revoked' => 1,
            'last_activity' => date('Y-m-d H:i:s'),
        ]);

        if ($this->history) {
            DB::table($this->table_history)->insert([
                'id_usuario' => $userId,
                'login'      => null,
                'acao'       => 'session_revoked',
                'status'     => 'success',
                'motivo'     => $reason ?? 'manual_revoke_all',
                'ip'         => getip(),
                'sistema'    => ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
            ]);
        }
    }

    /**
     * Aplica a política de controle de sessões simultâneas ("check_online").
     *
     * Quando a política for 'deny', revoga sessões anteriores (id < $sessionId) do mesmo user/guard.
     *
     * @param object $User
     * @param int|null $sessionId
     * @return void
     */
    private function checkOnline($User, $sessionId)
    {
        if (!$sessionId) return;

        if (!empty($this->check_online) && $this->check_online === 'deny') {
            DB::table($this->authSessionsTable)
                ->where('guard', '=', $this->guard)
                ->where('user_id', '=', $User->{$this->id})
                ->where('revoked', '=', 0)
                ->where('id', '<', $sessionId)
                ->update(['revoked' => 1]);

            if ($this->history) {
                DB::table($this->table_history)->insert([
                    'id_usuario' => $User->{$this->id},
                    'login'      => $User->{$this->login},
                    'acao'       => 'session_revoked',
                    'status'     => 'success',
                    'motivo'     => 'deny_policy_new_session',
                ]);
            }
        }
    }

    /**
     * Compara IPs de forma tolerante:
     * - IPv4: compara /24 (primeiros 3 octetos)
     * - IPv6: comparação literal (pode ajustar no futuro se quiser comparar prefixo)
     *
     * @param string $a
     * @param string $b
     * @return bool
     */
    private function compareIp($a, $b)
    {
        if (filter_var($a, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($b, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $oa = explode('.', $a);
            $ob = explode('.', $b);
            return ($oa[0] === $ob[0] && $oa[1] === $ob[1] && $oa[2] === $ob[2]);
        }

        return $a === $b;
    }

    /**
     * Garante que existe guard selecionado antes de executar ações.
     * @return void
     * @throws \LogicException
     */
    private function requireGuard(): void
    {
        if (empty($this->guard)) {
            throw new \LogicException("Auth: Nenhum guard definido. Use setGuard('usuario') antes de chamar este método.");
        }
    }

    /**
     * Normaliza ids de permissão:
     * - aceita array
     * - aceita JSON string
     * - trata double-encoded JSON
     *
     * @param mixed $value
     * @return array
     */
    private function normalizePermissionIds($value): array
    {
        if (is_array($value)) {
            $arr = $value;
        } else {
            $arr = json_decode($value, true);
            if (is_string($arr)) {
                $arr = json_decode($arr, true);
            }
        }

        if (!is_array($arr)) return [];

        $arr = array_values(array_unique(array_filter($arr, fn($x) => $x !== '' && $x !== null)));

        return $arr;
    }

    // =====================================================
    // SECURITY CONFIG helper (você usa no auth())
    // =====================================================

    /**
     * Lê config security em formato "a.b.c".
     * @param string $path
     * @param mixed $default
     * @return mixed
     */
    private function securityConfig(string $path, $default = null)
    {
        $sec = $this->config['security'] ?? [];
        $parts = explode('.', $path);

        $cur = $sec;
        foreach ($parts as $p) {
            if (!is_array($cur) || !array_key_exists($p, $cur)) {
                return $default;
            }
            $cur = $cur[$p];
        }

        return $cur ?? $default;
    }

    // =====================================================
    // THROTTLE (anti brute force)
    // =====================================================

    /**
     * Checa se login/ip estão bloqueados ou estouraram limite dentro da janela.
     * @param string $login
     * @return array{ok:bool,retry_in?:int,reason?:string}
     */
    private function throttleCheck(string $login): array
    {
        $sec = $this->config['security']['throttle'] ?? [];
        if (empty($sec['enabled'])) {
            return ['ok' => true];
        }

        $cfg = $this->throttleCfg();

        $ip  = getip() ?: '0.0.0.0';
        $now = time();

        $kLoginIp = $this->throttleKey('login_ip', $login, $ip);
        $kIp      = $this->throttleKey('ip', null, $ip);

        $lockLoginIp = (int)($this->cache->get($kLoginIp . '__lock_until') ?? 0);
        $lockIp      = (int)($this->cache->get($kIp . '__lock_until') ?? 0);

        if ($lockLoginIp > $now || $lockIp > $now) {
            $retryIn = max($lockLoginIp, $lockIp) - $now;
            return [
                'ok' => false,
                'retry_in' => max(1, $retryIn),
                'reason' => 'locked'
            ];
        }

        $countLoginIp = (int)($this->cache->get($kLoginIp . '__count') ?? 0);
        $countIp      = (int)($this->cache->get($kIp . '__count') ?? 0);

        if ($countLoginIp >= (int)$cfg['max_login_ip'] || $countIp >= (int)$cfg['max_ip']) {
            $until = $now + (int)$cfg['base_lock_sec'];

            $this->cache->set($kLoginIp . '__lock_until', $until);
            $this->cache->set($kIp . '__lock_until', $until);

            return [
                'ok' => false,
                'retry_in' => (int)$cfg['base_lock_sec'],
                'reason' => 'limit'
            ];
        }

        return ['ok' => true];
    }

    /**
     * Incrementa contadores e aplica lock progressivo ao estourar limites.
     * @param string $login
     * @return void
     */
    private function throttleHit(string $login): void
    {
        $sec = $this->config['security']['throttle'] ?? [];
        if (empty($sec['enabled'])) {
            return;
        }

        $cfg = $this->throttleCfg();

        $ip  = getip() ?: '0.0.0.0';
        $now = time();

        $kLoginIp = $this->throttleKey('login_ip', $login, $ip);
        $kIp      = $this->throttleKey('ip', null, $ip);

        // incrementa contadores
        $countLoginIp = (int)($this->cache->get($kLoginIp . '__count') ?? 0) + 1;
        $countIp      = (int)($this->cache->get($kIp . '__count') ?? 0) + 1;

        $this->cache->set($kLoginIp . '__count', $countLoginIp);
        $this->cache->set($kIp . '__count', $countIp);

        // janela
        $this->throttleWindowRoll($kLoginIp, (int)$cfg['window_sec']);
        $this->throttleWindowRoll($kIp, (int)$cfg['window_sec']);

        $limitAtingido = ($countLoginIp >= (int)$cfg['max_login_ip']) || ($countIp >= (int)$cfg['max_ip']);
        if (!$limitAtingido) {
            return;
        }

        // ✅ só escala lock_level se NÃO existe lock ativo (evita subir infinito no mesmo lock)
        $curLockLoginIp = (int)($this->cache->get($kLoginIp . '__lock_until') ?? 0);
        $curLockIp      = (int)($this->cache->get($kIp . '__lock_until') ?? 0);
        $lockAtivo = ($curLockLoginIp > $now) || ($curLockIp > $now);

        $lvlLoginIp = (int)($this->cache->get($kLoginIp . '__lock_level') ?? 0);
        $lvlIp      = (int)($this->cache->get($kIp . '__lock_level') ?? 0);

        $lvl = $lockAtivo ? max($lvlLoginIp, $lvlIp) : (max($lvlLoginIp, $lvlIp) + 1);

        $base = (int)$cfg['base_lock_sec'];
        $max  = (int)$cfg['max_lock_sec'];

        $duration = (int)min($max, $base * (2 ** max(0, $lvl - 1)));
        $until    = $now + $duration;

        $this->cache->set($kLoginIp . '__lock_until', $until);
        $this->cache->set($kIp . '__lock_until', $until);

        $this->cache->set($kLoginIp . '__lock_level', $lvl);
        $this->cache->set($kIp . '__lock_level', $lvl);
    }

    /**
     * Zera contadores/locks de throttle ao autenticar com sucesso.
     * @param string $login
     * @return void
     */
    private function throttleClear(string $login): void
    {
        $sec = $this->config['security']['throttle'] ?? [];
        if (empty($sec['enabled'])) return;

        $ip = getip() ?: '0.0.0.0';

        $kLoginIp = $this->throttleKey('login_ip', $login, $ip);

        // Limpa apenas a chave ligada ao login+ip informado.
        // A chave global por IP pode ser compartilhada entre usuarios da mesma
        // rede/NAT e nao deve ser removida por um unico login bem-sucedido.
        $this->clearRateLimitCacheKeySet($kLoginIp);
    }

    /**
     * Monta chave de throttle com HMAC para não expor login/ip em texto puro.
     * @param string $type
     * @param string|null $login
     * @param string|null $ip
     * @return string
     */
    private function throttleKey(string $type, ?string $login, ?string $ip): string
    {
        $sec  = $this->config['security']['throttle'] ?? [];
        $salt = (string)($sec['ip_hash_salt'] ?? 'change_me');

        $guard = (string)($this->guard ?? 'default');
        $guardSafe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $guard);

        $loginNorm = $login !== null ? mb_strtolower(trim($login), 'UTF-8') : '';
        $ipNorm    = $ip !== null ? trim($ip) : '';

        $raw  = $type . '|' . $guard . '|' . $loginNorm . '|' . $ipNorm;
        $hash = hash_hmac('sha256', $raw, $salt);

        return "authThrottle__{$type}__{$guardSafe}__{$hash}";
    }

    /**
     * Carrega config efetiva do throttle com override por guard.
     * @return array
     */
    private function throttleCfg(): array
    {
        $sec = $this->config['security']['throttle'] ?? [];

        $cfg = [
            'window_sec'     => (int)($sec['window_sec'] ?? 600),
            'max_login_ip'   => (int)($sec['max_login_ip'] ?? 8),
            'max_ip'         => (int)($sec['max_ip'] ?? 25),
            'base_lock_sec'  => (int)($sec['base_lock_sec'] ?? 600),
            'max_lock_sec'   => (int)($sec['max_lock_sec'] ?? 3600),
        ];

        $g = $this->guard ?? null;
        if ($g && !empty($sec['guards'][$g]) && is_array($sec['guards'][$g])) {
            $ov = $sec['guards'][$g];
            foreach ($cfg as $k => $v) {
                if (isset($ov[$k])) {
                    $cfg[$k] = (int)$ov[$k];
                }
            }
        }

        $cfg['window_sec']     = max(30, $cfg['window_sec']);
        $cfg['base_lock_sec']  = max(30, $cfg['base_lock_sec']);
        $cfg['max_lock_sec']   = max($cfg['base_lock_sec'], $cfg['max_lock_sec']);

        return $cfg;
    }

    /**
     * Implementa janela por timestamp (porque seu Cache tem TTL global).
     * @param string $baseKey
     * @param int $windowSec
     * @return void
     */
    private function throttleWindowRoll(string $baseKey, int $windowSec): void
    {
        $now = time();

        $kStart = $baseKey . '__start';
        $start  = (int)($this->cache->get($kStart) ?? 0);

        if ($start <= 0) {
            $this->cache->set($kStart, $now);
            return;
        }

        if (($now - $start) > $windowSec) {
            $this->cache->set($baseKey . '__count', 0);
            $this->cache->set($kStart, $now);
        }
    }

    /**
     * Checa um rate limit genérico configurado em security.rate_limits.<scope>.
     *
     * Esse helper cobre fluxos sensíveis fora do login, como recuperação e reset
     * de senha, sem misturar essas tentativas com o throttle de autenticação.
     *
     * @param string $scope
     * @param string|null $subject
     * @return array{ok:bool,retry_in?:int,reason?:string}
     */
    public function checkRateLimit(string $scope, ?string $subject = null): array
    {
        $cfg = $this->rateLimitCfg($scope);
        if (!$cfg || empty($cfg['enabled'])) {
            return ['ok' => true];
        }

        $ip = getip() ?: '0.0.0.0';
        $now = time();

        $kSubjectIp = $this->rateLimitKey($scope, 'subject_ip', $subject, $ip);
        $kIp = $this->rateLimitKey($scope, 'ip', null, $ip);

        $lockSubjectIp = (int)($this->cache->get($kSubjectIp . '__lock_until') ?? 0);
        $lockIp = (int)($this->cache->get($kIp . '__lock_until') ?? 0);

        if ($lockSubjectIp > $now || $lockIp > $now) {
            $retryIn = max($lockSubjectIp, $lockIp) - $now;
            return [
                'ok' => false,
                'retry_in' => max(1, $retryIn),
                'reason' => 'locked',
            ];
        }

        $countSubjectIp = (int)($this->cache->get($kSubjectIp . '__count') ?? 0);
        $countIp = (int)($this->cache->get($kIp . '__count') ?? 0);

        if ($countSubjectIp >= (int)$cfg['max_subject_ip'] || $countIp >= (int)$cfg['max_ip']) {
            $until = $now + (int)$cfg['base_lock_sec'];

            $this->cache->set($kSubjectIp . '__lock_until', $until);
            $this->cache->set($kIp . '__lock_until', $until);

            return [
                'ok' => false,
                'retry_in' => (int)$cfg['base_lock_sec'],
                'reason' => 'limit',
            ];
        }

        return ['ok' => true];
    }

    /**
     * Incrementa um rate limit genérico por escopo.
     *
     * @param string $scope
     * @param string|null $subject
     * @return void
     */
    public function hitRateLimit(string $scope, ?string $subject = null): void
    {
        $cfg = $this->rateLimitCfg($scope);
        if (!$cfg || empty($cfg['enabled'])) {
            return;
        }

        $ip = getip() ?: '0.0.0.0';
        $now = time();

        $kSubjectIp = $this->rateLimitKey($scope, 'subject_ip', $subject, $ip);
        $kIp = $this->rateLimitKey($scope, 'ip', null, $ip);

        $countSubjectIp = (int)($this->cache->get($kSubjectIp . '__count') ?? 0) + 1;
        $countIp = (int)($this->cache->get($kIp . '__count') ?? 0) + 1;

        $this->cache->set($kSubjectIp . '__count', $countSubjectIp);
        $this->cache->set($kIp . '__count', $countIp);

        $this->rateLimitWindowRoll($kSubjectIp, (int)$cfg['window_sec']);
        $this->rateLimitWindowRoll($kIp, (int)$cfg['window_sec']);

        $limitReached = ($countSubjectIp >= (int)$cfg['max_subject_ip']) || ($countIp >= (int)$cfg['max_ip']);
        if (!$limitReached) {
            return;
        }

        $curLockSubjectIp = (int)($this->cache->get($kSubjectIp . '__lock_until') ?? 0);
        $curLockIp = (int)($this->cache->get($kIp . '__lock_until') ?? 0);
        $lockActive = ($curLockSubjectIp > $now) || ($curLockIp > $now);

        $lvlSubjectIp = (int)($this->cache->get($kSubjectIp . '__lock_level') ?? 0);
        $lvlIp = (int)($this->cache->get($kIp . '__lock_level') ?? 0);
        $lvl = $lockActive ? max($lvlSubjectIp, $lvlIp) : (max($lvlSubjectIp, $lvlIp) + 1);

        $base = (int)$cfg['base_lock_sec'];
        $max = (int)$cfg['max_lock_sec'];

        $duration = (int)min($max, $base * (2 ** max(0, $lvl - 1)));
        $until = $now + $duration;

        $this->cache->set($kSubjectIp . '__lock_until', $until);
        $this->cache->set($kIp . '__lock_until', $until);
        $this->cache->set($kSubjectIp . '__lock_level', $lvl);
        $this->cache->set($kIp . '__lock_level', $lvl);
    }

    /**
     * Limpa um rate limit genérico por escopo.
     *
     * @param string $scope
     * @param string|null $subject
     * @return void
     */
    public function clearRateLimit(string $scope, ?string $subject = null): void
    {
        $cfg = $this->rateLimitCfg($scope);
        if (!$cfg || empty($cfg['enabled'])) {
            return;
        }

        $ip = getip() ?: '0.0.0.0';
        $keys = [
            $this->rateLimitKey($scope, 'subject_ip', $subject, $ip),
        ];

        foreach ($keys as $key) {
            $this->clearRateLimitCacheKeySet($key);
        }
    }

    /**
     * Lê a configuração do rate limit extra para um escopo.
     *
     * @param string $scope
     * @return array|null
     */
    private function rateLimitCfg(string $scope): ?array
    {
        $limits = $this->config['security']['rate_limits'] ?? [];
        if (empty($limits[$scope]) || !is_array($limits[$scope])) {
            return null;
        }

        $cfg = $limits[$scope];
        $cfg['window_sec'] = max(30, (int)($cfg['window_sec'] ?? 600));
        $cfg['max_subject_ip'] = max(1, (int)($cfg['max_subject_ip'] ?? 5));
        $cfg['max_ip'] = max(1, (int)($cfg['max_ip'] ?? 15));
        $cfg['base_lock_sec'] = max(30, (int)($cfg['base_lock_sec'] ?? 600));
        $cfg['max_lock_sec'] = max($cfg['base_lock_sec'], (int)($cfg['max_lock_sec'] ?? 3600));

        return $cfg;
    }

    /**
     * Monta a chave HMAC usada pelos rate limits extras.
     *
     * @param string $scope
     * @param string $type
     * @param string|null $subject
     * @param string|null $ip
     * @return string
     */
    private function rateLimitKey(string $scope, string $type, ?string $subject, ?string $ip): string
    {
        $sec = $this->config['security'] ?? [];
        $salt = (string)($sec['throttle']['ip_hash_salt'] ?? 'change_me');
        $guard = (string)($this->guard ?? 'default');
        $guardSafe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $guard);

        $subjectNorm = $subject !== null ? mb_strtolower(trim($subject), 'UTF-8') : '';
        $ipNorm = $ip !== null ? trim($ip) : '';
        $scopeSafe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $scope);

        $raw = $scope . '|' . $type . '|' . $guard . '|' . $subjectNorm . '|' . $ipNorm;
        $hash = hash_hmac('sha256', $raw, $salt);

        return "authRateLimit__{$scopeSafe}__{$type}__{$guardSafe}__{$hash}";
    }

    /**
     * Implementa a janela deslizante dos rate limits extras.
     *
     * @param string $baseKey
     * @param int $windowSec
     * @return void
     */
    private function rateLimitWindowRoll(string $baseKey, int $windowSec): void
    {
        $now = time();
        $kStart = $baseKey . '__start';
        $start = (int)($this->cache->get($kStart) ?? 0);

        if ($start <= 0) {
            $this->cache->set($kStart, $now);
            return;
        }

        if (($now - $start) > $windowSec) {
            $this->cache->set($baseKey . '__count', 0);
            $this->cache->set($kStart, $now);
        }
    }

    /**
     * Remove do cache todos os artefatos auxiliares de uma chave base
     * de throttle/rate limit.
     *
     * @param string $baseKey
     * @return void
     */
    private function clearRateLimitCacheKeySet(string $baseKey): void
    {
        foreach (['__count', '__lock_until', '__lock_level', '__start'] as $suffix) {
            $this->cache->clear($baseKey . $suffix);
        }
    }

    // getters (mantidos do arquivo original) -------------------------------------------------

    public function getDriver() { return $this->driver; }
    public function getAuthname() { return $this->authname; }
    public function getGuardName() { return $this->guard ?? null; }
    public function getTable() { return $this->table; }
    public function getColumnId() { return $this->id; }
    public function getColumnLogin() { return $this->login; }
    public function getColumnPassword() { return $this->password; }
    public function getColumnName() { return $this->name; }
    public function getColumnPhoto() { return $this->photo; }
    public function getColumnStatus() { return $this->status; }
    public function getColumnValidate() { return $this->validate; }
    public function getColumnPermissao() { return $this->permissao; }
    public function getColumnToken() { return $this->token; }
    public function getPermissions() { return $this->permissions; }
    public function getTypelogin() { return $this->type_login; }
    public function getRoute(string $name, $default = null) { return $this->routes[$name] ?? $default; }
    public function getRouteLogin() { return $this->route_login; }
    public function getRouteSignin() { return $this->route_signin; }
    public function getRouteLogout() { return $this->route_logout ?? $this->route_login ?? '/'; }
    public function getRouteRedirect() { return $this->route_redirect; }
    public function getRouteForgotPassword() { return $this->getRoute("forgot_password"); }
    public function getRouteForgotPasswordRequest() { return $this->getRoute("forgot_password_request"); }
    public function getRouteResetPassword() { return $this->getRoute("reset_password"); }
    public function getRouteResetPasswordUpdate() { return $this->getRoute("reset_password_update"); }
    public function getRoutes() { return $this->routes; }
    public function getFeature(string $name, $default = null) { return $this->features[$name] ?? $default; }
    public function getFeatures() { return $this->features; }
    public function getCheckOnline() { return $this->check_online; }
    public function getHistory() { return $this->history; }
    public function getTableHistory() { return $this->table_history; }
}
