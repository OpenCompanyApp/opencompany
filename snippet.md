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


ok, external integrations should only be able to be used in a lua script to save context, same for some internal stuff like docs/tables, calendars etc. Only the very impoirtant stuff like system, tasks, maybe agents, memory should be available as tools (in additon to lua api). all the rest lua,. I want a code first approach for tool callng (code mode kinda)

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

---------



show tokens per second as metric


-----

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
---

automations are chats, can be chosen how much previous messages if at all can be included

--

---------
Atlas
Lua Comprehensive QA Testing Complete ✅

───
Final Results

Test Suite                  Tests  Passed  Failed  Pass Rate
--------------------------  -----  ------  ------  ---------
Core Lua Library            62     62      0       100%     
App Namespace (Read)        15     11      4       73%      
Integrations                9      7       2       78%      
More Integrations + Writes  11     11      0       100%     
MCP Servers                 6      5       1       83%      
Edge Cases                  10     10      0       100%     
Table Writes                4      4       0       100%     
Rendering + Performance     11     11      0       100%     
Advanced Features           12     12      0       100%     
TOTAL                       140    133     7       95%      

───
What Works

Core Lua (100%)
• All math functions (27 functions tested)
• All string functions (12 functions tested)
• All table functions (4 functions tested)
• OS library (time, date, difftime)
• UTF-8 library (5 functions tested)
• Bit32 library (9 functions tested)

App Namespace (100% after fixes)
• app.chat.* — list_channels, search_messages, list_external_channels
• app.tables.* — list, get, get_rows, search_rows, add_row
• app.docs.* — list, get_tree
• app.calendar.* — list_events
• app.memory.* — recall, save
• app.workspace.* — list_agents, list_members
• app.lists.* — projects, item_statuses, all_items
• app.svg.* — render

Integrations (100%)
• Plausible Analytics ✅
• Exchange Rates ✅
• Celestial (moon phases) ✅
• World Bank ✅
• TickTick ✅
• CoinGecko ✅
• VegaLite ✅
• PlantUML ✅
• Mermaid ✅
• Typst ✅

MCP Servers (100%)
• Exa Search ✅
• DeepWiki ✅
• Context7 ✅
• Cloudflare Docs ✅

Write Operations (100%)
• ✅ Added test joke to Agent Joke Relay table
• ✅ Memory save working

Rendering (100%)
• SVG rendering (55-87ms)
• VegaLite charts (773-1845ms)
• PlantUML diagrams (~1ms)
• Mermaid diagrams (~1ms)

───
Documentation Issues Found (7)

Docs Say                                       API Actually Is                   
---------------------------------------------  ----------------------------------
`app.docs.tree`                                `app.docs.get_tree`               
`app.lists.list_projects`                      `app.lists.projects`              
`app.lists.list_statuses`                      `app.lists.item_statuses`         
`app.lists.list_all`                           `app.lists.all_items`             
`app.integrations.exchangerate.latest_rates`   `exchange_rates`                  
`app.integrations.worldbank.search_countries`  `list_countries`                  
`app.mcp.context7.resolve-library-uri`         `resolve_library_uri` (underscore)

───
Performance Benchmarks

Test                      Time      
------------------------  ----------
100K math operations      20ms      
Sort 1000 items           0.4ms     
String concat 1000 items  0.3ms     
Pattern match 1000 items  0.2ms     
SVG render                55-87ms   
VegaLite render           773-1845ms
PlantUML render           ~1ms      
Mermaid render            ~1ms      

───
Sandbox Limitations (Expected)

• coroutine library not available
• io library not available
• require not available
• CPU limit: 5 seconds
• Memory limit: 32MB

───
Test Data Created

⚠️ 1 test joke added to Agent Joke Relay table:
"Why do programmers confuse Halloween with Christmas? Because Oct 31 = Dec 25!"
Author: Atlas QA | Category: test | Rating: 5

Safe to delete if unwanted.