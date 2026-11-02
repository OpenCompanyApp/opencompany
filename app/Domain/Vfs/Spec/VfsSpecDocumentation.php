<?php

namespace App\Domain\Vfs\Spec;

/**
 * Renders every derived artifact of the VFS operation spec.
 *
 * Outputs (all deterministic: no timestamps, ids or environment data, so the
 * drift test can compare bytes):
 * - codeCatalog(): `/tools/vfs/code.json` (Code Mode signatures and aliases);
 * - openApi(): `/tools/vfs/openapi.json` and `vfs:spec --format=openapi`, an
 *   OpenAPI 3.1 description of the operations for a future CLI, MCP server or
 *   public API. There is no HTTP transport yet; `x-transport` says so;
 * - referenceMarkdown(): the generated reference section embedded in
 *   `docs/tools/vfs.md` and `resources/code-docs/vfs.md` between
 *   GENERATED_BEGIN / GENERATED_END markers (`vfs:spec --write-docs`).
 */
final class VfsSpecDocumentation
{
    public const GENERATED_BEGIN = '<!-- BEGIN GENERATED: php artisan vfs:spec --write-docs -->';

    public const GENERATED_END = '<!-- END GENERATED: vfs:spec -->';

    /** Repo-relative files that embed a generated reference section, => audience. */
    public const DOC_FILES = [
        'docs/tools/vfs.md' => 'tools',
        'resources/code-docs/vfs.md' => 'code',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function codeCatalog(): array
    {
        $functions = [];
        foreach (VfsOperationSpecRegistry::all() as $spec) {
            $functions[] = [
                'name' => 'app.vfs.'.$spec->rubyName,
                'signature' => $spec->rubySignature(),
                'tool' => $spec->toolSlug,
                'effect' => $spec->effect,
                'summary' => $spec->summary,
            ];
        }

        $aliases = [];
        foreach (VfsOperationSpecRegistry::RUBY_ALIASES as $alias => $target) {
            $aliases['app.'.$alias] = [
                'target' => 'app.'.$target,
                'defaults' => VfsOperationSpecRegistry::RUBY_ALIAS_DEFAULTS[$alias] ?? (object) [],
            ];
        }

        return [
            'namespace' => 'app.vfs',
            'runtime' => 'mruby Code Mode (code_exec)',
            'calling_convention' => 'Keyword arguments; snake_case keys map to the tool schema (max_matches: -> maxMatches). Positional arguments bind in signature order.',
            'return_envelope' => 'Hash {ok: true, result: {...}} (shell: {ok, exit_code, stdout, cwd, ...}). ok: false raises a typed failure whose type is the VFS error code; approval_required stops the execution as approval-pending.',
            'functions' => $functions,
            'aliases' => $aliases,
            'error_codes' => array_keys(VfsOperationSpecRegistry::ERROR_CODES),
            'permission_model' => 'Each function runs under its tool permission: reads under vfs_exec, edit/patch under vfs_patch, write/mkdir/cp/mv/rm under vfs_write, plus per-path domain permissions.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function openApi(): array
    {
        $paths = [];
        foreach (VfsOperationSpecRegistry::all() as $spec) {
            $paths['/vfs/'.$spec->cliVerb] = [
                'post' => [
                    'operationId' => $spec->toolSlug,
                    'summary' => $spec->summary,
                    'description' => $spec->description,
                    'tags' => [$spec->direct ? 'direct' : 'helper'],
                    'x-ruby-name' => 'app.vfs.'.$spec->rubyName,
                    'x-cli-verb' => 'oc vfs '.$spec->cliVerb,
                    'x-effect' => $spec->effect,
                    'x-action-class' => $spec->actionClass,
                    'x-permission' => $spec->permission,
                    'x-composed-permissions' => $spec->composedPermissions,
                    'x-error-codes' => $spec->errorCodes,
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => VfsParameterSpec::objectSchema($spec->parameters)]],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'VFS envelope. Failures are ok:false with error.code; approval-gated writes return the approval outcome.',
                            'content' => ['application/json' => ['schema' => [
                                'oneOf' => [
                                    $spec->name === 'exec' ? self::execSuccessSchema($spec) : self::successSchema($spec),
                                    ['$ref' => '#/components/schemas/Error'],
                                ],
                            ]]],
                        ],
                    ],
                ],
            ];
        }

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'OpenCompany VFS operations',
                'version' => '1',
                'description' => 'Generated from VfsOperationSpecRegistry. Describes the vfs_* tool contract for future CLI, MCP and API transports.',
            ],
            'x-transport' => 'No HTTP route serves these operations yet; today they run as agent tools and app.vfs.* in Code Mode.',
            'paths' => $paths,
            'components' => [
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'required' => ['ok', 'error'],
                        'properties' => [
                            'ok' => ['const' => false],
                            'error' => [
                                'type' => 'object',
                                'required' => ['code', 'message', 'context'],
                                'properties' => [
                                    'code' => ['type' => 'string', 'enum' => array_keys(VfsOperationSpecRegistry::ERROR_CODES)],
                                    'message' => ['type' => 'string'],
                                    'context' => ['type' => 'object'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generated reference section (without markers).
     *
     * @param  'tools'|'code'  $audience
     */
    public static function referenceMarkdown(string $audience): string
    {
        $lines = [];
        $lines[] = '## Reference';
        $lines[] = '';
        $lines[] = 'Generated from `app/Domain/Vfs/Spec/VfsOperationSpecRegistry.php`; edit the spec, then run `php artisan vfs:spec --write-docs`.';
        $lines[] = '';

        if ($audience === 'code') {
            $lines[] = '| Ruby | Tool | Effect | Summary |';
            $lines[] = '|---|---|---|---|';
            foreach (VfsOperationSpecRegistry::all() as $spec) {
                $lines[] = '| `app.vfs.'.$spec->rubySignature().'` | `'.$spec->toolSlug.'` | '.$spec->effect.' | '.self::cell($spec->summary).' |';
            }
            $lines[] = '';
            $lines[] = 'Aliases:';
            $lines[] = '';
            foreach (VfsOperationSpecRegistry::RUBY_ALIASES as $alias => $target) {
                $defaults = VfsOperationSpecRegistry::RUBY_ALIAS_DEFAULTS[$alias] ?? [];
                $lines[] = '- `app.'.$alias.'` = `app.'.$target.'`'.($defaults === [] ? '' : ' with '.self::defaultsText($defaults));
            }
            $lines[] = '';
            $lines[] = self::errorTable();

            return rtrim(implode("\n", $lines))."\n";
        }

        $lines[] = '| Tool | Model-visible | Ruby | CLI verb | Effect | Action class | Permission |';
        $lines[] = '|---|---|---|---|---|---|---|';
        foreach (VfsOperationSpecRegistry::all() as $spec) {
            $lines[] = '| `'.$spec->toolSlug.'` | '.($spec->direct ? 'yes' : 'Code Mode only').' | `app.vfs.'.$spec->rubyName.'` | `'.$spec->cliVerb.'` | '.$spec->effect.' | '.$spec->actionClass.' | `'.$spec->permission.'` |';
        }
        $lines[] = '';

        foreach (VfsOperationSpecRegistry::all() as $spec) {
            $lines[] = '### `'.$spec->toolSlug.'`';
            $lines[] = '';
            $lines[] = $spec->description;
            $lines[] = '';
            $lines[] = '| Parameter | Type | Required | Default | Description |';
            $lines[] = '|---|---|---|---|---|';
            foreach ($spec->parameters as $parameter) {
                $type = $parameter->type.($parameter->enum !== [] ? ' ('.implode('\|', $parameter->enum).')' : '');
                $description = $parameter->description.($parameter->aliases !== [] ? ' Aliases: '.implode(', ', $parameter->aliases).'.' : '');
                if (is_array($parameter->items)) {
                    $description .= ' Items: '.implode(', ', array_map(fn (VfsParameterSpec $item): string => $item->name.($item->required ? '' : '?'), $parameter->items)).'.';
                }
                $lines[] = '| `'.$parameter->name.'` | '.$type.' | '.($parameter->required ? 'yes' : 'no').' | '.self::defaultText($parameter).' | '.self::cell($description).' |';
            }
            $lines[] = '';
            $lines[] = 'Result: '.implode(', ', array_map(fn (string $key, string $meaning): string => '`'.$key.'` ('.$meaning.')', array_keys($spec->resultFields), $spec->resultFields)).'.';
            $lines[] = '';
            $lines[] = 'Permissions: '.implode('; ', array_map(fn (string $scope, array $tools): string => $scope.': '.implode(' + ', array_map(fn (string $tool): string => '`'.$tool.'`', $tools)), array_keys($spec->composedPermissions), $spec->composedPermissions)).'.';
            $lines[] = '';
            $lines[] = 'Errors: '.implode(', ', array_map(fn (string $code): string => '`'.$code.'`', $spec->errorCodes)).'.';
            if ($spec->notes !== []) {
                $lines[] = '';
                foreach ($spec->notes as $note) {
                    $lines[] = '- '.$note;
                }
            }
            if ($spec->examples !== []) {
                $lines[] = '';
                $lines[] = '```json';
                foreach ($spec->examples as $example) {
                    $lines[] = $example;
                }
                $lines[] = '```';
            }
            $lines[] = '';
        }

        $lines[] = self::errorTable();

        return rtrim(implode("\n", $lines))."\n";
    }

    /**
     * Replace the generated section of $document (markers included). A
     * document without markers gets the section appended.
     */
    public static function withGeneratedSection(string $document, string $audience): string
    {
        $section = self::GENERATED_BEGIN."\n\n".self::referenceMarkdown($audience)."\n".self::GENERATED_END;
        $begin = strpos($document, self::GENERATED_BEGIN);
        $end = strpos($document, self::GENERATED_END);

        if ($begin === false || $end === false || $end < $begin) {
            return rtrim($document)."\n\n".$section."\n";
        }

        return substr($document, 0, $begin).$section.substr($document, $end + strlen(self::GENERATED_END));
    }

    private static function errorTable(): string
    {
        $lines = ['### Error codes', '', '| Code | Meaning and next step |', '|---|---|'];
        foreach (VfsOperationSpecRegistry::ERROR_CODES as $code => $meaning) {
            $lines[] = '| `'.$code.'` | '.self::cell($meaning).' |';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<string, mixed>
     */
    private static function successSchema(VfsOperationSpec $spec): array
    {
        return [
            'type' => 'object',
            'required' => ['ok', 'result'],
            'properties' => [
                'ok' => ['const' => true],
                'result' => [
                    'type' => 'object',
                    'properties' => array_map(fn (string $meaning): array => ['description' => $meaning], $spec->resultFields),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function execSuccessSchema(VfsOperationSpec $spec): array
    {
        return [
            'type' => 'object',
            'required' => ['ok', 'exit_code', 'stdout', 'cwd'],
            'properties' => array_map(fn (string $meaning): array => ['description' => $meaning], $spec->resultFields),
        ];
    }

    private static function defaultText(VfsParameterSpec $parameter): string
    {
        return match (true) {
            $parameter->default === null => '',
            is_bool($parameter->default) => $parameter->default ? '`true`' : '`false`',
            default => '`'.$parameter->default.'`',
        };
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    private static function defaultsText(array $defaults): string
    {
        return implode(', ', array_map(
            fn (string $key, mixed $value): string => '`'.$key.': '.(is_bool($value) ? ($value ? 'true' : 'false') : (string) json_encode($value)).'`',
            array_keys($defaults),
            $defaults,
        ));
    }

    private static function cell(string $text): string
    {
        return str_replace(['|', "\n"], ['\|', ' '], $text);
    }
}
