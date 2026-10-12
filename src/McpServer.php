<?php
declare(strict_types=1);

final class McpServer
{
    public function __construct(private readonly PDO $pdo) {}

    public function handle(array $request): array
    {
        $id = $request['id'] ?? null;
        $method = $request['method'] ?? '';

        // Notifications do not require a response.
        if ($id === null) {
            return [];
        }

        if (!is_string($method)) {
            return $this->error($id, -32600, 'Invalid request');
        }

        switch ($method) {
            case 'initialize':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'protocolVersion' => '2025-03-26',
                        'capabilities' => [
                            'tools' => ['listChanged' => false]
                        ],
                        'serverInfo' => [
                            'name' => 'erp-ai-php',
                            'version' => '1.0.0'
                        ]
                    ]
                ];

            case 'ping':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => new stdClass()
                ];

            case 'tools/list':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'tools' => $this->getTools()
                    ]
                ];

            case 'tools/call':
                return $this->callTool($id, $request['params'] ?? []);

            default:
                return $this->error($id,-32601,'Method not found');
        }
    }

   
    private function callTool(mixed $id, array $params): array
    {
        $toolName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        if (!is_string($toolName) || !is_array($arguments)) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32602,
                    'message' => 'Invalid tool parameters'
                ]
            ];
        }

        switch ($toolName) {
            case 'get_database_info':
                $result = $this->getDatabaseInfo();
                break;
            case 'search_users':
                $result = $this->searchUsers($arguments);
                break;    
            case 'get_user_by_id':
                $result = $this->get_user_detail_by_id($arguments);
                break;
            default:
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32602,
                        'message' => 'Unknown tool: ' . $toolName
                    ]
                ];
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode(
                            $result,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        )
                    ]
                ]
            ]
        ];
    }

    
    private function getDatabaseInfo(): array
    {
        $stmt = $this->pdo->query('SELECT VERSION() AS version');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'database' => 'MySQL',
            'version' => $row['version'] ?? 'unknown'
        ];
    }


    private function getTools(): array
    {
        return [
            [
                'name' => 'get_database_info',
                'description' =>
                    'Return the MySQL server version for the connected ERP database.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'additionalProperties' => false
                ]
            ],
            [
                'name' => 'get_user_by_id',
                'description' => 'Retrieve basic ERP user details by user ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => [
                            'type' => 'integer',
                            'description' => 'The numeric ID of the ERP user.'
                        ]
                    ],
                    'required' => ['user_id'],
                    'additionalProperties' => false
                ]  
            ],
            [
                'name' => 'search_users',
                'description' => 'Search ERP users by name, email address, or employee number. Use this tool when the user does not know the internal user ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => [
                            'type' => 'string',
                            'description' => 'A user name, email address, or employee number to search for.'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results, between 1 and 20.'
                        ]
                    ],
                    'required' => ['search'],
                    'additionalProperties' => false
                ]  
            ]

        ];
    }

    private function searchUsers(array $arguments):array
    {
        $search = trim($arguments['search'] ?? '');
        $limit = filter_var(
            $arguments['limit'] ?? 10,
            FILTER_VALIDATE_INT
        );

        if ($search === '') {
            throw new InvalidArgumentException(
                'Search term is required.'
            );
        }

        if ($limit === false || $limit < 1 || $limit > 20) {
            throw new InvalidArgumentException(
                'Limit must be between 1 and 20.'
            );
        }

        $sql = "
            SELECT
                user_id,
                user_first_name,
                user_last_name,
                user_full_name,
                user_email,
                user_title,
                user_is_active,
                sso_last_login,
                last_activity,
                sso_disabled
            FROM user_details
            WHERE
                user_first_name LIKE :search1
                OR user_last_name LIKE :search2
                OR user_full_name LIKE :search3
                OR user_email LIKE :search4
            ORDER BY user_full_name
            LIMIT {$limit}
        ";

        $stmt = $this->pdo->prepare($sql);
        $term = '%' . $search . '%';

        $stmt->execute([
            'search1' => $term,
            'search2' => $term,
            'search3' => $term,
            'search4' => $term,
            'search5' => $term
        ]);

        return [
            'count' => 0,
            'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];     
    }

    private function get_user_detail_by_id(array $arguments):array
    {
        
    }

    private function error(
        mixed $id,
        int $code,
        string $message
    ): array {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
    }
}
