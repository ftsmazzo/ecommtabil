<?php
namespace App\Lib;

use Exception;

class GeoIP {

    //the geoPlugin server
    var $host = "http://www.geoplugin.net";

    //the default base currency
    var $currency = 'USD';

    //the default language
    var $lang = 'en';

    //initiate the geoPlugin vars
    var $ip = null;

    var $city = null;
    var $region = null;
    var $regionCode = null;
    var $regionName = null;
    var $dmaCode = null;
    var $countryCode = null;
    var $countryName = null;
    var $inEU = null;
    var $euVATrate = false;
    var $continentCode = null;
    var $continentName = null;
    var $latitude = null;
    var $longitude = null;
    var $locationAccuracyRadius = null;
    var $timezone = null;
    var $currencyCode = null;
    var $currencySymbol = null;
    var $currencyConverter = null;

    public function __construct($ip=null)
    {

        global $_SERVER;

        if (is_null($ip)) {
            $this->ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $this->ip = $ip;
        }
    }

    public function setIp($ip)
    {
        $this->ip = $ip;
        return $this;
    }

    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    public function setLang($lang)
    {
        $supported = ["de", "en", "es", "fr", "ja", "pt-BR", "ru", "zh-CN"];

        if (!in_array($lang, $supported)) {
            throw new Exception("This lang is not supported!");
        }

        $this->lang = $lang;
        return $this;
    }

    public function locate($ip = null)
    {

        if ($ip) {
            $this->setIp($ip);
        }

        $endpoint  = $this->host."/php.gp";
        $endpoint .= "?ip=".$this->ip;
        $endpoint .= "&base_currency=".$this->currency;
        $endpoint .= "&lang=".$this->lang;

        $response = $this->fetch($endpoint);

        // Adicionando verificação para garantir que a resposta não é false (erro) antes de unserialize
        if ($response === false) {
            // Logar ou tratar o erro de forma mais robusta, se necessário
            error_log("GeoIP Error: Failed to fetch data from geoplugin.net for IP " . $this->ip);
            return false; // Retorna false para indicar falha na localização
        }

        // Adicionando verificação para garantir que a resposta não está vazia
        if (empty($response)) {
            error_log("GeoIP Error: Empty response from geoplugin.net for IP " . $this->ip);
            return false;
        }

        $data = unserialize($response);

        // Adicionando verificação para garantir que a desserialização foi bem-sucedida e $data é um array
        if ($data === false || !is_array($data)) {
            error_log("GeoIP Error: Failed to unserialize response for IP " . $this->ip . ". Response: " . $response);
            return false;
        }

        // Se houver um erro na resposta da API (ex: geoplugin_status), você pode adicionar uma verificação aqui
        if (isset($data['geoplugin_status']) && $data['geoplugin_status'] != 200) {
            error_log("GeoIP API Status Error for IP " . $this->ip . ": " . ($data['geoplugin_message'] ?? 'Unknown API Error'));
            return false;
        }

        //set the geoPlugin vars
        $this->ip = $this->ip; // Esta linha é redundante, mas mantida por consistência com o original
        // Usando o operador de coalescência nula (??) para evitar "Trying to access array offset on value of type bool"
        $this->city = $data['geoplugin_city'] ?? null;
        $this->region = $data['geoplugin_region'] ?? null;
        $this->regionCode = $data['geoplugin_regionCode'] ?? null;
        $this->regionName = $data['geoplugin_regionName'] ?? null;
        $this->dmaCode = $data['geoplugin_dmaCode'] ?? null;
        $this->countryCode = $data['geoplugin_countryCode'] ?? null;
        $this->countryName = $data['geoplugin_countryName'] ?? null;
        $this->inEU = $data['geoplugin_inEU'] ?? null;
        $this->euVATrate = $data['euVATrate'] ?? false;
        $this->continentCode = $data['geoplugin_continentCode'] ?? null;
        $this->continentName = $data['geoplugin_continentName'] ?? null;
        $this->latitude = $data['geoplugin_latitude'] ?? null;
        $this->longitude = $data['geoplugin_longitude'] ?? null;
        $this->locationAccuracyRadius = $data['geoplugin_locationAccuracyRadius'] ?? null;
        $this->timezone = $data['geoplugin_timezone'] ?? null;
        $this->currencyCode = $data['geoplugin_currencyCode'] ?? null;
        $this->currencySymbol = $data['geoplugin_currencySymbol'] ?? null;
        $this->currencyConverter = $data['geoplugin_currencyConverter'] ?? null;

        return true; // Retorna true para indicar sucesso
    }



    function convert($amount, $float=2, $symbol=true) {

        //easily convert amounts to geolocated currency.
        if (!is_numeric($this->currencyConverter) || $this->currencyConverter == 0) {
            trigger_error('geoPlugin class Notice: currencyConverter has no value.', E_USER_NOTICE);
            return $amount;
        }
        if (!is_numeric($amount) ) {
            trigger_error ('geoPlugin class Warning: The amount passed to geoPlugin::convert is not numeric.', E_USER_WARNING);
            return $amount;
        }
        if ($symbol === true ) {
            return $this->currencySymbol . round(($amount * $this->currencyConverter), $float);
        } else {
            return round(($amount * $this->currencyConverter), $float);
        }
    }

    function nearby($radius=10, $limit=null) {

        if (!is_numeric($this->latitude) || !is_numeric($this->longitude)) {

            trigger_error('geoPlugin class Warning: Incorrect latitude or longitude values.', E_USER_NOTICE);
            return array( array());
        }

        $host = "http://www.geoplugin.net/extras/nearby.gp?lat=" . $this->latitude . "&long=" . $this->longitude . "&radius={$radius}";

        if ( is_numeric($limit) )
            $host .= "&limit={$limit}";

        return unserialize( $this->fetch($host));

    }


    private function fetch($host)
    {

        if (ini_get('allow_url_fopen')) {

            // Usando stream_context_create para adicionar um User-Agent, o que pode resolver o 403
            $options = array(
                'http' => array(
                    'method' => "GET",
                    'header' => "User-Agent: geoPlugin PHP Class v1.1\r\n" // Adicionando User-Agent
                )
            );
            $context = stream_context_create($options);

            //fall back to fopen()
            // file_get_contents($host, 'r') foi substituído por file_get_contents($host, false, $context)
            // O 'r' no segundo argumento de file_get_contents não é para fopen, é para use_include_path.
            // O segundo argumento é use_include_path (bool), o terceiro é context.
            // O uso de 'r' como segundo argumento no código original estava incorreto, mas não causava o 403.
            $response = @file_get_contents($host, false, $context); // Usando @ para suprimir o Warning

            // Se file_get_contents falhar, ele retorna false.
            if ($response === false) {
                // Tenta usar cURL como fallback, mesmo que allow_url_fopen esteja ativado, se o erro for 403.
                // Isso garante que o User-Agent seja enviado.
                if (function_exists('curl_init')) {
                    goto use_curl;
                }
            }
            return $response;

        } else if (function_exists('curl_init')) {

            use_curl: // Label para o goto

            //use cURL to fetch data
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $host);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            // O User-Agent é crucial para evitar o 403 em alguns servidores.
            curl_setopt($ch, CURLOPT_USERAGENT, 'geoPlugin PHP Class v1.1');
            $response = curl_exec($ch);

            // Adicionando verificação de erro do cURL
            if (curl_errno($ch)) {
                error_log('GeoIP cURL Error: ' . curl_error($ch));
                $response = false;
            } else {
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($http_code >= 400) {
                    error_log("GeoIP cURL HTTP Error: " . $http_code . " for " . $host);
                    $response = false;
                }
            }

            curl_close($ch);
            return $response;

        } else {

            trigger_error('geoPlugin class Error: Cannot retrieve data. Either compile PHP with cURL support or enable allow_url_fopen in php.ini ', E_USER_ERROR);
            return false; // Retorna false em caso de erro fatal
        }
    }

}