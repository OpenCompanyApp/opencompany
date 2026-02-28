# Visual Programming Language Research

Research into visual programming options that could complement the existing Lua scripting system. The goal is to offer an alternative way to build automations and workflows — especially for users who aren't comfortable writing code.

## Requirements

- Embeddable in a web app (Vue 3 / TypeScript stack)
- Can generate or map to Lua code (our existing runtime) OR has its own execution engine
- 100% client-side editor (no server dependency for the UI)
- Actively maintained, mature ecosystem
- Supports custom blocks/nodes for our `app.*` bridge API

---

## Option 1: Google Blockly (Recommended)

**Type:** Block-based (Scratch-style drag-and-drop)
**License:** Apache 2.0
**Website:** https://developers.google.com/blockly
**Maintainer:** Raspberry Pi Foundation (transferred from Google, Nov 2025)

### Why it fits

- **Built-in Lua generator** — Blockly ships with code generators for JavaScript, Python, Lua, Dart, and PHP. We can generate Lua directly from blocks, then execute it through our existing Luau runtime. Zero new backend work.
- **Custom blocks** — We can create blocks for each `app.*` namespace (e.g. a "Send Message" block with channel/content inputs that generates `app.chat.send_message({ channel_id = "...", content = "..." })`).
- **100% client-side** — No server dependency. The editor is a single embeddable component.
- **Massive ecosystem** — Used by Scratch, MIT App Inventor, MakeCode, and hundreds of education/automation platforms. Battle-tested at scale.
- **Toolbox customization** — We can organize blocks into categories matching our tool catalog (Chat, Calendar, Lists, Integrations, MCP, etc.).

### Integration approach

1. Embed Blockly editor in a new page (e.g. `/developer/visual-editor`)
2. Define custom block definitions from our tool catalog API (`/api/tools/catalog`)
3. Use `luaGenerator.workspaceToCode(workspace)` to produce Lua code
4. Execute via our existing `/api/lua/execute` endpoint
5. Optionally show generated Lua alongside blocks (educational / debugging)

### Considerations

- Block-based UIs are intuitive but can feel limiting for complex logic (deep nesting, data transformations)
- Best suited for: simple automations, event handlers, "if this then that" workflows
- Less suited for: complex data processing, algorithmic work (where code is more natural)

---

## Option 2: Rete.js

**Type:** Node-based (dataflow graph editor)
**License:** MIT
**GitHub:** https://github.com/retejs/rete (11.8k stars)
**Website:** https://retejs.org

### Why it's interesting

- **Vue 3 support** — First-class `rete-vue-plugin` for rendering nodes in Vue 3. Fits our stack directly.
- **TypeScript-first** — Well-typed API, aligns with our codebase.
- **Dataflow + Control flow** — Supports both paradigms. Dataflow for data transformations (like n8n), control flow for sequential logic.
- **Built-in engine** — `rete-engine` can execute graphs directly in the browser or on the server, without needing to transpile to another language.

### Integration approach

1. Embed Rete editor as a Vue component
2. Define custom nodes for each tool (Send Message node, Create Event node, etc.)
3. Two execution options:
   - **Direct execution** via rete-engine (each node calls our API directly)
   - **Lua transpilation** — convert the graph to Lua code for our existing runtime (more work, but unified execution model)
4. Graphs are serialized as JSON, storable in the database

### Considerations

- No built-in Lua generation — would need custom transpilation layer or use its own engine
- Node-based editors have a steeper learning curve than block-based
- More powerful for complex workflows (branching, parallel paths, data transformations)
- The ecosystem is smaller than Blockly's, but actively maintained (last commit Dec 2025)

---

## Other options evaluated

### Flume
- React-only (no Vue support) — would require a wrapper or iframe
- Good concept (JSON graph → logic engine) but small community
- https://flume.dev

### React Flow
- React-only, focused on visualization rather than execution
- Good for building custom node editors but requires significant custom logic
- https://reactflow.dev

### NoFlo / Flowhub
- Flow-based programming for Node.js
- More server-oriented, less suitable for client-side embedding
- https://noflojs.org

### Scratch Blocks
- Fork of Blockly by Scratch Foundation, but uses Scratch VM instead of code generation
- More opinionated, less flexible for custom use cases
- Better to use Blockly directly

### n8n / Node-RED
- Full workflow automation platforms, not embeddable components
- Overkill for our use case — we already have the execution runtime (Lua)
- Interesting as UX inspiration though

---

## Comparison Matrix

| Criteria               | Blockly          | Rete.js          |
|------------------------|------------------|------------------|
| Editor type            | Block-based      | Node-based       |
| Lua generation         | Built-in         | Custom needed    |
| Vue 3 support          | Vanilla JS (wrap)| First-class      |
| Learning curve (users) | Very low         | Medium           |
| Complexity ceiling     | Medium           | High             |
| GitHub stars           | 12k+             | 11.8k            |
| License                | Apache 2.0       | MIT              |
| Best for               | Simple automations, beginners | Complex workflows, data pipelines |

---

## Recommendation

**Start with Blockly** as the first visual programming option:

1. **Lowest integration effort** — Lua generation is built-in, so blocks immediately produce executable code through our existing runtime. No new backend work.
2. **Lowest user barrier** — Block-based editing is the most accessible visual programming paradigm. Users drag blocks, snap them together, done.
3. **Reuses our tool catalog** — We can auto-generate block definitions from `/api/tools/catalog`, the same data source we use for Lua autocomplete.
4. **Show generated Lua** — Users can see the Lua code their blocks produce, creating a learning path from visual → text programming.

**Consider Rete.js later** for power users who want node-based workflow builders (think n8n-style), especially if we add data transformation or multi-step pipeline features.

### Possible phased approach

- **Phase 1:** Blockly editor page with custom blocks for core tools → generates Lua → executes
- **Phase 2:** Save/load block workspaces, use in automation rules
- **Phase 3:** Evaluate Rete.js for advanced workflow builder if demand exists
