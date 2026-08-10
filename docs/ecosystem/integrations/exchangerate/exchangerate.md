# Exchange Rate — JavaScript API Reference

No API key needed. Uses the public Fawaz Ahmed exchange-api datasets and supports fiat, crypto, and precious metals.

## convert_currency

Convert an amount from one currency to another.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `from` | string | yes | Source currency code (e.g. `"usd"`, `"btc"`, `"xau"`) |
| `to` | string | yes | Target currency code (e.g. `"eur"`, `"jpy"`) |
| `amount` | string | no | Amount to convert (default: `"1"`) |
| `date` | string | no | Date for the rate: `"YYYY-MM-DD"` or `"latest"` (default) |

```js
var result = app.integrations.exchangerate.convert_currency({
  from: "usd",
  to: "eur",
  amount: "100",
})

console.log("100 USD = " + result.result + " EUR")
```
## pair_rate

Get the direct exchange rate for one currency pair without downloading the full base-currency matrix.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `from` | string | yes | Source currency code (e.g. `"usd"`, `"btc"`, `"xau"`) |
| `to` | string | yes | Target currency code (e.g. `"eur"`, `"jpy"`) |
| `date` | string | no | Date for the rate: `"YYYY-MM-DD"` or `"latest"` (default) |

```js
var result = app.integrations.exchangerate.pair_rate({
  from: "usd",
  to: "eur",
})

console.log(result.rate)
```
## history

Compare a currency pair across multiple dates.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `from` | string | yes | Source currency code |
| `to` | string | yes | Target currency code |
| `dates` | string | yes | Comma-separated dates (e.g. `"2026-01-01,2026-02-01,2026-03-01"`) |

```js
var result = app.integrations.exchangerate.history({
  from: "usd",
  to: "eur",
  dates: "2026-01-01,2026-02-01,2026-03-01",
})

for (const h of (result.history)) {
  console.log(h.date + ": " + h.rate)
}
// result.change.percentage shows overall change
```
## list_currencies

List and search available currencies.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `query` | string | no | Filter by code or name (e.g. `"dollar"`, `"btc"`, `"gold"`) |

```js
var result = app.integrations.exchangerate.list_currencies({ query: "gold" })

for (const c of (result.currencies)) {
  console.log(c.code + ": " + c.name)
}
```
## rates

Get all exchange rates for a base currency.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `base` | string | yes | Base currency code (e.g. `"usd"`) |
| `currencies` | string | no | Comma-separated codes to filter (e.g. `"eur,gbp,jpy"`) |
| `date` | string | no | Date for the rate: `"YYYY-MM-DD"` or `"latest"` (default) |

```js
var result = app.integrations.exchangerate.rates({
  base: "usd",
  currencies: "eur,gbp,jpy",
})
```
## popular_currencies

Show commonly used currency codes. No parameters.

```js
var result = app.integrations.exchangerate.popular_currencies({})
```
## Examples

### Convert 500 EUR to USD

```js
var result = app.integrations.exchangerate.convert_currency({
  from: "eur",
  to: "usd",
  amount: "500",
})
```
### Historical rate on a specific date

```js
var result = app.integrations.exchangerate.convert_currency({
  from: "gbp",
  to: "jpy",
  amount: "1",
  date: "2025-06-15",
})
```
### Track EUR/USD over several months

```js
var result = app.integrations.exchangerate.history({
  from: "eur",
  to: "usd",
  dates: "2025-10-01,2025-11-01,2025-12-01,2026-01-01,2026-02-01,2026-03-01",
})

console.log("Change: " + result.change.percentage + "%")
```
### Find a currency code

```js
var result = app.integrations.exchangerate.list_currencies({ query: "peso" })
// Returns matching currencies like MXN (Mexican Peso), ARS (Argentine Peso), etc.
```