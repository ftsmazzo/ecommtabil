<?php

namespace App\Core;

use App\Core\Controller;
use App\Core\Json;

/**
 * Controller base para serviços independentes de “cenário”.
 * Ele é neutro — não sabe se é Admin, Cliente ou API.
 * Detecta automaticamente o guard e o usuário autenticado.
 */
abstract class ControllerServico extends Controller
{

    public function __construct()
    {
        parent::__construct();
    }

}
