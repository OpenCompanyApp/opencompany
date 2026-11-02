# Licensing FAQ

Status: Prospective licensing guidance for a future release distributed with the current root
`LICENSE.md`; historical releases retain the license actually shipped with them.
Last verified: 2026-11-02.

## What license does OpenCompany use?

A future OpenCompany release that includes the current [LICENSE.md](LICENSE.md) will distribute the
complete self-hosted application under **GNU Affero General Public License v3.0 only**
(`AGPL-3.0-only`). AGPL is an OSI-approved open-source license.

This working-tree decision is prospective until reviewed, committed, and released. A tag, archive,
container, package, or binary remains governed by the license and notices shipped with that exact
version. The license text controls; this FAQ does not replace or modify it.

## Can my company self-host OpenCompany?

Yes. AGPL permits commercial and non-commercial use, study, modification, self-hosting,
redistribution, and operation, subject to its conditions. The source license does not impose user,
workspace, agent, automation, or Run limits and does not require an OpenCompany Cloud account.

Support, managed infrastructure, service levels, and Cloud consumption may have commercial prices,
but those prices do not narrow the rights granted by the AGPL-covered source.

## Can I offer OpenCompany as a hosted service?

Yes. OpenCompany is choosing genuine open source rather than reserving competing hosting rights. If
you modify the covered Program and users interact with that modified version over a network, AGPL
section 13 requires you to offer those users the Corresponding Source of that version through the
network interaction. Conveying copies or combined works also invokes the applicable AGPL source,
notice, and copyleft requirements.

Whether a particular deployment, plugin, linked module, or combination forms one covered work is a
legal question. Obtain qualified counsel for a specific architecture.

## Can I white-label, embed, or redistribute OpenCompany?

The copyright license does not prohibit white-label, OEM, embedded, competing, or commercial use.
You must satisfy AGPL obligations for covered and combined works and preserve required notices. The
AGPL does not grant a general right to use the OpenCompany name, logo, or official-service identity;
see the [Trademark and Compatibility Policy](TRADEMARKS.md).

A fork or hosted service should use its own primary brand and may accurately state that it is based
on or compatible with OpenCompany.

## Must I publish all of my surrounding application code?

Not automatically merely because it communicates with OpenCompany over a documented protocol.
Independent services, clients, and integrations can remain separate works. Code that modifies,
copies, embeds, or links with AGPL-covered implementation may have different obligations.

OpenCompany will make reusable protocols, schemas, SDKs, integration/provider kits, examples, and
templates available under Apache-2.0 where their path notices explicitly say so. That permissive
boundary is designed for independent applications and for the shared OpenCompany/Bowerbird
integration ecosystem.

### Current Apache-2.0 path notices

A path is Apache-2.0 only if it carries its own notice. In this repository today:

| Path | Contents | Notice |
|---|---|---|
| `resources/code-docs/` | Code Mode guides and examples for the `opencompany-code-v1` Ruby profile | [`resources/code-docs/LICENSE`](resources/code-docs/LICENSE) |

The Apache-2.0 text is in [`LICENSES/Apache-2.0.txt`](LICENSES/Apache-2.0.txt).

Official clients and SDKs live outside this repository under Apache-2.0:

| Repository | Contents | License |
|---|---|---|
| `opencompany-cli` | The Rust `oc` CLI: terminal client and connected-host daemon, using only the public versioned API and realtime stream ([plan](docs/planning/oc-cli-and-hosts.md)) | Apache-2.0, by that repository's own notices |

Standalone protocol/schema packages and other SDKs do not exist yet; they will carry their own
notices when published. The former `integrations` package repository is archived read-only and keeps
the terms in its own notices; OpenCompany no longer depends on it.

## What remains proprietary?

OpenCompany Cloud operations, billing, metering, abuse prevention, hosted-fleet automation, and
company-internal systems may remain proprietary when they are separate works across documented
interfaces. The private Cloud cannot silently patch or copy the AGPL application into a private
fork or use a feature flag, license key, detached notice, or repository name to remove rights from
AGPL-covered files.

The accepted default is a complete open-source self-hosted application, not a crippled community
edition. There is no generic proprietary enterprise notice overriding the root license.

## What happened to prior BSL and FSL versions?

Nothing changes retroactively:

- versions published under Business Source License 1.1 retain their shipped Additional Use Grant,
  Change Date, and GPL future license;
- any version actually published under FSL-1.1-ALv2 retains its FSL terms and Apache future-license
  grant; and
- AGPL-3.0-only applies only to a version distributed with the reviewed AGPL license and notices.

Git history and release artifacts must preserve these distinctions. The current branch's earlier
FSL preparation was not itself proof that an FSL release was published.

## How are contributions licensed?

The community default is inbound equals outbound. Contributors retain copyright and certify origin
and authority with the [Developer Certificate of Origin 1.1](https://developercertificate.org/) and
a Signed-off-by line. Contributions to AGPL paths are accepted under AGPL-3.0-only; contributions to
explicit Apache-2.0 paths are accepted under Apache-2.0.

OpenCompany is not using a broad copyright-assignment CLA or implied proprietary relicensing grant.
See [CONTRIBUTING.md](CONTRIBUTING.md).

## References

- [GNU Affero General Public License v3](https://www.gnu.org/licenses/agpl-3.0.html)
- [OSI AGPL-3.0 approval](https://opensource.org/license/agpl-3-0)
- [Apache License 2.0](https://www.apache.org/licenses/LICENSE-2.0)
- [Open Source Definition](https://opensource.org/osd)
- [Developer Certificate of Origin 1.1](https://developercertificate.org/)

Historical license references:

- [Business Source License 1.1](https://mariadb.com/bsl11/)
- [FSL-1.1-ALv2](https://github.com/getsentry/fsl.software/blob/main/FSL-1.1-ALv2.template.md)

## Who should I contact?

For official Cloud, support, migration, certification, partnerships, trademark permission, or a
specific commercial arrangement, contact:

- enterprise@opencompany.app

This FAQ is product guidance, not legal advice.
