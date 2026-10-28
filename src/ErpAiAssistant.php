<?php
require_once __DIR__ . '/OllamaClient.php';

class ErpAiAssistant
{
    private OllamaClient $ollama;
    private McpServer $mcpServer;

    public function __construct(OllamaClient $ollama,McpServer $mcpServer) {
        $this->ollama = $ollama;
        $this->mcpServer = $mcpServer;
    }
   
    public function ask(string $question): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' =>
                    'You are an ERP assistant. Answer using actual ERP database results. ' .
                    'Never invent database results. Use the provided tools when needed. ' .
                    'For project questions, use search_projects. Set ' .
                    'include_booked_by_name=true only when the user asks who booked ' .
                    'the project. After receiving tool results, explain them naturally.'
            ],
            [
                'role' => 'user',
                'content' => $question
            ]
        ];

        $tools = $this->mcpServer->getToolsForAI();

        // Allow up to 5 rounds of tool execution.
        for ($round = 0; $round < 5; $round++) {
            $response = $this->ollama->chatWithTools([
                'model' => 'gemma4:31b',// Add Modal name
                'messages' => $messages,
                'tools' => $tools,
                'stream' => false
            ]);

            $message = $response['message'] ?? null;

            if (!is_array($message)) {
                throw new RuntimeException(
                    'Invalid response from Ollama.'
                );
            }

            $toolCalls = $message['tool_calls'] ?? [];

            // No more tool calls: return the final natural-language answer.
            if (empty($toolCalls)) {
                return [
                    'answer' => $message['content'] ?? '',
                    'tool_rounds' => $round
                ];
            }

            // Preserve the assistant's tool-call message for Ollama.
            $messages[] = $message;

            foreach ($toolCalls as $toolCall) {
                $function = $toolCall['function'] ?? [];
                $toolName = $function['name'] ?? '';
                $arguments = $function['arguments'] ?? [];

                if (!is_string($toolName) || !is_array($arguments)) {
                    throw new RuntimeException(
                        'Invalid tool call returned by Ollama.'
                    );
                }

                // Execute through the existing MCP JSON-RPC handler.
                $toolResponse = $this->mcpServer->handle([
                    'jsonrpc' => '2.0',
                    'id' => $toolCall['id'] ?? 'ai-tool-' . $round,
                    'method' => 'tools/call',
                    'params' => [
                        'name' => $toolName,
                        'arguments' => $arguments
                    ]
                ]);

                if (isset($toolResponse['error'])) {
                    $toolResult = [
                        'error' => $toolResponse['error']['message']
                            ?? 'Tool execution failed.'
                    ];
                } else {
                    $toolResult = $toolResponse['result']['content'][0]['text']
                        ?? '{"error":"Tool returned no result."}';
                }

                $messages[] = [
                    'role' => 'tool',
                    'content' => is_string($toolResult)
                        ? $toolResult
                        : json_encode(
                            $toolResult,
                            JSON_UNESCAPED_SLASHES
                            | JSON_UNESCAPED_UNICODE
                            | JSON_THROW_ON_ERROR
                        )
                ];
            }
        }

        throw new RuntimeException(
            'Maximum tool-call rounds reached without a final answer.'
        );
    }

}
