# OpenCompany Integration Strategy & Roadmap

> The complete integration roadmap for making AI agents genuinely useful for real business operations.

---

## Why Integrations Matter

OpenCompany agents are only as useful as the systems they can reach. Without integrations, agents operate in a vacuum — they can chat and organize internal data, but they cannot read a CRM contact, close a support ticket, post a social media update, process a payment, or file a document.

Integrations are what transform OpenCompany from an internal collaboration tool into a genuine operational hub where agents drive real business outcomes. Every integration added multiplies the platform's value by expanding the surface area of what agents can autonomously do.

---

## Current State (February 2026)

| Category | Built & Working | Listed in UI (Not Built) |
|----------|----------------|--------------------------|
| AI Models | Anthropic, OpenAI, Gemini, DeepSeek, Groq, Mistral, xAI, Ollama, OpenRouter, MiniMax, Kimi, GLM, Codex | — |
| Communication | Telegram | Slack, Discord, Teams, Matrix |
| Analytics | Plausible, Google Analytics, Google Search Console | — |
| Productivity | Google Calendar, Google Drive, Google Docs, Google Sheets, Google Contacts, Google Forms, Google Tasks, Gmail, ClickUp, TickTick | Notion, Trello, Obsidian |
| Developer | — | GitHub, GitLab, Linear, Jira |
| Visualization | Mermaid, Typst, PlantUML, Vega-Lite, SVG | — |
| Data & APIs | Webhooks (partial) | Email SMTP, REST API |
| MCP Servers | DeepWiki, Context7, Cloudflare Docs, Exa Search | — |

---

## Business Use Cases & User Stories

### 1. Sales & Revenue Operations

**Who:** Sales managers, SDRs, account executives, revenue ops teams

- "As a sales manager, I want my agent to sync new leads from HubSpot into an OpenCompany table so the team can see pipeline updates in real-time."
- "As an SDR, I want my agent to monitor Calendly bookings and create follow-up tasks with the prospect's details pre-filled."
- "As a revenue ops lead, I want agents to pull Stripe payment data weekly and generate a revenue summary posted to our finance channel."
- "As an account exec, I want my agent to flag deals stuck in the same Pipedrive stage for 14+ days."
- "As a sales team, we want our agent to enrich new CRM contacts with LinkedIn data and score them against our criteria."

**Key integrations:** HubSpot, Salesforce, Pipedrive, GoHighLevel, Stripe, Calendly, LinkedIn

---

### 2. Customer Support & Success

**Who:** Support leads, CS managers, support agents

- "As a support lead, I want agents to create Zendesk tickets from customer messages in our Telegram channel."
- "As a CS manager, I want my agent to monitor Intercom conversations and escalate high-priority issues to our internal channel."
- "As a support team, we want our agent to pull Freshdesk ticket metrics daily and post a resolution-time summary."
- "As a CS lead, I want the agent to detect churn signals by monitoring support ticket frequency per customer — alert us when a customer opens 3+ tickets in a week."

**Key integrations:** Zendesk, Intercom, Freshdesk, Help Scout, Crisp, Twilio, WhatsApp Business API

---

### 3. Marketing & Growth

**Who:** Marketing managers, content teams, growth hackers, social media managers

- "As a marketing manager, I want my agent to pull Plausible analytics every morning and post a traffic summary." *(Already possible)*
- "As a content marketer, I want agents to schedule social media posts on Buffer and report engagement 24h later."
- "As a growth lead, I want my agent to monitor ConvertKit subscriber growth and alert me when daily signups drop below threshold."
- "As a social media manager, I want my agent to track brand mentions on Twitter/X and aggregate sentiment daily."
- "As an email marketer, I want my agent to create and send Mailchimp campaigns triggered by events in our CRM."

**Key integrations:** Mailchimp, SendGrid, Buffer, Twitter/X, LinkedIn, ConvertKit, ActiveCampaign

---

### 4. HR & People Operations

**Who:** HR managers, recruiters, people ops

- "As an HR manager, I want my agent to sync new hires from BambooHR into our workspace — creating user accounts and adding them to the right channels."
- "As a recruiter, I want my agent to track open positions and post weekly hiring pipeline updates."
- "As a people ops lead, I want my agent to check Gusto for upcoming payroll dates and remind managers to submit approvals."
- "As an HR team, we want our agent to schedule onboarding calendar events when a new hire is added." *(Partially possible with Google Calendar)*

**Key integrations:** BambooHR, Gusto, Deel, Rippling, Workday

---

### 5. Finance & Accounting

**Who:** Finance managers, bookkeepers, CFOs

- "As a finance manager, I want my agent to monitor Stripe for failed payments and create follow-up tasks."
- "As a bookkeeper, I want my agent to sync QuickBooks invoice data into an OpenCompany table for review."
- "As a CFO, I want weekly financial summaries pulling data from Stripe (revenue), Gusto (payroll), and our tables (expenses)."
- "As a finance team, we want agents to flag invoices overdue by 30+ days from Xero and notify the relevant account manager."

**Key integrations:** Stripe, QuickBooks, Xero, FreshBooks, PayPal, Plaid

---

### 6. IT Operations & DevOps

**Who:** DevOps engineers, SREs, IT managers, platform engineers

- "As a DevOps lead, I want my agent to monitor Sentry for error spikes and auto-create incident tasks in our kanban board."
- "As an SRE, I want my agent to receive PagerDuty alerts and post them to #incidents with context from Datadog."
- "As a platform engineer, I want my agent to monitor Vercel deployment status and notify the team on success or failure."
- "As an IT manager, I want my agent to create GitHub issues from tasks and sync status bi-directionally."
- "As a DevOps team, we want agents to run Cloudflare health checks and auto-escalate when a service goes down."

**Key integrations:** GitHub, GitLab, Sentry, Datadog, PagerDuty, Vercel, Cloudflare

---

### 7. Legal & Compliance

**Who:** Legal teams, compliance officers, contract managers

- "As a legal team, we want our agent to track DocuSign envelope status and notify us when contracts are signed."
- "As a compliance officer, I want my agent to pull PandaDoc audit trails weekly and flag unsigned agreements past deadline."
- "As a contract manager, I want my agent to monitor contract expiration dates and send renewal reminders 60 days out."

**Key integrations:** DocuSign, PandaDoc, Clio

---

### 8. Agency & Consultant Operations

**Who:** Agency owners, account managers, freelancers managing multiple clients

- "As an agency owner using GoHighLevel, I want my agent to pull client pipeline data across all sub-accounts and generate a weekly report."
- "As an account manager, I want my agent to sync ClickUp tasks with client-facing updates in Slack and generate time-tracking summaries." *(ClickUp already built)*
- "As a freelancer, I want my agent to track billable hours, generate invoices in FreshBooks, and send them automatically."
- "As a marketing agency, we want agents to pull Plausible analytics for all client sites and compile a cross-client performance dashboard." *(Plausible already built)*

**Key integrations:** GoHighLevel, ClickUp ✓, FreshBooks, Slack, Notion

---

### 9. E-commerce Operations

**Who:** E-commerce managers, store owners, fulfillment teams

- "As a Shopify store owner, I want my agent to monitor new orders and post daily order summaries to our operations channel."
- "As an e-commerce manager, I want my agent to track WooCommerce inventory levels and alert when products drop below reorder threshold."
- "As a DTC brand, I want my agent to correlate Stripe payment data with Shopify orders and flag discrepancies."
- "As a store owner, I want my agent to track Gumroad/LemonSqueezy sales and send a weekly revenue update to Telegram."

**Key integrations:** Shopify, WooCommerce, Stripe, Gumroad, LemonSqueezy

---

### 10. Real Estate

**Who:** Agents, brokers, property managers

- "As a real estate agent, I want my agent to monitor new MLS listings matching my criteria and post them to my client channels."
- "As a property manager, I want my agent to track maintenance requests and create tasks for contractors."
- "As a broker, I want my agent to pull Zillow Zestimate data for properties in my pipeline and update our comparables table."

**Key integrations:** Zillow API, MLS (RETS/RESO Web API), Realtor.com API

---

### 11. Healthcare Administration

**Who:** Practice managers, administrative staff, clinic operators

- "As a practice manager, I want my agent to sync appointment data and send patient reminders via SMS."
- "As an admin, I want my agent to track patient intake forms and flag incomplete records."
- "As a clinic operator, I want my agent to generate daily patient volume reports from our scheduling system."

**Key integrations:** FHIR/HL7 connector, Twilio SMS, Google Calendar ✓

---

### 12. Education & EdTech

**Who:** Instructors, program coordinators, EdTech operators

- "As an instructor, I want my agent to monitor Google Forms submissions for assignments and auto-grade based on rubrics." *(Google Forms already built)*
- "As a program coordinator, I want my agent to sync student progress data and flag students falling behind."
- "As an EdTech company, I want my agent to pull user engagement data and generate retention reports."

**Key integrations:** Google Forms ✓, Google Sheets ✓, Google Classroom API, Typeform

---

## Master Integration List

### CRM & Sales

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **HubSpot** | Marketing, sales, and CRM platform | Sales, Marketing, Agency | Read/write contacts, deals, tickets. Sync pipeline stages. Pull marketing analytics. Create workflows. | Phase 1 |
| **Salesforce** | Enterprise CRM | Sales, Enterprise | Read contacts/leads/opportunities. Update deal stages. Create cases. Pull reports. | Phase 2 |
| **Pipedrive** | Sales-focused CRM | Sales | Read deals, contacts, activities. Update stages. Create follow-up activities. | Phase 2 |
| **GoHighLevel** | All-in-one marketing/CRM for agencies | Agency | Read/write contacts across sub-accounts. Monitor pipelines. Trigger workflows. Pull campaign stats. Create opportunities. | Phase 1 |
| **Zoho CRM** | Cloud CRM suite | Sales | Read/write leads, contacts, deals. Sync modules. Create tasks. | Phase 3 |
| **Close** | Inside sales CRM | Sales | Read leads, manage sequences, log calls, update opportunities. | Phase 3 |
| **Freshsales** | CRM by Freshworks | Sales | Read contacts, deals. Create tasks. Update lifecycle stages. | Phase 3 |

---

### Customer Support

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **Zendesk** | Customer support platform | Support | Create/update/close tickets. Read ticket history. Pull satisfaction scores. Monitor SLAs. | Phase 1 |
| **Intercom** | Customer messaging platform | Support, Sales | Read conversations. Send messages. Create tickets. Monitor user segments. | Phase 1 |
| **Freshdesk** | Helpdesk software | Support | Create/update tickets. Read KB articles. Pull resolution metrics. | Phase 2 |
| **Help Scout** | Email-based support | Support | Read/reply to conversations. Create docs. Manage mailboxes. | Phase 2 |
| **Crisp** | Live chat and messaging | Support | Read conversations. Send messages. Manage contacts. | Phase 3 |

---

### Marketing & Email

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **Mailchimp** | Email marketing platform | Marketing | Create/send campaigns. Manage lists and segments. Pull campaign analytics. | Phase 1 |
| **SendGrid** | Email delivery service | Marketing, Data | Send transactional and marketing emails. Pull delivery stats. Manage contacts. | Phase 1 |
| **Brevo** (Sendinblue) | Email and SMS marketing | Marketing | Send campaigns. Manage contacts. Pull analytics. | Phase 2 |
| **ActiveCampaign** | Email marketing + automation | Marketing, Sales | Manage contacts. Create automations. Send campaigns. Pull engagement data. | Phase 2 |
| **ConvertKit** | Creator email marketing | Marketing | Manage subscribers. Create broadcasts. Pull subscriber growth data. | Phase 2 |
| **Beehiiv** | Newsletter platform | Marketing | Manage subscribers. Pull newsletter analytics. Schedule posts. | Phase 3 |

---

### Social Media

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **Twitter/X API** | Social media platform | Marketing, Growth | Post tweets. Monitor mentions. Track engagement metrics. Search keywords. | Phase 2 |
| **LinkedIn API** | Professional network | Marketing, Sales, HR | Post updates. Read company page analytics. Search profiles. Manage job postings. | Phase 2 |
| **Buffer** | Social scheduling tool | Marketing | Schedule posts across platforms. Pull engagement analytics. Manage posting calendar. | Phase 2 |
| **Instagram API** | Photo/video social | Marketing | Post content. Pull engagement metrics. Monitor comments. | Phase 3 |
| **Facebook API** | Social platform | Marketing | Post to pages. Pull page analytics. Manage ad campaigns. | Phase 3 |
| **Reddit** | Community platform | Marketing, Growth | Monitor subreddits. Track mentions. Post content. | Phase 3 |
| **ProductHunt** | Product launch platform | Growth | Monitor launch performance. Track upvotes. Pull comments. | Phase 3 |
| **Hootsuite** | Social management platform | Marketing | Schedule posts. Pull cross-platform analytics. | Phase 3 |

---

### E-commerce & Payments

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **Stripe** | Payment processing | Finance, E-commerce, Sales | Monitor payments and subscriptions. Pull revenue data. Track failed payments. Create invoices. Read customer data. | Phase 1 |
| **Shopify** | E-commerce platform | E-commerce | Read orders, products, inventory. Update order status. Pull sales analytics. Monitor stock levels. | Phase 1 |
| **PayPal** | Payments | Finance, E-commerce | Monitor transactions. Pull settlement reports. Track disputes. | Phase 2 |
| **WooCommerce** | WordPress e-commerce | E-commerce | Read orders, products. Update inventory. Pull sales data. | Phase 2 |
| **Square** | POS and payments | Finance, E-commerce | Pull transaction data. Monitor inventory. Read customer info. | Phase 3 |
| **Gumroad** | Digital products | E-commerce, Creator | Monitor sales. Pull product analytics. Manage offers. | Phase 3 |
| **LemonSqueezy** | Digital products and SaaS | E-commerce, Creator | Monitor sales and subscriptions. Pull revenue data. Manage products. | Phase 3 |

---

### Finance & Accounting

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **QuickBooks** | Accounting software | Finance | Read/create invoices. Pull P&L reports. Manage expenses. Track accounts receivable. | Phase 2 |
| **Xero** | Cloud accounting | Finance | Read invoices and bills. Pull financial reports. Manage contacts. | Phase 2 |
| **FreshBooks** | Invoicing and accounting | Finance, Agency | Create invoices. Track time. Pull expense reports. Manage projects. | Phase 2 |
| **Wave** | Free accounting | Finance | Read invoices. Pull financial reports. Manage customers. | Phase 3 |
| **Plaid** | Banking data | Finance | Read account balances. Pull transactions. Verify bank connections. | Phase 3 |

---

### HR & People

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **BambooHR** | HR management | HR | Read employee directory. Track time-off. Sync new hires. Pull org chart data. | Phase 2 |
| **Gusto** | Payroll and HR | HR, Finance | Read payroll schedules. Track benefits. Pull compensation data. | Phase 3 |
| **Deel** | Global payroll | HR, Finance | Read contractor/employee data. Track payments. Pull compliance info. | Phase 3 |
| **Rippling** | HR platform | HR | Sync employees. Track onboarding. Pull time-and-attendance. | Phase 3 |
| **Workday** | Enterprise HR | HR, Enterprise | Read employee data. Track approvals. Pull analytics. | Phase 3 |

---

### Project Management

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **ClickUp** | Project management | Productivity, Agency | Full task CRUD. Workspace hierarchy. Search. Time tracking. Docs. Chat. | ✅ Done |
| **TickTick** | Task management | Productivity | Full task CRUD. Manage projects. | ✅ Done |
| **Linear** | Issue tracking | Developer, Startup | Read/create/update issues. Manage cycles. Pull project analytics. Sync labels. | Phase 1 |
| **Jira** | Enterprise project management | Developer, Enterprise | Read/create/update issues. Manage sprints. Pull velocity data. Transition issues. | Phase 1 |
| **Notion** | All-in-one workspace | Productivity, Knowledge | Read/write pages and databases. Search content. Manage properties. | Phase 1 |
| **Asana** | Work management | Productivity | Read/create/update tasks. Manage projects. Pull portfolio data. | Phase 2 |
| **Monday.com** | Work OS | Productivity | Read/create items. Update columns. Pull dashboards. | Phase 2 |
| **Trello** | Kanban boards | Productivity | Read/create/move cards. Manage boards and lists. Add checklists. | Phase 2 |
| **Basecamp** | Project management | Productivity | Read projects and todos. Post messages. Upload files. | Phase 3 |
| **Shortcut** | Software project management | Developer | Read/create stories. Manage iterations. Pull velocity. | Phase 3 |

---

### Developer Tools & DevOps

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **GitHub** | Code hosting and collaboration | Developer | Read/create issues and PRs. Monitor CI status. Manage releases. Review PRs. Manage project boards. | Phase 1 |
| **GitLab** | DevOps platform | Developer | Read/create issues and MRs. Monitor pipelines. Manage releases. | Phase 2 |
| **Sentry** | Error tracking | DevOps | Monitor error events. Read issue details. Resolve/ignore issues. Pull error trends. | Phase 1 |
| **Datadog** | Observability platform | DevOps | Pull metrics and dashboards. Read monitor status. Create/silence alerts. | Phase 2 |
| **PagerDuty** | Incident management | DevOps | Read/create/acknowledge incidents. Pull on-call schedules. Escalate alerts. | Phase 2 |
| **Vercel** | Frontend deployment | DevOps | Monitor deployments. Read build logs. Pull analytics. Manage env vars. | Phase 2 |
| **Railway** | App hosting | DevOps | Monitor deployments. Read logs. Manage services. | Phase 3 |
| **Cloudflare** | CDN and security | DevOps | Read analytics. Manage DNS. Purge cache. Monitor security events. | Phase 2 |

---

### Communication

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **Slack** | Team messaging | All | Send/read messages. Manage channels. React. Post rich blocks. Thread replies. External channel bridging. | Phase 1 |
| **Discord** | Community chat | Community, Support | Send/read messages. Manage channels and roles. React. Post embeds. External channel bridging. | Phase 1 |
| **Microsoft Teams** | Enterprise collaboration | Enterprise | Send/read messages. Post adaptive cards. Manage channels. | Phase 2 |
| **Twilio** | SMS, voice, messaging APIs | Support, Sales, Healthcare | Send/receive SMS. Make/receive calls. Pull messaging logs. | Phase 1 |
| **WhatsApp Business API** | Business messaging | Support, Sales | Send/receive messages. Manage templates. Pull conversation data. | Phase 2 |
| **Matrix** | Decentralized chat | Community | Send/read messages. Manage rooms. | Phase 3 |
| **Mailgun** | Email delivery | Data | Send emails. Read delivery events. Manage mailing lists. | Phase 2 |

---

### Calendar & Scheduling

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **Google Calendar** | Calendar service | All | List/create/update/delete events. Check availability. Manage attendees. | ✅ Done |
| **Outlook Calendar** | Microsoft calendar | Enterprise | Read/create events. Check free/busy. Manage attendees. | Phase 2 |
| **Calendly** | Scheduling tool | Sales, HR | Read bookings. Pull availability. Create event types. Monitor cancellations. | Phase 1 |
| **Cal.com** | Open-source scheduling | Sales, HR | Read bookings. Manage event types. Pull analytics. | Phase 2 |

---

### File Storage & Documents

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **Google Drive** | Cloud storage | All | Search/list/upload/download files. Manage permissions. Create folders. | ✅ Done |
| **Google Docs** | Document editing | Knowledge, Productivity | Read/write documents. Search content. | ✅ Done |
| **Google Sheets** | Spreadsheets | Data, Finance | Read/write cells and ranges. Manage sheets. Pull data as tables. | ✅ Done |
| **Confluence** | Wiki / knowledge base | Knowledge, Enterprise | Read/write pages. Search content. Manage spaces. | Phase 2 |
| **Dropbox** | Cloud storage | Productivity | Search/upload/download files. Manage sharing. | Phase 3 |
| **OneDrive** | Microsoft cloud storage | Enterprise | Search/upload/download files. Manage sharing. | Phase 3 |
| **Box** | Enterprise file storage | Enterprise | Search/upload/download files. Manage collaboration. | Phase 3 |
| **SharePoint** | Enterprise document management | Enterprise | Read/write documents. Search content. Manage sites. | Phase 3 |

---

### Databases & Data

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **Airtable** | Spreadsheet-database | Data, Productivity | Read/write records. Manage views. Pull attachments. Sync data. | Phase 2 |
| **Supabase** | Postgres backend | Developer | Query tables. Insert/update data. Call functions. | Phase 2 |
| **PostgreSQL** | Relational database | Developer | Execute read-only queries. Pull schema info. Generate reports. | Phase 2 |
| **Firebase** | Google backend | Developer | Read/write Firestore documents. Pull analytics events. | Phase 3 |
| **MySQL** | Relational database | Developer | Execute read-only queries. Pull schema info. | Phase 3 |
| **Redis** | Key-value store | Developer | Read keys. Monitor queues. Pull stats. | Phase 3 |
| **MongoDB** | Document database | Developer | Query collections. Pull aggregations. | Phase 3 |

---

### Search & AI Data

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **Algolia** | Search-as-a-service | Developer, E-commerce | Query indices. Pull search analytics. Manage records. | Phase 3 |
| **Elasticsearch** | Search engine | Developer | Execute queries. Pull index stats. Monitor cluster health. | Phase 3 |
| **Pinecone** | Vector database | AI/ML | Query vectors. Upsert embeddings. Manage namespaces. | Phase 3 |
| **Weaviate** | Vector database | AI/ML | Query objects. Manage schemas. Pull analytics. | Phase 3 |

---

### Forms & Surveys

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **Google Forms** | Form builder | Education, HR, Marketing | Read responses. Monitor new submissions. Pull summary data. | ✅ Done |
| **Typeform** | Interactive forms | Marketing, HR | Read responses. Monitor new submissions. Pull completion rates. | Phase 2 |
| **Tally** | Free form builder | Marketing | Read responses. Monitor submissions. | Phase 3 |
| **SurveyMonkey** | Survey platform | HR, Marketing | Read responses. Pull analytics. Monitor completion rates. | Phase 3 |

---

### Analytics

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **Plausible** | Privacy-friendly analytics | Marketing | Query stats. Get realtime visitors. Manage sites/goals. | ✅ Done |
| **Google Analytics** | Website analytics | Marketing | Query reports. Pull user/session data. Read conversion data. | ✅ Done |
| **Google Search Console** | SEO analytics | Marketing, Developer | Query search performance. Read crawl errors. Submit URLs. | ✅ Done |
| **Mixpanel** | Product analytics | Growth | Query events. Pull funnels. Read user cohorts. | Phase 3 |
| **PostHog** | Open-source product analytics | Growth | Query events. Pull feature flags. Read session recordings. | Phase 3 |

---

### Legal & Contracts

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **DocuSign** | E-signatures | Legal, Sales | Send envelopes. Monitor signing status. Pull audit trails. | Phase 2 |
| **PandaDoc** | Document automation | Legal, Sales | Create docs from templates. Send for signing. Track status. | Phase 2 |
| **Clio** | Legal practice management | Legal | Read matters. Track time. Manage contacts. Pull billing. | Phase 3 |

---

### Community & Social

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **Discourse** | Forum software | Community | Read/create topics. Monitor categories. Pull user stats. | Phase 3 |
| **Circle** | Community platform | Community | Read posts. Manage members. Pull engagement data. | Phase 3 |

---

### Automation Orchestrators

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **n8n** | Open-source workflow automation | Automation | Trigger workflows. Read execution logs. Manage webhooks. | Phase 2 |
| **Zapier** | App-to-app automation | Automation | Trigger Zaps. Read task history. | Phase 3 |
| **Make** (Integromat) | Visual automation | Automation | Trigger scenarios. Read execution data. | Phase 3 |
| **Temporal** | Durable execution engine | Developer | Start/query workflows. Read workflow history. | Phase 3 |
| **Inngest** | Event-driven functions | Developer | Trigger functions. Read execution logs. | Phase 3 |

---

### Real Estate

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **Zillow API** | Property data | Real Estate | Pull property estimates. Search listings. Read market data. | Phase 3 |
| **MLS (RETS/RESO)** | Listing service | Real Estate | Search listings. Read property details. Monitor new listings. | Phase 3 |
| **Realtor.com API** | Property listings | Real Estate | Search listings. Read property data. | Phase 3 |

---

### Healthcare

| Integration | Description | Use Cases | What Agents Do | Priority |
|-------------|-------------|-----------|----------------|----------|
| **FHIR connector** | Healthcare data standard | Healthcare | Read patient records. Query appointments. Pull clinical data. | Phase 3 |
| **HL7 connector** | Healthcare messaging | Healthcare | Parse HL7 messages. Route clinical events. | Phase 3 |

---

## Priority Tiers

### Phase 1 — Core (17 integrations)

The minimum viable integration set for launch. These cover the top 4 use cases (Sales, Support, DevOps, Marketing) and serve the primary target audiences (developers and startups).

| Integration | Auth Type | Rationale |
|-------------|-----------|-----------|
| **Slack** | OAuth 2.0 | #1 most requested. External channel bridging like Telegram. |
| **Discord** | Bot Token / OAuth | #2 most requested. Community-focused users. |
| **GitHub** | OAuth 2.0 / PAT | Developer audience is the primary target. Issues, PRs, CI. |
| **HubSpot** | OAuth 2.0 | Largest free CRM. Covers sales + marketing + support. |
| **GoHighLevel** | OAuth 2.0 | Agency market. All-in-one CRM, marketing, scheduling, funnels. |
| **Stripe** | API Key | Revenue tracking. Every SaaS/e-commerce customer needs it. |
| **Notion** | OAuth 2.0 | Knowledge base. Widely used by startups. |
| **Linear** | API Key / OAuth | Developer/startup audience. Clean API, quick to build. |
| **Jira** | OAuth 2.0 (Atlassian) | Enterprise must-have for project management. |
| **Zendesk** | OAuth 2.0 / API Token | Top support platform. Ticket management. |
| **Intercom** | OAuth 2.0 | Customer messaging leader. Support + sales. |
| **Sentry** | API Token | Error monitoring. Every dev team uses it. Small API surface. |
| **Twilio** | API Key + Secret | SMS/Voice. Enables notifications across all use cases. |
| **Mailchimp** | OAuth 2.0 | Email marketing leader. Covers marketing use case. |
| **SendGrid** | API Key | Email delivery. Required for transactional email. |
| **Shopify** | OAuth 2.0 | Largest e-commerce platform. High demand. |
| **Calendly** | OAuth 2.0 | Sales scheduling. Small, focused API. |

---

### Phase 2 — Growth (~35 integrations)

Serve expanding needs of growing teams and secondary use cases. Enterprise readiness.

**CRM & Sales:** Salesforce, Pipedrive
**Support:** Freshdesk, Help Scout
**Marketing:** Brevo, ActiveCampaign, ConvertKit, Twitter/X, LinkedIn, Buffer
**Finance:** QuickBooks, Xero, FreshBooks, PayPal
**HR:** BambooHR
**DevOps:** GitLab, Datadog, PagerDuty, Vercel, Cloudflare
**Communication:** Microsoft Teams, WhatsApp Business, Mailgun
**Productivity:** Asana, Monday.com, Trello, Confluence
**Calendar:** Outlook Calendar, Cal.com
**Data:** Airtable, Supabase, PostgreSQL
**Forms:** Typeform
**Legal:** DocuSign, PandaDoc
**Automation:** n8n
**E-commerce:** WooCommerce

---

### Phase 3 — Ecosystem (~45 integrations)

Long tail. Niche markets and specialized verticals. Many can be effectively served by MCP servers or the generic REST API connector rather than dedicated packages.

**CRM:** Zoho CRM, Close, Freshsales
**E-commerce:** Square, Gumroad, LemonSqueezy
**Finance:** Wave, Plaid
**HR:** Gusto, Deel, Rippling, Workday
**Project Management:** Basecamp, Shortcut
**DevOps:** Railway
**Communication:** Matrix
**Files:** Dropbox, OneDrive, Box, SharePoint
**Databases:** MySQL, Redis, MongoDB, Firebase
**Search/AI:** Algolia, Elasticsearch, Pinecone, Weaviate
**Social:** Instagram, Facebook, Reddit, ProductHunt, Hootsuite
**Community:** Discourse, Circle
**Analytics:** Mixpanel, PostHog
**Legal:** Clio
**Real Estate:** Zillow, MLS, Realtor.com
**Healthcare:** FHIR, HL7
**Automation:** Zapier, Make, Temporal, Inngest
**Education:** Google Classroom
**Surveys:** Tally, SurveyMonkey
**Support:** Crisp

---

## Architecture Patterns

Each integration follows one of five patterns, all supported by the existing codebase:

### Pattern 1: ToolProvider Package (Primary)
A Composer package implementing `ToolProvider` + `ConfigurableIntegration`. The standard approach for most integrations.

**Reference:** Plausible, ClickUp, TickTick packages

```
opencompanyapp/ai-tool-{name}/
  src/
    {Name}ToolProvider.php              -- ToolProvider + ConfigurableIntegration
    {Name}Service.php                   -- HTTP client wrapper
    AiTool{Name}ServiceProvider.php     -- Laravel service provider
    Tools/
      {Name}Read.php                    -- Read operations
      {Name}Write.php                   -- Write operations
```

### Pattern 2: OAuth Integration
Extends Pattern 1 with an OAuth controller and token refresh. Uses `oauth_connect` config schema type.

**Reference:** Google Calendar, Gmail packages

### Pattern 3: MCP Server
Zero-code integration through the MCP config modal. The `McpServer` model discovers and exposes tools automatically.

**Best for:** Services that already expose an MCP endpoint, or for Phase 3 long-tail coverage.

### Pattern 4: Webhook + REST API
For services that push data via webhooks. The existing webhook system receives events and routes them to agents/channels/tasks.

**Best for:** Stripe webhooks, GitHub webhooks, Shopify webhooks.

### Pattern 5: Lua Scripting Bridge (Planned)
Lightweight deterministic automations that call integration APIs via `oc.integrations.query()` and `oc.http.post()` at zero token cost.

**Best for:** Simple routing, status sync, conditional notifications.

---

*Last Updated: February 2026*
