# OpenCompany Agent Guide

This file is the agent-facing operating guide for work inside the OpenCompany repository.

## Project Basics

- App URL for local development: `http://opencompany.test`
- Stack:
- Laravel 12
- Vue 3 + Inertia.js
- Tailwind CSS v4
- Reka UI primitives

## Workspace Rules

- OpenCompany is multi-workspace.
- Most data is workspace-scoped.
- Current workspace is resolved by middleware and available through `workspace()`.
- When adding queries, always scope them correctly.
- For models with `workspace_id`, use `forWorkspace()`.
- For related models, scope through the relationship with `whereHas(...)` or equivalent.

## Agent Runtime Notes

- The main runtime agent class is `app/Agents/OpenCompanyAgent.php`.
- Identity/system-prompt content is assembled from identity files and agent config, not from a static hardcoded prompt.
- The repo historically referenced `AGENTS.md` as an architectural concept, but this app currently stores agent instructions through its identity-file/document system.

## UI Rules

- Shared UI components live in `resources/js/Components/shared/`.
- Prefer wrapper components over raw elements when equivalents already exist.
- Dark mode exists and should not be broken by new UI work.

## MCP CLI

- MCP CLI is installed at `~/.local/bin/mcp-cli`.
- Config is at `~/.config/mcp/mcp_servers.json`.
- Common usage:
- `mcp-cli`
- `mcp-cli info <server>`
- `mcp-cli call <server> <tool> '<json>'`
- Connected servers currently include:
- `founder-mode`
- `notion`
- `vibe_kanban`
- `plane`

## Repo Conventions

- Prefer `rg` and `rg --files` for search.
- Keep edits targeted. Do not revert unrelated user changes.
- Do not patch `vendor/` for durable product work unless the task is explicitly temporary or exploratory.
- Put audits and investigations into markdown docs under `docs/`.

## Current Documentation Anchors

- Repo rules and local setup: `CLAUDE.md`
- Docs index: `docs/INDEX.md`
- Runtime audit: `docs/architecture/runtime-alignment-implementation-audit.md`
- Plane issue OC-1 investigation: `docs/architecture/plane-oc-1-investigation.md`
