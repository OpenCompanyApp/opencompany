<?php

namespace App\Services\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

class McpSchemaTranslator
{
    /**
     * Translate an MCP inputSchema (JSON Schema) into a Laravel Tool schema array.
     *
     * @param  array  $inputSchema  MCP tool's inputSchema (JSON Schema with type, properties, required)
     * @param  JsonSchema  $factory  The Laravel JsonSchema factory
     * @return array<string, Type>
     */
    public static function translate(array $inputSchema, JsonSchema $factory): array
    {
        $properties = $inputSchema['properties'] ?? [];
        $required = $inputSchema['required'] ?? [];
        $result = [];

        foreach ($properties as $name => $propSchema) {
            $type = self::translateProperty($propSchema, $factory);

            if (in_array($name, $required)) {
                $type->required();
            }

            if (isset($propSchema['description'])) {
                $type->description($propSchema['description']);
            }

            $result[$name] = $type;
        }

        return $result;
    }

    private static function translateProperty(array $propSchema, JsonSchema $factory): Type
    {
        $type = $propSchema['type'] ?? 'string';

        return match ($type) {
            'string' => self::buildString($propSchema, $factory),
            'integer' => $factory->integer(),
            'number' => $factory->number(),
            'boolean' => $factory->boolean(),
            'array' => self::buildArray($propSchema, $factory),
            'object' => self::buildObject($propSchema, $factory),
            default => $factory->string(),
        };
    }

    private static function buildString(array $schema, JsonSchema $factory): Type
    {
        $s = $factory->string();

        if (isset($schema['enum'])) {
            $s->enum($schema['enum']);
        }

        return $s;
    }

    private static function buildArray(array $schema, JsonSchema $factory): Type
    {
        $a = $factory->array();

        if (isset($schema['items'])) {
            $a->items(self::translateProperty($schema['items'], $factory));
        } else {
            // Strict providers (e.g. Codex) require items on every array
            $a->items($factory->string());
        }

        return $a;
    }

    private static function buildObject(array $schema, JsonSchema $factory): Type
    {
        $properties = [];
        $required = $schema['required'] ?? [];

        foreach ($schema['properties'] ?? [] as $name => $propSchema) {
            $prop = self::translateProperty($propSchema, $factory);

            if (in_array($name, $required)) {
                $prop->required();
            }

            if (isset($propSchema['description'])) {
                $prop->description($propSchema['description']);
            }

            $properties[$name] = $prop;
        }

        return $factory->object($properties);
    }
}
