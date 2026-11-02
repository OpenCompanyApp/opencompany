# Ruby patterns

Examples with `app.*` require the matching capability and real workspace IDs.
Read the current generated contract before copying an external call. Pure data
examples can run in the no-capability Code Console.

## Join and summarize data

```ruby
people = [{id: 1, name: "Ada"}, {id: 2, name: "Grace"}]
assignments = [{person_id: 2, title: "Review release"}]
names = {}
people.each { |person| names[person[:id]] = person[:name] }
assignments.map { |item| {owner: names[item[:person_id]], title: item[:title]} }
```

## Decode structured text without evaluation

```ruby
data = JSON.parse('{"items":[{"id":1,"active":false}]}')
{items: data[:items].map { |row| {id: row[:id], active: row[:active]} }}
```

## Read a bounded workspace table

```ruby
rows = app.tables.get_rows(table_id: "00000000-0000-4000-8000-000000000001", limit: 10)
{count: rows.length, sample: rows.take(3)}
```

For paginated tools, request a small page, return its next cursor and process a
bounded number of pages. Never assume every response has a `data` or `results`
field. A compiler pass cannot establish the provider's response shape.

## Small reusable transformations

```ruby
def active_ids(rows)
  rows.select { |row| row[:active] }.map { |row| row[:id] }
end
active_ids([{id: 1, active: true}, {id: 2, active: false}])
```

Keep writes separate from exploratory reads. Avoid large fan-outs, full response
logging, speculative API names and whole-script retries after partial effects.
