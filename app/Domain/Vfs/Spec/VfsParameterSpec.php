<?php

namespace App\Domain\Vfs\Spec;

/**
 * One parameter of a VFS operation, as declared in VfsOperationSpecRegistry.
 *
 * This is the single declaration every surface derives from: the direct tool
 * JSON schema (VfsToolSchemaFactory), Code Mode positional order (schema order
 * is what ScriptBridge binds positional arguments to), the OpenAPI export and
 * the generated reference docs.
 *
 * Aliases are accepted spellings, not extra schema properties. They are never
 * advertised in a model-visible schema (two keys for one value is exactly the
 * redundancy the spec exists to remove); handlers resolve them through
 * VfsToolInput. snake_case spellings of camelCase keys need no alias: Code Mode
 * converts them before dispatch.
 */
final class VfsParameterSpec
{
    /**
     * @param  'string'|'integer'|'boolean'|'array'|'object'  $type
     * @param  list<string|int>  $enum
     * @param  list<string>  $aliases
     * @param  'string'|list<VfsParameterSpec>|null  $items  Array item type: a scalar type name, or the properties of an object item
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $description,
        public readonly bool $required = false,
        public readonly array $enum = [],
        public readonly string|int|bool|null $default = null,
        public readonly array $aliases = [],
        public readonly string|array|null $items = null,
    ) {}

    /**
     * JSON-Schema-shaped description of this parameter (used by the OpenAPI
     * export and the reference docs; tool schemas go through Laravel's
     * JsonSchema builders in VfsToolSchemaFactory).
     *
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $schema = ['type' => $this->type, 'description' => $this->description];
        if ($this->enum !== []) {
            $schema['enum'] = $this->enum;
        }
        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }
        if ($this->type === 'array' && $this->items !== null) {
            $schema['items'] = is_string($this->items)
                ? ['type' => $this->items]
                : self::objectSchema($this->items);
        }
        if ($this->aliases !== []) {
            $schema['x-aliases'] = $this->aliases;
        }

        return $schema;
    }

    /**
     * @param  list<VfsParameterSpec>  $properties
     * @return array<string, mixed>
     */
    public static function objectSchema(array $properties): array
    {
        $schema = ['type' => 'object', 'properties' => []];
        $required = [];
        foreach ($properties as $property) {
            $schema['properties'][$property->name] = $property->toJsonSchema();
            if ($property->required) {
                $required[] = $property->name;
            }
        }
        if ($required !== []) {
            $schema['required'] = $required;
        }
        $schema['additionalProperties'] = false;

        return $schema;
    }
}
