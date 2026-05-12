<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\AgentDocumentService;
use Illuminate\Database\Seeder;

/**
 * Backfills the document-backed identity folder for existing agents.
 *
 * New agents create this structure during agent creation; this seeder exists for
 * older databases that predate document-backed identity and memory files.
 */
class AgentIdentitySeeder extends Seeder
{
    public function run(): void
    {
        $service = app(AgentDocumentService::class);

        // Only agents missing docs_folder_id need the migration path. Existing
        // folders may contain user-edited identity files and must be left intact.
        $agents = User::where('type', 'agent')
            ->whereNull('docs_folder_id')
            ->get();

        foreach ($agents as $agent) {
            $folder = $service->createAgentDocumentStructure($agent);
            $agent->update(['docs_folder_id' => $folder->id]);
        }
    }
}
