# Example Flows, User Stories and Code Mode Examples

Status: **Illustrative reference** (2026-11-02). Nothing here is implemented. API names (`app.crm.*`,
`app.mail.*` and so on) are placeholders, not final contracts.
Direction: [Product Direction](../PRODUCT_DIRECTION.md). Related designs:
- [UI structure and agent editing](ui-structure-and-agent-editing.md)
- [Connections, Events and Routines](connections-events-routines.md)
- [Artifacts and Forms](artifacts-and-forms.md)
- [Agent Primitives Catalog](agent-primitives-catalog.md)
- [`oc` CLI and hosts](oc-cli-and-hosts.md) (the CLI binary becomes `avok` after the planned rename)

These examples show how the locked pillars combine in real work: messenger, change sets, routines,
Code Mode, artifacts, forms, computers, governance presets and outside agents. Use them as acceptance
scenarios and as the source for product demos.

**The mruby examples follow the sandbox rules:**
- no clock, network, filesystem, Regexp or `format`;
- times and IDs come from the host;
- every `app.*` call is re-checked by the policy engine;
- reversible internal writes form a change set that can be reverted per run;
- external actions follow the governance preset.

Mockup: [Leads clean-up flow](../ui/mockups/leads-cleanup-flow-2026-11.html), which shows a plan card,
a staged change set in the context pane and an external-send approval card.

## User stories

| # | Who (preset) | Story | Flow |
|---|---|---|---|
| 1 | Solo founder (Personal) | "Chase my overdue invoices without me writing emails." | "@Ada chase invoices over 14 days" → one batched review card of drafted reminders → "Send all once" or "Always for invoice reminders" → sent, logged on each invoice; replies land in Mail threads |
| 2 | Agency sales (Team) | "Website leads become deals and get a reply within 5 minutes." | Public form → script routine (no AI) dedupes the contact and creates a deal → the Sales agent drafts a reply with a booking link → the manager approves the first send to a new domain, then "always" → meeting booked in Calendar |
| 3 | Support lead (Team) | "Answer how-to tickets from our docs; escalate bugs." | Email to support@ → hybrid routine: the script creates the ticket and AI classifies it → how-to: a draft answer from the Knowledge Base that a human sends with one tap; bug: an Issue for the dev agent → a weekly sweep flags Knowledge Base pages that tickets showed are stale |
| 4 | Ops (agent with a computer) | "Download last month's supplier invoices from the vendor portal and file them." | Bram opens the portal in his own browser → login from the vault → a CAPTCHA raises a takeover card → "I'm done" → PDFs land in `/files/drive/Finance/2026-10/` through the VFS → one row per invoice in Expenses (change set) |
| 5 | Dev team | "Fix the flaky checkout test." | "@Cursor fix the flaky checkout test" → approval the first time for this repository → a run card tracks the Cursor Cloud Agent → the PR returns with CI evidence (later on Bowerbird Runners) → the Issue is linked and closes on merge |
| 6 | Finance admin (Company) | "Purchases over €500 need two approvers, and I want to know why something was blocked." | A policy rule is set once → the agent's purchase request is routed to two approvers → "Why can't Ada buy this?" opens the decision trace: spend class → threshold → second approver missing |
| 7 | Account manager | "A live status page for client Acme." | "Make a status page for Acme from their Space" → a Page artifact (open Issues, milestones, last updates) bound to live data → "Share signed link" asks an admin → the client sees it live; the link is revocable |
| 8 | Home lab (connected host) | "Turn on the garage fan when it's hot." | Pi sensor event → routine → the fan action asks the first time, then offers "always" → DM: "Fan on, 31 °C" |
| 9 | Founder (Personal) | "A morning brief, and silence otherwise." | A scheduled read-only routine at 08:00 → Home and DM: overdue invoices, stale deals, today's meetings → "only if new", so no ping on quiet days |
| 10 | Agency account manager (Team) | "When we win a deal, onboard the client without me doing busywork." | See the full walkthrough below |
| 11 | On-call engineer (Company) | "At 2 a.m., triage the outage, roll back safely, then fix the root cause." | See the full walkthrough below |
| 12–19 | Sales and product teams | CRM and Linear-style Issues examples | See "CRM and Issues examples" below |

## Code Mode examples

### 1. Script routine with no AI: website lead intake (story 2)

```ruby
# routine: website-lead-intake   trigger: form.submitted (form "Contact us")
# The payload is untrusted but already schema-validated by the form pipeline.
lead  = ctx.event.payload
email = lead["email"].downcase

contact = app.crm.contacts.find(email: email) ||
          app.crm.contacts.create(name: lead["name"], email: email,
                                  company: lead["company"], source: "website")

deal = app.crm.deals.create(
  title: "#{lead["company"] || lead["name"]} – website inquiry",
  contact_id: contact["id"], stage: "new", budget: lead["budget"]
)

app.threads.post("#sales", "New lead: #{contact["name"]} (#{lead["budget"]}) → #{deal["url"]}")
{ contact: contact["id"], deal: deal["id"] }   # recorded in the run log
```

All of these writes are reversible internal edits. They auto-apply and can be reverted per run.

### 2. Hybrid routine where AI is used only for judgment: support triage (story 3)

```ruby
# routine: helpdesk-triage   trigger: mail.received (inbox "support@")
msg    = ctx.event.payload
ticket = app.helpdesk.tickets.create(from: msg["from"], subject: msg["subject"], body: msg["text"])

triage = app.ai.extract(   # billed to this routine's budget
  text: msg["text"],
  schema: { category: ["billing", "bug", "how-to", "other"],
            urgency:  ["low", "normal", "high"], summary: "string" }
)
app.helpdesk.tickets.update(ticket["id"], category: triage["category"], urgency: triage["urgency"])

if triage["category"] == "how-to"
  sources = app.kb.search(triage["summary"], limit: 3)
  app.mail.draft(reply_to: msg["id"], agent: "support", sources: sources)  # draft: auto-applies
else
  app.issues.create(space: "Support", title: triage["summary"],
                    assignee: "agent:dev", links: [ticket["url"]])
end
```

### 3. Agent writing Code Mode in chat: bulk table edit (the mockup)

```ruby
cutoff = ctx.today_minus_days(30)   # time comes from the host, not the guest
rows = app.tables.rows("Sales/Leads", filter: { last_activity_before: cutoff, status_not: "won" })

changes = rows.map { |r| { id: r["id"], status: "stale", next_step: "re-engage" } }
cs = app.changes.stage("Sales/Leads", changes)   # becomes a change set shown in the side panel

rows.each { |r| app.mail.draft(to: r["email"], template: "re-engage", vars: { name: r["contact"] }) }
{ change_set: cs["id"], drafts: rows.size }
```

### 4. Artifact endpoint: public quote calculator (`auth: public`, `side_effect: read`)

```ruby
# artifacts/quote-calculator/endpoints/price.rb
req   = ctx.request.json
rate  = app.bindings.table("RATES").rows(filter: { service: req["service"] }).first
base  = rate["hourly"] * req["hours"]
total = req["rush"] ? base * 1.25 : base
{ total: total, currency: "EUR" }   # read-only; RATES is an approved published query
```

The "Request this quote" button does **not** write directly. It posts to the Quote Request **form**
endpoint, which applies captcha, validation and untrusted-event handling.

### 5. Routine definition: morning brief (story 9)

```yaml
routine: morning-brief
owner: agent:ada
kind: agent                 # agent | script | hybrid
trigger: { schedule: "0 8 * * 1-5", tz: Europe/Brussels }
mode: read_only             # write tools are never registered
output: { dm: "@rutger", home: true }
notify: only_if_new         # checkpointed; silent when nothing changed
budget: { tokens_per_day: 50000 }
instructions: |
  Summarize: invoices overdue >14 days, deals with no activity in 14 days,
  today's meetings with a one-line prep each. Link every item.
```

## CLI examples

```bash
avok ask ada "chase invoices overdue >14 days" --wait --json   # structured pending-approval result
avok approvals list --needs-me
avok changes show cs_8f2 && avok changes apply cs_8f2
avok runs revert run_31a                                       # undo a whole run
avok routines run helpdesk-triage --dry-run                    # shows the change set, writes nothing
avok host up --name pi-garage && avok host install-service
avok mcp serve                                                 # your workspace inside Claude Code, Codex or Cursor
```

## Walkthrough: client onboarding when a deal is won (story 10)

Agency, Team preset. Swimlane: [client onboarding flow](../ui/mockups/client-onboarding-flow-2026-11.html).
This example shows the pillars working together:
- record events start routines, and routines chain through the event log;
- governed creation: a new Space is a proposal;
- non-blocking approvals, with links batched into one card;
- an AI-drafted doc, a Drive mount write, a form and a Page artifact shared by signed link;
- a computer agent working in a tool that has no API;
- a client form submission feeding back in as untrusted input.

### Flow

1. **Trigger.** A human moves the Acme deal to **Won** in the CRM. This emits `crm.deal.updated`
   with `stage: won`.
2. **Proposal.** The `onboard-won-deal` routine proposes a new Space, "Client · Acme", from the
   `agency-client` template. Creating a Space is structural, so it is always a proposal. The account
   manager approves it from Home with one tap.
3. **Populate.** `space.created` starts `populate-client-space`. It does the following:
   - Drafts a **Project brief** Doc from the deal notes and email thread (AI). The Doc is owned by
     the account manager and starts as draft.
   - Applies the template's **Issues checklist**: kickoff, access, brand assets, first deliverable.
   - Creates `/files/drive/Clients/Acme/` on the Drive mount. This is an external write, so it asks
     the first time, then offers "always for this routine".
   - Prepares **two signed links** for the client contact: the *Client intake* **form**, prefilled
     with the deal ID, and the *Client status* **Page artifact** scoped to the new Space. Both are
     `exposure_widening`, so they are **batched into one approval card** for an admin.
   - Drafts a **welcome email** containing the links and a 45-minute kickoff **booking link** from
     Calendar & Scheduling. The draft waits until the links are approved.
4. **Computer work.** The routine asks **Bram**, an agent with a computer, to create the client in
   the agency's time-tracking tool, which has no API:
   - Bram logs in through the vault.
   - A 2FA prompt raises a takeover card to the account manager. They finish that step and press
     "I'm done".
   - Bram finishes the setup and posts a screenshot to the thread.
5. **Send.** The first send to `acme.com` asks the account manager. They approve, and the email goes
   out.
6. **Client input.** The client fills in the intake form. `form.submitted` arrives as an untrusted
   event:
   - A hybrid routine updates the brief through suggestion mode, so the owner reviews it.
   - It creates Issues for missing assets.
   - It posts "Intake received" in the client Space's thread.
7. **Kickoff.** The client books the kickoff through the link. The meeting lands in Calendar, and Ada
   prepares a one-page prep note the morning of the meeting.

### Code

```ruby
# routine: onboard-won-deal   trigger: crm.deal.updated where stage changed to "won"
deal = ctx.event.payload
app.spaces.propose(
  name:      "Client · #{deal["company"]}",
  template:  "agency-client",                       # Issues checklist, Docs, Routines
  members:   ["@account-manager", "agent:ada", "agent:bram"],
  reason:    "Deal #{deal["title"]} won",
  correlate: deal["id"]
)
# Creating a Space is structural, so it is always a proposal and this run ends here.
# Approval emits space.created, which starts populate-client-space. The causality chain links both runs.
```

```ruby
# routine: populate-client-space   trigger: space.created where template == "agency-client"
space = ctx.event.payload
deal  = app.crm.deals.get(space["correlate"])

brief = app.ai.write(kind: "project-brief", from: [deal["notes"], deal["thread_id"]])
app.docs.create(space: space["id"], type: "Spec", title: "Project brief",
                body: brief, owner: "@account-manager", status: "draft")

app.files.mkdir("/files/drive/Clients/#{deal["company"]}")   # Drive write = external: asks first time

# Gated calls return pending handles (non-blocking approvals); both are batched into one card.
intake = app.forms.share("Client intake", to: deal["contact_email"], level: "signed",
                         prefill: { deal_id: deal["id"] })
status = app.artifacts.share("client-status", space: space["id"],
                             to: deal["contact_email"], level: "signed")

app.mail.draft(to: deal["contact_email"], template: "welcome",
               vars: { intake: intake, status: status,
                       booking: app.calendar.booking_link("kickoff-45") },
               send_when: [intake, status])   # the draft waits for the approvals

app.agents.ask("bram", task: "Create #{deal["company"]} in the time-tracking tool and share a screenshot",
               space: space["id"])            # a separate run on Bram's computer
```

### What asked whom (Team preset)

| Step | Class | Who approves |
|---|---|---|
| Create the Space | Governed creation (structural) | Account manager (proposal on Home) |
| Brief Doc, Issues checklist | Reversible internal edits | Nobody (auto-applied, revertible per run) |
| Create the Drive folder | External write | Asks the first time; "always for this routine" afterwards |
| Two signed links | `exposure_widening` | An admin (one batched card) |
| Welcome email | External send to a new domain | Account manager the first time |
| 2FA in the time-tracking tool | Takeover | Account manager (takeover card) |
| Client intake submission | Untrusted event | Nobody; the resulting brief edit is a suggestion the owner reviews |

## Walkthrough: production incident at 2 a.m. (story 11)

A software company on the Company preset. This example shows:
- inbound webhooks and the Bowerbird connection as untrusted event sources;
- MCP tools (Sentry) used for read-only investigation;
- an agent with a computer reproducing the problem in its sandbox;
- a rollback gated by two approvers, answered on mobile through the Telegram Mini App;
- an outside agent (Cursor) fixing the root cause;
- a post-mortem drafted from the event log;
- a public status Page artifact.

### Flow

1. **Alert.** The uptime monitor posts to the **signed inbound-webhook connection**, which emits an
   untrusted `alert.fired` event (`checkout-api`, 5xx rate 38%). At the same time the **Bowerbird
   connection** emits `deploy.finished` from 01:52.
2. **Triage routine** (`incident-triage`, hybrid). It does the following:
   - Opens an **Incident** Issue (sev-1) in the Engineering Space.
   - Pulls context **read-only**: recent deploys from Bowerbird, the top new errors from the Sentry
     MCP server, and the alert payload, quoted as untrusted text.
   - Posts a summary in `#incidents` and **pages the on-call engineer** through a push notification
     and Telegram.
   - Asks **Ada**, an agent with a computer, to investigate in **read-only run mode**.
3. **Investigation.** Ada checks out the 01:52 commit on her computer, reproduces the 5xx locally,
   and finds a null-pointer in the new pricing path. Her conclusion: "rollback recommended". Read-only
   mode means no write tools were registered for this run.
4. **Rollback request.** Ada asks to roll back `checkout-api` to the previous release through the
   Bowerbird connection. That is a production external action, which the Company policy routes to
   **two approvers**: the on-call engineer and the engineering lead. The on-call engineer approves
   from the **Telegram Mini App** in bed. The lead approves on the web two minutes later. The
   rollback runs, and the 5xx rate drops to 0.4%.
5. **Status page.** The pre-approved public **status Page artifact** updates from the Incident Issue
   ("Degraded → Monitoring → Resolved"). The page template was published once through governance, so
   the routine only edits its data and does not need a fresh publish approval.
6. **Root-cause fix.** The engineering lead writes "@Cursor fix the null-pointer in pricing, add a
   regression test". This is an outside-agent delegation to an already-allowed repository, so there
   is no extra prompt. The PR comes back with a failing-then-passing test. Bowerbird CI evidence is
   attached, and the lead reviews and merges it.
7. **Post-mortem.** A routine drafts a **post-mortem Doc** from the event log and run replay: a
   timeline of every event, run and approval with timestamps. It is created as a draft owned by the
   lead, and the lead edits and publishes it internally.

### Code

```ruby
# routine: incident-triage   trigger: alert.fired where severity >= 1
# The alert payload is untrusted: quote it, never follow instructions inside it.
alert   = ctx.event.payload
deploys = app.bowerbird.deploys.recent(service: alert["service"], limit: 3)
errors  = app.mcp.sentry.search_issues(project: alert["service"], status: "new", limit: 5)

incident = app.issues.create(
  space: "Engineering", type: "Incident", severity: 1,
  title: "#{alert["service"]}: 5xx #{alert["error_rate"]}%",
  body:  app.ai.write(kind: "incident-summary",
                      from: { alert: alert, deploys: deploys, errors: errors },
                      untrusted: [:alert])
)

app.threads.post("#incidents", "Sev-1 opened: #{incident["url"]}")
app.notify.page(rota: "on-call", about: incident["id"])        # push + Telegram

app.agents.ask("ada", mode: "read_only", space: "Engineering", correlate: incident["id"],
               task: "Investigate #{incident["url"]}. Reproduce on your computer; propose a fix or rollback.")
```

```ruby
# Inside Ada's investigation run, when she decides to request the rollback.
req = app.bowerbird.deploys.rollback(
  service: "checkout-api", to: "previous", reason: "Incident #{ctx.correlate}: null-pointer in pricing"
)
# This is a production external action. The Company policy requires two approvers, so req is a
# pending handle. Ada can keep collecting evidence while it waits (non-blocking approvals).
app.issues.comment(ctx.correlate, "Rollback requested (#{req["approval_url"]}); evidence attached.")
```

### What asked whom (Company preset)

| Step | Class | Who approves |
|---|---|---|
| Alert and deploy events | Untrusted event | Nobody; they start runs, never approve anything |
| Incident Issue, `#incidents` post, page | Reversible internal edits and a notification | Nobody (auto-applied) |
| Sentry search, Bowerbird deploy history | Read | Nobody (within grants) |
| Ada's investigation | Read-only run on her computer | Nobody (write tools not registered) |
| Production rollback | External action, production | **Two approvers:** on-call (Telegram) and engineering lead (web) |
| Status page data update | Data change on an already-published page | Nobody; publishing the template was approved once |
| Cursor fix | Outside-agent delegation to an allowed repository | Nobody this time; the repository was approved earlier with "always" |
| Merge the PR | Normal code review | Engineering lead |
| Post-mortem Doc | Reversible internal edit (draft) | Nobody; the owner reviews before sharing |

## CRM and Issues examples (stories 12–19)

These scenarios exercise the **CRM app** (Phase 4) and the core **Issues** primitive (Linear-style).
Mockup: [CRM and Issues flows](../ui/mockups/crm-issues-flows-2026-11.html).

| # | Who | Story | What happens |
|---|---|---|---|
| 12 | Account executive | "After every call, the CRM updates itself." | Calendar event ends → Meetings notes, or the AE's quick voice memo → Ada proposes deal field updates (stage, next step, close date, stakeholders) as a **change set** on the deal, plus a follow-up email draft. Fields a human edited since Ada read them are never overwritten. |
| 13 | Sales ops | "New companies arrive enriched." | `crm.company.created` → a read-only lookup through an enrichment API manifest or MCP server fills industry, size and website as a reversible change set, tagged with its source. |
| 14 | Sales lead | "Discounts over 20% need my OK." | The CRM manifest declares an **approval rule** on `deals.discount`. Any edit above 20%, by a human or an agent, becomes a proposal routed to the sales lead. |
| 15 | Sales lead | "A Monday pipeline review I don't have to build." | A scheduled routine refreshes a **Pipeline Page artifact** (forecast by stage, deals with no activity for 14 days) and DMs each owner their stale deals. Notify only if new. |
| 16 | Product lead | "Triage happens before I look." | New Issues from forms, mail, chat and support land in **Triage**. The triage agent suggests labels, priority, team and possible duplicates, as accept/reject chips. Nothing moves out of Triage without a human, unless a Space policy allows auto-accept for low-risk labels. |
| 17 | Engineer | "Hand an issue to an agent, keep ownership." | Assign the Issue to Ada as **delegate** while a human stays **owner**. The agent session shows plan, working, needs input and done. Ada works on her computer (ACP + Claude Code) or delegates to Cursor. The PR links back, and the Issue moves to In Review, then Done on merge. |
| 18 | Engineering lead | "Cycle planning in 5 minutes." | At cycle start, a routine proposes the cycle scope from priority, estimates and team capacity as a change set on the Issues' `cycle` field. The lead reviews it in the side panel and applies all or part. |
| 19 | Product + Sales | "Customers hear back when their request ships." | Support tickets and CRM notes tagged "feature request" link to one Issue, with the requesting customers and their deal value. When the Issue is Done, a routine drafts personal "it's live" emails per contact, sent as one batched approval card. |

### Code

```ruby
# Story 12. routine: log-meeting-to-crm   trigger: calendar.event.ended where attendees include a CRM contact
ev    = ctx.event.payload
deal  = app.crm.deals.for_contacts(ev["attendee_emails"]).first
notes = app.meetings.notes(ev["id"]) || ev["owner_memo"]            # untrusted text

update = app.ai.extract(text: notes, schema: {
  stage: ["discovery", "proposal", "negotiation", "won", "lost", "unchanged"],
  next_step: "string", close_date: "date?", new_stakeholders: ["string"] })

changes = { next_step: update["next_step"] }
changes[:stage]      = update["stage"]      unless update["stage"] == "unchanged"
changes[:close_date] = update["close_date"] if update["close_date"]
app.changes.stage("crm/deals", [changes.merge(id: deal["id"])])     # human-edited fields win at apply
app.crm.activity.log(deal["id"], kind: "meeting", ref: ev["id"], summary: update["next_step"])
app.mail.draft(to: ev["external_attendees"], template: "meeting-follow-up", vars: { notes: update })
```

```yaml
# Story 14. Excerpt from the CRM app manifest: an approval rule on a record field.
record_types:
  deals:
    fields:
      discount: { type: percent }
    approval_rules:
      - when: "discount > 20"
        applies_to: [human, agent]          # a business rule, not only an agent guard
        approver: role:sales-lead
        message: "Discount above 20% on {{title}} ({{amount}})"
```

```ruby
# Story 16. routine: issue-triage   trigger: issue.created where state == "triage"
issue = ctx.event.payload
similar = app.issues.search(issue["title"] + " " + issue["body"], space: issue["space"],
                            state_not: "canceled", limit: 3)
s = app.ai.extract(text: issue["body"], schema: {
  labels: ["bug", "feature", "question", "billing", "docs"], priority: ["urgent", "high", "medium", "low"],
  team: ["web", "api", "mobile", "infra"] })

app.issues.suggest(issue["id"],                          # shown as accept/reject chips in Triage
  labels: s["labels"], priority: s["priority"], team: s["team"],
  duplicate_of: similar.first && similar.first["score"] > 0.85 ? similar.first["id"] : nil)
```

```ruby
# Story 18. routine: propose-cycle-scope   trigger: cycle.started (team "web")
cycle    = ctx.event.payload
capacity = app.issues.team_capacity("web", cycle: cycle["id"])      # points, from history
backlog  = app.issues.list(team: "web", state: "backlog", sort: ["priority", "created_at"])

picked, used = [], 0
backlog.each do |i|
  pts = i["estimate"] || 2
  next if used + pts > capacity
  picked << { id: i["id"], cycle: cycle["id"] }
  used += pts
end
app.changes.stage("issues", picked, title: "Proposed scope for #{cycle["name"]} (#{used}/#{capacity} pts)")
```

```ruby
# Story 19. routine: notify-requesters-on-ship   trigger: issue.state_changed to "done" where label "feature-request"
issue    = ctx.event.payload
requests = app.issues.linked(issue["id"], kind: ["helpdesk.ticket", "crm.note"])
contacts = requests.map { |r| r["contact"] }.compact.uniq { |c| c["email"] }

contacts.each do |c|
  app.mail.draft(to: c["email"], template: "feature-shipped",
                 vars: { name: c["name"], feature: issue["title"], changelog: issue["release_note"] })
end
# Sending these is an external action, so all drafts appear on one batched review card.
```

### CLI

```bash
avok crm deals list --stale 14d --owner me --json
avok crm deals update dl_42 --stage negotiation --discount 25   # becomes a proposal to the sales lead
avok issues create "Checkout fails on Safari" --space Web --label bug
avok issues assign ISS-311 --delegate ada --owner me           # agent works, human stays owner
avok issues triage --accept-suggestions ISS-311
```
