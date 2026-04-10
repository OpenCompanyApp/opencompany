# Claude Code Web Tools — Reverse Engineered Spec

Reverse engineered from tool schemas, runtime behavior, and inner model probing on 2026-03-30.
Claude Code version: 2.1.86 (Bun-compiled Mach-O binary, installed via Homebrew cask).

## Architecture Overview

```
                        Claude Code (Opus 4.6, 1M context)
                          │                    │
                    WebSearch               WebFetch
                      │                       │
              ┌───────┴───────┐        ┌──────┴──────┐
              │  Search API   │        │  HTTP GET   │
              │  (unknown     │        │  raw HTML   │
              │   provider)   │        │     │       │
              └───────┬───────┘        │  HTML→MD    │
                      │                │  converter  │
              search result            │     │       │
              blocks with              │  Inner      │
              titles/URLs/             │  Claude     │
              snippets                 │  model      │
                      │                │     │       │
                      ▼                │  processed  │
              returned to              │  response   │
              outer model              └──────┬──────┘
                                              │
                                       returned to
                                       outer model
```

## WebSearch

### Schema

```json
{
  "name": "WebSearch",
  "parameters": {
    "query":           { "type": "string",   "required": true, "minLength": 2 },
    "allowed_domains": { "type": "string[]", "optional": true },
    "blocked_domains": { "type": "string[]", "optional": true }
  }
}
```

### Behavior

- Executes a web search and returns result blocks (titles, snippets, markdown hyperlinks)
- Domain filtering: whitelist via `allowed_domains`, blacklist via `blocked_domains`
- Geographically restricted to the US
- Results returned in a single API call — no pagination
- Search provider is opaque (likely Brave Search API based on public Anthropic disclosures)
- Outer model is required to append a `Sources:` section with URLs after any answer using results
- Must use current year (2026) in queries for recent info

### Constraints

- No authenticated/private URL access
- No JS rendering
- No control over result count
- US-only availability

## WebFetch

### Schema

```json
{
  "name": "WebFetch",
  "parameters": {
    "url":    { "type": "string", "format": "uri", "required": true },
    "prompt": { "type": "string", "required": true }
  }
}
```

### Pipeline

1. **HTTP GET** — Plain fetch, no JS execution, no headless browser
2. **HTTPS upgrade** — HTTP URLs auto-upgraded to HTTPS
3. **HTML to Markdown** — Raw HTML converted to markdown
4. **Inner model call** — Markdown content + user prompt sent to a Claude model
5. **Response** — Inner model's text response returned to the outer model

### Inner Model Details

| Property | Value |
|----------|-------|
| Model family | Claude (self-identifies as "3.5 Sonnet", actual version unknown, likely Haiku) |
| Context/budget | 200,000 tokens |
| System identity | "You are Claude Code, Anthropic's official CLI for Claude." |
| Tools | None — plain text completion |
| Conversation | Single turn, no history |
| Content placement | Web page content in user message, not system message |
| System prompt tags | `<budget>`, `<ip_reminder>` |

### Caching

- 15-minute self-cleaning cache
- Repeated fetches to the same URL within 15 minutes return cached results

### Redirect Handling

- Same-host redirects: followed automatically
- Cross-host redirects: returns redirect URL to outer model for manual re-fetch

### Failure Modes

- Authenticated URLs (Google Docs, Confluence, Jira): always fails
- JS-rendered SPAs (client-side only): returns empty shell HTML
- SSR pages: works fine (content in initial HTML)
- Large pages: content summarized/truncated by inner model

### Content Processing

The outer model (me) never sees raw HTML. The inner model acts as a lossy filter:
- Receives the full markdown conversion
- Processes it according to the `prompt` parameter
- Returns a summary/extraction
- Subject to IP restrictions (no full reproduction of copyrighted content, 125 char quote limit, no lyrics)

This means the `prompt` parameter is critical — it determines what information survives the inner model's processing.

## Typical Usage Pattern

```
1. WebSearch("laravel queue batching 2026")
   → search result blocks with URLs

2. User picks a relevant URL from results

3. WebFetch("https://laravel.com/docs/...", "Extract the code example for queue batching")
   → inner model reads page, extracts requested info, returns summary

4. Outer model synthesizes answer with Sources: section
```

## What We Don't Know

- Exact search provider (Brave suspected, not confirmed)
- Exact inner model version (Haiku suspected, self-reports as Sonnet)
- Whether the 200k budget is input context, output limit, or total
- Exact wording of the 5 ip_reminder sentences
- Whether the inner model system prompt varies by context
- Rate limits or quotas
