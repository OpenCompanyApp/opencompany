# CoinGecko JavaScript Reference

CoinGecko tools expose API v3 market, exchange, category, asset-platform, and public treasury data. Use CoinGecko IDs (`bitcoin`, `ethereum`) rather than ticker symbols (`BTC`, `ETH`).

## Common Discovery Flow

```js
var search = app.integrations.coingecko.search_coins({ query: "solana" })
var coin_id = search.coins[0].id

var categories = app.integrations.coingecko.list_categories({})
var platforms = app.integrations.coingecko.list_asset_platforms({})
```
`list_coins` can return contract addresses when called with `include_platform = "true"`:

```js
var coins = app.integrations.coingecko.list_coins({
  params: { include_platform: "true" },
})
```
## Prices And Markets

```js
var price = app.integrations.coingecko.price({
  ids: "bitcoin,ethereum",
  currencies: "usd,eur",
})

var token_price = app.integrations.coingecko.simple_token_price({
  asset_platform_id: "ethereum",
  contract_addresses: "0x0000000000000000000000000000000000000000",
  currencies: "usd",
})

var markets = app.integrations.coingecko.markets({
  currency: "usd",
  category: "layer-1",
  per_page: "10",
  page: "1",
})
```
`price` and `simple_token_price` include market cap, 24h volume, 24h change, and last-updated fields where CoinGecko returns them.

## Coin Detail And History

```js
var info = app.integrations.coingecko.info({ id: "bitcoin" })
var tickers = app.integrations.coingecko.coin_tickers({
  id: "bitcoin",
  params: { page: 1 },
})

var history_date = app.integrations.coingecko.coin_history_date({
  id: "bitcoin",
  date: "30-12-2025",
})

var chart = app.integrations.coingecko.history({
  id: "bitcoin",
  currency: "usd",
  days: "30",
})

var ohlc = app.integrations.coingecko.ohlc({
  id: "bitcoin",
  currency: "usd",
  days: "30",
})
```
The existing `info`, `markets`, `history`, `ohlc`, `search_coins`, `trending`, and `global` tools return normalized summaries to keep agent output small. New endpoint-family tools return CoinGecko's JSON shape directly.

## Exchanges

```js
var exchanges = app.integrations.coingecko.list_exchanges({
  params: { per_page: 25, page: 1 },
})

var exchange = app.integrations.coingecko.get_exchange({ id: "binance" })
var tickers = app.integrations.coingecko.get_exchange_tickers({
  id: "binance",
  params: { coin_ids: "bitcoin" },
})

var volume = app.integrations.coingecko.exchange_volume_chart({
  id: "binance",
  days: "30",
})
```
Use `list_exchange_ids` to discover exchange IDs before calling exchange-specific tools.

## Categories, Rates, Global Data

```js
var category_market = app.integrations.coingecko.categories_market_data({
  params: { order: "market_cap_desc" },
})

var rates = app.integrations.coingecko.exchange_rates({})
var trending = app.integrations.coingecko.trending({})
var global = app.integrations.coingecko.global({})
var defi = app.integrations.coingecko.global_defi({})
```
`exchange_rates` returns BTC-relative rates under `rates`.

## Asset Platforms And Token Lists

```js
var platforms = app.integrations.coingecko.list_asset_platforms({})
var token_list = app.integrations.coingecko.token_list({
  asset_platform_id: "ethereum",
})
```
Use asset platform IDs for token-price and token-list calls.

## Public Treasury

```js
var entities = app.integrations.coingecko.list_entities({})

var by_coin = app.integrations.coingecko.public_treasury_by_coin({
  entity: "companies",
  coin_id: "bitcoin",
  params: { per_page: 25 },
})

var by_entity = app.integrations.coingecko.public_treasury_entity({
  entity_id: "strategy",
})
```
`entity` must be `companies` or `governments` for `public_treasury_by_coin`.

## Long-Tail GET Endpoints

Use `api_get` only for read-only CoinGecko API v3 endpoints that do not yet have a first-class tool, for example derivatives or NFT endpoints:

```js
var derivatives = app.integrations.coingecko.api_get({
  path: "/derivatives",
  params: {},
})
```
`api_get` accepts relative API paths only. It does not call external URLs.
