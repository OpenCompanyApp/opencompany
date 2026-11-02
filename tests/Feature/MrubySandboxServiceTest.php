<?php

use App\Services\MrubySandboxService;

beforeEach(function () {
    $binary = getenv('RUBY_ENGINE_BINARY');
    if (! $binary || ! is_executable($binary)) {
        throw new RuntimeException('These tests require the real pinned Ruby engine via RUBY_ENGINE_BINARY.');
    }
    config(['code.engine_binary' => $binary]);
});

it('executes Ruby through the real PHP subprocess adapter', function () {
    $result = app(MrubySandboxService::class)->execute('[1, 2, 3].map { |n| n * 7 }.reduce(0) { |a, b| a + b }');
    expect($result->error)->toBeNull()
        ->and($result->result)->toBe(42)
        ->and($result->peakMemoryUsage)->toBeGreaterThan(0)
        ->and($result->effects['callbacks'])->toBe(0);
});

it('validates source without evaluating Ruby', function () {
    $result = app(MrubySandboxService::class)->execute('raise "must not execute"', validateOnly: true);
    expect($result->succeeded())->toBeTrue()->and($result->validatedOnly)->toBeTrue();
});

it('preserves structured results and data-only context', function () {
    $result = app(MrubySandboxService::class)->execute('{payload: ctx[:payload], empty: {}, list: [], no: false}',
        globals: ['ctx' => ['payload' => '#{raise "injection"}']]);
    expect($result->error)->toBeNull()
        ->and($result->result->payload)->toBe('#{raise "injection"}')
        ->and($result->result->empty)->toBeInstanceOf(stdClass::class)
        ->and($result->result->list)->toBe([])
        ->and($result->result->no)->toBeFalse();
});

it('returns repair metadata for real parser diagnostics', function () {
    $result = app(MrubySandboxService::class)->execute('value = )');
    expect($result->error['type'])->toBe('syntax_error')
        ->and($result->error['line'])->toBe(1)
        ->and($result->error['suggestion'])->toBeString()
        ->and($result->error['effectStatus'])->toBe('none');
});

it('never falls back to another interpreter when the engine is unavailable', function () {
    config(['code.engine_binary' => '/nonexistent/opencompany-ruby-engine']);
    $result = app(MrubySandboxService::class)->execute('42');
    expect($result->error['type'])->toBe('engine_unavailable')
        ->and($result->error['retryable'])->toBeFalse();
});

it('keeps console capabilities closed and captures bounded logs', function () {
    $result = app(MrubySandboxService::class)->execute('puts "hello"; false', profile: 'console');
    expect($result->error)->toBeNull()->and($result->output)->toBe('hello')->and($result->result)->toBeFalse();
    $denied = app(MrubySandboxService::class)->execute('app.records.list', profile: 'console');
    expect($denied->succeeded())->toBeFalse();
});
