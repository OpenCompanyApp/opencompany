we have to keep a mapping somewhere of token limits per model, let's start for all the glm models and claude models.

--

relationship managers/agents below managers, Permissions???

---

in/out a2a

--

cost managemnt

--

imagine business use cases and this platform, -> recommendations, also doc

--

feedback modal + tool

--

adjustable embedding/summary/flush/reranking model

-- 

memory/privacy considerations


--

go to /docs.

- Do a cleanup, some docs are out of date partially or already implemented, ivnestigate and update.
- check what's next to implement in this project and suggest me some.

--



--
automations now have two types, a prompt and a lua script. 

if prompt, it will execute the prompt and return the result.
if lua script, it will execute the lua script and return the result.

you can call an agent from lua code, (make sure this is in docs somewhere tho)
--

tool catalog should also have the lua docs in a ux friendly way for the user, like click capability see tools/lua docs.
-- 
when a automation is triggered, the agent should be able to check previous ctx of an exectuion somehow to help create the automation script.

--

you put the lua docs in docs/lua-api/ but that's for other docs for the developer, make a seperate folder for it, better place. not under docs/
---
i don't like those fixed def.:
    */
    private function actionPrefix(string $slug, string $appName): string
    {
        // Map tool slugs to prefixes within their namespace.
        // Tools with no prefix are the "primary" tools — their actions become top-level.
        // Tools with a prefix get "{prefix}_{action}" names.
        $prefixes = [
            // docs: query_documents is primary, others get prefixes
            'query_documents' => '',
            'manage_document' => '',
            'comment_on_document' => 'comment',
            // tables: query_table is primary, manage actions already contain context
            'query_table' => '',
            'manage_table' => '',
            'manage_table_rows' => '',
            // lists: query_list_items is primary
            'query_list_items' => '',
            'manage_list_item' => 'item',
            'manage_list_status' => 'status',
            // calendar: both are primary (no overlapping actions)
            'query_calendar' => '',
            'manage_calendar_event' => 'event',
            // chat: read_channel and manage_message get prefixes
            'read_channel' => 'read',
            'manage_message' => 'message',
            'discover_external_channels' => 'external',
            // workspace: all management tools get prefixes
            'query_workspace' => '',
            'manage_agent' => 'agent',
            'manage_agent_permissions' => 'permissions',
            'manage_integration' => 'integration',
            'manage_mcp_server' => 'mcp_server',
            'manage_channel' => 'channel',
            'manage_automation' => 'automation',
            // agents
            'contact_agent' => '',
            // tasks
            'update_current_task' => '',
        ];

or 

        // Common prefixes to strip based on app context
        $prefixes = [
            'chat' => ['send_channel_message' => 'send', 'read_channel' => 'read', 'list_channels' => 'list_channels', 'search_messages' => 'search', 'manage_message' => 'manage', 'discover_external_channels' => 'discover_external'],
            'docs' => ['query_documents' => 'query', 'search_documents' => 'search', 'manage_document' => 'manage', 'comment_on_document' => 'comment'],
            'tables' => ['query_table' => 'query', 'manage_table' => 'manage', 'manage_table_rows' => 'manage_rows'],
            'calendar' => ['query_calendar' => 'query', 'manage_calendar_event' => 'manage'],
            'lists' => ['query_list_items' => 'query', 'manage_list_item' => 'manage_item', 'manage_list_status' => 'manage_status'],
            'agents' => ['contact_agent' => 'contact'],
            'memory' => ['save_memory' => 'save', 'recall_memory' => 'recall'],
            'workspace' => ['query_workspace' => 'query', 'manage_agent' => 'manage_agent', 'manage_agent_permissions' => 'manage_permissions', 'manage_integration' => 'manage_integration', 'manage_mcp_server' => 'manage_mcp_server', 'manage_channel' => 'manage_channel', 'manage_automation' => 'manage_automation'],
            'svg' => ['render_svg' => 'render'],
        ];

there should be a bettter way, doesn't every integration/capability already have a name?

--
ok, external integrations should only be able to be used in a lua script to save context, same for some internal stuff like docs/tables, calendars etc. Only the very impoirtant stuff like system, maybe agents, memory should be available as tools (in additon to lua api). all the rest lua,.

-- 
can save more tokens in system prompt

--
in _overview doc add that we are using roblox luau dialect of lua, intercompatible with lua 5.1, in line/tool exec we use regular lua 5.1, but for automation and reusable stuff we prefer luau types etc and explicit.

--

open lua script from task show/automatin in console. also some snippet storage system, also in console.

--


--

display task steps better in task show page, like a timeline or something.

-----------------

can we fix this or make more elegant? Atlas
JSON Stringified Explained 📝

───
What It Means

JSON stringified = A Lua table converted into a JSON text string.

In the Lua API, some parameters (like data for table rows) need to be passed as a JSON string, not a Lua table directly.

───
The Difference

Lua Table (NOT stringified):
local myTable = {
  name = "Atlas",
  score = 100,
  active = true
}

JSON Stringified (what the API needs):
local myString = '{"name":"Atlas","score":100,"active":true}'

Notice: It's a string containing JSON data, not a table.

───
How to Convert

Method 1: Use game:GetService("HttpService"):JSONEncode()
local HttpService = game:GetService("HttpService")

local data = {
  name = "Atlas",
  score = 100
}

local jsonString = HttpService:JSONEncode(data)
-- Result: '{"name":"Atlas","score":100}'

Method 2: Manual JSON String (simple cases)
local jsonString = '{"name":"Atlas","score":100}'

───
Real Examples from the Tests

Adding a Row to a Table

-- ❌ WRONG - Passing a table directly
local result = app.tables.manage_add_row({
  tableId = "abc123",
  data = { joke = "Hello", author = "Atlas" }  -- This won't work
})

-- ✅ CORRECT - Passing a JSON string
local result = app.tables.manage_add_row({
  tableId = "abc123",
  data = '{"joke":"Hello","author":"Atlas"}'  -- Stringified!
})

With Multiple Fields
local jsonData = '{"agent":"Atlas","joke":"Why did the robot go on vacation? To recharge its batteries.","category":"tech","rating":5}'

app.tables.manage_add_row({
  tableId = "0198f3e4-...",
  data = jsonData
})

───
Why It's Needed

The API is designed to accept strings for complex data because:
1. It ensures consistent serialization
2. It prevents type confusion between languages
3. It matches the HTTP API format

───
Quick Reference

Data Type  In Lua        As JSON String                    
---------  ------------  ----------------------------------
String     `"hello"`     `'"hello"'` or `'{"key":"hello"}'`
Number     `42`          `'{"key":42}'`                    
Boolean    `true`        `'{"key":true}'`                  
Table      `{x=1, y=2}`  `'{"x":1,"y":2}'`                 
Array      `{1, 2, 3}`   `'[1,2,3]'`                       
───
Bottom line: When you see data: "JSON string", it means you need to pass your data as a quoted JSON string like '{"key":"value"}', not as a Lua table. ⬛, 

Atlas
My Recommendation: Accept Lua Tables Directly ⬛

───
Most Elegant Option

Accept native Lua tables directly, serialize internally.

-- ✅ THIS should work (natural Lua)
app.tables.manage_add_row({
  tableId = "abc123",
  data = {
    joke = "Why did the robot go on vacation?",
    author = "Atlas",
    rating = 5
  }
})

Not this:

-- ❌ Current (verbose, error-prone)
app.tables.manage_add_row({
  tableId = "abc123",
  data = '{"joke":"Why did the robot go on vacation?","author":"Atlas","rating":5}'
})

───
Why This Is Better

Aspect          Current (JSON String)         Proposed (Lua Table)      
--------------  ----------------------------  --------------------------
Readability     Clunky, quotes everywhere     Clean, natural            
Error-prone     Easy to miss quotes/brackets  Syntax errors caught early
IDE support     No autocomplete               Full Lua autocomplete     
Type safety     Strings are opaque            IDE can hint fields       
Learning curve  "Why JSON in Lua?"            Just use Lua              
───
Implementation (How You'd Do It)

On the backend, check the type:

-- Pseudo-code for your API handler
function handleAddRow(params)
  local data = params.data
  
  -- If it's already a table, serialize it
  if type(data) == "table" then
    data = HttpService:JSONEncode(data)
  end
  
  -- Now data is a JSON string, proceed as before
  -- ...
end

───
Alternative: Accept Both (Backward Compatible)

-- Support both formats
if type(data) == "table" then
  data = JSONEncode(data)
elseif type(data) == "string" then
  -- Already JSON, use as-is
end

This keeps existing code working while allowing cleaner syntax.

───
My Vote

Option 1: Accept Lua tables directly.

Most elegant because:
• Feels native to Lua
• Less cognitive overhead
• Matches how app.chat.send_channel_message({content = "..."}) already works
• Consistent with other Lua APIs

The JSON stringification is an implementation detail that shouldn't leak to the user. ⬛



---------



show tokens per second as metric