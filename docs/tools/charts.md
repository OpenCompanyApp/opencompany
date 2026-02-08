# Visualization Tools — Reference

> Reference for the five visualization tools available to agents. Each tool accepts markup or a spec and returns an image embed (or PDF).

---

## Overview

Agents have five tools for generating visual output:

| Tool | Input | Output | Provider |
|------|-------|--------|----------|
| `render_svg` | Raw SVG markup | PNG image | Built-in |
| `render_vegalite` | Vega-Lite JSON spec | PNG image | External (MCP) |
| `render_mermaid` | Mermaid diagram markup | PNG image | External (MCP) |
| `render_plantuml` | PlantUML markup | PNG image | External (MCP) |
| `render_typst` | Typst markup | PDF document | External (MCP) |

All image tools return a markdown image embed (`![title](/storage/...png)`). The Typst tool returns a markdown link to the generated PDF.

**Rule:** Never fabricate image or document URLs. Always use these tools and embed the returned path.

---

## `render_svg` — SVG to PNG (Built-in)

Converts raw SVG markup to a PNG image using `rsvg-convert`. Use this for custom diagrams, icons, illustrations, infographics, or any visual that does not fit neatly into a data-charting spec.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `svg` | string | Yes | — | Complete SVG markup. Must include a root `<svg>` element with `xmlns` and `viewBox`. |
| `title` | string | No | `"Image"` | Alt text for the returned markdown image embed. |
| `width` | integer | No | `1400` | Output width in pixels (100--4000). Height scales from viewBox unless set explicitly. |
| `height` | integer | No | — | Output height in pixels (100--4000). Omit to preserve the SVG aspect ratio. |

### Tips

- Always include `xmlns="http://www.w3.org/2000/svg"` and a `viewBox` on the root element.
- Use `font-family="sans-serif"` — named fonts are not guaranteed.
- Use hex colors (`#rrggbb`), not CSS named colors.
- Supported features: shapes, paths, text, gradients (linear/radial), filters (blur, drop-shadow), clip-paths, masks, patterns, transforms.
- For crisp output, stick to vector elements. Avoid embedded raster images.

### Example — Simple Infographic

```json
{
  "svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 400 300\"><rect width=\"400\" height=\"300\" fill=\"#1e1e2e\" rx=\"12\"/><circle cx=\"200\" cy=\"120\" r=\"60\" fill=\"#89b4fa\"/><text x=\"200\" y=\"240\" text-anchor=\"middle\" font-family=\"sans-serif\" font-size=\"24\" fill=\"#cdd6f4\">Hello World</text></svg>",
  "title": "Hello World Graphic",
  "width": 1400
}
```

### Example — Status Indicators

```json
{
  "svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 600 100\"><rect width=\"600\" height=\"100\" fill=\"#f8fafc\" rx=\"8\"/><circle cx=\"60\" cy=\"50\" r=\"16\" fill=\"#10b981\"/><text x=\"90\" y=\"56\" font-family=\"sans-serif\" font-size=\"16\" fill=\"#334155\">Online (12)</text><circle cx=\"250\" cy=\"50\" r=\"16\" fill=\"#f59e0b\"/><text x=\"280\" y=\"56\" font-family=\"sans-serif\" font-size=\"16\" fill=\"#334155\">Busy (3)</text><circle cx=\"430\" cy=\"50\" r=\"16\" fill=\"#ef4444\"/><text x=\"460\" y=\"56\" font-family=\"sans-serif\" font-size=\"16\" fill=\"#334155\">Offline (5)</text></svg>",
  "title": "Agent Status Summary"
}
```

---

## `render_vegalite` — Vega-Lite Charts (External)

Accepts a [Vega-Lite](https://vega.github.io/vega-lite/) JSON specification and renders it to a PNG image. This is the primary tool for data charts — bar, line, area, pie, scatter, and everything else Vega-Lite supports.

Call `get_tool_info("render_vegalite")` to confirm exact parameters before first use.

### Spec Format

Pass a standard Vega-Lite v5 specification. At minimum you need `mark`, `encoding`, and either inline `data` or a `data.values` array.

### Example — Vertical Bar Chart

```json
{
  "$schema": "https://vega.github.io/schema/vega-lite/v5.json",
  "title": "Monthly Revenue",
  "width": 500,
  "height": 300,
  "data": {
    "values": [
      {"month": "Jan", "revenue": 120},
      {"month": "Feb", "revenue": 150},
      {"month": "Mar", "revenue": 180},
      {"month": "Apr", "revenue": 200}
    ]
  },
  "mark": "bar",
  "encoding": {
    "x": {"field": "month", "type": "nominal", "axis": {"labelAngle": 0}},
    "y": {"field": "revenue", "type": "quantitative", "title": "Revenue ($K)"},
    "color": {"value": "#4F46E5"}
  }
}
```

### Example — Multi-Series Line Chart

```json
{
  "$schema": "https://vega.github.io/schema/vega-lite/v5.json",
  "title": "Weekly Active Users",
  "width": 500,
  "height": 300,
  "data": {
    "values": [
      {"week": "W1", "platform": "Mobile", "users": 1200},
      {"week": "W2", "platform": "Mobile", "users": 1350},
      {"week": "W3", "platform": "Mobile", "users": 1500},
      {"week": "W4", "platform": "Mobile", "users": 1600},
      {"week": "W1", "platform": "Desktop", "users": 800},
      {"week": "W2", "platform": "Desktop", "users": 850},
      {"week": "W3", "platform": "Desktop", "users": 920},
      {"week": "W4", "platform": "Desktop", "users": 950}
    ]
  },
  "mark": {"type": "line", "point": true},
  "encoding": {
    "x": {"field": "week", "type": "ordinal"},
    "y": {"field": "users", "type": "quantitative", "title": "Users"},
    "color": {"field": "platform", "type": "nominal"}
  }
}
```

### Example — Area Chart

```json
{
  "$schema": "https://vega.github.io/schema/vega-lite/v5.json",
  "title": "Traffic Over Time",
  "width": 500,
  "height": 300,
  "data": {
    "values": [
      {"day": "Mon", "visitors": 500},
      {"day": "Tue", "visitors": 700},
      {"day": "Wed", "visitors": 600},
      {"day": "Thu", "visitors": 800},
      {"day": "Fri", "visitors": 750}
    ]
  },
  "mark": {"type": "area", "opacity": 0.6, "color": "#4F46E5"},
  "encoding": {
    "x": {"field": "day", "type": "ordinal"},
    "y": {"field": "visitors", "type": "quantitative"}
  }
}
```

### Example — Pie / Donut Chart

Vega-Lite renders pie charts using `arc` mark with a theta encoding:

```json
{
  "$schema": "https://vega.github.io/schema/vega-lite/v5.json",
  "title": "Market Share",
  "width": 300,
  "height": 300,
  "data": {
    "values": [
      {"browser": "Chrome", "share": 65},
      {"browser": "Safari", "share": 18},
      {"browser": "Firefox", "share": 8},
      {"browser": "Edge", "share": 5},
      {"browser": "Other", "share": 4}
    ]
  },
  "mark": {"type": "arc", "innerRadius": 50},
  "encoding": {
    "theta": {"field": "share", "type": "quantitative"},
    "color": {"field": "browser", "type": "nominal"}
  }
}
```

Set `"innerRadius": 0` for a solid pie, or increase it for a donut.

### Example — Grouped Bar Chart

```json
{
  "$schema": "https://vega.github.io/schema/vega-lite/v5.json",
  "title": "Q1 vs Q2 Revenue",
  "width": 400,
  "height": 300,
  "data": {
    "values": [
      {"product": "A", "quarter": "Q1", "revenue": 120},
      {"product": "A", "quarter": "Q2", "revenue": 140},
      {"product": "B", "quarter": "Q1", "revenue": 90},
      {"product": "B", "quarter": "Q2", "revenue": 110},
      {"product": "C", "quarter": "Q1", "revenue": 150},
      {"product": "C", "quarter": "Q2", "revenue": 160}
    ]
  },
  "mark": "bar",
  "encoding": {
    "x": {"field": "product", "type": "nominal"},
    "xOffset": {"field": "quarter", "type": "nominal"},
    "y": {"field": "revenue", "type": "quantitative"},
    "color": {"field": "quarter", "type": "nominal"}
  }
}
```

### Vega-Lite Tips

- Always include the `$schema` field — it tells the renderer which version to use.
- Use `"type": "nominal"` for categories, `"ordinal"` for ordered categories, `"quantitative"` for numbers, `"temporal"` for dates.
- For stacked charts, add `"stack": true` to the y-encoding.
- For horizontal bars, swap the x and y encodings.
- Custom colors can be set per-series with `"scale": {"range": ["#4F46E5", "#EF4444", "#10B981"]}` on the color encoding.
- Vega-Lite handles legends, axes, and labels automatically.

---

## `render_mermaid` — Mermaid Diagrams (External)

Renders [Mermaid](https://mermaid.js.org/) diagram markup to a PNG image. Best for flowcharts, sequence diagrams, class diagrams, ER diagrams, Gantt charts, and similar structural diagrams.

Call `get_tool_info("render_mermaid")` to confirm exact parameters before first use.

### Example — Flowchart

```
graph TD
    A[Start] --> B{Decision}
    B -->|Yes| C[Action 1]
    B -->|No| D[Action 2]
    C --> E[End]
    D --> E
```

### Example — Sequence Diagram

```
sequenceDiagram
    participant U as User
    participant A as API
    participant D as Database
    U->>A: POST /tasks
    A->>D: INSERT task
    D-->>A: task_id
    A-->>U: 201 Created
```

### Example — Gantt Chart

```
gantt
    title Project Timeline
    dateFormat YYYY-MM-DD
    section Planning
    Research       :done, 2025-01-01, 2025-01-20
    Design         :active, 2025-01-15, 2025-02-10
    section Development
    Backend        :2025-02-01, 2025-03-15
    Frontend       :2025-02-15, 2025-03-30
```

---

## `render_plantuml` — PlantUML Diagrams (External)

Renders [PlantUML](https://plantuml.com/) markup to a PNG image. Best for UML diagrams — class, component, activity, state, deployment, use-case, and more.

Call `get_tool_info("render_plantuml")` to confirm exact parameters before first use.

### Example — Class Diagram

```
@startuml
class User {
  +id: int
  +name: string
  +email: string
  +tasks(): HasMany
}

class Task {
  +id: int
  +title: string
  +status: string
  +user(): BelongsTo
}

User "1" -- "*" Task : owns
@enduml
```

### Example — Activity Diagram

```
@startuml
start
:Receive request;
if (Authenticated?) then (yes)
  :Process request;
  :Return response;
else (no)
  :Return 401;
endif
stop
@enduml
```

---

## `render_typst` — Typst to PDF (External)

Renders [Typst](https://typst.app/) markup to a PDF document. Use this for formatted reports, invoices, letters, or any document that needs precise layout and typography.

Call `get_tool_info("render_typst")` to confirm exact parameters before first use.

### Example — Simple Report

```typst
#set page(margin: 2cm)
#set text(font: "Inter", size: 11pt)

= Quarterly Report

== Summary

Revenue increased by *18%* compared to the previous quarter.

#table(
  columns: (1fr, 1fr, 1fr),
  [*Metric*], [*Q1*], [*Q2*],
  [Revenue], [$120K], [$142K],
  [Users], [1,200], [1,580],
  [Churn], [3.2%], [2.8%],
)

== Key Highlights

- Launched mobile app (12K downloads in first month)
- Reduced infrastructure costs by 15%
- Hired 3 new engineers
```

### Example — Invoice

```typst
#set page(margin: 1.5cm)
#set text(size: 10pt)

#align(right)[
  *Invoice \#2025-042* \
  Date: February 8, 2025
]

#v(1cm)

*Bill To:* \
Acme Corporation \
123 Business Ave

#v(0.5cm)

#table(
  columns: (3fr, 1fr, 1fr, 1fr),
  [*Description*], [*Qty*], [*Rate*], [*Amount*],
  [Consulting hours], [40], [$150], [$6,000],
  [Platform license], [1], [$500], [$500],
  [Support (monthly)], [1], [$200], [$200],
)

#align(right)[*Total: $6,700*]
```

---

## When to Use Which Tool

| Need | Tool |
|------|------|
| Data chart (bar, line, area, scatter, pie) | `render_vegalite` |
| Flowchart, sequence diagram, ER diagram | `render_mermaid` |
| UML class/component/state diagram | `render_plantuml` |
| Custom graphic, icon, infographic | `render_svg` |
| Formatted report, invoice, letter (PDF) | `render_typst` |

When in doubt, prefer `render_vegalite` for anything data-driven and `render_mermaid` for structural diagrams. Use `render_svg` when you need full creative control over the visual.
