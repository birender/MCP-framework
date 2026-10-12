<?php
require_once __DIR__ . '/OllamaClient.php';

class ErpAiAssistant
{
    private OllamaClient $ollama;
    private McpServer $mcpServer;

    public function __construct(
        OllamaClient $ollama,
        McpServer $mcpServer
    ) {
        $this->ollama = $ollama;
        $this->mcpServer = $mcpServer;
    }

    public function ask(string $question): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' =>
                    'You are an ERP assistant. Use the provided tools ' .
                    'to answer questions about ERP data. Never invent ' .
                    'database results. Call search_projects for project ' .
                    'questions and include_booked_by_name=true only when ' .
                    'the user asks who booked a project.'
            ],
            [
                'role' => 'user',
                'content' => $question
            ]
        ];

        $payload = [
            'model' => 'qwen2.5:3b',
            'messages' => $messages,
            'tools' => $this->mcpServer->getToolsForAI(),
            'stream' => false
        ];

        return $this->ollama->chatWithTools($payload);
    }
}
