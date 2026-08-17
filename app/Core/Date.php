<?php
namespace App\Core;

use DateTime;
use DateInterval;
use DatePeriod;

class Date
{
    private DateTime $date;

    private static string $timezone = 'America/Sao_Paulo';
    private static string $lang = 'br';

    /* ===========================
     *  CONSTRUTOR & FACTORIES
     * =========================== */

    public function __construct(string $date = 'now', ?string $timezone = null)
    {
        date_default_timezone_set($timezone ?? self::$timezone);
        $this->date = new DateTime($date);
    }

    public static function now(): self
    {
        return new self('now');
    }

    public static function make(?string $date = null): self
    {
        return new self($date ?? 'now');
    }

    public static function setTimezone(string $timezone): void
    {
        self::$timezone = $timezone;
        date_default_timezone_set($timezone);
    }

    public static function setLang(string $lang): void
    {
        self::$lang = $lang;
    }

    /* ===========================
     *  FORMATOS
     * =========================== */

    public function format(string $format): string
    {
        return $this->date->format($format);
    }

    public function onlyDate(): string
    {
        return $this->format('Y-m-d');
    }

    public function onlyTime(bool $sec = false): string
    {
        return $this->format($sec ? 'H:i:s' : 'H:i');
    }

    public function datebr(bool $time = false, bool $sec = true): string
    {
        $format = 'd/m/Y';
        if ($time) {
            $format .= ' H:i';
            if ($sec) $format .= ':s';
        }
        return $this->format($format);
    }

    /* ===========================
     *  OPERAÇÕES DE DATA
     * =========================== */

    public function add(int $n, string $period): self
    {
        $this->date->modify("+{$n} {$this->normalizePeriod($period)}");
        return $this;
    }

    public function sub(int $n, string $period): self
    {
        $this->date->modify("-{$n} {$this->normalizePeriod($period)}");
        return $this;
    }

    public function addDays(int $n = 1): self { return $this->add($n, 'days'); }
    public function addMonths(int $n = 1): self { return $this->add($n, 'months'); }
    public function addYears(int $n = 1): self { return $this->add($n, 'years'); }
    public function addHours(int $n = 1): self { return $this->add($n, 'hours'); }
    public function addMinutes(int $n = 1): self { return $this->add($n, 'minutes'); }
    public function addSeconds(int $n = 1): self { return $this->add($n, 'seconds'); }

    public function subDays(int $n = 1): self { return $this->sub($n, 'days'); }
    public function subMonths(int $n = 1): self { return $this->sub($n, 'months'); }
    public function subYears(int $n = 1): self { return $this->sub($n, 'years'); }
    public function subHours(int $n = 1): self { return $this->sub($n, 'hours'); }
    public function subMinutes(int $n = 1): self { return $this->sub($n, 'minutes'); }
    public function subSeconds(int $n = 1): self { return $this->sub($n, 'seconds'); }

    /* ===========================
     *  COMPARAÇÕES
     * =========================== */

    public static function diff(string $start, ?string $end = null): \DateInterval
    {
        $start = new DateTime($start);
        $end = new DateTime($end ?? 'now');
        return $start->diff($end);
    }

    public static function diffInSeconds(string $start, ?string $end = null): int
    {
        return strtotime($end ?? 'now') - strtotime($start);
    }

    /* ===========================
     *  DIAS ÚTEIS / SEMANA
     * =========================== */

    public function isWeekend(): bool
    {
        return $this->date->format('N') >= 6;
    }

    public function isHoliday(): bool
    {
        return in_array($this->date->format('d/m'), self::holidays());
    }

    public function isBusinessDay(): bool
    {
        return !$this->isWeekend() && !$this->isHoliday();
    }

    public static function firstDayOfWeek(?string $date = null): string
    {
        $d = new self($date ?? 'now');
        $day = (int)$d->date->format('w');
        return $d->subDays($day)->onlyDate();
    }

    public static function lastDayOfWeek(?string $date = null): string
    {
        $d = new self($date ?? 'now');
        $day = (int)$d->date->format('w');
        return $d->addDays(6 - $day)->onlyDate();
    }

    function daysLeft($date)
    {

        $data_final = $date;
        $data_inicial = date('Y-m-d');

        $time_inicial = strtotime($data_inicial);
        $time_final   = strtotime($data_final);
        // Calcula a diferença de segundos entre as duas datas:
        $diferenca = $time_final - $time_inicial; // 19522800 segundos
        // Calcula a diferença de dias
        $dias = (int)floor( $diferenca / (60 * 60 * 24)); // 225 dias

        return $dias;
    }

    /* ===========================
     *  INTERVALOS
     * =========================== */

    public static function daysBetween(string $start, string $end, ?array $days = null, string $format = 'Y-m-d'): array
    {
        $start = new DateTime($start);
        $end = (new DateTime($end))->modify('+1 day');

        $period = new DatePeriod($start, new DateInterval('P1D'), $end);

        $dates = [];
        foreach ($period as $date) {
            if (!$days || in_array($date->format('w'), $days)) {
                $dates[] = $date->format($format);
            }
        }
        return $dates;
    }

    public static function age($date)
    {

        $time = self::toStr($date);

        $year_diff = 0;

        $date = date('Y-m-d', $time);

        list($year, $month, $day) = explode('-', $date);

        $year_diff = date('Y') - $year;
        $month_diff = date('m') - $month;
        $day_diff = date('d') - $day;

        if ($day_diff < 0 || $month_diff < 0) $year_diff--;

        return $year_diff;
    }

    public function timeLeft($seconds)
    {

        if ($seconds<0) {

            $time = "0 segundo";
        }
        elseif ($seconds<60) {

            $time = $seconds.' '.($seconds>1?'segundos':'segundo');
        }
        elseif ($seconds<3600) {

            $minutes = round($seconds/60);
            $time = $minutes.' '.($minutes>1?'minutos':'minuto');
        }
        else {

            $hours   = floor($seconds / 3600);
            $minutes = floor(($seconds - ($hours * 3600)) / 60);

            $time  = $hours.' '.($hours>1?'horas':'hora');
            $time .= ' e ';
            $time .= $minutes.' '.($minutes>1?'minutos':'minuto');

        }

        return $time;
    }


    /* ===========================
     *  PERIODOS PRONTOS ✅
     * =========================== */

    public static function periods(): array
    {
        return [
            'semana' => [
                self::firstDayOfWeek(),
                self::lastDayOfWeek(),
            ],
            'mes' => [
                date('Y-m-01'),
                date('Y-m-t'),
            ],
            'mes-anterior' => [
                date('Y-m-01', strtotime('-1 month')),
                date('Y-m-t', strtotime('-1 month')),
            ],
            'ano' => [
                date('Y-01-01'),
                date('Y-12-31'),
            ],
        ];
    }

    /* ===========================
     *  UTILITÁRIOS
     * =========================== */

    private function normalizePeriod(string $period): string
    {
        return match (strtolower($period)) {
            'd','dia','dias','day','days' => 'days',
            'm','mes','meses','month','months' => 'months',
            'a','y','ano','anos','year','years' => 'years',
            'h','hora','horas','hour','hours' => 'hours',
            'i','min','minuto','minutos','minute','minutes' => 'minutes',
            's','seg','sec','segundo','segundos','second','seconds' => 'seconds',
            default => 'days',
        };
    }

    private static function holidays(?string $year = null): array
    {
        $year = !empty($year) ? $year : date('Y');

        $seconds = 86400;
        $pascoa = easter_date($year);

        return [
            '01/01', // ano novo
            date('d/m', $pascoa - 47 * $seconds), // carnaval
            date('d/m', $pascoa - 2 * $seconds), // sexta santa
            date('d/m', $pascoa), // pascoa
            '21/04', // tiradentes
            '01/05', // dia do trabalho
            date('d/m', $pascoa + 60 * $seconds), // corpus christi
            '07/09', // independendia
            '12/10', // nossa sra aparecida
            '02/11', // finados
            '15/11', // proclamação da republica
            '20/11', // dia da conciência negra
            '25/12', // natal
        ];
    }

}
