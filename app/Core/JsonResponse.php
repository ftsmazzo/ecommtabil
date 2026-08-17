<?php

namespace App\Core;

class JsonResponse
{
    /**
     * Retorna uma resposta JSON estruturada
     */
    public static function send($data = null, int $code = 200, array $headers = []): void
    {
        if (ob_get_length()) {
            ob_end_clean();
        }

        header_remove();
        http_response_code($code);

        $defaultHeaders = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Powered-By' => 'AppCore',
            'Status' => self::message($code),
        ];

        foreach (array_merge($defaultHeaders, $headers) as $key => $value) {
            header("$key: $value");
        }

        $message = is_array($data) && isset($data['_message'])
            ? $data['_message']
            : self::message($code);

        if (is_array($data)) {
            unset($data['_message']);
        }

        $response = [
            'status'  => $code,
            'success' => $code >= 200 && $code < 300,
            'message' => $message,
            'data'    => $data,
        ];

        echo json_encode(
            $response,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK
        );

        exit;
    }

    // ------------------------------------------------------------------
    // Helpers gerais
    // ------------------------------------------------------------------

    public static function success(string $message, $data = [], int $code = 200): void
    {
        self::send(array_merge(['_message' => $message], (array)$data), $code);
    }

    public static function error(string $message, int $code = 400, array $extra = []): void
    {
        self::send(array_merge(['_message' => $message], $extra), $code);
    }

    // ------------------------------------------------------------------
    // Helpers semânticos (mais expressivos)
    // ------------------------------------------------------------------

    public static function created(string $message = 'Created', $data = []): void
    {
        self::success($message, $data, 201);
    }

    public static function updated(string $message = 'Updated', $data = []): void
    {
        self::success($message, $data, 200);
    }

    public static function deleted(string $message = 'Deleted'): void
    {
        self::success($message, null, 200);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Not Found'): void
    {
        self::error($message, 404);
    }

    public static function validationError(array $errors, string $message = 'Validation Error'): void
    {
        self::send(
            [
                '_message' => $message,
                'errors'   => $errors
            ],
            422
        );
    }

    public static function serverError(string $message = 'Internal Server Error', array $extra = []): void
    {
        self::error($message, 500, $extra);
    }

    // ------------------------------------------------------------------
    // Tabela de mensagens HTTP padrão
    // ------------------------------------------------------------------

    private static function message(int $code): string
    {
        static $status = [
            // 2xx Success
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            204 => 'No Content',

            // 3xx Redirect
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',

            // 4xx Client errors
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',

            // 5xx Server errors
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            503 => 'Service Unavailable',
        ];

        return $status[$code] ?? 'Unknown Status';
    }
}
