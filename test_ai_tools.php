
<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/McpServer.php';
require_once __DIR__ . '/src/OllamaClient.php';
require_once __DIR__ . '/src/ErpAiAssistant.php';

try {
    $pdo = getPdo();

    $mcpServer = new McpServer($pdo);
    $ollama = new OllamaClient();

    $assistant = new ErpAiAssistant($ollama, $mcpServer);

    echo "====================================\n";
    echo "       ERP AI ASSISTANT\n";
    echo "====================================\n";
    echo "Ask questions about your ERP data.\n";
    echo "Type 'bye' to exit.\n\n";

    while (true) {
        echo "You: ";

        $question = fgets(STDIN);

        // Exit if terminal input ends.
        if ($question === false) {
            echo "\nGoodbye!\n";
            break;
        }

        $question = trim($question);

        // Ignore empty input.
        if ($question === '') {
            continue;
        }

        // Exit when the user types bye.
        if (strcasecmp($question, 'bye') === 0) {
            echo "AI: Goodbye! Have a great day.\n";
            break;
        }

        try {
            echo "AI: Thinking...\n";

            $result = $assistant->ask($question);

            $answer = $result['answer'] ?? '';

            if ($answer !== '') {
                echo "AI: " . $answer . "\n\n";
            } else {
                echo "AI: No answer was returned.\n\n";
            }

        } catch (Throwable $e) {
            echo "AI Error: " . $e->getMessage() . "\n\n";
        }
    }

} catch (Throwable $e) {
    echo "Startup Error: " . $e->getMessage() . "\n";
}
