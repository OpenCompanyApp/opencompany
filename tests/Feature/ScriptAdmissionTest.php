<?php

use App\Models\Routine;
use App\Services\ScriptAdmission;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $binary = getenv('RUBY_ENGINE_BINARY');
    if (! $binary || ! is_executable($binary)) {
        throw new RuntimeException('Set RUBY_ENGINE_BINARY to the pinned engine.');
    }
    config(['code.engine_binary' => $binary]);
});

it('binds admission to exact source profile and engine bytes', function () {
    $admission = app(ScriptAdmission::class);
    $metadata = $admission->admit('42');
    $routine = new Routine(['kind' => 'script', 'script' => '42', ...$metadata]);
    expect($admission->matches($routine))->toBeTrue();
    $routine->script = '43';
    expect($admission->matches($routine))->toBeFalse();
    $routine->script = '42';
    $routine->script_engine_digest = str_repeat('0', 64);
    expect($admission->matches($routine))->toBeFalse();
});

it('does not infer a language from a legacy body that also parses as Ruby', function () {
    $routine = new Routine(['kind' => 'script', 'script' => '42', 'script_runtime' => null]);
    expect(app(ScriptAdmission::class)->matches($routine))->toBeFalse();
});

it('never admits invalid Ruby or evaluates source during admission', function () {
    expect(app(ScriptAdmission::class)->admit('raise "must not run"')['script_digest'])
        ->toBe(hash('sha256', 'raise "must not run"'));
    app(ScriptAdmission::class)->admit('value = )');
})->throws(ValidationException::class);
