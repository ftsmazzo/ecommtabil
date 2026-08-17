<?php
namespace App\Core;

use App\Core\Message;
use App\Core\Password;
use App\Core\Csrf;
use App\Core\Redirect;
use Exception;
use Respect\Validation\Validator as v;
use Transliterator;
use App\Models\Configuracao;
use DateTime;

class Data
{
    private array $data = [];
    private string $response;
    private int $errCode;

    private ?Transliterator $transliterator = null;

    /**
     * Campos que devem ser ignorados automaticamente.
     */
    protected array $ignoreKeys = [
        'route',
    ];

    /**
     * Lista de termos proibidos em inputs.
     */
    private array $blackList = [
        '.asp','.co.uk','.kz','.net','.nl','.org','.php','.rss','.ru',
        'cgi-bin','file','ftp','http','link=','redirect','telegr','url=',
        'whats','www','zap',
    ];

    /**
     * Cache do JSON lido do php://input
     */
    private static ?array $cachedInput = null;

    /**
     * Construtor mantém compatibilidade total.
     */
    public function __construct($data = null, bool $ignoreNull = true, array $ignoreStrips = [])
    {
        if ($data) {
            $data = (array)$data;
            $valid = $this->filter($data, $ignoreNull, $ignoreStrips);
            $this->data = $valid;

            $this->csrf();

            if (!$ignoreNull) {
                $this->nullIfEmpty("*");
            }
        }

        $this->errCode  = 400;
        $this->response = "message"; // json | exception | message
    }

    // ============================================================
    // SETTERS / CONFIG
    // ============================================================

    public function setData($data, bool $ignoreNull = true)
    {
        $data = (array)$data;
        $this->data = $this->filter($data, $ignoreNull);
        return $this;
    }

    public function setResponse($type)
    {
        $this->response = $type;
        return $this;
    }

    public function setErrCode(int $errCode)
    {
        $this->errCode = $errCode;
        return $this;
    }

    public function setBlacklist(array $blackList)
    {
        $this->blackList = $blackList;
        return $this;
    }

    // ============================================================
    // GETTERS
    // ============================================================

    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function has(string $name): bool
    {
        return isset($this->data[$name]) && $this->data[$name] !== '' && $this->data[$name] !== null;
    }

    public function notHas(string $name, $alter = null)
    {
        return $this->has($name) ? $this->data[$name] : $alter;
    }

    public function get(string $name, $alter = null)
    {
        return $this->notHas($name, $alter);
    }

    public function contain(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    public function toObject()
    {
        (object)$this->data;
        return $this;
    }

    // ============================================================
    // JSON
    // ============================================================

    public function fromJson(bool $ignoreNull = true): array
    {
        if (self::$cachedInput === null) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);

            self::$cachedInput = is_array($decoded) ? $decoded : [];
        }

        $this->setData(self::$cachedInput, $ignoreNull);
        return $this->data;
    }

    // ============================================================
    // MANIPULAÇÃO
    // ============================================================

    public function add($field, $value): Data
    {
        $this->data[$field] = $value;
        return $this;
    }

    public function remove($fields): Data
    {
        foreach ((array)$fields as $f) {
            unset($this->data[$f]);
        }
        return $this;
    }

    public function del($fields): Data
    {
        return $this->remove($fields);
    }

    public function union(string $name, array $fields, string $sep = " ", bool $remove = true): Data
    {
        $values = [];

        foreach ($fields as $f) {
            $values[] = $this->data[$f] ?? '';
        }

        $new = implode($sep, $values);

        if ($remove) {
            $this->remove($fields);
        }

        return $this->add($name, $new);
    }

    // ============================================================
    // FILTRO / SANITIZAÇÃO
    // ============================================================

    private function filter(array $data, bool $ignoreNull = true, array $ignoreStripeds = []): array
    {
        // Remove chaves ignoradas (route, etc)
        foreach ($this->ignoreKeys as $key) {
            unset($data[$key]);
        }

        // Campos preservados (sem sanitização)
        $stripeds = [];

        foreach ($ignoreStripeds as $field) {
            if (isset($data[$field])) {
                $stripeds[$field] = $data[$field];
                unset($data[$field]);
            }
        }

        // Sanitiza
        $data = $this->sanitizeArray($data);

        // Volta campos que não devem ser modificados
        if ($stripeds) {
            $data = array_merge($data, $stripeds);
        }

        // Remove nulls/vazios (exceto "0")
        if ($ignoreNull) {
            $data = array_filter($data, function ($v) {
                return $v !== false && $v !== null && ($v !== '' || $v === '0');
            });
        }

        return $data;
    }

    private function sanitizeArray(array $array): array
    {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $array[$k] = $this->sanitizeArray($v);
            } else {
                $array[$k] = $this->sanitizeValue($v);
            }
        }
        return $array;
    }

    private function sanitizeValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $clean = trim($value);
        $clean = strip_tags($clean);

        // Blacklist
        foreach ($this->blackList as $word) {
            if (stripos($clean, $word) !== false) {
                $clean = str_ireplace($word, '', $clean);
            }
        }

        return $clean;
    }

    // ============================================================
    // CSRF
    // ============================================================
    // private function csrf()
    // {
    //     if (isset($this->data['csrf'])) {

    //         if (!Csrf::confirm($this->data['csrf'])) {
    //             // throw new \Exception("Token CSRF inválido.");
    //             return false;
    //         }

    //         // remove automaticamente após validar
    //         unset($this->data['csrf']);
    //     }
    // }

    private function csrf()
    {
        if (!isset($this->data['csrf'])) {
            return true;
        }

        if (!\App\Core\Csrf::confirm($this->data['csrf'])) {
            unset($this->data['csrf']);

            // padrão seguro: exception
            throw new \Exception("Token CSRF inválido.");
        }

        unset($this->data['csrf']);
        return true;
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private function getIp() {

        $ipCandidates = [];

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipCandidates[] = $_SERVER['REMOTE_ADDR'];
        }

        // Cabeçalhos podem ser manipulados — use com cautela
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X_FORWARDED_FOR pode conter lista: pegar primeiro IP
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ipCandidates[] = trim($parts[0]);
        }
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipCandidates[] = $_SERVER['HTTP_CLIENT_IP'];
        }

        foreach ($ipCandidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_PRIV_RANGE)) {
                return $ip;
            }
        }

        // se não houver IP público válido, aceitar o primeiro válido (incluindo privados)
        foreach ($ipCandidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return 'UNKNOWN';
    }

    // ============================================================
    // VERIFICAÇÕES
    // ============================================================

    public function ifEmpty($array)
    {
        foreach ($array as $field => $value) {
            $this->data[$field] = $this->has($field) ? $this->data[$field] : $value;
        }

        return $this;
    }

    public function zeroIfEmpty($fields)
    {
        foreach ((array)$fields as $f) {
            $this->data[$f] = $this->has($f) ? $this->data[$f] : 0;
        }

        return $this;
    }

    public function nullIfEmpty($fields)
    {
        // "*" significa aplicar em todos os campos atuais
        if ($fields === "*") {
            $fields = array_keys($this->data);
        } else {
            $fields = (array)$fields;
        }

        foreach ($fields as $f) {
            // Se campo não existe → vira null
            if (!array_key_exists($f, $this->data)) {
                $this->data[$f] = null;
                continue;
            }

            $value = $this->data[$f];

            // Se valor é considerado empty (exceto string "0")
            if ($value === '' || $value === null || $value === false) {
                $this->data[$f] = null;
                continue;
            }

            // Se é string vazia depois do trim
            if (is_string($value) && trim($value) === '') {
                $this->data[$f] = null;
                continue;
            }

            // Mantém o valor se não for vazio
            $this->data[$f] = $value;
        }

        return $this;
    }

    // ============================================================
    // VALIDAÇÕES
    // ============================================================

    public function validateField(string $field, callable $rules)
    {
        $value = $this->data[$field] ?? null;
        $validator = Validator::make($value);

        $rules($validator);

        if (!$validator->validate()) {
            $this->return(
                "Erro no campo '{$field}': " . implode(", ", $validator->errors())
            );
        }

        return $this;
    }

    public function ignore($fields)
    {
        $fields = (array)$fields;

        foreach ($fields as $f) {
            if (!isset($this->data[$f]) || $this->data[$f] === '' || $this->data[$f] === null) {
                unset($this->data[$f]);
            }
        }

        return $this;
    }

    public function blackListed($fields = false)
    {
        $invalids = [];

        $listFields = $fields ? (array)$fields : array_keys($this->data);

        foreach ($listFields as $f) {
            if (isset($this->data[$f]) && $this->isBlackListed($this->data[$f])) {
                $invalids[] = $f;
            }
        }

        return empty($invalids);
    }

    private function isBlackListed($str)
    {
        if (!is_string($str) || $str === '') {
            return false;
        }

        // Inicializa transliterator apenas uma vez
        if ($this->transliterator === null) {
            $this->transliterator = Transliterator::createFromRules(
                ':: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;',
                Transliterator::FORWARD
            );
        }

        // Normaliza a string de entrada
        $normalized = $this->transliterator->transliterate($str);
        $normalized = str_replace("@", "a", $normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized);

        foreach ($this->blackList as $word) {
            if ($word === '') continue;

            // Normaliza a palavra também
            $w = strtolower($word);
            $w = preg_replace('/[^a-z0-9]+/', '', $w);

            if ($w !== '' && strpos($normalized, $w) !== false) {
                return true;
            }
        }

        return false;
    }

    public function isCensored($fields)
    {
        $config = Configuracao::list();
        $rawFiltro = $config->comentario_filtro ?? null;

        $filtro = [];

        if ($rawFiltro) {
            $decoded = json_decode($rawFiltro, true);

            if (is_array($decoded)) {
                $filtro = $decoded;
            } else {
                $un = @unserialize($rawFiltro, ['allowed_classes' => false]);
                $filtro = is_array($un) ? $un : [];
            }
        }

        $fields = (array)$fields;

        foreach ($fields as $f) {

            if (!isset($this->data[$f])) continue;

            $value = $this->data[$f];

            foreach ($filtro as $word) {
                if ($word === '') continue;

                $pattern = '/' . preg_quote($word, '/') . '/i';

                if (preg_match($pattern, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function only($fields)
    {
        $fields = (array)$fields;

        $new = [];
        foreach ($fields as $f) {
            $new[$f] = $this->data[$f] ?? null;
        }

        $this->data = $new;
        return $this->data; // mantém comportamento original
    }

    public function required($fields, $returnType = "msg", $msg = false)
    {
        $fields = (array)$fields;
        $errors = [];

        foreach ($fields as $f) {
            $value = $this->data[$f] ?? null;

            try {
                Validator::make($value)->required()->validate();
            } catch (\Exception $e) {
                $errors[] = $f;
            }
        }

        if (!empty($errors)) {
            if ($returnType === "msg") {
                $message = $msg ?: "O(s) campo(s) <b>" . implode(", ", $errors) . "</b> são obrigatórios";
                $this->return($message);
                exit;
            }
            return false;
        }

        return true;
    }

    public function allow($fields)
    {
        $fields = (array)$fields;
        $errors = [];

        foreach ($this->data as $key => $value) {
            try {
                Validator::make($key)->in($fields)->validate();
            } catch (\Exception $e) {
                $errors[] = $key;
            }
        }

        if (!empty($errors)) {
            $this->return("O(s) campo(s) " . implode(", ", $errors) . " são inválidos");
        }

        return $this;
    }

    public function id($name = null)
    {
        $id = $name !== null ? ($this->data[$name] ?? null) : ($this->data["id"] ?? null);

        // 1. Id não existe ou é vazio
        if ($id === null || $id === '' || $id === false) {
            $this->return("Id não encontrado");
            exit;
        }

        // 2. Validar como inteiro usando seu Validator
        try {
            Validator::make($id)->int()->validate();
        } catch (\Exception $e) {
            $this->return("Id inválido");
            exit;
        }

        return (int)$id;
    }

    public function isInt($fields)
    {
        $fields = (array)$fields;
        $errors = [];

        foreach ($fields as $f) {
            if (!$this->has($f)) continue;

            try {
                Validator::make($this->data[$f])->int()->validate();
            } catch (\Exception $e) {
                $errors[] = $f;
            }
        }

        if (!empty($errors)) {
            $this->return("O(s) campo(s) " . implode(", ", $errors) . " devem ser do tipo inteiro");
        }

        return $this;
    }

    public function isBool($fields)
    {
        $fields = (array)$fields;
        $errors = [];

        foreach ($fields as $f) {
            if (!$this->has($f)) continue;

            try {
                Validator::make($this->data[$f])->bool()->validate();
            } catch (\Exception $e) {
                $errors[] = $f;
            }
        }

        if (!empty($errors)) {
            $this->return("O(s) campo(s) " . implode(", ", $errors) . " devem ser do tipo verdadeiro/falso");
        }

        return $this;
    }

    public function isArray($fields)
    {
        $fields = (array)$fields;
        $errors = [];

        foreach ($fields as $f) {
            if (!$this->has($f)) continue;

            try {
                Validator::make($this->data[$f])->array()->validate();
            } catch (\Exception $e) {
                $errors[] = $f;
            }
        }

        if (!empty($errors)) {
            $this->return("O(s) campo(s) " . implode(", ", $errors) . " devem ser do tipo array");
        }

        return $this;
    }

    public function isNumeric($fields)
    {
        $fields = (array)$fields;
        $errors = [];

        foreach ($fields as $f) {
            if ($this->has($f)) {
                $v = Validator::make($this->data[$f]);

                if (!$v->float()->validate()) {
                    $errors[] = $f;
                }
            }
        }

        if (!empty($errors)) {
            $this->return(
                "O(s) campo(s) " . implode(", ", $errors) . " devem ser do tipo numérico"
            );
        }

        return $this;
    }

    public function isEmail($fields)
    {
        $fields = (array)$fields;
        $errors = [];

        foreach ($fields as $f) {
            if ($this->has($f)) {
                $v = Validator::make($this->data[$f]);

                if (!$v->email()->validate()) {
                    $errors[] = $f;
                }
            }
        }

        if (!empty($errors)) {
            $this->return(
                "O(s) campo(s) " . implode(", ", $errors) . " devem ser do tipo email"
            );
        }

        return $this;
    }

    public function isDate($fields)
    {
        $fields = (array)$fields;
        $errors = [];

        foreach ($fields as $f) {

            if (!$this->has($f)) {
                continue; // não valida campo ausente (mesmo comportamento antigo)
            }

            $value = $this->data[$f];

            $validator = Validator::make($value)->date();

            if (!$validator->validate()) {
                $errors[] = $f;
            }
        }

        if (!empty($errors)) {
            $this->return(
                "O(s) campo(s) " . implode(", ", $errors) . " devem ser uma data válida (yyyy-mm-dd)"
            );
        }

        return $this;
    }

    public function isDateBr($fields)
    {
        $fields = (array)$fields;
        $errors = [];

        foreach ($fields as $f) {

            if (!$this->has($f)) {
                continue;
            }

            $value = $this->data[$f];

            $validator = Validator::make($value)->dateBr();

            if (!$validator->validate()) {
                $errors[] = $f;
            }
        }

        if (!empty($errors)) {
            $this->return(
                "O(s) campo(s) " . implode(", ", $errors) . " devem estar no formato dd/mm/yyyy"
            );
        }

        return $this;
    }

    public function isDatetimeBr($fields, $seconds = false)
    {
        $fields = (array)$fields;
        $errors = [];

        foreach ($fields as $f) {

            if (!$this->has($f)) {
                continue;
            }

            $value = $this->data[$f];

            $validator = Validator::make($value)->dateTimeBr($seconds);

            if (!$validator->validate()) {
                $msg = $seconds
                    ? "dd/mm/yyyy HH:MM:SS"
                    : "dd/mm/yyyy HH:MM";

                $errors[] = "$f (formato: $msg)";
            }
        }

        if (!empty($errors)) {
            $this->return(
                "O(s) campo(s) devem estar no formato correto: <br>" .
                implode("<br>", $errors)
            );
        }

        return $this;
    }

    public function list($fields, $list)
    {
        $fields = (array)$fields;
        $list   = (array)$list;
        $errors = [];

        foreach ($fields as $f) {
            if (!$this->has($f)) continue;

            $value = $this->data[$f];

            if (!Validator::make($value)->in($list)->validate()) {
                $errors[] = $f;
            }
        }

        if (!empty($errors)) {
            $this->return(
                "O(s) campo(s) " . implode(", ", $errors) . " possuem valores inválidos"
            );
        }

        return $this;
    }

    public function max($fields, $length)
    {
        $fields = (array)$fields;
        $errors = [];

        foreach ($fields as $f) {
            if (!$this->has($f)) continue;

            $value = $this->data[$f];

            if (!Validator::make($value)->max($length)->validate()) {
                $errors[] = $f;
            }
        }

        if (!empty($errors)) {
            $this->return(
                "O(s) campo(s) " . implode(", ", $errors) .
                " devem ser menores ou iguais a " . $length
            );
        }

        return $this;
    }

    public function min($fields, $length)
    {
        $fields = (array)$fields;
        $errors = [];

        foreach ($fields as $f) {
            if (!$this->has($f)) continue;

            $value = $this->data[$f];

            if (!Validator::make($value)->min($length)->validate()) {
                $errors[] = $f;
            }
        }

        if (!empty($errors)) {
            $this->return(
                "O(s) campo(s) " . implode(", ", $errors) .
                " devem ser maiores ou iguais a " . $length
            );
        }

        return $this;
    }

    // ============================================================
    // CONVERSÕES
    // ============================================================


    public function upper($fields)
    {
        return $this->convert_case($fields, MB_CASE_UPPER);
    }

    public function lower($fields)
    {
        return $this->convert_case($fields, MB_CASE_LOWER);
    }

    public function capitalize($fields, $preposicao = "lower")
    {
        // 1. Aplica Title Case base
        $fields = $this->convert_case($fields, MB_CASE_TITLE);

        // 2. Correção de preposições e regras adicionais
        if ($preposicao) {

            $preposicoes = ['De', 'Da', 'Do', 'Dos', 'Em', 'Na', 'No', 'Web'];

            foreach ($fields as $f => $v) {
                if (!$this->has($f)) continue;

                $words = explode(" ", $this->data[$f]);

                foreach ($words as $i => $word) {

                    if (in_array($word, $preposicoes, true)) {

                        // mantém minúsculo ou maiúsculo conforme pedido
                        $words[$i] = $preposicao === "upper"
                            ? mb_convert_case($word, MB_CASE_UPPER, "UTF-8")
                            : mb_convert_case($word, MB_CASE_LOWER, "UTF-8");

                    } elseif (mb_strlen($word, 'UTF-8') > 3) {

                        // regra antiga: palavras grandes ficam capitalizadas corretamente
                        $words[$i] = mb_convert_case(
                            mb_strtolower($word, "UTF-8"),
                            MB_CASE_TITLE,
                            "UTF-8"
                        );
                    }
                }

                $this->data[$f] = implode(' ', $words);
            }
        }

        return $this->data;
    }

    private function convert_case($fields, $mode = MB_CASE_UPPER)
    {
        $fields = (array)$fields;

        foreach ($fields as $f) {
            if ($this->has($f)) {
                $this->data[$f] = mb_convert_case(
                    trim((string)$this->data[$f]),
                    $mode,
                    "UTF-8"
                );
            }
        }

        return $this->data;
    }


    public function date($fields, $force = false)
    {
        $fields = (array)$fields;

        foreach ($fields as $f) {

            if ($this->has($f)) {

                $value = $this->data[$f];

                if ($this->isDateBr($value)) {

                    // converte DD/MM/YYYY → YYYY-MM-DD
                    $this->data[$f] = implode('-', array_reverse(explode('/', $value)));

                } else {

                    // tenta converter qualquer formato válido do PHP
                    $this->data[$f] = date('Y-m-d', strtotime($value));
                }

            } elseif ($force) {
                $this->data[$f] = date("Y-m-d");
            }
        }

        return $this;
    }

    public function time($fields, $sec = true, $force = false)
    {
        $fields = (array)$fields;
        $format = "H:i" . ($sec ? ":s" : "");

        foreach ($fields as $f) {

            if ($this->has($f)) {

                $this->data[$f] = date($format, strtotime($this->data[$f]));

            } elseif ($force) {

                $this->data[$f] = date($format);
            }
        }

        return $this;
    }

    public function datetime($fields, $force = false)
    {
        $fields = (array)$fields;

        foreach ($fields as $f) {

            if ($this->has($f)) {

                $value = $this->data[$f];

                // formato BR DD/MM/YYYY HH:MM[:SS]
                if ($this->is_datebr($value, true)) {

                    $parts = explode(" ", $value);
                    $data = $parts[0];
                    $hora = $parts[1] ?? '00:00:00';

                    $this->data[$f] = implode('-', array_reverse(explode('/', $data))) . " " . $hora;

                } else {
                    // qualquer formato strtotime
                    $this->data[$f] = date('Y-m-d H:i:s', strtotime($value));
                }

            } elseif ($force) {

                $this->data[$f] = date("Y-m-d H:i:s");
            }
        }

        return $this;
    }

    public function seconds($fields)
    {
        $fields = (array)$fields;

        foreach ($fields as $f) {
            if ($this->has($f)) {

                $time = explode(":", $this->data[$f]);

                $hour    = ((int)($time[0] ?? 0)) * 3600;
                $minutes = ((int)($time[1] ?? 0)) * 60;
                $seconds = (int)($time[2] ?? 0);

                $this->data[$f] = $hour + $minutes + $seconds;
            }
        }

        return $this;
    }

    public function merge($new, $fields, $remove = true)
    {
        $fields = (array)$fields;

        $parts = [];
        foreach ($fields as $f) {
            $parts[] = $this->data[$f] ?? '';
        }

        $this->data[$new] = trim(implode(" ", $parts));

        if ($remove) {
            $this->remove($fields);
        }

        return $this;
    }

    public function numbers($fields)
    {
        $fields = (array)$fields;

        foreach ($fields as $f) {
            if ($this->has($f)) {
                $this->data[$f] = preg_replace('/\D+/', '', $this->data[$f]);
            }
        }

        return $this;
    }

    public function password($fields)
    {
        foreach ((array)$fields as $f) {
            if ($this->has($f)) {
                $this->data[$f] = Password::hash((string)$this->data[$f]);
            }
        }
        return $this;
    }

    public function url($fields)
    {
        foreach ((array)$fields as $f) {
            if ($this->has($f)) {
                $this->data[$f] = preg_replace('#^https?://#i', '', $this->data[$f]);
            }
        }
        return $this;
    }

    public function hours2seconds($fields)
    {
        foreach ((array)$fields as $f) {
            if ($this->has($f)) {
                $this->data[$f] = (float)$this->data[$f] * 3600;
            }
        }
        return $this;
    }

    public function ip($fields)
    {
        $ip = $this->getIp();

        foreach ((array)$fields as $f) {
            if ($this->has($f)) {
                $this->data[$f] = $ip;
            }
        }

        return $this;
    }

    public function timestamp($fields, $sec = true)
    {
        $format = $sec ? "Y-m-d H:i:s" : "Y-m-d";
        $now = date($format);

        foreach ((array)$fields as $f) {
            $this->data[$f] = $now;
        }

        return $this;
    }

    public function seconds2hour($fields)
    {
        foreach ((array)$fields as $f) {
            if ($this->has($f)) {
                $this->data[$f] = (float)$this->data[$f] / 3600;
            }
        }
        return $this;
    }

    public function unique($fields)
    {
        foreach ((array)$fields as $f) {
            if (isset($this->data[$f]) && is_array($this->data[$f])) {
                $this->data[$f] = array_values(array_unique($this->data[$f]));
            }
        }
        return $this;
    }

    public function serialize($fields, $sep = ",")
    {
        foreach ((array)$fields as $f) {
            if ($this->has($f)) {
                $value = $this->data[$f];

                if (!is_array($value)) {
                    $value = explode($sep, $value);
                }

                $value = array_map('trim', $value);

                $this->data[$f] = serialize($value);
            }
        }
        return $this;
    }

    public function token($fields, $algo = "strong", $length = null, $key = null, $max = 255)
    {
        // strong / random
        if (in_array($algo, ['strong', 'random'], true)) {
            try {
                $hash = bin2hex(random_bytes(16)); // 32 hex chars
            } catch (\Exception $e) {
                $hash = hash("sha256", (string)($key ?? microtime(true) . random_int(1, PHP_INT_MAX)));
            }
        } else {
            // compatibilidade
            switch ($algo) {
                case 'sha1':
                    $hash = sha1($key ?? uniqid('', true));
                    break;
                case 'md5':
                    $hash = md5($key ?? uniqid('', true));
                    break;
                case 'base64':
                    $hash = base64_encode($key ?? uniqid('', true));
                    break;
                default:
                    $hash = bin2hex(random_bytes(16));
            }
        }

        if ($length !== null) {
            $hash = substr($hash, 0, $length);
        }

        $hash = substr($hash, 0, $max);

        foreach ((array)$fields as $f) {
            $this->data[$f] = $hash;
        }

        return $this;
    }

    public function month2Date($fields, $day = "01")
    {
        $meses = [
            // Português
            'janeiro' => '01', 'fevereiro' => '02', 'março' => '03', 'marco' => '03',
            'abril' => '04', 'maio' => '05', 'junho' => '06', 'julho' => '07',
            'agosto' => '08', 'setembro' => '09', 'outubro' => '10', 'novembro' => '11', 'dezembro' => '12',

            // Inglês
            'january' => '01', 'february' => '02', 'march' => '03', 'april' => '04',
            'may' => '05', 'june' => '06', 'july' => '07', 'august' => '08',
            'september' => '09', 'october' => '10', 'november' => '11', 'december' => '12'
        ];

        $fields = (array)$fields;

        foreach ($fields as $f) {
            if (!$this->has($f)) continue;

            $texto = trim(mb_strtolower($this->data[$f], 'UTF-8'));

            // captura: "janeiro 2024", "março 1999", "august 2020"
            if (preg_match('/([a-zçãõéáíúêôâ]+)\s+(\d{4})/iu', $texto, $m)) {

                $mesNome = $m[1];
                $ano = $m[2];

                if (isset($meses[$mesNome])) {
                    $this->data[$f] = "{$ano}-{$meses[$mesNome]}-{$day}";
                } else {
                    $this->data[$f] = null;
                }

            } else {
                $this->data[$f] = null;
            }
        }

        return $this;
    }

    public function string2Int($fields)
    {
        $fields = (array)$fields;

        foreach ($fields as $f) {
            if ($this->has($f)) {
                $this->data[$f] = (int)$this->data[$f];
            }
        }

        return $this;
    }

    public function decimal2Float($fields)
    {
        $fields = (array)$fields;

        foreach ($fields as $f) {
            if ($this->has($f) && !empty($this->data[$f])) {
                $this->data[$f] = str_replace(",", ".", $this->data[$f]);
            }
        }

        return $this;
    }

    public function money2Float($fields)
    {
        $fields = (array)$fields;

        foreach ($fields as $f) {
            if ($this->has($f)) {

                $value = str_replace(".", "", $this->data[$f]); // remove milhares
                $value = str_replace(",", ".", $value);         // troca decimal

                $this->data[$f] = (float)$value;
            }
        }

        return $this;
    }

    public function trim($fields = null)
    {
        if ($fields) {

            foreach ((array)$fields as $f) {
                if ($this->has($f)) {
                    $this->data[$f] = is_string($this->data[$f])
                        ? trim($this->data[$f])
                        : $this->data[$f];
                }
            }

        } else {

            foreach ($this->data as $k => $v) {
                if (is_string($v)) {
                    $this->data[$k] = trim($v);
                }
            }
        }

        return $this;
    }

    public function toJson($fields, string $sep = ",", ?callable $callback = null, bool $force = false)
    {
        $fields = (array)$fields;

        foreach ($fields as $f) {

            if (!$this->has($f)) {
                if ($force) {
                    $this->data[$f] = null;
                }
                continue;
            }

            $value = $this->data[$f];

            // 1. Já é JSON válido?
            if (is_string($value)) {
                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->data[$f] = $value; // mantém
                    continue;
                }
            }

            // 2. Se não é array → tentar transformar
            if (!is_array($value)) {
                // explode se for string
                if (is_string($value)) {
                    $value = array_map('trim', explode($sep, $value));
                } else {
                    $this->data[$f] = null;
                    continue;
                }
            }

            // 3. aplicar callback
            if (is_callable($callback)) {
                $value = array_map($callback, $value);
            }

            // 4. limpar valores nulos
            $value = array_filter($value, fn($v) => $v !== null && $v !== "");

            if (empty($value)) {
                $this->data[$f] = null;
                continue;
            }

            // 5. codificar
            $json = json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
            );

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->data[$f] = null;
                continue;
            }

            $this->data[$f] = $json;
        }

        return $this;
    }

    // ============================================================
    // SAÍDAS
    // ============================================================

    public function print($die = false)
    {
        echo "<pre>" . htmlspecialchars(print_r($this->data, true)) . "</pre>";
        if ($die) die();
    }

    public function dump($die = false)
    {
        dump($this->data);
        if ($die) die();
    }

    public function dd()
    {
        dd($this->data);
    }

    private function return($message)
    {
        // JSON response
        if ($this->response === "json") {
            echo $this->response_json(
                ["error" => strip_tags($message)],
                $this->errCode
            );
            exit;
        }

        // Exception mode
        if ($this->response === "exception") {
            throw new \Exception($message);
        }

        // Flash + redirect
        (new Message())->flash($message, "danger", true);

        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $host    = $_SERVER['HTTP_HOST'] ?? '';

        // Proteção contra open redirect
        $safe = filter_var($referer, FILTER_VALIDATE_URL)
                && $host
                && stripos($referer, $host) !== false;

        if ($safe) {
            (new Redirect())->referer();
        } else {
            (new Redirect())->to('/');
        }
    }

    private function response_json($data, int $code = 200, string $iso = "utf-8")
    {
        header_remove();
        header("Access-Control-Allow-Origin: *");

        header("Content-Type: application/json; charset={$iso}");
        http_response_code($code);

        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

}
