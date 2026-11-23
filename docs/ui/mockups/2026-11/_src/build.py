#!/usr/bin/env python3
"""
Build the 2026-11 full-screen mockups into self-contained HTML files.

Why a generator: every mockup repeats the same 260px rail and the same token design system.
Pages are authored once as fragments in `pages/<slug>.html`; this script inlines `tokens.css`,
renders the rail for the page's active item, adds the mockup chrome (top bar, theme toggle,
notes strip) and writes `../<slug>.html`. The OUTPUT files need no build and no network: open
them directly in a browser. Re-run `python3 build.py` after editing a fragment.

Fragment format: an HTML comment holding JSON metadata, followed by the screen body.

    <!--meta
    {"title": "Home", "area": "Messenger", "order": 10, "rail": "home",
     "layout": "three" | "two" | "solo" | "free",
     "ctx_w": 380, "height": 900, "exploration": false,
     "summary": "one line for the index card",
     "notes": [["k", "Title", "Text", "docs/... §cite"], ...]}
    -->
    <main class="main">...</main><aside class="ctx">...</aside>

Note kinds: k (needs you / key), p (primary), s (success), a (agent), q (open question), "" plain.
"""
import json
import re
import sys
from pathlib import Path

SRC = Path(__file__).resolve().parent
OUT = SRC.parent

# ---------------------------------------------------------------------------------------------
# Tokens. Dark is the default (as in the accepted sidebar mockup); light follows the OS unless
# the viewer forces a theme with the toggle (data-theme on <html>).
# ---------------------------------------------------------------------------------------------
COMMON = """--font-sans:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
--font-mono:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;--radius:10px;
--primary:#3b82f6;--primary-foreground:#fff;--warning:#f59e0b;--warning-foreground:#1b1300;
--success:#22c55e;--info:#3b82f6;--danger:#ef4444;
--ada:#7c5cff;--bram:#e0812c;--cleo:#1f9d8a;--finn:#5b6b7f;--sanne:#3b82f6;--jonas:#94a3b8;--rutger:#0f766e;--ext:#a16207;
--chart-1:#3b82f6;--chart-2:#14b8a6;--chart-3:#f59e0b;--chart-4:#a855f7;"""
DARK = """--desk:#050506;--background:#0b0b0c;--foreground:#ececee;--muted-foreground:#9a9aa3;--subtle-foreground:#6e6e78;
--card:#141416;--card-2:#18181b;--popover:#1b1b1f;--border:#2a2a2e;--border-strong:#3c3c43;--secondary:#232327;
--secondary-foreground:#ececee;--hover:#1c1c20;--code-bg:#101012;--scrim:rgba(0,0,0,.55);--phone-bezel:#26262b;
--primary-soft:rgba(59,130,246,.16);--primary-text:#93b4fb;--primary-border:rgba(59,130,246,.45);
--warning-soft:rgba(245,158,11,.14);--warning-text:#fbbf24;--warning-border:rgba(245,158,11,.45);
--success-soft:rgba(34,197,94,.13);--success-text:#4ade80;--success-border:rgba(34,197,94,.4);
--danger-soft:rgba(239,68,68,.14);--danger-text:#f87171;--danger-border:rgba(239,68,68,.45);
--ada-soft:rgba(124,92,255,.20);--ada-text:#b3a1ff;--ada-border:rgba(124,92,255,.55);
--bram-soft:rgba(224,129,44,.18);--bram-text:#f0a560;--cleo-soft:rgba(31,157,138,.2);--cleo-text:#4fd1bd;
--shadow:0 18px 50px rgba(0,0,0,.55);--shadow-sm:0 1px 2px rgba(0,0,0,.4);color-scheme:dark;"""
LIGHT = """--desk:#e4e4e8;--background:#fafafa;--foreground:#18181b;--muted-foreground:#66666f;--subtle-foreground:#8e8e97;
--card:#ffffff;--card-2:#f7f7f8;--popover:#ffffff;--border:#e4e4e7;--border-strong:#d0d0d6;--secondary:#f1f1f3;
--secondary-foreground:#18181b;--hover:#f4f4f5;--code-bg:#f6f6f7;--scrim:rgba(24,24,27,.32);--phone-bezel:#1f1f23;
--primary-soft:rgba(59,130,246,.11);--primary-text:#1d4ed8;--primary-border:rgba(59,130,246,.4);
--warning-soft:rgba(245,158,11,.13);--warning-text:#a14d05;--warning-border:rgba(217,119,6,.45);
--success-soft:rgba(34,197,94,.12);--success-text:#15803d;--success-border:rgba(22,163,74,.4);
--danger-soft:rgba(239,68,68,.1);--danger-text:#c2261d;--danger-border:rgba(220,38,38,.4);
--ada-soft:rgba(124,92,255,.12);--ada-text:#5b3fd6;--ada-border:rgba(124,92,255,.5);
--bram-soft:rgba(224,129,44,.14);--bram-text:#a5520c;--cleo-soft:rgba(31,157,138,.13);--cleo-text:#0f766e;
--shadow:0 18px 50px rgba(24,24,27,.16);--shadow-sm:0 1px 2px rgba(24,24,27,.08);color-scheme:light;"""

TOKENS = (
    ":root{" + COMMON + DARK + "}\n"
    "@media (prefers-color-scheme: light){:root:not([data-theme=\"dark\"]){" + LIGHT + "}}\n"
    ":root[data-theme=\"light\"]{" + LIGHT + "}\n"
    ":root[data-theme=\"dark\"]{" + DARK + "}\n"
)


def css() -> str:
    return (SRC / "tokens.css").read_text().replace("/*TOKENS*/", TOKENS)


# ---------------------------------------------------------------------------------------------
# The rail (Appendix A of docs/planning/ui-structure-and-agent-editing.md, accepted 2026-11-02).
# `active` is a key such as "home", "agent:ada", "sales:tables", "team", "admin".
# `open` names the expanded Space (default "sales").
# ---------------------------------------------------------------------------------------------
SPACES = {
    "sales": ("Sales", [("issues", "◇", "Issues", "12"), ("tables", "▦", "Tables", ""), ("docs", "≡", "Docs", ""),
                        ("files", "▤", "Files", "Drive"), ("artifacts", "◩", "Artifacts", "3"),
                        ("routines", "↻", "Routines", "5"), ("deals", "◈", "Deals", "CRM")]),
    "engineering": ("Engineering", [("issues", "◇", "Issues", "31"), ("tables", "▦", "Tables", ""), ("docs", "≡", "Docs", ""),
                                    ("files", "▤", "Files", ""), ("artifacts", "◩", "Artifacts", "2"),
                                    ("routines", "↻", "Routines", "4")]),
    "operations": ("Operations", [("issues", "◇", "Issues", "9"), ("tables", "▦", "Tables", ""), ("docs", "≡", "Docs", ""),
                                  ("files", "▤", "Files", "Drive"), ("artifacts", "◩", "Artifacts", "1"),
                                  ("routines", "↻", "Routines", "6")]),
}


def rail(active: str = "home", open_space: str = "sales", agent_status: dict | None = None) -> str:
    st = {"ada": ("work", "editing Leads…", "2"), "bram": ("needd", "", "!"), "cleo": ("on", "idle", ""),
          "finn": ("off", "paused", "")}
    if agent_status:
        st.update(agent_status)

    def row(key, inner, cls=""):
        a = " active" if key == active else ""
        return f'<div class="row{a}{(" " + cls) if cls else ""}">{inner}</div>'

    def agent(key, letter, name, comp):
        dot, status, badge = st[key]
        b = ""
        if badge == "!":
            b = '<span class="badge need">!</span>'
        elif badge:
            b = f'<span class="badge unread">{badge}</span>'
        s = f'<span class="status">{status}</span>' if status else ""
        c = '<span class="tag" title="Has its own computer">🖥</span>' if comp else ""
        return row(f"agent:{key}",
                   f'<span class="av agent {key}">{letter}<i class="dot {dot}"></i></span>{name}{c}'
                   f'<span class="r">{s}{b}</span>')

    h = ['<nav class="rail">',
         '<div class="ws"><div class="logo">A</div>Acme <span class="chev">▾</span><div class="new" title="New chat">✎</div></div>',
         '<div class="search">⌕ Search or jump to… <kbd>⌘K</kbd></div>',
         row("home", '<span class="ic">◉</span>Home<span class="r"><span class="badge need">3 need you</span></span>'),
         '<div class="sec">Pinned</div>',
         row("chan:launch-q4", '<span class="ic">#</span>launch-q4<span class="r"><span class="badge unread">4</span></span>'),
         row("pin:leads", '<span class="ic">▦</span>Leads<span class="r"><span class="pc">Sales</span></span>'),
         row("pin:ops-142", '<span class="ic">◇</span>OPS-142 Renew insurance'),
         '<div class="sec">Agents <span>+ hire</span></div>',
         agent("ada", "A", "Ada", True), agent("bram", "B", "Bram", True), agent("cleo", "C", "Cleo", False),
         agent("finn", "F", "Finn", False),
         '<div class="sec">People</div>',
         row("person:sanne", '<span class="av sanne">S<i class="dot on"></i></span>Sanne<span class="r"><span class="badge unread">1</span></span>'),
         row("person:jonas", '<span class="av jonas">J<i class="dot off"></i></span>Jonas'),
         '<div class="sec">Groups &amp; channels</div>',
         row("chan:general", '<span class="ic">#</span>general'),
         row("chan:sales", '<span class="ic">#</span>sales'),
         row("group:abs", '<span class="ic">◎</span>Ada, Bram, Sanne'),
         '<div class="sec">Apps <span>+ add</span></div>',
         row("app:mail", '<span class="ic">✉</span>Mail<span class="r"><span class="badge unread">12</span></span>'),
         row("app:crm", '<span class="ic">◈</span>CRM<span class="r"><span class="pc">8 deals due</span></span>'),
         '<div class="sec">Spaces <span>+</span></div>']
    for skey, (sname, tools) in SPACES.items():
        if skey == open_space:
            h.append(row(f"{skey}:overview", f'<span class="ic">▾</span><b>{sname}</b>'))
            for tkey, ic, label, pc in tools:
                r = f'<span class="r"><span class="pc">{pc}</span></span>' if pc else ""
                h.append(row(f"{skey}:{tkey}", f'<span class="ic">{ic}</span>{label}{r}', "sub"))
        else:
            h.append(row(f"{skey}:overview", f'<span class="ic">▸</span>{sname}'))
    h += ['<div class="spacer"></div><div class="foot">',
          row("team", '<span class="ic">◍</span>Team'),
          row("admin", '<span class="ic">⚙</span>Admin'),
          row("me", '<span class="av rutger">R<i class="dot on"></i></span>Rutger<span class="r"><span class="pc">⋯</span></span>'),
          '</div></nav>']
    return "\n".join(h)


SCRIPT = """<script>
(function(){
  var root=document.documentElement, KEY='oc-mock-theme';
  function set(t){ if(t==='system'){root.removeAttribute('data-theme');} else {root.setAttribute('data-theme',t);}
    try{localStorage.setItem(KEY,t);}catch(e){}
    document.querySelectorAll('[data-set-theme]').forEach(function(b){b.classList.toggle('on',b.getAttribute('data-set-theme')===t);}); }
  var saved='system'; try{saved=localStorage.getItem(KEY)||'system';}catch(e){}
  var q=new URLSearchParams(location.search).get('theme'); if(q){saved=q;}
  set(saved);
  document.addEventListener('click',function(e){var b=e.target.closest('[data-set-theme]'); if(b){set(b.getAttribute('data-set-theme'));}});
  /* Fit the fixed 1440px screen to narrow windows; the mockup itself is a desktop design. */
  function fit(){ var w=document.querySelectorAll('.fit'); var avail=window.innerWidth-48;
    w.forEach(function(el){ var z=Math.min(1,avail/1440); el.style.zoom=z<1?z:''; }); }
  window.addEventListener('resize',fit); fit();
})();
</script>"""


def parse(path: Path):
    text = path.read_text()
    m = re.match(r"\s*<!--meta\s*(\{.*?\})\s*-->\s*(.*)\Z", text, re.S)
    if not m:
        raise SystemExit(f"{path.name}: missing <!--meta {{...}} --> header")
    try:
        meta = json.loads(m.group(1))
    except json.JSONDecodeError as e:
        raise SystemExit(f"{path.name}: bad meta JSON: {e}")
    return meta, m.group(2)


def esc(s: str) -> str:
    return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")


def build_page(slug: str, meta: dict, body: str, style: str) -> str:
    layout = meta.get("layout", "three")
    title = meta["title"]
    area = meta.get("area", "")
    height = meta.get("height", "auto")
    hcss = f"{height}px" if isinstance(height, int) else height
    ctx_w = meta.get("ctx_w", 380)
    notes = meta.get("notes", [])
    cols = meta.get("notes_cols", min(4, max(1, len(notes))))
    explore = meta.get("exploration", False)

    if layout == "free":
        screen = f'<div class="screen free">{body}</div>'
    else:
        cls = {"three": "app", "two": "app two", "solo": "app solo"}[layout]
        rail_html = "" if layout == "solo" else rail(meta.get("rail", "home"), meta.get("space", "sales"),
                                                    meta.get("agent_status"))
        screen = (f'<div class="screen" style="--screen-h:{hcss};--screen-min:{meta.get("min_height", 1040)}px">'
                  f'<div class="{cls}" style="--ctx-w:{ctx_w}px">{rail_html}{body}</div></div>')

    note_html = "".join(
        f'<div class="note {k}"><b>{esc(t)}</b><span class="muted">{x}</span>'
        f'{f"<span class=cite>{esc(c)}</span>" if c else ""}</div>'
        for k, t, x, c in notes)
    banner = ('<div class="explore-banner fit">EXPLORATION, NOT LOCKED. Ideas from Linear GIN-2542, '
              'GIN-2543, GIN-2544, GIN-2545 and GIN-2612 (backlog). Nothing on this page is an accepted '
              'decision; it shows how the idea could sit inside the locked layout.</div>') if explore else ""
    return f"""<!doctype html>
<!--
  OpenCompany full-screen mockup: {esc(title)} (2026-11, GIN-1872).
  Static design artifact only; not application code. Generated from _src/pages/{slug}.html by _src/build.py.
  Locked UI authority: docs/planning/ui-structure-and-agent-editing.md and docs/PRODUCT_DIRECTION.md.
-->
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{esc(title)} · OpenCompany mockup</title>
<style>{style}</style></head>
<body>
<div class="mock-top"><a class="back" href="index.html">← All mockups</a><span class="crumb">{esc(area)} · <b>{esc(title)}</b></span>
<span class="tools"><button data-set-theme="system">System</button><button data-set-theme="light">Light</button><button data-set-theme="dark">Dark</button></span></div>
{banner}
<div class="screen-wrap"><div class="fit">{screen}
<div class="notes-strip" style="--notes-cols:{cols}">{note_html}</div></div></div>
{SCRIPT}
</body></html>
"""


def main(argv):
    style = css()
    pages = []
    only = set(argv[1:])
    for p in sorted((SRC / "pages").glob("*.html")):
        slug = p.stem
        try:
            meta, body = parse(p)
        except SystemExit as e:
            # Another author's fragment may be mid-edit; only fail for pages we were asked to build.
            if only and slug not in only:
                print(f"skipped {slug}: {e}")
                continue
            raise
        pages.append((slug, meta))
        if only and slug not in only:
            continue
        (OUT / f"{slug}.html").write_text(build_page(slug, meta, body, style))
    (SRC / "pages.json").write_text(json.dumps(
        [{"slug": s, **{k: m.get(k) for k in ("title", "area", "order", "summary", "exploration")}} for s, m in pages],
        indent=1))
    print(f"built {len(pages) if not only else len(only)} page(s)")




# ---------------------------------------------------------------------------------------------
# Index page. Groups pages by their `order` range, shows a light and a dark thumbnail per page
# (thumbs/<slug>-light.jpg / -dark.jpg, made by the screenshot script) and lists the open
# questions from open-questions.json. `--artifact` writes a variant without the document
# skeleton for publishing as a claude.ai Artifact (the host adds its own skeleton).
# ---------------------------------------------------------------------------------------------
AREAS = [
    (0, 50, "Messenger", "Home, direct messages and channels: the chat-first half of the three panes."),
    (50, 100, "Team", "The directory of people and agents, and agent profiles."),
    (100, 200, "Spaces", "A Space overview and each of the six fixed tools: Issues, Tables, Docs, Files, Artifacts, Routines."),
    (200, 300, "Approvals and takeover", "Approval cards, change-set review, decision traces, and the computer takeover."),
    (300, 400, "Apps", "Optional add-ons built only from the core primitives: Mail, CRM and a Form view on a Table."),
    (400, 500, "Admin", "Connections, governance, audit, members, hosts, budgets and data sovereignty."),
    (500, 600, "Search", "The ⌘K palette and full search."),
    (600, 700, "Onboarding and empty states", "First workspace, preset choice, first agent, and what empty looks like."),
    (700, 800, "Mobile", "Phone width with the bottom bar."),
    (800, 10_000, "Exploration (not locked)", "Ideas from backlog tickets GIN-2542 to GIN-2545 and GIN-2612, shown inside the locked layout. Nothing here is decided."),
]

INDEX_CSS = """
/* Layout: one reading column of area sections, each a responsive grid of thumbnail cards. */
.ix{max-width:1240px;margin:0 auto;padding-block:32px 64px;padding-left:max(16px,env(safe-area-inset-left,0px));padding-right:max(16px,env(safe-area-inset-right,0px))}
.ix header{display:grid;gap:12px;margin-bottom:28px}
.ix .eyebrow{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted-foreground);font-weight:650}
.ix h1{font-size:30px;letter-spacing:-.025em;text-wrap:balance;margin:0}
.ix .lede{max-width:72ch;color:var(--muted-foreground);font-size:14px;line-height:1.55}
.ix .facts{display:flex;flex-wrap:wrap;gap:8px}
.ix .toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.ix .toolbar button{border:1px solid var(--border);background:var(--card);color:var(--foreground);border-radius:7px;padding:4px 10px;font:inherit;font-size:12.5px;cursor:pointer}
.ix .toolbar button.on{background:var(--secondary);font-weight:650}
.ix .toolbar button:focus-visible,.ix a.card-l:focus-visible{outline:2px solid var(--primary);outline-offset:2px}
.ix section{margin-top:36px}
.ix section>h2{font-size:18px;margin:0 0 2px;display:flex;align-items:baseline;gap:10px}
.ix section>h2 .n{font-size:12px;color:var(--muted-foreground);font-weight:500}
.ix section>p{margin:0 0 14px;color:var(--muted-foreground);max-width:80ch}
.ix .cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
.ix a.card-l{display:flex;flex-direction:column;border:1px solid var(--border);border-radius:12px;background:var(--card);overflow:hidden;transition:border-color .15s,transform .15s;min-width:0}
.ix a.card-l:hover{border-color:var(--border-strong);transform:translateY(-1px)}
.ix .shot{aspect-ratio:16/10;background:var(--secondary);overflow:hidden;border-bottom:1px solid var(--border);max-width:100%}
.ix .shot img{width:100%;height:100%;object-fit:cover;object-position:top left;display:block}
.ix .shot .t-dark{display:none}
.ix .meta{padding:10px 12px 12px;display:grid;gap:4px;min-width:0}
.ix .meta b{font-size:13.5px}
.ix .meta span{font-size:12px;color:var(--muted-foreground);line-height:1.45}
.ix .meta code{font-size:10.5px;color:var(--subtle-foreground);font-family:var(--font-mono)}
.ix section.explore .cards a.card-l{border-style:dashed;border-color:var(--warning-border)}
.ix .oq{display:grid;gap:8px;counter-reset:q;padding:0;margin:0;list-style:none}
.ix .oq li{border:1px solid var(--border);background:var(--card);border-radius:10px;padding:10px 12px;display:grid;grid-template-columns:auto 1fr;gap:4px 12px;align-items:baseline}
.ix .oq li::before{counter-increment:q;content:counter(q);font-family:var(--font-mono);font-size:11px;color:var(--muted-foreground);grid-row:span 2}
.ix .oq li b{font-size:13px}
.ix .oq li span{font-size:12.5px;color:var(--muted-foreground)}
.ix .oq li code{font-family:var(--font-mono);font-size:11px}
.ix footer{margin-top:40px;color:var(--muted-foreground);font-size:12px;max-width:80ch}
@media (max-width:600px){.ix h1{font-size:24px}}
@media (prefers-reduced-motion:reduce){.ix a.card-l{transition:none}.ix a.card-l:hover{transform:none}}
"""
# Dark thumbnails follow the same three theme states as the tokens.
INDEX_THEME = (
    "@media (prefers-color-scheme: dark){:root:not([data-theme=\"light\"]) .ix .shot .t-light{display:none}"
    ":root:not([data-theme=\"light\"]) .ix .shot .t-dark{display:block}}\n"
    ":root[data-theme=\"dark\"] .ix .shot .t-light{display:none}:root[data-theme=\"dark\"] .ix .shot .t-dark{display:block}\n"
)


def build_index(artifact: bool = False) -> str:
    pages = json.loads((SRC / "pages.json").read_text())
    oq_path = SRC / "open-questions.json"
    questions = json.loads(oq_path.read_text()) if oq_path.exists() else []
    # The index is light-first for the artifact host, but the product tokens are dark-first.
    # Re-use the same token blocks; the index only adds layout.
    style = css() + INDEX_CSS + INDEX_THEME
    secs = []
    for lo, hi, name, blurb in AREAS:
        items = sorted([p for p in pages if lo <= (p.get("order") or 0) < hi], key=lambda p: p["order"])
        if not items:
            continue
        cards = "".join(
            f'<a class="card-l" href="{p["slug"]}.html"><div class="shot">'
            f'<img class="t-light" src="thumbs/{p["slug"]}-light.jpg" alt="{esc(p["title"])}, light theme" loading="lazy">'
            f'<img class="t-dark" src="thumbs/{p["slug"]}-dark.jpg" alt="{esc(p["title"])}, dark theme" loading="lazy"></div>'
            f'<div class="meta"><b>{esc(p["title"])}</b><span>{esc(p.get("summary") or "")}</span>'
            f'<code>{p["slug"]}.html</code></div></a>' for p in items)
        cls = ' class="explore"' if lo >= 800 else ""
        secs.append(f'<section{cls}><h2>{esc(name)} <span class="n">{len(items)}</span></h2><p>{esc(blurb)}</p>'
                    f'<div class="cards">{cards}</div></section>')
    oq = "".join(f'<li><b>{esc(q["q"])}</b><span>{q["detail"]}</span></li>' for q in questions)
    n = len(pages)
    body = f"""<div class="ix">
<header>
 <div class="eyebrow">OpenCompany · new client · design mockups · GIN-1872</div>
 <h1>Every page of the new client, filled in</h1>
 <p class="lede">{n} full-screen mockups of the Vue 3 + Reka UI client for the fictional company Acme, viewed by Rutger. Each one shows the 260px rail, the main pane and the context pane with realistic content, and ends with a notes strip that cites the doc section it implements. They follow the locked direction: Home as the attention page, one rail, three panes, flat Spaces with six tools, gating by reversibility, and Space-level access. Static design artifacts only; nothing here is implemented.</p>
 <div class="facts"><span class="pill">Team preset</span><span class="pill">Agents: Ada 🖥 · Bram 🖥 · Cleo · Finn</span><span class="pill">People: Rutger · Sanne · Jonas</span><span class="pill">Spaces: Sales · Engineering · Operations</span><span class="pill warn">Exploration pages are not locked</span></div>
 <div class="toolbar"><span class="muted small">Theme</span><button data-set-theme="system">System</button><button data-set-theme="light">Light</button><button data-set-theme="dark">Dark</button><span class="muted small">The choice carries over to every mockup.</span></div>
</header>
{''.join(secs)}
<section><h2>Open questions <span class="n">{len(questions)}</span></h2><p>Where the docs are silent, the mockups make a choice and list it here for a decision.</p><ol class="oq">{oq}</ol></section>
<footer>Source fragments live in <code>docs/ui/mockups/2026-11/_src/</code> (one shared token stylesheet, the rail generator and one fragment per page); <code>python3 build.py</code> regenerates the self-contained pages. Locked authority: <code>docs/planning/ui-structure-and-agent-editing.md</code> and <code>docs/PRODUCT_DIRECTION.md</code>. Earlier mockups (sidebar, primitives and apps, flows) are one folder up.</footer>
</div>"""
    head = (f'<title>OpenCompany UI Mockups</title>\n<meta name="claude:viewport-fit-sides" content="cover">\n'
            f'<style>{style}\nbody{{background:var(--background)}}</style>')
    if artifact:
        return head + "\n" + body + "\n" + SCRIPT
    return ('<!doctype html>\n<!-- OpenCompany 2026-11 mockup index (GIN-1872). Generated by _src/build.py. -->\n'
            '<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">\n'
            f'{head}</head><body>{body}{SCRIPT}</body></html>\n')


def write_index(artifact_path: str | None = None):
    (OUT / "index.html").write_text(build_index())
    if artifact_path:
        Path(artifact_path).write_text(build_index(artifact=True))


if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "--index":
        write_index(sys.argv[2] if len(sys.argv) > 2 else None)
        print("built index")
    else:
        main(sys.argv)
