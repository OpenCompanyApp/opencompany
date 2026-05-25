# CoinGecko — Lua API Reference

## Important: Coin IDs vs Ticker Symbols

CoinGecko tools use CoinGecko IDs (e.g. `"bitcoin"`, `"ethereum"`, `"solana"`), **not** ticker symbols (`"BTC"`, `"ETH"`). If you only know the ticker, use `coingecko_search_coins` first to find the correct ID.

**Rate limits:** free tier allows ~30 calls/min.

## coingecko_search_coins

Find coin IDs by name or ticker symbol.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `query` | string | yes | Coin name or ticker (e.g. `"bitcoin"`, `"ETH"`) |

```lua
local result = coingecko_search_coins({ query = "SOL" })

-- result.coins is an array of { id, name, symbol, market_cap_rank }
for _, coin in ipairs(result.coins) do
  log(coin.id .. " (" .. coin.symbol .. ") — rank #" .. (coin.market_cap_rank or "?"))
end
```

## coingecko_price

Get current price for one or more coins. Includes 24h change, volume, and market cap.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `ids` | string | yes | Comma-separated CoinGecko IDs (e.g. `"bitcoin,ethereum"`) |
| `currencies` | string | no | Comma-separated target currencies (default: `"usd"`) |

```lua
local result = coingecko_price({
  ids = "bitcoin,ethereum",
  currencies = "usd,eur"
})
```

## coingecko_markets

Top coins ranked by market cap with full market data.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `ids` | string | no | Filter to specific coin IDs |
| `currency` | string | no | Target currency (default: `"usd"`) |
| `category` | string | no | Filter by category (e.g. `"decentralized-finance-defi"`) |
| `per_page` | string | no | Results per page (default: `"20"`, max: 100) |
| `page` | string | no | Page number (default: `"1"`) |
| `price_change_percentage` | string | no | Timeframes (default: `"24h,7d"`). Options: `1h,24h,7d,14d,30d,200d,1y` |

```lua
local result = coingecko_markets({
  per_page = "10",
  price_change_percentage = "24h,7d,30d"
})

for _, coin in ipairs(result.coins) do
  log("#" .. coin.market_cap_rank .. " " .. coin.name .. ": $" .. coin.current_price)
end
```

## Examples

### Search for a coin, then get its price

```lua
-- Step 1: find the coin ID
local search = coingecko_search_coins({ query = "Cardano" })
local coin_id = search.coins[1].id  -- "cardano"

-- Step 2: get the price
local price = coingecko_price({
  ids = coin_id,
  currencies = "usd,btc"
})
```

### Top 10 coins by market cap

```lua
local result = coingecko_markets({
  per_page = "10",
  currency = "usd"
})

for _, coin in ipairs(result.coins) do
  log(coin.name .. ": $" .. coin.current_price .. " (24h: " .. (coin.price_change_percentage_24h or "?") .. "%)")
end
```

### Compare specific coins

```lua
local result = coingecko_markets({
  ids = "bitcoin,ethereum,solana",
  currency = "usd",
  price_change_percentage = "1h,24h,7d"
})
```
