<?php

namespace App\Controllers\Api;

use App\Core\Response;
use App\Core\Jwt;
use App\Core\Data;
use App\Models\User;
use App\Models\Usuario;
use App\Models\ApiClient;

class AuthController
{
    private Jwt $jwt;

    public function __construct()
    {
        $this->jwt = new Jwt();
    }

    /**
     * POST /api/auth/login
     * Login de usuário (cliente ou admin)
     */
    public function login()
    {
        $data = (new Data())->fromJson();

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $type = strtolower($data['type'] ?? 'cliente'); // cliente ou "admin"

        if (!$email || !$password) {
            return Response::error(
                'Campos obrigatórios ausentes.',
                422,
                ['errors' => [
                    'email' => 'Obrigatório',
                    'password' => 'Obrigatório'
                ]]
            );
        }

        $model = $type === 'admin' ? Usuario::class : User::class;
        $user = $model::where('email', "=", $email)->first();

        if (!$user || !password_verify($password, $user->password)) {
            return Response::error('Credenciais inválidas.', 401);
        }

        $token = $this->jwt->generate([
            'sub' => $user->id,
            'type' => $type,
            'email' => $user->email
        ]);

        return Response::success('Login realizado com sucesso.', [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $type
            ]
        ]);
    }

    /**
     * POST /api/auth/client
     * Autenticação de outro sistema (API Client)
     */
    public function authenticate()
    {
        $data = (new Data())->fromJson();

        $client_id = $data['client_id'] ?? null;
        $client_secret = $data['client_secret'] ?? null;

        if (!$client_id || !$client_secret) {
            return Response::error(
                'Campos obrigatórios ausentes.',
                422,
                ['errors' => [
                    'client_id' => 'Obrigatório',
                    'client_secret' => 'Obrigatório'
                ]]
            );
        }

        $client = ApiClient::where('client_id', "=", $client_id)
            ->where('client_secret', "=", $client_secret)
            ->where('active', "=", 1)
            ->first();

        if (!$client) {
            return Response::error('Credenciais do cliente inválidas.', 401);
        }

        $token = $this->jwt->generate([
            'sub' => $client->id,
            'type' => 'api_client',
            'scopes' => $client->scopes
        ]);

        $newRefresh = bin2hex(random_bytes(40));

        ApiClient::where('id', "=", $client->id)->update([
            'refresh_token' => $newRefresh,
            'refresh_expires' => gmdate('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        return Response::success(
            'Cliente autenticado com sucesso.',
            [
                'token' => $token,
                'refresh_token' => $newRefresh,
                'expires_in' => $this->jwt->getExpires()
                // 'client' => [
                //     'id' => $client->id,
                //     'name' => $client->name,
                //     'scopes' => $client->scopes
                // ]
            ]
        );

    }

    /**
     * GET /api/auth/verify
     * Verifica token JWT
     */
    public function verify(array $headers)
    {
        $authHeader = $headers['Authorization'] ?? '';

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return Response::error('Token ausente ou inválido.', 401);
        }

        $token = trim(str_replace('Bearer', '', $authHeader));

        if (!$this->jwt->validate($token)) {
            return Response::error('Token inválido ou expirado.', 401);
        }

        $data = $this->jwt->getData($token);

        return Response::success('Token válido.', $data);
    }


    public function refresh()
    {
        $data = (new Data())->fromJson();
        $refresh = $data['refresh_token'] ?? null;

        if (!$refresh) {
            return Response::error('Refresh token ausente.', 422);
        }

        // Verifica refresh token no banco
        $client = ApiClient::where('refresh_token', "=", $refresh)
            ->where('active', "=", 1)
            ->first();

        if (!$client || strtotime($client->refresh_expires) < time()) {
            return Response::error('Refresh token inválido ou expirado.', 401);
        }

        // Gera novo access token
        $accessToken = $this->jwt->generate(
            [
                'sub' => $client->id,
                'type' => 'api_client',
                'scopes' => $client->scopes
            ]
        );

        return Response::success(
            'Token renovado com sucesso.', [
                'token' => $accessToken,
                'expires_in' => $this->jwt->getExpires()
            ]
        );
    }

    /**
     * POST /api/auth/logout
     * Logout simbólico (client-side)
     */
    public function logout()
    {
        return Response::success('Logout efetuado com sucesso (client-side).');
    }
}
