<?php

namespace App\Core;

class Validator
{
    private $value;
    private $rules = [];
    private $errors = [];

    public static function make($value): self
    {
        $v = new self();
        $v->value = $value;
        return $v;
    }

    public function addRule(callable $rule, string $message): self
    {
        $this->rules[] = [$rule, $message];
        return $this;
    }

    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as [$rule, $message]) {
            if (!$rule($this->value)) {
                $this->errors[] = $message;
            }
        }

        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /* =========================
        REGRAS FLUENT API
    ==========================*/

    public function required(): self
    {
        return $this->addRule(
            fn($v) => !is_null($v) && $v !== '',
            "Campo obrigatório."
        );
    }

    public function notEmpty(): self
    {
        return $this->addRule(
            fn($v) => !(is_string($v) ? trim($v) === '' : empty($v)),
            "O valor não pode ser vazio."
        );
    }

    public function email(bool $checkDns = false): self
    {
        return $this->addRule(function ($v) use ($checkDns) {
            if (!filter_var($v, FILTER_VALIDATE_EMAIL)) return false;

            if ($checkDns) {
                [$local, $domain] = explode('@', $v);
                return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
            }

            return true;
        }, "E-mail inválido.");
    }

    public function cpf(): self
    {
        return $this->addRule(function ($cpf) {
            $cpf = preg_replace('/\D/', '', $cpf);
            if (strlen($cpf) != 11 || preg_match('/^(.)\1{10}$/', $cpf)) return false;

            for ($i=9; $i<11; $i++) {
                for ($j=0,$sum=0; $j<$i; $j++) $sum += $cpf[$j] * (($i+1)-$j);
                $digit = ((10*$sum)%11)%10;
                if ($cpf[$i] != $digit) return false;
            }
            return true;
        }, "CPF inválido.");
    }

    public function cnpj(): self
    {
        return $this->addRule(function ($cnpj) {
            $cnpj = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $cnpj));

            if (strlen($cnpj) !== 14 || preg_match('/^(.)\1{13}$/', $cnpj)) {
                return false;
            }

            $value = static function (string $char): ?int {
                if (ctype_digit($char)) {
                    return (int) $char;
                }

                if (ctype_alpha($char)) {
                    return ord($char) - 48;
                }

                return null;
            };

            $calc = static function (string $base, array $weights) use ($value): ?int {
                $sum = 0;
                foreach ($weights as $index => $weight) {
                    $charValue = $value($base[$index] ?? '');
                    if ($charValue === null) {
                        return null;
                    }
                    $sum += $charValue * $weight;
                }
                $mod = $sum % 11;
                return $mod < 2 ? 0 : 11 - $mod;
            };

            $d1 = $calc(substr($cnpj, 0, 12), [5,4,3,2,9,8,7,6,5,4,3,2]);
            if ($d1 === null) {
                return false;
            }

            $d2 = $calc(substr($cnpj, 0, 12) . $d1, [6,5,4,3,2,9,8,7,6,5,4,3,2]);
            if ($d2 === null) {
                return false;
            }

            return $cnpj[12] === (string) $d1 && $cnpj[13] === (string) $d2;
        }, "CNPJ inválido.");
    }

    public function rg(): self
    {
        return $this->addRule(
            fn($v) => preg_match('/^\d{1,2}\.?\d{3}\.?\d{3}-?[0-9Xx]$/',$v),
            "RG inválido."
        );
    }

    public function telefone(): self
    {
        return $this->addRule(function ($v) {
            $v = preg_replace('/\D/', '', $v);
            return preg_match('/^[1-9]{2}[0-9]{8,9}$/', $v);
        }, "Telefone inválido.");
    }

    public function cep(): self
    {
        return $this->addRule(
            fn($v)=>preg_match('/^\d{5}-?\d{3}$/',$v),
            "CEP inválido."
        );
    }

    public function nomeCompleto(): self
    {
        return $this->addRule(function ($v){
            return preg_match('/^[\p{L} ]+$/u',$v) && substr_count(trim($v),' ')>=1;
        }, "Nome completo inválido.");
    }

    public function min(int $len): self
    {
        return $this->addRule(
            fn($v)=>mb_strlen($v) >= $len,
            "Mínimo de {$len} caracteres."
        );
    }

    public function max(int $len): self
    {
        return $this->addRule(
            fn($v)=>mb_strlen($v) <= $len,
            "Máximo de {$len} caracteres."
        );
    }

    public function between(int $min,int $max): self
    {
        return $this->addRule(
            fn($v)=>mb_strlen($v)>= $min && mb_strlen($v)<= $max,
            "De {$min} a {$max} caracteres."
        );
    }

    public function alpha(): self
    {
        return $this->addRule(
            fn($v)=>preg_match('/^[\p{L} ]+$/u',$v),
            "Use apenas letras."
        );
    }

    public function alphaNum(): self
    {
        return $this->addRule(
            fn($v)=>preg_match('/^[\p{L}\p{N} ]+$/u',$v),
            "Use apenas letras e números."
        );
    }

    public function numeric(): self
    {
        return $this->addRule(
            fn($v)=>preg_match('/^[0-9]+$/',$v),
            "Use apenas números."
        );
    }

    public function int(): self
    {
        return $this->addRule(
            fn($v)=>filter_var($v,FILTER_VALIDATE_INT)!==false,
            "Número inteiro inválido."
        );
    }

    public function float(): self
    {
        return $this->addRule(
            fn($v)=>filter_var($v,FILTER_VALIDATE_FLOAT)!==false,
            "Número decimal inválido."
        );
    }

    public function url(): self
    {
        return $this->addRule(
            fn($v)=>filter_var($v,FILTER_VALIDATE_URL)!==false,
            "URL inválida."
        );
    }

    public function ip(): self
    {
        return $this->addRule(
            fn($v)=>filter_var($v,FILTER_VALIDATE_IP)!==false,
            "IP inválido."
        );
    }

    /**
     * Verifica se o valor pertence a uma lista de opções
     *
     * Ex: Validator::make($v)->in(['a','b'])->validate();
     */
    public function in(array $list): self
    {
        return $this->addRule(
            fn($v) => in_array($v, $list, true),
            "Valor inválido."
        );
    }

    /**
     * Valida booleano.
     * Aceita true/false, 1/0, "1"/"0", "true"/"false", "on"/"off", "yes"/"no".
     * Usa FILTER_VALIDATE_BOOLEAN com NULL_ON_FAILURE para detectar inválidos.
     */
    public function bool(): self
    {
        return $this->addRule(
            function ($v) {
                // aceitar nulos vazios como inválidos aqui (a regra 'required' separa)
                if ($v === null || $v === '') return false;

                // FILTER_NULL_ON_FAILURE devolve null quando não é reconhecido como boolean
                $res = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                return $res === true || $res === false;
            },
            "Valor booleano inválido."
        );
    }

    /**
     * Valida array
     */
    public function array(): self
    {
        return $this->addRule(
            fn($v) => is_array($v),
            "Deve ser um array."
        );
    }


    public function placa(): self
    {
        return $this->addRule(function($v){
            return preg_match('/^[A-Z]{3}\-?[0-9]{4}$/i',$v) ||
                   preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/i',$v);
        }, "Placa inválida.");
    }

    public function creditCard(): self
    {
        return $this->addRule(function($num){
            $num=preg_replace('/\D/','',$num);
            $sum=0;$alt=false;
            for($i=strlen($num)-1;$i>=0;$i--){
                $n=$num[$i];
                if($alt){ $n*=2;if($n>9)$n-=9; }
                $sum+=$n;
                $alt=!$alt;
            }
            return ($sum%10)===0;
        },"Cartão de crédito inválido.");
    }

    public function equal($other): self
    {
        return $this->addRule(
            fn($v)=>$v === $other,
            "Os valores não coincidem."
        );
    }

    public function regex(string $pattern,string $msg="Formato inválido."): self
    {
        return $this->addRule(
            fn($v)=>preg_match($pattern,$v),
            $msg
        );
    }

    public function passwordStrong(
        int $minLen = 8,
        bool $upper = true,
        bool $lower = true,
        bool $number = true,
        bool $special = true
    ): self {
        return $this->addRule(function($v) use ($minLen,$upper,$lower,$number,$special){
            if(strlen($v)<$minLen) return false;
            if($upper && !preg_match('/[A-Z]/',$v)) return false;
            if($lower && !preg_match('/[a-z]/',$v)) return false;
            if($number && !preg_match('/[0-9]/',$v)) return false;
            if($special && !preg_match('/[\W_]/',$v)) return false;
            return true;
        },"Senha fraca.");
    }

    public function dateBr(): self
    {
        return $this->addRule(function ($v) {

            if (!is_string($v)) return false;

            // dd/mm/yyyy
            if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $v)) {
                return false;
            }

            [$d, $m, $y] = explode('/', $v);

            return checkdate((int)$m, (int)$d, (int)$y);
        }, "Data inválida (use dd/mm/yyyy).");
    }

    public function dateTimeBr(bool $seconds = false): self
    {
        return $this->addRule(function ($v) use ($seconds) {

            if (!is_string($v)) return false;

            $pattern = $seconds
                ? '/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/'
                : '/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/';

            if (!preg_match($pattern, $v)) return false;

            [$date, $time] = explode(" ", $v);
            [$d, $m, $y] = explode("/", $date);

            if (!checkdate((int)$m, (int)$d, (int)$y)) return false;

            $t = explode(":", $time);

            if ($seconds && count($t) !== 3) return false;
            if (!$seconds && count($t) !== 2) return false;

            [$H, $i] = $t;
            $s = $seconds ? $t[2] : 0;

            return $H >= 0 && $H <= 23 &&
                $i >= 0 && $i <= 59 &&
                $s >= 0 && $s <= 59;
        }, "Data/hora inválida (use dd/mm/yyyy HH:MM" . ($seconds ? ":SS" : "") . ").");
    }

    public function date(): self
    {
        return $this->addRule(function ($v) {

            if (!is_string($v)) return false;

            // yyyy-mm-dd
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                return false;
            }

            [$y, $m, $d] = explode('-', $v);

            return checkdate((int)$m, (int)$d, (int)$y);
        }, "Data inválida (use yyyy-mm-dd).");
    }

    public function dateTime(bool $seconds = false): self
    {
        return $this->addRule(function ($v) use ($seconds) {

            if (!is_string($v)) return false;

            $pattern = $seconds
                ? '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/'
                : '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/';

            if (!preg_match($pattern, $v)) return false;

            [$date, $time] = explode(" ", $v);
            [$y, $m, $d] = explode("-", $date);

            if (!checkdate((int)$m, (int)$d, (int)$y)) return false;

            $t = explode(":", $time);

            if ($seconds && count($t) !== 3) return false;
            if (!$seconds && count($t) !== 2) return false;

            [$H, $i] = $t;
            $s = $seconds ? $t[2] : 0;

            return $H >= 0 && $H <= 23 &&
                $i >= 0 && $i <= 59 &&
                $s >= 0 && $s <= 59;
        }, "Data/hora inválida (use yyyy-mm-dd HH:MM" . ($seconds ? ":SS" : "") . ").");
    }

    public function betweenDates(string $min, string $max): self
    {
        return $this->addRule(function ($v) use ($min, $max) {

            $vTime = strtotime($v);
            $minTime = strtotime($min);
            $maxTime = strtotime($max);

            if ($vTime === false || $minTime === false || $maxTime === false) {
                return false;
            }

            return $vTime >= $minTime && $vTime <= $maxTime;

        }, "A data deve estar entre {$min} e {$max}.");
    }



}
