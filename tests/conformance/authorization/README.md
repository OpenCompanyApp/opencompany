# Authorization conformance vectors

Language-neutral test cases for the OpenCompany authorization engine
(`App\Domain\Authorization\Engine\PolicyEngine`). Each case is
`facts + request → expected effect + reason code`. The PHP engine runs them in
`tests/Unit/Authorization/ConformanceVectorsTest.php`. A later Rust governance
crate (GIN-2057) must pass the same files unchanged. Change a vector only
together with a rule change and a `PolicyEngine::VERSION` bump.

## File format (`format: opencompany.authz.vectors/v1`)

```json
{
  "format": "opencompany.authz.vectors/v1",
  "description": "what this file covers",
  "facts": { "...shared facts..." },
  "cases": [
    {
      "name": "unique, readable case name",
      "facts": { "...overrides, deep-merged into the shared facts..." },
      "request": { "workspace": "ws1", "actor": "u-admin", "on_behalf_of": null, "action": "channel.read" },
      "expect": { "effect": "allow | deny | approval_required", "reason": "reason_code" }
    }
  ]
}
```

Merge rule for `cases[].facts`: JSON objects merge key by key, recursively.
Arrays, strings, numbers, booleans and `null` replace the shared value.
An implementation compares `effect` and `reason` (the reason code) only; the
English reason and the trace are not part of the contract.

## Facts

| Key | Shape |
|---|---|
| `now` | ISO 8601 UTC instant; the engine never reads a clock |
| `workspace` | `{ "id", "owner_id", "preset": "personal" \| "team" \| "company" }` |
| `actors` | map of actor id → actor facts (below); the request actor and the on-behalf-of human must be here |
| `resource` | resource facts (below) or `null` |
| `context` | action-specific facts (below) |

Actor facts by `kind`:

- `human`: `workspace_role` (`admin`, `approver`, `member`, `viewer` or `null` when not a member), `platform_operator` (bool), optional `name`. The owner is the human whose id equals `workspace.owner_id`; they hold every capability.
- `agent`: `workspace_id`, `manager_id`, `channel_grants` (list of channel ids, or `null` = unrestricted), `contact_rules` (map of target agent id or `*` → `{ "permission": "allow" | "deny", "requires_approval": bool }`).
- `api_key`: `workspace_id`, `revoked` (bool), `expires_at` (ISO instant or `null`), `scopes` `{ "actions": [...], "models": [...] }` (`"*"` = every model).

Resource facts by `type` (every resource has `id` and `workspace_id`):

- `workspace`: the workspace itself (`workspace_id` = its id).
- `channel`: `channel_type` (`public`, `private`, `dm`, `external`), `members` (map user id → channel role `admin` \| `member`).
- `message` / `reaction`: `owner_id` (author or reacting user) and `channel` (channel facts).
- `document`: `is_folder`, `is_system`, `author_id`, `chain` (the document then its ancestors, nearest first; each `{ "id", "author_id", "shares": { user id → "viewer" \| "editor" } }`).
- `document_comment`: `owner_id` and `document` (document facts).
- `agent`: `manager_id`.
- `member`: `user_id`, `role`.
- `approval`: `requester_id`; `workspace_id` may be `null` (legacy rows).
- `ai_model`: the model id as `id`.

Context keys:

- `tool.invoke`: `action_class`, `explicit_permission` (`null` or `{ "permission", "requires_approval" }`), `approval_exempt`, `trigger_started`, `grant` `{ "scoped": bool, "always": bool }`.
- `channel.manage_members`: `target_in_workspace` (bool).
- `agent.create`: `manager_id` (requested manager or `null`).
- `member.update_role`: `role` (the new role).
- `ai_gateway.invoke`: `operation` (`models.list`, `chat.completions`, `embeddings`).

## Decision order

Fixed and documented in `PolicyEngine`: known action → workspace boundary →
hard-deny floor → actor kind → the action's rule → on-behalf-of intersection.
