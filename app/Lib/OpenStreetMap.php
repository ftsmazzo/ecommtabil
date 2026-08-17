<?php
namespace App\Lib;

class OpenStreetMap
{
    private $nominatimUrl = "https://nominatim.openstreetmap.org";
    private $overpassUrl = "https://overpass-api.de/api/interpreter";

    /**
     * Faz uma solicitação HTTP usando cURL.
     *
     * @param string $url
     * @return mixed
     */
    private function makeRequest($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERAGENT, 'PHP OpenStreetMapAPI Client');
        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }

    /**
     * Converte um endereço em coordenadas (geocodificação).
     *
     * @param string $address
     * @return mixed
     */
    public function geocode($address)
    {
        $url = $this->nominatimUrl . "/search?q=" . urlencode($address) . "&format=json&limit=1";
        return $this->makeRequest($url);
    }

    /**
     * Converte coordenadas em um endereço (geocodificação reversa).
     *
     * @param float $lat
     * @param float $lon
     * @return mixed
     */
    public function reverseGeocode($lat, $lon)
    {
        $url = $this->nominatimUrl . "/reverse?lat={$lat}&lon={$lon}&format=json";
        return $this->makeRequest($url);
    }

    /**
     * Busca por POIs (Pontos de Interesse) usando Overpass API.
     *
     * @param float $lat
     * @param float $lon
     * @param string $tag
     * @param int $radius
     * @return mixed
     */
    public function getPOIs($lat, $lon, $tag = "amenity", $radius = 500)
    {
        // Consulta Overpass para buscar POIs dentro de um raio de X metros.
        $query = "[out:json];node[\"$tag\"](around:$radius,$lat,$lon);out;";
        $url = $this->overpassUrl . "?data=" . urlencode($query);

        return $this->makeRequest($url);
    }

    /**
     * Calcula a rota entre dois pontos usando OSRM (Open Source Routing Machine).
     *
     * @param float $startLat
     * @param float $startLon
     * @param float $endLat
     * @param float $endLon
     * @return mixed
     */
    public function getRoute($startLat, $startLon, $endLat, $endLon)
    {
        $osrmUrl = "http://router.project-osrm.org/route/v1/driving/{$startLon},{$startLat};{$endLon},{$endLat}?overview=false&geometries=geojson";
        return $this->makeRequest($osrmUrl);
    }
}

?>
