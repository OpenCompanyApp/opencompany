<?php

namespace Tests\Feature;

use App\Services\Mcp\McpSchemaTranslator;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Tests\TestCase;

class McpSchemaTranslatorTest extends TestCase
{
    private JsonSchemaTypeFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new JsonSchemaTypeFactory;
    }

    public function test_translates_string_property(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query',
                ],
            ],
            'required' => ['query'],
        ];

        $result = McpSchemaTranslator::translate($schema, $this->factory);

        $this->assertArrayHasKey('query', $result);
    }

    public function test_translates_required_fields(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'required_field' => ['type' => 'string'],
                'optional_field' => ['type' => 'string'],
            ],
            'required' => ['required_field'],
        ];

        $result = McpSchemaTranslator::translate($schema, $this->factory);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('required_field', $result);
        $this->assertArrayHasKey('optional_field', $result);
    }

    public function test_translates_string_with_enum(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'format' => [
                    'type' => 'string',
                    'enum' => ['json', 'xml', 'csv'],
                    'description' => 'Output format',
                ],
            ],
        ];

        $result = McpSchemaTranslator::translate($schema, $this->factory);

        $this->assertArrayHasKey('format', $result);
    }

    public function test_translates_integer_property(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum results',
                ],
            ],
        ];

        $result = McpSchemaTranslator::translate($schema, $this->factory);

        $this->assertArrayHasKey('limit', $result);
    }

    public function test_translates_number_property(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'temperature' => [
                    'type' => 'number',
                ],
            ],
        ];

        $result = McpSchemaTranslator::translate($schema, $this->factory);

        $this->assertArrayHasKey('temperature', $result);
    }

    public function test_translates_boolean_property(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'verbose' => [
                    'type' => 'boolean',
                    'description' => 'Enable verbose output',
                ],
            ],
        ];

        $result = McpSchemaTranslator::translate($schema, $this->factory);

        $this->assertArrayHasKey('verbose', $result);
    }

    public function test_translates_array_property(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Tags to filter by',
                ],
            ],
        ];

        $result = McpSchemaTranslator::translate($schema, $this->factory);

        $this->assertArrayHasKey('tags', $result);
    }

    public function test_translates_nested_object(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'config' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'value' => ['type' => 'string'],
                    ],
                    'required' => ['key'],
                ],
            ],
        ];

        $result = McpSchemaTranslator::translate($schema, $this->factory);

        $this->assertArrayHasKey('config', $result);
    }

    public function test_unknown_type_falls_back_to_string(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'unknown_type',
                ],
            ],
        ];

        $result = McpSchemaTranslator::translate($schema, $this->factory);

        $this->assertArrayHasKey('data', $result);
    }

    public function test_handles_empty_schema(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        $result = McpSchemaTranslator::translate($schema, $this->factory);

        $this->assertEmpty($result);
    }

    public function test_handles_missing_properties_key(): void
    {
        $schema = ['type' => 'object'];

        $result = McpSchemaTranslator::translate($schema, $this->factory);

        $this->assertEmpty($result);
    }

    public function test_translates_complex_mcp_schema(): void
    {
        // Simulates a real MCP tool like brave_web_search
        $schema = [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query (max 400 chars)',
                ],
                'count' => [
                    'type' => 'integer',
                    'description' => 'Number of results (1-20)',
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Pagination offset (max 9)',
                ],
                'freshness' => [
                    'type' => 'string',
                    'enum' => ['pd', 'pw', 'pm', 'py'],
                    'description' => 'Time filter',
                ],
            ],
            'required' => ['query'],
        ];

        $result = McpSchemaTranslator::translate($schema, $this->factory);

        $this->assertCount(4, $result);
        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('offset', $result);
        $this->assertArrayHasKey('freshness', $result);
    }
}
