<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\Redirect;
use App\Core\Request;
use App\Services\UsuarioPreferenciaService;

class UsuarioPreferenciaController extends ControllerAdmin
{
    private UsuarioPreferenciaService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new UsuarioPreferenciaService();
    }

    public function theme(Request $request)
    {
        $data = new Data($request->all());

        $tema = $data->tema ?? 'light';

        $this->service->alterarTema(
            $this->user->uid,
            $tema
        );

        $this->log->app(
            'Tema alterado',
            [
                'user'   => $this->user->uid,
                'tabela' => 'preferencia',
            ]
        );

        echo json_encode(['update' => true]);
    }

    public function update(Request $request)
    {
        $data = new Data($request->all(), false);

        $tab = $data->botao;
        $data->del(['botao']);

        $this->service->atualizar(
            $this->user->uid,
            $data->all()
        );

        $this->session->set('tab', $tab);

        $this->message->flash(10);

        $this->log->app(
            'update',
            [
                'page'   => $tab,
                'user'   => $this->user->uid,
                'tabela' => 'preferencia',
            ]
        );

        Redirect::referer();
    }
}
