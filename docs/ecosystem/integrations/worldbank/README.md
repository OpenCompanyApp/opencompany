# World Bank Integration

World Bank Indicators API v2 integration for the OpenCompany integration ecosystem. It exposes public country, aggregate, source, topic, indicator, language, and data endpoints. No API key is required.

## Tools

| Tool | Type | Description |
|------|------|-------------|
| `worldbank_countries` | read | List or search countries with region and income-level filters |
| `worldbank_country_info` | read | Get metadata for one country or aggregate code |
| `worldbank_regions` | read | List region and aggregate codes |
| `worldbank_income_levels` | read | List income-level codes |
| `worldbank_lending_types` | read | List lending-type codes |
| `worldbank_sources` | read | List World Bank data sources |
| `worldbank_source_indicators` | read | List indicators available in a data source |
| `worldbank_indicators` | read | Search indicators or list common indicators |
| `worldbank_indicator_info` | read | Get metadata for one indicator |
| `worldbank_topics` | read | List topics or indicators in a topic |
| `worldbank_languages` | read | List supported API languages |
| `worldbank_get_data` | read | Fetch one indicator for countries, aggregates, dates, or most recent values |
| `worldbank_multi_indicator_data` | read | Fetch multiple indicators from one source |
| `worldbank_compare_data` | read | Compare one indicator across multiple countries |

## Common Indicators

| Code | Description |
|------|-------------|
| `NY.GDP.MKTP.CD` | GDP (current US$) |
| `NY.GDP.MKTP.KD.ZG` | GDP growth (annual %) |
| `NY.GDP.PCAP.CD` | GDP per capita (current US$) |
| `FP.CPI.TOTL.ZG` | Inflation, consumer prices (annual %) |
| `SL.UEM.TOTL.ZS` | Unemployment (% of labor force) |
| `SP.POP.TOTL` | Population, total |
| `SP.DYN.LE00.IN` | Life expectancy at birth (years) |
| `SI.POV.GINI` | Gini index |
| `EN.ATM.CO2E.PC` | CO2 emissions (metric tons per capita) |

## Notes

Multiple-indicator data calls require a source ID. The default source is `2` (World Development Indicators), and the tool enforces the World Bank V2 limit of 60 indicators per request.

## License

MIT
