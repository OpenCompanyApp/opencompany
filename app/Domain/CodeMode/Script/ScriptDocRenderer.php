<?php

namespace App\Domain\CodeMode\Script;

/**
 * Renders concise, searchable Code Mode references for agents and developers.
 *
 * Parameter tables come from canonical tool metadata. Supplementary package
 * pages add executable Ruby examples, result shapes, and provider-specific
 * constraints without bloating the agent's base prompt.
 */
class ScriptDocRenderer
{
    /**
     * @param  array<string, array<string, mixed>>  $namespaces
     * @param  array<string, string>  $staticPages
     */
    public function generateNamespaceIndex(array $namespaces, array $staticPages = [], ?string $filterNamespace = null): string
    {
        $allNamespaces = $namespaces;

        if ($filterNamespace !== null) {
            $namespaces = array_filter(
                $namespaces,
                fn (mixed $_value, string $key) => $key === $filterNamespace || str_starts_with($key, $filterNamespace.'.'),
                ARRAY_FILTER_USE_BOTH,
            );

            if ($namespaces === []) {
                return "Namespace '{$filterNamespace}' not found. Available: ".implode(', ', array_keys($allNamespaces));
            }
        }

        $lines = ['Available Code Mode API namespaces:', ''];

        foreach ($namespaces as $namespaceName => $namespace) {
            $lines[] = "**app.{$namespaceName}** — {$namespace['description']}";
            $lines[] = '';
        }

        $lines[] = 'Use `code_read_doc` to inspect a namespace before calling its functions.';
        $lines[] = 'Examples: `code_read_doc(page: "integrations.coingecko")`, `code_read_doc(page: "integrations.coingecko.price")`.';
        $lines[] = '';

        if ($staticPages !== []) {
            $lines[] = 'Supplementary docs: '.implode(', ', array_keys($staticPages));
            $lines[] = 'Use code_read_doc to read any page or namespace in detail.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, array<string, mixed>>  $namespaces
     * @param  null|callable(string): ?string  $supplementaryDocsResolver
     */
    public function generateNamespaceDocs(
        string $namespace,
        array $namespaces,
        ?callable $supplementaryDocsResolver = null,
    ): string {
        if (! isset($namespaces[$namespace])) {
            return "Namespace '{$namespace}' not found. Available: ".implode(', ', array_keys($namespaces));
        }

        $namespaceData = $namespaces[$namespace];
        $lines = ["# app.{$namespace} — {$namespaceData['description']}", ''];

        foreach ($namespaceData['functions'] as $function) {
            $lines[] = '## app.'.$namespace.'.'.$this->buildSignature($function);
            $lines[] = '';

            if ($function['description'] !== '') {
                $lines[] = $function['description'];
                $lines[] = '';
            }

            $lines[] = '**Effect:** `'.$this->effect($function['effect'] ?? null).'`';
            $lines[] = '';

            $lines[] = $this->formatParameterTable($function['parameters']);
            $lines[] = '';
            $lines[] = $this->formatReturnShape($function['returns'] ?? []);
            $lines[] = '';
        }

        if ($supplementaryDocsResolver !== null) {
            $supplementary = $supplementaryDocsResolver($namespace);
            if ($supplementary !== null && $supplementary !== '') {
                $lines[] = '---';
                $lines[] = '';
                $lines[] = $supplementary;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, array<string, mixed>>  $namespaces
     */
    public function generateFunctionDocs(string $namespace, string $function, array $namespaces): string
    {
        if (! isset($namespaces[$namespace])) {
            return "Namespace '{$namespace}' not found.";
        }

        $match = null;
        foreach ($namespaces[$namespace]['functions'] as $entry) {
            if ($entry['name'] === $function || ($entry['exactName'] ?? null) === $function) {
                $match = $entry;
                break;
            }
        }

        if ($match === null) {
            $available = implode(', ', array_map(
                fn (array $entry) => $entry['name'],
                $namespaces[$namespace]['functions'],
            ));

            return "Function '{$function}' not found in app.{$namespace}. Available: {$available}";
        }

        $lines = [
            '# app.'.$namespace.'.'.$this->buildSignature($match),
            '',
        ];

        if ($match['description'] !== '') {
            $lines[] = $match['description'];
            $lines[] = '';
        }

        if ($match['fullDescription'] !== '' && $match['fullDescription'] !== $match['description']) {
            $lines[] = $match['fullDescription'];
            $lines[] = '';
        }

        $lines[] = '**Effect:** `'.$this->effect($match['effect'] ?? null).'`';
        $lines[] = '';

        $lines[] = $this->formatParameterTable($match['parameters']);
        $lines[] = '';
        $lines[] = $this->formatReturnShape($match['returns'] ?? []);
        $lines[] = '';
        $lines[] = "*(Maps to tool: `{$match['sourceToolSlug']}`)*";
        if (isset($match['exactName']) && $match['exactName'] !== $match['name']) {
            $exactPath = $namespace.'.'.$match['exactName'];
            $lines[] = 'Stable exact capability: `app.call('.json_encode($exactPath, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).', ...)`. Uses the same keywords and permissions.';
        }

        return implode("\n", $lines);
    }

    /** Render unknown/legacy metadata conservatively in executable docs. */
    private function effect(mixed $effect): string
    {
        return $effect === 'read' ? 'read' : 'write';
    }

    /**
     * @param  array<string, array<string, mixed>>  $namespaces
     * @param  array<string, string>  $staticPages
     */
    public function search(string $query, array $namespaces, array $staticPages = [], int $limit = 10): string
    {
        $queryLower = strtolower($query);
        $results = [];

        foreach ($namespaces as $namespaceName => $namespace) {
            foreach ($namespace['functions'] as $function) {
                $score = $this->scoreMatch($function, $namespaceName, $queryLower);

                if ($score > 0) {
                    $results[] = [
                        'score' => $score,
                        'text' => '**app.'.$namespaceName.'.'.$this->buildSignature($function).'** — '.$function['description'],
                    ];
                }
            }
        }

        foreach ($staticPages as $slug => $content) {
            if (stripos($content, $query) === false) {
                continue;
            }

            $results[] = [
                'score' => 1,
                'text' => "**[{$slug}]** (supplementary doc)\n".$this->extractSearchContext($content, $query),
            ];
        }

        usort($results, fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $results = array_slice($results, 0, $limit);

        if ($results === []) {
            return "No results found for '{$query}'.";
        }

        $lines = ['Found '.count($results)." result(s) for '{$query}':", ''];
        foreach ($results as $result) {
            $lines[] = $result['text'];
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, array<string, mixed>>  $namespaces
     * @param  array<string, string>  $staticPages
     * @return list<string>
     */
    public function getAvailablePages(array $namespaces, array $staticPages = []): array
    {
        $pages = array_keys($staticPages);
        $pages = array_merge($pages, array_keys($namespaces));
        sort($pages);

        return $pages;
    }

    /**
     * @param  array<string, array<string, mixed>>  $namespaces
     */
    public function getNamespaceSummary(array $namespaces): string
    {
        $internal = [];
        $integrations = [];
        $mcp = [];

        foreach (array_keys($namespaces) as $namespaceName) {
            if (str_starts_with($namespaceName, 'mcp.')) {
                $mcp[] = "app.{$namespaceName}";
            } elseif (str_starts_with($namespaceName, 'integrations.')) {
                $integrations[] = "app.{$namespaceName}";
            } else {
                $internal[] = "app.{$namespaceName}";
            }
        }

        $lines = [];

        if ($internal !== []) {
            $lines[] = '  Internal: '.implode(', ', $internal);
        }

        if ($integrations !== []) {
            $lines[] = '  Integrations: '.implode(', ', $integrations);
        }

        if ($mcp !== []) {
            $lines[] = '  MCP: '.implode(', ', $mcp);
        }

        $lines[] = '  Use code_read_doc(page) for function signatures and parameters.';

        return implode("\n", $lines);
    }

    /**
     * @param  array{name: string, parameters: array<int, array<string, mixed>>}  $function
     */
    private function buildSignature(array $function): string
    {
        $params = [];

        foreach ($function['parameters'] as $parameter) {
            $required = (bool) ($parameter['required'] ?? false);
            $name = (string) ($parameter['name'] ?? 'arg');
            $params[] = $required ? $name.':' : $name.': optional';
        }

        return $function['name'].'('.implode(', ', $params).')';
    }

    /**
     * @param  array<int, array<string, mixed>>  $parameters
     */
    private function formatParameterTable(array $parameters): string
    {
        if ($parameters === []) {
            return '*No parameters.*';
        }

        $lines = [
            '| Parameter | Type | Required | Description |',
            '|-----------|------|----------|-------------|',
        ];

        foreach ($parameters as $parameter) {
            $type = $parameter['type'] ?? 'string';
            if (is_array($type)) {
                $type = implode(' | ', $type);
            }

            $description = str_replace('|', '\\|', (string) ($parameter['description'] ?? ''));
            if (! empty($parameter['enum']) && is_array($parameter['enum'])) {
                $enumValues = implode(', ', array_map(
                    fn (mixed $value) => "`{$value}`",
                    $parameter['enum'],
                ));
                $description .= ($description !== '' ? ' ' : '')."Values: {$enumValues}";
            }

            $lines[] = sprintf(
                '| %s | %s | %s | %s |',
                (string) ($parameter['name'] ?? 'arg'),
                (string) $type,
                ! empty($parameter['required']) ? 'yes' : 'no',
                $description,
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Render the declared return contract without inventing a shape when a
     * package has not provided one yet.
     *
     * @param  array<string, mixed>  $returns
     */
    private function formatReturnShape(array $returns): string
    {
        if ($returns === []) {
            return '**Returns:** provider-defined JSON-compatible value.';
        }

        $type = (string) ($returns['type'] ?? 'value');
        $description = trim((string) ($returns['description'] ?? ''));
        $properties = is_array($returns['properties'] ?? null)
            ? array_keys($returns['properties'])
            : [];

        $summary = '**Returns:** `'.$type.'`';
        if ($description !== '') {
            $summary .= ' — '.$description;
        }
        if ($properties !== []) {
            $summary .= ' Keys: `'.implode('`, `', array_slice($properties, 0, 20)).'`.';
        }

        return $summary;
    }

    /**
     * @param  array{name: string, description: string, parameters: array<int, array<string, mixed>>}  $function
     */
    private function scoreMatch(array $function, string $namespaceName, string $queryLower): int
    {
        $score = 0;
        $words = array_filter(explode(' ', $queryLower));

        foreach ($words as $word) {
            if (strtolower($function['name']) === $word) {
                $score += 10;
            } elseif (str_contains(strtolower($function['name']), $word)) {
                $score += 5;
            }

            if (str_contains(strtolower($namespaceName), $word)) {
                $score += 3;
            }

            if (str_contains(strtolower($function['description']), $word)) {
                $score += 2;
            }

            foreach ($function['parameters'] as $parameter) {
                if (str_contains(strtolower((string) ($parameter['name'] ?? '')), $word)) {
                    $score += 1;
                }

                if (str_contains(strtolower((string) ($parameter['description'] ?? '')), $word)) {
                    $score += 1;
                }
            }
        }

        if (str_contains(strtolower($function['description']), $queryLower)) {
            $score += 5;
        }

        return $score;
    }

    private function extractSearchContext(string $content, string $query): string
    {
        $lines = explode("\n", $content);
        $snippets = [];

        foreach ($lines as $index => $line) {
            if (stripos($line, $query) === false) {
                continue;
            }

            $start = max(0, $index - 2);
            $end = min(count($lines) - 1, $index + 2);
            $snippets[] = implode("\n", array_slice($lines, $start, $end - $start + 1));

            if (count($snippets) >= 2) {
                break;
            }
        }

        return implode("\n...\n", $snippets);
    }
}
