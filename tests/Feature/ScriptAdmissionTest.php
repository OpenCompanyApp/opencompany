<?php

use App\Models\Automation;
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
    $automation = new Automation(['execution_type' => 'script', 'script' => '42', ...$metadata]);
    expect($admission->matches($automation))->toBeTrue();
    $automation->script = '43';
    expect($admission->matches($automation))->toBeFalse();
    $automation->script = '42';
    $automation->script_engine_digest = str_repeat('0', 64);
    expect($admission->matches($automation))->toBeFalse();
});

it('does not infer a language from a legacy body that also parses as Ruby', function () {
    $automation = new Automation(['execution_type' => 'script', 'script' => '42', 'script_runtime' => null]);
    expect(app(ScriptAdmission::class)->matches($automation))->toBeFalse();
});

it('never admits invalid Ruby or evaluates source during admission', function () {
    expect(app(ScriptAdmission::class)->admit('raise "must not run"')['script_digest'])
        ->toBe(hash('sha256', 'raise "must not run"'));
    app(ScriptAdmission::class)->admit('value = )');
})->throws(ValidationException::class);
