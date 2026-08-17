<?php

namespace App\Core;

use App\Models\Configuracao;
use App\Models\Site;

class SEO
{

    private static $separador;

    public function __construct()
    {

    }

    public static function generate($params = [])
    {

        $seo = null;

        $separador = " | ";

        $config = Configuracao::list();

        $session = new Session();

        $lang = $session->has("lang") ? $session->lang : 1;

        $site = Site::find($lang);

        $print_title = $params["title"] ?? $site->title;

        if (isset($params["subtitle"])) {
            $print_title .= $separador . $params["subtitle"];
        }


        $seo .= '<title>' . $print_title . '</title>
    ';
        $seo .= '<meta name="Description" content="' . ($params["description"]??$site->description) . '" />
    ';

        if (isset($params["keywords"])) {
            $seo .= '<meta name="Keywords" content="' . $params["keywords"] . '" />
    ';
        } else if ($site->keywords) {
            $seo .= '<meta name="Keywords" content="' . $site->keywords . '" />
    ';
        }

        // Favicon
        if (isset($config->favicon)) {

            $seo .= '<link rel="icon" type="image/png" href="' . storage() . '/media/site/favicon.png" sizes="32x32">
    ';
            $seo .= '<link rel="shortcut icon" href="' . storage() . '/media/site/favicon.ico" type="image/x-icon" />
    ';
            $seo .= '<link rel="apple-touch-icon" sizes="180x180" href="' . storage() . '/media/site/apple-touch-icon.png">
    ';
            $seo .= '<link rel="apple-touch-icon-precomposed" href="' . storage() . '/media/site/apple-touch-icon.png">
    ';
            $seo .= '<meta name="msapplication-TileImage" content="' . storage() . '/media/site/favicon.png">
    ';
            $seo .= '<meta name="msapplication-TileColor" content="#fff">
    ';
        }

        // Facebook Metas
        $seo .= '<meta property="og:title" content="' . $print_title . '" />
    ';
        $seo .= '<meta property="og:type" content="' . ($params["type"]??"website") . '" />
    ';
        $seo .= '<meta property="og:description" content="' . ($params["description"]??$site->description) . '" />
    ';
        $seo .= '<meta property="og:image" content="' . ($params["image"]??asset("img/logo.png")) . '" />
    ';
        $seo .= '<meta property="og:url" content="' . ((isset($_SERVER['HTTPS']) ? "https" : "http") . '://' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] ) . '" />
    ';
        $seo .= '<meta property="og:site_name" content="' . TITLE_SITE . '" />
    ';

        // Twitter
        $seo .= '<meta name="twitter:card" value="summary">
    ';
        // $seo .= '<meta name="twitter:site" content="Conta do Twitter do site (incluindo arroba)">';
        // $seo .= '<meta name="twitter:creator" content="Conta do Twitter do autor do texto (incluindo arroba)“>';
        $seo .= '<meta name="twitter:title" content="' . $print_title . '">
    ';
        $seo .= '<meta name="twitter:description" content="' . ($params["description"] ?? $site->description) . '">
    ';
        $seo .= '<meta name="twitter:image" content="' . ($params["image"] ?? asset('img/logo.png')) . '">
    ';

        return $seo;

    }


}
