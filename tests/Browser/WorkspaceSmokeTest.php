<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Str;

it('loads core workspace routes without JavaScript errors', function () {
    $user = User::factory()->create();
    $workspace = app('currentWorkspace');

    $this->actingAs($user);

    Document::create([
        'id' => Str::uuid()->toString(),
        'workspace_id' => $workspace->id,
        'title' => 'Browser Smoke Doc',
        'content' => '# Browser Smoke Doc',
        'content_format' => 'markdown',
        'author_id' => $user->id,
        'is_folder' => false,
    ]);

    foreach ([
        '/w/test/',
        '/w/test/tasks',
        '/w/test/docs',
        '/w/test/integrations',
    ] as $path) {
        $this->visit($path)
            ->wait(0.25)
            ->assertSee('OpenCompany')
            ->assertNoJavaScriptErrors();
    }

    $this->visit('/w/test/docs')
        ->wait(0.5)
        ->assertSee('Browser Smoke Doc')
        ->assertNoJavaScriptErrors();
});
