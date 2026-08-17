<?php
declare(strict_types=1);

namespace App\Lib;

use OpenAI\Client;
use OpenAI\Exceptions\RateLimitException;

class ChatGPT
{
    private Client $client;
    private string $model;

    public function __construct(?array $conf = null)
    {
        $conf = $conf ?? \App\Core\Config::get('openai');

        $apiKey = (string)($conf['openai_key'] ?? '');
        if ($apiKey === '') {
            throw new \RuntimeException('Config openai_key não definida.');
        }

        $this->model = (string)($conf['model'] ?? 'gpt-4.1-mini');
        $this->client = \OpenAI::client($apiKey);
    }

    /**
     * @return array{ok:bool,text?:string,id?:string,error?:string}
     */
    public function send(
        string $systemPrompt,
        string $prompt,
        string $text = '',
        ?string $previousResponseId = null,
        array $opts = []
    ): array {

        $maxRetries = (int)($opts['retries'] ?? 5);
        $baseDelayMs = (int)($opts['base_delay_ms'] ?? 300);

        // monta a mensagem final
        $message = trim(
            $systemPrompt .
            "\n\n" .
            $prompt .
            "\n\n" .
            $text
        );

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {

            try {

                $payload = [
                    'model' => $opts['model'] ?? $this->model,
                    'input' => $message,
                ];

                if (!empty($previousResponseId)) {
                    $payload['previous_response_id'] = $previousResponseId;
                }

                $response = $this->client->responses()->create($payload);

                return [
                    'ok' => true,
                    'id' => $response->id ?? null,
                    'text' => trim((string)($response->outputText ?? '')),
                ];

            } catch (RateLimitException $e) {

                if ($attempt === $maxRetries) {
                    return [
                        'ok' => false,
                        'error' => 'Rate limit excedido.'
                    ];
                }

                $jitter = random_int(0, 200);
                $sleepMs = (int)($baseDelayMs * (2 ** $attempt) + $jitter);
                usleep($sleepMs * 1000);

            } catch (\Throwable $e) {

                return [
                    'ok' => false,
                    'error' => $e->getMessage()
                ];

            }
        }

        return [
            'ok' => false,
            'error' => 'Falha desconhecida'
        ];
    }
}