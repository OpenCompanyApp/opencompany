# Files

> Finder-style workspace file manager for browsing disks, folders, uploads, previews, rename/delete actions, and disk-aware file search.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/w/{workspace}/files` |
| **Name** | `files` |
| **Auth** | Required (`auth`, `verified`, `resolve.workspace`) |
| **Layout** | AppLayout |

---

## Layout

```
+--------------------------------------------------------------------+
| FileToolbar: back/forward, breadcrumbs, search, view, disk, upload |
+----------------------+---------------------------------------------+
| FinderSidebar        | FileUploadZone                              |
| folder tree          |                                             |
| mobile slideover     | Grid view or list view                      |
|                      | inline new-folder input                     |
|                      | context menus and selection                 |
+----------------------+---------------------------------------------+
| FileStatusBar: item count and selected count                       |
+--------------------------------------------------------------------+
| FilePreview slideover, ConfirmDialog, hidden file input            |
+--------------------------------------------------------------------+
```

---

## Components

| Component | Path | Purpose |
|-----------|------|---------|
| `FileToolbar` | `Components/files/FileToolbar.vue` | Navigation, breadcrumbs, search, view switcher, disk switcher, upload/new-folder actions |
| `FinderSidebar` | `Components/files/FinderSidebar.vue` | Folder tree and mobile tree panel |
| `FileUploadZone` | `Components/files/FileUploadZone.vue` | Drag-and-drop upload target and upload progress |
| `FileIcon` | `Components/files/FileIcon.vue` | Folder/file icon rendering by MIME type |
| `FileInlineRename` | `Components/files/FileInlineRename.vue` | Inline rename control used in grid and list rows |
| `FileStatusBar` | `Components/files/FileStatusBar.vue` | Total/selected item summary |
| `FilePreview` | `Components/files/FilePreview.vue` | Preview slideover with download/delete affordances |
| `ContextMenu` | `Components/shared/ContextMenu.vue` | Open/quick-look/download/rename/delete actions |
| `ConfirmDialog` | `Components/shared/ConfirmDialog.vue` | Delete confirmation |

---

## Features & Interactions

- Back/forward history is local to the file manager.
- Breadcrumb navigation is built from the folder tree.
- Search is debounced at 300ms and reloads `/api/files`.
- View mode is persisted in `localStorage` as `files-view-mode`.
- Grid view shows image thumbnails when a `downloadUrl` exists.
- List view supports sorting by name, modified date, size, and kind.
- Selection supports single click, `Cmd/Ctrl` multi-select, and shift range selection.
- Double-clicking a folder navigates into it; double-clicking a file opens preview.
- Context menu actions are Open or Quick Look, Download when available, Rename, and Delete.
- Upload works through the hidden file input or drag/drop upload zone.
- Disk switching resets folder state, history, breadcrumbs, selection, and rename state.

---

## Data & API

| Surface | Endpoints |
|---------|-----------|
| Disk selector | `GET /api/disks` |
| File listing | `GET /api/files?parent_id=&search=&disk_id=` |
| Folder tree | `GET /api/files/tree` |
| Upload | `POST /api/files` multipart form with `file`, optional `parent_id`, optional `disk_id` |
| New folder | `POST /api/files/folder` with `name`, optional `parent_id`, optional `disk_id` |
| Rename/move | `PATCH /api/files/{id}` |
| Delete | `DELETE /api/files/{id}` |
| Copy/download/detail | `POST /api/files/{id}/copy`, `GET /api/files/{id}/download`, `GET /api/files/{id}` |

---

## States

| State | Description |
|-------|-------------|
| **Loading** | Spinner in the main content area while files load |
| **Empty folder** | Folder-dashed icon with drag/drop prompt |
| **No search results** | Search icon and "No files found" message |
| **Renaming** | Inline input replaces the filename label |
| **Deleting** | Danger `ConfirmDialog` blocks destructive action |
| **Previewing** | `FilePreview` opens for non-folder files |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Files.vue` | Page shell and file-manager wiring |
| `resources/js/composables/useFileManager.ts` | File manager state, API calls, navigation, selection, sorting, upload, rename, delete |
| `resources/js/composables/useApi.ts` | File and disk API wrappers |
| `resources/js/Components/files/*` | File-manager UI components |
