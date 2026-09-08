# mruby agent-experience regression corpus

`tests/Feature/MrubyAgentExperienceTest.php` is a deterministic functional
corpus for OpenCompany Code Mode. It starts the real pinned mruby engine and
routes its `app.integrations.ax.*` capabilities through the real ScriptBridge,
IntegrationRuntime, and scoped dispatch adapter. The tools are local fakes:
the corpus has no provider credentials, network calls, or LLM requests.

The workflows protect practical script behavior:

- filtering and selecting records without losing `false`, empty strings, or
  identifiers above JavaScript's safe-integer range;
- bounded two-page traversal rather than an unbounded continuation loop;
- invalid keyword arguments rejected before fake-tool dispatch, followed by a
  repaired call in the same Ruby program;
- a completed fake write followed by a Ruby error, which must remain
  non-retryable; and
- discovery limited to the exact published capability paths.

This is not a model-quality comparison, an optimal-agent-experience claim, or
a benchmark against QuickJS. It is a small, repeatable compatibility and safety
regression suite for the currently supported Ruby bridge contract.

Run it only with the pinned engine available:

```sh
RUBY_ENGINE_BINARY=/absolute/path/to/ruby-engine \
  ./vendor/bin/phpunit --filter=MrubyAgentExperienceTest tests/Feature/MrubyAgentExperienceTest.php
```
