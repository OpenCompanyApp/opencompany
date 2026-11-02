# mruby dependency advisory triage

Audited 2026-09-08 with `composer audit --format=json` against the locked
`feat/mruby` application. The command reported 29 advisories across nine locked
packages: 1 critical, 9 high, 17 medium, 1 low, and 1 with no supplied severity.
No package update was performed for this audit.

## Migration attribution

The mruby branch adds only `bowerbird/ruby-engine` at `0.1.0-rc.1` and switches
the local integration path repositories. Comparing the current lockfile with
`origin/dev:composer.lock` shows that every package reported by Composer has the
same locked version on both branches. Composer reported no advisory for
`bowerbird/ruby-engine`.

Accordingly, all 29 findings are inherited lockfile debt, not advisories
introduced by the mruby dependency change. This is version comparison evidence,
not a claim that any package is unexploitable.

| Package | Locked version (mruby = dev) | Advisories | Severity count | Local reachability evidence |
| --- | --- | ---: | --- | --- |
| `guzzlehttp/guzzle` | `7.10.4` | 9 | 1 high, 8 medium | Laravel's HTTP facade is used by Code Mode-reachable MCP dispatch (`McpProxyTool` → `McpRuntime` → `McpClient`); Composer identifies Laravel as a Guzzle consumer. This does not establish an advisory precondition. |
| `guzzlehttp/psr7` | `2.10.1` | 4 | 4 medium | Transitive requirement of locked Guzzle; same MCP/Laravel HTTP path above. |
| `laravel/framework` | `v12.60.2` | 1 | 1 medium | Core application runtime; local source calls `URL::temporarySignedRoute` for Telegram linking. No Code Mode-specific signed-URL path was established. |
| `league/commonmark` | `2.8.2` | 10 | 8 high, 2 medium | Laravel requires it. No direct `League\\CommonMark` use was found under `app/` or `config/`. |
| `mtdowling/jmespath.php` | `2.8.0` | 1 | 1 critical | Transitive from `aws/aws-sdk-php`; no direct application reference was found. |
| `paragonie/sodium_compat` | `v2.5.0` | 1 | severity unspecified | Transitive from `pusher/pusher-php-server`; no direct application reference was found. |
| `symfony/http-foundation` | `v7.4.8` | 1 | 1 medium | Laravel framework runtime dependency. |
| `symfony/polyfill-intl-idn` | `v1.37.0` | 1 | 1 low | Transitive from `egulias/email-validator` and `symfony/mime`; no direct application reference was found. |
| `symfony/routing` | `v7.4.12` | 1 | 1 medium | Laravel framework runtime dependency; signed-route use is noted above. |

## Primary advisory records

- Guzzle: [high host validation](https://github.com/advisories/GHSA-v5mv-p594-2x33), [cookie-domain scope](https://github.com/advisories/GHSA-f7vp-7xgx-4w4r), [fragment Referer disclosure](https://github.com/advisories/GHSA-h95v-h523-3mw8), [host-only cookie scope](https://github.com/advisories/GHSA-wm3w-8rrp-j577), [cookie DoS](https://github.com/advisories/GHSA-f283-ghqc-fg79), [IP cookie injection](https://github.com/advisories/GHSA-g446-98w2-8p5w), [proxy authorization](https://github.com/advisories/GHSA-94pj-82f3-465w), [dot-only domains](https://github.com/guzzle/guzzle/security/advisories/GHSA-cwxw-98qj-8qjx), and [HTTPS proxy downgrade](https://github.com/guzzle/guzzle/security/advisories/GHSA-wpwq-4j6v-78m3).
- PSR-7: [host validation](https://github.com/advisories/GHSA-c2w2-prh8-qm98), [start-line CRLF](https://github.com/guzzle/psr7/security/advisories/GHSA-vm85-hxw5-5432), [URI host CRLF](https://github.com/guzzle/psr7/security/advisories/GHSA-hq7v-mx3g-29hw), and [authority reinterpretation](https://github.com/guzzle/psr7/security/advisories/GHSA-34xg-wgjx-8xph).
- Laravel: [temporary signed URL path confusion](https://github.com/advisories/GHSA-crmm-hgp2-wgrp).
- CommonMark: [distinct attributes DoS](https://github.com/advisories/GHSA-8rr7-cvq3-gmfh), [SmartPunct/attributes DoS](https://github.com/advisories/GHSA-jjv6-8j6v-6j52), [attribute filter bypass](https://github.com/advisories/GHSA-f8fg-pg57-v4j8), [fence/reference/emphasis DoS](https://github.com/advisories/GHSA-j8pm-gj4c-rq4x), [nested XML DoS](https://github.com/advisories/GHSA-mj63-m3rc-8ppr), [heading slug DoS](https://github.com/advisories/GHSA-mh25-x5hq-wrqp), [footnote DoS](https://github.com/advisories/GHSA-jfm3-95jq-q3rf), [inline attributes DoS](https://github.com/advisories/GHSA-g2gp-3wwq-f4ph), [quadratic parsing DoS](https://github.com/advisories/GHSA-2q4p-g7hv-5rgv), and [unsafe-link control bytes](https://github.com/advisories/GHSA-29pj-957v-52mc).
- JMESPath: [compiler runtime injection](https://github.com/jmespath/jmespath.php/security/advisories/GHSA-pcw8-m77r-2528).
- Sodium compatibility: [Ed25519 public-key validation](https://github.com/paragonie/sodium_compat/pull/206).
- Symfony: [HttpFoundation SSRF bypass](https://symfony.com/cve-2026-48736), [IDN equivalence](https://symfony.com/cve-2026-46644), and [routing dot-segment handling](https://symfony.com/cve-2026-48784).

## Remaining release gate

This branch should not be presented as dependency-audit clean. A dependency
owner must choose framework-compatible fixed versions, update the lockfile in a
separate reviewed change, run the relevant application/runtime tests, and rerun
`composer audit --format=json` until this report no longer lists these locked
versions. In particular, Composer's current affected ranges require at least
Guzzle `7.15.2`, PSR-7 `2.12.3`, Laravel `12.61.1`, CommonMark `2.10.0`,
JMESPath `2.9.1`, sodium-compat `2.5.1`, Symfony HttpFoundation/routing
`7.4.13`, and polyfill-intl-idn `1.38.1` where the surrounding constraints
permit them. Those are remediation thresholds from this audit, not changes made
in this branch.
