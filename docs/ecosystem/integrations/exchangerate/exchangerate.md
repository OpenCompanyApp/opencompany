# Exchange Rate — Lua API Reference

No API key needed. Supports 340+ currencies: fiat, crypto, and precious metals.

## exchangerate_convert_currency

Convert an amount from one currency to another.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `from` | string | yes | Source currency code (e.g. `"usd"`, `"btc"`, `"xau"`) |
| `to` | string | yes | Target currency code (e.g. `"eur"`, `"jpy"`) |
| `amount` | string | no | Amount to convert (default: `"1"`) |
| `date` | string | no | Date for the rate: `"YYYY-MM-DD"` or `"latest"` (default) |

```lua
local result = exchangerate_convert_currency({
  from = "usd",
  to = "eur",
  amount = "100"
})

log("100 USD = " .. result.result .. " EUR")
```

## exchangerate_history

Compare a currency pair across multiple dates.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `from` | string | yes | Source currency code |
| `to` | string | yes | Target currency code |
| `dates` | string | yes | Comma-separated dates (e.g. `"2026-01-01,2026-02-01,2026-03-01"`) |

```lua
local result = exchangerate_history({
  from = "usd",
  to = "eur",
  dates = "2026-01-01,2026-02-01,2026-03-01"
})

for _, h in ipairs(result.history) do
  log(h.date .. ": " .. h.rate)
end
-- result.change.percentage shows overall change
```

## exchangerate_list_currencies

List and search available currencies.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `query` | string | no | Filter by code or name (e.g. `"dollar"`, `"btc"`, `"gold"`) |

```lua
-- Find gold-related currencies
local result = exchangerate_list_currencies({ query = "gold" })

for _, c in ipairs(result.currencies) do
  log(c.code .. ": " .. c.name)
end
```

## exchangerate_rates

Get all exchange rates for a base currency (not shown in detail -- pass `base` currency code).

## exchangerate_popular_currencies

Show commonly used currency codes. No parameters.

```lua
local result = exchangerate_popular_currencies({})
```

## Examples

### Convert 500 EUR to USD

```lua
local result = exchangerate_convert_currency({
  from = "eur",
  to = "usd",
  amount = "500"
})
```

### Historical rate on a specific date

```lua
local result = exchangerate_convert_currency({
  from = "gbp",
  to = "jpy",
  amount = "1",
  date = "2025-06-15"
})
```

### Track EUR/USD over several months

```lua
local result = exchangerate_history({
  from = "eur",
  to = "usd",
  dates = "2025-10-01,2025-11-01,2025-12-01,2026-01-01,2026-02-01,2026-03-01"
})

log("Change: " .. result.change.percentage .. "%")
```

### Find a currency code

```lua
local result = exchangerate_list_currencies({ query = "peso" })
-- Returns matching currencies like MXN (Mexican Peso), ARS (Argentine Peso), etc.
```
