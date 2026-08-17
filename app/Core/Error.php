<?php
namespace App\Core;

use \App\Core\View;

class Error
{

    /** @var View */
    private $view;

    public function index($data): void
    {

        $error = filter_var($data["errcode"], FILTER_VALIDATE_INT);

        $this->view = new View();

        $params = [
            "title" => "Ooops! Erro ".$error,
            "errcode" => $error,
            "errmsg" => $this->message($error),
            "url" => URL_BASE . "/",
        ];

        echo $this->view->render('template/error', $params);
    }

    private function message($errcode)
    {
        $errors = [
            "400" => "Bad Request",
            "401" => "Não Autorizado",
            "404" => "Página Não Encontrada",
            "405" => "Método Não Permitido",
            "501" => "Não Implementado",
        ];

        return $errors[$errcode];
    }

}
