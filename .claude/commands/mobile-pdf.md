---
description: Generate a beautifully styled narrow PDF document using Typst
argument-hint: <topic or instruction>
---

# Generate Narrow Typst PDF

Create a professional, mobile-friendly PDF document using Typst about: **$ARGUMENTS**

## Typst Template

Use this exact template as your base. Replace the placeholder content with actual content based on the user's topic.

```typst
// [Title] — [Subtitle]
// OpenCompany Platform

#set document(
  title: "[TITLE]",
  author: "OpenCompany",
  date: datetime.today(),
)

// Narrow page for mobile readability (A6-ish)
#set page(
  width: 105mm,
  height: 148mm,
  margin: (top: 12mm, bottom: 14mm, left: 12mm, right: 12mm),
  header: context [
    #if counter(page).get().first() > 1 [
      #set text(6pt, fill: luma(160))
      #smallcaps[HEADER_LEFT]
      #h(1fr)
      #smallcaps[OpenCompany]
    ]
  ],
  footer: context [
    #set text(6.5pt, fill: luma(150))
    #h(1fr)
    #counter(page).display("1 / 1", both: true)
    #h(1fr)
  ],
)

#set text(font: "Helvetica Neue", size: 8.5pt, fill: luma(25))
#set par(justify: true, leading: 0.65em)
#set heading(numbering: "1.1")

// Heading styles — indigo palette
#show heading.where(level: 1): it => {
  v(0.8em)
  block(
    width: 100%,
    below: 0.4em,
  )[
    #set text(13pt, weight: "bold", fill: rgb("#1e1b4b"))
    #it
    #v(-0.2em)
    #line(length: 100%, stroke: 0.6pt + rgb("#c7d2fe"))
  ]
}

#show heading.where(level: 2): it => {
  v(0.6em)
  set text(10.5pt, weight: "bold", fill: rgb("#312e81"))
  block(it)
  v(0.2em)
}

#show heading.where(level: 3): it => {
  v(0.4em)
  set text(9pt, weight: "bold", fill: rgb("#4338ca"))
  block(it)
  v(0.15em)
}

// Callout block — left-bordered info box
// Usage: #callout(title: "Note")[Content here]
// Colors: rgb("#6366f1") indigo (default), rgb("#16a34a") green, rgb("#dc2626") red, rgb("#d97706") amber
#let callout(body, title: none, color: rgb("#6366f1")) = {
  block(
    width: 100%,
    inset: (left: 10pt, rest: 8pt),
    stroke: (left: 2.5pt + color),
    fill: color.lighten(95%),
    radius: (right: 3pt),
  )[
    #if title != none [
      #text(weight: "bold", size: 8pt, fill: color.darken(20%))[#title] \
    ]
    #set text(8pt)
    #body
  ]
}

// Code block helper
// Usage: #codeblock[`code here`]
// Dark variant: #codeblock(bg: rgb("#1e1b4b"))[#set text(fill: white) `code`]
#let codeblock(body, bg: rgb("#f8fafc")) = {
  block(
    width: 100%,
    inset: 8pt,
    fill: bg,
    radius: 4pt,
    stroke: 0.4pt + luma(215),
  )[
    #set text(7pt, font: "Menlo")
    #body
  ]
}

// ─── Title page ───

#v(2cm)

#align(center)[
  #block(width: 100%)[
    #text(10pt, tracking: 0.15em, fill: rgb("#6366f1"), weight: "medium")[OPENCOMPANY]
    #v(0.6cm)
    #text(22pt, weight: "bold", fill: rgb("#1e1b4b"))[
      TITLE_LINE_1\ TITLE_LINE_2
    ]
    #v(0.5cm)
    #text(9.5pt, fill: luma(100))[
      SUBTITLE_LINE
    ]
    #v(1cm)
    #line(length: 30%, stroke: 0.8pt + rgb("#c7d2fe"))
    #v(0.5cm)
    #text(8pt, fill: luma(130))[
      Version X.X --- MONTH YEAR
    ]
  ]
]

#v(1.5cm)

#block(
  width: 100%,
  inset: 12pt,
  fill: rgb("#f8fafc"),
  radius: 6pt,
  stroke: 0.5pt + luma(215),
)[
  #text(9pt, weight: "bold", fill: rgb("#1e1b4b"))[Abstract]
  #v(3pt)
  #text(8pt, fill: luma(50))[
    ABSTRACT_TEXT
  ]
]

#pagebreak()

#outline(
  title: text(12pt, weight: "bold", fill: rgb("#1e1b4b"))[Contents],
  indent: 1em,
  depth: 2,
)

#pagebreak()

// ─── Content starts here ───

= First Section

// ... content ...
```

## Style Guide

Follow these styling conventions consistently:

### Colors (Indigo palette from Tailwind)
- Primary headings: `rgb("#1e1b4b")` (indigo-950)
- H2 headings: `rgb("#312e81")` (indigo-900)
- H3 headings: `rgb("#4338ca")` (indigo-700)
- Accent/brand: `rgb("#6366f1")` (indigo-500)
- Decorative lines: `rgb("#c7d2fe")` (indigo-200)

### Tables
```typst
#table(
  columns: (auto, 1fr),
  inset: 6pt,
  stroke: 0.4pt + luma(210),
  fill: (x, y) => if y == 0 { rgb("#eef2ff") } else { none },
  table.header(
    text(weight: "bold", size: 7.5pt)[Header 1],
    text(weight: "bold", size: 7.5pt)[Header 2],
  ),
  [Cell 1], [Cell 2],
)
```

Header row colors by context:
- Default/info: `rgb("#eef2ff")` (indigo-50)
- Success: `rgb("#ecfdf5")` (emerald-50)
- Warning: `rgb("#fef3c7")` (amber-50)
- Error: `rgb("#fef2f2")` (red-50)
- Purple: `rgb("#fdf4ff")` (fuchsia-50)

### Lists
```typst
#set list(marker: text(fill: rgb("#6366f1"))[--])
- Item one
- Item two
```

### Callout colors by purpose
- Info/default: `rgb("#6366f1")` indigo
- Success/positive: `rgb("#16a34a")` green
- Warning/caution: `rgb("#d97706")` amber
- Error/critical: `rgb("#dc2626")` red

### Typst gotchas
- Use `---` for em-dashes (not `—`)
- Use `\` for line breaks in blocks
- Use `~` for non-breaking spaces (e.g., `Agent~A`)
- Use `#sym.arrow` for arrow symbols
- Avoid `@` in text — Typst parses it as a label reference. Use `#"@"` if needed.
- Fonts available: `"Helvetica Neue"` (body), `"Menlo"` (code)

### Footer
Always end with:
```typst
#v(1cm)
#line(length: 100%, stroke: 0.4pt + luma(210))
#v(0.3cm)
#align(center)[
  #text(7pt, fill: luma(150))[
    OpenCompany --- [Document Title] vX.X --- [Month Year]
  ]
]
```

## Process

1. Write the `.typ` file to `/tmp/{slug}.typ` (derive slug from topic)
2. Compile to PDF:
   ```bash
   /opt/homebrew/bin/typst compile /tmp/{slug}.typ /Users/rutger/Sites/opencompany/storage/app/public/typst/{slug}.pdf
   ```
3. If compilation fails, fix the Typst markup and retry
4. Report the output path to the user

## Content Guidelines

- Write substantive, well-structured content — not just a template
- Use callouts for important notes, warnings, or key takeaways
- Use tables for comparisons and structured data
- Use codeblocks for technical examples
- Include an abstract on the title page
- Include a table of contents
- Break content into logical sections with headings
- Keep paragraphs concise — the narrow format means text wraps quickly
- Use numbered lists (`+`) for sequential steps, bullet lists for non-ordered items
