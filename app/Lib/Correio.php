<?php
namespace App\Lib;

use Exception;

class Correio {

    private $url;
    private $comprimento;
    private $altura;
    private $largura;
    private $weight;
    private $valor_declarado;
    private $contract;
    private $pass;
    private $origin;
    private $destiny;


    public function __construct()
    {

        $this->comprimento = "16";
        $this->altura = "4";
        $this->largura = "12";
        $this->valor_declarado = "25.00";

    }

    /**
     * Set the value of comprimento
     *
     * @return  self
     */
    public function setComprimento($comprimento)
    {
        $this->comprimento = $comprimento;

        return $this;
    }

    /**
     * Set the value of altura
     *
     * @return  self
     */
    public function setAltura($altura)
    {
        $this->altura = $altura;

        return $this;
    }

    /**
     * Set the value of largura
     *
     * @return  self
     */
    public function setLargura($largura)
    {
        $this->largura = $largura;

        return $this;
    }

    /**
     * Set the value of largura
     *
     * @return  self
     */
    public function setValorDeclarado($valor_declarado)
    {
        $this->valor_declarado = $valor_declarado;

        return $this;
    }

    /**
     * Set the value of origin
     *
     * @return  self
     */
    public function setOrigin($origin)
    {
        $this->origin = preg_replace("/[^0-9]/", "", $origin);

        return $this;
    }

    /**
     * Set the value of destiny
     *
     * @return  self
     */
    public function setDestiny($destiny)
    {
        $this->destiny = preg_replace("/[^0-9]/", "", $destiny);

        return $this;
    }

    /**
     * Set the value of contract
     *
     * @return  self
     */
    public function setContract($contract)
    {
        $this->contract = $contract;

        return $this;
    }

    /**
     * Set the value of password
     *
     * @return  self
     */
    public function setPass($pass)
    {
        $this->pass = $pass;

        return $this;
    }

    /**
     * Set the value of weight
     *
     * @return  self
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;

        return $this;
    }

    private function calculaFrete($origin=null, $destiny=null, $service=null) {


        if ($origin) {
            $this->origin = preg_replace("/[^0-9]/", "", $origin);
        }

        if ($destiny) {
            $this->destiny = preg_replace("/[^0-9]/", "", $destiny);
        }

        if (!$this->origin) {
            throw new Exception("O CEP de origem é obrigatório");
        }

        if (!$this->destiny) {
            throw new Exception("O CEP de destino é obrigatório");
        }

        if (!$this->weight) {
            throw new Exception("O Peso é obrigatório");
        }

        $url = "http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx";


        $params["nCdEmpresa"] = $this->contract ?? null;
        $params["sDsSenha"] = $this->pass ?? null;
        $params["sCepOrigem"] = $this->origin;
        $params["sCepDestino"] = $this->destiny;
        $params["nVlPeso"] = $this->weight;
        $params["nCdFormato"] = 1;
        $params["nVlComprimento"] = $this->comprimento;
        $params["nVlAltura"] = $this->altura;
        $params["nVlLargura"] = $this->largura;
        $params["sCdMaoPropria"] = "n";
        $params["nVlValorDeclarado"] = $this->valor_declarado;
        $params["sCdAvisoRecebimento"] = "n";
        $params["nCdServico"] = $service;
        $params["nVlDiametro"] = 0;
        $params["StrRetorno"] = "xml";

        $endpoint = $url . "?" . http_build_query($params);

        $xml = simplexml_load_file($endpoint);

        // $Erro = $xml->cServico->Erro;

        // if ($Erro==0 || $Erro=="011" || $Erro=="010") {

        //     if ($xml->cServico->Valor>0 && $xml->cServico->PrazoEntrega>0) {

        //         $dados = [
        //                     "error" => false,
        //                     "valor" => $xml->cServico->Valor,
        //                     "prazo" => $xml->cServico->PrazoEntrega,
        //                     "obs" => !empty($xml->cServico->obsFim) ? $xml->cServico->obsFim : null
        //         ];

        //         return $dados;

        //     } else {

        //         $dados = [
        //             "error" => true,
        //             "message" => !empty($xml->cServico->MsgErro) ? $xml->cServico->MsgErro : $this->error($Erro),
        //         ];

        //         // echo '<script>console.log("'.$xml->cServico->MsgErro.'");</script>';
        //     }
        // }
        // else {

        //     $dados = [
        //         "error" => true,
        //         "message" => !empty($xml->cServico->MsgErro) ? $xml->cServico->MsgErro : $this->error($Erro),
        //     ];
        //     // echo '<script>alert("'.$xml->cServico->MsgErro.'");</script>';
        // }

        return (object)$xml->cServico;
    }

    public function sedex($origin=null, $destiny=null, $code=null)
    {

        $codigo_servico = $code ? preg_replace("/[^0-9]/", "", $code) : '04014';
        return $this->calculaFrete($origin, $destiny, $codigo_servico);

    }

    public function pac($origin=null, $destiny=null, $code=null)
    {

        $codigo_servico = $code ? preg_replace("/[^0-9]/", "", $code) : '04510';
        return $this->calculaFrete($origin, $destiny, $codigo_servico);

    }


    public function tracking($code, $type="array")
    {

        $url = "https://www.websro.com.br/detalhes.php?P_COD_UNI=".strtoupper($code);

        $contents = file_get_contents($url);

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

    }

    public function error($cod) {

        $erros = [
                    "0" => "Processamento com sucesso",
                    "-1" => "Código de serviço inválido",
                    "-2" => "CEP de origem inválido",
                    "-3" => "CEP de destino inválido",
                    "-4" => "Peso excedido",
                    "-5" => "O Valor Declarado não deve exceder R$ 10.000,00",
                    "-6" => "Serviço indisponível para o trecho informado",
                    "-7" => "O Valor Declarado é obrigatório para este serviço",
                    "-8" => "Este serviço não aceita Mão Própria",
                    "-9" => "Este serviço não aceita Aviso de Recebimento",
                    "-10" => "Precificação indisponível para o trecho informado",
                    "-11" => "Para definição do preço deverão ser informados, também, o comprimento, a largura e altura do objeto em centímetros (cm).",
                    "-12" => "Comprimento inválido.",
                    "-13" => "Largura inválida.",
                    "-14" => "Altura inválida.",
                    "-15" => "O comprimento não pode ser maior que 105 cm.",
                    "-16" => "A largura não pode ser maior que 105 cm.",
                    "-17" => "A altura não pode ser maior que 105 cm.",
                    "-18" => "A altura não pode ser inferior a 2 cm.",
                    "-20" => "A largura não pode ser inferior a 11 cm.",
                    "-22" => "O comprimento não pode ser inferior a 16 cm.",
                    "-23" => "A soma resultante do comprimento + largura + altura não deve superar a 200 cm.",
                    "-24" => "Comprimento inválido.",
                    "-25" => "Diâmetro inválido",
                    "-26" => "Informe o comprimento.",
                    "-27" => "Informe o diâmetro.",
                    "-28" => "O comprimento não pode ser maior que 105 cm.",
                    "-29" => "O diâmetro não pode ser maior que 91 cm.",
                    "-30" => "O comprimento não pode ser inferior a 18 cm.",
                    "-31" => "O diâmetro não pode ser inferior a 5 cm.",
                    "-32" => "A soma resultante do comprimento + o dobro do diâmetro não deve superar a 200 cm.",
                    "-33" => "Sistema temporariamente fora do ar. Favor tentar mais tarde.",
                    "-34" => "Código Administrativo ou Senha inválidos.",
                    "-35" => "Senha incorreta.",
                    "-36" => "Cliente não possui contrato vigente com os Correios.",
                    "-37" => "Cliente não possui serviço ativo em seu contrato.",
                    "-38" => "Serviço indisponível para este código administrativo.",
                    "-39" => "Peso excedido para o formato envelope",
                    "-40" => "Para definicao do preco deverao ser informados, tambem, o comprimento e a largura e altura do objeto em centimetros (cm).",
                    "-41" => "O comprimento nao pode ser maior que 60 cm.",
                    "-42" => "O comprimento nao pode ser inferior a 16 cm.",
                    "-43" => "A soma resultante do comprimento + largura nao deve superar a 120 cm.",
                    "-44" => "A largura nao pode ser inferior a 11 cm.",
                    "-45" => "A largura nao pode ser maior que 60 cm.",
                    "-888" => "Erro ao calcular a tarifa",
                    "006" => "Localidade de origem não abrange o serviço informado",
                    "007" => "Localidade de destino não abrange o serviço informado",
                    "008" => "Serviço indisponível para o trecho informado",
                    "009" => "CEP inicial pertencente a Área de Risco.",
                    "010" => "CEP de destino está temporariamente sem entrega domiciliar. A entrega será efetuada na agência indicada no Aviso de Chegada que será entregue no endereço do destinatário",
                    "011" => "CEP de destino está sujeito a condições especiais de entrega pela ECT e será realizada com o acréscimo de até 7 (sete) dias úteis ao prazo regular.",
                    "7" => "Serviço indisponível, tente mais tarde",
                    "99" => "Outros erros diversos do .Net",
                ];

        return $erros[$cod];
    }







}