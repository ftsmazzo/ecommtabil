<?php
/**
 * Classe para manipulação da Api
 * Here Maps
 * https://developer.here.com/
 */
namespace App\Lib;

use App\Core\Config;

class Here {

    public $api_key;
    public $obj;

    public function __construct()
    {
        $config = Config::get("here");

        $this->api_key = $config["api_key"];
    }


    /**
     * Set the Api Key from Here Maps
     *
     * @param string $api_key
     * @return void
     */
    public function setApiKey($api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * Get the GeoCode from Address
     *
     * @param string $origin
     * @param string $destiny
     * @param string $attributes
     * @param string $output
     * @return void
     */
    public function calculateRoute($origin, $destiny, $attributes="wp,sm,sh,sc", $output="json")
    {

        $url  = "https://route.ls.hereapi.com/routing/7.2/calculateroute.".$output;
        $url .= "?apiKey=".$this->api_key;
        $url .= "&waypoint0=geo!".$origin;
        $url .= "&waypoint1=geo!".$destiny;
        $url .= "&routeattributes=".$attributes;
        $url .= "&mode=fastest;car";

        if ($output=="json") {

            return $this->getContents($url);
        }
        elseif ($output=="xml") {

            return $this->xml($url);
        }
    }

    /**
     * Get the GeoCode from Address
     *
     * @param string $address
     * @return object
     */
    public function geocode(string $address)
    {
        $url  = "https://geocoder.ls.hereapi.com/6.2/geocode.json";
        $url .= "?apiKey=".$this->api_key;
        $url .= "&searchtext=".urlencode($address);
        return $this->getContents($url);
    }

    /**
     * Get the Address from Coordinates
     *
     * @param string $coordinates
     * @return object
     */
    public function reverseGeocode(string $coordinates, $mode="retrieveAddresses", $maxresults=1, $gen=9)
    {

        $url  = "https://reverse.geocoder.ls.hereapi.com/6.2/reversegeocode.json";
        $url .= "?prox=".urlencode($coordinates);
        $url .= "&mode=".$mode;
        $url .= "&maxresults=".$maxresults;
        $url .= "&gen=".$gen;
        $url .= "&apiKey=".$this->api_key;
        return $this->getContents($url);
    }

    public function getAddress(string $coordinates, $mode="retrieveAddresses", $maxresults=1, $gen=9)
    {
        return $this->reverseGeocode($coordinates, $mode, $maxresults, $gen);
    }

    /**
     * Get Summary from Route
     *
     * @param string $origin
     * @param string $destiny
     * @return object
     */
    public function summaryRoute($origin, $destiny, $attributes="wp,sm,sh,sc", $output="json")
    {
        $response = $this->calculateRoute($origin, $destiny, $attributes, $output);
        return $response->response->route[0]->summary;
    }

    /**
     * Get the Location from geocode
     *
     * @param string $address
     * @return object
     */
    public function location(string $address)
    {
        $response = $this->geocode($address);
        return $response->Response->View[0]->Result[0]->Location;
    }

    /**
     * Get the Location from geocode
     *
     * @param string $address
     * @return object
     */
    public function coordinates(string $address)
    {
        $response = $this->location($address);
        return $response->NavigationPosition[0];
    }

    /**
     * Get the Address from reverseGeocode
     *
     * @param string $address
     * @return object
     */
    public function address(string $coordinates)
    {
        $response = $this->reverseGeocode($coordinates);
        return $response->Response->View[0]->Result[0]->Location->Address;
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

        $obj = json_decode($result);
        return $obj->response;
    }

    public function xml($url)
    {
        $obj = simplexml_load_file($url);
        return $obj;
    }



}