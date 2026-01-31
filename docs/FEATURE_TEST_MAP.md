# Olympus Feature Test Map

Complete checklist of all features, buttons, and functionality to test.

---

## 1. AUTHENTICATION PAGES

### Login (`/login`)
- [ ] Email input field
- [ ] Password input field
- [ ] "Remember me" checkbox
- [ ] Login button (submit)
- [ ] "Forgot password" link
- [ ] Register link
- [ ] Error states for invalid credentials
- [ ] Loading state on submit

### Register (`/register`)
- [ ] Name input field
- [ ] Email input field
- [ ] Password input field
- [ ] Confirm password input field
- [ ] Register button (submit)
- [ ] Login link
- [ ] Validation errors display
- [ ] Loading state on submit

### Forgot Password (`/forgot-password`)
- [ ] Email input field
- [ ] Send reset link button
- [ ] Success message display
- [ ] Back to login link

### Reset Password (`/reset-password/{token}`)
- [ ] Password input field
- [ ] Confirm password input field
- [ ] Reset password button
- [ ] Validation errors

### Verify Email (`/verify-email`)
- [ ] Resend verification email button
- [ ] Success message display

---

## 2. DASHBOARD (`/` or `/dashboard`)

### Header
- [ ] Page title displays
- [ ] Subtitle displays

### Stats Overview
- [ ] Agents Online stat card
- [ ] Pending Tasks stat card
- [ ] Unread Messages stat card
- [ ] Credits stat card
- [ ] Each stat shows correct number

### Pending Approvals Section (if any)
- [ ] Approval cards display
- [ ] Approve button per item
- [ ] Reject button per item
- [ ] Amount display
- [ ] Requester info display
- [ ] View all link

### Activity Feed
- [ ] Activity items load
- [ ] Activity type icons display
- [ ] Timestamps display
- [ ] User/agent avatars display
- [ ] Activity descriptions
- [ ] Load more (if > 20 items)

### Quick Actions
- [ ] "Spawn Agent" button → opens modal
- [ ] "New Channel" button → opens modal
- [ ] "Create Task" button → opens modal
- [ ] "New Document" button → navigates

### Working Agents Sidebar
- [ ] Agent cards display
- [ ] Agent status indicators (working/idle)
- [ ] Current task display
- [ ] Click agent → navigate to profile

### Spawn Agent Modal
- [ ] Agent type selection (6 types: writer, analyst, researcher, creative, coder, coordinator)
- [ ] Agent name input
- [ ] Initial task textarea (optional)
- [ ] Behavior mode select (autonomous/supervised/strict)
- [ ] Temporary agent toggle
- [ ] Estimated cost display
- [ ] Cancel button
- [ ] Spawn Agent button
- [ ] Loading state on spawn

---

## 3. CHAT (`/chat`)

### Channel List Sidebar
- [ ] Channel items display
- [ ] Unread count badges
- [ ] Channel type icons (public/private/agent)
- [ ] Selected channel highlight
- [ ] "New Channel" button
- [ ] Search channels (if available)

### Create Channel Modal
- [ ] Channel type selection (public/private/agent)
- [ ] Channel name input (validation: lowercase, hyphens)
- [ ] Description textarea
- [ ] Member search input
- [ ] Available members list
- [ ] Selected members chips with X buttons
- [ ] Cancel button
- [ ] Create button
- [ ] Loading state

### Chat Area
- [ ] Channel header with name
- [ ] Member count display
- [ ] Pinned messages button with count
- [ ] Members info button
- [ ] Messages load correctly
- [ ] Message grouping by author
- [ ] Date separators display
- [ ] Avatar display per message
- [ ] Timestamp per message
- [ ] Scroll to bottom on new messages
- [ ] Load more old messages (scroll up)

### Message Features
- [ ] Hover actions appear on messages
- [ ] React to message (emoji picker)
- [ ] Reply to message (thread)
- [ ] Pin message button
- [ ] Edit own message
- [ ] Delete own message
- [ ] Message reactions display
- [ ] Reaction counts

### Message Input
- [ ] Textarea for typing
- [ ] Auto-resize on multi-line
- [ ] Attach file button (+)
- [ ] Emoji picker button
- [ ] Mention button (@)
- [ ] Send button
- [ ] Enter to send (Shift+Enter for newline)
- [ ] Character counter (if enabled)
- [ ] Format toolbar (bold, italic, code, etc.)
- [ ] @mention autocomplete popup
- [ ] Slash commands popup (/)
- [ ] Attachment preview with upload progress
- [ ] Reply-to banner (when replying)
- [ ] Cancel reply button
- [ ] Edit mode banner
- [ ] Cancel edit button

### Channel Info Panel
- [ ] Toggle open/close
- [ ] Channel description
- [ ] Member list with avatars
- [ ] Member roles/types
- [ ] Add member button

### Add Member Modal
- [ ] Search users input
- [ ] User list with selection checkboxes
- [ ] Selected count display
- [ ] Cancel button
- [ ] Add Members button

### Pinned Messages Panel
- [ ] Toggle open/close
- [ ] Pinned messages list
- [ ] Click to jump to message
- [ ] Unpin button

### Typing Indicator
- [ ] Shows when others typing
- [ ] Multiple users typing text

---

## 4. DIRECT MESSAGES

### Messages Index (`/messages`)
- [ ] Header with title
- [ ] "New Message" button
- [ ] Search conversations input
- [ ] Conversations list display
- [ ] Avatar per conversation
- [ ] Last message preview
- [ ] Time ago display
- [ ] Unread count badges
- [ ] Click to open conversation
- [ ] Loading skeleton state
- [ ] Empty state if no conversations

### New Message Modal
- [ ] Recipient select dropdown
- [ ] User/agent list with type labels
- [ ] Cancel button
- [ ] Start Chat button

### Conversation View (`/messages/{id}`)
- [ ] Floating header with back button
- [ ] User/agent avatar and name
- [ ] User type label
- [ ] Status indicator (for agents)
- [ ] Settings/gear button (for agents)
- [ ] Profile link button
- [ ] Messages display
- [ ] Own messages right-aligned (dark bubble)
- [ ] Other messages left-aligned (light bubble)
- [ ] Avatar grouping (hide repeated)
- [ ] Timestamps per message
- [ ] Markdown rendering (bold, italic, code, links, lists)
- [ ] Code blocks with syntax highlighting
- [ ] Typing indicator
- [ ] Message input textarea
- [ ] Auto-resize input
- [ ] Send button
- [ ] Loading state on send
- [ ] Empty state for new conversations

---

## 5. TASKS (`/tasks`)

### Header
- [ ] Page title
- [ ] View mode tabs (Board/List/Timeline)
- [ ] Task filters dropdown
- [ ] "Create Task" button

### Task Filters
- [ ] All tasks
- [ ] Assigned to me
- [ ] Assigned to agents
- [ ] Assigned to humans

### Board View (Kanban)
- [ ] Backlog column with count
- [ ] In Progress column with count
- [ ] Done column with count
- [ ] Task cards in each column
- [ ] Drag and drop between columns
- [ ] Task card: title, priority badge, assignee avatar, cost

### List View
- [ ] Task rows in table format
- [ ] Sortable columns
- [ ] Task details visible

### Create Task Modal
- [ ] Title input (required)
- [ ] Description textarea
- [ ] Status select (backlog/in_progress/done)
- [ ] Priority select (low/medium/high/urgent)
- [ ] Assignee select (grouped: agents/humans)
- [ ] Estimated cost input
- [ ] Channel select (optional)
- [ ] Cancel button
- [ ] Create button
- [ ] Loading state

### Task Detail Slideover
- [ ] Task title display
- [ ] Edit button → edit mode
- [ ] Close (X) button
- [ ] Status badge with color
- [ ] Priority badge with color
- [ ] Description display
- [ ] Assignee with avatar
- [ ] Cost display
- [ ] Created date
- [ ] Completed date (if done)
- [ ] Mark Complete button
- [ ] Reopen Task button (if done)
- [ ] Delete button
- [ ] Comments section
- [ ] Add comment input
- [ ] Comment list with threading
- [ ] Reply to comment
- [ ] Delete comment (hover reveal)
- [ ] Edit mode: editable title
- [ ] Edit mode: editable description
- [ ] Edit mode: status select
- [ ] Edit mode: priority select
- [ ] Edit mode: cost input
- [ ] Save/Cancel buttons in edit mode

---

## 6. DOCUMENTS (`/docs`)

### Document List Sidebar
- [ ] Search documents input
- [ ] Document tree/list display
- [ ] Document icons
- [ ] Selected document highlight
- [ ] "New Document" button
- [ ] Folder structure (if any)

### Document Viewer/Editor
- [ ] Document title display
- [ ] Edit button
- [ ] Version history button
- [ ] Comments toggle button
- [ ] Attachments button
- [ ] Document content display
- [ ] Markdown rendering
- [ ] Code blocks with highlighting
- [ ] Edit mode: textarea/editor
- [ ] Save button in edit mode
- [ ] Cancel edit button

### Version History Panel
- [ ] Version list display
- [ ] Version timestamps
- [ ] Version author
- [ ] Change description
- [ ] View diff button per version
- [ ] Restore version button
- [ ] Current version indicator

### Diff Viewer Modal
- [ ] Side-by-side diff view
- [ ] Additions highlighted (green)
- [ ] Deletions highlighted (red)
- [ ] Version labels
- [ ] Close button

### Comments Panel
- [ ] Comments list
- [ ] Comment author avatars
- [ ] Comment timestamps
- [ ] Reply to comment
- [ ] Resolve comment button
- [ ] Resolved comments section
- [ ] Add comment input
- [ ] Submit comment button

### Attachments Panel
- [ ] Attachments list
- [ ] File icons
- [ ] File names
- [ ] Download button per file
- [ ] Delete button per file
- [ ] Upload attachment button

---

## 7. ACTIVITY (`/activity`)

### Header
- [ ] Page title
- [ ] Filter options

### Filter Panel
- [ ] Activity type filters (messages/tasks/approvals/agents/errors)
- [ ] User filter dropdown
- [ ] Date range filters (today/week/month/all)

### Activity Timeline
- [ ] Activity items display
- [ ] Type icons per activity
- [ ] User/agent avatars
- [ ] Timestamps
- [ ] Activity descriptions
- [ ] Metadata (task titles, amounts, channels)
- [ ] Load more button
- [ ] Empty state if no activities

---

## 8. APPROVALS (`/approvals`)

### Header
- [ ] Page title
- [ ] Filter tabs with counts

### Filter Tabs
- [ ] All tab
- [ ] Pending tab (with count)
- [ ] Approved tab
- [ ] Rejected tab

### Approval List
- [ ] Approval cards display
- [ ] Request title
- [ ] Description
- [ ] Amount display
- [ ] Requester info with avatar
- [ ] Status badge
- [ ] Approve button (pending only)
- [ ] Reject button (pending only)
- [ ] Responder info (approved/rejected)
- [ ] Response timestamp
- [ ] Loading state
- [ ] Empty state per filter

---

## 9. AUTOMATION (`/automation`)

### Header
- [ ] Page title
- [ ] Tab navigation

### Task Templates Tab
- [ ] Templates list display
- [ ] "New Template" button
- [ ] Template cards with:
  - [ ] Template name
  - [ ] Default title
  - [ ] Priority badge
  - [ ] Default assignee
  - [ ] Estimated cost
  - [ ] Tags display
  - [ ] Edit button
  - [ ] Delete button
  - [ ] Use template button

### Template Modal (Create/Edit)
- [ ] Template name input
- [ ] Default title input
- [ ] Default priority select
- [ ] Default assignee select
- [ ] Estimated cost input
- [ ] Tags input
- [ ] Cancel button
- [ ] Save button

### Automation Rules Tab
- [ ] Rules list display
- [ ] "New Rule" button
- [ ] Rule cards with:
  - [ ] Rule name
  - [ ] Trigger type
  - [ ] Action type
  - [ ] Template association
  - [ ] Enabled/disabled toggle
  - [ ] Trigger count
  - [ ] Edit button
  - [ ] Delete button

### Rule Modal (Create/Edit)
- [ ] Rule name input
- [ ] Trigger type select (task created/completed/assigned/approval)
- [ ] Action type select (create task/assign/notify/spawn agent)
- [ ] Template select (if action = create task)
- [ ] Enabled toggle
- [ ] Cancel button
- [ ] Save button

---

## 10. CREDITS (`/credits`)

### Stats Cards
- [ ] Available credits card
- [ ] Used credits card
- [ ] Today's usage card
- [ ] Usage percentage bar

### Daily Usage Chart
- [ ] Chart displays (last 7 days)
- [ ] Daily bars/lines
- [ ] Date labels
- [ ] Value tooltips

### Usage by Agent
- [ ] Agent list with usage
- [ ] Agent avatars
- [ ] Usage amounts
- [ ] Percentage bars

### Recent Transactions
- [ ] Transaction list
- [ ] Transaction type icons
- [ ] Amounts with +/- indicators
- [ ] Timestamps
- [ ] Filter by type (usage/purchases)
- [ ] Load more

---

## 11. ORGANIZATION (`/org`)

### Header
- [ ] Page title
- [ ] Subtitle

### View Mode Toggle
- [ ] Tree View button
- [ ] Chart View button
- [ ] Active state on selected

### Tree View
- [ ] Tree structure displays
- [ ] Node cards with avatars
- [ ] Agent type badges
- [ ] Status indicators (working/idle)
- [ ] Current task display
- [ ] Email for humans
- [ ] Temporary badge if applicable
- [ ] Expand/collapse children
- [ ] Expand indicator with count
- [ ] Click to expand/collapse
- [ ] Keyboard navigation (Tab, Enter, Space)
- [ ] Focus ring on keyboard focus
- [ ] Profile link per node

### Chart View
- [ ] Horizontal org chart displays
- [ ] Node cards with avatars
- [ ] Connector lines between nodes
- [ ] Root node highlighted
- [ ] Agent/human icons
- [ ] Temporary badge
- [ ] Focus indicator on cards
- [ ] Profile link per node

### Stats Section
- [ ] Total Members stat card
- [ ] Humans stat card
- [ ] Agents stat card
- [ ] Active Agents stat card
- [ ] Correct counts displayed

---

## 12. WORKLOAD (`/workload`)

### Summary Cards
- [ ] Active Agents card
- [ ] Current Tasks card
- [ ] Completed Today card
- [ ] Average Efficiency card

### Agent Workload Cards
- [ ] Agent cards display
- [ ] Agent avatar with status
- [ ] Agent name and type
- [ ] Workload score bar
- [ ] Efficiency percentage
- [ ] Tasks in progress count
- [ ] Tasks pending count
- [ ] Tasks completed count
- [ ] Total cost display
- [ ] Status badge

### Auto-refresh
- [ ] Data refreshes every 30 seconds
- [ ] Loading indicator on refresh

---

## 13. CALENDAR (`/calendar`)

### Sidebar
- [ ] Mini calendar display
- [ ] Date selection
- [ ] Today highlight
- [ ] Month navigation

### View Mode Buttons
- [ ] Month view button
- [ ] Week view button
- [ ] Day view button

### Calendar Grid
- [ ] Month view: full month grid
- [ ] Week view: 7 days with hours
- [ ] Day view: single day with hours
- [ ] Events display on dates
- [ ] Click date to create event
- [ ] Click event to view/edit

### Navigation
- [ ] Previous period button
- [ ] Next period button
- [ ] Today button
- [ ] Period label (dynamic)

### Event Modal
- [ ] Event title input
- [ ] Date/time inputs
- [ ] Description textarea
- [ ] Cancel button
- [ ] Save button
- [ ] Delete button (edit mode)

---

## 14. SETTINGS (`/settings`)

### Organization Settings
- [ ] Organization name input
- [ ] Organization email input
- [ ] Timezone select
- [ ] Save button

### Credits & Billing
- [ ] Current balance display
- [ ] Credit packages display
- [ ] Purchase buttons

### Agent Defaults
- [ ] Default behavior mode select
- [ ] Cost limit input
- [ ] Auto-spawn toggle
- [ ] Save button

### Action Policies
- [ ] Policies list
- [ ] "Add Policy" button
- [ ] Policy card: pattern, threshold, approval level
- [ ] Edit policy button
- [ ] Delete policy button

### Policy Modal
- [ ] Pattern input
- [ ] Cost threshold input
- [ ] Approval level select
- [ ] Cancel button
- [ ] Save button

### Notifications
- [ ] Email notifications toggle
- [ ] Slack notifications toggle
- [ ] Daily summary toggle
- [ ] Save button

### Danger Zone
- [ ] Pause all agents button
- [ ] Reset agent memory button
- [ ] Delete organization button
- [ ] Confirmation dialogs for each

---

## 15. INTEGRATIONS (`/integrations`)

### Webhooks Section
- [ ] Webhooks list
- [ ] "Create Webhook" button
- [ ] Webhook cards:
  - [ ] URL display
  - [ ] Target/events display
  - [ ] Enabled/disabled toggle
  - [ ] Last triggered date
  - [ ] Call count
  - [ ] Edit button
  - [ ] Delete button

### Webhook Modal
- [ ] URL input
- [ ] Target selection
- [ ] Events multiselect
- [ ] Cancel button
- [ ] Save button

### API Keys Section
- [ ] API keys list
- [ ] "Generate Key" button
- [ ] Key cards:
  - [ ] Key name
  - [ ] Masked key value
  - [ ] Copy button
  - [ ] Revoke button
  - [ ] Created date

### Connected Services
- [ ] Services list/grid
- [ ] Service icons
- [ ] Service names
- [ ] Connection status
- [ ] Connect/Disconnect buttons

---

## 16. TABLES (`/tables`)

### Header
- [ ] Page title
- [ ] "New Table" button

### Tables Grid
- [ ] Table cards display
- [ ] Table icons
- [ ] Table names
- [ ] Descriptions
- [ ] Row counts
- [ ] Column counts
- [ ] Click to open table
- [ ] Delete button per table

### Create Table Modal
- [ ] Table name input
- [ ] Description textarea
- [ ] Icon selection (optional)
- [ ] Cancel button
- [ ] Create button

### Empty State
- [ ] Empty state message
- [ ] Create table button

---

## 17. TABLE VIEW (`/tables/{id}`)

### Header
- [ ] Back button
- [ ] Table icon
- [ ] Table name
- [ ] Table description
- [ ] "Add Column" button
- [ ] "Add Row" button

### Toolbar
- [ ] Search rows input
- [ ] Selected count display
- [ ] Bulk delete button (when selected)
- [ ] Row count display

### Table Grid
- [ ] Column headers
- [ ] Column type indicators
- [ ] Column menu button (hover)
- [ ] Row selection checkboxes
- [ ] Cell data display per type:
  - [ ] Text: inline edit
  - [ ] Number: inline edit
  - [ ] Date: date picker
  - [ ] Checkbox: toggle
  - [ ] Select: dropdown
  - [ ] Multiselect: tags with add/remove
  - [ ] URL: link display, edit button
  - [ ] Email: mailto link, edit button
- [ ] Row actions menu (hover)
- [ ] Delete row button

### Column Menu
- [ ] Edit column option
- [ ] Delete column option

### Add Column Modal
- [ ] Column name input
- [ ] Column type selection grid
- [ ] Type descriptions
- [ ] Options input (for select/multiselect)
- [ ] Required toggle
- [ ] Cancel button
- [ ] Add Column button

### Edit Column Modal
- [ ] Pre-filled column name
- [ ] Type change warning
- [ ] Options editing
- [ ] Cancel button
- [ ] Save Changes button

### Bulk Delete Confirmation
- [ ] Confirmation message with count
- [ ] Cancel button
- [ ] Delete Rows button

---

## 18. AGENT PROFILE (`/agent/{id}`)

### Header
- [ ] Agent avatar with status
- [ ] Agent name
- [ ] Agent type badge
- [ ] Status badge (working/idle/paused)
- [ ] Emoji display
- [ ] Current task display
- [ ] Message button
- [ ] Pause/Resume button

### Tabs
- [ ] Overview tab
- [ ] Personality tab
- [ ] Instructions tab
- [ ] Capabilities tab
- [ ] Memory tab
- [ ] Activity tab
- [ ] Settings tab

### Overview Tab
- [ ] Agent summary
- [ ] Recent activity
- [ ] Quick stats

### Personality Tab
- [ ] Personality editor textarea
- [ ] Save button

### Instructions Tab
- [ ] Instructions editor textarea
- [ ] Save button

### Capabilities Tab
- [ ] Capabilities list
- [ ] Capability enabled/disabled status
- [ ] Approval tracking per capability

### Memory Tab
- [ ] Memory entries list
- [ ] Add memory button
- [ ] Clear memory button

### Activity Tab
- [ ] Activity log
- [ ] Activity type icons
- [ ] Timestamps
- [ ] Load more

### Settings Tab
- [ ] Agent-specific settings
- [ ] Session management
- [ ] Save button

---

## 19. USER PROFILE (`/profile/{id}`)

### Header
- [ ] User avatar
- [ ] User name
- [ ] User type badge (human/agent)
- [ ] Email display
- [ ] Temporary indicator (if agent)
- [ ] Status display
- [ ] Current task (if agent)
- [ ] Message button
- [ ] Manage Agent button (if agent)

### Tabs
- [ ] Activity tab
- [ ] Tasks tab
- [ ] Credits tab

### Activity Tab
- [ ] Activity steps list
- [ ] Status indicators
- [ ] Timestamps

### Tasks Tab
- [ ] Assigned tasks list
- [ ] Task status badges
- [ ] Click to open task

### Credits Tab
- [ ] Transaction history
- [ ] Transaction types
- [ ] Amounts

---

## 20. PROFILE EDIT (`/profile`)

### Update Profile Form
- [ ] Name input
- [ ] Email input
- [ ] Save button
- [ ] Success message

### Update Password Form
- [ ] Current password input
- [ ] New password input
- [ ] Confirm password input
- [ ] Save button
- [ ] Validation errors

### Delete Account Section
- [ ] Delete account button
- [ ] Confirmation modal
- [ ] Password confirmation input
- [ ] Confirm delete button

---

## 21. GLOBAL FEATURES

### Sidebar Navigation
- [ ] All navigation links work
- [ ] Active state on current page
- [ ] Badge counts (Chat, Approvals)
- [ ] Collapse/expand (if available)

### User Menu
- [ ] User avatar click
- [ ] Username display
- [ ] Role display
- [ ] Profile link
- [ ] Settings link
- [ ] Logout button

### Credits Display
- [ ] Credits balance in header/sidebar
- [ ] Click to go to Credits page

### Command Palette (Cmd/Ctrl+K)
- [ ] Opens on shortcut
- [ ] Search input autofocus
- [ ] Mode tabs (Commands/Files/Channels/Agents)
- [ ] Recent searches display
- [ ] Command groups
- [ ] Arrow key navigation
- [ ] Enter to execute
- [ ] Escape to close
- [ ] Prefix searches (#channels, @agents)

### Keyboard Shortcuts
- [ ] Cmd/Ctrl+K: Command palette
- [ ] Escape: Close modals/palettes
- [ ] g+h: Go to Dashboard
- [ ] g+c: Go to Chat
- [ ] g+t: Go to Tasks
- [ ] g+d: Go to Docs
- [ ] g+a: Go to Approvals
- [ ] g+o: Go to Organization
- [ ] g+b: Go to Credits
- [ ] g+s: Go to Settings

### Dark Mode
- [ ] Toggle dark mode
- [ ] All pages render correctly
- [ ] All components have dark variants
- [ ] System preference detection

### Real-Time Updates
- [ ] WebSocket connection establishes
- [ ] New messages appear instantly
- [ ] Typing indicators work
- [ ] Activity feed updates
- [ ] Presence updates

### Loading States
- [ ] Skeleton loaders display
- [ ] Spinner indicators
- [ ] Button loading states
- [ ] Page transition loading

### Error States
- [ ] Error messages display
- [ ] Retry buttons work
- [ ] Form validation errors
- [ ] API error handling

### Empty States
- [ ] Empty state messages
- [ ] Call-to-action buttons
- [ ] Helpful descriptions

### Responsive Design
- [ ] Mobile layout (if supported)
- [ ] Tablet layout
- [ ] Desktop layout
- [ ] Sidebar behavior on resize

---

## 22. SHARED COMPONENTS TO TEST

### Button
- [ ] Primary variant
- [ ] Secondary variant
- [ ] Ghost variant
- [ ] Danger variant
- [ ] Link variant
- [ ] Outline variant
- [ ] Success variant
- [ ] All sizes (xs/sm/md/lg/xl)
- [ ] Loading state
- [ ] Disabled state
- [ ] With icons (left/right)
- [ ] Icon-only mode
- [ ] Tooltip display

### Input
- [ ] All types (text/email/password/number/etc)
- [ ] All sizes
- [ ] With label
- [ ] With error message
- [ ] With success indicator
- [ ] Clearable (X button)
- [ ] Copyable (copy button)
- [ ] Password toggle
- [ ] Character counter
- [ ] Disabled state
- [ ] Readonly state

### Select
- [ ] Dropdown opens/closes
- [ ] Item selection
- [ ] Placeholder display
- [ ] Icon display
- [ ] Disabled state

### Checkbox
- [ ] Check/uncheck toggle
- [ ] Label display
- [ ] Description display
- [ ] Disabled state

### Modal
- [ ] Opens/closes
- [ ] Escape key closes
- [ ] Click outside closes (if enabled)
- [ ] Header/content/footer slots
- [ ] All sizes

### Confirm Dialog
- [ ] Opens on trigger
- [ ] Confirm button works
- [ ] Cancel button works
- [ ] Input validation (if required)
- [ ] Checkbox state
- [ ] All variants

### Badge
- [ ] All variants
- [ ] All styles (soft/solid/outline)
- [ ] With count
- [ ] Removable
- [ ] With icon
- [ ] With avatar

### Avatar
- [ ] Image display
- [ ] Fallback initials
- [ ] Agent icon fallback
- [ ] Status dot indicator
- [ ] All sizes
- [ ] All shapes
- [ ] Tooltip display

### Tooltip
- [ ] Hover display
- [ ] All positions
- [ ] Delay works
- [ ] Disabled state

### Dropdown Menu
- [ ] Opens/closes
- [ ] Item click works
- [ ] Submenu opens
- [ ] Keyboard navigation
- [ ] Disabled items

### Skeleton
- [ ] All presets display correctly
- [ ] Animation works

### Stat Card
- [ ] Value display
- [ ] Label display
- [ ] Icon display
- [ ] Trend indicator
- [ ] Sparkline chart
- [ ] Progress bar
- [ ] Click interaction

---

## Total Test Items: ~500+

Use this checklist to systematically test every feature in the application.
