# Integration: CoinGecko

Cryptocurrency market data for AI agents: coin IDs, prices, token-address prices, market rankings, categories, asset platforms, exchanges, exchange rates, trending data, historical charts, and public treasury data.

> Part of the **OpenCompany** integration ecosystem. These packages extend AI agents with real-world capabilities through integration tools.

## Available Tools

| Area | Tools |
|---|---|
| Discovery | `coingecko_search_coins`, `coingecko_list_coins`, `coingecko_list_categories`, `coingecko_list_asset_platforms` |
| Prices and markets | `coingecko_price`, `coingecko_simple_token_price`, `coingecko_markets`, `coingecko_top_gainers_losers`, `coingecko_new_coins` |
| Coin detail and history | `coingecko_info`, `coingecko_coin_tickers`, `coingecko_coin_history_date`, `coingecko_history`, `coingecko_ohlc` |
| Exchanges | `coingecko_list_exchanges`, `coingecko_list_exchange_ids`, `coingecko_get_exchange`, `coingecko_get_exchange_tickers`, `coingecko_exchange_volume_chart` |
| General data | `coingecko_exchange_rates`, `coingecko_trending`, `coingecko_global`, `coingecko_global_defi`, `coingecko_token_list` |
| Public treasury | `coingecko_list_entities`, `coingecko_public_treasury_by_coin`, `coingecko_public_treasury_entity` |
| Long tail | `coingecko_api_get` for read-only CoinGecko API v3 GET endpoints not yet modeled as first-class tools |

## Installation

```bash
composer require opencompanyapp/integration-coingecko
```

## Configuration

A CoinGecko Demo API key is optional for public endpoints, but recommended for predictable rate limits. The service sends it with the `x-cg-demo-api-key` header.

## Notes

- Coin tools use CoinGecko IDs such as `bitcoin`, not ticker symbols such as `BTC`.
- Token-price tools use asset platform IDs such as `ethereum` or `polygon-pos`.
- Public treasury tools use CoinGecko entity IDs from `coingecko_list_entities`.
- `coingecko_api_get` is read-only and accepts only relative CoinGecko API paths.

## Dependencies

| Package | Purpose |
|---|---|
| `opencompanyapp/integration-core` | Shared tool provider contracts and registry |

## License

MIT
