# OpenCompany and Bowerbird: the boundary

Status: **Accepted** (owner, 2026-11-02). Nothing here is implemented. Binding summary:
[Product Direction](../PRODUCT_DIRECTION.md), "Relationship to Bowerbird". This page uses public
Bowerbird information only; see [bowerbird.si](https://bowerbird.si).

[Bowerbird](https://bowerbird.si) is a sister product: a durable execution and CI/CD engine with
hosted runners and agent sandboxes. OpenCompany and Bowerbird are separate products that work well
together. Neither depends on the other.

## Split by workload

- **OpenCompany owns business automation:** routines, events, approvals, agent work and the human
  surface (messenger, Home, approval cards).
- **Bowerbird owns software delivery and heavy, durable pipelines:** CI/CD, builds, and long,
  reproducible runs with evidence.
- This supersedes older docs that gave Bowerbird ownership of OpenCompany's triggers and workflows
  ([earlier integration doc](../architecture/bowerbird-durable-automation-integration.md)).

## The only ways the products meet

OpenCompany reaches Bowerbird through these four paths and no others:

1. **The `ComputerProvider` driver.** Bowerbird agent sandboxes are one computer provider among
   several, implemented directly on Bowerbird's public Workspace API `v1alpha`
   ([computer contract](../architecture/computer-provider-contract.md)). OpenCompany is a reference
   consumer of that API, runs its conformance and agent-experience suites, and proposes `browser`,
   `screen` and `desktop_stream` as an optional capability. The driver follows once Bowerbird's
   agent sandboxes launch; boat and local providers ship first.
2. **The Bowerbird deep connection** (Phases 3–4). CI, run and review events flow into the
   workspace event log. Agents can re-run, cancel, and approve or reject a deployment under the
   normal policy engine. Bowerbird reviews appear as OpenCompany approval cards.
3. **The shared mruby engine.** Bowerbird owns the mruby guest
   ([`bowerbird-ruby-engine`](https://github.com/OpenCompanyApp/bowerbird-ruby-engine)); OpenCompany
   owns its host bindings and permissions. Engine releases must pass OpenCompany's Code Mode evals.
   A shared dialect spec lets a heavy script routine graduate to a Bowerbird pipeline with the same
   Ruby, while OpenCompany keeps the trigger, approvals and context.
4. **Shared CLI crates.** Contexts, device login, keychain, structured errors, schema and guide,
   and the eval harness live in Bowerbird's public client track under Apache-2.0. The `oc` CLI
   consumes them ([`oc` CLI and hosts](oc-cli-and-hosts.md)).

## Invariants

- **Self-hosted OpenCompany never requires a Bowerbird account.** Any later account linking on
  OpenCompany Cloud is optional, and Cloud bills its own seats and plans.
- **OpenCompany never waits on Bowerbird.** Every Bowerbird path is optional and ships when it is
  ready.
- **Provider-neutral.** Bowerbird is never an exclusive provider of computers, pipelines or coding
  agents. Roost stays a neutral price guide.
- **Governed like everything else.** Bowerbird actions go through the same policy engine, change
  sets, approvals and audit log as any other connection or computer.
