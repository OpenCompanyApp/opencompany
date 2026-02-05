# Documents

> Collaborative document management with a sidebar tree, rich viewer, version history, comments, and file attachments.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/docs` |
| **Name** | `docs` |
| **Auth** | Required |
| **Layout** | AppLayout |

---

## Layout

```
+--------------------------------------------------------------------+
| Mobile Toolbar (md:hidden)                                         |
| [Docs] Selected doc title          [Attachments] [Comments]        |
+------------+-------------------------------------------------------+
|            |                                                       |
| DocList    |  DocViewer                                             |
| (w-72)     |  ┌─────────────────────────────────────────────┐      |
|            |  │ DocViewerHeader                              │      |
| ┌────────┐ |  │ title, author, updated, star/pin/lock       │      |
| │ Search │ |  ├─────────────────────────────────────────────┤      |
| ├────────┤ |  │ Toolbar (when editing)                      │      |
| │ Doc    │ |  │ bold, italic, underline, headings, insert   │      |
| │ Tree   │ |  ├─────────────────────────────────────────────┤      |
| │ Items  │ |  │                                             │      |
| │        │ |  │ DocViewerContent                            │      |
| │  Folder│ |  │ (rendered markdown / editable content)      │      |
| │   Doc  │ |  │                                             │      |
| │   Doc  │ |  │                                             │      |
| │  Folder│ |  ├─────────────────────────────────────────────┤      |
| │   Doc  │ |  │ Footer: word count, chars, read time        │      |
| └────────┘ |  └─────────────────────────────────────────────┘      |
|            |                                                       |
+------------+---+---------------------------------------------------+
                 | Version History / Attachments / Comments Sidebar   |
                 | (w-80, slide-left transition, toggled on demand)   |
                 +---------------------------------------------------+

Floating Actions (desktop, bottom-right):
  [Attachments FAB]  [Comments FAB]
```

---

## Components

| Component | Path | Purpose |
|-----------|------|---------|
| `DocList` | `Components/docs/DocList.vue` | Sidebar with search, document/folder tree, create dropdown |
| `DocTreeItem` | `Components/docs/DocTreeItem.vue` | Recursive tree row for each document/folder |
| `DocTreeItemRow` | `Components/docs/doc-tree/DocTreeItemRow.vue` | Inner row rendering for tree items |
| `DocViewer` | `Components/docs/DocViewer.vue` | Main document viewer with header, toolbar, content, TOC, footer |
| `DocViewerHeader` | `Components/docs/doc-viewer/DocViewerHeader.vue` | Title, author, timestamps, star/pin/lock, edit/share actions |
| `DocViewerContent` | `Components/docs/doc-viewer/DocViewerContent.vue` | Rendered document content, editable mode, section tracking |
| `CodeBlock` | `Components/docs/doc-viewer/CodeBlock.vue` | Syntax-highlighted code blocks within documents |
| `CommentThread` | `Components/docs/CommentThread.vue` | Single comment with reply form, resolve/unresolve, delete |
| `DocumentDiffViewer` | `Components/docs/DocumentDiffViewer.vue` | Fullscreen modal for side-by-side version comparison |
| `DocumentAttachments` | `Components/docs/DocumentAttachments.vue` | Attachment sidebar with drag-and-drop upload, file list |
| `DocItem` | `Components/docs/DocItem.vue` | Alternative document list item rendering |

---

## Features & Interactions

### Document Tree Sidebar
- **Search**: filters documents by title or author name in real time
- **Create dropdown**: "New Document" and "New Folder" options via `DropdownMenu`
- **Tree structure**: documents nested under folders via `parentId`, rendered recursively by `DocTreeItem`
- **Selection**: clicking a document loads it in the viewer; unsaved-change guard shows a confirm dialog
- **Stats**: header shows document count and folder count

### Document Viewing & Editing
- **View mode**: renders document content via `DocViewerContent` with optional table of contents sidebar
- **Edit mode**: triggered from header, shows a rich formatting toolbar (bold, italic, underline, strikethrough, code, headings, links, images, tables, code blocks, quotes, dividers)
- **Save/Cancel**: save button disabled until changes detected; cancel discards edits
- **Footer stats**: word count, character count, estimated read time

### Version History
- **Panel**: right sidebar (w-80) listing all versions with author avatar, date, change description
- **Compare**: opens `DocumentDiffViewer` modal with side-by-side LCS-based diff, line numbers, addition/deletion stats
- **Restore**: restores a previous version (creates a snapshot of current state first), requires confirm dialog
- **API calls**: `fetchDocumentVersions`, `restoreDocumentVersion`

### Comments
- **Sidebar**: right panel (w-80) with comment list and add-comment form (textarea + submit button)
- **Threads**: each `CommentThread` supports reply, resolve/unresolve, and delete actions
- **Resolved state**: resolved comments show strikethrough text and a green badge
- **API calls**: `fetchDocumentComments`, `addDocumentComment`, `updateDocumentComment`, `deleteDocumentComment`

### Attachments
- **Sidebar**: right panel (w-80) with drag-and-drop upload zone (10MB limit) and file list
- **File display**: icon colored by MIME type, file name, size, date, download/delete actions on hover
- **Upload progress**: overlay spinner while uploading
- **API calls**: `fetchDocumentAttachments`, `uploadDocumentAttachment`, `deleteDocumentAttachment`

### Real-time Updates
- Subscribes to `document:comment:new` and `document:updated` events via `useRealtime`
- Automatically refreshes comments, versions, or documents when events arrive for the selected document

---

## States

| State | Description |
|-------|-------------|
| **Empty (no documents)** | DocList shows centered icon, "No documents yet" message, and a "Create document" button |
| **Empty (search no results)** | Magnifying glass icon, "No results" message with "Clear search" link |
| **No document selected** | DocViewer shows dashed file icon and "No document selected" message |
| **Loading** | DocList shows 5 skeleton rows; DocViewer shows full skeleton (header + content placeholders) |
| **Editing** | Toolbar slides down with formatting actions; save/cancel buttons appear; auto-save indicator |
| **Saving** | Spinner icon in toolbar with "Saving..." text; save button disabled |
| **No versions** | Version history panel shows "No version history yet" centered text |
| **No comments** | Comments panel shows "No comments yet" centered text |
| **No attachments** | Attachments panel shows "No attachments yet" centered text |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| **Desktop (md+)** | DocList sidebar always visible at w-72; viewer fills remaining space; floating action buttons (bottom-right) for attachments/comments |
| **Mobile (<md)** | Mobile toolbar at top with doc-list toggle, title, attachment/comment buttons; DocList becomes full-screen overlay (fixed inset-0 z-30); Version/attachment/comment sidebars take full screen |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Docs.vue` | Page component with layout orchestration and API integration |
| `resources/js/Components/docs/DocList.vue` | Document tree sidebar |
| `resources/js/Components/docs/DocTreeItem.vue` | Recursive tree item |
| `resources/js/Components/docs/doc-tree/DocTreeItemRow.vue` | Tree item row |
| `resources/js/Components/docs/DocViewer.vue` | Main document viewer |
| `resources/js/Components/docs/doc-viewer/DocViewerHeader.vue` | Viewer header bar |
| `resources/js/Components/docs/doc-viewer/DocViewerContent.vue` | Viewer content area |
| `resources/js/Components/docs/doc-viewer/CodeBlock.vue` | Code block rendering |
| `resources/js/Components/docs/CommentThread.vue` | Comment with replies |
| `resources/js/Components/docs/DocumentDiffViewer.vue` | Version diff modal |
| `resources/js/Components/docs/DocumentAttachments.vue` | Attachment management sidebar |
| `resources/js/Components/docs/DocItem.vue` | Document list item |
| `resources/js/composables/useApi.ts` | API composable (document CRUD, comments, versions, attachments) |
| `resources/js/composables/useRealtime.ts` | Real-time event subscriptions |
