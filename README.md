# PHP MCP Framework --- ERP AI Assistant

A lightweight PHP prototype that connects an ERP MySQL database to an AI
assistant using tool calling and MCP concepts.

The project demonstrates how an AI model can interpret a
natural-language question, select a PHP tool, query ERP data through
PDO, and turn the returned data into a readable answer.

> **Project status:** Prototype / learning project. Review security,
> error handling, and MCP transport compatibility before using it with
> production data.

## Features

-   **PHP-first implementation** --- no Node.js runtime required for the
    current prototype.
-   **MySQL access with PDO** --- parameterized queries for ERP data
    retrieval.
-   **AI tool calling** --- the model can select a tool and provide
    structured arguments.
-   **Ollama integration** --- designed for an Ollama-compatible chat
    API; the current setup uses Ollama Cloud and `gemma4:31b`.
-   **Natural-language questions** --- query projects, users, and
    database information.
-   **Interactive terminal chat** --- ask multiple questions in one
    session and type `bye` to exit.
-   **Extensible tool definitions** --- add more tools as your ERP use
    cases grow.

## Architecture

``` text
User question
     |
     v
ErpAiAssistant.php
     |
     v
Ollama Cloud / chat API
     |
     | tool call + arguments
     v
McpServer.php
     |
     v
PDO -> MySQL ERP database
     |
     | tool result
     v
ErpAiAssistant.php
     |
     v
Ollama generates a natural-language answer
```

The AI model chooses from the tools exposed to it. PHP validates and
executes the selected tool; the model should not receive database
credentials or unrestricted SQL execution.

## Technology Stack

-   PHP 8.2+
-   MySQL
-   PDO
-   PHP cURL extension
-   Ollama chat API (cloud or local, depending on configuration)

## Project Structure

``` text
MCP-framework/
├── config/
│   └── database.php        # PDO database connection
├── public/
│   └── mcp.php             # JSON-RPC/MCP-style endpoint
├── src/
│   ├── McpServer.php       # Tool definitions and execution
│   ├── OllamaClient.php    # HTTP client for Ollama chat API
│   └── ErpAiAssistant.php  # AI conversation and tool-calling loop
├── test_ai_tools.php       # Interactive CLI ERP assistant
├── test_ollama.php         # Basic Ollama connectivity test
└── README.md
```

File names or responsibilities may change as the project evolves.

## Requirements

-   PHP 8.2 or newer recommended
-   MySQL database with the ERP tables used by configured tools
-   PHP extensions: `pdo_mysql` and `curl`
-   An Ollama Cloud API key when using `https://ollama.com/api/chat`, or
    a reachable local Ollama server when using a local endpoint

Check PHP and extensions:

``` powershell
php -v
php -m
```

Confirm `curl` and `pdo_mysql` appear in the module list.

## Setup

### 1. Clone the repository

``` powershell
git clone https://github.com/birender/MCP-framework.git
cd MCP-framework
```

### 2. Configure the database

Edit `config/database.php` for your MySQL connection.

Keep credentials out of source control. Prefer environment variables or
a local, untracked configuration file for passwords and connection
details.

### 3. Configure the Ollama API

Set the API key in the same PowerShell session used to run PHP:

``` powershell
$env:OLLAMA_API_KEY = "YOUR_OLLAMA_API_KEY"
```

Do not commit the key or include it in logs.

For Ollama Cloud, configure the client endpoint as:

``` text
https://ollama.com/api/chat
```

The model name is configured in `src/ErpAiAssistant.php` (currently
`gemma4:31b`). Ensure it is available for your Ollama account. For a
local model, configure the endpoint and model accordingly.

### 4. Check PHP syntax

Run from the repository root:

``` powershell
php -l src/McpServer.php
php -l src/OllamaClient.php
php -l src/ErpAiAssistant.php
php -l test_ai_tools.php
```

## Run the CLI Assistant

Start the interactive assistant:

``` powershell
php test_ai_tools.php
```

Example session:

``` text
====================================
       ERP AI ASSISTANT
====================================
Ask questions about your ERP data.
Type 'bye' to exit.

You: I am looking database info
AI: Your database is running MySQL version 8.4.6.

You: give info P30014
AI: Project P30014 is titled "Jackets - China Program_28624"...

You: bye
AI: Goodbye! Have a great day.
```

The project details above are examples from development testing. Actual
output depends on the configured database.

If your CLI script supports a single question as a command-line
argument:

``` powershell
php test_ai_tools.php "give info P30014"
```

## Available Tools

  -----------------------------------------------------------------------
  Tool                                Purpose
  ----------------------------------- -----------------------------------
  `get_database_info`                 Return the MySQL server version

  `search_users`                      Search users by name, email, or
                                      employee number

  `get_user_by_id`                    Retrieve user details by numeric
                                      ID; implementation should be
                                      completed and verified before
                                      relying on it

  `search_projects`                   Search projects/orders by project
                                      number, order number, or project
                                      name; optionally include the
                                      booker's name
  -----------------------------------------------------------------------

Tool availability and arguments are defined in `McpServer.php`. Keep
each tool schema synchronized with its PHP implementation.

## How Tool Calling Works

1.  The user asks a question in natural language.
2.  `ErpAiAssistant` sends the conversation and tool schemas to the
    configured model.
3.  The model returns a tool call with a name and structured arguments.
4.  PHP dispatches the call through `McpServer`.
5.  The tool queries the database using PDO prepared statements.
6.  The result is returned to the model.
7.  The model generates a natural-language answer.

The model chooses a tool; it does not authorize access. Enforce
permissions and validate every argument in PHP.

## Adding a Tool

1.  Add a definition to `McpServer::getTools()` with a name,
    description, and JSON input schema.
2.  Expose it to the model through `getToolsForAI()`.
3.  Add a matching case to the tool dispatcher.
4.  Implement a PHP method that validates arguments and performs the
    operation.
5.  Use prepared statements for SQL values and enforce result limits.
6.  Test valid and invalid arguments, empty results, and database
    errors.

Keep the schema and implementation synchronized. Never turn
model-generated text into raw SQL.

## Security Notes

This repository is a prototype and should not be connected to a
production ERP or exposed publicly without additional hardening.

Before production use:

-   Protect HTTP endpoints with authentication and authorization.
-   Restrict each tool to the minimum data and operations needed.
-   Use a least-privilege database account; use read-only access for
    lookup tools where possible.
-   Validate tool arguments on the server, even when a schema is
    supplied to the model.
-   Keep API keys and database credentials out of Git and logs.
-   Avoid logging prompts or results containing sensitive information.
-   Add rate limits, request-size limits, timeouts, and safe error
    handling.
-   Review CORS and network exposure for HTTP endpoints.
-   Test SQL against your actual schema.
-   Treat model output as untrusted; never use it as authorization or
    execute arbitrary SQL.

The `public/mcp.php` endpoint is a custom JSON-RPC/MCP-style
implementation. Verify MCP lifecycle, transport, and protocol
compatibility with your intended MCP client before describing it as a
fully compliant MCP server.

## Troubleshooting

### `Ollama returned HTTP 401`

-   Check that `OLLAMA_API_KEY` is set in the same terminal session.
-   Confirm the key is valid and the endpoint is
    `https://ollama.com/api/chat`.
-   Ensure the client sends `Authorization: Bearer <key>`.
-   Never share the actual API key in diagnostic output.

### `Ollama returned HTTP 400`

-   Inspect the outgoing JSON payload and the model's expected tool-call
    message format.
-   Check that assistant tool-call messages and tool-result messages are
    serialized correctly.
-   Redact sensitive ERP data before sharing logs.

### PHP cannot connect to MySQL

-   Verify host, port, database name, username, and password.
-   Confirm MySQL is running and `pdo_mysql` is enabled.

### Tool returns no records

-   Check that table and column names match your ERP schema.
-   Confirm tool input names match the PHP method's expected arguments.
-   Test whether the search should be exact or partial.

## Roadmap

Potential next improvements:

-   Complete and test all declared tools.
-   Add active/inactive user filters and flexible search criteria.
-   Add automated tests for schemas, argument validation, and SQL
    results.
-   Move configuration to environment variables and provide a safe
    `.env.example`.
-   Add structured logging and clearer error responses.
-   Add authentication and authorization to the HTTP endpoint.
-   Verify full MCP protocol and transport compatibility with target
    clients.
-   Add tools for ERP modules such as accounts, vendors, sales, and
    purchase orders.

## Contributing

Issues and pull requests are welcome. When proposing a new tool, include
its purpose, input schema, validation rules, and tests.

## License

No license has been specified yet. Until a license is added to the
repository, do not assume others have permission to reuse, distribute,
or modify this code.
