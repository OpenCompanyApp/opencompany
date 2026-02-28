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

when a automation is triggered, the agent should be able to check previous ctx of an exectuion somehow to help create the automation script.

automations are in luau strictly typed unlike lua exec too (unless a human manually creates it). plan implementation for script automations.


--

also some snippet storage system, also in console. in skills

--

display task steps better in task show page, like a timeline or something.

---------


--
luau type check and linter
---

live update progress along tool calls


--
no available server

--

stress test, do lots of automations at once, see what happens.

--

--
code automation can have agent input

--
read lua docs before lua exec
--
refresh ouath

--

