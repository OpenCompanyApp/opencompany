# Exchange Rate Integration

> **Direction status (2026-11-02):** Superseded by [Product Direction](../../../PRODUCT_DIRECTION.md), and by [Connections, Events and Routines](../../../planning/connections-events-routines.md) — the integration package system is deleted and the integrations repository archived; replaced by deep connectors plus generic MCP, API manifests and skills (pillar 9).

Currency exchange rate integration for the OpenCompany integration ecosystem. Provides access to 340 fiat currencies, cryptocurrencies, and precious metals via the fawazahmed0/exchange-api.

No API key required.

## Tools

The current provider registers six read-only tools:

| Tool | Description |
|------|-------------|
| `exchangerate_list_currencies` | List and search available fiat, crypto, and precious-metal currencies |
| `exchangerate_popular_currencies` | Show commonly used currency codes |
| `exchangerate_convert_currency` | Convert an amount from one currency to another |
| `exchangerate_pair_rate` | Get the direct exchange rate for one currency pair |
| `exchangerate_rates` | Get all exchange rates for a base currency |
| `exchangerate_history` | Compare a currency pair across multiple dates |

## Popular Currency Codes

| Code | Currency |
|------|----------|
| usd | US Dollar |
| eur | Euro |
| gbp | British Pound |
| jpy | Japanese Yen |
| cny | Chinese Yuan |
| btc | Bitcoin |
| eth | Ethereum |
| xau | Gold (troy oz) |
| xag | Silver (troy oz) |

## License

MIT
