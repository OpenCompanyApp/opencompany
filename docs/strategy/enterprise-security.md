# Enterprise Security & Governance

> **OpenCompany's Key Differentiator**: While OpenClaw targets individual power users with a "local-first, security-optional" approach, OpenCompany is built for enterprise teams requiring mandatory approval flows, comprehensive audit trails, and compliance-ready governance.

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Current State](#current-state)
3. [Planned Features](#planned-features)
4. [Required Enterprise Features](#required-enterprise-features)
5. [Implementation Priority](#implementation-priority)
6. [Compliance Framework Mapping](#compliance-framework-mapping)

---

## Executive Summary

### The Enterprise AI Agent Problem

Organizations deploying AI agents at scale face unique challenges:

- **Accountability**: Who approved this agent's action? When? Why?
- **Cost Control**: How do we prevent runaway API costs?
- **Data Security**: What data can agents access? Where does it go?
- **Compliance**: How do we prove governance to auditors?
- **Risk Management**: How do we prevent agents from taking harmful actions?

### OpenCompany vs OpenClaw

| Aspect | OpenClaw | OpenCompany |
|--------|----------|-------------|
| **Target User** | Individual power user | Business teams & enterprises |
| **Security Model** | Opt-in, local trust | Mandatory approval flows |
| **Data Storage** | Local JSONL files | Database-backed with encryption |
| **Audit Trail** | Minimal | Comprehensive, immutable |
| **Access Control** | Per-workspace files | RBAC with team scoping |
| **Compliance** | N/A | SOC 2, GDPR, HIPAA ready |
| **Cost Controls** | Token counting | Budgets, quotas, alerts |
| **Approvals** | CLI/socket-based | UI-driven with audit trails |

### Key Enterprise Value Propositions

1. **Control**: Every agent action is governed by policies you define
2. **Visibility**: Complete audit trail of all agent activities
3. **Compliance**: Built-in support for regulatory requirements
4. **Scale**: Multi-team deployment with proper isolation
5. **Integration**: Fits into existing enterprise security stack

---

## Current State

### What's Implemented

#### Approval Workflow System
- **ApprovalRequest Model** with 4 types: `budget`, `action`, `spawn`, `access`
- Full lifecycle tracking: `pending` → `approved`/`rejected`
- Records requester, responder, timestamp, and amount
- API endpoints for managing approvals

#### Document-Level Permissions
- Basic role-based access: `viewer` and `editor` per document
- User-level permission tracking
- Foreign key integrity

#### Activity Tracking
- Activity model with type, description, actor, metadata
- ActivityStep for task progression tracking
- JSON metadata for extensibility
- Timestamp-based audit trail

#### User Role System
- User types: `human` vs `agent`
- Agent types: `manager`, `writer`, `analyst`, `creative`, `researcher`, `coder`, `coordinator`
- Manager-subordinate relationships via `manager_id`
- Presence indicators: `online`, `away`, `busy`, `offline`

#### Authentication
- Email/password authentication
- Password reset functionality
- Email verification capability
- Session management

---

## Planned Features

> Features documented in existing planning docs but not yet implemented.

### Workspace Isolation & Multi-Tenancy
**Priority**: HIGH | **Phase**: 1

- Multi-workspace support with team scoping
- Workspace-level budget allocation
- Agent isolation by workspace
- Credential vault per workspace
- Cross-workspace visibility controls

### Granular Tool/Action Approval System
**Priority**: HIGH | **Phase**: 1

```
ApprovalPolicy:
  level: "allowlist" | "approval_required" | "blocked"
  patterns: ["read:*", "write:documents/*", "execute:safe-commands/*"]
  requires_approval_above: { cost: 10, risk: "medium" }
  approvers: [role: "manager", user_ids: [...]]
```

- Three security levels: `deny`, `allowlist`, `full`
- Pattern-based command allowlisting with glob matching
- Cost-based approval thresholds
- Role-based approver assignment
- Default allowlist of safe operations

### Cost Attribution & Budgets
**Priority**: MEDIUM | **Phase**: 1

- Per-agent and per-workspace cost tracking
- Monthly budget limits with hard caps
- Per-task cost breakdown (input tokens, output tokens, model, cost)
- Alert thresholds at 80% usage
- Cost rollup by team, project, and agent

### Session Management Policies
**Priority**: MEDIUM | **Phase**: 2

- Session scoping: `shared`, `per_user`, `per_channel`
- Reset policies: `daily`, `idle-based`, `manual`
- Retention settings (days + max tokens)
- Auto-compaction for context management

### Context Management Services
**Priority**: MEDIUM | **Phase**: 3

- **ContextWindowGuard**: Monitor and enforce token limits
- **MemoryFlushService**: Pre-compaction memory preservation
- **SessionPruningService**: TTL-based cleanup
- **ToolKindClassifier**: Classify operations for approval routing
- **ExecutionApprovalService**: Centralized approval logic

---

## Required Enterprise Features

> Features NOT yet in planning docs but REQUIRED for enterprise deployment.

### Identity & Access Management (IAM)

#### SSO/SAML/OIDC Integration
**Priority**: P0 - Required for enterprise pilot

| Feature | Description | Benefit |
|---------|-------------|---------|
| SAML 2.0 | Enterprise SSO standard | Integrates with existing IdP |
| OIDC | Modern OAuth-based SSO | Flexible, API-friendly |
| Just-in-time provisioning | Auto-create users on first login | Zero admin overhead |
| Attribute mapping | Map IdP claims to roles | Automated role assignment |

#### Multi-Factor Authentication (MFA)
**Priority**: P0

- TOTP authenticator app support
- SMS/email fallback (configurable)
- Hardware key support (FIDO2/WebAuthn)
- MFA enforcement policies by role
- Remember device options

#### Directory Synchronization
**Priority**: P1

- Azure Active Directory sync
- Okta integration
- Google Workspace sync
- LDAP/AD direct connection
- Automated group → role mapping
- Scheduled sync with conflict resolution

#### Session Security
**Priority**: P0

- Configurable session timeouts
- Concurrent session limits
- Forced logout (admin-triggered)
- Session activity monitoring
- Geographic anomaly detection

#### Network Controls
**Priority**: P1

- IP allowlisting per workspace
- VPN requirement enforcement
- Geographic access restrictions
- API access controls

---

### Role-Based Access Control (RBAC)

#### Hierarchical Role System
**Priority**: P0

```
Roles Hierarchy:
├── Super Admin (platform-wide)
├── Organization Admin
│   ├── Workspace Admin
│   │   ├── Agent Manager
│   │   ├── User Manager
│   │   └── Viewer
│   └── Billing Admin
└── Custom Roles
```

#### Permission Model
**Priority**: P0

| Resource | Permissions |
|----------|-------------|
| Agents | create, read, update, delete, execute, approve |
| Documents | create, read, update, delete, share |
| Workspaces | create, read, update, delete, manage_members |
| Users | invite, remove, update_role, view |
| Budgets | set, view, override |
| Audit Logs | view, export |
| Settings | read, update |

#### Policy Features
**Priority**: P1

- Custom role creation
- Permission inheritance (workspace → agent)
- Time-based access (temporary elevated access)
- Resource-level overrides
- Policy-as-code (JSON/YAML definitions)
- Policy versioning and rollback

---

### Audit & Compliance

#### Immutable Audit Logs
**Priority**: P0

Every action generates an audit record:

```json
{
  "event_id": "uuid",
  "timestamp": "ISO8601",
  "actor": { "type": "user|agent|system", "id": "...", "ip": "..." },
  "action": "agent.execute_tool",
  "resource": { "type": "agent", "id": "...", "workspace": "..." },
  "details": { "tool": "web_search", "input": "...", "output_hash": "..." },
  "outcome": "success|failure|blocked",
  "approval": { "required": true, "approved_by": "...", "policy": "..." }
}
```

Features:
- Write-once, append-only storage
- Cryptographic chaining (tamper-evident)
- Retention policies (configurable, minimum 1 year)
- Search and filtering
- Export capabilities (JSON, CSV)

#### SIEM Integration
**Priority**: P1

- Real-time event streaming
- Splunk HEC integration
- Datadog logs integration
- Elastic/OpenSearch support
- Custom webhook destinations
- Standardized event format (CEF, LEEF)

#### Compliance Reporting
**Priority**: P1

- Pre-built compliance dashboards
- Automated evidence collection
- Policy violation alerts
- Remediation tracking
- Auditor access (read-only, time-limited)

#### Data Residency
**Priority**: P1

- Region selection per workspace
- Data sovereignty controls
- Cross-border transfer policies
- Local encryption key storage
- Residency compliance reporting

---

### Data Security

#### Encryption
**Priority**: P0

| Layer | Standard | Details |
|-------|----------|---------|
| At Rest | AES-256-GCM | All database fields, file storage |
| In Transit | TLS 1.3 | All API, webhook, internal traffic |
| Application | Field-level | Sensitive fields (credentials, PII) |

#### Key Management
**Priority**: P1

- AWS KMS / Azure Key Vault / GCP KMS integration
- Bring Your Own Key (BYOK) support
- Automatic key rotation
- Key access auditing
- HSM support for high-security deployments

#### PII Protection
**Priority**: P1

- Automatic PII detection in agent I/O
- Configurable redaction rules
- Data masking in logs
- Right to deletion support (GDPR)
- Data minimization policies

#### Data Classification
**Priority**: P2

- Classification labels (Public, Internal, Confidential, Restricted)
- Auto-classification rules
- Label-based access policies
- Handling instructions per classification
- DLP integration hooks

#### Credential Vault
**Priority**: P0

- Encrypted credential storage
- Per-agent credential access
- Credential rotation policies
- Access auditing
- Integration with external vaults (HashiCorp Vault)

---

### Agent Governance

#### Sandboxing Levels
**Priority**: P0

| Level | Description | Use Case |
|-------|-------------|----------|
| **Strict** | All actions require approval | High-risk operations |
| **Standard** | Allowlist + approval for unknowns | Normal operation |
| **Trusted** | Expanded allowlist, audit only | Vetted agents |
| **Isolated** | No external access | Internal-only tasks |

#### Tool Capability Matrix
**Priority**: P0

Define what each agent can do:

```yaml
agent_policy:
  tools:
    web_search: { allowed: true, rate_limit: "100/hour" }
    code_execution: { allowed: false }
    file_write: { allowed: true, paths: ["/workspace/*"] }
    api_call: { allowed: true, domains: ["api.internal.com"] }
    database: { allowed: true, operations: ["read"] }
```

#### Output Guardrails
**Priority**: P1

- Content filtering (PII, profanity, sensitive topics)
- Output length limits
- Format validation
- Confidence thresholds
- Human review triggers
- Output sampling for QA

#### Prompt Injection Protection
**Priority**: P1

- Input sanitization
- Prompt boundary enforcement
- Injection pattern detection
- Anomaly alerting
- Automated response blocking

#### Model Access Controls
**Priority**: P1

- Approved model allowlist per workspace
- Model cost tiers
- Fallback model policies
- Model version pinning
- A/B testing controls

#### Resource Limits
**Priority**: P0

| Resource | Limit Type | Enforcement |
|----------|------------|-------------|
| Tokens | Per request, per day, per month | Hard cap + alerts |
| API Calls | Per minute, per hour | Rate limiting |
| Cost | Per agent, per workspace, per org | Budget controls |
| Execution Time | Per task | Timeout |
| Concurrent Tasks | Per agent | Queue management |

---

### Operational Security

#### API Security
**Priority**: P0

- API key management (create, rotate, revoke)
- Key scoping (workspace, permissions)
- Rate limiting per key
- Usage monitoring and alerts
- IP restrictions per key

#### Rate Limiting & DDoS Protection
**Priority**: P0

- Configurable rate limits
- Automatic throttling
- DDoS mitigation (CDN integration)
- Abuse detection
- Automatic blocking

#### Webhook Security
**Priority**: P1

- HMAC signature verification
- Timestamp validation (replay prevention)
- IP allowlisting for senders
- Payload encryption option
- Delivery retry with backoff

#### Secret Management
**Priority**: P0

- Environment variable encryption
- Secret rotation policies
- Access logging
- Integration secret management
- Zero-trust secret delivery

#### Vulnerability Management
**Priority**: P2

- Dependency scanning integration
- Container image scanning
- SAST/DAST hooks
- Vulnerability disclosure process
- Patch management policies

---

### Disaster Recovery & Business Continuity

#### Backup & Restore
**Priority**: P1

- Automated daily backups
- Point-in-time recovery (PITR)
- Backup encryption
- Backup integrity verification
- Restore testing automation

#### High Availability
**Priority**: P1

- Multi-AZ deployment
- Automatic failover
- Load balancing
- Health monitoring
- Zero-downtime deployments

#### Geographic Redundancy
**Priority**: P2

- Cross-region replication
- Active-passive failover
- DNS-based routing
- Data sync monitoring
- Regional isolation option

#### Incident Response
**Priority**: P1

- Documented response playbooks
- Automated alerting
- Communication templates
- Post-incident review process
- SLA commitments

---

## Implementation Priority

### P0: Must-Have for Enterprise Pilot

| Feature | Rationale |
|---------|-----------|
| SSO/SAML | Enterprises won't adopt without IdP integration |
| MFA | Security baseline requirement |
| Basic RBAC | Need role separation from day 1 |
| Immutable Audit Logs | Compliance and accountability |
| Encryption (rest + transit) | Data security baseline |
| Agent Sandboxing | Control over agent actions |
| Credential Vault | Secure integration management |
| API Key Management | Developer access control |
| Rate Limiting | Prevent abuse and cost overruns |
| Resource Limits | Budget and usage control |

### P1: Required for Production

| Feature | Rationale |
|---------|-----------|
| Directory Sync | Scale user management |
| Custom Roles | Flexible permission model |
| SIEM Integration | Enterprise monitoring stack |
| Data Residency | Regulatory compliance |
| Key Management (KMS) | Enterprise key control |
| PII Protection | GDPR/privacy compliance |
| Prompt Injection Protection | Security hardening |
| Webhook Security | Secure integrations |
| Backup & Restore | Data protection |
| HA Deployment | Uptime requirements |

### P2: Competitive Advantage

| Feature | Rationale |
|---------|-----------|
| Data Classification | Advanced governance |
| Geographic Redundancy | Global enterprise needs |
| Vulnerability Scanning | Security maturity |
| Policy-as-Code | DevOps integration |
| Output Guardrails | Quality control |
| Model Access Controls | Cost optimization |

### P3: Future Roadmap

| Feature | Rationale |
|---------|-----------|
| BYOK | Highly regulated industries |
| HSM Support | Government/finance |
| Advanced DLP | Data loss prevention |
| Federated Identity | Multi-org scenarios |

---

## Compliance Framework Mapping

### SOC 2 Type II

| Trust Principle | OpenCompany Controls |
|-----------------|---------------------|
| **Security** | SSO, MFA, RBAC, encryption, audit logs, vulnerability management |
| **Availability** | HA deployment, backup/restore, incident response |
| **Processing Integrity** | Input validation, output guardrails, approval workflows |
| **Confidentiality** | Data classification, encryption, access controls |
| **Privacy** | PII detection, data residency, retention policies |

### GDPR

| Requirement | OpenCompany Feature |
|-------------|---------------------|
| Lawful basis | Consent management, audit trails |
| Data minimization | Retention policies, PII redaction |
| Right to access | Data export capabilities |
| Right to erasure | Deletion workflows, cascade rules |
| Data portability | Standard export formats |
| Security | Encryption, access controls, breach detection |
| Data transfers | Residency controls, transfer logging |

### HIPAA (Healthcare)

| Safeguard | OpenCompany Control |
|-----------|---------------------|
| Access Control | RBAC, MFA, session management |
| Audit Controls | Immutable logs, SIEM integration |
| Integrity Controls | Encryption, tamper detection |
| Transmission Security | TLS 1.3, encrypted webhooks |
| BAA Support | Enterprise agreement option |

### ISO 27001

| Domain | Relevant Features |
|--------|-------------------|
| A.5 Security Policies | Policy-as-code, RBAC |
| A.6 Organization | Role hierarchy, responsibilities |
| A.7 Human Resources | Directory sync, offboarding |
| A.8 Asset Management | Data classification, inventory |
| A.9 Access Control | RBAC, MFA, SSO |
| A.10 Cryptography | Encryption, key management |
| A.12 Operations Security | Logging, monitoring, backup |
| A.13 Communications | TLS, network controls |
| A.16 Incident Management | Response playbooks, alerting |
| A.18 Compliance | Audit reports, evidence collection |

---

## Conclusion

OpenCompany's enterprise security and governance capabilities are designed to meet the rigorous requirements of regulated industries and security-conscious organizations. By implementing the features outlined in this document, OpenCompany will provide:

1. **Trust**: Comprehensive controls that enterprises can rely on
2. **Compliance**: Ready-made mappings to major frameworks
3. **Visibility**: Complete audit trail and monitoring
4. **Control**: Granular governance over all agent activities
5. **Integration**: Fits into existing enterprise security ecosystems

This positions OpenCompany as the enterprise-grade alternative to consumer-focused AI agent platforms, enabling organizations to safely deploy AI agents at scale.
