# OpenCompany for Enterprise

> Deploy AI agents at scale with the security, compliance, and control your organization requires.

---

## Why Enterprise Teams Choose OpenCompany

OpenCompany is built for organizations that need more than just AI capabilities—they need **governance**, **accountability**, and **compliance** at every level.

```text
┌─────────────────────────────────────────────────────────────────────────────────┐
│                                                                                 │
│   "We evaluated 12 AI agent platforms. OpenCompany was the only one            │
│    that met our security requirements out of the box."                         │
│                                                                                 │
│                                        — CISO, Fortune 500 Financial Services  │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Identity & Access Management

### Single Sign-On (SSO)

| Protocol | Support |
|----------|---------|
| SAML 2.0 | Full support |
| OIDC / OAuth 2.0 | Full support |
| Azure AD | Native integration |
| Okta | Native integration |
| Google Workspace | Native integration |
| Custom IdP | SAML/OIDC compatible |

**Features:**
- Just-in-time user provisioning
- Automatic role mapping from IdP groups
- Session management and forced logout
- Configurable session timeouts

### Multi-Factor Authentication (MFA)

- TOTP authenticator apps (Google Authenticator, Authy, 1Password)
- Hardware security keys (FIDO2/WebAuthn)
- SMS/Email backup codes (configurable)
- MFA enforcement by role or workspace
- Remember trusted devices

### Directory Sync

- Automatic user provisioning/deprovisioning
- Group-to-role mapping
- Scheduled sync with conflict resolution
- Audit trail of all sync operations

---

## Role-Based Access Control (RBAC)

### Built-in Roles

```text
┌─────────────────────────────────────────────────────────────────┐
│  ROLE HIERARCHY                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Organization Admin                                             │
│  └── Full platform access, billing, security settings          │
│                                                                 │
│  Workspace Admin                                                │
│  └── Manage workspace members, agents, settings                 │
│                                                                 │
│  Agent Manager                                                  │
│  └── Create/edit agents, approve actions, view logs            │
│                                                                 │
│  Member                                                         │
│  └── Use agents, submit requests, view own activity            │
│                                                                 │
│  Viewer                                                         │
│  └── Read-only access to dashboards and reports                │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Custom Roles

Create custom roles with granular permissions:

| Resource | Available Permissions |
|----------|----------------------|
| Agents | create, read, update, delete, execute, approve, configure |
| Workspaces | create, read, update, delete, manage_members, manage_settings |
| Users | invite, remove, update_role, view_activity |
| Documents | create, read, update, delete, share, export |
| Budgets | view, set, override, approve_overages |
| Audit Logs | view, export, configure_retention |
| Integrations | create, read, update, delete, manage_credentials |

### Permission Features

- Role inheritance (workspace → agent level)
- Time-limited elevated access
- Policy-as-code (JSON/YAML definitions)
- Role change audit trail

---

## Audit & Compliance

### Comprehensive Audit Logs

Every action is logged with full context:

```text
┌─────────────────────────────────────────────────────────────────┐
│  AUDIT LOG ENTRY                                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Event ID:      evt_a1b2c3d4e5f6                               │
│  Timestamp:     2024-01-15T14:32:18.456Z                       │
│  Actor:         user:john@company.com                          │
│  IP Address:    192.168.1.100                                  │
│  Action:        agent.tool.execute                             │
│  Resource:      agent:sales-bot                                │
│  Workspace:     ws:marketing-team                              │
│                                                                 │
│  Details:                                                       │
│    Tool:        web_search                                     │
│    Input:       "competitor pricing analysis"                  │
│    Output:      [hash: sha256:a1b2c3...]                      │
│    Duration:    1.2s                                           │
│    Tokens:      1,247                                          │
│    Cost:        $0.0124                                        │
│                                                                 │
│  Approval:                                                      │
│    Required:    false (within allowlist)                       │
│    Policy:      pol_marketing_standard                         │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Log Features

- **Immutable**: Write-once, append-only storage
- **Tamper-evident**: Cryptographic chaining
- **Searchable**: Full-text search across all fields
- **Exportable**: JSON, CSV, or streaming to SIEM
- **Configurable retention**: 90 days to 7 years

### SIEM Integration

Real-time log streaming to your security tools:

- Splunk (HEC)
- Datadog
- Elastic / OpenSearch
- Sumo Logic
- Custom webhooks (CEF/LEEF format)

### Compliance Reports

Pre-built reports for common audits:

- User access reviews
- Agent activity summaries
- Permission change history
- Failed authentication attempts
- Budget utilization
- Data access patterns

---

## Data Security

### Encryption

| Layer | Standard | Details |
|-------|----------|---------|
| **At Rest** | AES-256-GCM | All database fields, file storage, backups |
| **In Transit** | TLS 1.3 | All API traffic, webhooks, internal communication |
| **Application** | Field-level | Sensitive fields (credentials, PII, API keys) |

### Key Management

- **Managed keys**: We manage encryption keys securely
- **BYOK**: Bring Your Own Key support
- **KMS Integration**: AWS KMS, Azure Key Vault, GCP Cloud KMS
- **Key rotation**: Automatic rotation on configurable schedule
- **HSM support**: Hardware security modules for high-security deployments

### Data Residency

Choose where your data lives:

| Region | Data Center |
|--------|-------------|
| US | AWS us-east-1, us-west-2 |
| EU | AWS eu-west-1, eu-central-1 |
| APAC | AWS ap-southeast-1 |
| Custom | Your own infrastructure (self-hosted) |

- Data sovereignty controls
- Cross-border transfer policies
- Regional encryption keys
- Compliance certifications per region

### PII Protection

- Automatic PII detection in agent inputs/outputs
- Configurable redaction rules
- Data masking in logs and exports
- Right to deletion (GDPR Article 17)
- Data minimization policies

---

## Agent Governance

### Approval Workflows

```text
┌─────────────────────────────────────────────────────────────────┐
│  APPROVAL FLOW                                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Agent Action                                                   │
│       │                                                         │
│       ▼                                                         │
│  ┌─────────────────┐                                           │
│  │  Policy Check   │                                           │
│  └────────┬────────┘                                           │
│           │                                                     │
│     ┌─────┴─────┐                                              │
│     │           │                                              │
│     ▼           ▼                                              │
│  Allowed    Needs Approval                                     │
│     │           │                                              │
│     │           ▼                                              │
│     │    ┌─────────────┐                                       │
│     │    │  Approvers  │                                       │
│     │    │  Notified   │                                       │
│     │    └──────┬──────┘                                       │
│     │           │                                              │
│     │     ┌─────┴─────┐                                        │
│     │     │           │                                        │
│     │     ▼           ▼                                        │
│     │  Approved    Rejected                                    │
│     │     │           │                                        │
│     └──┬──┘           │                                        │
│        │              │                                        │
│        ▼              ▼                                        │
│     Execute        Logged                                      │
│        │                                                        │
│        ▼                                                        │
│     Logged                                                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Policy Configuration

```yaml
# Example approval policy
agent_policy:
  name: "Marketing Team Standard"

  # Automatically allowed actions
  allowlist:
    - "web_search:*"
    - "file_read:/marketing/*"
    - "api_call:analytics.company.com/*"

  # Actions requiring approval
  approval_required:
    - "file_write:*"
    - "api_call:external/*"
    - "database:*"
    - cost_above: 10.00

  # Blocked actions
  blocklist:
    - "code_execute:*"
    - "api_call:*.competitor.com/*"

  # Approvers
  approvers:
    - role: "workspace_admin"
    - user: "marketing-lead@company.com"

  # Escalation
  escalation:
    timeout: "4h"
    escalate_to: "org_admin"
```

### Resource Limits

| Resource | Limit Options |
|----------|---------------|
| **Tokens** | Per request, per hour, per day, per month |
| **Cost** | Per agent, per workspace, per organization |
| **API Calls** | Rate limits per endpoint |
| **Execution Time** | Timeout per task |
| **Concurrent Tasks** | Max parallel executions |

### Budget Controls

- Set budgets at any level (org, workspace, agent)
- Alert thresholds (e.g., 80% of budget)
- Hard caps that block execution
- Budget rollover policies
- Cost attribution reports

---

## Compliance Certifications

### Current Certifications

| Certification | Status |
|---------------|--------|
| SOC 2 Type II | Certified |
| GDPR | Compliant |
| HIPAA | BAA Available |
| ISO 27001 | In Progress |
| CCPA | Compliant |

### SOC 2 Type II

Our SOC 2 report covers all five trust service criteria:

- **Security**: Access controls, encryption, vulnerability management
- **Availability**: Uptime SLAs, disaster recovery, redundancy
- **Processing Integrity**: Data validation, error handling, audit trails
- **Confidentiality**: Data classification, access controls, encryption
- **Privacy**: PII handling, consent management, data retention

### GDPR Compliance

- Data Processing Agreement (DPA) available
- Sub-processor list maintained
- Right to access/rectification/erasure supported
- Data portability (standard export formats)
- Privacy by design principles
- Data Protection Officer (DPO) appointed

### HIPAA Compliance

- Business Associate Agreement (BAA) available
- PHI encryption at rest and in transit
- Access controls and audit logging
- Automatic logoff and session management
- Breach notification procedures

---

## Deployment Options

### Cloud (Managed)

Let us handle the infrastructure:

- **Regions**: US, EU, APAC
- **SLA**: 99.9% uptime
- **Backups**: Automatic daily, 30-day retention
- **Updates**: Zero-downtime deployments
- **Support**: 24/7 for Enterprise plans

### Self-Hosted

Full control on your infrastructure:

- **Docker**: Single-node or Docker Compose
- **Kubernetes**: Helm charts available
- **Air-gapped**: Fully offline deployments
- **Requirements**: PostgreSQL 14+, Redis 6+

### Hybrid

Best of both worlds:

- Control plane in our cloud
- Data plane in your infrastructure
- Your data never leaves your network

---

## Support & SLAs

### Support Tiers

| Tier | Response Time | Channels | Included In |
|------|---------------|----------|-------------|
| **Community** | Best effort | GitHub, Discord | Free |
| **Standard** | 24h (business) | Email, Chat | Team |
| **Priority** | 4h (business) | Email, Chat, Phone | Business |
| **Premium** | 1h (24/7) | Dedicated Slack, Phone | Enterprise |

### SLA Guarantees

| Plan | Uptime SLA | Service Credits |
|------|------------|---------|
| Team | 99.5% | 10% per 0.1% below |
| Business | 99.9% | 25% per 0.1% below |
| Enterprise | 99.99% | 50% per 0.01% below |

### Enterprise Support Includes

- Dedicated Customer Success Manager
- Quarterly business reviews
- Priority feature requests
- Custom integration support
- Security review participation
- Training and onboarding

---

## Pricing

### Plans Overview

| Feature | Team | Business | Enterprise |
|---------|------|----------|------------|
| Users | Up to 20 | Up to 100 | Unlimited |
| Agents | 10 | 50 | Unlimited |
| Workspaces | 3 | 10 | Unlimited |
| SSO | OIDC only | SAML + OIDC | Full SSO suite |
| Audit logs | 90 days | 1 year | Custom retention |
| Support | Standard | Priority | Premium |
| SLA | 99.5% | 99.9% | 99.99% |

### Enterprise Add-ons

- HIPAA compliance package
- Dedicated infrastructure
- Custom data residency
- Advanced security features
- Professional services

---

## Getting Started

```text
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│   Ready to deploy OpenCompany in your organization?            │
│                                                                 │
│   ┌─────────────────────────────────────────────────────────┐  │
│   │                                                         │  │
│   │   [ Schedule a Demo ]      [ Contact Sales ]            │  │
│   │                                                         │  │
│   │   [ Security Whitepaper ]  [ Request SOC 2 Report ]    │  │
│   │                                                         │  │
│   └─────────────────────────────────────────────────────────┘  │
│                                                                 │
│   Or self-host today:                                          │
│                                                                 │
│   $ git clone https://github.com/opencompany/opencompany       │
│   $ docker-compose -f docker-compose.enterprise.yml up         │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## FAQ

**Q: Can we run OpenCompany in our own cloud?**
Yes. OpenCompany supports self-hosted deployments on any infrastructure—AWS, GCP, Azure, on-premises, or air-gapped environments.

**Q: Do you sign BAAs for HIPAA compliance?**
Yes. Business Associate Agreements are available for Business and Enterprise plans.

**Q: How long are audit logs retained?**
Configurable from 90 days to 7+ years depending on your compliance requirements.

**Q: Can we use our own encryption keys?**
Yes. BYOK (Bring Your Own Key) is supported, with integration to AWS KMS, Azure Key Vault, and GCP Cloud KMS.

**Q: Is there a free trial for Enterprise?**
Yes. Contact sales for a 30-day Enterprise trial with full features.

---

*For detailed technical security documentation, see [Enterprise Security & Governance](../strategy/enterprise-security.md).*
