<?php
/**
 * Funcões do Sistema
 */

function base_url() {
    return URL_BASE;
}

function base_path(string $path = ''): string
{
    $configPath = \App\Core\Config::get('app.path');

    $root = $configPath
        ? trim($configPath)
        : trim($_SERVER['DOCUMENT_ROOT']);

    $root = rtrim($root, '/\\');
    $root = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root);

    if ($path) {
        $path = ltrim(trim($path), '/\\');
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        return $root . DIRECTORY_SEPARATOR . $path;
    }

    return $root;
}

function printa($array, $die = false) {

    echo '<pre style="background:#fafafa;padding:20px;">';
        print_r($array);
    echo '</pre>';

    if ($die) die();
}

function table($array, $die=false) {

    $array = (array)$array;

    if (count($array)>0) {

        echo '<table class="table-depured">';
            echo '<thead>';
                echo '<tr>';
                    foreach (array_keys(((array)$array[0])) as $h) {
                        echo '<th>'.$h.'</th>';
                    }
                echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
                foreach ($array as $line) {
                    echo '<tr>';
                        foreach ($line as $v) {
                            echo '<td>'.$v.'</td>';
                        }
                    echo '</tr>';
                }
            echo '</tbody>';
        echo '</table>';
    }

    if ($die) die();
}

// function dd($a)
// {
//     ini_set('xdebug.var_display_max_depth', -1);
//     ini_set('xdebug.var_display_max_children', -1);
//     ini_set('xdebug.var_display_max_data', -1);
//     var_dump($a);
//     die;
// }

// function dump($a)
// {
//     var_dump($a);
// }

if (!function_exists('ddx')) {
    function ddx($var) {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        echo "<small><b>Debug em:</b> {$bt['file']} : {$bt['line']}</small><br>";
        dd($var);
    }
}


function storage()
{
    return URL_BASE . "/storage";
}

function layout($filename)
{
    return URL_BASE . "/storage/layout/" . $filename;
}

function media($path, $filename, $time = false)
{
    return !empty($filename)
        ? URL_BASE . "/storage/media/" . $path . "/" . $filename . ($time ? "?v=" . time() : "")
        : null;
}

function root()
{
    return URL_BASE;
}

function is_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function is_json($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

function datebr($data): null|string
{
    if ($data=="now") $data = date("Y-m-d");
    return !empty($data) ? date("d/m/Y", strtotime($data)) : null;
}

function dateus($data): string
{
    if ($data=="now") $data = date("Y-m-d");
    return !empty($data) ? date("Y-m-d", strtotime($data)) : null;
}

function datetimebr($data, $sec=false, $format=null): null|string
{
    if ($data=="now") $data = date($format??"Y-m-d H:i:s");
    $format = $format ?? 'd/m/Y H:i'.($sec ? ':s' : '');
    return !empty($data) ? date($format, strtotime($data)) : null;
}

function timebr($hour, $sec = false): string
{
    if ($hour=="now") $hour = date("H:i:s");
    $format = 'H:i'.($sec ? ':s' : '');
    return date($format, strtotime($hour));
}

function format_date($date)
{
    return implode('-', array_reverse(explode('/', trim($date))));
}

function format_datetime($datetime)
{
    $parts = explode(' ', $datetime);
    $date = implode('-', array_reverse(explode('/', trim($parts[0]))));
    $time = $parts[1] ?? '00:00:00';
    return $date . ' ' . $time;
}

function asset(string $path, $time = true): string
{
    $file = URL_BASE . "/public/assets/{$path}";
    $fileOnDir = dirname(__DIR__, 1)."/public/assets/{$path}";
    if ($time && file_exists($fileOnDir)) {
        $file .= "?time=".filemtime($fileOnDir);
    }
    return $file;
}

function photo($photo, $path = "user") {
    $token = generateToken($photo); // Gera um token único para a imagem
    return URL_BASE . "/" . $path . "/photo/" . encrypt($photo, "_") . "?token=" . $token;
}

function generateToken($photo) {
    $_SESSION['photo_token'] = hash('sha256', $photo . SECRET_KEY); // Usa uma chave secreta para gerar o token
    return $_SESSION['photo_token'];
}

function lists($name)
{
    ini_set("allow_url_include", "1");
    $file = URL_BASE . "/storage/lists/" . $name . ".list";
    return parse_ini_file( $file );
}

function input_upper($field, $upper)
{
    if (in_array($field,$upper) || (isset($upper[0]) && $upper[0]=="*")){
        return "input-upper";
    }
}

function is_upper($field, $upper)
{
    return in_array($field, $upper) || (isset($upper[0]) && $upper[0]=="*");
}

function is_required_field(string $field, array|string|null $required): bool
{
    if (is_string($required)) {
        $required = array_filter(array_map('trim', explode(',', $required)));
    }

    if (!is_array($required) || empty($required)) {
        return false;
    }

    return in_array($field, $required, true) || (isset($required[0]) && $required[0] === '*');
}

function required_mark(string $field, array|string|null $required): string
{
    return is_required_field($field, $required)
        ? ' <span class="text-danger">*</span>'
        : '';
}

function required_title(string $field, array|string|null $required): string
{
    return is_required_field($field, $required)
        ? ' title="Campo Obrigatório"'
        : '';
}

function only_numbers($text)
{
    return preg_replace('/[^\d]/', '', $text);
}

function cpf($cpf)
{
    return substr($cpf, 0, 3). '.' .
           substr($cpf, 3, 3). '.' .
           substr($cpf, 6, 3). '-' .
           substr($cpf, 9, 2);
}

function phone(?string $valor): string
{
    $v = preg_replace('/[^0-9]/', '', (string) $valor);
    $len = strlen($v);

    if ($len === 11) {
        return '(' . substr($v, 0, 2) . ') ' . substr($v, 2, 5) . '-' . substr($v, 7, 4);
    }

    if ($len === 10) {
        return '(' . substr($v, 0, 2) . ') ' . substr($v, 2, 4) . '-' . substr($v, 6, 4);
    }

    return $valor ?? '';
}

function cnpj($valor){

    if (!empty($valor)) {

        return substr($valor, 0, 2). '.' .
               substr($valor, 2, 3). '.' .
               substr($valor, 5, 3). '/' .
               substr($valor, 8, 4). '-' .
               substr($valor, 12, 2);
    } else {
        return $valor;
    }
}

function colorContrast(string $corFundo): string
{
    $corFundo = trim($corFundo);

    if ($corFundo === '') {
        return 'black';
    }

    $corFundo = ltrim($corFundo, '#');

    if (strlen($corFundo) === 3) {
        $r = hexdec(str_repeat(substr($corFundo, 0, 1), 2));
        $g = hexdec(str_repeat(substr($corFundo, 1, 1), 2));
        $b = hexdec(str_repeat(substr($corFundo, 2, 1), 2));
    } elseif (strlen($corFundo) === 6) {
        $r = hexdec(substr($corFundo, 0, 2));
        $g = hexdec(substr($corFundo, 2, 2));
        $b = hexdec(substr($corFundo, 4, 2));
    } else {
        return 'black';
    }

    $brilho = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

    return ($brilho > 128) ? 'black' : 'white';
}

function int2cnpj($valor){

    return substr($valor, 0, 2). '.' .
           substr($valor, 2, 3). '.' .
           substr($valor, 5, 3). '/' .
           substr($valor, 8, 4). '-' .
           substr($valor, 12, 2);
}

function decimal2float($valor)
{
    return str_replace(",", ".", $valor);
}

function money2float($valor)
{

    $comma = strpos($valor, ",");

    if ($comma === false) {
        return $valor;
    } else {
        return str_replace(",", ".", str_replace(".", "", $valor));
    }
}

function moedabr($valor, $cifrao = false)
{
    if (!empty($valor)) {
        return ($cifrao ? "R$ " : "") . number_format($valor, 2, ",", ".");
    } else {
        return "0,00";
    }
}

function format_percent($valor)
{
    return number_format($valor, 1, ",", "");
}

function formatPercent($valor, $decimais = 1, $symbol = "%") {
    if ($valor === null) return '';
    $valor = round($valor, 1);
    return fmod($valor, 1.0) == 0.0
        ? number_format($valor, 0, ',', '.') . $symbol
        : number_format($valor, $decimais, ',', '.') . $symbol;
}



// function age($date){

//     $time = strtotime($date);
//     if($time === false){
//       return '';
//     }

//     $year_diff = '';
//     $date = date('Y-m-d', $time);
//     list($year,$month,$day) = explode('-',$date);
//     $year_diff = date('Y') - $year;
//     $month_diff = date('m') - $month;
//     $day_diff = date('d') - $day;
//     if ($day_diff < 0 || $month_diff < 0) $year_diff--;

//     return $year_diff;
// }

function codeurl($texto) {
    $array1 = array('-', 'ª','á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','º','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ','Á','À','Â','Ã','Ä','É','È','Ê','Ë','Í','Ì','Î','Ï','Ó','Ò','Ô','Õ','Ö','Ú','Ù','Û','Ü','Ç','Ñ');
    $array2 = array('', 'a','a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','o','u','u','u','u','c','n','A','A','A','A','A','E','E','E','E','I','I','I','I','O','O','O','O','O','U','U','U','U','C','N');
    $texto  = str_replace($array1, $array2, $texto);
    $texto  = preg_replace('/( )/i', '-', $texto);
    $texto  = preg_replace('/[^a-z0-9\-]/i', '', $texto);
    $texto  = preg_replace('/--/i', '-', $texto);
    return strtolower($texto);
}

function base64ToImage($base64_string, $output_file) {
    $file = fopen($output_file, "wb");
    $data = explode(',', $base64_string);
    fwrite($file, base64_decode($data[1]));
    fclose($file);
    return $output_file;
}

function valorPorExtenso( $valor = 0, $bolExibirMoeda = true, $bolPalavraFeminina = false ) {

    // $valor = self::removerFormatacaoNumero( $valor );

    $singular = null;
    $plural = null;

    if ( $bolExibirMoeda )
    {
        $singular = array("centavo", "real", "mil", "milhão", "bilhão", "trilhão", "quatrilhão");
        $plural = array("centavos", "reais", "mil", "milhões", "bilhões", "trilhões","quatrilhões");
    }
    else
    {
        $singular = array("", "", "mil", "milhão", "bilhão", "trilhão", "quatrilhão");
        $plural = array("", "", "mil", "milhões", "bilhões", "trilhões","quatrilhões");
    }

    $c = array("", "cem", "duzentos", "trezentos", "quatrocentos","quinhentos", "seiscentos", "setecentos", "oitocentos", "novecentos");
    $d = array("", "dez", "vinte", "trinta", "quarenta", "cinquenta","sessenta", "setenta", "oitenta", "noventa");
    $d10 = array("dez", "onze", "doze", "treze", "quatorze", "quinze","dezesseis", "dezesete", "dezoito", "dezenove");
    $u = array("", "um", "dois", "três", "quatro", "cinco", "seis","sete", "oito", "nove");


    if ( $bolPalavraFeminina )
    {

        if ($valor == 1)
        {
            $u = array("", "uma", "duas", "três", "quatro", "cinco", "seis","sete", "oito", "nove");
        }
        else
        {
            $u = array("", "um", "duas", "três", "quatro", "cinco", "seis","sete", "oito", "nove");
        }


        $c = array("", "cem", "duzentas", "trezentas", "quatrocentas","quinhentas", "seiscentas", "setecentas", "oitocentas", "novecentas");


    }


    $z = 0;

    $valor = number_format( $valor, 2, ".", "." );
    $inteiro = explode( ".", $valor );

    for ( $i = 0; $i < count( $inteiro ); $i++ )
    {
        for ( $ii = mb_strlen( $inteiro[$i] ); $ii < 3; $ii++ )
        {
            $inteiro[$i] = "0" . $inteiro[$i];
        }
    }

    // $fim identifica onde que deve se dar junção de centenas por "e" ou por "," ;)
    $rt = null;
    $fim = count( $inteiro ) - ($inteiro[count( $inteiro ) - 1] > 0 ? 1 : 2);
    for ( $i = 0; $i < count( $inteiro ); $i++ )
    {
        $valor = $inteiro[$i];
        $rc = (($valor > 100) && ($valor < 200)) ? "cento" : $c[$valor[0]];
        $rd = ($valor[1] < 2) ? "" : $d[$valor[1]];
        $ru = ($valor > 0) ? (($valor[1] == 1) ? $d10[$valor[2]] : $u[$valor[2]]) : "";

        $r = $rc . (($rc && ($rd || $ru)) ? " e " : "") . $rd . (($rd && $ru) ? " e " : "") . $ru;
        $t = count( $inteiro ) - 1 - $i;
        $r .= $r ? " " . ($valor > 1 ? $plural[$t] : $singular[$t]) : "";
        if ( $valor == "000")
            $z++;
        elseif ( $z > 0 )
            $z--;

        if ( ($t == 1) && ($z > 0) && ($inteiro[0] > 0) )
            $r .= ( ($z > 1) ? " de " : "") . $plural[$t];

        if ( $r )
            $rt = $rt . ((($i > 0) && ($i <= $fim) && ($inteiro[0] > 0) && ($z < 1)) ? ( ($i < $fim) ? ", " : " e ") : " ") . $r;
    }

    $rt = mb_substr( $rt, 1 );

    return($rt ? trim( $rt ) : "zero");

}

// function months($month=null, $lenght=null, $language="br") {

//     $months_br = [
//         "01"=>"Janeiro",
//         "02"=>"Fevereiro",
//         "03"=>"Março",
//         "04"=>"Abril",
//         "05"=>"Maio",
//         "06"=>"Junho",
//         "07"=>"Julho",
//         "08"=>"Agosto",
//         "09"=>"Setembro",
//         "10"=>"Outubro",
//         "11"=>"Novembro",
//         "12"=>"Dezembro"
//     ];

//     $months_en = [
//         "01"=>"January",
//         "02"=>"February",
//         "03"=>"March",
//         "04"=>"April",
//         "05"=>"May",
//         "06"=>"June",
//         "07"=>"July",
//         "08"=>"August",
//         "09"=>"September",
//         "10"=>"October",
//         "11"=>"November",
//         "12"=>"December"
//     ];

//     $array = $language=="br" ? $months_br : $months_en;

//     if ($month) {

//         return $lenght ? substr($array[$month], 0, $lenght) : $array[$month];

//     } else {

//         if ($lenght) {
//             foreach ($array as $m => $v) {
//                 $array[$m] = substr($v, 0, $lenght);
//             }
//         }

//         return $array;
//     }
// }

function nome_mes($mes = null, $len = null) {

    $mes = str_pad($mes, 2, '0', STR_PAD_LEFT);

    $meses = [
        "01" => "Janeiro",
        "02" => "Fevereiro",
        "03" => "Março",
        "04" => "Abril",
        "05" => "Maio",
        "06" => "Junho",
        "07" => "Julho",
        "08" => "Agosto",
        "09" => "Setembro",
        "10" => "Outubro",
        "11" => "Novembro",
        "12" => "Dezembro"
    ];

    return $mes ? ($len ? substr($meses[$mes], 0, $len) : $meses[$mes]) : $meses;
}

// function dia_semana($dia, $len=null) {

//     $dias = array(0=>"Domingo", 1=>"Segunda-Feira", 2=>"Terça-Feira", 3=>"Quarta-Feira", 4=>"Quinta-Feira", 5=>"Sexta-Feira", 6=>"Sábado");
//     if ($len) return substr($dias[$dia], 0, $len);
//     else      return $dias[$dia];
// }

// function PrimeiroDiaSemana() {

//     $dia_da_semana = date('w');
//     $dia = date('d');
//     $mes = date('m');
//     $ano = date('Y');
//     $primeiro_dia = mktime ( 0, 0, 0, $mes, $dia - $dia_da_semana, $ano );
//     return strftime("%Y-%m-%d", $primeiro_dia);
// }

// function UltimoDiaSemana() {

//     $dia_da_semana = date('w');
//     $dia = date('d');
//     $mes = date('m');
//     $ano = date('Y');
//     $somar_dias = 6 - $dia_da_semana;
//     $ultimo_dia   = mktime ( 0, 0, 0, $mes, $dia + $somar_dias, $ano );
//     return strftime("%Y-%m-%d", $ultimo_dia);
// }

// function calculaDias($data_final, $data_inicial=null) {

//     $data_inicial = ($data_inicial) ? implode('-',array_reverse(explode('/', $data_inicial))) : date('Y-m-d');
//     $data_final   = implode('-',array_reverse(explode('/', $data_final)));

//     // Calcula a diferença em segundos entre as datas
//     $diferenca = strtotime($data_final) - strtotime($data_inicial);

//     //Calcula a diferença em dias
//     $dias = (int)floor( $diferenca / (60 * 60 * 24));

//     return $dias;
// }

function isMobile() {

    $useragent=$_SERVER['HTTP_USER_AGENT'];
    if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4)))
        return true;
    else
        return false;
}

// function dateRange($first, $last, $step = '+1 day', $format = 'd/m/Y' ) {

//     $dates   = array();
//     $current = strtotime($first);
//     $last    = strtotime($last);

//     while( $current <= $last ) {

//         $dates[] = date($format, $current);
//         $current = strtotime($step, $current);
//     }

//     return $dates;
// }

function base64_urlencode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64_urldecode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

function mostraTempo($date) {

    $data_inicial = date('Y-m-d H:i:s');
    $data_final   = $date;

    $segundos = strtotime($data_inicial) - strtotime($data_final);

    $minutos  = round($segundos / 60);
    $horas    = round($minutos / 60);
    $dias     = round($horas / 24);
    $semanas  = round($dias / 7);
    $meses    = round($dias / 30);
    $anos     = round($dias / 365);

    if ($segundos<10)                    $retorno = 'Agora mesmo';
    else if ($segundos<60)               $retorno = $segundos.' segundos atrás';
    else if ($minutos==1)                $retorno = $minutos.' minuto atrás';
    else if ($minutos>1 && $minutos<60)  $retorno = $minutos.' minutos atrás';
    else if ($horas==1)                  $retorno = $horas.' hora atrás';
    else if ($horas>1 && $horas<24)      $retorno = $horas.' horas atrás';
    else if ($dias==1)                   $retorno = $dias.' dia atrás';
    else if ($dias>1 && $dias<7)         $retorno = $dias.' dias atrás';
    else if ($semanas==1)                $retorno = $semanas.' semana atrás';
    else if ($semanas>1 && $semanas<4)   $retorno = $semanas.' semanas atrás';
    else if ($meses==1)                  $retorno = $meses.' mês atrás';
    else if ($meses>1 && $meses<12)      $retorno = $meses.' meses atrás';
    else if ($anos==1)                   $retorno = $anos.' ano atrás';
    else if ($anos>1)                    $retorno = $anos.' anos atrás';

    return $retorno;
}

function parseHeaders( $headers ) {

    $head = array();
    foreach( $headers as $k=>$v )
    {
        $t = explode( ':', $v, 2 );
        if( isset( $t[1] ) )
            $head[ trim($t[0]) ] = trim( $t[1] );
        else
        {
            $head[] = $v;
            if( preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#",$v, $out ) )
                $head['reponse_code'] = intval($out[1]);
        }
    }
    return $head;
}

function preenche($texto, $num=5, $caracter='0', $side='left') {

    if ($side=='left')      return str_pad($texto, $num, $caracter, STR_PAD_LEFT);
    else if ($side=='both') return str_pad($texto, $num, $caracter, STR_PAD_BOTH);
    else                    return str_pad($texto, $num, $caracter); // RIGHT default

}

function sec2hour($seconds, $sec = true, $sepH = ":", $sepM = ":") {

    $horas    = floor($seconds / 3600);
    $minutos  = floor(($seconds - ($horas * 3600)) / 60);
    $segundos = floor($seconds % 60);

    return str_pad($horas, 2, 0, STR_PAD_LEFT)
           .$sepH
           .str_pad($minutos, 2, 0, STR_PAD_LEFT)
           .($sec ? $sepM : "")
           .($sec ? str_pad($segundos, 2, 0, STR_PAD_LEFT) : "");
}

function reArrayFiles(&$file_post) {

    $file_ary = array();
    $file_count = count($file_post['name']);
    $file_keys = array_keys($file_post);

    for ($i=0; $i<$file_count; $i++) {
        foreach ($file_keys as $key) {
            $file_ary[$i][$key] = $file_post[$key][$i];
        }
    }

    return $file_ary;
}

function show_link($texto, $blank=true) {

    if (!is_string ($texto))
        return $texto;

     $texto = str_replace("https://", "", str_replace("http://", "", $texto));

     $er = "/(http:\/\/(www\.|.*?\/)?|www\.)([a-zA-Z0-9]+|_|-)+(\.(([0-9a-zA-Z]|-|_|\/|\?|=|&)+))+/i";
     preg_match_all ($er, $texto, $match);

     foreach ($match[0] as $link) {

         //coloca o 'http://' caso o link não o possua
         $link_completo = (stristr($link, "http://") === false) ? "http://" . $link : $link;

         $link_len = strlen ($link);

         //troca "&" por "&", tornando o link válido pela W3C
         $web_link = str_replace ("&", "&amp;", $link_completo);

         $target = ($blank) ? 'target="_blank"' : '';

         $texto = str_ireplace ($link, '<a href="' . strtolower($web_link) . '" '.$target.'>'. (($link_len > 60) ? substr ($web_link, 0, 25). '...'. substr ($web_link, -15) : $web_link) .'</a>', $texto);

         return $texto;
     }

}

function diasRestantes($date) {

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

function linkWhatsapp($number, $text = "Olá! Venho do site e gostaria de um orçamento.", $ddi = 55) {

    $fone = preg_replace("/[^0-9]/", "", $ddi . $number);

    $useragent = $_SERVER['HTTP_USER_AGENT'];
    if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| ||a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4))) {

        return "https://api.whatsapp.com/send?phone=" . $fone . (!empty($text) ? "&text=" . urlencode($text) : '');

    } else {

        return "https://web.whatsapp.com/send?phone=" . $fone . (!empty($text) ? "&text=" . urlencode($text) : '');
    }
}

function welcome($nome) {
    $hora = date("H");
    $diasemana = date('w');
    $dia = date('d');
    $mes = date('n');
    $ano = date ('Y');

    $dias_da_semana = [0=> "Domingo", 1=>"Segunda-Feira", 2=>"Terça-Feira", 3=>"Quarta-Feira", 4=>"Quinta-Feira", 5=>"Sexta-Feira", 6=>"Sábado"];
    $meses_do_ano   = [1=>"Janeiro", 2=>"Fevereiro", 3=>"Março", 4=>"Abril", 5=>"Maio", 6=>"Junho", 7=>"Julho", 8=>"Agosto", 9=>"Setembro", 10=>"Outubro", 11=>"Novembro", 12=>"Dezembro"];

    if (($hora >= 0) && ($hora < 12)) echo "Bom dia, ";
    else if (($hora >= 12) && ($hora < 18)) echo "Boa tarde, ";
    else echo "Boa Noite, ";
    echo $nome;
    echo " | ".$dias_da_semana[$diasemana].", ".$dia." de ".$meses_do_ano[$mes]." de ".$ano;
}

function birthday($date) {

    $dia = date('d', strtotime($date));
    $mes = date('m', strtotime($date));

    if ($dia==date('d') && $mes==date('m')) return true;
    else 									return false;
}

function imagemVimeo($link, $tamanho = 'thumbnail_medium'){

    if (preg_match_all('/^http[s]?:\/\/vimeo\.com\/([0-9]+)[\/]?$/', $link, $saida)){

      @$retornoApi = file_get_contents("http://vimeo.com/api/v2/video/".$saida[1][0].".php");

      if ($retornoApi){

        $video = unserialize($retornoApi);

        if (is_array($video)){

          if (isset($video[0][$tamanho])){

            return $video[0][$tamanho];
          }
        }
      }
    }

    return 'http://seudominio.com.br/link-img-error.jpg';
}

function calculaRaiz($numero, $grau){
    return pow($numero, (1/$grau));
}

function capitalize($text) {
    // Converte todo o texto para minúsculas
    $text = mb_strtolower($text, 'UTF-8');

    // Divide o texto em palavras
    $words = explode(" ", $text);

    // Palavras a serem excluídas da capitalização
    $exclude = array('da', 'das', 'de', 'do', 'dos', 'e', 'em', 'na', 'no');

    $new = "";

    foreach ($words as $word) {
        // Verifica se a palavra está na lista de exclusão
        if (in_array($word, $exclude)) {
            // Mantém a palavra em minúsculas
            $new .= " " . mb_strtolower($word, 'UTF-8');
        } else {
            // Capitaliza a primeira letra da palavra e mantém as demais letras em minúsculas
            $new .= " " . mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
        }
    }

    return trim($new); // Remove espaços em branco extras
}

function dateDiff($dateStart, $dateEnd) {

    if (empty($dateStart) || empty($dateEnd)) {
        return null;
    }

    try {
        $d1 = new DateTime($dateStart);
        $d2 = new DateTime($dateEnd);

        $diff = $d1->diff($d2);

        $days = $diff->days;

        // Se invert = 1 → d2 < d1 → negativo
        return $diff->invert ? -$days : $days;

    } catch (Exception $e) {
        return null;
    }
}


function dateAtual() {
    return date('Y-m-d H:i:s');
}

function __output_header__($__success = true, $__message = null, $_dados = array()){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        array(
            'success' => $__success,
            'message' => $__message,
            'dados'   => $_dados
        )
    );
    # por ser a ultima funcao, podemos matar o processo aqui.
    exit;
}

function response_json($data, $code=200, $iso="utf-8") {

    header_remove();

    header('Access-Control-Allow-Origin: *');
    // header('Access-Control-Allow-Credentials: true');
    // header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    // header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
    // header('Access-Control-Max-Age: 86400');

    if ($iso)
        header("Content-type: application/json; charset=".$iso);
    else
        header("Content-type: application/json");

    http_response_code($code);
    echo json_encode($data, JSON_NUMERIC_CHECK);
    exit();
}

function unauthorized() {
    response_json(["message"=>"Unauthorized"], 401);
}

function time_left($seconds) {

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

function distancia($origem, $destino, $unidade="km") {

    $lat1 = explode(",", $origem)[0];
    $lon1 = explode(",", $origem)[1];

    $lat2 = explode(",", $destino)[0];
    $lon2 = explode(",", $destino)[1];

    $lat1 = deg2rad($lat1);
    $lat2 = deg2rad($lat2);
    $lon1 = deg2rad($lon1);
    $lon2 = deg2rad($lon2);

    $dist = (6371 * acos( cos( $lat1 ) * cos( $lat2 ) * cos( $lon2 - $lon1 ) + sin( $lat1 ) * sin($lat2) ) );
    $dist = number_format($dist, 2, '.', '');

    if ($unidade=="km")
        return $dist;
    elseif ($unidade=="m")
        return $dist * 1000;

}

function remove_acentos($texto) {

    $array1 = array('á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ','Á','À','Â','Ã','Ä','É','È','Ê','Ë','Í','Ì','Î','Ï','Ó','Ò','Ô','Õ','Ö','Ú','Ù','Û','Ü','Ç','Ñ');
    $array2 = array('a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n','A','A','A','A','A','E','E','E','E','I','I','I','I','O','O','O','O','O','U','U','U','U','C','N');
    $texto  = str_replace( $array1, $array2, $texto);
    return $texto;
}

function addMonth(\DateTime $date, $monthToAdd) {

    $year  = $date->format('Y');
    $month = $date->format('n');
    $day   = $date->format('d');

    $year += floor($monthToAdd / 12);
    $monthToAdd = $monthToAdd % 12;
    $month += $monthToAdd;
    if ($month > 12) {
        $year ++;
        $month = $month % 12;
        if ($month === 0) {
            $month = 12;
        }
    }

    if (! checkdate($month, $day, $year)) {
        $newDate = \DateTime::createFromFormat('Y-n-j', $year . '-' . $month . '-1');
        $newDate->modify('last day of');
    } else {
        $newDate = \DateTime::createFromFormat('Y-n-d', $year . '-' . $month . '-' . $day);
    }
    $newDate->setTime($date->format('H'), $date->format('i'), $date->format('s'));

    return $newDate->format('Y-m-d');
}

function validaCPF($cpf) {

    // Extrai somente os números
    $cpf = preg_replace( '/[^0-9]/is', '', $cpf );

    // Verifica se foi informado todos os digitos corretamente
    if (strlen($cpf) != 11) {
        return false;
    }

    // Verifica se foi informada uma sequência de digitos repetidos. Ex: 111.111.111-11
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }

    // Faz o calculo para validar o CPF
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;

}

function validaCNPJ($cnpj)
{
	$cnpj = preg_replace('/[^0-9]/', '', (string) $cnpj);

	// Valida tamanho
	if (strlen($cnpj) != 14)
		return false;

	// Verifica se todos os digitos são iguais
	if (preg_match('/(\d)\1{13}/', $cnpj))
		return false;

	// Valida primeiro dígito verificador
	for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++)
	{
		$soma += $cnpj[$i] * $j;
		$j = ($j == 2) ? 9 : $j - 1;
	}

	$resto = $soma % 11;

	if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto))
		return false;

	// Valida segundo dígito verificador
	for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++)
	{
		$soma += $cnpj[$i] * $j;
		$j = ($j == 2) ? 9 : $j - 1;
	}

	$resto = $soma % 11;

	return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
}

function validaCNPJBR($cnpj)
{
    $cnpj = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $cnpj));

    if (strlen($cnpj) !== 14) {
        return false;
    }

    if (preg_match('/^(.)\1{13}$/', $cnpj)) {
        return false;
    }

    $charValue = static function (string $char): ?int {
        if (ctype_digit($char)) {
            return (int) $char;
        }

        if (ctype_alpha($char)) {
            return ord($char) - 48;
        }

        return null;
    };

    $calcDigit = static function (string $base, array $weights) use ($charValue): ?int {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $value = $charValue($base[$index] ?? '');

            if ($value === null) {
                return null;
            }

            $sum += $value * $weight;
        }

        $mod = $sum % 11;
        return $mod < 2 ? 0 : 11 - $mod;
    };

    $base = substr($cnpj, 0, 12);
    $digit1 = $calcDigit($base, [5,4,3,2,9,8,7,6,5,4,3,2]);

    if ($digit1 === null) {
        return false;
    }

    $digit2 = $calcDigit($base . $digit1, [6,5,4,3,2,9,8,7,6,5,4,3,2]);

    if ($digit2 === null) {
        return false;
    }

    return $cnpj[12] === (string) $digit1 && $cnpj[13] === (string) $digit2;
}

function clear_space($text)
{
    return str_replace(" ", "", $text);
}


function daysBetween($start, $end, $days=null) {

    $start = new DateTime($start);
    $end = new DateTime($end);
    $end->modify('+1 day');

    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval ,$end);

    if ($days) {

        $days = (array)$days;

        foreach($period as $date) {

            $day_of_week = $date->format("w");

            if (in_array($day_of_week, $days))
                $dates[] = $date->format("Y-m-d");
        }

    } else {

        foreach($period as $date){

            $dates[] = $date->format("Y-m-d");
        }
    }

    return $dates;

}

function hashtag($text, $link=null) {

    $replace = $link ? '<a href="'.$link.'%23\2">#\2</a>' : '<span>#\2</span>';

    $text = preg_replace('/(^|\s)#(\w*[a-zA-Z_]+\w*)/', '\1'.$replace, $text);

    return $text;

}

function users($text, $link=null) {

    $replace = $link ? '<a href="'.$link.'%23\2">@\2</a>' : '<span>@\2</span>';

    $text = preg_replace('/(^|\s)@(\w*[a-zA-Z_]+\w*)/', '\1'.$replace, $text);

    return $text;

}

function rearrangeFiles( $arr ) {
    foreach( $arr as $key => $all ) {
        foreach( $all as $i => $val ) {
            $new[$i][$key] = $val;
        }
    }
    return $new;
}

function limitaTexto($text, $len, $s = "...", $start = 0)
{
    if (empty($text)) {
        return null;
    }

    // Garante UTF-8
    $encoding = 'UTF-8';

    $texto = mb_substr($text, $start, $len, $encoding);

    if (mb_strlen($text, $encoding) > $len) {
        $texto .= $s;
    }

    return $texto;
}

function milhar($valor) {

    return !is_null($valor) ? number_format($valor, 0, "", ".") : 0;
}

function nreg($array) {

    return isset($array) ? count($array) : 0;
}

function is_home() {

    $pages = explode("/", $_SERVER["SCRIPT_NAME"]);
    $page  = end($pages);
    return in_array($page, ["index.php", "index.html", "index.htm"]);
}

function highlight($text)
{
    if (!empty($text)) {
        $text = nl2br(str_replace("{{", '<span class="highlight">', str_replace("}}", "</span>", $text)));
    }
    return $text;
}

function unhighlight($text)
{
    return str_replace(["{{", "}}"], ["", ""], $text);
}

function remove_highlight($text)
{
    return str_replace(['<span class="highlight">', "</span>"], ["", ""], $text);
}

function detach($text)
{
    // Separar frase em array
    $texts = explode(" ", $text);
    $phrase = $texts[0];
    unset($texts[0]);
    $phrase .= ' <span class="highlight">' . implode(" ", $texts) . '</span>';

    return nl2br($phrase);
}

function _t($index, $alt=null) {

    global $lang;
    $text = isset($lang[$index]) ? $lang[$index] : (isset($alt) ? $alt : $index);
    return $text;
}

function MostrarLink($texto, $blank=true) {

    if (!is_string ($texto))
        return $texto;

     $texto = str_replace("https://", "", str_replace("http://", "", $texto));

     $er = "/(http:\/\/(www\.|.*?\/)?|www\.)([a-zA-Z0-9]+|_|-)+(\.(([0-9a-zA-Z]|-|_|\/|\?|=|&)+))+/i";
     preg_match_all ($er, $texto, $match);

     foreach ($match[0] as $link) {

         //coloca o 'http://' caso o link não o possua
         $link_completo = (stristr($link, "http://") === false) ? "http://" . $link : $link;

         $link_len = strlen ($link);

         //troca "&" por "&", tornando o link válido pela W3C
         $web_link = str_replace ("&", "&amp;", $link_completo);

         $target = ($blank) ? 'target="_blank"' : '';

         $texto = str_ireplace ($link, "<a href=\"" . strtolower($web_link) . "\" ".$target.">". (($link_len > 60) ? substr ($web_link, 0, 25). "...". substr ($web_link, -15) : $web_link) ."</a>", $texto);

     }

     return $texto;
}

// Function to get the user IP address
function getip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function encrypt($value, $sep="$")
{
    $chaves = ['acefwhjlmnpqstuwxy123456789','acdfhiklmnvprtuvwxz123456789'];
    $chave = $chaves[ array_rand($chaves) ];
    $value64 = rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $pass = substr(str_shuffle($chave),0,rand(1,strlen($chave))).
            $sep.
            strrev(substr($value64,ceil(strlen($value64)/4)*3,ceil(strlen($value64)/4))).
            $sep.
            substr(str_shuffle($chave),0,rand(1,strlen($chave))).
            $sep.
            strrev(substr($value64,ceil(strlen($value64)/4)*2,ceil(strlen($value64)/4))).
            $sep.
            substr(str_shuffle($chave),0,rand(1,strlen($chave))).
            $sep.
            strrev(substr($value64,ceil(strlen($value64)/4)*1,ceil(strlen($value64)/4))).
            $sep.
            substr(str_shuffle($chave),0,rand(1,strlen($chave))).
            $sep.
            strrev(substr($value64,ceil(strlen($value64)/4)*0,ceil(strlen($value64)/4))).
            $sep.
            substr(str_shuffle($chave),0,rand(1,strlen($chave)));
    return $pass;
}

function decrypt($value, $sep="$")
{
    $parts  = explode($sep, $value);
    $base64 = strrev($parts[7]).strrev($parts[5]).strrev($parts[3]).strrev($parts[1]);
    $pass   = base64_decode(str_pad(strtr($base64, '-_', '+/'),strlen($base64) % 4, '=', STR_PAD_RIGHT));
    return $pass;
}

function hexToRgb($hex, $alpha = false) {
    $hex      = str_replace('#', '', $hex);
    $length   = strlen($hex);
    $rgb['r'] = hexdec($length == 6 ? substr($hex, 0, 2) : ($length == 3 ? str_repeat(substr($hex, 0, 1), 2) : 0));
    $rgb['g'] = hexdec($length == 6 ? substr($hex, 2, 2) : ($length == 3 ? str_repeat(substr($hex, 1, 1), 2) : 0));
    $rgb['b'] = hexdec($length == 6 ? substr($hex, 4, 2) : ($length == 3 ? str_repeat(substr($hex, 2, 1), 2) : 0));
    if ($alpha) {
        $rgb['a'] = $alpha;
    }
    return $rgb;
 }

 function ThumbVimeo($video, $size = "large") {
    $info = file_get_contents("https://vimeo.com/api/v2/video/" . $video . ".json");
    $infos = json_decode($info);
    $key = "thumbnail_" . $size;
    return $infos[0]->$key;
}

function DisplayDouble($value, $decimals = 1) {

    $values = explode(".", $value);
    if ($value[1] == 0) {
        return $values[0];
    } else {
        return round($value, $decimals);
    }
}

function getOS() {

    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    $os_platform    =   "Unknown OS Platform";

    $os_array       =   array(
                            '/windows nt 10/i'     =>  'Windows 10',
                            '/windows nt 6.3/i'     =>  'Windows 8.1',
                            '/windows nt 6.2/i'     =>  'Windows 8',
                            '/windows nt 6.1/i'     =>  'Windows 7',
                            '/windows nt 6.0/i'     =>  'Windows Vista',
                            '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
                            '/windows nt 5.1/i'     =>  'Windows XP',
                            '/windows xp/i'         =>  'Windows XP',
                            '/windows nt 5.0/i'     =>  'Windows 2000',
                            '/windows me/i'         =>  'Windows ME',
                            '/win98/i'              =>  'Windows 98',
                            '/win95/i'              =>  'Windows 95',
                            '/win16/i'              =>  'Windows 3.11',
                            '/macintosh|mac os x/i' =>  'Mac OS X',
                            '/mac_powerpc/i'        =>  'Mac OS 9',
                            '/linux/i'              =>  'Linux',
                            '/ubuntu/i'             =>  'Ubuntu',
                            '/iphone/i'             =>  'iPhone',
                            '/ipod/i'               =>  'iPod',
                            '/ipad/i'               =>  'iPad',
                            '/android/i'            =>  'Android',
                            '/blackberry/i'         =>  'BlackBerry',
                            '/webos/i'              =>  'Mobile'
                        );

    foreach ($os_array as $regex => $value) {

        if (preg_match($regex, $user_agent)) {
            $os_platform    =   $value;
        }

    }

    return $os_platform;

}

function getBrowser() {

    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    $browser =   "Unknown Browser";

    $browser_array  =   array(
                            '/msie/i'       =>  'Internet Explorer',
                            '/firefox/i'    =>  'Firefox',
                            '/safari/i'     =>  'Safari',
                            '/chrome/i'     =>  'Chrome',
                            '/edge/i'       =>  'Edge',
                            '/opera/i'      =>  'Opera',
                            '/netscape/i'   =>  'Netscape',
                            '/maxthon/i'    =>  'Maxthon',
                            '/konqueror/i'  =>  'Konqueror',
                            '/mobile/i'     =>  'Handheld Browser'
                        );

    foreach ($browser_array as $regex => $value) {

        if (preg_match($regex, $user_agent)) {
            $browser    =   $value;
        }

    }

    return $browser;

}

function months($month = null, $lenght = null, $language="br") {

    $months_br = [
        "1"=>"Janeiro",
        "2"=>"Fevereiro",
        "3"=>"Março",
        "4"=>"Abril",
        "5"=>"Maio",
        "6"=>"Junho",
        "7"=>"Julho",
        "8"=>"Agosto",
        "9"=>"Setembro",
        "10"=>"Outubro",
        "11"=>"Novembro",
        "12"=>"Dezembro"
    ];

    $months_en = [
        "1"=>"January",
        "2"=>"February",
        "3"=>"March",
        "4"=>"April",
        "5"=>"May",
        "6"=>"June",
        "7"=>"July",
        "8"=>"August",
        "9"=>"September",
        "10"=>"October",
        "11"=>"November",
        "12"=>"December"
    ];

    $array = $language=="br" ? $months_br : $months_en;

    if ($month) {

        return $lenght ? substr($array[$month], 0, $lenght) : $array[$month];

    } else {

        if ($lenght) {
            foreach ($array as $m => $v) {
                $array[$m] = substr($v, 0, $lenght);
            }
        }

        return $array;
    }
}

function daysOfWeek($year, $weeknr, $start = 0, $end = 6) {

    $date = new DateTime();
    $date->setISODate($year, $weeknr, $start);
    $date_start = $date->format('Y-m-d');
    $date->setISODate($year, $weeknr, $end);
    $date_end = $date->format('Y-m-d');

    return [$date_start, $date_end];

}

function columnsExcel($n) {

    if ($n > 702) {
        throw new Exception("Excel:: Only 702 columns are allowed");
    }

    $alphabet = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z"];

    $total = count($alphabet);

    if ($n <= $total) {

        for ($i = 0; $i < $n; $i++) {

            $columns[] = $alphabet[$i];
        }

    } else {

        $loop = floor($n / $total);

        $over = $n % $total;

        for ($i = 0; $i < $loop; $i++) {

            for ($j = 0; $j < $total; $j++) {

                $col  = $i > 0 ? $alphabet[$i-1] : "";
                $col .= $alphabet[$j];

                $columns[] = $col;
            }
        }

        if ($over>0) {

            for ($s=0; $s<$over; $s++) {

                $col_over  = $alphabet[$loop-1];
                $col_over .= $alphabet[$s];

                $columns[] = $col_over;
            }
        }
    }

    return $columns;

}


function rounded($value, $length, $sep = ".") {

    $values = explode(".", $value);

    $integer = $values[0];
    $decimals = $values[1];

    if ($decimals == 0) {

        $response = $integer;

    } else {

        if (strlen($decimals) < $length) {

            $decimals = str_pad($decimals, $length, "0", STR_PAD_RIGHT);

        } else {

            $decimals = substr($decimals, 0, $length);

        }

        $response = $integer . $sep . $decimals;
    }

    return $response;

}

// SE TODOS OS VALORES PASSADOS ESTÁ NO ARRAY
function in_array_all(array $needles, array $haystack)
{
    return empty(array_diff($needles, $haystack));
}

// SE ALGUM DOS VALORES PASSADOS ESTÁ NO ARRAY
function in_array_any(array $needles, array $haystack)
{
    return !empty(array_intersect($needles, $haystack));
}

function convertBytes($size, $convert = "MB", $legend = false, $decimals = 2){
    $units = array(1=>"KB", 2=>"MB", 3=>"GB", 4=>"TB", 5=>"PB", 6=>"EB", 7=>"ZB", 8=>"YB");
    $key = (is_string($convert)) ? array_search(strtoupper($convert), $units) : $convert;
    return round($size / pow(1024, $key), $decimals) . ($legend ? " " . $units[$key] : null);
}

function formatarTextoWhatsapp($texto) {

    // Substituir * por negrito
    $texto = preg_replace('/\*(.*?)\*/', '<strong>$1</strong>', $texto);

    // Substituir _ por itálico
    $texto = preg_replace('/\_(.*?)\_/', '<em>$1</em>', $texto);

    // Substituir ~ por tachado
    $texto = preg_replace('/\~(.*?)\~/', '<del>$1</del>', $texto);

    // Substituir - por sublinhado
    // $texto = preg_replace('/\-(.*?)\-/', '<u>$1</u>', $texto);

    return $texto;
}

function numberToWords($number) {
    // Array de palavras para os números até 19
    $words = array(
        0 => 'zero',
        1 => 'one',
        2 => 'two',
        3 => 'three',
        4 => 'four',
        5 => 'five',
        6 => 'six',
        7 => 'seven',
        8 => 'eight',
        9 => 'nine',
        10 => 'ten',
        11 => 'eleven',
        12 => 'twelve',
        13 => 'thirteen',
        14 => 'fourteen',
        15 => 'fifteen',
        16 => 'sixteen',
        17 => 'seventeen',
        18 => 'eighteen',
        19 => 'nineteen'
    );

    // Array de palavras para as dezenas
    $tens = array(
        20 => 'twenty',
        30 => 'thirty',
        40 => 'forty',
        50 => 'fifty',
        60 => 'sixty',
        70 => 'seventy',
        80 => 'eighty',
        90 => 'ninety'
    );

    if ($number < 20) {
        return $words[$number];
    }

    if ($number < 100) {
        $ten = (int)($number / 10) * 10;
        $one = $number % 10;
        return $tens[$ten] . (($one > 0) ? ' ' . $words[$one] : '');
    }

    if ($number < 1000) {
        $hundred = (int)($number / 100);
        $remainder = $number % 100;
        return $words[$hundred] . ' hundred' . (($remainder > 0) ? ' and ' . numberToWords($remainder) : '');
    }

    if ($number < 1000000) {
        $thousand = (int)($number / 1000);
        $remainder = $number % 1000;
        return numberToWords($thousand) . ' thousand' . (($remainder > 0) ? ' ' . numberToWords($remainder) : '');
    }

    if ($number < 1000000000) {
        $million = (int)($number / 1000000);
        $remainder = $number % 1000000;
        return numberToWords($million) . ' million' . (($remainder > 0) ? ' ' . numberToWords($remainder) : '');
    }

    if ($number < 1000000000000) {
        $billion = (int)($number / 1000000000);
        $remainder = $number % 1000000000;
        return numberToWords($billion) . ' billion' . (($remainder > 0) ? ' ' . numberToWords($remainder) : '');
    }

    if ($number < 1000000000000000) {
        $trillion = (int)($number / 1000000000000);
        $remainder = $number % 1000000000000;
        return numberToWords($trillion) . ' trillion' . (($remainder > 0) ? ' ' . numberToWords($remainder) : '');
    }

    // Se o número for maior que um trilhão, retornar uma mensagem de erro
    return 'number too large';
}

function getClassByPercentage($percentage, $array) {

    // Inicializa a classe padrão
    $class = "bg-default";

    // Itera sobre o array de porcentagens
    foreach ($array as $threshold => $classValue) {
        // Se a porcentagem atual for maior ou igual ao limiar, atualiza a classe
        if ($percentage >= $threshold) {
            $class = $classValue;
        } else {
            // Como o array está ordenado, podemos parar a iteração assim que a condição não for atendida
            break;
        }
    }

    return $class;
}

function convertLinksAndEmailsToLowerCase($text) {

    // Convertendo links para lowercase
    $text = preg_replace_callback(
        '/(?:https?|ftp):\/\/\S+/i',
        function($matches) {
            return strtolower($matches[0]);
        },
        $text
    );

    // Convertendo e-mails para lowercase
    $text = preg_replace_callback(
        '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i',
        function($matches) {
            return strtolower($matches[0]);
        },
        $text
    );

    return $text;
}

function transformLinksToHTML($text, $target_blank = false) {
    // Identificar links no texto e transformá-los em elementos HTML <a>
    $text = preg_replace_callback(
        '/(?:https?|ftp):\/\/\S+/i',
        function($matches) use ($target_blank) {
            $url = strtolower($matches[0]);
            return "<a href='$url' target='" . ($target_blank ? '_blank' : '_self') . "'>$matches[0]</a>";
        },
        $text
    );

    return $text;
}

function custom_implode($array, $separator = ",", $last_separator = "e") {
    $count = count($array);
    if ($count === 0) {
        return '';
    } elseif ($count === 1) {
        return $array[0];
    } elseif ($count === 2) {
        return implode(' ' . $last_separator . ' ', $array);
    } else {
        $last = array_pop($array);
        return implode($separator . ' ', $array) . ' ' . $last_separator . ' ' . $last;
    }
}

function comoChegarGoogleMaps($destination, $origin = false)
{

    $url = "https://www.google.com/maps/dir/";

    $url .= '?api=1&destination=' . urlencode($destination);

    if ($origin) {
        $url .= '&origin=' . urlencode($origin);
    }

    return $url;

}

function substituirLinks($texto) {
    // Substitui [whatsapp]...[/whatsapp] por um link de WhatsApp
    $texto = preg_replace_callback('/\[whatsapp\]([^\]]*?)\[\/whatsapp\]/', function($match) {
        $numero = preg_replace('/[^\d]/', '', $match[1]);
        return '<a href="https://wa.me/' . $numero . '">' . $match[1] . '</a>';
    }, $texto);

    // Substitui [tel]...[/tel] por um link de telefone
    $texto = preg_replace_callback('/\[tel\]([^\]]*?)\[\/tel\]/', function($match) {
        $numero = preg_replace('/[^\d]/', '', $match[1]);
        return '<a href="tel:' . $numero . '">' . $match[1] . '</a>';
    }, $texto);

    // Substitui [email]...[/email] por um link de email
    $texto = preg_replace_callback('/\[email\]([^\]]*?)\[\/email\]/', function($match) {
        return '<a href="mailto:' . $match[1] . '">' . $match[1] . '</a>';
    }, $texto);

    return $texto;
}

function limitHtmlText($html, $limit, $suffix = '...')
{
    // Cria um novo DOMDocument e carrega o HTML
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // Cria um DOMXPath para consultar os nós de texto
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//text()');

    $text = '';
    $length = 0;
    $limitReached = false;

    // Coleta o texto até o limite
    foreach ($nodes as $node) {
        $nodeValue = $node->nodeValue;
        $nodeLength = mb_strlen($nodeValue, 'UTF-8');

        if ($length + $nodeLength > $limit) {
            $remainingLength = $limit - $length;
            $node->nodeValue = mb_substr($nodeValue, 0, $remainingLength, 'UTF-8') . $suffix;
            $limitReached = true;
            break;
        }

        $length += $nodeLength;
    }

    // Remove os nós de texto restantes, se o limite foi atingido
    if ($limitReached) {
        while ($node = $node->nextSibling) {
            $node->parentNode->removeChild($node);
        }
    }

    // Retorna o HTML truncado
    return $dom->saveHTML($dom->documentElement);
}


function renderNode($node, &$text)
{
    $html = '';

    if ($node->nodeType == XML_ELEMENT_NODE) {
        $html .= '<' . $node->nodeName;

        foreach ($node->attributes as $attr) {
            $html .= ' ' . $attr->nodeName . '="' . htmlspecialchars($attr->nodeValue) . '"';
        }

        $html .= '>';

        foreach ($node->childNodes as $child) {
            if ($text === '') break;
            $html .= renderNode($child, $text);
        }

        if ($node->childNodes->length > 0) {
            $html .= '</' . $node->nodeName . '>';
        }
    } elseif ($node->nodeType == XML_TEXT_NODE) {
        $nodeValueLength = mb_strlen($node->nodeValue, 'UTF-8');
        $html .= mb_substr($text, 0, $nodeValueLength, 'UTF-8');
        $text = mb_substr($text, $nodeValueLength, null, 'UTF-8');
    }

    return $html;
}

function adicionarProtocolo($url) {
    // Verifica se a URL já começa com um protocolo (http:// ou https://)
    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        // Adiciona 'http://' no início da URL se não houver protocolo
        $url = "http://" . $url;
    }
    return $url;
}

function formataHora($hora) {

    // Criar um objeto DateTime a partir da string de hora
    $dateTime = new DateTime($hora);

    // Obter a hora e os minutos
    $hora = $dateTime->format('H');  // Formato 24 horas
    $minutos = $dateTime->format('i');  // Minutos

    // Verificar se os minutos são "00"
    if ($minutos == '00') {
        // Se minutos for "00", exibir apenas a hora seguida de "H"
        return $hora . 'H';
    } else {
        // Caso contrário, exibir a hora e os minutos juntos (sem dois pontos)
        return $hora . 'H' . $minutos;
    }
}

function replaceText($text, $vars, $br = false) {

    // Verifica se $vars é um array e $text é uma string
    if (!is_array($vars) || !is_string($text)) {
        return $text; // Retorna o texto original se os tipos não forem válidos
    }

    $keys = array_keys($vars);
    $vals = array_values($vars);

    $text = str_replace($keys, $vals, $text);

    return $br ? nl2br($text) : $text;
}

function highlightText($text, $term, $before = 3, $after = 3)
{
    // Quebra o texto em palavras
    $words = explode(' ', $text);

    // Cria um array para armazenar o resultado final do trecho
    $highlighted = [];
    $term = strtolower($term);  // Faz a busca case-insensitive

    // Percorre as palavras do texto
    for ($i = 0; $i < count($words); $i++) {
        $word = strtolower($words[$i]);

        // Verifica se a palavra contém o termo de busca
        if (strpos($word, $term) !== false) {
            // Adiciona palavras antes do termo
            $start = max(0, $i - $before);
            // Adiciona palavras depois do termo
            $end = min(count($words), $i + $after + 1);

            // Coleta o trecho de palavras para exibir
            $context = array_slice($words, $start, $end - $start);

            // Substitui o termo em negrito
            foreach ($context as &$contextWord) {
                if (stripos($contextWord, $term) !== false) {
                    $contextWord = "<strong>" . $contextWord . "</strong>";
                }
            }

            // Junta as palavras no trecho e retorna
            $highlighted[] = implode(' ', $context);
        }
    }

    // Se nenhum termo for encontrado, retorna o texto original
    if (empty($highlighted)) {
        return $text;
    }

    // Retorna os trechos encontrados com destaque
    return implode('...', $highlighted);
}

function printaList($haystack, $n = 3, $sufix = " e mais __x__") {
    $count = count($haystack);
    $print = array_slice($haystack, 0, $n);
    $rest = max(0, $count - $n);  // Garante que $rest não seja negativo

    // Se o sufixo contém "x", substituímos por $rest; caso contrário, o usamos como está
    if ($rest > 0) {
        $sufix = strpos($sufix, "__x__") !== false ? str_replace("__x__", $rest, $sufix) : $sufix;
    } else {
        $sufix = "";  // Remove o sufixo se não houver "mais" itens
    }

    return implode(", ", $print) . $sufix;
}

function getCardBrand($bin) {
    if (preg_match('/^4/', $bin)) {
        return 'Visa';
    } elseif (preg_match('/^5[1-5]/', $bin)) {
        return 'Mastercard';
    } elseif (preg_match('/^3[47]/', $bin)) {
        return 'American Express';
    } elseif (preg_match('/^6(011|5|4[4-9])/', $bin)) {
        return 'Discover';
    } elseif (preg_match('/^35(2[89]|[3-8])/', $bin)) {
        return 'JCB';
    } elseif (preg_match('/^50(67|9)|^65/', $bin)) {
        return 'Elo';
    }
    return 'Unknown';
}

function formatarValorEfi($valor, $cifra = false) {
    return ($cifra ? $cifra . " " : "") . number_format($valor / 100, 2, ',', '.');
}

// function formatDimension($valor, $decimals = 4) {
//     // Converte o valor para número de ponto flutuante
//     $valor = floatval($valor);

//     // Verifica se o valor tem decimais não inteiramente zeros
//     $hasDecimals = fmod($valor, 1) != 0;

//     if ($hasDecimals) {
//         // Determina o número de casas decimais do valor original
//         $numDecimals = strlen(substr(strrchr($valor, '.'), 1));

//         // Calcula o número de casas decimais a mostrar, respeitando o mínimo e o máximo (até 4)
//         $decimalsToShow = min(max($decimals, $numDecimals), 4);

//         // Formata o número com o número calculado de casas decimais
//         return number_format($valor, $decimalsToShow, ',', '');
//     } else {
//         // Caso não haja decimais, retorna apenas o valor inteiro
//         return number_format($valor, 0, ',', '');
//     }
// }

function formatDimension($valor, $decimals = 4) {
    // Converte o valor para número de ponto flutuante
    $valor = floatval($valor);

    // Se o valor for inteiro, retorna sem casas decimais
    if (fmod($valor, 1) == 0) {
        return number_format($valor, 0, ',', '');
    }

    // Garante que o número seja formatado com até 4 casas decimais, removendo zeros desnecessários
    return rtrim(rtrim(number_format($valor, $decimals, ',', ''), '0'), ',');
}

function detectarEConverterData($data) {
    // Se for um número, assume que é um serial do Excel
    if (is_numeric($data)) {
        $dataConvertida = date('Y-m-d', strtotime("1899-12-30 +$data days"));
        return $dataConvertida;
    }

    // Remover espaços extras
    $data = trim($data);

    // Detectar formato BR (dd/mm/yyyy)
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data)) {
        $dateTime = DateTime::createFromFormat('d/m/Y', $data);
        return $dateTime ? $dateTime->format('Y-m-d') : null;
    }

    // Detectar formato US (mm/dd/yyyy)
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data)) {
        $dateTime = DateTime::createFromFormat('m/d/Y', $data);
        return $dateTime ? $dateTime->format('Y-m-d') : null;
    }

    // Detectar formato ISO (yyyy-mm-dd)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        $dateTime = DateTime::createFromFormat('Y-m-d', $data);
        return $dateTime ? $dateTime->format('Y-m-d') : null;
    }

    return null; // Retorna null se não conseguir converter
}

function unit2Float($valor) {
    // Remover espaços extras
    $valor = !empty($valor) ? trim($valor) : "";

    // Remover qualquer texto (Kg, un, Lt, etc.)
    $valor = preg_replace('/[^0-9.,]/', '', $valor);

    // Se houver mais de uma vírgula ou ponto, garantir que apenas o último separa decimais
    if (substr_count($valor, ',') > 1) {
        $valor = str_replace(',', '', $valor);
    }

    // Substituir a última vírgula por um ponto para conversão correta
    $valor = str_replace(',', '.', $valor);

    return floatval($valor);
}

function normalizarTexto($texto) {
    return preg_replace('/[\pZ\pC]+/u', ' ', trim($texto)); // Remove espaços invisíveis e caracteres de controle
}

function excelDateToMesAno($excelDate) {
    if (!is_numeric($excelDate)) return false;

    $unixTimestamp = ($excelDate - 25569) * 86400;
    $date = gmdate("Y-m-d", $unixTimestamp);

    $meses = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
    $mesIndex = (int) date('m', strtotime($date)) - 1;
    $mes = $meses[$mesIndex];
    $ano = date('y', strtotime($date));

    return "$mes/$ano";
}

// function py($scriptFile, $args = [], $error = true) {

//     $python = PYTHON_COMMAND;

//     // Resolve caminho absoluto para o script Python
//     // $script = realpath(__DIR__ . '/../python/' . $scriptFile);
//     // Constrói caminho absoluto corretamente no Windows
//     $script = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'python' . DIRECTORY_SEPARATOR . $scriptFile);

//     if (!$script || !file_exists($script)) {
//         return "Script não encontrado: $scriptFile";
//     }

//     // Escapa cada argumento de forma segura
//     $escapedArgs = array_map('escapeshellarg', $args);
//     $argsString = implode(' ', $escapedArgs);

//     // Monta o comando
//     $cmd = escapeshellcmd("$python $script") . " $argsString";

//     if ($error) {
//         $cmd .= ' 2>&1';
//     }

//     return shell_exec($cmd);
// }

/**
 * Executa um script Python com segurança e retorna stdout/stderr/código/JSON.
 *
 * @param string      $python   Caminho do Python (ex: C:\Python311\python.exe)
 * @param string|null $script   Caminho do .py (ou null se for usar -c)
 * @param array       $args     Args posicionais ou associativos:
 *                              - Associativo: ['--file'=>$file, '--id-upload'=>123, '--flag'=>true]
 *                              - Posicional:  ['--flag', 'valor', 'outro']
 * @param string|null $cwd      Working dir (opcional)
 * @param int         $timeout  Timeout em segundos (0 = sem limite)
 * @param bool        $lastJson Se true, tenta parsear a ÚLTIMA linha como JSON
 *
 * @return array {
 *   ok: bool,            // true se code==0 e json.error==false
 *   code: int,           // código de saída do processo
 *   json: ?array,        // JSON decodificado (se houver)
 *   stdout: string,
 *   stderr: string,
 *   cmd: string          // comando montado (debug)
 * }
 */
function pyExec(
    string $python,
    ?string $script,
    array $args = [],
    ?string $cwd = null,
    int $timeout = 0,
    bool $lastJson = true
): array {
    // Monta comando base: força UTF-8 e ignora warnings (Windows-friendly)
    $cmd = escapeshellarg($python) . ' -X utf8 -W ignore ';

    if ($script !== null) {
        $cmd .= escapeshellarg($script) . ' ';
    }

    // Normaliza args: aceita assoc e posicional
    foreach ($args as $k => $v) {
        if (is_int($k)) {
            // posicional (já vem com --flag ou valor)
            $cmd .= escapeshellarg($v) . ' ';
        } else {
            // associativo
            if ($v === true) {
                $cmd .= $k . ' ';
            } elseif ($v === false || $v === null) {
                // ignora flags false/null
            } else {
                $cmd .= $k . ' ' . escapeshellarg((string)$v) . ' ';
            }
        }
    }

    // Descritores para stdout/stderr
    $desc = [
        1 => ['pipe', 'w'], // stdout
        2 => ['pipe', 'w'], // stderr
    ];

    $proc = proc_open($cmd, $desc, $pipes, $cwd ?: null, null);
    if (!is_resource($proc)) {
        return ['ok' => false, 'code' => -1, 'json' => null, 'stdout' => '', 'stderr' => 'proc_open failed', 'cmd' => $cmd];
    }

    // Timeout simples (opcional)
    if ($timeout > 0) {
        stream_set_blocking($pipes[1], true);
        stream_set_blocking($pipes[2], true);
        stream_set_timeout($pipes[1], $timeout);
        stream_set_timeout($pipes[2], $timeout);
    }

    $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $code   = proc_close($proc);

    // Tenta JSON
    $json = null;
    if ($lastJson) {
        $lines = preg_split("/\r\n|\n|\r/", trim($stdout));
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $try = json_decode($lines[$i] ?? '', true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($try)) {
                $json = $try;
                break;
            }
        }
    } else {
        $json = json_decode($stdout, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $json = null;
        }
    }

    $ok = ($code === 0) && is_array($json) && array_key_exists('error', $json) && !$json['error'];

    return compact('ok', 'code', 'json', 'stdout', 'stderr') + ['cmd' => $cmd];
}


function pip($extName, $pyCommand = null) {

    $pyCommand = $pyCommand ?? (PYTHON_COMMAND ?? 'python');

    // Comando para instalar o módulo via pip
    $cmd = escapeshellcmd("$pyCommand -m pip install $extName --user");

    exec($cmd, $output, $retCode);

    if ($retCode !== 0) {
        echo "Erro ao instalar $extName:\n" . implode("\n", $output);
    } else {
        echo "$extName instalado com sucesso!\n";
    }
}


function parseData($valor) {
    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }

    // Formato ISO 2025-05-12T00:00:00
    if (strpos($valor, 'T') !== false && preg_match('/^\d{4}-\d{2}-\d{2}T/', $valor)) {
        return substr($valor, 0, 10); // já no formato Y-m-d
    }

    // Formato 2025-05-12 (sem T)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
        return $valor; // já correto
    }

    // Formato brasileiro 19/05/2025
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $valor)) {
        // Evita DateTime: converte manualmente
        [$dia, $mes, $ano] = explode('/', $valor);
        return "$ano-$mes-$dia";
    }

    // Se for algo inesperado, retorna null (ou valor original, se preferir)
    return null;
}

function roundNumber($numero, $casas) {
    // Arredonda o número para a quantidade de casas desejadas
    $arredondado = round($numero, $casas);

    // Remove zeros desnecessários após a vírgula
    $resultado = rtrim(rtrim(number_format($arredondado, $casas, '.', ''), '0'), '.');

    return $resultado;
}

function corTextoContraste($corFundo) {
    // Remove o # se existir
    $corFundo = ltrim($corFundo, '#');

    // Converte para RGB
    if (strlen($corFundo) == 3) {
        // Exemplo: #abc → aabbcc
        $r = hexdec(str_repeat(substr($corFundo, 0, 1), 2));
        $g = hexdec(str_repeat(substr($corFundo, 1, 1), 2));
        $b = hexdec(str_repeat(substr($corFundo, 2, 1), 2));
    } elseif (strlen($corFundo) == 6) {
        $r = hexdec(substr($corFundo, 0, 2));
        $g = hexdec(substr($corFundo, 2, 2));
        $b = hexdec(substr($corFundo, 4, 2));
    } else {
        return 'black'; // valor padrão
    }

    // Calcula o brilho percebido (perceptual brightness)
    $brilho = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

    // Se for claro, texto preto; se for escuro, texto branco
    return ($brilho > 128) ? 'black' : 'white';
}

function slugify($texto) {
    // Mapeia manualmente os acentos
    $acentos = array(
        'á','à','â','ã','ä','Á','À','Â','Ã','Ä',
        'é','è','ê','ë','É','È','Ê','Ë',
        'í','ì','î','ï','Í','Ì','Î','Ï',
        'ó','ò','ô','õ','ö','Ó','Ò','Ô','Õ','Ö',
        'ú','ù','û','ü','Ú','Ù','Û','Ü',
        'ç','Ç','ñ','Ñ'
    );

    $sem_acentos = array(
        'a','a','a','a','a','a','a','a','a','a',
        'e','e','e','e','e','e','e','e',
        'i','i','i','i','i','i','i','i',
        'o','o','o','o','o','o','o','o','o','o',
        'u','u','u','u','u','u','u','u',
        'c','c','n','n'
    );

    // Substitui acentos
    $texto = str_replace($acentos, $sem_acentos, $texto);

    // Converte para minúsculas
    $texto = mb_strtolower($texto, 'UTF-8');

    // Troca tudo que não for letra ou número por hífen
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);

    // Remove hífens duplicados e no início/fim
    $texto = trim($texto, '-');

    return $texto;
}

if (!function_exists('flash')) {
    function flash()
    {
        static $message;

        if (!$message) {
            $message = new \App\Core\Message();
        }

        $html = $message->flash();

        if (!empty($html)) {
            echo $html;
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return \App\Core\Env::get($key, $default);
    }
}

if (!function_exists('lists')) {
    function lists(string $name): \App\Core\Lists
    {
        return \App\Core\Lists::get($name);
    }
}

function cache(): \App\Core\Cache {
    return \App\Core\Container::get(\App\Core\Cache::class);
}

function clearCache(string $id): void {
    cache()->clear($id);
}

function clearCacheGroup(string $prefix): void {
    cache()->clearGroup($prefix); // precisa criar na classe
}

function clearCacheAll(): void {
    cache()->clear(); // sem id = limpa tudo
}

function utf8_fix($text) {
    // Primeiro, força interpretar o texto como ISO-8859-1
    // depois converte para UTF-8 sem perder letras
    $fixed = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1, UTF-8');

    // Remove caracteres que ainda forem inválidos
    $fixed = preg_replace('/[^\PC\s]/u', '', $fixed);

    return $fixed;
}

// helper: limpa mensagem para gravar no DB (mantém acentos)
// function utf8_clean($s) {
//     $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
//     // remove caracteres de controle
//     $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s);
//     // remove �
//     $s = str_replace("�", "", $s);
//     return $s;
// }

function utf8_clean($s) {
    $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');

    // remove caracteres de controle, MAS preserva \n (0x0A), \r (0x0D) e \t (0x09)
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);

    $s = str_replace("�", "", $s);
    return $s;
}


function iniciaisResponsavel($nomeCompleto)
{
    $partes = explode(" ", trim($nomeCompleto));

    if (count($partes) == 1) {
        return strtoupper(substr($partes[0], 0, 1)); // Só primeira letra
    }

    $primeiro = strtoupper(substr($partes[0], 0, 1));
    $ultimo   = strtoupper(substr(end($partes), 0, 1));

    return $primeiro . $ultimo;
}

function avatar($id, $data, $index = 0)
{

    $data = (array)$data;

    if (!empty($data["foto"])) {
        $ctt = '<img src="' . storage() . '/media/datas/' . $data["foto"] . '" />';
    } else {
        $ctt = iniciaisResponsavel($data["nome"]);
    }

    return '<div title="' . $data["nome"] . '" class="user-avatar" style="--i:' . $index . ';background-color:' . ($data["cor"] ?? "#000") . '; color: ' . corTextoContraste($data["cor"] ?? "#fff") . '" data-id="' . $id . '">' . $ctt . '</div>';
}


function ddm(iterable $items, bool $die = true)
{
    $map = [];

    foreach ($items as $item) {
        $map[] = $item instanceof \App\Core\Model
            ? $item->toArray()
            : $item;
    }

    return $die ? dd($map) : dump($map);
}

function media_url(string $path = '', ?string $disk = null): string
{
    $config = \App\Core\Config::get('app.media', []);

    $base = rtrim((string)($config['base_url'] ?? ''), '/');

    if ($base === '') {
        return ltrim($path, '/');
    }

    if ($disk) {
        $diskPath = trim((string)($config['paths'][$disk] ?? ''), '/');
        if ($diskPath !== '') {
            $path = $diskPath . '/' . ltrim($path, '/');
        }
    }

    return $base . '/' . ltrim($path, '/');
}

function route_url(string $name, array $params = []): string
{
    return \App\Core\Container::get(\App\Core\Router::class)->route($name, $params);
}

function route(string $name, array $params = []): string
{
    return route_url($name, $params);
}

function age(string|DateTime $date): int
{
    if (!$date) {
        return 0;
    }

    $birth = $date instanceof DateTime ? $date : new DateTime($date);

    return $birth->diff(new DateTime('today'))->y;
}

function formatarCep(?string $cep): ?string
{
    $cep = preg_replace('/\D+/', '', (string)$cep);

    if (strlen($cep) !== 8) {
        return null;
    }

    return substr($cep, 0, 2) . '.' .
           substr($cep, 2, 3) . '-' .
           substr($cep, 5, 3);
}

if (!function_exists('app')) {
    function app(string $key, mixed $default = null): mixed
    {
        return \App\Core\App::get($key, $default);
    }
}


function html2WhatsappText(?string $html): string
{
    if ($html === null) return '';

    $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Quebras de linha por blocos
    $text = preg_replace('~</\s*(p|div|h[1-6]|li)\s*>~i', "\n", $text);
    $text = preg_replace('~<\s*br\s*/?\s*>~i', "\n", $text);

    // li vira bullet
    $text = preg_replace('~<\s*li\s*>~i', "• ", $text);

    // span com line-through vira ~texto~
    $text = preg_replace('~<\s*span[^>]*text-decoration\s*:\s*line-through[^>]*>~i', '~', $text);
    $text = preg_replace('~</\s*span\s*>~i', '~', $text);

    // Marcadores WhatsApp
    $text = preg_replace('~<\s*(strong|b)\s*>~i', '*', $text);
    $text = preg_replace('~</\s*(strong|b)\s*>~i', '*', $text);

    $text = preg_replace('~<\s*(em|i)\s*>~i', '_', $text);
    $text = preg_replace('~</\s*(em|i)\s*>~i', '_', $text);

    $text = preg_replace('~<\s*(s|del|strike)\s*>~i', '~', $text);
    $text = preg_replace('~</\s*(s|del|strike)\s*>~i', '~', $text);

    $text = preg_replace('~<\s*code\s*>~i', '`', $text);
    $text = preg_replace('~</\s*code\s*>~i', '`', $text);

    $text = preg_replace('~<\s*pre\s*>~i', "\n```", $text);
    $text = preg_replace('~</\s*pre\s*>~i', "```\n", $text);

    // remove tags restantes
    $text = strip_tags($text);

    // normaliza linhas
    $text = preg_replace("/[ \t]+\n/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    // 1) tira espaços DENTRO dos marcadores (* texto * -> *texto*)
    $text = normalizeWhatsappMarkers($text);

    // 2) evita grudar palavras com marcadores (Olá*...*já -> Olá *...* já)
    $text = fixWhatsappMarkerSpacing($text);

    return trim($text);
}

function normalizeWhatsappMarkers(string $text): string
{
    foreach (['*', '_', '~', '`'] as $m) {
        $qm = preg_quote($m, '/');

        for ($i = 0; $i < 3; $i++) {
            $text = preg_replace_callback(
                "/{$qm}([^{$qm}]*?){$qm}/u",
                function ($matches) use ($m) {
                    $inner = $matches[1];
                    $inner = preg_replace('/^\s+/u', '', $inner);
                    $inner = preg_replace('/\s+$/u', '', $inner);
                    return $inner === '' ? '' : ($m . $inner . $m);
                },
                $text
            );
        }
    }
    return $text;
}

function fixWhatsappMarkerSpacing(string $text): string
{
    // Considera letra/número/acento como "word"
    $word = '[0-9\p{L}\p{M}]';

    foreach (['*', '_', '~', '`'] as $m) {
        $qm = preg_quote($m, '/');

        // A) se estiver colado na esquerda (Olá*...), e houver outro marcador depois (abertura), põe espaço ANTES
        $text = preg_replace_callback(
            "/($word)($qm)(?=$word)/u",
            function ($matches) use ($m, $text) {
                // tenta detectar se é ABERTURA: existe outro marcador igual mais à frente
                // (heurística simples e funciona bem pra templates)
                $pos = strpos($text, $matches[0]);
                if ($pos === false) return $matches[0];

                $rest = substr($text, $pos + strlen($matches[0]));
                if (strpos($rest, $m) !== false) {
                    return $matches[1] . ' ' . $matches[2]; // "Olá *"
                }
                return $matches[0]; // não mexe (provavelmente fechamento)
            },
            $text
        );

        // B) se estiver colado na direita (...*já), e NÃO houver outro marcador depois (fechamento), põe espaço DEPOIS
        $text = preg_replace_callback(
            "/($word)($qm)(?=$word)/u",
            function ($matches) use ($m, $text) {
                $pos = strpos($text, $matches[0]);
                if ($pos === false) return $matches[0];

                $rest = substr($text, $pos + strlen($matches[0]));
                if (strpos($rest, $m) === false) {
                    return $matches[1] . $matches[2] . ' '; // "* já"
                }
                return $matches[0];
            },
            $text
        );
    }

    // limpa duplos espaços
    $text = preg_replace('/[ \t]{2,}/', ' ', $text);

    // ajusta espaços antes de pontuação
    $text = preg_replace('/\s+([,.;:!?])/u', '$1', $text);

    return $text;
}

function allow(string $permission): bool
{
    try {
        $auth = \App\Core\Container::get(\App\Core\Auth::class);
        return (bool) $auth->allow($permission);
    } catch (\Throwable $e) {
        return false;
    }
}
