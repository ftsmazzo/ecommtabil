<?php
namespace App\Controllers\Cliente;

use App\Core\Cache;
use App\Core\ControllerCliente;
use App\Core\Data;
use App\Core\Redirect;
use App\Models\UserPreferencia;

class UserPreferenciaController extends ControllerCliente
{

    public function __construct()
    {
        parent::__construct();
    }

    public function theme($request)
    {

        $data = new Data($request);

        $data->ifEmpty(["tema" => "light"]);

        // ALTERAR PREFERÊNCIA DO USUÁRIO
        UserPreferencia::where("id_user", "=", $this->user->uid)
            ->update(
                [
                    "tema" => $data->tema
                ]
            );

        $this->log->app(
            "Tema alterado",
            [
                "user" => $this->user->uid,
                "tabela" => "preferencia",
            ]
        );

        // EXCLUIR CACHE
        $preferenceCacheName = 'preferences_' . $this->auth->getGuard() . '_' . $this->user->uid;
        (new Cache())->clear($preferenceCacheName);

        // RETORNO AJAX
        echo json_encode(["update" => true]);

        // RETORNO NORMAL
        // Redirect::referer();
    }


    public function update($request)
    {

        $data = new Data($request, false);

        $tab = $data->botao;

        $data->del(["botao"]);

        // ATUALIZAR REGISTRO DO USUÁRIO

        $update = UserPreferencia::where("id_user", "=", $this->user->uid)->update($data->all());

        $this->session->set("tab", $tab);

        $this->message->flash(10);
        $this->log->app(
            "update",
            [
                "page" => $tab,
                "user" => $this->user->uid,
                "tabela" => "preferencia",
            ]
        );

        Redirect::referer();
    }


}
