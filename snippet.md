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


-- 
when a automation is triggered, the agent should be able to check previous ctx of an exectuion somehow to help create the automation script.

--


ok, external integrations should only be able to be used in a lua script to save context, same for some internal stuff like docs/tables, calendars etc. Only the very impoirtant stuff like system, maybe agents, memory should be available as tools (in additon to lua api). all the rest lua,.

-- 
can save more tokens in system prompt for lua libs

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


-----

issues: 

Atlas
Lua System QA Complete — Full Report ✅

───
Summary

Test Suite        Tests  Passed  Failed  Pass Rate
----------------  -----  ------  ------  ---------
Core Lua          6      6       0       100%     
App Namespaces    27     25      2       93%      
Integrations      10     10      0       100%     
MCP Servers       2      2       0       100%     
Write Operations  12     12      0       100%     
Edge Cases        24     24      0       100%     
TOTAL             81     79      2       98%      
───
What Works

Core Lua (100%)
• Variables, tables, math, strings, patterns
• bit32, utf8, os libraries
• Metatables, closures, recursion
• Algorithms (Fibonacci, factorial, quicksort)
• Error handling with pcall

App Namespace (100%)
• app.chat — list, search, send
• app.tables — list, get, search, add rows
• app.docs — list, search
• app.calendar — list events
• app.memory — save, recall
• app.workspace — list agents, members
• app.lists — kanban projects, statuses
• app.svg — render SVG to PNG

Integrations (100%)
• Plausible Analytics
• TickTick
• CoinGecko
• Exchange Rates
• Celestial (astronomy)
• World Bank
• VegaLite charts
• Typst PDFs
• PlantUML
• Mermaid

MCP Servers (100%)
• Exa Web Search
• Context7 (app.mcp["context7"]["resolve-library-uri"])

───
Issues Found (2)

Issue                   Cause                                                Fix                                              
----------------------  ---------------------------------------------------  -------------------------------------------------
Context7 function name  Underscores in docs vs dashes in API                 Use `app.mcp["context7"]["resolve-library-uri"]` 
Tables return string    `query_list_tables` returns string, not table array  Minor parsing issue, doesn't affect functionality
───
Performance

Metric         Value         
-------------  --------------
100K math ops  0.024s        
Ops/second     4.1M          
Memory usage   ~700KB average
Max memory     32MB          
CPU limit      5 seconds     
───
Key Findings

1. All 22 namespaces functional
2. Write operations work — Added joke to table successfully
3. Rendering works — SVG, VegaLite, Typst all generate output
4. Error handling solid — pcall catches errors correctly
5. Large data supported — 10K item tables, 5K+ char strings

───
Recommendations

1. Fix Context7 docs — Change resolve_library_uri to resolve-library-uri in docs
2. Fix tables return type — query_list_tables should return array, not string
3. Add JSON stringify helper — Auto-convert tables to JSON for data param

───
Bottom line: Lua system is production-ready. 98% pass rate with only minor documentation/formatting issues. ⬛



---

autocomplete in monaco editor


----
\
clear overview which tools were used in a task, with icons etc.

--

tool catalog should only show installed integrations

--
luau type check and linter