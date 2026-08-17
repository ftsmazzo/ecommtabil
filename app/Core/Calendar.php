<?php
namespace App\Core;

use DateTime;

class Calendar
{

    private static $locale = "Portuguese";
    private static $timezone = "America/Sao_Paulo";
    private static $class = "calendar";
    private static $year;
    private static $month;
    private static $first_day = 0;
    private static $day_name_length = 3;
    private static $month_link = null;
    private static $prev = false;
    private static $next = false;
    private static $holidays = [];
    private static $days;

    public function __construct()
    {
        setlocale(LC_TIME, self::$locale);
        date_default_timezone_set(self::$timezone);
    }

    public static function setLocale($locale)
    {
        self::$locale = $locale;
        return new self;
    }

    public static function setTimezone($timezone)
    {
        self::$timezone = $timezone;
        return new self;
    }

    public static function setClass($class)
    {
        self::$class .= " " . $class;
        return new self;
    }

    public static function setYear($year)
    {
        self::$year = $year;
        return new self;
    }

    public static function setMonth($month)
    {
        self::$month = $month;
        return new self;
    }

    public static function setFirstDay($first_day)
    {
        self::$first_day = $first_day;
        return new self;
    }

    public static function setDayNameLength($day_name_length = null)
    {
        self::$day_name_length = $day_name_length;
        return new self;
    }

    public static function setPrev($link = false, $icon = null)
    {
        self::$prev = [];
        self::$prev["link"] = $link;
        self::$prev["icon"] = $icon;
        return new self;
    }

    public static function setNext($link = false, $icon = null)
    {
        self::$next = [];
        self::$next["link"] = $link;
        self::$next["icon"] = $icon;
        return new self;
    }

    public static function setMonthLink($link)
    {
        self::$month_link = $link;
        return new self;
    }

    public static function setDay(int $day, $content=null, $classes=null)
    {

        self::$days[$day]["content"] = $content;
        self::$days[$day]["classes"] = $classes;

        return new self;
    }

    public static function setDays(array $days)
    {
        self::$days = $days;
        return new self;
    }

    public static function setHolidays(array $holidays)
    {
        self::$holidays = $holidays;
        return new self;
    }

    private static function daysNames($day = null)
    {

        $names[0] = "Domingo";
        $names[1] = "Segunda-Feira";
        $names[2] = "Terça-Feira";
        $names[3] = "Quarta-Feira";
        $names[4] = "Quinta-Feira";
        $names[5] = "Sexta-Feira";
        $names[6] = "Sabado";

        return $day ? $names[$day] : $names;
    }

    private static function monthsNames($month = null)
    {

        $names["01"] = "Janeiro";
        $names["02"] = "Fevereiro";
        $names["03"] = "Março";
        $names["04"] = "Abril";
        $names["05"] = "Maio";
        $names["06"] = "Junho";
        $names["07"] = "Julho";
        $names["08"] = "Agosto";
        $names["09"] = "Setembro";
        $names["10"] = "Outubro";
        $names["11"] = "Novembro";
        $names["12"] = "Dezembro";

        return $month ? $names[$month] : $names;
    }

    public static function generate($year = null, $month = null, $days = null)
    {

        $year = $year ?: date("Y");
        $month = $month ?: date("m");

        self::check($year, $month);

        if ($days) {
            self::setDay($days);
        }

        $first_day_of_month = gmmktime(0, 0, 0, self::$month, 1, self::$year);

        $day_names = self::daysNames();

        $month = gmdate("m", $first_day_of_month);
        $year = gmdate("Y", $first_day_of_month);
        $month_name = self::monthsNames($month);
        $weekday = gmdate("w", $first_day_of_month);

        // Adjust for $first_day
        $weekday_colspan = ($weekday + 7 - self::$first_day) % 7;
        $month_name = self::monthsNames($month);

        $title = mb_convert_encoding(ucfirst($month_name), "UTF-8", mb_detect_encoding($month_name)) . " " . $year;

        // NAVS
        if (self::$prev || self::$next) {

            $nav_dates = self::getMonthYearInfo($year, $month);

            if (self::$prev) {

                $prev_link = self::$prev["link"]
                    ? 'href="' . self::getCurrentUrl() . '?year=' . $nav_dates["previous"]["year"] . '&month=' . $nav_dates["previous"]["month"] . '"'
                    : '';

                $prev_icon = self::$prev["icon"]
                    ? '<i class="' . self::$prev["icon"] . '"></i>'
                    : '&larr;';

                $prev = '<span class="calendar-prev">';
                    $prev .= '<a ' . $prev_link.' data-year="' . $nav_dates["previous"]["year"] . '" data-month="' . $nav_dates["previous"]["month"] . '">';
                        $prev .= $prev_icon;
                    $prev .= '</a>';
                $prev .= '</span>&nbsp;';
            }

            if (self::$next) {

                $next_link = self::$next["link"]
                    ? 'href="' . self::getCurrentUrl() . '?year=' . $nav_dates["next"]["year"] . '&month=' . $nav_dates["next"]["month"] . '"'
                    : '';

                $next_icon = self::$next["icon"]
                    ? '<i class="' . self::$next["icon"] . '"></i>'
                    : '&rarr;';

                $next = '<span class="calendar-next">';
                    $next .= '<a ' . $next_link.' data-year="' . $nav_dates["next"]["year"] . '" data-month="' . $nav_dates["next"]["month"] . '">';
                        $next .= $next_icon;
                    $next .= '</a>';
                $next .= '</span>&nbsp;';
            }
        }

        $calendar = '<table class="calendar '.(self::$class).' cellpadding="0" cellspacing="0">';
            $calendar .= '<caption class="calendar-month" data-year="' . $year . '" data-month="' . $month . '">';
                $calendar .= '<div>';
                    $calendar .= ($prev ?? '') . '<span class="calendar-month-title">' . (self::$month_link ? '<a href="'.htmlspecialchars(self::$month_link).'">'.$title.'</a>' : $title) . '</span>' . ($next ?? '');
                $calendar .= '</div>';
            $calendar .= "</caption>";
        $calendar .= "<tr>";

        $calendar = str_replace("&nbsp;", "", $calendar);

        // SE OS NOMES DOS DIAS SERÃO MOSTRADOS
        if (self::$day_name_length || is_null(self::$day_name_length)) {

            foreach ($day_names as $d) {

                $calendar .= '<th abbr="'.substr($d, 0, 3).'">';
                    $calendar .= is_null(self::$day_name_length) ? $d : substr($d, 0, self::$day_name_length);
                $calendar .= '</th>';
            }
            $calendar .= "</tr>\n<tr>";
        }

        if ($weekday_colspan > 0) {
             // DIAS VAZIOS NO INÍCIO
            $calendar .= '<td colspan="'.$weekday_colspan.'">&nbsp;</td>';
        }

        $today = date("Y-m-d");

        // PERCORRER OS DIAS
        for ($day = 1; $day <= gmdate('t', $first_day_of_month); $day++, $weekday_colspan++) {

            if ($weekday_colspan == 7) {
                $weekday_colspan   = 0; // Inicia uma nova Semana
                $calendar .= "</tr>\n<tr>";
            }

            $current_day = $year . "-" . $month . "-" . str_pad($day, 2, 0, STR_PAD_LEFT);
            $week_day = date("w", strtotime($current_day));

            $class_day  = $current_day == $today ? "calendar-today" : ($current_day<$today ? "calendar-past" : "");
            $class_day .= ($week_day == 0 ? ' calendar-sunday ' : ($week_day == 6 ? ' calendar-saturday ' : ''));

            if (isset(self::$days[$day]) && is_array(self::$days[$day])) {

                $class_day .= !is_null(self::$days[$day]["classes"]) ? ' ' . self::$days[$day]["classes"] : '';

                $calendar .= '<td class="' . $class_day . '">';
                    $calendar .= '<span class="calendar-day" data-day="' . $current_day . '">' . $day . '</span>';

                    if (!is_null(self::$days[$day]["content"])) {
                        $calendar .= self::$days[$day]["content"];
                    }
                $calendar .= '</td>';

            } else {

                $calendar .= '<td class="' . $class_day . '"><div class="calendar-day">' . $day . '</div></td>';
            }
        }

        if ($weekday_colspan != 7) {
            $calendar .= '<td colspan="'.(7-$weekday_colspan).'">&nbsp;</td>';
        }

        $calendar .= "</tr>\n</table>\n";

        return $calendar;
    }

    private static function check($year, $month)
    {

        if ($year) {
            self::setYear($year);
        }

        if ($month) {
            self::setMonth($month);
        }

        if (!self::$year) {
            die("Você deve informar o ano");
        }

        if (!self::$month) {
            die("Você deve informar o mês");
        }
    }

    private static function getCurrentUrl() {

        // Verifica se o site está usando HTTPS
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        // Obtém o nome do host
        $host = $_SERVER['HTTP_HOST'];

        // Obtém o caminho e a query string
        $requestUri = $_SERVER['REQUEST_URI'];

        // Monta a URL completa
        $url = $protocol . '://' . $host . $requestUri;

        return $url;
    }

    private static function getMonthYearInfo($year, $month) {

        // Cria um objeto DateTime para o primeiro dia do mês atual
        $currentDate = new DateTime("$year-$month-01");

        // Obter o mês e o ano anterior
        $prevMonthDate = clone $currentDate;
        $prevMonthDate->modify('-1 month');
        $prevMonth = $prevMonthDate->format('m');
        $prevYear = $prevMonthDate->format('Y');

        // Obter o próximo mês e ano
        $nextMonthDate = clone $currentDate;
        $nextMonthDate->modify('+1 month');
        $nextMonth = $nextMonthDate->format('m');
        $nextYear = $nextMonthDate->format('Y');

        return [
            'previous' => [
                'month' => $prevMonth,
                'year' => $prevYear,
            ],
            'next' => [
                'month' => $nextMonth,
                'year' => $nextYear,
            ],
        ];
    }

}
