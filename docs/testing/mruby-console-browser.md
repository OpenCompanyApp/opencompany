# mruby console browser fixture

This is a **mocked-API browser regression fixture**, not authenticated Laravel,
database, provider, or released-engine evidence. It loads this isolated
checkout's real built Vue assets, intercepts every browser request, and permits
only synthetic `POST /api/code/execute` responses. No credentials, saved browser
state, external API, LLM, integration, or Ruby engine process is used.

The normal application layout starts a background presence heartbeat. The fixture
explicitly aborts that `PATCH /api/users/fixture-user/presence` attempt before it
can leave the mocked page and reports it separately as blocked background noise;
it is not counted as a successful request or console side effect. Every other
unexpected non-GET API request is rejected and fails the fixture assertion.

Build the isolated checkout, then serve only its `public` directory in a
separate terminal:

```sh
npm run build:typecheck
php -S 127.0.0.1:18743 -t public
```

The fixture uses the named browser session. It must not replace another active
owner's session:

```sh
playwright-cli -s=browser open about:blank --persistent
playwright-cli -s=browser run-code --filename=scripts/testing/mruby-console-browser.js
playwright-cli -s=browser snapshot
playwright-cli -s=browser close
```

It asserts four built-UI behaviors:

- successful validation displays `Validation passed. Nothing was executed.` and
  no execution output or return value;
- executing the mocked Ruby source renders its `console smoke` log and return
  value `42`;
- a located syntax response renders its line/column, repair guidance, and Monaco
  error marker; and
- editing the source clears that marker, so diagnostics do not remain attached
  to a successor source. The latest error output itself remains visible until a
  later result or explicit clear, by design.

This fixture deliberately proves only client rendering and request shape. Use
the separate authenticated test-site/database smoke for real mruby execution
and queue evidence.
