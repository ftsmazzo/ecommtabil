<?php
$router->namespace("\App\Controllers\Api")->group("/api");

// 🔸 Autenticação de usuário humano (cliente ou admin)
$router->post("/login", "AuthController:login", "api.user.login");
$router->get("/logout", "AuthController:logout", "api.user.logout");
// 🔸 Autenticação de sistemas externos (via client_id / secret)
$router->post("/authenticate", "AuthController:authenticate", "api.auth");
$router->post("/refresh", "AuthController:refresh", "api.auth.refresh");
$router->get('/verify', 'AuthController:verify');


// 🔸 Rota protegida para teste
// $router->get("/profile", "UserController:profile", "api.profile");
// $router->get("/empresas", "EmpresaController:list", "api.empresa.list");

