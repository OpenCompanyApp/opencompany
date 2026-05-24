# Web

Use `app.web.search` and `app.web.fetch` for normal non-browser web research.
These tools use workspace-configured provider adapters and return structured
tables in Lua.

## `app.web.search(args)`

Search the web with the configured provider.

```lua
local results = app.web.search({
    query = "Laravel 12 queue batching official docs",
    provider = "tavily",
    max_results = 5,
    allowed_domains = { "laravel.com" },
    include_snippets = true,
    include_answer = false,
})

for _, result in ipairs(results.results or {}) do
    print(result.title)
    print(result.url)
end
```

Common arguments:

- `query` string, required
- `provider` optional: `tavily`, `zai`, `exa`, `brave`, `firecrawl`, `jina`,
  `searxng`, `perplexity`, `openai_native`, `anthropic_native`
- `max_results` number
- `allowed_domains` list
- `blocked_domains` list
- `search_depth` as `basic` or `advanced`
- `include_snippets` boolean
- `include_answer` boolean
- `mode`, `country`, `language`, `recency`, `timeout_seconds`, `no_cache`

## `app.web.fetch(args)`

Fetch and extract a web page.

```lua
local page = app.web.fetch({
    url = "https://laravel.com/docs/12.x/queues",
    mode = "outline",
})

local section = app.web.fetch({
    url = "https://laravel.com/docs/12.x/queues",
    mode = "section",
    section_id = page.outline[1].id,
    max_chars = 8000,
})
```

Modes:

- `metadata`
- `outline`
- `main`
- `full`
- `section`
- `match`
- `chunk`

Common arguments:

- `url` string, required
- `provider` optional: `direct`, `jina`, `firecrawl`, `tavily`, `exa`,
  `parallel`, `zai`
- `strategy`: `auto`, `direct_only`, or `provider_only`
- `format`: `markdown`, `text`, or `html`
- `max_chars`
- `heading`
- `section_id`
- `match`
- `chunk_token`
- `include_metadata`
- `include_outline`
- `timeout_seconds`
- `no_cache`
