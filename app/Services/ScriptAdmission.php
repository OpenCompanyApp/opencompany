<?php

namespace App\Services;

use App\Models\Automation;
use Illuminate\Validation\ValidationException;

/**
 * Owns source/profile/build admission, not workspace or scheduling authority.
 * Only an explicit submitted Ruby body may acquire admission. Metadata edits
 * cannot relabel a legacy body, even when that body happens to parse as Ruby.
 */
final class ScriptAdmission
{
    public function __construct(private MrubySandboxService $sandbox) {}

    /** Compile without authority and bind the receipt to immutable source/build digests. */
    public function admit(string $source): array
    {
        if (trim($source) === '') {
            throw ValidationException::withMessages(['script' => 'Submit a Ruby body before enabling this automation.']);
        }
        $engine = $this->sandbox->engineDigest();
        $result = $this->sandbox->execute($source, profile: 'automation', validateOnly: true, sourceName: 'automation-code.rb');
        if (! $result->succeeded()) {
            $error = $result->error;
            throw ValidationException::withMessages(['script' => '['.$error['type'].']'
                .(isset($error['line']) ? ' at line '.$error['line'] : '').': '.$error['message']]);
        }
        if (! hash_equals($engine, $this->sandbox->engineDigest())) {
            throw ValidationException::withMessages(['script' => 'The Ruby engine changed during validation. Validate again.']);
        }

        return ['script_runtime' => config('code.runtime'), 'script_digest' => hash('sha256', $source),
            'script_engine_digest' => $engine, 'script_validated_at' => now()];
    }

    /** Fail closed on changed source, build, runtime or missing admission metadata. */
    public function matches(Automation $automation): bool
    {
        try {
            return $automation->script_runtime === config('code.runtime')
                && $automation->script_validated_at !== null
                && is_string($automation->script_digest)
                && hash_equals($automation->script_digest, hash('sha256', (string) $automation->script))
                && is_string($automation->script_engine_digest)
                && hash_equals($automation->script_engine_digest, $this->sandbox->engineDigest());
        } catch (\Throwable) {
            return false;
        }
    }
}
