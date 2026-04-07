# Integration: CoinGecko

Cryptocurrency market data for AI agents — search coins, get prices, market rankings, trending coins, and historical chart data.

> Part of the **OpenCompany** integration ecosystem. These packages extend AI agents with real-world capabilities through the Laravel AI SDK.

## Available Tools

| Tool | Type | Description |
|---|---|---|
| `coingecko_search` | read | Search coins by name/symbol, trending coins, global market overview |
| `coingecko_market` | read | Current prices, market rankings, and market cap data |
| `coingecko_details` | read | Coin profiles, historical price charts, OHLC candlestick data |

## Installation

```bash
composer require opencompanyapp/integration-coingecko
```

## Configuration

Requires a free CoinGecko Demo API key. Get one at [CoinGecko Developer Dashboard](https://www.coingecko.com/en/api/pricing).

## Dependencies

| Package | Purpose |
|---|---|
| `opencompanyapp/integration-core` | Shared tool provider contracts and registry |
| `laravel/ai` | Laravel AI SDK tool interface |

## License

MIT
