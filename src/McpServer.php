<?php
declare(strict_types=1);

final class McpServer
{
    private const PROTOCOL_VERSION = '2025-03-26';
    public function __construct(private readonly PDO $pdo) {}

    public function handle(array $request): array
    {
        $id = $request['id'] ?? null;
        $method = $request['method'] ?? '';

        // Notifications do not require a response.        
        if ($id === null) {
            if (is_string($method) && str_starts_with($method, 'notifications/')) {
                return [];
            }

            return $this->error(null, -32600, 'Invalid request');
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
                        'protocolVersion' => self::PROTOCOL_VERSION,
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
            case 'search_projects':
                $result = $this->searchProjects($arguments);
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
            ],                        
            [
                'name' => 'search_projects',
                'description' =>
                    'Search ERP projects or orders by project number, order number, or project name. Set include_booked_by_name to true only when the user asks who booked the project or requests the booker name.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'project_no' => [
                            'type' => 'string',
                            'description' => 'The project number.'
                        ],
                        'order_number' => [
                            'type' => 'string',
                            'description' => 'The order number.'
                        ],
                        'project_name' => [
                            'type' => 'string',
                            'description' => 'The project name or part of its name.'
                        ],
                        'include_booked_by_name' => [
                            'type' => 'boolean',
                            'description' => 'Set to true only when the user asks for the name of the person who booked the project. Otherwise false or omit.'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]

        ];/* End of Array */
    }/* End of Method */

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
                sso_disabled FROM core_user_details WHERE (user_first_name LIKE ? OR user_last_name LIKE ? OR user_full_name LIKE ? OR user_email LIKE ?) ORDER BY user_full_name LIMIT {$limit}";
        
        $stmt = $this->pdo->prepare($sql);

        $term = '%' . $search . '%';

        $stmt->execute([$term,$term,$term,$term]);

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'count' => count($users),
            'users' => $users
        ];    
    }
    
     
    private function searchProjects(array $arguments): array
    {
        $includeBookedByName = !empty($arguments['include_booked_by_name']);

        $limit = filter_var(
            $arguments['limit'] ?? 10,
            FILTER_VALIDATE_INT
        );

        if ($limit === false || $limit < 1 || $limit > 20) {
            throw new InvalidArgumentException(
                'Limit must be between 1 and 20.'
            );
        }

        $conditions = [];
        $params = [];

        $searchFields = [
            'project_no',
            'order_number',
            'project_name'
        ];

        foreach ($searchFields as $field) {
            if (!isset($arguments[$field]) || !is_string($arguments[$field])) {
                continue;
            }

            $value = trim($arguments[$field]);

            if ($value === '') {
                continue;
            }

            $placeholder = ':search_' . $field;

            $conditions[] = "p.{$field} LIKE {$placeholder}";
            $params[$placeholder] = '%' . $value . '%';
        }

        if (empty($conditions)) {
            throw new InvalidArgumentException(
                'Provide a project number, order number, or project name.'
            );
        }

        $sql = "
            SELECT
                p.project_no,
                p.order_number,
                p.order_type,
                p.order_stage,
                p.is_cancelled,
                p.store_location,
                p.booked_date,
                p.booked_by,
                p.booked_revenue_usd,
                p.booked_margin,
                p.project_name
        ";

        if ($includeBookedByName) {
            $sql .= ",
                u.user_full_name AS booked_by_name
            ";
        }

        $sql .= "
            FROM core_projects_details AS p
        ";

        if ($includeBookedByName) {
            $sql .= "
                LEFT JOIN core_user_details AS u
                    ON u.user_id = p.booked_by
            ";
        }

        $sql .= "
            WHERE (" . implode(' OR ', $conditions) . ")
            ORDER BY p.project_name
            LIMIT {$limit}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'count' => count($projects),
            'projects' => $projects
        ];
    }


    private function get_user_detail_by_id(array $arguments):array
    {
        
    }
    
    public function getToolsForAI(): array
    {
        $tools = [];

        foreach ($this->getTools() as $tool) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['inputSchema']
                ]
            ];
        }

        return $tools;
    }


    private function error(mixed $id,int $code,string $message): array 
    {
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
