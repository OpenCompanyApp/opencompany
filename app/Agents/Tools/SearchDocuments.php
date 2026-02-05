<?php

namespace App\Agents\Tools;

use App\Models\Document;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchDocuments implements Tool
{
    public function description(): string
    {
        return 'Search workspace documents by keyword. Returns matching document titles and content snippets.';
    }

    public function handle(Request $request): string
    {
        try {
            $query = $request['query'];
            $limit = $request['limit'] ?? 5;

            $lowerQuery = '%' . strtolower($query) . '%';
            $documents = Document::where('is_folder', false)
                ->where(function ($q) use ($lowerQuery) {
                    $q->whereRaw('LOWER(title) LIKE ?', [$lowerQuery])
                      ->orWhereRaw('LOWER(content) LIKE ?', [$lowerQuery]);
                })
                ->orderBy('updated_at', 'desc')
                ->take($limit)
                ->get(['id', 'title', 'content', 'updated_at']);

            if ($documents->isEmpty()) {
                return "No documents found matching '{$query}'.";
            }

            $results = $documents->map(function ($doc) {
                $snippet = Str::limit($doc->content, 300);
                return "**{$doc->title}** (ID: {$doc->id})\n{$snippet}";
            });

            return "Found {$documents->count()} document(s) matching '{$query}':\n\n" . $results->implode("\n\n---\n\n");
        } catch (\Throwable $e) {
            return "Error searching documents: {$e->getMessage()}";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('The search query to find documents.')
                ->required(),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of results to return. Default: 5.'),
        ];
    }
}
