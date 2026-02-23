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
---

automations are chats, can be chosen how much previous messages if at all can be included

--

