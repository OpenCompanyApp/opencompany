# OpenCompany Linear roadmap and source coverage — 2026-11-02

Status: operational backlog realignment to the final [Product Direction](../PRODUCT_DIRECTION.md), verified by
live Linear MCP read-back on 2026-11-02. This does not implement, commit, deploy or qualify the
application.
Verified: 2026-11-02 against Linear project P-GIN-4 and docs/PRODUCT_DIRECTION.md.
Supersedes: [2026-10-09 coverage](linear-project-backlog-coverage-2026-10-09.md) for milestone
structure and new epics. That file stays as the historical source-inventory and TODO-reconciliation
record; its per-source issue mapping is still valid, only the milestone names changed.

Project: [OpenCompany](https://linear.app/ginger-media/project/opencompany-2d9752cd90e0) (P-GIN-4, team
GIN). Linear mirror: the project document "OpenCompany roadmap — 2026-11-02 product-direction
realignment". The older Linear document "OpenCompany roadmap — full Markdown source coverage and
issue register" is at Linear's size limit, so it got a pointer comment instead of new content. No
dates, estimates or assignees were invented.

## Agent connections and app catalog passes (2026-11-02)

No dates or assignees were set.

### Agent connections (pillar 9, commit a0f787b)

**GIN-2000 Agent connections** (Phase 2 · Computer v1):

| Phase | Issues |
|---|---|
| 2 | GIN-2001 `ExternalAgentProvider` contract · GIN-2002 ACP runner on computers and hosts · GIN-2003 Copilot coding agent · GIN-2006 delegation run card · GIN-2007 external-action gate and provider/repo allowlist · GIN-2008 usage into budgets |
| 3 | GIN-2004 Cursor Cloud Agents (API in beta) · GIN-2005 Codex cloud, Jules, Claude Code cloud tasks |

GIN-2009 Bowerbird Agents provider is under GIN-1977, after S1, in Phase 3 Connections (labelled Later).

### App catalog (pillar 7)

One ticket per app under GIN-1893, with no child issues:

- **#1–3, updated:** GIN-1898 Mail (Phase 2), GIN-1899 Calendar & Scheduling (Phase 3), GIN-1900 CRM
  (Phase 4).
- **#4–6, "Next", in Phase 4:** GIN-2010 Helpdesk, GIN-2011 Requests & Approvals, GIN-2012 Knowledge
  Base.
- **#7–14, "Later", in Deferred with `OC: Later`:** GIN-2013 Quotes & Invoices, GIN-2014 Recruiting,
  GIN-2015 Meetings, GIN-2016 Content & Social, GIN-2017 Client Portal, GIN-2018 Assets & Vendors,
  GIN-2019 Goals/OKRs, GIN-2020 People basics.
- **Owner decision:** GIN-2021 "Decide beachhead customer and app bundle", in Phase 0 product truth.

## OpenCompany × Bowerbird pass (2026-11-02, commit 3e12a28)

[OpenCompany × Bowerbird](bowerbird-synergy.md) is accepted, and Aya is discontinued. The new epic is
**GIN-1977**. No Bowerbird Linear projects were edited and no dates were set.

| Sequencing | Issues |
|---|---|
| Now | GIN-1979 shared mruby engine and dialect spec with Code Mode evals in the engine gate (Phase 0 mruby) · GIN-1983 OpenCompany CI on Bowerbird Runners at C1 (Phase 0 CI) · GIN-1943 shared CLI crates, kept under GIN-1931 and linked |
| Phase 2 | GIN-1781 Bowerbird `ComputerProvider` driver with conformance and agent-experience suites (rewritten, moved from Phase 5) · GIN-1978 browser/screen/desktop_stream proposal · GIN-1982 Roost prices in the picker |
| Phases 3–4 | GIN-1981 Bowerbird deep connection · GIN-1984 cross-sell surfaces · GIN-1980 routine → pipeline graduation |
| Phase 5 | GIN-1985 shared account, wallet and bundles |
| Owner decision | GIN-1986 GitHub org, legal entity, wallet currency |

GIN-1814 was also updated: Cloud defaults to Bowerbird sandboxes after S1.

Some GIN issues already had relations to BWB issues from earlier passes: GIN-1781, 1933, 1934, 1936,
1937 and 1943. They are left as they are.

## Connected hosts, `oc` CLI and data sovereignty passes (2026-11-02, commits f0c1683 and 5139e15)

No dates or assignees were set in either pass.

### `oc` CLI and Connected hosts

Design: [`oc` CLI and hosts](oc-cli-and-hosts.md).

**GIN-1931 `oc` CLI (Rust, agent-first)**

| Phase | Issues |
|---|---|
| 0 | GIN-1933 generated client · GIN-1934 device flow and token types |
| 1 | GIN-1935–1943: scaffold, login and contexts, agent-first contract, TUI, work objects, `oc fs`/runs, `oc mcp serve`, distribution, shared crates (linked to Bowerbird's client track) |
| 2 | GIN-1944 `oc artifact dev` · GIN-1945 `oc code run` |
| 3 | GIN-1946 `oc mount` · GIN-1947 eval gate · GIN-1948 `oc admin` |

**GIN-1932 Connected hosts**

| Phase | Issues |
|---|---|
| 2 | GIN-1950 host tokens · GIN-1952 gateway · GIN-1954 `oc host up` · GIN-1956 container sandbox · GIN-1957 Admin → Hosts · GIN-1959 computer capability · GIN-1782 SSH hosts (rewritten) · GIN-1973 Tailscale docs · GIN-1975 Raspberry Pi |
| 3 | GIN-1964 bwrap · GIN-1966 local MCP bridge · GIN-1968 VFS projection · GIN-1970 event source |
| 5 | GIN-1976 Windows hosts (Later) |

GIN-1831 (local computer) is a duplicate of GIN-1959.

### Data sovereignty

Design: [Data sovereignty](data-sovereignty.md).

**GIN-1949 Data sovereignty**

| Phase | Issues |
|---|---|
| 0 | GIN-1951 `.ocarchive` format · GIN-1953 no-telemetry CI check |
| 1 | GIN-1955 downloads and `oc export` |
| 2 | GIN-1958 archive export · GIN-1960 import with round-trip test · GIN-1961 secrets in archive · GIN-1962 `oc admin backup/restore` |
| 3 | GIN-1963 scheduled encrypted backups · GIN-1965 verification · GIN-1967 Git mirror · GIN-1969 GDPR export · GIN-1971 export gates |
| 5 | GIN-1972 Cloud exit, region, verified deletion · GIN-1974 BYOK (Later) |

## Artifacts and Forms pass (2026-11-02, commit 2bacbe6)

[Artifacts and Forms](artifacts-and-forms.md) is accepted: Artifacts are the ninth core primitive,
every Space has six tools, and Forms are a view on any Table. Linear changes (no dates or assignees):

- **GIN-1150 is now the Artifacts epic** (Pages + interactive; one manifest family with apps). Its
  children were rewritten and moved by phase:
  - Phase 0: GIN-1178 (sandbox and CSP design).
  - Phase 1: GIN-1169, 1170, 1175, 1177 (Liquid → Pages + MCP Apps bridge), 1179 (Lab → Space
    Artifacts tool, preview and Make list), 1180.
  - Phase 2: GIN-1172 (platform change sets), 1173 (Lua → mruby `endpoint` profile), 1174, 1176
    (platform runs and event log).
  - Phase 3: GIN-1181. Phase 4: GIN-1171. Phase 5: GIN-1182, 1183, 1184.
- **Canceled:** GIN-1185 (visual editing, live refresh, template distribution).
- **New under GIN-1150:**
  - GIN-1911 user-content origin and sandbox proxy (Phase 0 design, Phase 1 build);
  - GIN-1912 published-surfaces registry and abuse controls (Phase 3);
  - GIN-1926 Code Mode bridge re-homed into the app (Phase 0);
  - GIN-1927 renderer outputs into Files (Phase 0);
  - GIN-1928 artifact cards in chat (Phase 1);
  - GIN-1929 static bundles from computers (Phase 2);
  - GIN-1930 MCP Apps export (Phase 5).
- **New Forms epic GIN-1913:**
  - Forms v1, Phase 2: GIN-1914–1919 (view and schema, submission pipeline, builder, page mode,
    internal/Telegram, agent generation).
  - Forms v2, Phase 3: GIN-1920–1924 (logic and partial saves, one-question, conversational, embeds,
    consent and retention).
  - Payments, Later (Phase 5): GIN-1925.
- **Other updates:** the "After Phase 4 · Artifacts and Lab" milestone now holds leftovers only.
  GIN-1895 (views catalog) gains `form`. "Six tools" was updated in GIN-1759, 1885 and 1886.

## Governance revision pass (2026-11-02, commit 5127548)

Pillar 5 now defines one policy engine with Personal, Team and Company presets, gated by
reversibility. Linear changes under GIN-1750 (no dates or assignees):

| Issue | What | Milestone |
|---|---|---|
| GIN-1902 | On-behalf-of access intersection with DM, routine and group-chat tests | Phase 0 · authorization and security |
| GIN-1903 | Presets and switching that keeps explicit grants | Phase 0 · authorization and security |
| GIN-1904 | Trash (30 days) and purge (approval; two approvers in Company) | Phase 0 · authorization and security |
| GIN-1905 | Learned exceptions | Phase 3 · roles, approvals and hard-deny floor |
| GIN-1906 | Batched approval cards | Phase 3 · roles, approvals and hard-deny floor |
| GIN-1907 | Autonomy slider (named grant bundles; not behaviour modes) | Phase 3 · roles, approvals and hard-deny floor |
| GIN-1908 | Space-level permissions, agents as Space members | Phase 4 · Work apps and apps platform |
| GIN-1909 | "Why can't Ada see this?" decision-trace explainer | Phase 3 · budgets, audit and observability |
| GIN-1910 | Preset-dependent Governance UI | Phase 3 · roles, approvals and hard-deny floor |

Rewritten to reversibility gating rather than canceled:
- GIN-1862 is now the policy engine with the pillar 5 table, replacing behaviour modes.
- GIN-1796 is now approval authority per preset.

Patched to match:
- Governance items: GIN-1797, 1798, 1799, 1800 and 1818.
- Routines: GIN-1850 and 1852.
- Change sets and creation: GIN-1875, 1880, 1886 and 1889.
- Approval cards: GIN-1762.
- External-send notes: GIN-1824, 1829, 1846, 1855, 1857, 1898 and 1899.

GIN-1199 (document permissions, P0) has a comment saying it converges on GIN-1908.

## UI lock pass (2026-11-02, after the final direction)

The owner accepted [UI structure and agent editing](ui-structure-and-agent-editing.md) (Home, one rail,
three panes, change sets, core primitives, add-on apps). Linear changes:

- New epics GIN-1871 (New client and messenger shell), GIN-1874 (Change sets and agent editing),
  GIN-1885 (Structure and anti-mess) and GIN-1893 (Apps platform), linked to the existing epics.
- GIN-1744 renamed "Messenger conversations"; its shell children (GIN-1759, 1764, 1765, 1866 → Home)
  moved under GIN-1871.
- Milestone "Phase 4 · Work apps — Tables and Issues" renamed "Phase 4 · Work apps and apps platform";
  Phase 1 UI and Deferred milestone descriptions updated (modular platform replaced by add-on apps).
- No dates, estimates or assignees.


All issues are in P-GIN-4, status Backlog, using existing labels, with no dates, estimates or assignees.

**Milestone renamed:** c4c82501 is now "Phase 4 · Work apps and apps platform", with an updated description. The milestone a50a8f23 had already been renamed by someone else to "Phase 3 · Connections — grants, MCP and deep connectors"; the Calendar app child was placed there.

#### GIN-1871 Epic: New client and messenger shell (Phase 1 · UI). Related: GIN-1744, GIN-1745, GIN-1838
- GIN-1759: re-parented from GIN-1744 and rewritten as "One 260px left rail" (exact sections, collapse/reorder, live badges).
- GIN-1866: re-parented and rewritten as "Home: attention page (needs you → results → FYI) replacing Dashboard, Activity and Workload" (was the activity feed).
- GIN-1764: re-parented and rewritten as "Three-pane layout: rail | main | resizable context pane (chat-first and object-first)" (was side panels).
- GIN-1765: re-parented and rewritten as "Mobile: bottom bar (Home, Chats, Spaces, Team), full-screen sheets".
- GIN-1872 (new): Vue 3 + Reka UI SPA foundation with a token design system. Related to GIN-1757, which stays in the API epic.
- GIN-1873 (new): delete dead UI now; delete Inertia pages as parity is reached. Related to GIN-1863.

Kept in GIN-1744 and updated to the UI spec:
- GIN-1760, retitled "Chats: … sections, unread counts, mentions and threads";
- GIN-1762, inline approvals at the run step, allow once/run/always/deny, DM/Home only;
- GIN-1763, retitled "Typed run activity …".

GIN-1744 got a split note prepended. GIN-1767 stays Duplicate of GIN-1863.

#### GIN-1874 Epic: Change sets and agent editing (Phase 4). Related: GIN-1751, 1750, 1839, 1755, 1871
- GIN-1875 Change-set model and versioned API (blockedBy GIN-1839, GIN-1753)
- GIN-1876 Record history with actor and run (blockedBy GIN-1839)
- GIN-1877 Realtime and presence for every object (blockedBy GIN-1755, GIN-1839)
- GIN-1878 Virtualized grid with server paging and row detail (blockedBy GIN-1872)
- GIN-1879 New column types: Person/Agent, Relation, Attachment, Long-text
- GIN-1880 Table staging and review: plan/dry-run, live staging, per cell/row/all (blockedBy 1875, 1877, 1878, 1876)
- GIN-1881 Revert this run (blockedBy 1875, 1876)
- GIN-1882 Human edits win conflict handling (blockedBy 1875)
- GIN-1883 Docs collaboration, suggestion mode, canonical format (blockedBy 1875, 1877)
- GIN-1884 Fix: doc autosave silently overwrites agent edits [Phase 1 UI, Bug]. Moved earlier because it is a data-loss bug.

#### GIN-1885 Epic: Structure and anti-mess (Phase 4). Related: GIN-1751, 1838, 1750, 1863, 1811, 1871
- GIN-1886 Spaces: flat, five tools, scoped app views
- GIN-1887 Typed docs and templates; flat docs; embeds link canonical objects
- GIN-1888 Owner/status/review date + stale sweep
- GIN-1889 Governed creation proposals (blockedBy GIN-1875)
- GIN-1890 Move agent system files out of Docs → profile + VFS /agents
- GIN-1891 Merge Org into Team [Phase 1 UI, because Team is in the shell footer]
- GIN-1892 Merge three file stores into one Files store

Merges not duplicated (related instead):
- Lists → Issues: GIN-1863 and GIN-1811
- Automation → Routines: GIN-1864, GIN-1850
- Dashboard/Activity/Workload → Home: GIN-1866
- Tasks → runs: GIN-1763

#### GIN-1893 Epic: Apps platform (Phase 4). Related: GIN-1751, 1837, 1836, 1195, 1874, 1885
- GIN-1894 Manifest format and runtime (blockedBy GIN-1875, GIN-1879)
- GIN-1895 Views catalog (blockedBy GIN-1878, GIN-1872)
- GIN-1896 Install/uninstall (archives)/upgrade (migrates schema) (blockedBy 1894)
- GIN-1897 /apps VFS mount (blockedBy 1894)
- GIN-1898 Mail app on Gmail/Outlook, doubles as agent inbox [Phase 2 · Computer v1] (blockedBy GIN-1855; related GIN-1855, 1856, 1824)
- GIN-1899 Calendar app + Home agenda [Phase 3 · Connections] (blockedBy GIN-1857)
- GIN-1900 CRM app, first schema-only app (blockedBy 1894, 1895, 1896, 1897)
- GIN-1901 Later: third-party signed manifests + sandboxed custom views [Deferred] (related GIN-1869, GIN-1805)

#### Consistency notes
- GIN-1811 (which model becomes Issues) and GIN-1863 (Lists → Issues, delete Dashboard/Workload) are unchanged and consistent. GIN-1767 remains Duplicate of GIN-1863.
- GIN-1810 (Tables/Issues/Docs side panels, under GIN-1751) is still blockedBy GIN-1764, now the three-pane layout. That remains valid.
- Mail and Calendar ship before the full Phase 4 manifest runtime. Their issues state that the minimal manifest pieces they need land with them, so they are not blocked by GIN-1894.


## Final direction pass (2026-11-02, later the same day)

The owner locked the final direction (nine pillars, connections/events/routines, nuclear decisions,
API-first now, policy model replacing behaviour modes). This pass:

- **Milestones renamed/re-described:** "Phase 1 · API-first backend and clients" (API-first now,
  Inertia frozen); "Phase 1 · Messenger — Telegram, MCP client and Drive"; "Phase 3 · Connections —
  grants, MCP and deep connectors" (formerly integrations); "Phase 3 · Governance — routines, triggers
  and orchestration". Phase 0 run control plane now carries the nuclear cleanup and replacement
  foundations; Phase 2 carries the early hard-deny entries and compute cap.
- **New epics:** GIN-1836 Connections, events and routines; GIN-1837 Deep connectors; GIN-1838
  Nuclear cleanup (Phase 0). Plus GIN-1843 (cross-workspace credential singleton bug, P0, under
  GIN-1186), GIN-1866 (activity feed from the event log), GIN-1867 (Phase 2 governance subset) and
  GIN-1868–1870 (OpenCompany as MCP server, MCP Apps cards, A2A Agent Cards; Phase 5, under GIN-1752).
- **Existing epics updated:** GIN-1745 (API-first now), GIN-1750 (policy model, one event log),
  GIN-1803 (replay on the event log), GIN-1767 (duplicate of GIN-1863), GIN-1811 (Lists→Issues
  decided), GIN-1825/1826 (routines). Vault GIN-1788 and broker GIN-1789 moved to Phase 0 auth, as the
  direction puts the Connections model with vault and broker in Phase 0.
- **Canceled as superseded:** 44 issues (package system and catalog, per-package
  integrations, catalog coverage audits, Automations as-is, visual builder, Bowerbird-owned automation
  execution, legacy-compatibility gates). Each has a comment pointing to `docs/PRODUCT_DIRECTION.md`.
  None was marked completed.
- **Kept or moved with a pillar-9 re-scope comment:** 32 issues (MCP hardening, Connect
  accounts, webhooks, heartbeat lanes → routines, skills, A2A research, and others).

### Canceled in the final pass

| Issue | Title | Action | Milestone | Reason |
|---|---|---|---|---|
| GIN-13 | Port integration scripting contracts and examples to mruby | canceled | Phase 0 mruby | Package system deleted, integrations repo archived; mruby cutover continues in GIN-12 |
| GIN-1234 | Quarantine legacy scripts and fence workers during Ruby cutover | canceled | Phase 0 mruby | Legacy-compatibility gate; migrations squashed, no legacy gates |
| GIN-1200 | Close workspace scoping gaps in list automation rules | canceled | Phase 0 auth | ListAutomationRule deleted; replaced by record-change triggers on the event log |
| GIN-1222 | Normalize tool-provider metadata and split catalog/wrapping responsibilities | canceled | Phase 0 run control | Integration-package metadata/catalog work; package system deleted; replaced by the Connections model |
| GIN-1342 | Qualify roadmap integration coverage: CRM & Sales | canceled | P3 integrations | Catalog coverage audit (package system deleted) → Tier 2 generic |
| GIN-1343 | Qualify roadmap integration coverage: Customer Support | canceled | P3 integrations | Catalog coverage → Tier 2 generic |
| GIN-1344 | Qualify roadmap integration coverage: Marketing & Email | canceled | P3 integrations | Catalog coverage → mailboxes Tier 1; rest Tier 2 |
| GIN-1345 | Qualify roadmap integration coverage: Social Media | canceled | P3 integrations | Catalog coverage → Tier 2; X later channel |
| GIN-1346 | Qualify roadmap integration coverage: E-commerce & Payments | canceled | P3 integrations | Catalog coverage → Tier 2 |
| GIN-1347 | Qualify roadmap integration coverage: Finance & Accounting | canceled | P3 integrations | Catalog coverage → Tier 2 |
| GIN-1348 | Qualify roadmap integration coverage: HR & People | canceled | P3 integrations | Catalog coverage → Tier 2 |
| GIN-1349 | Qualify roadmap integration coverage: Project Management | canceled | P3 integrations | Catalog coverage → Issues; Notion import later; Tier 2 |
| GIN-1350 | Qualify roadmap integration coverage: Developer Tools & DevOps | canceled | P3 integrations | Catalog coverage → Git later Tier 1; Tier 2 |
| GIN-1351 | Qualify roadmap integration coverage: Communication | canceled | P3 integrations | Catalog coverage → channel layer; Tier 2 |
| GIN-1352 | Qualify roadmap integration coverage: Calendar & Scheduling | canceled | P3 integrations | Catalog coverage → calendars Tier 1 deep connectors |
| GIN-1353 | Qualify roadmap integration coverage: File Storage & Documents | canceled | P3 integrations | Catalog coverage → storage mounts Tier 1 (Drive, OneDrive…) |
| GIN-1354 | Qualify roadmap integration coverage: Databases & Data | canceled | P3 integrations | Catalog coverage → Tables; Tier 2 |
| GIN-1355 | Qualify roadmap integration coverage: Search & AI Data | canceled | P3 integrations | Catalog coverage → Tier 2 |
| GIN-1356 | Qualify roadmap integration coverage: Forms & Surveys | canceled | P3 integrations | Catalog coverage → signed webhooks; Tier 2 |
| GIN-1357 | Qualify roadmap integration coverage: Analytics | canceled | P3 integrations | Catalog coverage → Tier 2 |
| GIN-1358 | Qualify roadmap integration coverage: Legal & Contracts | canceled | P3 integrations | Catalog coverage → Tier 2 |
| GIN-1359 | Qualify roadmap integration coverage: Community & Social | canceled | P3 integrations | Catalog coverage → Tier 2 |
| GIN-1360 | Qualify roadmap integration coverage: Automation Orchestrators | canceled | P3 integrations | Catalog coverage → native routines; managed connectors via MCP |
| GIN-1361 | Qualify roadmap integration coverage: Real Estate | canceled | P3 integrations | Catalog coverage → Tier 2 |
| GIN-1362 | Qualify roadmap integration coverage: Healthcare | canceled | P3 integrations | Catalog coverage → Tier 2 |
| GIN-1273 | Qualify package authoring and host conformance against the installed catalog | canceled | P3 integrations | Package system/catalog deleted → Nuclear cleanup; Connections model |
| GIN-161 | Statamic integration | canceled | P3 integrations | Per-app package → Tier 2 generic |
| GIN-170 | Windsor | canceled | P3 integrations | Per-app package → Tier 2 generic |
| GIN-171 | Gcp support | canceled | P3 integrations | Per-app package → Tier 2 / computer CLI |
| GIN-172 | Deepwiki | canceled | P3 integrations | Per-app package → generic MCP |
| GIN-188 | Remotion | canceled | P3 integrations | Per-app package → computer + skill |
| GIN-132 | Translate n8n community nodes into ai tools and opencompany integrations | canceled | P3 integrations | Package system + n8n parity → Tier 2 / managed connectors |
| GIN-197 | Offer integration-supplied skills for mruby Code Mode | canceled | P3 skills | Package-supplied skills → SKILL.md skills/plugin import |
| GIN-1264 | Evaluate extracting reusable MCP transport contracts after safety hardening | canceled | P3 integrations | MCP client rebuilt on laravel/mcp; integrations repo archived |
| GIN-1167 | Separate agent automation registration from explicit activation | canceled | P3 triggers | Automations deleted → Routines (GIN-1825); activation requirement carried in comment |
| GIN-159 | Reconcile automation navigation and task/list-item trigger terminology | canceled | P3 triggers | Automations/ListAutomationRule deleted; Lists → Issues |
| GIN-154 | Later: visual workflow builder over the accepted Ruby/runtime contracts | canceled | Deferred | Visual builder is a non-goal → Routines |
| GIN-1292 | Add optional automation execution-backend seam without requiring Bowerbird | canceled | P3 triggers | Bowerbird-owned execution superseded |
| GIN-1293 | Pair workspace/project identities and project ordered Bowerbird events | canceled | P3 triggers | Bowerbird-owned execution superseded |
| GIN-1294 | Implement app-side durable invocation, trigger and result projection | canceled | P3 triggers | Bowerbird-owned execution superseded |
| GIN-1295 | Project Bowerbird review checkpoints into authorized OpenCompany approvals | canceled | P3 triggers | Bowerbird-owned execution superseded |
| GIN-1296 | Build connector authoring, immutable deployment and capability-diff UX | canceled | P3 triggers | Bowerbird-owned execution superseded |
| GIN-1297 | Qualify shared NocoDB operation/event contracts and connection migration | canceled | P3 triggers | Bowerbird + package contracts superseded |
| GIN-1298 | Qualify connected-mode security, backup, upgrade and disconnect recovery | canceled | P3 triggers | Bowerbird connected mode superseded |

### Re-scoped or moved in the final pass

| Issue | Title | Action | Milestone | Reason |
|---|---|---|---|---|
| GIN-1235 | Port integration bridges and remove legacy executable Code Mode dependencies | kept, re-scoped | Phase 0 mruby | Lua/QuickJS removal kept (nuclear cleanup); sibling-integrations part dropped |
| GIN-1228 | Freeze mruby migration inventory, ownership and compatibility contracts | kept, re-scoped | Phase 0 mruby | Keep inventory and pinning; drop compatibility contracts, package paths and Bowerbird execution ownership |
| GIN-1233 | Migrate automations and Code Console to the shared mruby engine | kept, re-scoped | Phase 0 mruby | Code Console plus the engine for script/hybrid routines; do not migrate Automations as-is |
| GIN-1338 | Publish provider/integration setup and current capability guides | kept, re-scoped | Phase 0 docs | AI providers and Connections only, not per-package integrations |
| GIN-1335 | Publish getting-started, workspace and automation guides | kept, re-scoped | Phase 0 docs | Automation guides become routines/triggers guides |
| GIN-1163 | Harden MCP transport, bounds and provenance-based trust policy | moved Phase 3 integrations → Phase 0 auth, re-scoped | Phase 0 auth | MCP isolation/SSRF is now explicit Phase 0 P0 (pillar 9) |
| GIN-1843 | Fix: cross-workspace credential leak from singleton-bound integration services | created | Phase 0 auth | No existing issue for the P0 credential singleton bug (searched "singleton", "credential", "cross-workspace") |
| GIN-1271 | Complete webhook setup, delivery inspection and safe testing UX | moved (re-scoped) | P3 integrations → Phase 2 · Computer v1 | Tier 2 signed inbound-webhook connection (Phase 2) |
| GIN-1270 | Later: normalized triggers, subscriptions and deduplicated replay | moved (re-scoped) | P3 integrations → P3 triggers | Connection events into event log; event-filter triggers |
| GIN-1241 | Later: thin integration-backed VFS mounts and unified Files locations | moved (re-scoped) | Deferred → P3 integrations, MCP and credentials | Tier 1 storage mounts (Drive is Phase 1 flagship) |
| GIN-139 | Research: A2A protocol support via a Laravel A2A package | moved (re-scoped) | P3 triggers → Phase 5 · Cloud | A2A Agent Cards are Phase 5 outward items |
| GIN-1163 | Harden MCP transport, bounds and provenance-based trust policy | kept (re-scoped comment) | P3 integrations | Tier 2 rebuilt MCP client hardening |
| GIN-1164 | Introduce specific external resources through scoped bindings | kept (re-scoped comment) | P3 integrations | Scoped grants on all Connections |
| GIN-1265 | Later: managed auth configs, connected accounts and token lifecycle | kept (re-scoped comment) | P3 integrations | Generic OAuth broker + vault |
| GIN-1266 | Later: optional external auth-broker bridge with explicit trust and audit | kept (re-scoped comment) | P3 integrations | Optional managed connectors as MCP |
| GIN-1267 | Later: connection links, grants and managed-account control plane | kept (re-scoped comment) | P3 integrations | Connections model grants |
| GIN-1268 | Later: scoped remote execution API and short-lived client tokens | kept (re-scoped comment) | P3 integrations | Public API / OpenCompany as MCP server |
| GIN-1269 | Later: first-party OpenCompany CLI for connections and tool execution | kept (re-scoped comment) | P3 integrations | CLI over public API |
| GIN-1274 | Later: provider reliability, execution logs and trigger health dashboard | kept (re-scoped comment) | P3 integrations | Health from event log + DLQ |
| GIN-187 | Qualify multi-account integration selection and permission gaps | kept (re-scoped comment) | P3 integrations | Multiple personal/shared Connections |
| GIN-1190 | Roadmap: Integrations, MCP and Connect | kept (re-scoped comment) | P3 integrations | Epic now tracks Connections (Tier 1/Tier 2) |
| GIN-192 | Users can add skills to tools in capabilities… | kept (re-scoped comment) | P3 skills | SKILL.md skills + plugin import |
| GIN-1284 | Later: governed agent lanes with modes, wake rules and quiet hours | kept (re-scoped comment) | P3 triggers | Re-scoped as routines |
| GIN-1285 | Later: proactive heartbeat runtime with cheap preflight and wake audit | kept (re-scoped comment) | P3 triggers | Re-scoped as heartbeat/monitor routines |
| GIN-1287 | Later: shared lane operations across Telegram, Discord, Teams and internal chat | kept (re-scoped comment) | P3 triggers | Re-scoped as routines on channel triggers |
| GIN-1191 | Roadmap: External chat and proactive lanes | kept (re-scoped comment) | P1 Telegram/external | Channels kept; lanes → routines |
| GIN-1286 | Later: Slack-native teammate actions, digests and onboarding | kept (re-scoped comment) | P1 Telegram/external | Digests → scheduled routines |
| GIN-146 | Desired output format/destination somehow | kept (re-scoped comment) | P3 triggers | Routine output target + notify policy |
| GIN-1290 | Turn successful tasks into reviewed routines with input/output contracts | kept (re-scoped comment) | P3 triggers | Save task as routine (GIN-1825) |
| GIN-1192 | Roadmap: Automation and multi-agent orchestration | kept (re-scoped comment) | P3 triggers | Automations → Routines |
| GIN-1173 | Execute Artifact HTTP, Automation and agent calls through one endpoint runner | kept (re-scoped comment) | After P4 Artifacts | Automation calls → routine invocations; Lua → mruby |
| GIN-588 | chore: Land or close opencompany#6 (dev→main ecosystem + memory merge) | kept (re-scoped comment) | Ongoing ecosystem | Kept; comment recommends closing #6 as superseded (owner go-ahead) |

### New and updated epics in the final pass


All issues are in P-GIN-4, status Backlog, with no dates, estimates or assignees. They use existing labels: epics carry `OC: Epic` + `product` + an area label + a planning label; nuclear children are labelled `debt`.

There is no Phase 1 or Phase 2 "connections" milestone, and no milestones were created. Phase 1 connection items sit in "Phase 1 · Messenger — Telegram and external surfaces", or in "Phase 1 · UI" for routines from chat. Phase 2 items sit in "Phase 2 · Computer v1". Phase 3 items sit in the matching Phase 3 milestones.

#### GIN-1836 Epic: Connections, events and routines (Phase 0 · run control plane)
- GIN-1839 Workspace event log (envelope, append-only, dead-letter) [P0 run control]
- GIN-1840 Causality chain + depth limit [P0] (blockedBy 1839)
- GIN-1841 Connections model (actions/data/events, personal vs shared, vault + broker) [P0] (blockedBy GIN-1788 vault, GIN-1789 broker)
- GIN-1842 Generic OAuth broker (CIMD → DCR → admin creds) [P0 auth & security]
- GIN-1844 MCP client rebuild on laravel/mcp [P1 external surfaces] (blockedBy 1841, 1842)
- GIN-1845 Connection directory [P1, per design] (blockedBy 1844)
- GIN-1846 API manifests [P2] (blockedBy 1841, 1842)
- GIN-1847 Signed inbound webhooks [P2] (blockedBy 1839, 1841)
- GIN-1848 MCP-resources mount [P3 integrations, per design] (blockedBy 1844)
- GIN-1850 Routine model + triggers + agent routines from chat + Automations migration [P1 UI] (blockedBy 1839, 1840)
- GIN-1851 Script (mruby) and hybrid routines [P2] (blockedBy 1850)
- GIN-1852 Routine governance (mode, notify, debounce, concurrency, quiet hours, budget, approval policy) [P3 triggers] (blockedBy 1850, GIN-1801)
- GIN-1849 Later: managed connectors as MCP (Activepieces/Composio/Pipedream/Zapier) [Deferred] (blockedBy 1844)
- "Save task as routine" was not duplicated: existing GIN-1290 is related instead.
- Related: GIN-1787, 1750, 1746, 1744, 1745, 1190, 1192, 1290

#### GIN-1837 Epic: Deep connectors (Tier 1) (Phase 1 · external surfaces)
The epic description holds the shared criteria: lazy index/fetch into Files and the VFS, VFS write path with revision checks, external ACL ∩ grants, untrusted labelling, events with a cursor.

Each child below except GIN-1858 is blockedBy GIN-1839, GIN-1841 and GIN-1842.
- GIN-1853 Google Drive mount, Phase 1 flagship [P1] (related GIN-1775, GIN-1241)
- GIN-1854 OneDrive/SharePoint [P3 integrations]
- GIN-1855 Gmail [P2] (related GIN-1824 agent inbox)
- GIN-1856 Outlook/IMAP [P3 integrations]: the direction does not phase it, so it is placed with the other Microsoft connectors.
- GIN-1857 Calendars Google/Microsoft/CalDAV [P3 integrations]
- GIN-1858 Later: Flysystem/rclone mounts [P3 integrations, OC: Later] (blockedBy 1853)
- Related: GIN-1836, 1787, 1750, 1746, 1744, 1745, 1775, 1824

#### GIN-1838 Epic: Nuclear cleanup (Phase 0) (Phase 0 · run control plane)
The epic notes that the fix session executes these in code, and that remote operations need the owner's go-ahead.
- GIN-1859 Delete the integration package system (mine Google OAuth/client code first)
- GIN-1860 Archive the integrations repo read-only (back up ~9.9k paths first; owner go-ahead) (blockedBy 1859)
- GIN-1861 Squash migrations into a fresh baseline, no legacy gates incl. mruby
- GIN-1862 Remove behaviour modes in favour of the policy model [P0 auth & security] (related GIN-1796, 1818, 1202, 1205)
- GIN-1863 Delete Workload + old Dashboard; fold Lists into Issues
- GIN-1864 Replace Automations/RunDueAutomations/ListAutomationRule/HasTriggers/IntegrationWebhook/cron with Routines/Triggers/Events (blockedBy 1839)
- GIN-1865 Branch cleanup (Phase 0 → main, tag old main, delete QuickJS/mruby/Lua branches; remote only with go-ahead)
- Related: GIN-1836, 1787, 1750, 1746, 1744, 1745, 1190, 12

#### Other new issues
- GIN-1866 Messenger activity feed from the event log (child of GIN-1744, Phase 1 UI) (blockedBy 1839, 1753, 1755)
- GIN-1867 Phase 2 governance subset: money-movement + password-change hard-deny entries, basic compute cap (child of GIN-1750, Phase 2) (related GIN-1798, 1802)

#### Updated existing issues
- GIN-1745: retitled "Epic: API-first backend and clients (Inertia frozen)". Its context is now pillar 4 and API-first now. The "interim rule" became "Rule: Inertia frozen, no new pages/props", and "timing" was removed from the open questions.
- GIN-1750: context now covers the policy model replacing behaviour modes (GIN-1862), one event log (GIN-1839), GIN-1803 as replay on the log, and the Phase 2 subset (GIN-1867). Pillar references fixed (5, 9).
- GIN-1803: retitled "Run replay and audit trail on the workspace event log". It is now a consumer of GIN-1839 (blockedBy), so there is no separate run log.
- GIN-1767: marked Duplicate of GIN-1863, with a comment.
- GIN-1811: retitled "Decide which Task/list model becomes Issues and plan the Lists → Issues fold". Lists → Issues is recorded as decided, and Calendar points to deep connectors (GIN-1857).
- GIN-1825 and GIN-1826: delivery notes prepended; related to GIN-1850/1851/1852.
- GIN-1788 (vault) and GIN-1789 (broker): moved to Phase 0 · authorization and security (the direction puts the Connections model with vault and broker in Phase 0), with a comment on each.

#### Notes
- Pillar numbers inside older child issues (e.g. "pillar 3" for messenger, "pillar 8" for primitives) were fixed only on GIN-1745, GIN-1750 and GIN-1803; other children still use the pre-2026-11-02-final numbering. The direction doc is authoritative.
- Milestone descriptions were updated afterwards for API-first, Tables and Issues, Phase 2 and the routines milestone.
- The package-era "Qualify roadmap integration coverage" issues (GIN-1342–1362) and GIN-1273 were canceled in the triage pass above.
- Phase 5 outward items are tracked as GIN-1868–1870.

## Authority

`docs/PRODUCT_DIRECTION.md` owns what OpenCompany is becoming and in what order; `docs/CURRENT_STATE.md`
owns implementation truth; `docs/ROADMAP.md` owns priority and follows the direction. Linear is
operational tracking only.

## Milestones → phases

The 15 workstream milestones were **renamed in place** (issue history preserved) and five were added.

| Milestone (2026-11-02) | Former name |
|---|---|
| Phase 0 · Foundation — authorization and security | 01 · Authorization and security |
| Phase 0 · Foundation — run control plane, stabilization and CI | 02 · Runtime stabilization and maintainability |
| Phase 0 · Foundation — mruby-only Code Mode | 03 · mruby-only Code Mode |
| Phase 0 · Foundation — VFS release gates | 04 · VFS correctness and interoperability |
| Phase 0 · Foundation — licensing, docs and product truth | 14 · Product documentation and licensing |
| Phase 1 · Messenger — UI and product UX | 11 · Product UX and QA |
| Phase 1 · Messenger — Telegram, MCP client and Drive | 08 · External chat and proactive lanes |
| Phase 1 · API-first backend and clients | new (pillar 4) |
| Phase 2 · Computer v1 | new |
| Phase 3 · Governance — roles, approvals and hard-deny floor | new |
| Phase 3 · Governance — budgets, audit and observability | 12 · Budgets and observability |
| Phase 3 · Governance — routines, triggers and orchestration | 09 · Automation and multi-agent orchestration |
| Phase 3 · Governance — SKILL.md skills | 06 · Reusable skills |
| Phase 3 · Connections — grants, MCP and deep connectors | 07 · Integrations, MCP and Connect |
| Phase 4 · Work apps and apps platform (renamed from "Work apps — Tables and Issues") | new |
| Phase 4 · Work apps — Docs, memory and knowledge | 05 · Documents, memory and versioned workspace |
| After Phase 4 · Artifacts and Lab | 10 · Artifacts and Lab |
| Phase 5 · OpenCompany Cloud | new |
| Deferred · Modular platform and later bets | 13 · Modular platform and later bets |
| Ongoing · Ecosystem maintenance | 15 · Ecosystem maintenance |

## Triage of existing open issues (first pass)

294 open issues reviewed: 37 moved, 6 canceled, the rest kept in their renamed
milestone. Done, canceled and duplicate issues were not touched. Every moved or canceled issue has a
comment pointing to the direction. Parent links were not changed, so some older epics (GIN-1148,
GIN-1186, GIN-1187) now have children in several milestones.

### Canceled as superseded (never marked completed)

| Issue | Title | From → To | Reason |
|---|---|---|---|
| GIN-142 | Later: Reader module first vertical slice | Deferred modular (old 13) → — | Reader module first slice; Reader-first superseded |
| GIN-1309 | Later: Reader OPML, rules, saved searches, topic feeds and digests | Deferred modular (old 13) → — | Reader-first modular OS superseded |
| GIN-1311 | Later: graduate Forms and webhook inboxes on first-party module foundations | Deferred modular (old 13) → — | Allbase lab module graduation (Forms/webhook inboxes) superseded |
| GIN-1312 | Later: graduate Reports module on first-party module foundations | Deferred modular (old 13) → — | Allbase lab module graduation (Reports) superseded |
| GIN-1313 | Later: graduate Imports and public portals on first-party module foundations | Deferred modular (old 13) → — | Allbase lab module graduation (Imports/portals) superseded |
| GIN-1314 | Later: graduate CRM module on first-party module foundations | Deferred modular (old 13) → — | Allbase lab module graduation (CRM) superseded |

Lua work in scope was already canceled earlier (GIN-189, GIN-180, GIN-193); Lua retirement and the
mruby migration (GIN-12, GIN-13, GIN-1228–1236) stay in Phase 0.

### Moved

| Issue | Title | From → To | Reason |
|---|---|---|---|
| GIN-1665 | Brand kit for OpenCompany before directory and launch submissions | (none) → Phase 0 · Foundation — licensing, docs and product truth | No milestone; launch brand kit with claims checked against what ships = product truth |
| GIN-1628 | chore: Land or close kosmokrator#1 TUI overhaul (open since 2026-04-07) | (none) → Ongoing · Ecosystem maintenance | No milestone; kosmokrator PR housekeeping = ecosystem maintenance |
| GIN-1240 | Expose binary metadata and externally extracted text through governed VFS projections | Phase 0 · Foundation — VFS release gates → Phase 4 · Work apps — Docs, memory and knowledge | Binary/extracted-text projections are Files/Docs knowledge work, not a release gate |
| GIN-1241 | Later: thin integration-backed VFS mounts and unified Files locations | Phase 0 · Foundation — VFS release gates → Deferred · Modular platform and later bets | Later/research integration-backed mounts; not superseded, so deferred |
| GIN-1238 | Define projection round trips and safe concurrent VFS mutations | Phase 0 · Foundation — VFS release gates → Phase 4 · Work apps — Tables and Issues | Record projection round-trips/mutations belong to agent-native Tables and Issues |
| GIN-1227 | Later: qualify explicit model failover policies without replaying unsafe effects | Phase 0 · Foundation — run control plane, stabilization and CI → Deferred · Modular platform and later bets | Explicitly 'Later' model failover; not Phase 0 stabilization |
| GIN-1213 | Later: retention, legal hold, eDiscovery and enterprise controls | Phase 0 · Foundation — authorization and security → Deferred · Modular platform and later bets | Retention/legal hold/eDiscovery enterprise controls; deferred |
| GIN-1211 | Later: custom roles, structured policies and multi-approver governance | Phase 0 · Foundation — authorization and security → Phase 3 · Governance — roles, approvals and hard-deny floor | Custom roles and multi-approver governance = Phase 3 |
| GIN-1212 | Later: SSO, IdP group mapping and SCIM provisioning | Phase 0 · Foundation — authorization and security → Deferred · Modular platform and later bets | SSO/SCIM explicitly a non-goal until it exists; deferred |
| GIN-1208 | Build Security & Access role matrix, effective access and impact previews | Phase 0 · Foundation — authorization and security → Phase 3 · Governance — roles, approvals and hard-deny floor | Role matrix/effective-access UI = Phase 3 roles |
| GIN-1209 | Separate export authority and protect external disclosure of private data | Phase 0 · Foundation — authorization and security → Phase 3 · Governance — roles, approvals and hard-deny floor | Export capabilities/disclosure approvals = Phase 3 roles/approvals; not in P0 list |
| GIN-1205 | Migrate action policies into versioned rows with one runtime evaluator | Phase 0 · Foundation — authorization and security → Phase 3 · Governance — roles, approvals and hard-deny floor | Versioned policy engine = approver policies (Phase 3), not in ROADMAP P0 list |
| GIN-1210 | Introduce explicit external principals and owner-attributed service identities | Phase 0 · Foundation — authorization and security → Phase 3 · Governance — roles, approvals and hard-deny floor | Later/research service identities = Phase 3 per-agent identity/roles |
| GIN-1162 | VFS: Design historical reads and pinned snapshot views | Phase 0 · Foundation — VFS release gates → Deferred · Modular platform and later bets | Historical/snapshot reads explicitly later, not needed for core VFS release |
| GIN-1161 | VFS: Plan and implement /artifacts over canonical artifact records | Phase 0 · Foundation — VFS release gates → After Phase 4 · Artifacts and Lab | /artifacts projection depends on artifact domain; artifacts come after Phase 4 |
| GIN-1160 | VFS: Plan and implement a governed /skills mount | Phase 0 · Foundation — VFS release gates → Phase 3 · Governance — SKILL.md skills | /skills namespace depends on SKILL.md skill domain; not a VFS release gate |
| GIN-162 | Research: Mine awesome-openclaw-usecases for workspace pack and template ideas | Phase 0 · Foundation — licensing, docs and product truth → Deferred · Modular platform and later bets | Workspace packs/templates research; packs are deferred (OpenClaw use-case mining, not marketing) |
| GIN-143 | Approvals need context and reasons, and also why/improvements etc. | Phase 0 · Foundation — authorization and security → Phase 3 · Governance — roles, approvals and hard-deny floor | Approval context/reasons = Phase 3 approvals (approval cards state what/why) |
| GIN-140 | Approvals can also be recommended manual actions | Phase 0 · Foundation — authorization and security → Phase 3 · Governance — roles, approvals and hard-deny floor | Approval-as-recommended-manual-action = Phase 3 approvals |
| GIN-138 | File disk with perms, optinal shareability | Phase 0 · Foundation — authorization and security → Phase 4 · Work apps — Docs, memory and knowledge | File permissions and share links = Files/Docs work app |
| GIN-135 | Research: Review NanoClaw (container-isolated OpenClaw alternative) for OpenCompany patterns | Phase 0 · Foundation — licensing, docs and product truth → Phase 2 · Computer v1 | NanoClaw container-isolation research informs agent computer design |
| GIN-133 | Password manager tool | Phase 0 · Foundation — authorization and security → Phase 2 · Computer v1 | Password manager = credential broker / secret injection (Phase 2) |
| GIN-148 | Tables also embeddings | P4 Docs/memory (old 05) → Phase 4 · Work apps — Tables and Issues | Tables-related |
| GIN-202 | Offer paid proxy for embedding models/ocr servers etc. | Deferred modular (old 13) → Phase 5 · OpenCompany Cloud | Paid hosted proxy is a Cloud offering |
| GIN-208 | Later: managed/self-hosted browser providers, recording and lifecycle | Deferred modular (old 13) → Phase 2 · Computer v1 | Managed/self-hosted browser providers, recording, remote takeover |
| GIN-1305 | Later: app-owned isolated browser sessions and human takeover | Deferred modular (old 13) → Phase 2 · Computer v1 | Isolated browser sessions + human takeover = computer browser/takeover |
| GIN-1317 | Later: managed-cloud isolation, residency and multi-region recovery requirements | Deferred modular (old 13) → Phase 5 · OpenCompany Cloud | Managed-cloud isolation/residency/multi-region |
| GIN-1284 | Later: governed agent lanes with modes, wake rules and quiet hours | P1 Telegram/external (old 08) → Phase 3 · Governance — triggers, heartbeats and orchestration | Proactive lanes/wake rules |
| GIN-1285 | Later: proactive heartbeat runtime with cheap preflight and wake audit | P1 Telegram/external (old 08) → Phase 3 · Governance — triggers, heartbeats and orchestration | Proactive heartbeat runtime |
| GIN-1287 | Later: shared lane operations across Telegram, Discord, Teams and internal chat | P1 Telegram/external (old 08) → Phase 3 · Governance — triggers, heartbeats and orchestration | Cross-channel lane operations (wake presets, quiet hours) |
| GIN-1151 | Fix: Add reproducible backend, frontend and security CI gates | P1 UI (old 11) → Phase 0 · Foundation — run control plane, stabilization and CI | CI gates |
| GIN-1325 | Complete deterministic Pest browser setup while preserving Dusk coverage | P1 UI (old 11) → Phase 0 · Foundation — run control plane, stabilization and CI | Browser test infrastructure |
| GIN-1326 | Generate route-to-test coverage and reconcile the historical feature map | P1 UI (old 11) → Phase 0 · Foundation — run control plane, stabilization and CI | Route-to-test coverage inventory |
| GIN-1327 | Close core resource API and workspace-ownership regression coverage gaps | P1 UI (old 11) → Phase 0 · Foundation — run control plane, stabilization and CI | Regression test coverage gaps (two-workspace denial matrix) |
| GIN-1329 | Qualify approval-to-resume and tool execution integration contracts | P1 UI (old 11) → Phase 0 · Foundation — run control plane, stabilization and CI | Approval-to-resume / run control-plane qualification tests |
| GIN-196 | Later: policy-filtered hosted MCP for workspace resources and integration tools | P3 integrations/MCP (old 07) → Phase 5 · OpenCompany Cloud | Hosted MCP |
| GIN-154 | Later: visual workflow builder over the accepted Ruby/runtime contracts | P3 triggers (old 09) → Deferred · Modular platform and later bets | Visual workflow builder is a later bet |

## New epics and child issues (first pass)

Project P-GIN-4, team GIN. All issues are in Backlog with no dates, estimates, assignees or cycles. Epics carry the labels `OC: Epic` + `product` + an area label + a planning label. Children carry an area label + `OC: Ready`/`OC: Research`/`OC: Later` + `Feature`/`Improvement`/`spike`. No new labels were created.

### 1. GIN-1744 Epic: Messenger UI — chat as home with AI teammates (Phase 1 · Messenger — UI and product UX)
- GIN-1759 Chat home + teammate rail (blockedBy GIN-1753)
- GIN-1760 DMs and multi-agent group chats (blockedBy GIN-1146, GIN-1198)
- GIN-1761 Inline task/record cards (blockedBy GIN-1755)
- GIN-1762 Inline approval cards (what/why/where-from, one tap web/mobile/Telegram) (blockedBy GIN-1755, GIN-1202)
- GIN-1763 Live run stream in chat (blockedBy GIN-1755, GIN-1214)
- GIN-1764 Built-in apps as side panels (blockedBy GIN-1753)
- GIN-1765 Mobile-friendly messenger
- GIN-1766 Computer panel slot, agent mode indicator (Computer vs VFS-only), computerless "recommend a computer" state. The panel appears only for agents that have a computer.
- GIN-1767 Demote Dashboard/Workload
- Related: GIN-1745 (API-first epic), GIN-1193, GIN-1191

### 2. GIN-1745 Epic: API-first backend and client split (Phase 1 · API-first backend and client split)
The epic description includes the interim rule: new UI goes through the API and the realtime stream, not new Inertia props.
- GIN-1753 Versioned public API + OpenAPI
- GIN-1754 Native-app auth (tokens/OAuth)
- GIN-1755 Realtime event stream (WebSocket/SSE): blocks GIN-1761, 1762, 1763, 1783, 1784
- GIN-1756 Push notifications (approvals, mentions)
- GIN-1757 Web SPA extraction
- GIN-1758 Spike: mobile and desktop clients
- Related: GIN-1744, GIN-1336, GIN-1206, GIN-1221

### 3. GIN-1746 Epic: ComputerProvider contract, per-agent computers and computerless mode (Phase 2 · Computer v1)
The description covers the VFS rule (complete fallback without a computer; never bypassed when a computer exists) and per-agent computer choice.
- GIN-1768 ComputerProvider contract on Bowerbird Workspace API v1alpha + capability discovery + conformance suite
- GIN-1769 Config-driven provider registry (blockedBy 1768)
- GIN-1770 Computerless guarantee: VFS as complete fallback, clean tool degradation, no-provider CI regression suite
- GIN-1771 Per-agent computer attachment: attach/detach, mode switch preserving identity, memory and VFS grants (blockedBy 1768)
- GIN-1772 One computer per agent by default; warned opt-in shared team computer (blockedBy 1768)
- GIN-1773 Idle stop, snapshots, resume (blockedBy 1768)
- GIN-1774 Egress allowlist (blockedBy 1768)
- GIN-1775 Company data into the computer only via the VFS (mount/sync/CLI bridge), with no bypass. Writes go through VFS write policy, approvals and audit. (blockedBy 1768; label OC: vfs)
- GIN-1776 Recommend a computer in onboarding, agent creation and the agent profile
- Related: GIN-1148, GIN-1305, GIN-208

### 4. GIN-1747 Epic: Computer providers (Phase 2 · Computer v1)
All children are blockedBy GIN-1768.
- GIN-1777 Local Docker provider (self-host default; Firecracker later)
- GIN-1778 boat provider (first hosted partner)
- GIN-1779 E2B provider (OC: Later)
- GIN-1780 Daytona provider (OC: Later)
- GIN-1781 Later: Bowerbird sandboxes provider. Milestone Phase 5 · OpenCompany Cloud. blockedBy GIN-1768, BWB-218, BWB-228.
- GIN-1782 Later: bring-your-own SSH host (Phase 2 milestone)

### 5. GIN-1748 Epic: Takeover viewer (Phase 2 · Computer v1)
- GIN-1783 Live view: terminal/browser/screen stream, separate view vs control permissions (blockedBy 1768, 1755)
- GIN-1784 Take over / hand back with takeover request cards (blockedBy 1783, 1768, 1755)
- GIN-1785 Audit takeover sessions
- GIN-1786 Spike: login capture, takeover sign-in vs secrets form (open question)
- Related: GIN-1746, GIN-1305, GIN-1744

### 6. GIN-1749 Epic: Credential broker (Phase 2 · Computer v1)
- GIN-1789 Broker injection at the network/tool boundary with placeholder tokens (blockedBy GIN-1788 vault)
- GIN-1790 Per-agent least-privilege credential grants
- GIN-1791 Audit every credential use, including computer-originated egress and browser fills
- GIN-1792 Rotation and revocation
- Related: GIN-1746, GIN-1265, GIN-1266, GIN-133

### 7. GIN-1750 Epic: Company governance (Phase 3 · Governance — roles, approvals and hard-deny floor)
The description maps each requirement to an existing or new issue and includes an audit criterion for computer-originated VFS access.
- GIN-1796 Approver policies + no self-approval at any level [roles] (blockedBy GIN-1197, GIN-1202, GIN-1205)
- GIN-1797 Computer-access policy: who may attach a computer to which agent [roles] (blockedBy GIN-1197, GIN-1796)
- GIN-1798 Hard-deny floor + mandatory handoffs [roles] (blockedBy GIN-1205)
- GIN-1799 Trigger is not consent [roles] (blockedBy GIN-1202)
- GIN-1800 Untrusted-content runs lose auto-approval [roles] (blockedBy GIN-1205)
- GIN-1801 Read-only run modes at tool registration [roles] (blockedBy GIN-1222)
- GIN-1802 Budgets and spend caps that pause work (tokens + compute + money) [budgets] (blockedBy GIN-1299)
- GIN-1803 Durable replayable run event log [budgets] (blockedBy GIN-1214)
- GIN-1804 Audit view "who allowed what, at what cost" [budgets] (blockedBy GIN-1204, GIN-1803)
- GIN-1805 SKILL.md import with security scanning [skills] (blockedBy GIN-1259, GIN-1261)
- Existing issues linked as related instead of duplicated: GIN-1186, GIN-1202 (no high-risk self-approval), GIN-1211 (custom roles/multi-approver), GIN-1197, GIN-1205, GIN-1204, GIN-1194 + GIN-1299 (budgets), GIN-1189 + GIN-1259/1261/1263 (skills registry/approval/import-export), GIN-1285 + GIN-1284 (heartbeats/lanes), GIN-1214, GIN-1746, GIN-1787

### 8. GIN-1751 Epic: Agent-native Tables, Issues and Docs (Phase 4 · Work apps — Tables and Issues)
- GIN-1806 Mentionable records
- GIN-1807 Assign records to agents
- GIN-1808 Record-change triggers (blockedBy GIN-1799 trigger-is-not-consent)
- GIN-1809 VFS projections for Tables/Issues/Docs (OC: vfs)
- GIN-1810 Tables/Issues/Docs as messenger side panels (blockedBy GIN-1764)
- GIN-1811 Spike: freeze/merge Lists, Calendar, Workload
- Related: GIN-1188, GIN-1148, GIN-159

### 9. GIN-1752 Epic: OpenCompany Cloud (Phase 5 · OpenCompany Cloud)
The description states that pricing and packaging are an open question. No pricing is set.
- GIN-1812 Hosted tenancy and provisioning
- GIN-1813 Managed computers on boat (blockedBy GIN-1778)
- GIN-1814 Boat → Bowerbird sandbox migration path (blockedBy GIN-1781)
- GIN-1815 Metering and billing on the budgets ledger (blockedBy GIN-1802, GIN-1299)
- GIN-1816 Self-host parity guard (no Cloud-only forks)
- GIN-1817 Pilot workspaces
- Related: GIN-1317, GIN-196, GIN-1747

### 10. GIN-1787 Epic: Agent primitives (Phase 2 · Computer v1), per pillar 6
The source is `docs/planning/agent-primitives-catalog.md`. Each child cites its catalog section.

Phase 2 (Computer v1):
- GIN-1788 Vault
- GIN-1793 Secure form (blockedBy 1788)
- GIN-1794 Persistent per-agent browser profile (blockedBy 1768)
- GIN-1795 Approval-gated, time-limited, authenticated ports (blockedBy 1768)

Phase 3:
- GIN-1818 Scoped approval grants [roles] (blockedBy GIN-1202)
- GIN-1819 Non-blocking approvals [roles] (blockedBy GIN-1214)
- GIN-1820 Separate pause/stop/cancel-schedule/delete [roles]
- GIN-1821 Kill switch [roles] (related to 1820)
- GIN-1822 1Password connector [integrations/MCP/credentials] (blockedBy 1788, 1818)
- GIN-1823 Bitwarden/Vaultwarden connector [integrations] (blockedBy 1822, 1788, 1818)
- GIN-1824 Agent inbox and OTPs [integrations]
- GIN-1825 Routines [triggers]
- GIN-1826 Daily brief and monitors [triggers] (blockedBy GIN-1801 read-only modes)
- GIN-1827 Memory controls [Phase 4 · Docs, memory and knowledge milestone, where memory work lives]

Later (milestone Deferred · Modular platform and later bets; each says "Later" in its title and description):
- GIN-1828 Single-use virtual cards
- GIN-1829 Phone number (blockedBy 1824)
- GIN-1830 Teach by demonstration (blockedBy 1784)
- GIN-1831 Local computer (blockedBy GIN-1782)
- GIN-1832 CSV vault import (blockedBy 1788)

Primitives tracked in other epics, related here instead of duplicated:
- Credential broker → GIN-1789
- Live view → GIN-1783
- Takeover → GIN-1784
- Hard-deny floor → GIN-1798
- Spend caps → GIN-1802
- Snapshots → GIN-1773
- Approval cards → GIN-1762
- Also related: GIN-1790/1791/1792, GIN-1786

### Direction clarifications applied
- **VFS rule** is in the GIN-1746 description and in GIN-1770 (complete fallback workspace) and GIN-1775 (no bypass; writes go through VFS policy, approvals and audit). GIN-1750 and GIN-1804 have audit criteria for computer-originated access, and GIN-1791 covers it for credential use. `docs/architecture/vfs-agent-friendliness-audit-2026-11.md` is cited in the VFS-related issues.
- **Per-agent computer choice:**
  - GIN-1771: attach/detach and mode switch.
  - GIN-1797: governance policy, related to GIN-1746.
  - GIN-1766: messenger mode indicator; panel shown only for agents with a computer.
  - GIN-1776: onboarding recommendation.

### Notes
- Catalog items not tracked separately: ask-vs-notify, file handoff, action items/notes. This is noted in the GIN-1787 description.
- The design docs are referenced by path. Some of them may not exist yet.


## Open triage calls

- GIN-1306 (user-owned browser extension and computer use) kept in Deferred; could move to Phase 2.
- GIN-1265/1267/1268 (Connect accounts, links, remote execution API) kept in Phase 3 integrations
  because their criteria include self-hosting; move to Phase 5 if Connect is hosted-only.
- GIN-1212 (SSO/SCIM) and GIN-1213 (retention, legal hold) deferred rather than Phase 5, since the
  direction forbids such claims before they exist.
- GIN-1209/1210 moved from Phase 0 to Phase 3 as they are not on the ROADMAP P0 list.
- GIN-1665 (brand kit) description references another project's kit and may need rewording.
- Memory controls (GIN-1827) sit in Phase 4 Docs/memory rather than Phase 3.
