# Script history browser qualification

This is a **mocked-API browser fixture**, not authenticated Laravel or local
database/cutover evidence. It loads the isolated checkout's actual built Vue
assets at `http://opencompany.test`, intercepts every browser request, and never
passes an application request to the running checkout. No credentials or saved
login state are needed for this synthetic page.

From the isolated mruby checkout, run `npm run build:typecheck`, then serve only
its `public` directory in a separate terminal:

```sh
php -S 127.0.0.1:18743 -t public
```

Use the repository's named browser session (do not replace another active
owner's session):

```sh
playwright-cli -s=browser open about:blank --persistent
playwright-cli -s=browser run-code --filename=scripts/testing/mruby-history-browser.js
playwright-cli -s=browser snapshot
playwright-cli -s=browser close
```

Verified on 2026-09-08: initial history requests contain metadata only; inspection
preserves exact source whitespace and escapes HTML; loading requires an explicit
button and closes the dialog; pagination advances; dark mode applies; none of
these actions issue an automation save/run request. Manual inspection also
confirmed that the selected body appears in the editor and the dark-mode source
dialog remains readable.

The fixture deliberately blocks presence writes and external icon requests, so
their network errors and unconfigured realtime warnings are expected. They are
not hidden and do not establish production network health. Source authorization
and workspace isolation are separately covered by `MrubyScriptHistoryTest`.
