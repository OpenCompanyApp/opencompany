<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\AgentDocumentService;
use Illuminate\Database\Seeder;

class AgentIdentitySeeder extends Seeder
{
    public function run(): void
    {
        $service = app(AgentDocumentService::class);

        $agents = User::where('type', 'agent')
            ->whereNull('docs_folder_id')
            ->get();

        foreach ($agents as $agent) {
            $folder = $service->createAgentDocumentStructure($agent);
            $agent->update(['docs_folder_id' => $folder->id]);
        }
    }
}
