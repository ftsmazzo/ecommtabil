<?php
namespace App\Lib;

use Exception;

/**
 * Realizar o upload do documento
 * Cadastrar o webhook(POSTBack) //OPCIONAL
 * Cadastrar os signatários
 * Enviar o documento para assinatura
 * Utilizar o EMBED D4Sign para exibir o documento em seu website //OPCIONAL
 *
 */

class D4Sign
{

    protected $baseurl;
    protected $tokenAPI;
    protected $cryptKey;
    protected $timeout;
    protected $version;
    protected $uuid_safe;
    protected $uuid_folder;
    protected $signers = [];

    public function __construct($environment = "production")
    {

        $this->timeout = 240;

        $this->version = "v1";

        $this->baseurl = $environment == "production"
            ? "https://secure.d4sign.com.br/api"
            : "https://sandbox.d4sign.com.br/api";
    }

    public function setTokenAPI($tokenAPI)
    {
        $this->tokenAPI = $tokenAPI;
    }

    public function setCryptKey($cryptKey)
    {
        $this->cryptKey = $cryptKey;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function setVersion($version)
    {
        $this->version = $version;
    }

    public function setSafe($uuid_safe)
    {
        $this->uuid_safe = $uuid_safe;
    }

    public function setFolder($uuid_folder)
    {
        $this->uuid_folder = $uuid_folder;
    }

    public function getSigners()
    {
        return $this->signers;
    }

    public function  setCredentials($tokenAPI, $cryptKey)
    {

        if (!$tokenAPI) {
            throw new Exception("The Token API is required");
        }

        if (!$cryptKey) {
            throw new Exception("The Crypt Key is required");
        }

        $this->setTokenAPI($tokenAPI);

        $this->setCryptKey($cryptKey);
    }

    private function getCredentials()
    {

        if (!$this->tokenAPI) {
            throw new Exception("The Token API is required");
        }

        if (!$this->cryptKey) {
            throw new Exception("The Crypt Key is required");
        }

        $params = [
            "tokenAPI" => $this->tokenAPI,
            "cryptKey" => $this->cryptKey
        ];

        return http_build_query($params);
    }

    /**
     * Exibir saldo da conta
     * Este objeto retornará o balanço da sua conta
     *
     * @return void
     */
    public function getAccountBalance()
    {

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/account/balance";

        $url = $endpoint . "?" . $access_params;

        $account = $this->request($url, "GET");

        return $account;
    }

    /**
     * Listar todos os cofres
     * Este objeto retornará TODOS os COFRES da sua conta.
     *
     * @return void
     */
    public function listSafes()
    {

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/safes";

        $url = $endpoint . "?" . $access_params;

        $list = $this->request($url, "GET");

        return $list;
    }

    /**
     * List ALL Documents
     *
     * @return void
     */
    public function listAllDocuments($page = false)
    {

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/documents";

        $url = $endpoint . "?" . $access_params;

        if ($page) {
            $data["pg"] = $page;
        }

        $list = $this->request($url, "GET", $data ?? []);

        return $list;
    }

    /**
     * List Specific Documents
     *
     * @return void
     */
    public function listDocument($uuid)
    {

        if (!$uuid) {
            throw new Exception("The Document ID is required");
        }

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$uuid}";

        $url = $endpoint . "?" . $access_params;

        return $this->request($url, "GET");
    }

    /**
     * Pega informações de um documento específico
     *
     * @return void
     */
    public function getDocument($uuid)
    {

        if (!$uuid) {
            throw new Exception("The Document ID is required");
        }

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$uuid}";

        $url = $endpoint . "?" . $access_params;

        $document = $this->request($url, "GET");

        if (isset($document[0])) {
            return $document[0];
        } else {
            return $document;
        }
    }

    /**
     * List Documents By Safe
     *
     * @return void
     */
    public function listDocumentsBySafe($uuid_safe = null, $page = false)
    {

        if ($uuid_safe) {
            $this->setSafe($uuid_safe);
        }

        if (!$this->uuid_safe) {
            throw new Exception("The Safe ID is required");
        }

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$this->uuid_safe}/safe";

        if ($page) {
            $data["pg"] = $page;
        }

        $url = $endpoint . "?" . $access_params;

        $list = $this->request($url, "GET", $data ?? []);

        return $list;
    }

    /**
     * List Folder Of Safe
     *
     * @return void
     */
    public function listFolders($uuid_safe = null)
    {

        if ($uuid_safe) {
            $this->setSafe($uuid_safe);
        }

        if (!$this->uuid_safe) {
            throw new Exception("The Safe ID is required");
        }

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/folders/{$this->uuid_safe}/find";

        $url = $endpoint . "?" . $access_params;

        $list = $this->request($url, "GET");

        return $list;
    }

    /**
     * List Documents By Folder of Safe
     *
     * @return void
     */
    public function listDocumentsByFolder($uuid_folder = null, $uuid_safe = null, $page = null)
    {

        if ($uuid_safe) {
            $this->setSafe($uuid_safe);
        }

        if ($uuid_folder) {
            $this->setFolder($uuid_folder);
        }

        if (!$uuid_safe) {
            throw new Exception("The Safe ID is required");
        }

        if (!$uuid_folder) {
            throw new Exception("The Folder ID is required");
        }

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$this->uuid_safe}/safe/{$this->uuid_folder}";

        if ($page) {
            $data["pg"] = $page;
        }

        $url = $endpoint . "?" . $access_params;

        $list = $this->request($url, "GET", $data ?? []);

        return $list;
    }

    /**
     * List Documents By Status
     *
     * @return void
     */
    public function listDocumentsByStatus($id_status, $page = null)
    {

        if (!$id_status) {
            throw new Exception("The Status ID is required");
        }

        $allowed_status = [
            1 => "Processando",
            2 => "Aguardando Signatários",
            3 => "Aguardando Assinaturas",
            4 => "Finalizado",
            5 => "Arquivado",
            6 => "Cancelado",
            7 => "Editando",
        ];

        if (!array_key_exists($id_status, $allowed_status)) {
            throw new Exception("The Status ID is not allowed");
        }

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$id_status}/status";

        if ($page) {
            $data["pg"] = $page;
        }

        $url = $endpoint . "?" . $access_params;

        $list = $this->request($url, "GET", $data ?? []);

        return $list;

    }

    /**
     * Create Folder on Safe
     *
     * @return void
     */
    public function createFolder($folder_name, $uuid_safe = null)
    {

        if (!$folder_name) {
            throw new Exception("The Folder Name is required");
        }

        if ($uuid_safe) {
            $this->setSafe($uuid_safe);
        }

        if (!$this->uuid_safe) {
            throw new Exception("The Safe ID is required");
        }

        $endpoint = $this->baseurl . "/" . $this->version . "/folders/{$this->uuid_safe}/create";

        $access_params = $this->getCredentials();

        $url = $endpoint . "?" . $access_params;

        $data["folder_name"] = $folder_name;

        $create = $this->request($url, "POST", $data);

        return $create;
    }

    /**
     * Rename Folder on Safe
     *
     * @param string $uuid_safe Safe Identify
     * @param string $uuid_folder Folder Identidy
     * @param string $folder_name New Folder Name
     * @return void
     */
    public function renameFolder($folder_name, $uuid_folder = null, $uuid_safe = null)
    {

        if (!$folder_name) {
            throw new Exception("The Folder Name is required");
        }

        if ($uuid_safe) {
            $this->setSafe($uuid_safe);
        }

        if ($uuid_folder) {
            $this->setFolder($uuid_folder);
        }

        if (!$this->uuid_safe) {
            throw new Exception("The Safe ID is required");
        }

        if (!$this->uuid_folder) {
            throw new Exception("The Folder ID is required");
        }

        $endpoint = $this->baseurl . "/" . $this->version . "/folders/{$this->uuid_safe}/rename";

        $access_params = $this->getCredentials();

        $url = $endpoint . "?" . $access_params;

        $data["folder_name"] = $folder_name;
        $data["uuid_folder"] = $this->uuid_folder;

        $rename = $this->request($url, "POST", $data);

        return $rename;
    }

    /**
     * Get Document Dimensions
     *
     * @param string $uuid_document
     * @return void
     */
    public function getDocumentDimension($uuid_document)
    {

        if (!$uuid_document) {
            throw new Exception("The Document ID is required");
        }

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$uuid_document}/dimensions";

        $access_params = $this->getCredentials();

        $url = $endpoint . "?" . $access_params;

        $dimensions = $this->request($url, "GET");

        return $dimensions;
    }


    /**
     * UPLOAD de um documento principal
     *
     * @param string $file (obrigatório) Arquivo que será enviado para os servidores da D4Sign. MIME Types aceitos PDF,DOC,DOCX,JPG,PNG,BMP
     * @param string $uuid_safe Identificador do cofre onde o arquivo ficará armazenado.
     * @param string $uuid_folder Para que o documento fique armazenado dentro da pasta, informe o UUID dela.
     * @return void
     */
    public function uploadDocument($file, $postname = null, $uuid_safe = null, $uuid_folder = null)
    {

        if (!$file) {
            throw new Exception("The File is required");
        }

        if ($uuid_safe) {
            $this->setSafe($uuid_safe);
        }

        if ($uuid_folder) {
            $this->setFolder($uuid_folder);
        }

        if (!$this->uuid_safe) {
            throw new Exception("The Safe ID is required");
        }

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$this->uuid_safe}/upload";

        $access_params = $this->getCredentials();

        $url = $endpoint . "?" . $access_params;

        $data["file"] = $this->_getCurlFile($file, null, $postname);

        if ($this->uuid_folder) {
            $data["uuid_folder"] = $this->uuid_folder;
        }

        $upload = $this->request($url, "POST", $data, 200, "multipart/form-data;");

        return $upload;
    }


    public function cancelDocument($uuid_document, $comment = null)
    {

        if (!$this->tokenAPI) {
            throw new Exception("The Token API is required");
        }

        if (!$this->cryptKey) {
            throw new Exception("The Crypt Key is required");
        }

        if (!$uuid_document) {
            throw new Exception("The Document ID is required");
        }

        // POST {{host_d4sign}}/documents/{{uuid_document}}/cancel?tokenAPI={{tokenAPI}}&cryptKey={{cryptKey}}

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$uuid_document}/cancel";

        $access_params = $this->getCredentials();

        $url = $endpoint . "?" . $access_params;

        $cancel = $this->request($url, "POST");

        return $cancel;


    }

    public function downloadDocument($uuid_document, $type = "PDF", $language = "pt")
    {

        if (!$this->tokenAPI) {
            throw new Exception("The Token API is required");
        }

        if (!$this->cryptKey) {
            throw new Exception("The Crypt Key is required");
        }

        if (!$uuid_document) {
            throw new Exception("The Document ID is required");
        }

        if (!in_array(strtoupper($type), ["PDF", "ZIP"])) {
            throw new Exception("The type is invalid. Only PDF or ZIP");
        }

        if (!in_array(strtolower($language), ["pt", "en"])) {
            throw new Exception("The language is invalid. Only pt or en");
        }

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$uuid_document}/download";

        $access_params = $this->getCredentials();

        $url = $endpoint . "?" . $access_params;

        $data["type"] = $type;
        $data["language"] = $language;

        $download = $this->request($url, "POST", $data);

        return $download;
    }

    public function uploadSlaveDocument($file, $uuid_document)
    {

        if (!$this->tokenAPI) {
            throw new Exception("The Token API is required");
        }

        if (!$this->cryptKey) {
            throw new Exception("The Crypt Key is required");
        }

        if (!$file) {
            throw new Exception("The File is required");
        }

        if (!$uuid_document) {
            throw new Exception("The Document ID is required");
        }

        // {UUID-DOC-PRINCIPAL}/uploadslave

        // POST /documents/{UUID-SAFE}/upload

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$uuid_document}/uploadslave";

        $access_params = $this->getCredentials();

        $url = $endpoint . "?" . $access_params;

        // POST
    }


    // {
    //     "base64_binary_file": "JVhsdCAdwesAD2dsadfASDQW...",
    //     "mime_type": "application/pdf",
    //     "name": "Meu contrato de venda",
    //     "uuid_folder": "{UUID DA PASTA}"
    // }
    // Parâmetro	Descrição
    // base64_binary_file (obrigatório)	Arquivo que será enviado para os servidores da D4Sign. ATENÇÃO: Você deve enviar o binário do seu arquivo codificado em BASE64
    // mime_type (obrigatório)	Informe o MIMETYPE do seu arquivo
    // name (opcional)	Informe o nome do seu arquivo
    // uuid_folder (opcional)	Para que o documento fique armazenado dentro da pasta, informe o UUID dela.

    public function uploadBinaryDocument($file, $uuid_safe = null, $uuid_folder = null)
    {

        if (!$this->tokenAPI) {
            throw new Exception("The Token API is required");
        }

        if (!$this->cryptKey) {
            throw new Exception("The Crypt Key is required");
        }

        if (!$file) {
            throw new Exception("The File is required");
        }

        if ($uuid_safe) {
            $this->setSafe($uuid_safe);
        }

        if ($uuid_folder) {
            $this->setFolder($uuid_folder);
        }

        if (!$this->uuid_safe) {
            throw new Exception("The Safe ID is required");
        }

        // File (obrigatório)	Arquivo que será enviado para os servidores da D4Sign. MIME Types aceitos PDF,DOC,DOCX,JPG,PNG,BMP
        // uuid_folder (opcional)	Para que o documento fique armazenado dentro da pasta, informe o UUID dela.

        // POST /documents/{UUID-SAFE}/upload

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$this->uuid_safe}/upload";

        $access_params = $this->getCredentials();

        $url = $endpoint . "?" . $access_params;

        // POST
    }

    /**
     * Enviar documento para assinatura
     *
     * Esse objeto enviará o documento para assinatura, ou seja, o documento entrará na fase 'Aguardando assinaturas', onde, a partir dessa fase, os signatários poderão assinar os documentos.
     *
     * @param string $uuid_document ID do documento
     * @param string $message (opcional) Mensagem que será enviada para os signatários, caso o parâmetro skip_email esteja definido como 0
     * @param integer $workflow 0 = Para não seguir o workflow. e 1 = Para seguir o workflow.
     * ATENÇÃO: Nos casos em que o EMBED ou a ASSINATURA PRESENCIAL estiver sendo usado, ou seja, quando o signatário for efetuar a assinatura diretamente do seu website ou em seu Tablet, o parâmetro skip_email DEVERÁ ser definido como 1
     * @param integer $skip_email 0 = Os signatários serão avisados por e-mail que precisam assinar um documento. e 1 = O e-mail não será disparado.
     * Caso o parâmetro workflow seja definido como 1, o segundo signatário só receberá a mensagem de que há um documento aguardando sua assinatura DEPOIS que o primeiro signatário efetuar a assinatura, e assim sucessivamente.
     * Porém, caso seja definido como 0, todos os signatários poderão assinar o documento ao mesmo tempo.
     * @return void
     */
    public function sendToSigner($uuid_document, $message = null, $workflow = 0, $skip_email = 0)
    {

        if (!$this->tokenAPI) {
            throw new Exception("The Token API is required");
        }

        if (!$this->cryptKey) {
            throw new Exception("The Crypt Key is required");
        }

        if (!$uuid_document) {
            throw new Exception("The Document ID is required");
        }

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$uuid_document}/sendtosigner";

        $url = $endpoint . "?" . $access_params;

        if ($message) {
            $data["message"] = $message;
        }

        $data["skip_email"] = $skip_email;
        $data["workflow"] = $workflow;
        $data["tokenAPI"] = $this->tokenAPI;

        $response = $this->request($url, "POST", $data);

        return $response;
    }

    /**
     * Listar signatários de um documento
     *
     * @param string $uuid_document UUID do documento que deverá ser listado.
     * @return void
     */
    public function listSigners($uuid_document)
    {

        if (!$uuid_document) {
            throw new Exception("The Document ID is required");
        }

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$uuid_document}/list";

        $url = $endpoint . "?" . $access_params;

        $list = $this->request($url, "GET");

        return $list;
    }

    /**
     * Listar Grupos de Assinaturas
     *
     * @param string $uuid_safe UUID do COFRE que deverá ser listado.
     * @return void
     */
    public function listGroups($uuid_safe)
    {

        if (!$uuid_safe) {
            throw new Exception("The Safe ID is required");
        }

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/groups/{$uuid_safe}";

        $url = $endpoint . "?" . $access_params;

        $list = $this->request($url, "GET");

        return $list;
    }

    /**
     * Cadastrar signatários
     * Esse objeto realizará o cadastro dos signatários do documento, ou seja, quais pessoas precisam assinar esse documento.
     *
     * @param string $uuid_document
     * @return void
     */
    public function createSigner($uuid_document)
    {

        if (!$uuid_document) {
            throw new Exception("The Document ID is required");
        }

        if (!$this->signers) {
            throw new Exception("The signers are not found");
        }

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$uuid_document}/createlist";

        $url = $endpoint . "?" . $access_params;

        $data["signers"] = json_encode($this->signers);

        $response = $this->request($url, "POST", $data);

        return $response;
    }

    /**
     * Alterar signatário
     *
     * @param string $uuid_document ID do documento
     * @param string $current ANTIGO e-mail ou número de WhatsApp do signatário
     * @param string $new NOVO e-mail ou número de WhatsApp do signatário
     * @param string $keySigner Chave do signatário
     * @return void
     */
    public function alterSigner($uuid_document, $current, $new, $keySigner = null)
    {

        if (!$uuid_document) {
            throw new Exception("The Document ID is required");
        }

        if (!$current) {
            throw new Exception("The current E-mail/Whatsapp number is required");
        }

        if (!$new) {
            throw new Exception("The new E-mail/Whatsapp number is required");
        }

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$uuid_document}/changeemail";

        $url = $endpoint . "?" . $access_params;

        $data["email-before"] = $current;
        $data["email-after"] = $new;

        if (!empty($keySigner)) {
            $data["key-signer"] = $keySigner;
        }

        $list = $this->request($url, "POST", $data);

        return $list;
    }

    public function alterSignerSMS($uuid_document, $email, $sms_number, $keySigner = null)
    {

        if (!$uuid_document) {
            throw new Exception("The Document ID is required");
        }

        if (!$email) {
            throw new Exception("The E-mail is required");
        }

        if (!$sms_number) {
            throw new Exception("The SMS number is required");
        }

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$uuid_document}/changesmsnumber";

        $url = $endpoint . "?" . $access_params;

        $data["email"] = $email;
        $data["sms-number"] = $sms_number;

        if (!empty($keySigner)) {
            $data["key-signer"] = $keySigner;
        }

        $list = $this->request($url, "POST", $data);

        return $list;
    }

    /**
     * Adicionar uma pessoa na lista de signatários
     *
     * @param string $email E-mail do signatário (pessoa que precisa assinar o documento)
     * @param int $act Ação da assinatura. [1 à 13]
     * @param int $foreign Indica se o signatário é estrangeiro, ou seja, se possui CPF. 0 = Possui CPF (Brasileiro). 1 = Não possui CPF (Estrangeiro).
     * @param array $extra
     * @return void
     */
    public function addSigner($email, $act, $foreign, $extra = [])
    {

        // VALIDAR E-MAIL
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Signer e-mail address {$email} invalid!");
        } else {
            $signer["email"] = $email;
        }

        // VALIDAR A AÇÃO DO SIGNATÁRIO
        if (!in_array($act, array_keys($this->actions()))) {
            throw new Exception("Signer act invalid!");
        } else {
            $signer["act"] = $act;
        }

        // VALIDAR A AÇÃO DO SIGNATÁRIO
        if (!in_array($foreign, [0, 1])) {
            throw new Exception("Signer foreign value invalid!");
        } else {
            $signer["foreign"] = $foreign;
        }

        // CAMPOS OBRIGATÓRIOS
        $signer["certificadoicpbr"] = 0;
        $signer["assinatura_presencial"] = 0;

        if (!empty($extra)) {

            $allowed_params = [
                "email", // (obrigatório) E-mail do signatário (pessoa que precisa assinar o documento)
                "act", // (obrigatório) Ação da assinatura.
                "foreign", // (obrigatório) Indica se o signatário é estrangeiro, ou seja, se possui CPF. 0 = Possui CPF (Brasileiro). 1 = Não possui CPF (Estrangeiro).
                "foreign_lang", // Indica qual idioma será utilizado para o estrangeiro. en = Inglês (US) es = Espanhol ptBR = Português
                "certificadoicpbr", // (obrigatório) Indica se o signatário DEVE efetuar a assinatura com um Certificado Digital ICP-Brasil.
                "assinatura_presencial", // (obrigatório) Indica se o signatário DEVE efetuar a assinatura de forma presencial.
                "docauth", // (opcional) Indica se o signatário DEVE efetuar a assinatura apresentando um documento com foto.
                "docauthandselfie", // (opcional) Indica se o signatário DEVE efetuar a assinatura apresentando um documento com foto e depois registrar uma selfie segurando o mesmo documento.
                "embed_methodauth", // (opcional) Indica qual o método de autenticação será utilizado no EMBED.
                "embed_smsnumber", // (opcional) Indica o número de telefone que será enviado o TOKEN.
                "upload_allow", // (opcional) Indica se o signatário poderá enviar outros documentos
                "upload_obs", // (opcional) Se o upload_allow for setado como 1, indique aqui quais documentos o signatário deve enviar
                "after_position", // (opcional) Caso o seu documento esteja na fase "Aguardando assinaturas" e a sequencia de assinatura estiver sendo seguida, você poderá determinar qual a posição do signatário que você deseja adicionar.
                "skipemail", // (opcional) Defina com o valor 1 para não enviar e-mails ao signatário
                "whatsapp_number", // (opcional) Para enviar para o WhatsApp, digite o número no formato E.164. Ex.: Ex.: +5511953020202 (código do país, DDD, número do telefone)
                "uuid_grupo", // (opcional) Para cadastrar um grupo de assinaturas, insira o UUID do grupo.
                "certificadoicpbr_tipo", // (opcional) Definir uma modalidade de assinatura com certificado digital. 1 = Qualquer certificado2 = e-CPF3 = e-CNPJ
                "certificadoicpbr_cpf", // (opcional) Entre com o CPF do signatário. DEIXE EM BRANCO PARA ACEITAR QUALQUER CERTIFICADO E-CPF.
                "certificadoicpbr_cnpj", // (opcional) Entre com o CNPJ do signatário. DEIXE EM BRANCO PARA ACEITAR QUALQUER CERTIFICADO E-CNPJ.
                "password_code", // (opcional) Entre com um código para o acesso do signatário. DEIXE EM BRANCO PARA REMOVER O CÓDIGO ANTERIOR.
                "auth_pix", // (opcional) Autenticacão bancária por PIX
                "auth_pix_nome", // Caso o auth_pix seja 1, o nome do signatário será obrigatório
                "auth_pix_cpf", // Caso o auth_pix seja 1, o CPF do signatário será obrigatório
                "videoselfie", // Caso o videoselfie seja 1, o signatário deverá registrar uma vídeo selfie no momento da assinatura
                "d4sign_score", // (opcional) Ativação da D4Sign Score - Consulta na base de dados do Governo Federal - Só será aceita se docauthandselfie =1 ou videoselfie = 1.
                "d4sign_score_nome", // Caso o d4sign_score seja 1, o nome do signatário será obrigatório
                "d4sign_score_cpf", // Caso o d4sign_score seja 1, o CPF do signatário será obrigatório
                "d4sign_score_similarity", // Nível de similaridade exigida - min 70 - max 90
            ];

            foreach ($extra as $key => $val) {

                if (!in_array($key, $allowed_params)) {
                    throw new Exception("The param {$key} is invalid");
                } else {
                    $signer[$key] = $val;
                }
            }
        }

        $this->signers[] = $signer;
    }






    /**
     * Listar Webhook de um documento
     *
     * @param string $uuid_document ID do documento
     * @return void
     */
    public function listWebhook($uuid_document)
    {

        if (!$uuid_document) {
            throw new Exception("The Document ID is required");
        }

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$uuid_document}/webhooks";

        $url = $endpoint . "?" . $access_params;

        $response = $this->request($url, "GET");

        return $response;
    }

    /**
     * Undocumented function
     *
     * @param string $uuid_document ID do documento
     * @param string $url (obrigatório) URL que receberá o POSTBack da D4Sign após o documento atingir a fase FINALIZAD
     * @return void
     */
    public function createWebhook($uuid_document, $url_webhook)
    {

        if (!$uuid_document) {
            throw new Exception("The Document ID is required");
        }

        if (!$url_webhook) {
            throw new Exception("The Webhook URL is required");
        }

        $access_params = $this->getCredentials();

        $endpoint = $this->baseurl . "/" . $this->version . "/documents/{$uuid_document}/webhooks";

        $url = $endpoint . "?" . $access_params;

        $data["url"] = $url_webhook;

        $response = $this->request($url, "POST", $data);

        return $response;
    }


    private function actions()
    {

        $actions = [
            1 => "Assinar",
            2 => "Aprovar",
            3 => "Reconhecer",
            4 => "Assinar como parte",
            5 => "Assinar como testemunha",
            6 => "Assinar como interveniente",
            7 => "Acusar recebimento",
            8 => "Assinar como Emissor, Endossante e Avalista",
            9 => "Assinar como Emissor, Endossante, Avalista, Fiador",
            10 => "Assinar como fiador",
            11 => "Assinar como parte e fiador",
            12 => "Assinar como responsável solidário",
            13 => "Assinar como parte e responsável solidário",
        ];

        return $actions;
    }

    private function checkExtensionFile($file)
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $alloweds = ["PDF", "DOC", "DOCX", "JPG", "PNG", "BMP"];
        $ext = strtoupper($extension);
        return in_array($ext, $alloweds);
    }

    protected function doRequest($url, $method, $data = [], $contentType = false)
    {

        $curl = curl_init();

        $headers[] = "Accept: application/json";

        if ($contentType) {
            $headers[] = "Content-Type: {$contentType}";
        }

        switch ($method) {

            case "GET":
                curl_setopt($curl, CURLOPT_HTTPGET, true);
                if (!empty($data)) {
                    $url .= "&" . http_build_query($data);
                }
                break;

            case "POST":
                curl_setopt($curl, CURLOPT_POST, true);
                if (!empty($data)) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                }
                break;

            case "DELETE":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
                if (!empty($data)) {
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                }
                break;
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_HEADER, true);

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;

    }

    public function request($url, $method, $data = [], $expectedHttpCode = 200, $contentType = false)
    {
        $response = $this->doRequest($url, $method, $data, $contentType);

        return $this->parseResponse($url, $response, $expectedHttpCode);
    }

    protected function parseResponse($url, $response, $expectedHttpCode)
    {
        $header = false;
        $content = [];
        $status = 200;

        $lines = explode("\r\n", $response);

        foreach ($lines as $line) {

            if (strpos($line, "HTTP/2") === 0 || strpos($line, "HTTP/1.1") === 0) {

                $lineParts = explode(" ", $line);
                $status = intval($lineParts[1]);
                $header = true;

            } else if ($line == "") {

                $header = false;

            } else if ($header) {

                $line = explode(": ", $line);
                if ($line[0] == "Status") {
                    $status = intval(substr($line[1], 0, 3));
                }

            } else {

                $content[] = $line;
            }
        }

        if ($status !== $expectedHttpCode) {
            throw new Exception($this->utf8_ansi($content[0]));
        }

        $object = json_decode(implode("\n", $content));

        return $object;

    }

    private function utf8_ansi($valor = '') {

        $utf8_ansi2 = array(
        "\u00c0" => "À",
        "\u00c1" => "Á",
        "\u00c2" => "Â",
        "\u00c3" => "Ã",
        "\u00c4" => "Ä",
        "\u00c5" => "Å",
        "\u00c6" => "Æ",
        "\u00c7" => "Ç",
        "\u00c8" => "È",
        "\u00c9" => "É",
        "\u00ca" => "Ê",
        "\u00cb" => "Ë",
        "\u00cc" => "Ì",
        "\u00cd" => "Í",
        "\u00ce" => "Î",
        "\u00cf" => "Ï",
        "\u00d1" => "Ñ",
        "\u00d2" => "Ò",
        "\u00d3" => "Ó",
        "\u00d4" => "Ô",
        "\u00d5" => "Õ",
        "\u00d6" => "Ö",
        "\u00d8" => "Ø",
        "\u00d9" => "Ù",
        "\u00da" => "Ú",
        "\u00db" => "Û",
        "\u00dc" => "Ü",
        "\u00dd" => "Ý",
        "\u00df" => "ß",
        "\u00e0" => "à",
        "\u00e1" => "á",
        "\u00e2" => "â",
        "\u00e3" => "ã",
        "\u00e4" => "ä",
        "\u00e5" => "å",
        "\u00e6" => "æ",
        "\u00e7" => "ç",
        "\u00e8" => "è",
        "\u00e9" => "é",
        "\u00ea" => "ê",
        "\u00eb" => "ë",
        "\u00ec" => "ì",
        "\u00ed" => "í",
        "\u00ee" => "î",
        "\u00ef" => "ï",
        "\u00f0" => "ð",
        "\u00f1" => "ñ",
        "\u00f2" => "ò",
        "\u00f3" => "ó",
        "\u00f4" => "ô",
        "\u00f5" => "õ",
        "\u00f6" => "ö",
        "\u00f8" => "ø",
        "\u00f9" => "ù",
        "\u00fa" => "ú",
        "\u00fb" => "û",
        "\u00fc" => "ü",
        "\u00fd" => "ý",
        "\u00ff" => "ÿ");

        return strtr($valor, $utf8_ansi2);

    }




    /*




	public function changeemail($documentKey, $email_before, $email_after, $key='')
    {
        $data = array("email-before" => json_encode($email_before),"email-after" => json_encode($email_after),"key-signer" => json_encode($key));
        return $this->client->request("/documents/$documentKey/changeemail", "POST", $data, 200);
    }

	public function find($documentKey = '')
    {
        $data = array();
        return $this->client->request("/documents/$documentKey", "GET", $data, 200);
    }

    public function listsignatures($documentKey)
    {
    	$data = array();
    	return $this->client->request("/documents/$documentKey/list", "GET", $data, 200);
    }

    public function status($status)
    {
    	$data = array();
    	return $this->client->request("/documents/$status/status", "GET", $data, 200);
    }

    public function safe($safeKey, $uuid_folder = '')
    {
    	$data = array();
    	return $this->client->request("/documents/$safeKey/safe/$uuid_folder", "GET", $data, 200);
    }

    public function upload($uuid_safe, $filePath, $uuid_folder = '')
    {

    	if (!$uuid_safe){
    		return 'UUID Safe not set.';
    	}

		return $this->_upload($uuid_safe, $filePath, $uuid_folder);

    }

    public function cancel($documentKey)
    {
    	$data = array();
    	return $this->client->request("/documents/$documentKey/cancel", "POST", $data, 200);
    }

    public function createList($documentKey, $signers, $skipEmail = false)
    {
        $data = array("signers" => json_encode($signers));
        return $this->client->request("/documents/$documentKey/createlist", "POST", $data, 200);
    }

    public function makedocumentbytemplate($documentKey, $name_document, $templates, $uuid_folder = '')
    {
    	$data = array("templates" => json_encode($templates), "name_document"=>json_encode($name_document), "uuid_folder"=>json_encode($uuid_folder));
    	return $this->client->request("/documents/$documentKey/makedocumentbytemplate", "POST", $data, 200);
    }

    public function webhookadd($documentKey, $url)
    {
    	$data = array("url" => json_encode($url));
    	return $this->client->request("/documents/$documentKey/webhooks", "POST", $data, 200);
    }

    public function webhooklist($documentKey)
    {
    	return $this->client->request("/documents/$documentKey/webhooks", "GET", null, 200);
    }

    public function sendToSigner($documentKey, $message = '', $workflow = '0', $skip_email = false)
    {
    	$data = array("message" => json_encode($message), "workflow" => json_encode($workflow), "skip_email" => json_encode($skip_email));

    	return $this->client->request("/documents/$documentKey/sendtosigner", "POST", $data, 200);
    }

    public function addinfo($documentKey, $email = '', $display_name = '', $documentation = '', $birthday = '', $key='')
    {
    	$data = array("key_signer" => json_encode($key),"email" => json_encode($email), "display_name" => json_encode($display_name), "documentation" => json_encode($documentation), "birthday" => json_encode($birthday));

    	return $this->client->request("/documents/$documentKey/addinfo", "POST", $data, 200);
    }

    public function resend($documentKey, $email, $key='')
    {
        $data = array("email" => json_encode($email),"key_signer" => json_encode($key));
        return $this->client->request("/documents/$documentKey/resend", "POST", $data, 200);
    }

    public function getfileurl($documentKey, $type)
    {
    	$data = array("type" => json_encode($type));
    	return $this->client->request("/documents/$documentKey/download", "POST", $data, 200);
    }




    */
    private function _getCurlFile($filename, $contentType = null, $postname = null)
    {

        if (function_exists('curl_file_create')) {

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $finfo = finfo_file($finfo, $filename);
            $file = curl_file_create($filename, $finfo, (!is_null($postname) ? $postname : basename($filename)));
            return $file;

        } else {

            // Use the old style if using an older version of PHP
            $postname = !is_null($postname) ? $postname : basename($filename);
            $value = "@{$filename};filename=" . $postname;
            if ($contentType) {
                $value .= ';type=' . $contentType;
            } else {
                $value .= ';type=' . mime_content_type($filename);
            }
            return $value;
        }

    }
}
