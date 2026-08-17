<?php

namespace App\Core;

abstract class ApiController extends Controller
{
    /** @var Jwt */
    protected Jwt $jwt;

    /** @var object|null Usuário autenticado pelo JWT */
    protected ?object $authUser = null;

    public function __construct()
    {
        parent::__construct();
        $this->jwt = new Jwt();
    }

    /**
     * Envia uma resposta JSON padronizada
     */
    protected function respond($data, int $status = 200): void
    {
        JsonResponse::send($data, $status);
    }

    /**
     * Valida o token JWT e autentica o usuário
     *
     * @param string|null $requiredType Ex: 'admin', 'user', 'api_client'
     */
    protected function requireAuth(?string $requiredType = null): void
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        // Token ausente
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            JsonResponse::unauthorized('Token ausente.');
        }

        // Extrai somente o token sem o prefixo Bearer
        $token = trim(str_replace('Bearer', '', $authHeader));

        // Token inválido / expirado
        if (!$this->jwt->validate($token)) {
            JsonResponse::unauthorized('Token inválido ou expirado.');
        }

        // Dados do usuário autenticado
        $this->authUser = $this->jwt->getData($token);

        // Se houver exigência de tipo, valida
        if ($requiredType && ($this->authUser->type ?? null) !== $requiredType) {
            JsonResponse::forbidden('Acesso negado.');
        }
    }
}