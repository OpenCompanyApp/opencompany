# Chart Generation — JSON Adapter Reference

> Complete reference for the `create_jpgraph_chart` tool. Agents pass a JSON config object and receive a markdown image embed.

---

## Common Config

Every chart call requires a `type` and at least one data source (`series`, `items`, or `matrix`).

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | Yes | Chart type (see below) |
| `title` | string | No | Title displayed at top of chart |
| `labels` | string[] | No | Category labels — x-axis (bar/line/combo), slice names (pie/donut), spoke names (radar) |
| `series` | object[] | Depends | Data series (all types except `gantt` and `contour`) |
| `items` | object[] | Gantt only | Gantt timeline items |
| `matrix` | number[][] | Contour only | 2D numeric grid for heatmap/contour |
| `width` | integer | No | Image width in pixels (default: 1400, range: 100–4000) |
| `height` | integer | No | Image height in pixels (default: 800, range: 100–4000) |
| `options` | object | No | Styling and behavior options (see Options table) |

Fonts, margins, markers, and line weights all scale proportionally to width. At 700px reference width, base sizes apply unchanged. At 1400px (default), everything is 2x.

---

## Series Object

Used by all chart types except `gantt` and `contour`.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `name` | string | — | Series name (shown in legend) |
| `values` | array | **Required** | Data values — format depends on chart type |
| `color` | string | Auto | Hex color (`"#4F46E5"`) |
| `lineWeight` | integer | 2 | Line thickness (line/area/spline/combo/impulse) |
| `fillColor` | string | Auto | Fill color with alpha (`"#4F46E5@0.5"`) |
| `marker` | string | None | Marker type: `"circle"`, `"square"`, `"diamond"`, `"triangle"`, `"triangle_down"`, `"star"`, `"cross"`, `"x"` |
| `markerSize` | integer | 6 | Marker diameter in pixels (scales with resolution) |
| `plotType` | string | Auto | **Combo only.** `"bar"` or `"line"`. Default: first series = bar, rest = line |

### Values format by type

| Type | Values format | Example |
|------|--------------|---------|
| bar, horizontal_bar, grouped_bar, stacked_bar | `number[]` | `[10, 20, 30]` |
| line, area, stacked_area, spline | `number[]` | `[10, 25, 18, 35]` |
| combo | `number[]` | `[120, 150, 180]` |
| error_line | `[value, errMin, errMax][]` | `[[50, 2, 3], [55, 1, 2]]` |
| scatter, impulse | `[x, y][]` | `[[1, 5], [3, 8], [5, 2]]` |
| polar | `[angle, radius][]` | `[[0, 3], [90, 8], [180, 6]]` |
| pie, pie_3d, donut | `number[]` | `[30, 25, 20, 15, 10]` |
| radar | `number[]` | `[5, 8, 6, 9, 4]` |
| stock | `[open, close, min, max][]` | `[[100, 105, 98, 107]]` |
| box | `[low, q1, median, q3, high][]` | `[[10, 25, 50, 75, 90]]` |

---

## Gantt Items

Used only by `type: "gantt"`. Replaces `series`.

Each item has a `type` field:

### Bar item
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | `"bar"` | Yes | |
| `label` | string | Yes | Task name |
| `start` | string | Yes | Start date (`"YYYY-MM-DD"`) |
| `end` | string | Yes | End date (`"YYYY-MM-DD"`) |
| `progress` | integer | No | Completion percentage (0–100) |
| `color` | string | No | Bar fill color |
| `caption` | string | No | Text after the bar |
| `heightFactor` | float | No | Bar height (0–1, default 0.6) |

### Milestone item
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | `"milestone"` | Yes | |
| `label` | string | Yes | Milestone name |
| `date` | string | Yes | Target date |
| `color` | string | No | Diamond marker color |

### Vertical line item
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | `"vline"` | Yes | |
| `date` | string | Yes | Date position |
| `label` | string | No | Line label |
| `color` | string | No | Line color |

---

## Contour Matrix

Used only by `type: "contour"`. Replaces `series`.

A 2D array of numbers representing Z-values on a grid. Minimum 2x2. All rows must have the same length.

```json
{
  "type": "contour",
  "matrix": [
    [1, 2, 3, 4, 5],
    [2, 4, 6, 8, 10],
    [3, 6, 9, 12, 15],
    [4, 8, 12, 16, 20]
  ]
}
```

---

## Options

All fields are optional. Pass in the `options` object.

### Layout & Axes

| Option | Type | Default | Applies to |
|--------|------|---------|------------|
| `xAxisLabel` | string | — | Bar, line, area, combo, error_line, scatter, impulse |
| `yAxisLabel` | string | — | Same as above |
| `yMin` | number | Auto | Any with Y axis |
| `yMax` | number | Auto | Any with Y axis |
| `labelAngle` | integer | Auto (45 if >8 labels) | Any with X labels |
| `margin` | object | Auto-scaled | All types. Keys: `left`, `right`, `top`, `bottom` (px at 700px reference) |

### Legend

| Option | Type | Default | Applies to |
|--------|------|---------|------------|
| `legend` | boolean | true | All (shown when >1 series) |
| `legendPosition` | string | `"top"` | All. Values: `"top"`, `"bottom"`, `"right"` |

### Values & Grid

| Option | Type | Default | Applies to |
|--------|------|---------|------------|
| `showValues` | boolean | false | Bar types, combo |
| `valueFormat` | string | `"%d"` | Bar types, combo. Printf format |
| `showGrid` | boolean | true | All with axes |
| `gridColor` | string | `"#E5E7EB"` | All with axes |

### Colors & Style

| Option | Type | Default | Applies to |
|--------|------|---------|------------|
| `backgroundColor` | string | `"white"` | All |
| `colors` | string[] | Built-in palette | All. Array of hex colors as palette override |
| `lineWeight` | integer | 2 | Line, area, spline, combo (global default) |

### Pie-specific

| Option | Type | Default | Applies to |
|--------|------|---------|------------|
| `percentage` | boolean | false | pie, pie_3d, donut — show % labels |
| `threeDAngle` | integer | 50 | pie_3d — 3D perspective angle |
| `donutSize` | float | 0.5 | donut — hole size (0–1) |

### Radar-specific

| Option | Type | Default | Applies to |
|--------|------|---------|------------|
| `radarFill` | boolean | true | radar — fill area under lines |

### Spline-specific

| Option | Type | Default | Applies to |
|--------|------|---------|------------|
| `splineDensity` | integer | 50 | spline — interpolated points (10–500) |

### Contour-specific

| Option | Type | Default | Applies to |
|--------|------|---------|------------|
| `isobar` | integer | 10 | contour — number of contour levels |
| `interpolation` | integer | 1 | contour — smoothing factor (1–5) |

---

## Chart Types

### Bar Charts

#### `bar` — Vertical Bar
```json
{
  "type": "bar",
  "title": "Monthly Sales",
  "labels": ["Jan", "Feb", "Mar", "Apr"],
  "series": [{"name": "Sales", "values": [120, 150, 180, 200]}],
  "options": {"showValues": true, "valueFormat": "$%d"}
}
```

#### `horizontal_bar` — Horizontal Bar
```json
{
  "type": "horizontal_bar",
  "title": "Department Budget",
  "labels": ["Engineering", "Marketing", "Sales", "Support"],
  "series": [{"name": "Budget", "values": [500, 300, 400, 200]}]
}
```

#### `grouped_bar` — Grouped (Side-by-Side)
```json
{
  "type": "grouped_bar",
  "title": "Q1 vs Q2 Revenue",
  "labels": ["Product A", "Product B", "Product C"],
  "series": [
    {"name": "Q1", "values": [120, 90, 150]},
    {"name": "Q2", "values": [140, 110, 160]}
  ]
}
```

#### `stacked_bar` — Stacked
```json
{
  "type": "stacked_bar",
  "title": "Revenue Breakdown",
  "labels": ["2022", "2023", "2024"],
  "series": [
    {"name": "Subscriptions", "values": [200, 280, 350]},
    {"name": "Services", "values": [100, 120, 140]},
    {"name": "Licenses", "values": [50, 60, 70]}
  ]
}
```

### Line Charts

#### `line` — Line Chart
```json
{
  "type": "line",
  "title": "Weekly Active Users",
  "labels": ["W1", "W2", "W3", "W4", "W5", "W6"],
  "series": [
    {"name": "Mobile", "values": [1200, 1350, 1100, 1500, 1400, 1600], "marker": "circle"},
    {"name": "Desktop", "values": [800, 850, 900, 870, 920, 950], "marker": "square"}
  ]
}
```

#### `area` — Area Chart
```json
{
  "type": "area",
  "title": "Traffic Over Time",
  "labels": ["Mon", "Tue", "Wed", "Thu", "Fri"],
  "series": [{"name": "Visitors", "values": [500, 700, 600, 800, 750], "fillColor": "#4F46E5@0.3"}]
}
```

#### `stacked_area` — Stacked Area
```json
{
  "type": "stacked_area",
  "title": "Resource Usage",
  "labels": ["00:00", "06:00", "12:00", "18:00", "24:00"],
  "series": [
    {"name": "CPU", "values": [20, 45, 80, 60, 30]},
    {"name": "Memory", "values": [30, 35, 40, 45, 35]},
    {"name": "Disk I/O", "values": [10, 15, 25, 20, 10]}
  ]
}
```

#### `spline` — Smooth Curved Line
Automatically interpolates between data points. Requires minimum 3 points.
```json
{
  "type": "spline",
  "title": "Temperature Curve",
  "series": [{"name": "Temp", "values": [5, 8, 15, 22, 28, 25, 18, 10]}],
  "options": {"splineDensity": 100, "yAxisLabel": "Temperature (C)"}
}
```

#### `error_line` — Line with Error Bars
Values are `[value, errorDeltaMin, errorDeltaMax]` triples.
```json
{
  "type": "error_line",
  "title": "Measurement Accuracy",
  "labels": ["Trial 1", "Trial 2", "Trial 3", "Trial 4", "Trial 5"],
  "series": [
    {"name": "Sensor A", "values": [[50, 2, 3], [55, 1, 2], [48, 3, 4], [60, 2, 2], [52, 1, 3]]},
    {"name": "Sensor B", "values": [[45, 3, 2], [50, 2, 3], [52, 1, 1], [55, 2, 3], [48, 2, 2]]}
  ]
}
```

### Pie Charts

#### `pie` — Pie Chart
```json
{
  "type": "pie",
  "title": "Market Share",
  "labels": ["Chrome", "Safari", "Firefox", "Edge", "Other"],
  "series": [{"values": [65, 18, 8, 5, 4]}],
  "options": {"percentage": true}
}
```

#### `pie_3d` — 3D Pie Chart
```json
{
  "type": "pie_3d",
  "title": "Budget Allocation",
  "labels": ["Engineering", "Marketing", "Sales", "Ops"],
  "series": [{"values": [40, 25, 20, 15]}],
  "options": {"threeDAngle": 45, "percentage": true}
}
```

#### `donut` — Donut / Ring Chart
```json
{
  "type": "donut",
  "title": "Task Status",
  "labels": ["Done", "In Progress", "Todo", "Blocked"],
  "series": [{"values": [45, 25, 20, 10]}],
  "options": {"donutSize": 0.55, "percentage": true}
}
```

### Scatter & Impulse

#### `scatter` — Scatter Plot
Values are `[x, y]` pairs.
```json
{
  "type": "scatter",
  "title": "Height vs Weight",
  "series": [
    {"name": "Male", "values": [[170, 70], [175, 80], [180, 85], [165, 65], [185, 90]], "marker": "circle"},
    {"name": "Female", "values": [[155, 50], [160, 55], [165, 60], [158, 52], [170, 65]], "marker": "diamond"}
  ],
  "options": {"xAxisLabel": "Height (cm)", "yAxisLabel": "Weight (kg)"}
}
```

#### `impulse` — Stem / Lollipop Chart
Same `[x, y]` format as scatter but displays as vertical stems.
```json
{
  "type": "impulse",
  "title": "Signal Strength",
  "series": [{"name": "Signal", "values": [[1, 5], [2, 8], [3, 3], [4, 12], [5, 6], [6, 9], [7, 4], [8, 11]]}],
  "options": {"xAxisLabel": "Frequency", "yAxisLabel": "Amplitude"}
}
```

### Radar & Polar

#### `radar` — Spider / Radar Chart
```json
{
  "type": "radar",
  "title": "Skill Assessment",
  "labels": ["Frontend", "Backend", "DevOps", "Design", "Leadership"],
  "series": [
    {"name": "Alice", "values": [9, 7, 5, 8, 6]},
    {"name": "Bob", "values": [6, 9, 8, 4, 7]}
  ],
  "options": {"radarFill": true}
}
```

#### `polar` — Polar Coordinate Chart
Values are `[angle, radius]` pairs. Angles in degrees.
```json
{
  "type": "polar",
  "title": "Wind Direction",
  "series": [
    {"name": "Winter", "values": [[0, 5], [45, 8], [90, 12], [135, 6], [180, 4], [225, 9], [270, 7], [315, 10]]},
    {"name": "Summer", "values": [[0, 3], [45, 6], [90, 8], [135, 10], [180, 7], [225, 4], [270, 5], [315, 6]]}
  ]
}
```

### Statistical

#### `stock` — Candlestick / OHLC Chart
Values are `[open, close, min, max]` tuples.
```json
{
  "type": "stock",
  "title": "AAPL Weekly",
  "labels": ["Mon", "Tue", "Wed", "Thu", "Fri"],
  "series": [{
    "name": "AAPL",
    "values": [
      [150, 155, 148, 157],
      [155, 152, 150, 158],
      [152, 158, 149, 160],
      [158, 156, 154, 161],
      [156, 160, 153, 162]
    ]
  }]
}
```

#### `box` — Box Plot
Values are `[low, q1, median, q3, high]` tuples.
```json
{
  "type": "box",
  "title": "Response Time Distribution",
  "labels": ["API v1", "API v2", "API v3"],
  "series": [{
    "name": "Latency",
    "values": [
      [50, 120, 200, 350, 500],
      [30, 80, 150, 250, 400],
      [20, 60, 100, 180, 300]
    ]
  }],
  "options": {"yAxisLabel": "Response Time (ms)"}
}
```

### Specialized

#### `gantt` — Gantt Timeline
Uses `items` instead of `series`. Three item types: `bar`, `milestone`, `vline`.
```json
{
  "type": "gantt",
  "title": "Project Timeline",
  "items": [
    {"type": "bar", "label": "Research", "start": "2025-01-01", "end": "2025-01-20", "progress": 100, "color": "#10B981"},
    {"type": "bar", "label": "Design", "start": "2025-01-15", "end": "2025-02-10", "progress": 80, "color": "#4F46E5"},
    {"type": "milestone", "label": "Design Review", "date": "2025-02-10", "color": "#EF4444"},
    {"type": "bar", "label": "Development", "start": "2025-02-01", "end": "2025-03-30", "progress": 30, "color": "#F59E0B"},
    {"type": "bar", "label": "Testing", "start": "2025-03-15", "end": "2025-04-15", "color": "#8B5CF6"},
    {"type": "vline", "date": "2025-02-20", "label": "Today", "color": "#EF4444"}
  ]
}
```

#### `contour` — Contour / Heatmap
Uses `matrix` instead of `series`. 2D grid of Z-values.
```json
{
  "type": "contour",
  "title": "Heat Distribution",
  "matrix": [
    [10, 15, 20, 25, 20, 15, 10],
    [15, 25, 35, 40, 35, 25, 15],
    [20, 35, 50, 60, 50, 35, 20],
    [25, 40, 60, 80, 60, 40, 25],
    [20, 35, 50, 60, 50, 35, 20],
    [15, 25, 35, 40, 35, 25, 15],
    [10, 15, 20, 25, 20, 15, 10]
  ],
  "options": {"isobar": 8, "interpolation": 2}
}
```

#### `combo` — Mixed Bar + Line
Series use `plotType` to specify rendering. Default: first series = bar, rest = line.
```json
{
  "type": "combo",
  "title": "Revenue vs Growth Rate",
  "labels": ["Q1", "Q2", "Q3", "Q4"],
  "series": [
    {"name": "Revenue ($K)", "values": [120, 150, 180, 220], "plotType": "bar", "color": "#4F46E5"},
    {"name": "Growth %", "values": [5, 12, 18, 22], "plotType": "line", "color": "#EF4444", "marker": "circle"}
  ],
  "options": {"showValues": true, "yAxisLabel": "Revenue ($K)"}
}
```

---

## Default Color Palette

When no colors are specified, series cycle through:

| Index | Color | Hex |
|-------|-------|-----|
| 0 | Indigo | `#4F46E5` |
| 1 | Red | `#EF4444` |
| 2 | Emerald | `#10B981` |
| 3 | Amber | `#F59E0B` |
| 4 | Violet | `#8B5CF6` |
| 5 | Pink | `#EC4899` |
| 6 | Cyan | `#06B6D4` |
| 7 | Lime | `#84CC16` |
| 8 | Orange | `#F97316` |
| 9 | Indigo-alt | `#6366F1` |

Override with `options.colors: ["#hex", ...]`.

---

## Resolution & Scaling

| Width | Font scale | Best for |
|-------|-----------|----------|
| 700 | 1x (base) | Thumbnails, inline previews |
| 1400 | 2x (default) | Standard display, chat messages |
| 2800 | 4x | High-DPI, print, presentations |

All text, margins, markers, and line weights scale linearly with width relative to the 700px reference.
