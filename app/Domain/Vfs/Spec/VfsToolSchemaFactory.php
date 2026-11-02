<?php

namespace App\Domain\Vfs\Spec;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * Builds a tool's Laravel JSON schema from its VfsOperationSpec.
 *
 * Every `vfs_*` tool's schema() delegates here, so the model-visible schema,
 * the Code Mode parameter catalog (ToolRegistry serializes these schemas) and
 * the OpenAPI export cannot drift apart. Aliases are intentionally absent:
 * they are accepted by handlers (VfsToolInput) but never advertised.
 *
 * Descriptions get the default appended ("Default: x.") so the model sees it
 * without a separate field; enums are real JSON-schema enums.
 */
final class VfsToolSchemaFactory
{
    /**
     * @return array<string, Type>
     */
    public static function build(JsonSchema $schema, VfsOperationSpec $spec): array
    {
        $properties = [];
        foreach ($spec->parameters as $parameter) {
            $properties[$parameter->name] = self::type($schema, $parameter);
        }

        return $properties;
    }

    private static function type(JsonSchema $schema, VfsParameterSpec $parameter): Type
    {
        $type = match ($parameter->type) {
            'string' => $schema->string(),
            'integer' => $schema->integer(),
            'boolean' => $schema->boolean(),
            'object' => $schema->object(),
            default => $schema->array(),
        };

        if ($parameter->type === 'array' && $parameter->items !== null && method_exists($type, 'items')) {
            $type->items(is_string($parameter->items)
                ? self::scalar($schema, $parameter->items)
                : $schema->object(self::objectProperties($schema, $parameter->items))->withoutAdditionalProperties());
        }

        if ($parameter->enum !== []) {
            $type->enum($parameter->enum);
        }

        $type->description(self::description($parameter));

        if ($parameter->required) {
            $type->required();
        }

        return $type;
    }

    /**
     * @param  list<VfsParameterSpec>  $properties
     * @return array<string, Type>
     */
    private static function objectProperties(JsonSchema $schema, array $properties): array
    {
        $types = [];
        foreach ($properties as $property) {
            $types[$property->name] = self::type($schema, $property);
        }

        return $types;
    }

    private static function scalar(JsonSchema $schema, string $type): Type
    {
        return match ($type) {
            'integer' => $schema->integer(),
            'boolean' => $schema->boolean(),
            default => $schema->string(),
        };
    }

    public static function description(VfsParameterSpec $parameter): string
    {
        return $parameter->default === null
            ? $parameter->description
            : $parameter->description.' Default: '.self::scalarText($parameter->default).'.';
    }

    private static function scalarText(string|int|bool $value): string
    {
        return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
    }
}
