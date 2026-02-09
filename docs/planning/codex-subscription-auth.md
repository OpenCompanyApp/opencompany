# OpenCode: Codex Subscription Authentication Research

> How OpenCode (github.com/anomalyco/opencode) allows users to authenticate with their ChatGPT Pro/Plus subscription to use Codex models at zero marginal cost, instead of paying for OpenAI API tokens directly.

**Source**: `inspiration/opencode/packages/opencode/src/plugin/codex.ts` (625 lines — the entire implementation)

---

## Summary

OpenCode implements a **plugin-based OAuth flow** that authenticates users against `auth.openai.com` (the same auth system ChatGPT uses), then **rewrites all API calls** to route through `chatgpt.com/backend-api/codex/responses` instead of `api.openai.com/v1`. This means the user's ChatGPT subscription covers all token costs — the Codex models show `$0` for input/output. The entire implementation lives in a single 625-line TypeScript file that hooks into the provider system via a custom `fetch()` function.

---

## 1. OAuth Configuration

```
CLIENT_ID:          "app_EMoamEEZ73f0CkXaXp7hrann"
ISSUER:             "https://auth.openai.com"
CODEX_API_ENDPOINT: "https://chatgpt.com/backend-api/codex/responses"
OAUTH_PORT:         1455 (local callback server)
SCOPES:             "openid profile email offline_access"
```

The `CLIENT_ID` is a registered OAuth app at OpenAI. The `codex_cli_simplified_flow=true` parameter in the authorize URL signals a CLI-optimized login flow.

## 2. Authentication Methods

### Method A: Browser OAuth (PKCE)

1. Generate PKCE code verifier + challenge (SHA-256, base64url)
2. Generate random state for CSRF protection
3. Start local HTTP server on `localhost:1455`
4. Open browser to `https://auth.openai.com/oauth/authorize?...`
5. User logs into ChatGPT in browser
6. Callback hits `localhost:1455/auth/callback` with authorization code
7. Validate state parameter (CSRF protection)
8. Exchange code for tokens at `POST https://auth.openai.com/oauth/token`
9. Extract `access_token`, `refresh_token`, `expires_in`, `id_token`
10. Parse JWT to extract `chatgpt_account_id` (for org subscriptions)
11. Store in `~/.local/share/opencode/auth.json` with mode `0o600`

The local server also handles error responses and cancellation (`/cancel` endpoint). A 5-minute timeout auto-rejects if the user doesn't complete auth.

### Method B: Device Authorization (Headless)

For environments without a browser:

1. `POST https://auth.openai.com/api/accounts/deviceauth/usercode` → get `device_auth_id` + `user_code`
2. Display user code to terminal, direct user to `https://auth.openai.com/codex/device`
3. Poll `POST https://auth.openai.com/api/accounts/deviceauth/token` at configured interval + 3s safety margin
4. When user approves, exchange `authorization_code` + `code_verifier` for tokens at `POST https://auth.openai.com/oauth/token`
5. Same token storage as Method A

The redirect URI for device auth is `https://auth.openai.com/deviceauth/callback`.

### Method C: Manual API Key

Traditional `sk-...` API key entry — bypasses OAuth entirely, uses standard `api.openai.com` endpoints, and bills per-token.

## 3. Token Storage

**File**: `~/.local/share/opencode/auth.json` (file mode `0o600` — owner read/write only)

```typescript
// OAuth (Codex subscription)
{
  "openai": {
    "type": "oauth",
    "refresh": "rt_...",         // Refresh token (long-lived)
    "access": "eyJ...",          // Access token (short-lived JWT)
    "expires": 1707500000000,    // Expiration timestamp (ms)
    "accountId": "acct_..."      // ChatGPT org account ID (optional)
  }
}

// API key (traditional)
{
  "openai": {
    "type": "api",
    "key": "sk-..."
  }
}

// Well-known (enterprise/managed)
{
  "provider-id": {
    "type": "wellknown",
    "key": "...",
    "token": "..."
  }
}
```

The auth store is a simple JSON file keyed by provider ID. Zod schemas validate the data on read.

## 4. Token Refresh

Automatic refresh when `access` is empty or `expires < Date.now()`:

```
POST https://auth.openai.com/oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=refresh_token
&refresh_token={stored_refresh_token}
&client_id=app_EMoamEEZ73f0CkXaXp7hrann
```

Returns new `access_token`, `refresh_token`, `expires_in`. The `accountId` is preserved across refreshes — re-extracted from the new JWT if possible, falling back to the previously stored value.

The refresh happens inside the custom `fetch()` function, so it's transparent to the SDK.

## 5. Request Interception — The Core Trick

The plugin returns a custom `fetch()` function that intercepts every SDK HTTP call:

```typescript
// 1. Strip the dummy API key header
//    (Vercel AI SDK requires an API key, so a dummy is provided)
headers.delete("Authorization")

// 2. Inject OAuth access token
headers.set("authorization", `Bearer ${currentAuth.access}`)

// 3. Add org account header (for team/enterprise subscriptions)
if (authWithAccount.accountId) {
  headers.set("ChatGPT-Account-Id", authWithAccount.accountId)
}

// 4. REWRITE THE URL — this is the key part
//    /v1/responses       → https://chatgpt.com/backend-api/codex/responses
//    /chat/completions   → https://chatgpt.com/backend-api/codex/responses
const url = parsed.pathname.includes("/v1/responses")
         || parsed.pathname.includes("/chat/completions")
  ? new URL("https://chatgpt.com/backend-api/codex/responses")
  : parsed
```

The Vercel AI SDK thinks it's talking to `api.openai.com`, but every request is silently redirected to the Codex subscription endpoint at `chatgpt.com`. The request/response format is identical — OpenAI's Codex backend accepts the same payload format as their public API.

## 6. Model Filtering

When using OAuth (Codex subscription), only these models are available:

| Model ID | Notes |
|----------|-------|
| `gpt-5.3-codex` | Latest; manually injected if missing from models.dev registry |
| `gpt-5.2-codex` | |
| `gpt-5.2` | |
| `gpt-5.1-codex` | |
| `gpt-5.1-codex-max` | Max context variant |
| `gpt-5.1-codex-mini` | Smaller/faster variant |

All other OpenAI models are **deleted** from the provider when using OAuth auth. All Codex model costs are zeroed out:

```typescript
for (const model of Object.values(provider.models)) {
  model.cost = { input: 0, output: 0, cache: { read: 0, write: 0 } }
}
```

The `gpt-5.3-codex` model is special-cased — if it doesn't exist in the models.dev registry, the plugin creates it manually with hardcoded capabilities (400K context, reasoning, tool calls, image input).

## 7. JWT Account ID Extraction

The access/id token JWT contains the ChatGPT account ID, searched in priority order:

```
1. claims.chatgpt_account_id                                // Root-level claim
2. claims["https://api.openai.com/auth"].chatgpt_account_id // Namespaced claim
3. claims.organizations[0].id                               // Org array fallback
```

This account ID is sent as the `ChatGPT-Account-Id` header on every request, which is required for organization/team ChatGPT subscriptions. The parser first tries the `id_token`, then falls back to the `access_token`.

## 8. Additional Request Headers

Every OpenAI request also gets these headers via the `chat.headers` hook:

```
originator: opencode
User-Agent: opencode/{VERSION} ({platform} {release}; {arch})
session_id: {sessionID}
```

## 9. Comparison: Codex Subscription vs API Key

| Aspect | Codex Subscription (OAuth) | API Key (Traditional) |
|--------|---------------------------|----------------------|
| **Auth method** | OAuth PKCE + refresh tokens | `sk-...` bearer token |
| **API endpoint** | `chatgpt.com/backend-api/codex/responses` | `api.openai.com/v1/responses` |
| **Cost** | $0 (included in ChatGPT Pro/Plus) | Pay-per-token |
| **Available models** | Only Codex models (6 models) | All OpenAI models |
| **Auth header** | `Authorization: Bearer {jwt}` + `ChatGPT-Account-Id` | `Authorization: Bearer sk-...` |
| **Token refresh** | Automatic (refresh_token grant) | N/A (keys don't expire) |
| **Token storage** | `auth.json` — OAuth schema (refresh + access + expires) | `auth.json` — API schema (key only) |
| **Rate limits** | ChatGPT subscription tier limits | API tier limits |
| **Org support** | `ChatGPT-Account-Id` header | API key scoped to org |

## 10. Key Source Files

| File | Purpose |
|------|---------|
| `inspiration/opencode/packages/opencode/src/plugin/codex.ts` | **Core implementation** — OAuth flows, token management, request interception, model filtering (625 lines) |
| `inspiration/opencode/packages/opencode/src/auth/index.ts` | Token storage/retrieval (`auth.json`, mode 0600, Zod schemas) |
| `inspiration/opencode/packages/opencode/src/provider/auth.ts` | Provider auth framework (plugin hooks, auth method registry) |
| `inspiration/opencode/packages/opencode/src/provider/provider.ts` | SDK instantiation, custom fetch injection, provider loading, bundled SDK map |
| `inspiration/opencode/packages/opencode/src/cli/cmd/auth.ts` | CLI `auth login/list/logout` commands |
| `inspiration/opencode/packages/opencode/test/plugin/codex.test.ts` | JWT parsing and account ID extraction tests |
| `inspiration/opencode/packages/web/src/content/docs/providers.mdx` | User-facing docs on provider setup |
