# Web research

Use `app.web.search` and `app.web.fetch` for approved network access. QuickJS
itself has no network API.

## Search

```js
var response = app.web.search({
  query: "Laravel 12 queue batching official documentation",
  provider: "tavily",
  max_results: 5,
  allowed_domains: ["laravel.com"],
  include_snippets: true,
  include_answer: false,
});

for (const result of (response.results || [])) {
  console.log(result.title, result.url);
}

return response.results || [];
```

Common fields include `query` (required), `provider`, `max_results`,
`allowed_domains`, `blocked_domains`, `search_depth`, `include_snippets`,
`include_answer`, `country`, `language`, `recency`, `timeout_seconds`, and
`no_cache`. Read `code_read_doc("web.search")` for the current contract.

## Fetch incrementally

```js
var outline = app.web.fetch({
  url: "https://laravel.com/docs/12.x/queues",
  mode: "outline",
});

var firstSection = outline.outline?.[0];
if (!firstSection) return { title: outline.title, content: "" };

return app.web.fetch({
  url: "https://laravel.com/docs/12.x/queues",
  mode: "section",
  section_id: firstSection.id,
  max_chars: 8000,
});
```

Fetch modes include `metadata`, `outline`, `main`, `full`, `section`, `match`,
and `chunk`. Prefer outline/section or chunked reads over returning a full large
page. Provider selection, caching, timeouts, URL policy, and authorization stay
host-owned.
