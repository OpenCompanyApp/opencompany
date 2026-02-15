<?php

namespace Database\Seeders;

use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class WorkspaceSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Workspace::create([
            'name' => 'OpenCompany',
            'slug' => 'default',
            'owner_id' => null, // Will be set after user creation in UserSeeder
        ]);
    }
}
