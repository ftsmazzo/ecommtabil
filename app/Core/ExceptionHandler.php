<?php

namespace App\Core;

use App\Exceptions\DomainException;
use Throwable;

class ExceptionHandler
{
    public static function handle(Throwable $e): void
    {
        /** @var Log $log */
        $log = Container::get(Log::class);
        $log->exception($e);

        /** @var Message $message */
        $message = Container::get(Message::class);

        /** @var Router $router */
        $router = Container::get(Router::class);

        // Exceções de domínio → mensagem amigável
        if ($e instanceof DomainException) {

            $message->warning($e->getMessage());

            // Se a exceção definir rota, respeita
            if (method_exists($e, 'redirectRoute') && $e->redirectRoute()) {
                $router->redirect($e->redirectRoute());
                exit;
            }

            // fallback padrão
            $router->back();
            exit;
        }


        printa("ERRO:");
        printa($e->getMessage());
        die;


        // Exceção técnica / inesperada
        $message->error('Erro interno do sistema');
        $router->back();
        exit;
    }
}
