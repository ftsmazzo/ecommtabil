<?php
namespace App\Controllers\Cliente;

use App\Core\ControllerCliente;

class HomeController extends ControllerCliente
{

    private $estabelecimentos;

    public function __construct()
    {

        parent::__construct();

        if (empty($this->user->extra_data->estabelecimentos)) {
            $this->auth->logout();
        } else {
            $this->estabelecimentos = (array) $this->user->extra_data->estabelecimentos;
        }

        $this->view->addData(
            [
                "title" => "Home",
                "page_title" => "Dashboard",
                "active_menu" => "painel",
            ]
        );
    }

    public function index()
    {
        $params = [];


        echo $this->view->render("cliente/home/index", $params);




    }

}
