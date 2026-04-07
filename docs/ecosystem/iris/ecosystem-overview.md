# Iris Ecosystem Overview

Audit date: 2026-04-03

This document summarizes how Iris appears to fit into the broader
OpenCompany/KosmoKrator/token-commerce plan based on the current local docs and
code in:

- `/Users/rutger/Projects/kosmokrator`
- `/Users/rutger/Sites/opencompany`

Important: some of the strongest statements come from planning and confidential
architecture docs, not from already-shipped code. So this should be read as
"intended product/service direction" plus "current implementation reality."

## Executive summary

The intended architecture is not "Iris as just a proxy."

The intended architecture is:

- `prism-relay` = shared provider/model normalization library
- `Iris` = deployed inference proxy and settlement layer
- `OpenCompany` = team collaboration product
- `KosmoKrator` = open-source agent client
- `tokens.opencompany.app` = future token marketplace / token commerce product

The business plan is to keep team SaaS billing and AI compute billing separate,
while making them feel connected in product UX.

## The intended product split

The clearest strategy document is
`/Users/rutger/Projects/kosmokrator/docs/confidential/business-and-token-architecture.md`.

Its model is:

- **OpenCompany** = team AI platform
- **Token Commerce** = token packs, drops, exchange
- **KosmoKrator** = open-source agent
- **Relay/Iris** = inference proxy, provider routing, token accounting

That document explicitly describes:

- `opencompany.app` for team management and seat-based billing
- `tokens.opencompany.app` for packs, drops, exchange, and token billing
- `relay.opencompany.app` as the inference proxy service

In other words, Iris is meant to be infrastructure with product consequences:
it is where usage becomes billable.

## What Iris is supposed to be

Based on the same architecture doc, the intended responsibilities of Iris are:

- token auth
- token balance accounting
- provider routing
- provider failover
- rate limiting
- model pricing / settlement
- OpenAI-compatible inference endpoint

That makes Iris the service boundary between:

- user-facing apps
- third-party API consumers
- upstream model providers

The same doc treats Iris as the mission-critical piece because it is the one
service that can both execute inference and settle cost.

## Why Iris exists separately from `prism-relay`

The intended separation is:

- `prism-relay` is the library
- `Iris` is the deployed service

`prism-relay` owns:

- provider/model registry
- aliases and normalization
- provider capabilities
- pricing metadata
- request/response normalization
- provider-specific runtime adapters where available

Iris owns:

- HTTP API surface
- auth
- token ledger
- settlement
- routing policy
- failover behavior
- rate limits
- balance endpoints

That separation is consistent with the current codebase direction.

## How KosmoKrator fits in

KosmoKrator is currently both a consumer of `prism-relay` and part of the
strategic funnel into the rest of the ecosystem.

Observed from local code/docs:

- KosmoKrator already boots `RelayRegistryBuilder`, `RelayRegistry`, `Relay`,
  and `RelayManager`.
- It uses relay for request normalization, error normalization, response
  normalization, and prompt caching.
- Its README positions it as a multi-provider agent client with local and
  configurable provider access.

Strategically, the confidential business doc frames KosmoKrator as:

- an MIT/open-source agent product
- something that can connect directly to the relay
- a separate buyer/user path from OpenCompany

So KosmoKrator is both:

- a real relay consumer
- a distribution wedge that can create demand for hosted compute tokens later

## How OpenCompany fits in

OpenCompany is the team product, not the token product.

Current local docs show:

- OpenCompany is the self-hosted collaboration platform for teams
- its AI stack uses Laravel AI SDK plus Prism
- it already tracks token usage and provider/model analytics internally
- it treats OpenRouter as an important provider option

Strategically, the token/business architecture doc says OpenCompany should not
own token billing directly. Instead, OpenCompany should:

- own platform subscription billing
- surface token balances and usage
- link out to token management
- send inference traffic through the relay service

So OpenCompany is supposed to consume Iris, not absorb it.

## Planned token marketplace

The planned token-commerce product is explicitly described as separate from
OpenCompany.

Its planned responsibilities are:

- token pack browsing and purchase
- pack billing
- weekly token drops
- exchange listings / buy-sell order flow
- transaction history
- webhook handling for payments

The reason for separating it is economic and product clarity:

- OpenCompany sells seats to teams
- token commerce sells compute to individuals, teams, and third-party apps

This is why the strategy docs keep repeating that tokens should not be buried
inside OpenCompany billing.

## What this means for OpenRouter and other providers

Within this architecture, a provider like OpenRouter is useful because it gives
the ecosystem broad model reach behind one provider integration, while Iris and
`prism-relay` preserve control of:

- model catalog normalization
- internal accounting units
- provider capability rules
- API shape exposed to clients

That means OpenCompany and KosmoKrator can treat OpenRouter as one normalized
provider among many, while Iris remains the thing that decides billing and
settlement.

## What exists today versus what is still planned

### Exists today

- `prism-relay` as a real shared package
- Iris as the beginning of the standalone proxy/settlement service
- KosmoKrator using relay heavily in runtime
- OpenCompany using Prism and provider abstractions
- internal docs that clearly define the service split

### Planned or partial

- a full standalone token marketplace app
- production-grade token pack, drop, and exchange flows
- the complete "relay.opencompany.app" operational surface described in strategy
- perfect settlement controls and all policy enforcement inside Iris
- complete runtime parity for every provider in the generated relay registry

## Current reality for Iris

Iris is aligned with the intended direction, but it is not fully at the target
described in the architecture docs yet.

From the current Iris codebase, the remaining notable gaps are:

- exact preauthorization and reservation, not just post-settlement
- real rate-limit enforcement
- relay-normalized error mapping across the HTTP surface
- active routing failover policy
- product features around packs/drops/exchange are still absent from Iris itself

See also:

- `docs/missing-in-iris.md`

## Practical interpretation

If the ecosystem plan holds, Iris should become the canonical hosted compute
gateway for:

- OpenCompany hosted usage
- KosmoKrator hosted mode
- third-party consumers using OpenCompany-issued tokens
- future token-commerce settlement

That implies Iris should optimize for:

- strict correctness in accounting
- provider abstraction stability
- simple client API compatibility
- auditable ledger behavior
- high-quality failure handling
- separation from UI/product concerns

## Recommended framing for Iris

The cleanest positioning is:

- `prism-relay` normalizes providers
- `Iris` monetizes and governs inference
- `tokens` sells compute access
- `OpenCompany` and `KosmoKrator` consume that compute in different product
  contexts

If that remains the plan, then Iris is not a side utility. It is shared
infrastructure at the center of the token economy.
