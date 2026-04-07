# Plausible Analytics — Lua API Reference

## plausible_query_stats

Query website analytics with aggregate stats, timeseries, or breakdowns.

### Parameters

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `site_id` | string | yes | Site domain, e.g. `"example.com"` |
| `metrics` | array | yes | Metrics to retrieve (see list below) |
| `date_range` | string | yes | Time period (see options below) |
| `dimensions` | array | no | Group results by dimension (see list below) |
| `filters` | string | no | JSON-encoded filter expressions |
| `date_from` | string | no | Start date (ISO 8601) when `date_range="custom"` |
| `date_to` | string | no | End date (ISO 8601) when `date_range="custom"` |
| `order_by` | string | no | JSON-encoded sort order |
| `limit` | integer | no | Max results (default: 10000) |

### Date Range Options

`"7d"`, `"28d"`, `"30d"`, `"month"`, `"3mo"`, `"6mo"`, `"12mo"`, `"custom"`

When using `"custom"`, you must also pass `date_from` and `date_to`.

### Available Metrics

`visitors`, `pageviews`, `visits`, `bounce_rate`, `visit_duration`, `views_per_visit`, `events`, `conversion_rate`

### Available Dimensions

| Dimension | Description |
|-----------|-------------|
| `visit:source` | Traffic source (Google, Twitter, etc.) |
| `visit:country` | Country code |
| `visit:city` | City name |
| `visit:device` | Device type (Desktop, Mobile, Tablet) |
| `visit:browser` | Browser name |
| `visit:os` | Operating system |
| `event:page` | Page path |
| `event:name` | Custom event name |
| `time:day` | Day-level timeseries |
| `time:month` | Month-level timeseries |

### Filter Syntax

Filters are a JSON string containing an array of filter expressions:

```
[["operator", "dimension", ["value1", "value2"]]]
```

Operators: `is`, `is_not`, `contains`, `does_not_contain`, `matches`, `does_not_match`

### Order By Syntax

```
[["metric_name", "desc"]]
```

## Examples

### Top pages by visitors (last 30 days)

```lua
local result = plausible_query_stats({
  site_id = "example.com",
  metrics = {"visitors", "pageviews"},
  date_range = "30d",
  dimensions = {"event:page"},
  order_by = '[["visitors", "desc"]]',
  limit = 20
})

for _, row in ipairs(result.rows) do
  log(row["event:page"] .. ": " .. row.visitors .. " visitors")
end
```

### Traffic by country (custom date range)

```lua
local result = plausible_query_stats({
  site_id = "example.com",
  metrics = {"visitors", "visits", "bounce_rate"},
  date_range = "custom",
  date_from = "2026-01-01",
  date_to = "2026-01-31",
  dimensions = {"visit:country"},
  order_by = '[["visitors", "desc"]]',
  limit = 10
})

for _, row in ipairs(result.rows) do
  log(row["visit:country"] .. ": " .. row.visitors .. " visitors, " .. row.bounce_rate .. "% bounce")
end
```

### Filter to specific country

```lua
local result = plausible_query_stats({
  site_id = "example.com",
  metrics = {"visitors", "pageviews"},
  date_range = "7d",
  dimensions = {"event:page"},
  filters = '[["is", "visit:country", ["US"]]]'
})
```

### Filter pages containing /blog

```lua
local result = plausible_query_stats({
  site_id = "example.com",
  metrics = {"visitors", "pageviews"},
  date_range = "30d",
  dimensions = {"event:page"},
  filters = '[["contains", "event:page", ["/blog"]]]',
  order_by = '[["pageviews", "desc"]]'
})
```

### Daily timeseries

```lua
local result = plausible_query_stats({
  site_id = "example.com",
  metrics = {"visitors"},
  date_range = "30d",
  dimensions = {"time:day"}
})
```

### Aggregate totals (no dimensions)

```lua
local result = plausible_query_stats({
  site_id = "example.com",
  metrics = {"visitors", "pageviews", "bounce_rate", "visit_duration"},
  date_range = "30d"
})
```
