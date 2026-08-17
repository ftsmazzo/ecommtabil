<?php
$router->namespace("\App\Controllers\Servicos");

$router->group("/notificacoes");
    // NOTIFICAÇÕES WEBPUSH
    // $router->get("/webpush/generate", "WebpushController:generate", "webpush.generate");
    $router->post("/webpush/subscription-save", "WebpushController:save", "webpush.subscription.save");
    $router->post("/webpush/subscription-delete", "WebpushController:delete", "webpush.subscription.delete");
    $router->get("/webpush/enviar", "WebpushController:send");

$router->group("/cronjobs");
    // $router->get("/whatsapp/send", "CronjobController:sendWhatsapp", "cronjob.send.whatsapp");
    $router->get("/cartoes/vencer", "CronjobController:vencerCartoes", "cronjob.cartoes.vencer");
    $router->get("/creditos/expirar", "CronjobController:vencerCreditosExpirados", "cronjob.creditos.expirar");
    $router->get("/ipca/sincronizar", "CronjobController:ipcaSincronizar", "cronjob.ipca.sincronizar");
    $router->get("/selic/sincronizar", "CronjobController:selicSincronizar", "cronjob.selic.sincronizar");
    $router->get("/igpm/sincronizar", "CronjobController:igpmSincronizar", "cronjob.igpm.sincronizar");
