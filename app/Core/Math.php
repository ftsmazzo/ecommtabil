<?php
namespace App\Core;

class Math
{
    /**
     * Calcula o valor correspondente a um percentual de um valor.
     *
     * @param float $value Valor total.
     * @param float $percent Percentual a ser calculado.
     * @param int $decimal Número de casas decimais para arredondamento. Padrão: 2.
     * @return float Valor correspondente ao percentual calculado.
     */
    public static function calculateValueByPercentage(float $value, float $percent, int $decimal = 2): float
    {
        return round($value * ($percent / 100), $decimal);
    }

    /**
     * Calcula a porcentagem que $value representa em relação a $total.
     *
     * @param float $value Valor parcial.
     * @param float $total Valor total.
     * @param int|null $decimal Número de casas decimais para arredondamento. Padrão: 1.
     * @param string $dec_point Caracter de separação decimal. Padrão: ".".
     * @return string Porcentagem formatada como string.
     */
    public static function calculatePercentageByValue(float $value, float $total, ?int $decimal = 1, string $dec_point = "."): string
    {
        if ($total == 0) {
            throw new \InvalidArgumentException("O total não pode ser zero.");
        }

        $percent = ($value / $total) * 100;
        $formatted = $decimal === null ? $percent : round($percent, $decimal);
        return str_replace(".", $dec_point, (string)$formatted);
    }

    /**
     * Calcula o valor final após aplicar um desconto percentual.
     *
     * @param float $value Valor original.
     * @param float $percent Percentual de desconto a ser aplicado.
     * @return float Valor final com o desconto aplicado.
     */
    public static function calculateDiscountedValue(float $value, float $percent): float
    {
        return $value - ($value * ($percent / 100));
    }

    /**
     * Calcula a raiz n-ésima de um número.
     *
     * @param float $number Número base.
     * @param int $degree Grau da raiz (n).
     * @return float Resultado da raiz calculada.
     */
    public static function calculateNthRoot(float $number, int $degree): float
    {
        if ($degree <= 0) {
            throw new \InvalidArgumentException("O grau da raiz deve ser maior que zero.");
        }
        return pow($number, 1 / $degree);
    }

    /**
     * Converte um valor numérico para o formato por extenso.
     *
     * @param float $value Valor numérico a ser convertido.
     * @param bool $showCurrency Define se o valor incluirá a moeda. Padrão: true.
     * @param bool $isFeminine Define se as palavras devem ser femininas. Padrão: false.
     * @return string Valor por extenso.
     */
    public static function convertValueToWords(float $value = 0, bool $showCurrency = true, bool $isFeminine = false): string
    {

        // $valor = self::removerFormatacaoNumero($valor);

        $singular = null;
        $plural = null;

        if ($bolExibirMoeda) {

            $singular = array("centavo", "real", "mil", "milhão", "bilhão", "trilhão", "quatrilhão");
            $plural = array("centavos", "reais", "mil", "milhões", "bilhões", "trilhões","quatrilhões");

        } else {

            $singular = array("", "", "mil", "milhão", "bilhão", "trilhão", "quatrilhão");
            $plural = array("", "", "mil", "milhões", "bilhões", "trilhões","quatrilhões");
        }

        $c = array("", "cem", "duzentos", "trezentos", "quatrocentos","quinhentos", "seiscentos", "setecentos", "oitocentos", "novecentos");
        $d = array("", "dez", "vinte", "trinta", "quarenta", "cinquenta","sessenta", "setenta", "oitenta", "noventa");
        $d10 = array("dez", "onze", "doze", "treze", "quatorze", "quinze","dezesseis", "dezesete", "dezoito", "dezenove");
        $u = array("", "um", "dois", "três", "quatro", "cinco", "seis","sete", "oito", "nove");


        if ($bolPalavraFeminina) {

            if ($valor == 1) {

                $u = array("", "uma", "duas", "três", "quatro", "cinco", "seis","sete", "oito", "nove");

            } else {

                $u = array("", "um", "duas", "três", "quatro", "cinco", "seis","sete", "oito", "nove");
            }

            $c = array("", "cem", "duzentas", "trezentas", "quatrocentas","quinhentas", "seiscentas", "setecentas", "oitocentas", "novecentas");
        }


        $z = 0;

        $valor = number_format($valor, 2, ".", ".");
        $inteiro = explode(".", $valor);

        for ($i = 0; $i < count($inteiro); $i++) {

            for ($ii = mb_strlen($inteiro[$i]); $ii < 3; $ii++) {
                $inteiro[$i] = "0" . $inteiro[$i];
            }
        }

        // $fim identifica onde que deve se dar junção de centenas por "e" ou por "," ;)
        $rt = null;
        $fim = count($inteiro) - ($inteiro[count($inteiro) - 1] > 0 ? 1 : 2);

        for ($i = 0; $i < count($inteiro); $i++) {

            $valor = $inteiro[$i];
            $rc = (($valor > 100) && ($valor < 200)) ? "cento" : $c[$valor[0]];
            $rd = ($valor[1] < 2) ? "" : $d[$valor[1]];
            $ru = ($valor > 0) ? (($valor[1] == 1) ? $d10[$valor[2]] : $u[$valor[2]]) : "";

            $r = $rc . (($rc && ($rd || $ru)) ? " e " : "") . $rd . (($rd && $ru) ? " e " : "") . $ru;
            $t = count($inteiro) - 1 - $i;
            $r .= $r ? " " . ($valor > 1 ? $plural[$t] : $singular[$t]) : "";
            if ($valor == "000")
                $z++;
            elseif ($z > 0)
                $z--;

            if (($t == 1) && ($z > 0) && ($inteiro[0] > 0))
                $r .= (($z > 1) ? " de " : "") . $plural[$t];

            if ($r)
                $rt = $rt . ((($i > 0) && ($i <= $fim) && ($inteiro[0] > 0) && ($z < 1)) ? (($i < $fim) ? ", " : " e ") : " ") . $r;
        }

        $rt = mb_substr($rt, 1);

        return($rt ? trim($rt) : "zero");

    }

    /**
     * Calcula a distância entre duas coordenadas geográficas.
     *
     * @param string $origin Coordenada de origem no formato "latitude,longitude".
     * @param string $destiny Coordenada de destino no formato "latitude,longitude".
     * @param string $unit Unidade de medida: "km" (quilômetros), "m" (metros), "mi" (milhas), "ft" (pés), "yd" (jardas). Padrão: "km".
     * @return float Distância calculada na unidade especificada.
     */
    public static function calculateGeoDistance(string $origin, string $destiny, string $unit = "km"): float
    {
        $units = [
            "km" => 1,
            "m" => 1000,
            "mi" => 0.621371,
            "ft" => 3280.84,
            "yd" => 1093.61
        ];

        if (!array_key_exists($unit, $units)) {
            throw new \InvalidArgumentException("Unidade inválida fornecida: $unit");
        }

        $originCoords = explode(",", $origin);
        $destinyCoords = explode(",", $destiny);

        if (count($originCoords) !== 2 || count($destinyCoords) !== 2) {
            throw new \InvalidArgumentException("Coordenadas devem estar no formato 'latitude,longitude'.");
        }

        [$lat1, $lon1] = array_map('deg2rad', $originCoords);
        [$lat2, $lon2] = array_map('deg2rad', $destinyCoords);

        $dist = 6371 * acos(cos($lat1) * cos($lat2) * cos($lon2 - $lon1) + sin($lat1) * sin($lat2));
        return $dist * $units[$unit];
    }
}
