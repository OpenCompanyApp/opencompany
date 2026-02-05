# OpenCompany Features

> A comprehensive list of everything OpenCompany can do.

---

## Agents

### Core Agent Capabilities

| Feature | Description |
|---------|-------------|
| **Persistent Agents** | Agents that run 24/7, not just when you call them |
| **Multiple Agents** | Create as many agents as you need with different roles |
| **Agent Identity** | Custom names, avatars, personalities per agent |
| **Agent Types** | Manager, Writer, Analyst, Creative, Researcher, Coder, Coordinator |
| **Agent Status** | Online, working, idle, offline indicators |
| **Configurable Behavior** | Autonomous, supervised, or strict modes |

### Agent Intelligence

| Feature | Description |
|---------|-------------|
| **LLM Support** | OpenAI, Anthropic, Gemini, Groq, xAI, GLM/Zhipu AI, and more via Laravel AI SDK |
| **Model Selection** | Choose different models per agent |
| **Model Failover** | Automatic fallback across providers if primary model fails |
| **Streaming Responses** | Real-time output as agents work via WebSocket broadcasting |
| **Context Management** | Smart handling of conversation history |
| **Token Optimization** | Automatic context compaction to stay within limits |
| **MCP Server** | Expose workspace as MCP server for external AI clients |

### Sub-Agents

| Feature | Description |
|---------|-------------|
| **Dynamic Spawning** | Agents can create sub-agents for complex tasks |
| **Task Delegation** | Parent agents assign work to child agents |
| **Result Aggregation** | Sub-agent outputs merged automatically |
| **Auto-Dissolution** | Sub-agents cleaned up after task completion |
| **Spawn Permissions** | Control which agents can spawn others |

---

## Communication

### Chat Interface

| Feature | Description |
|---------|-------------|
| **Real-Time Chat** | Talk to agents like messaging a colleague |
| **Group Channels** | Multiple humans and agents in shared channels |
| **Direct Messages** | Private conversations with individual agents |
| **@Mentions** | Tag specific agents to get their attention |
| **Message Threading** | Organized conversations with threads |
| **Rich Messages** | Files, images, code blocks, formatted text |
| **Reactions** | React to agent messages |

### External Channels

| Feature | Description |
|---------|-------------|
| **Slack** | Message your agents through Slack |
| **Discord** | Connect agents to Discord servers |
| **Microsoft Teams** | Teams integration |
| **WhatsApp** | Chat with agents via WhatsApp |
| **Telegram** | Telegram bot support |
| **Email** | Agents can send/receive emails |
| **Web Chat** | Built-in web interface |
| **Signal** | Signal messenger support |
| **Matrix** | Matrix protocol support |

---

## Memory & Knowledge

### Short-Term Memory

| Feature | Description |
|---------|-------------|
| **Conversation Context** | Remembers current conversation |
| **Working State** | Tracks what agent is currently doing |
| **Recent Outputs** | Recalls recent tool results |
| **Session Management** | Separate contexts per conversation |

### Long-Term Memory

| Feature | Description |
|---------|-------------|
| **Persistent Storage** | Memory survives restarts and sessions |
| **Past Conversations** | Recall previous interactions |
| **Learned Preferences** | Adapts to how you work |
| **Project History** | Remembers past projects and decisions |
| **Semantic Search** | Find relevant memories by meaning |
| **Vector Embeddings** | AI-powered memory retrieval |

### Workspace Knowledge

| Feature | Description |
|---------|-------------|
| **Document Access** | Agents can read workspace documents |
| **Codebase Understanding** | Navigate and understand code |
| **Team Context** | Knows team structure and relationships |
| **Shared Knowledge** | Knowledge shared across agents |

---

## Organization

### Org Chart

| Feature | Description |
|---------|-------------|
| **Visual Hierarchy** | See your entire organization visually |
| **Drag & Drop** | Reorganize by dragging nodes |
| **Human + Agent Mix** | Humans and agents in same org chart |
| **Reporting Lines** | Clear manager-subordinate relationships |
| **Status Indicators** | See who's online, working, or away |
| **Click to Chat** | Click any person/agent to message them |

### Team Structure

| Feature | Description |
|---------|-------------|
| **Workspaces** | Separate workspaces for different teams |
| **Departments** | Organize by function (Sales, Ops, etc.) |
| **Agent Managers** | Agents can manage other agents |
| **Human Managers** | Humans can manage agents |
| **Flexible Hierarchy** | Any structure that fits your needs |

### Roles & Permissions

| Feature | Description |
|---------|-------------|
| **User Roles** | Admin, Member, Viewer, etc. |
| **Agent Roles** | Different capabilities per agent role |
| **Custom Roles** | Create your own role definitions |
| **Permission Inheritance** | Permissions flow down hierarchy |
| **Role-Based Access** | Control who sees/does what |

---

## Tasks

### Task Management

| Feature | Description |
|---------|-------------|
| **Task Types** | Support tickets, content requests, research, analysis, custom |
| **Task Lifecycle** | Pending → Active → Paused → Completed/Failed/Cancelled |
| **Task Assignment** | Assign tasks to agents automatically or manually |
| **Priority Levels** | Low, Normal, High, Urgent priorities |
| **Due Dates** | Set deadlines for task completion |
| **Sub-Tasks** | Break complex tasks into smaller pieces |

### Task Execution

| Feature | Description |
|---------|-------------|
| **Step Tracking** | See each step the agent takes |
| **Live Progress** | Watch agents work in real-time |
| **Agent Channel Link** | Jump to the conversation where work happens |
| **Context & Files** | Attach relevant data to tasks |
| **Results & Deliverables** | Structured output when complete |

### Task Views

| Feature | Description |
|---------|-------------|
| **Task List** | All tasks with filters by status, agent, priority |
| **Active Tasks** | See what agents are working on right now |
| **Task History** | Complete record of completed tasks |
| **Agent Workload** | See how many tasks each agent has |

---

## Lists

### List Management

| Feature | Description |
|---------|-------------|
| **Kanban Boards** | Visual board with columns for status |
| **Checklists** | Simple todo lists for tracking items |
| **Backlog** | Prioritized lists for planning |
| **Drag & Drop** | Move items between columns |
| **Custom Columns** | Configure board columns |

### List Items

| Feature | Description |
|---------|-------------|
| **Item Status** | Track progress through workflow |
| **Assignees** | Assign to humans or agents |
| **Labels & Tags** | Categorize items |
| **Comments** | Discuss items with team |
| **Convert to Task** | Turn list items into agent tasks |

---

## Automation

### Triggers

| Feature | Description |
|---------|-------------|
| **Webhooks** | Trigger agents from external HTTP calls |
| **Cron Jobs** | Schedule agents to run at specific times |
| **Event-Based** | React to events from connected apps |
| **Polling** | Periodically check external sources |
| **Manual Trigger** | Start automations on demand |

### Integrations

| Feature | Description |
|---------|-------------|
| **GitHub** | React to commits, PRs, issues |
| **Stripe** | Handle payment events |
| **Gmail** | Process incoming emails |
| **Google Calendar** | Calendar-based triggers |
| **Custom APIs** | Connect any REST API |
| **OAuth Support** | Secure authentication for integrations |

### Workflows

| Feature | Description |
|---------|-------------|
| **Multi-Step** | Chain multiple agent actions |
| **Conditional Logic** | If-then branching in workflows |
| **Error Handling** | Graceful failure recovery |
| **Retry Logic** | Automatic retries on failure |
| **Timeout Management** | Handle long-running tasks |

---

## Collaboration

### Human-in-the-Loop

| Feature | Description |
|---------|-------------|
| **Approval Requests** | Agents ask before taking sensitive actions |
| **One-Click Approve** | Quick approve/reject buttons |
| **Approval Context** | Full context provided for decisions |
| **Approval Routing** | Route approvals to right person |
| **Escalation** | Auto-escalate if no response |

### Approvals

| Feature | Description |
|---------|-------------|
| **Budget Approvals** | Approve spending over thresholds |
| **Action Approvals** | Approve specific agent actions |
| **Spawn Approvals** | Approve creation of new agents |
| **Access Approvals** | Approve access to sensitive resources |

### Notifications

| Feature | Description |
|---------|-------------|
| **Real-Time Alerts** | Instant notifications for important events |
| **@Mention Notifications** | Know when you're tagged |
| **Approval Notifications** | Alerted when approval needed |
| **Task Completion** | Notified when agents finish work |
| **Error Alerts** | Know when something goes wrong |

---

## Tools & Skills

### Built-in Tools

| Category | Tools |
|----------|-------|
| **Web** | Browse websites, search the web, scrape pages, fill forms |
| **Code** | Execute code, debug, deploy, git operations |
| **Files** | Read, write, organize, search files |
| **APIs** | REST calls, GraphQL, webhooks, OAuth |
| **Database** | Query, insert, update, migrate data |
| **Analysis** | Create charts, reports, forecasts, dashboards |
| **Creative** | Generate images, documents, slides |
| **Communication** | Send emails, Slack messages, calendar invites |

### Custom Skills

| Feature | Description |
|---------|-------------|
| **Skill Creation** | Build custom skills for your agents |
| **Skill Sharing** | Share skills across agents |
| **Skill Marketplace** | Discover and install community skills |
| **Skill Configuration** | Customize skill behavior per agent |
| **Skill Permissions** | Control which agents can use which skills |

### Tool Controls

| Feature | Description |
|---------|-------------|
| **Tool Allowlists** | Define which tools agents can use |
| **Tool Blocklists** | Block specific tools |
| **Rate Limiting** | Limit how often tools can be called |
| **Path Restrictions** | Limit file/API access by path |
| **Domain Restrictions** | Limit web/API access by domain |

---

## Security & Governance

### Access Control

| Feature | Description |
|---------|-------------|
| **SSO/SAML** | Enterprise single sign-on |
| **MFA** | Multi-factor authentication |
| **RBAC** | Role-based access control |
| **Session Management** | Configurable session policies |

### Audit & Compliance

| Feature | Description |
|---------|-------------|
| **Audit Logs** | Complete record of all actions |
| **Immutable Logs** | Tamper-evident logging |
| **Log Export** | Export to SIEM tools |
| **Compliance Reports** | Pre-built compliance reporting |

### Data Security

| Feature | Description |
|---------|-------------|
| **Encryption** | Data encrypted at rest and in transit |
| **Data Residency** | Choose where your data lives |
| **PII Protection** | Automatic PII detection and handling |

### Agent Governance

| Feature | Description |
|---------|-------------|
| **Approval Policies** | Define when approvals are required |
| **Budget Controls** | Set spending limits per agent/team |
| **Resource Limits** | Token, API call, and time limits |
| **Sandboxing** | Isolate agent execution environments |

**[See full Enterprise features →](enterprise.md)**

---

## Deployment

### Cloud Hosted

| Feature | Description |
|---------|-------------|
| **Managed Infrastructure** | We handle servers, scaling, backups |
| **Global Regions** | US, EU, APAC availability |
| **Automatic Updates** | Always on latest version |
| **99.9% Uptime SLA** | Enterprise-grade reliability |

### Self-Hosted

| Feature | Description |
|---------|-------------|
| **Docker** | Single-command deployment |
| **Kubernetes** | Helm charts for k8s |
| **Air-Gapped** | Fully offline deployments |
| **Your Infrastructure** | AWS, GCP, Azure, on-prem |

### Open Source

| Feature | Description |
|---------|-------------|
| **Full Source Code** | Complete codebase on GitHub |
| **MIT License** | Use however you want |
| **Community Contributions** | Accept PRs, report issues |
| **No Vendor Lock-in** | Fork and customize freely |

---

## Dashboard & UI

### Overview

| Feature | Description |
|---------|-------------|
| **Organization Dashboard** | See all activity at a glance |
| **Agent Status** | Monitor all agents in real-time |
| **Pending Approvals** | Queue of items needing attention |
| **Recent Activity** | Feed of what's happening |
| **Cost Tracking** | See spending by agent/team |

### Management

| Feature | Description |
|---------|-------------|
| **Agent Configuration** | Edit agent settings visually |
| **User Management** | Invite, remove, manage users |
| **Workspace Settings** | Configure workspace options |
| **Integration Setup** | Connect external services |
| **Billing & Usage** | Monitor usage and costs |

### Dark Mode

| Feature | Description |
|---------|-------------|
| **Dark Theme** | Full dark mode support |
| **Light Theme** | Classic light mode |
| **System Preference** | Auto-match system setting |

---

## API & Developers

### REST API

| Feature | Description |
|---------|-------------|
| **Full API Access** | Everything available via API |
| **API Keys** | Secure key management |
| **Rate Limiting** | Fair usage limits |
| **Webhooks** | Receive events via webhook |

### SDK & Libraries

| Feature | Description |
|---------|-------------|
| **JavaScript/TypeScript** | Official JS SDK |
| **Python** | Official Python SDK |
| **REST** | Use any HTTP client |

### Extensibility

| Feature | Description |
|---------|-------------|
| **Custom Integrations** | Build your own integrations |
| **Plugin System** | Extend functionality |
| **Custom Tools** | Create new agent tools |
| **Webhook Handlers** | Custom webhook processing |

---

## Coming Soon

| Feature | Status |
|---------|--------|
| **Voice Interface** | Talk to agents by voice | Planned |
| **Mobile App** | iOS and Android apps | In Development |
| **Agent Marketplace** | Share and sell agent templates | Planned |
| **Visual Workflow Builder** | Drag-and-drop automation | Planned |
| **Advanced Analytics** | Deep insights into agent performance | Planned |

---

*Missing a feature? [Request it on GitHub](https://github.com/opencompany/opencompany/issues) or [contact us](mailto:hello@opencompany.ai).*
