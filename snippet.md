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

---------



show tokens per second as metric


-----



---

autocomplete in monaco editor


----
\
clear overview which tools were used in a task, with icons etc.


--
luau type check and linter
---

automations are chats, can be chosen how much previous messages if at all can be included

--

oddities with automatin, retries, double responses, stuck, recovery on restart etc. better retries ui in tasks show page etc. Statefullness.

--

live update progress along tool calls

--

oath redirect works but 404, also check worspace context/specific stuff.

--
no available server

--

stress test, do lots of automations at once, see what happens.

--

better doc structure/tree tools

--

comms integrations are seperate section in opencompany, so linear can be double, but easily transfer credentials

--
code automation can have agent input

--
chatogrator pin