# Contributing to OpenCompany

Status: Accepted prospective community contribution policy; publication workflow and automation
remain to be implemented and reviewed.
Last verified: 2026-09-01.

Issues, design discussions, reproducible bug reports, documentation, integrations, and pull
requests are welcome. OpenCompany uses an inbound-equals-outbound model:

- contributions to AGPL-covered application paths are provided under `AGPL-3.0-only`;
- contributions to explicitly Apache-2.0 protocols, schemas, SDKs, integration kits, examples, and
  templates are provided under Apache-2.0; and
- documentation contributions use the license declared for that documentation path.

Contributors retain their copyright. The project does not infer copyright assignment, a private
relicensing grant, or permission to issue proprietary exceptions from a pull request.

## Developer Certificate of Origin

Every commit intended for merge must certify the
[Developer Certificate of Origin 1.1](https://developercertificate.org/) with a Signed-off-by line:

```text
Signed-off-by: Your Name <you@example.com>
```

Use `git commit -s` to add it. Signing off means you have the right to submit the contribution under
the destination's stated license; it is not a copyright assignment.

## Provenance and AI-assisted work

Submit only original work or material whose permission, source, and license are documented and
compatible. Disclose material AI assistance when it affects provenance review. Do not submit:

- credentials, auth state, customer data, private prompts or transcripts, or production logs;
- generated material whose rights or source cannot be explained;
- copied third-party code, tests, assets, datasets, or model output without compatible terms and
  attribution; or
- code that moves a proprietary Cloud implementation into an open-source path or AGPL core code
  into an Apache-only artifact.

Reviewers must verify destination license, third-party notices, test evidence, security impact, and
the Signed-off-by chain before merge. The planned automation should enforce these checks; this
document does not claim that automation exists today.

See [Licensing FAQ](LICENSING.md), [Commercial Use](COMMERCIAL_USE.md), and [Trademark and
Compatibility Policy](TRADEMARKS.md). This policy is not legal advice.
