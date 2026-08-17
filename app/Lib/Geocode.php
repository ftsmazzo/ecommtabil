<?php
namespace App\Lib;

class Geocode
{

    public $api_key;
    // public $obj;
    public $base_url;

    public function __construct($api_key = null)
    {

        $this->api_key = $api_key;

        $this->base_url = "https://geocode.xyz";

    }

    /**
     * Set the Api Key
     *
     * @param string $api_key
     * @return void
     */
    public function setApiKey($api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * Pegar as coordenadas de um determinado endereço
     *
     * @param string $address
     * @return array
     */
    public function coordinates(string $address): array
    {

        $response = $this->fowardGeocoding($address);

        $data = json_decode($response);

        $coordinates = [
            "latitude" => $data->latt,
            "longitude" => $data->longt
        ];

        return $coordinates;
    }

    /**
     * Pegar o endereço à partir de uma coordenada
     *
     * @param string $latitude
     * @param string $longitude
     * @return array
     */
    public function address(string $latitude, string $longitude): array
    {

        $response = $this->reverseGeocoding($latitude . "," . $longitude);

        $data = json_decode($response);

        $address = [
            "logradouro" => $data->staddress,
            "numero" => $data->stnumber,
            // "bairro" => $data->
            "cidade" => $data->city,
            "estado" => $data->state,
            "cep" => $data->postal,
            "pais" => $data->country,
        ];

        return $address;
    }

    /**
     * Encontrar os dados de Geocoding através de uma coordenada
     *
     * @param string $coordinates
     * @return void
     */
    public function reverseGeocoding(string $coordinates)
    {

        $coordinates = str_replace(" ", "", $coordinates);

        $queryString = http_build_query(
            [
                "auth" => $this->api_key,
                "geoit" => "JSON"
            ]
        );

        $url = $this->base_url . "/" . $coordinates . "?" . $queryString;

        $response = $this->curl($url);

        return $response;

    }

    /**
     * Encontrar os dados de Geocoding através de um endereço
     *
     * @param string $coordinates
     * @return void
     */
    private function fowardGeocoding($address)
    {

        $address = urlencode($address);

        $queryString = http_build_query(
            [
                "auth" => $this->api_key,
                "json" => "1"
            ]
        );

        $url = $this->base_url . "/" . $address . "?" . $queryString;

        $response = $this->curl($url);

        return $response;
    }

    /**
     * Request from url_fopen
     *
     * @param string $url
     * @return void
     */
    public function getContents($url)
    {
        ini_set("allow_url_fopen", 1);

        $json = file_get_contents($url);
        $obj = json_decode($json);
        return $obj;
    }

    /**
     * Request cURL
     *
     * @param string $url
     * @return void
     */
    public function curl($url)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    public function xml($url)
    {
        $obj = simplexml_load_file($url);
        return $obj;
    }



}