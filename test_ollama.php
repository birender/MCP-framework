
<?php

require_once __DIR__ . '/src/OllamaClient.php';

try {
    $ollama = new OllamaClient();

    $answer = $ollama->chat(
        'Reply with exactly: PHP connected to Ollama'
    );

    echo $answer . PHP_EOL;

} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
