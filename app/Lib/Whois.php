<?php
namespace App\Lib;

use Exception;

class Whois {

    private $domain;
    private $whois;


    public function __construct($domain = null)
    {
        $this->domain = $domain;
    }



    public function get($domain = null)
    {

        if ($domain) {
            $this->setDomain($domain);
        }

        if (!$this->domain) {
            throw new Exception("The doman is required");
        }

        // VERIFICAR SE O DOMÍNIO É BR
        $regex = '/\.br$/i';

        if (preg_match($regex, $this->domain)) {
            $url = "https://registro.br/tecnologia/ferramentas/whois?search=" . $this->domain;
        } else {
            $url = "https://who.is/whois/" . $this->domain;
        }

        // $html = file_get_contents($url);

        // // Verifica se o conteúdo foi obtido com sucesso
        // if ($html !== false) {
        //     // Criar um novo objeto DOMDocument
        //     $dom = new DOMDocument();
        //     // Carregar o HTML
        //     $dom->loadHTML($html);

        //     // Criar um novo objeto DOMXPath
        //     $xpath = new DOMXPath($dom);

        //     // Consulta XPath para encontrar todas as células com a classe específica
        //     $celulas = $xpath->query("//td[contains(@class, 'minha-classe')]");

        //     // Verificar se foram encontradas células com a classe específica
        //     if ($celulas->length > 0) {
        //         // Loop através das células encontradas
        //         foreach ($celulas as $celula) {
        //             // Obter o conteúdo da célula
        //             $conteudo = $celula->nodeValue;
        //             // Exibir o conteúdo da célula
        //             echo "Conteúdo da célula: $conteudo<br>";
        //         }
        //     } else {
        //         echo "Nenhuma célula com a classe específica foi encontrada.";
        //     }
        // } else {
        //     echo "Não foi possível obter o conteúdo HTML.";
        // }

        /*
        $table = explode('<table', $contents);
        $table_content = explode("</table>", $table[1]);

        if ($type=="table") {

            return $table_content[0];
        }

        $tbody = explode("<tbody>", $table_content[0])[1];

        $rows = explode("<tr>", $tbody);

        for ($i=1; $i<count($rows); $i++) {

            $cell = explode("<td>", $rows[$i]);

            $date = explode("<br>", $cell[0]);
            $msg  = explode("<br>", $cell[1]);

            $track[] = [
                "date"   => trim(strip_tags($date[0]." ".$date[1])),
                "city"   => trim(strip_tags($date[2])),
                "status" => trim(strip_tags($msg[0])),
                "obs"    => isset($msg[2]) ? trim(strip_tags($msg[1])) : null,
                "desc"   => trim(strip_tags($msg[2] ?? $msg[1]))
            ];

        }

        return $track;

        */

    }

    public function getUserRegistrobr($user)
    {



    }


    /**
     * Get the value of domain
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Set the value of domain
     *
     * @return  self
     */
    public function setDomain($domain)
    {

        // Remover o www do ínicio se presente
        $domain = preg_replace('/^www\./i', '', $domain);
        // Converter o domínio para minúsculas
        $domain = strtolower($domain);

        $this->domain = $domain;

        return $this;
    }



}