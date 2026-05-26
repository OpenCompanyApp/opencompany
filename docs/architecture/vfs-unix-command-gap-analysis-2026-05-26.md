# VFS Unix Command Gap Analysis

Date: 2026-05-26

## Scope

This audit compares the current OpenCompany VFS command executor against the unix-like command surfaces found in the cloned VFS inspiration repos, with Mirage as the main baseline. The goal is not to make the VFS execute host commands. The goal is to make agent UX feel reliably unix-like: commands either work with expected flags or fail with a precise, permission-aware, structured unsupported error and a better OpenCompany-native suggestion.

Sources inspected:

| Repo | Commit | What was inspected | Command relevance |
| --- | --- | --- | --- |
| `inspiration/mirage` | `5e5674a` | `python/mirage/commands/spec/builtin_specs.py`, command folders, shell helper code | Primary command and flag baseline |
| `inspiration/filer` | `4f4015a` | `src/shell/shell.js`, README shell API | Small shell helper surface |
| `inspiration/lightning-fs` | `f8a3439` | `src/index.js` | Filesystem API only, no shell commands |
| `inspiration/zenfs` | `2f3326f` | mount/config and node API files | Filesystem API only, no shell commands |
| `inspiration/memfs` | `74c3560` | node fs promise API | Filesystem API only, no shell commands |
| `inspiration/unionfs` | `2e40b35` | union mount API | Filesystem API only, no shell commands |
| `inspiration/browserfs` | `76fd512` | backend and emulation APIs | Filesystem API only, no shell commands |
| `inspiration/pyfakefs` | `b907b2b` | fake fs/path/os/shutil APIs | Filesystem API only, no shell commands |
| OpenCompany | current `feat/vfs` worktree | `app/Domain/Vfs/Core/*`, `docs/tools/vfs.md` | Current executor, catalog, docs, parser |

## Executive Summary

OpenCompany already has a strong core VFS shell shape: `pwd`, `cd`, `ls`, `tree`, `find`, `cat`, `head`, `tail`, `wc`, `stat`, `file`, `du`, path transforms, `grep`, `rg`, `jq`, common text transforms, checksums, write commands, pipes, `;`, `&&`, `||`, simple globs, and stdout/stderr redirection.

The main gap is breadth and contract consistency. Mirage supports roughly 78 commands and many flags. OpenCompany supports roughly 50 commands, but several are intentionally smaller subsets. To make the AX feel "crazy good", OpenCompany should adopt a two-part compatibility contract:

1. **Recognize every Mirage command and flag** in the command catalog and parser.
2. For each recognized item, either **implement the safe VFS behavior** or **return a deliberate unsupported/permission error** with a direct alternative, such as `vfs_patch`, direct integration tools, or Lua helpers.

This avoids the worst AX failure mode: an agent tries familiar unix syntax and gets a surprising parser miss, misleading support discovery, or a different result inside a conditional chain.

## Current OpenCompany Coverage

Current command catalog:

`pwd`, `cd`, `ls`, `tree`, `find`, `cat`, `head`, `tail`, `wc`, `stat`, `file`, `du`, `realpath`, `dirname`, `basename`, `grep`, `rg`, `search`, `echo`, `true`, `false`, `printf`, `sort`, `uniq`, `cut`, `awk`, `xargs`, `tr`, `nl`, `rev`, `paste`, `jq`, `sed`, `diff`, `cmp`, `comm`, `sha256sum`, `sha1sum`, `md5sum`, `checksum`, `which`, `type`, `command -v`, `env`, `printenv`, `date`, `mkdir`, `touch`, `cp`, `mv`, `rm`/`rmdir`, `truncate`, `tee`.

Current shell syntax:

| Feature | OpenCompany today | Gap |
| --- | --- | --- |
| Pipes | Supported, quote-aware split | Good |
| `;` | Supported | Good |
| `&&` / `||` | Supported and skipped branches are preflighted | Good concept, but preflight coverage drifts for some flags |
| Redirection | `>`, `>>`, `1>`, `1>>`, `/dev/null`, `2>/dev/null`, `2>&1` | No stdin redirect, heredoc, here-string, or stderr file targets |
| Quoting/escaping | Single/double quotes and backslash handling | Good enough for VFS shell, but not tree-sitter-bash level |
| Globs | One-parent `*`, `?`, character classes; quoted globs stay literal | Missing recursive `**` and first-class `glob` command/API surface |
| Unsupported shell features | `$VAR`, `$()`, backticks, process substitution, subshells, background `&` rejected | Correct for safety |

## Drift Inside OpenCompany

These are not Mirage gaps; they are internal consistency gaps that will make agent UX feel unreliable.

| Area | Current drift | Suggested fix |
| --- | --- | --- |
| `mkdir -p` | Executor accepts `-p`, but skipped-branch preflight routes `mkdir` through no-flag validation; catalog does not advertise it | Make route preflight, executor, catalog, docs, and tests share one command-spec source |
| `jq -r/-c` | Executor accepts `-r`, `-c`, long aliases; skipped-branch preflight validates `jq` with no flags | Move jq flag support into shared command spec |
| `tee -a` | Executor accepts `-a`; skipped-branch preflight validates `tee` with no flags | Move tee flag support into shared command spec |
| `echo -e` | Executor accepts `-e`, but catalog says only `-n` | Update catalog from shared command spec |
| `truncate -s` | Supported and preflighted, but broader docs should clarify only size `0` is supported | Keep as explicit safe subset |
| Support discovery | `which`, `type`, `/tools/vfs/commands.json`, docs, and runtime validation can drift | Generate catalog/docs/tests from command specs |

Best architectural correction: introduce a single VFS command spec registry that owns aliases, flags, value options, command class, safety class, permission behavior, docs text, and test cases. `RoutesVfsCommands`, `VfsCommandCatalog`, `/tools/vfs.md`, Lua docs, and preflight validation should consume it rather than duplicating allowed flags.

## Mirage Command And Flag Gap Table

Legend:

- **Covered**: OpenCompany implements the command and the important Mirage flags.
- **Partial**: command exists but flags or semantics are meaningfully narrower.
- **Missing**: command is not implemented as a VFS shell command.
- **Reject intentionally**: command or flag should be recognized and rejected for safety or product boundary reasons.

| Command | Mirage flags/features | OpenCompany today | Gap | Recommended OpenCompany approach |
| --- | --- | --- | --- | --- |
| `ls` | `-l -a -A -h -t -S -r -1 -R -d -F` | `-1 -a -l -R` | Partial | Add `-A`, `-F`, `-d`, `-h`, `-t`, `-S`, `-r`. Sorting flags should work over VFS entries. `-h` can affect displayed byte values only when size exists. |
| `stat` | `-c`, `-f` templates | JSON stat only | Partial | Add a safe stat template subset for common `%n`, `%s`, `%F`, `%y`, `%m`, `%i`-style placeholders. Keep JSON default because it is better for agents. |
| `pwd` | `-P`, `-L` | no flags | Partial | Accept `-P`/`-L` as no-op aliases because VFS paths are already canonical/logical. |
| `find` | `-name -type -maxdepth -size -mtime -iname -path -mindepth -print -print0 -delete -prune -ls -empty -o -or -a -and -not`, parens ignored | `-name -type -maxdepth --max-depth -print` | Partial | Add read-only predicates first: `-iname`, `-path`, `-mindepth`, `-empty`, `-print0`, `-ls`, boolean combinators. Recognize `-size`, `-mtime` when stat data is available. Reject `-delete` and `-exec` with permission-aware guidance to direct tools. |
| `tree` | `-a -L -I -d -P` | `-L`/`--level` | Partial | Add hidden/all handling, directory-only, include/exclude globs. Preserve budgets and truncation metadata. |
| `du` | `-h -s -a --max-depth -c` | byte size of one readable path | Partial | Add directory traversal using `stat`/read sizes where available, human output, summary, all files, max depth, grand total. |
| `cat` | `-n` | no flags | Partial | Add line numbering. |
| `head` | `-n`, numeric shorthand, `-c` | Covered plus long aliases and byte suffixes | Covered | Keep. |
| `tail` | `-n`, numeric shorthand, `-c`, `-q`, `-v`, `-f/--follow` | lines/bytes and `+N`; no `-q/-v/-f` | Partial | Add `-q`/`-v` header behavior. Recognize `-f/--follow` and reject with "streaming follow is not supported by bounded VFS exec" unless a future watch API exists. |
| `wc` | `-l -w -c -m -L` | `-l -w -c` | Partial | Add chars and max line length. |
| `md5` | file digest | `md5sum` only | Partial | Add `md5` alias. |
| `diff` | `-i -w -b -e -u -q -r` | simple compare, no flags | Partial | Add `-q`, `-u`, whitespace/case flags. Reject `-e`. Add recursive directory diff later after directory reads are stronger. |
| `file` | `-b -i` | no flags | Partial | Add brief and MIME output. |
| `python`, `python3` | `-c` | missing | Reject intentionally | Recognize and reject as host/interpreter execution. Suggest Lua helpers or direct tools. |
| `nl` | `-b -v -i -w -s` | default line numbering only | Partial | Add common formatting flags. |
| `grep` | `-r -R -i -I -v -n -c -l -w -F -E -o -q -H -h -m -A -B -C -e` | `-c -l -n -r -R -i -o -m --max-count --max-depth` | Partial | This is a P1 gap. Add invert, word, fixed/extended mode, quiet status, file-prefix controls, context lines, binary-ignore no-op, and explicit `-e` pattern. |
| `rg` | `-i -v -n -c -l -w -F -o -m -A -B -C --hidden --type --glob` | `-c -l -n -i -o -m --max-count --max-depth`; regex default | Partial | Add parity with grep plus `--glob`; treat `--hidden` as accepted no-op unless hidden entries are modeled; `--type` maps to content format/type when possible. |
| `sort` | `-r -n -u -f -k -t -h -V -s -M` | `-r -n -u -k` | Partial | Add delimiter/key support with `-t`, case fold, human/version/month sorts, stable mode. |
| `uniq` | `-c -d -u -f -s -i -w` | `-c -d -u` | Partial | Add field/char skips, case-insensitive compare, width compare. |
| `cut` | `-f -d -c --complement -z` | `-f -d` | Partial | Add ranges, character mode, complement. Recognize `-z` and reject or implement NUL records consistently with `find -print0`. |
| `mkdir` | `-p -v` | `-p` executor only, preflight/catalog drift | Partial | Fix drift, add verbose. |
| `touch` | `-c -r -d` | no flags | Partial | Add no-create and date/reference timestamp where adapters support metadata; otherwise accept and no-op timestamp safely for content-only files. |
| `cp` | `-r -R -a -f -n -v` | no flags; `/files` only | Partial | Add no-clobber/force/verbose. Add recursive only for `/files` directories after directory copy semantics are explicit. `-a` can alias recursive+preserve where supported. |
| `mv` | `-f -n -v` | no flags; `/files` only | Partial | Add no-clobber/force/verbose. |
| `rm` | `-r -R -f -v -d` | no flags; `/files` only | Partial | Add force/verbose/directory. Add recursive with strong permission and approval handling; never allow silent broad deletes outside write-enabled adapters. |
| `sed` | `-i -e -n -E` | substitution only, no flags, stdin only | Partial | Add `-e`, `-n`, basic print/substitute expressions, file args. Keep `-i` rejected with `vfs_patch` suggestion because patch remains a separate tool. |
| `echo` | `-n -e` | Covered, catalog drift on `-e` | Covered with drift | Fix catalog/docs. |
| `tee` | `-a` | Covered, preflight drift | Covered with drift | Fix shared spec. |
| `tr` | `-d -s -c` | translate and `-d` | Partial | Add squeeze and complement. |
| `curl` | `-H -A -X -d -F -o -L -s -S --jina` | missing | Reject intentionally | Keep network outside VFS. Recognize and reject with `web_fetch`, integration tools, or browser suggestions. |
| `wget` | `-O -q --spider` | missing | Reject intentionally | Same as `curl`. |
| `jq` | `-r -c -s` | `-r -c`, NDJSON/file args/safe subset | Partial | Add slurp `-s`; fix preflight drift. |
| `awk` | `-F -v -f` | `-F` and `{print $N}` only | Partial | Add `-v` variables for simple print expressions. Reject external `-f` file programs or support VFS file programs only after sandboxing expression subset. |
| `paste` | `-d -s` | tab merge only | Partial | Add delimiter and serial mode. |
| `tac` | reverse file/stdin lines | missing | Missing | Add simple read-transform. |
| `printf` | format args | Covered | Covered | Keep. |
| `seq` | `-s -w -f` | missing | Missing | Add bounded sequence generator. |
| `base64` | `-d -D -w` | missing | Missing | Add encode/decode over stdin/files with max bytes. |
| `sha256sum` | `-c` | digest only | Partial | Add check mode. |
| `xxd` | `-r -p -l -c -s -g -u` | missing | Missing | Add bounded hex dump; add reverse plain hex only if useful. |
| `tar` | `-c -x -t -z -j -J -v -f -C --strip-components --exclude` | missing | Later / restricted | Recognize. Listing/extracting archives is useful later for `/files`; creation/extraction should be permission-aware and bounded. |
| `gzip` | `-d -k -f -c -1..-9` | missing | Later | Add only when binary `/files` support is strong. |
| `gunzip` | `-k -f -c -t` | missing | Later | Same as gzip. |
| `zip` | `-r -j -q` | missing | Later | Same as tar. |
| `unzip` | `-o -l -d -q -p -t` | missing | Later | Prioritize `unzip -l` and `unzip -p`; extraction needs write permission. |
| `basename` | no flags | Covered | Covered | Keep. |
| `dirname` | no flags | Covered | Covered | Keep. |
| `realpath` | `-e -m` | normalize only | Partial | Add `-e` existence check and `-m` missing-ok normalization. |
| `readlink` | `-f -e -m -n` | missing | Missing | Add after symlink/path-alias contract is defined. `-f/-e/-m` can map to realpath behavior; `-n` suppresses newline. |
| `ln` | `-s -f -n -v` | missing | Missing | Add symbolic links only for `/files` or VFS aliases after adapter contract supports link entries. Reject hard links. |
| `split` | `-l -b -n -d -a` | missing | Missing | Add later for `/files` writes; requires multi-output write semantics and permissions. |
| `patch` | `-p -R -i -N` | missing as shell command; direct `vfs_patch` exists | Reject intentionally | Keep as separate tool. Recognize `patch` and reject with `vfs_patch` suggestion so agents do not try shell patching. |
| `shuf` | `-n -e -z -r` | missing | Missing | Add bounded deterministic/random shuffle; consider seeded behavior for reproducibility. |
| `comm` | `-1 -2 -3 --check-order --nocheck-order` | common lines only | Partial | Implement real three-column `comm` and suppress flags. |
| `column` | `-t -s -o` | missing | Missing | Add table formatting for agent-readable output. |
| `fold` | `-w -s` | missing | Missing | Add text wrap. |
| `fmt` | `-w` | missing | Missing | Add paragraph reflow. |
| `cmp` | `-l -s -n -b -i` | basic differs message | Partial | Add silent, byte limit, skip offsets, byte diagnostics. |
| `iconv` | `-f -t -c -o` | missing | Later | Only needed when binary/encoding extraction is in scope. Recognize and reject for now. |
| `strings` | `-n` | missing | Later | Useful after binary files/extraction; low priority now. |
| `rev` | no flags | Covered | Covered | Keep. |
| `zcat` | no flags | missing | Later | Needs compressed file read support. |
| `zgrep` | grep flags over gzip | missing | Later | Needs compressed file read support; can delegate to gzip+grep later. |
| `mktemp` | `-d -p -t` | missing | Later / restricted | Useful for scripts if `/tmp` exists in `/files`; add only with temp lifecycle and permissions. |
| `bc` | `-l -q` | missing | Missing | Add small calculator or reject in favor of Lua. Useful but not VFS-specific. |
| `expr` | expressions | missing | Missing | Add simple POSIX expression evaluator or reject in favor of Lua. |
| `history` | `-c` | missing | Product-specific | If command history is exposed, implement read/clear through permissions. Otherwise recognize and reject. |
| `date` | `-d -u -I -R` | current ISO time only | Partial | Add UTC, ISO/RFC output, and safe parse/format `-d`. |
| `csplit` | `-f -n -b -k -s` | missing | Later | Requires multi-output write semantics. |
| `expand` | `-t -i` | missing | Missing | Add tabs-to-spaces transform. |
| `unexpand` | `-t -a` | missing | Missing | Add spaces-to-tabs transform. |
| `tsort` | topological sort | missing | Missing | Add text transform. |
| `look` | `-f` | missing | Missing | Add prefix search over sorted stdin/file; low effort. |
| `sleep` | seconds | missing | Reject intentionally | Do not block agent workers through VFS shell. Recognize and reject or cap to tiny no-op only if tests need it. |
| `bash` | `-c -s -l -i -e -u -x --login --norc --noprofile --posix` | missing | Reject intentionally | Recognize and reject. VFS shell is not a general shell or host process runner. |
| `join` | `-t -1 -2 -a -v -e -o` | missing | Missing | Add relational line join for text files. |

## Highest-Impact Gaps To Close First

P1 should focus on common agent muscle memory and commands that reduce the need for bespoke tools.

| Priority | Work | Why |
| --- | --- | --- |
| P1 | Shared command spec registry | Eliminates drift between execution, skipped branch preflight, docs, catalog, and Lua docs |
| P1 | `grep`/`rg` parity: `-v -w -F -E -q -H -h -A -B -C -e --glob` | Search is the core VFS advantage over embeddings for precise retrieval |
| P1 | `find` parity for read-only predicates and output modes | Agents will rely on `find` for large VFS folders |
| P1 | `ls`, `tree`, `du`, `cat`, `wc`, `file`, `stat` flags | Basic filesystem intuition |
| P1 | Mutation flags: `mkdir -p/-v`, `touch -c/-d/-r`, `cp/mv -f/-n/-v`, `rm -f/-r/-v/-d` | Agents expect these when working in `/files` |
| P1 | Missing small text transforms: `tac`, `seq`, `base64`, `column`, `fold`, `fmt`, `expand`, `unexpand` | Cheap to implement and useful in pipelines |
| P1 | Recognize-and-reject unsafe commands: `bash`, `python`, `curl`, `wget`, `sleep`, `patch`, `find -delete`, `sed -i` | Better AX than "unsupported command" surprises; keeps permission model clear |

## Other Repo Findings

Mirage is the only inspiration repo with broad unix command coverage. The others are still useful for adapter and VFS semantics, but they do not materially expand the shell command list.

| Repo | Command/API surface | OpenCompany gap |
| --- | --- | --- |
| Filer | Shell helpers: `cd`, `pwd`, `find`, `ls`, `exec`, `touch`, `cat`, `rm`, `tempDir`, `mkdirp` | `mkdirp` maps to `mkdir -p`; ensure it is consistently supported. `rm recursive=true` maps to `rm -r`; missing. `tempDir` suggests a future `/tmp` or `/files/tmp` convention. `exec` should not map to host execution. |
| LightningFS | `readFile`, `writeFile`, `unlink`, `readdir`, `mkdir`, `rmdir`, `rename`, `stat`, `lstat`, `readlink`, `symlink`, `du`, `backFile`, `flush` | Future adapter contract should include `lstat`, `readlink`, `symlink`, `du`, flush/sync, and explicit backing-file/cache behavior where relevant. |
| ZenFS | Node-like fs plus mount/umount, nested mounts, overlay/backends, mount-with-mkdir | Future adapter contract should support mount metadata, mount ordering, and backend capability descriptors. No shell commands to add. |
| BrowserFS | Node fs emulation and backends including overlay, zip, HTTP, IndexedDB | Inspiration for adapter-backed mounts and zip/HTTP-backed read-only files. No shell commands to add. |
| memfs | Node fs in memory with broad fs APIs | Inspiration for tests and in-memory adapter. No shell commands to add. |
| unionfs | Union/mount composition | Inspiration for adapter overlay precedence. No shell commands to add. |
| pyfakefs | Python fake filesystem/os/path/shutil semantics | Inspiration for semantic tests around path resolution, symlinks, stat/lstat, shutil-like copy/move. No shell commands to add. |

## Suggested Compatibility Contract

OpenCompany should not blindly clone Mirage's behavior. It should clone the useful unix affordances and make the unsafe boundaries explicit.

Recommended command states:

| State | Meaning | Examples |
| --- | --- | --- |
| Implemented | Safe, bounded, permission-aware behavior exists | `grep -A`, `find -iname`, `cat -n`, `du -h`, `seq` |
| Accepted no-op compatibility | Flag has no meaningful VFS distinction but common commands should not fail | `pwd -P`, `pwd -L`, maybe `rg --hidden` until hidden entries exist |
| Recognized unsupported | Parser knows it and returns a targeted error | `bash -c`, `python -c`, `curl`, `wget`, `sleep`, `patch`, `sed -i` |
| Permission-gated | Command can mutate only after the same tool permission/approval as direct tools | `rm -r`, `cp -R`, `tee`, redirection to `/files` |
| Later binary/archive phase | Useful but depends on binary file support, extraction, or archive boundaries | `tar`, `zip`, `unzip`, `gzip`, `zgrep`, `strings`, `iconv` |

For AX, unsupported should still be "covered" in discovery:

```text
$ which patch
patch: recognized but not supported by vfs_exec; use vfs_patch for targeted document/file edits.

$ curl https://example.com
curl: network commands are outside VFS; use web_fetch or an integration tool.
```

## Parser And Shell Feature Gaps

Mirage uses tree-sitter-bash helpers and models more shell grammar, including redirects, heredocs, here-strings, glob parsing, and careful redirect binding around `&&`/`||`.

OpenCompany should stay simpler, but add these targeted improvements:

| Feature | Suggested approach |
| --- | --- |
| `&&` / `||` | Keep current behavior. Add regression tests that skipped branches still validate supported flags through the shared spec. |
| Heredoc / here-string | Defer. They are useful but increase parser complexity. If added, only feed bounded stdin to VFS commands; never host shell. |
| Stdin redirect `< file` | Consider adding before heredoc because it is common and maps cleanly to `cat file | command`. |
| Recursive glob `**` | Add budgeted recursive glob expansion. It is valuable for `rg **/*.md`, `ls **/*.pdf`, and future integration mounts. |
| Brace expansion | Keep unsupported. Agents can use `find`, `xargs`, or Lua. |
| Variables / command substitution | Keep unsupported. It makes the VFS shell too much like a real shell and weakens predictability. |
| Stderr file redirects | Keep unsupported or route only to `/files` with explicit write permission. Current explicit rejection is acceptable. |

## Permission-Aware Semantics

Every new command should be classified before implementation:

| Class | Examples | Permission behavior |
| --- | --- | --- |
| Browse/read | `ls`, `find`, `stat`, `cat`, `grep`, `du`, `file` | Adapter read permission and budgets |
| Pure transform | `sort`, `uniq`, `cut`, `base64`, `seq`, `column` | No VFS write permission unless reading file args |
| Write | `mkdir`, `touch`, `cp`, `mv`, `tee`, stdout redirection | `vfs_write` permission and approval behavior |
| Destructive | `rm`, `truncate`, archive extraction overwrite | `vfs_write` plus destructive operation classification; approval when required |
| External side effect | `curl`, `wget`, `bash`, `python` | Not VFS commands; reject and point at direct tools |

Write flags cannot bypass policy. `rm -f` should suppress not-found errors only after command permission is allowed. `cp -n` should reduce writes, not bypass authorization. Recursive flags must be budgeted and emit `returned`, `skipped`, `truncated`, and per-path failures.

## Recommended Implementation Shape

1. Add `VfsCommandSpec` and `VfsCommandSpecRegistry` under `app/Domain/Vfs/Core`.
2. Move command aliases, supported flags, value options, long flags, safety class, permission class, and docs descriptions into specs.
3. Change `assertCommandGroupSupported()` and `assertSupportedOptions()` to use specs for every command, including skipped `&&`/`||` branches.
4. Generate `VfsCommandCatalog::all()` from specs.
5. Generate `/tools/vfs/commands.json`, `/tools/vfs.md`, and Lua docs command references from the same specs.
6. Add a fixture test that iterates the Mirage command list and asserts each command is either implemented or recognized with the expected unsupported reason.
7. Add per-command tests for common unix examples, especially conditionals:

```sh
false && jq -r .name /files/data.json || echo fallback
true || mkdir -p /files/x
find /docs -iname '*.md' -print0 | xargs -0 ...
grep -R -n -A2 -B2 -e invoice /docs
```

`xargs -0` is not in the current OpenCompany or Mirage spec, but if `find -print0` is added, `xargs -0` should be added too or `find -print0` will be much less useful.

## Recommended Roadmap

| Phase | Scope |
| --- | --- |
| Phase 1: Spec unification | Shared command specs, catalog/docs generation, preflight drift fixes, recognize-and-reject unsafe Mirage commands |
| Phase 2: Core unix parity | `ls/tree/find/grep/rg/du/cat/wc/file/stat/date` flags |
| Phase 3: Text tool belt | `tac`, `seq`, `base64`, `column`, `fold`, `fmt`, `expand`, `unexpand`, `join`, `comm`, `cmp`, `sort`, `uniq`, `cut`, `tr`, `paste` parity |
| Phase 4: `/files` mutation parity | `cp`, `mv`, `rm`, `mkdir`, `touch`, recursive and no-clobber flags, temp directory convention |
| Phase 5: Binary/archive phase | `tar`, `zip`, `unzip`, `gzip`, `zcat`, `zgrep`, `strings`, `xxd`, `iconv` after binary/extraction scope is ready |
| Phase 6: Future adapter semantics | `readlink`, `ln -s`, `lstat`, mounts, overlay behavior, integration-backed read/write capability declarations |

The best OpenCompany-specific target is not "Mirage clone". It is: Mirage-level command familiarity, OpenCompany-level permissions, structured metadata, budgets, and direct-tool handoffs where a real unix command would be unsafe or outside the VFS domain.
