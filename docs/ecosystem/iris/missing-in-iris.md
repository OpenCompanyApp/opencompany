# What Is Still Missing In Iris

Audit date: 2026-04-03

Status: Historical Iris audit. OpenCompany no longer uses Prism, Prism Relay, or Prism Codex as runtime dependencies; current OpenCompany AI ownership is app-local.

This note captures the remaining gaps in Iris after the Prism + `prism-relay`
integration and the bundled-only relay registry mode.

## Highest-priority gaps

### 1. Exact preauthorization is still missing

Settlement is exact after the response finishes, but preflight balance checks are
still conservative only.

- Iris only rejects when balance is non-positive or when requested max output
  cost exceeds balance.
- Iris does not estimate prompt-side token cost before dispatch.
- Iris does not reserve funds before sending the upstream request.
- Concurrent requests can still race and overspend the same balance.

Relevant code:

- `src/Http/Controller/ProxyController.php`
- `src/Accounting/PricingEngine.php`
- `src/Accounting/TokenLedger.php`

### 2. Rate limiting is configured but not enforced

`rate_limit` exists in config and is stored in the ledger, but the request path
does not currently apply any requests-per-window check before allowing a call.

Relevant code:

- `config/iris.yaml`
- `src/Kernel.php`
- `src/Auth/TokenAuthenticator.php`
- `src/Accounting/TokenLedger.php`

### 3. Relay error normalization is not fully wired into HTTP responses

Iris still returns generic normalization and provider failure responses instead
of routing exceptions through relay's structured error categorization.

What is missing:

- mapping provider exceptions through `OpenCompany\PrismRelay\Normalizers\ErrorNormalizer`
- returning stable error codes and HTTP statuses by normalized category
- forwarding retry hints such as `Retry-After`
- shaping streaming failure output consistently with the normalized error model

Relevant code:

- `src/Http/Controller/ProxyController.php`
- `vendor/opencompanyapp/prism-relay/src/Normalizers/ErrorNormalizer.php`

### 4. Routing policy config is still mostly dead

The config declares `default_provider` and `failover`, but the router currently
uses direct model-to-provider lookup from configured models and does not execute
failover chains on provider errors.

Relevant code:

- `config/iris.yaml`
- `src/Provider/ProviderRouter.php`

### 5. Full provider runtime parity is not there yet

Iris uses relay metadata and runtime adapters where they exist, but not every
provider in the relay catalog is executable through Prism yet.

As of the current relay package, these providers are still metadata-only:

- `cerebras`
- `cloudflare-ai-gateway`
- `codex`
- `cohere`
- `custom`
- `deepinfra`
- `gitlab`
- `google-vertex-anthropic`
- `sap-ai-core`
- `togetherai`
- `v0`
- `venice`

Relevant docs:

- `vendor/opencompanyapp/prism-relay/TODO.md`

## Secondary gaps

### 6. Authentication is still minimal

A bearer token is accepted if it exists in static config or in the local ledger.
There is still no stronger production token model around issuer verification,
expiry, scopes, revocation, or hashed token storage.

Relevant code:

- `src/Auth/TokenAuthenticator.php`

### 7. Test coverage is still too thin for production confidence

Current tests cover mapping and some request-building behavior, but not the main
operational risks.

Missing test areas:

- auth failures
- rate limiting
- exact billing and overspend prevention
- streaming settlement
- normalized error responses
- failover behavior
- end-to-end provider request handling

Relevant paths:

- `tests/`

### 8. README and config still overstate the implemented product surface

The current docs still describe parts of the product that do not actually exist
in the Iris service today.

Examples:

- balance checking is described as middleware behavior
- pack, drop, and exchange concepts are documented, but not implemented in Iris
- routing config implies failover support that is not active

Relevant files:

- `README.md`
- `config/iris.yaml`

## Practical interpretation

If the goal is "usable internal proxy with exact post-settlement and shared
relay metadata," Iris is already close.

If the goal is "watertight production settlement and fully normalized proxy,"
the blocking work is:

1. exact preauthorization and reservation flow
2. real rate-limit enforcement
3. relay-normalized error handling
4. actual routing failover logic
5. closing or clearly scoping the remaining relay runtime-provider gaps
