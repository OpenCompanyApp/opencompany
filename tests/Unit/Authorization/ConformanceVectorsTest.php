<?php

use App\Domain\Authorization\Engine\PolicyEngine;

/*
 * Runs every language-neutral authorization vector in
 * tests/conformance/authorization against the pure PolicyEngine (no
 * framework, no database). A future Rust governance crate must pass the same
 * files (GIN-2057); see the README there for the format.
 */

/**
 * Deep-merge per the vector format: objects merge recursively, everything
 * else (lists, scalars, null) replaces.
 *
 * @param  array<mixed>  $base
 * @param  array<mixed>  $override
 * @return array<mixed>
 */
function authzVectorMerge(array $base, array $override): array
{
    foreach ($override as $key => $value) {
        $isObject = is_array($value) && ($value === [] ? false : ! array_is_list($value));
        $baseIsObject = isset($base[$key]) && is_array($base[$key]) && ($base[$key] === [] || ! array_is_list($base[$key]));
        $base[$key] = $isObject && $baseIsObject ? authzVectorMerge($base[$key], $value) : $value;
    }

    return $base;
}

/** @return array<string, array{0: array<string, mixed>, 1: array<string, mixed>, 2: array{effect: string, reason: string}}> */
function authzVectorCases(): array
{
    $cases = [];
    foreach (glob(dirname(__DIR__, 2).'/conformance/authorization/*.json') ?: [] as $file) {
        $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        expect($data['format'] ?? null)->toBe('opencompany.authz.vectors/v1');
        foreach ($data['cases'] as $case) {
            $facts = authzVectorMerge($data['facts'], $case['facts'] ?? []);
            $cases[basename($file, '.json').': '.$case['name']] = [$case['request'], $facts, $case['expect']];
        }
    }

    return $cases;
}

it('passes every authorization conformance vector', function (array $request, array $facts, array $expect) {
    $decision = (new PolicyEngine)->decide($request, $facts);

    expect(['effect' => $decision->effect, 'reason' => $decision->reasonCode])
        ->toBe(['effect' => $expect['effect'], 'reason' => $expect['reason']], implode(' > ', $decision->trace));
})->with(authzVectorCases());

it('has vectors for every action the engine knows', function () {
    $covered = [];
    foreach (authzVectorCases() as [$request]) {
        $covered[(string) $request['action']] = true;
    }

    $uncovered = array_values(array_filter(
        PolicyEngine::actions(),
        // Settings commands share one rule; a few representatives cover it.
        fn (string $action): bool => ! isset($covered[$action]) && ! str_starts_with($action, 'settings.command.'),
    ));

    expect($uncovered)->toBe([]);
});
