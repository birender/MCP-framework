
<?php

class OllamaClient
{
    private string $endpoint;
    private string $model;

    public function __construct(
        string $endpoint = 'http://localhost:11434/api/chat',
        string $model = 'qwen2.5:3b'
    ) {
        $this->endpoint = $endpoint;
        $this->model = $model;
    }

    public function chat(string $message): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $message,
                ],
            ],
            'stream' => false,
        ];

        $ch = curl_init($this->endpoint);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new RuntimeException(
                'Ollama connection failed: ' . $error
            );
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                'Ollama returned HTTP ' . $statusCode
                . ': ' . $response
            );
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            throw new RuntimeException(
                'Invalid JSON response from Ollama.'
            );
        }

        if (!isset($data['message']['content'])) {
            throw new RuntimeException(
                'Ollama response did not contain message content.'
            );
        }

        return $data['message']['content'];
    }

    
    public function chatWithTools(array $payload): array
    {
        $ch = curl_init($this->endpoint);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(
                $payload,
                JSON_THROW_ON_ERROR
            ),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new RuntimeException(
                'Ollama connection failed: ' . $error
            );
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                'Ollama returned HTTP ' . $statusCode . ': ' . $response
            );
        }

        $data = json_decode(
            $response,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($data) || !isset($data['message'])) {
            throw new RuntimeException(
                'Invalid chat response from Ollama.'
            );
        }

        return $data;
    }

}
