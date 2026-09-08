<?php

use App\Services\MrubySandboxService;

it('compiles every application Ruby guide without executing callbacks', function () {
    $count = 0;
    foreach (glob(resource_path('code-docs/*.md')) as $file) {
        preg_match_all('/```ruby\R(.*?)```/s', file_get_contents($file), $blocks);
        foreach ($blocks[1] as $index => $source) {
            $result = app(MrubySandboxService::class)->execute($source, validateOnly: true);
            expect($result->error, basename($file).' example '.($index + 1))->toBeNull();
            $count++;
        }
    }
    expect($count)->toBeGreaterThan(5);
});
