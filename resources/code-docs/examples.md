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

## Find a table by name and aggregate every row

`app.tables.get_rows` returns an ARRAY of rows keyed by column name, plus
`"_id"`. One call returns at most `limit` rows (default 25, max 100), so page
with `offset` until a short page. Compute in Ruby and return a small summary.

```ruby
table = app.tables.list.find { |t| t["name"] == "Deals" }
rows = []
offset = 0
20.times do                      # hard cap: 20 pages x 100 rows
  page = app.tables.get_rows(table_id: table["id"], limit: 100, offset: offset)
  rows.concat(page)
  break if page.length < 100
  offset += 100
end
won = rows.select { |r| r["Stage"] == "won" }
{rows: rows.length, won_count: won.length, won_total: won.sum { |r| r["Amount"].to_i }}
```

Group and sum per key with a plain hash:

```ruby
totals = Hash.new(0)
rows.each { |r| totals[r["Owner"]] += r["Amount"].to_i }
totals.sort_by { |_owner, amount| -amount }.to_h
```

When you do not know a result shape yet, return a tiny sample
(`{sample: page.first(2)}`) in one execution, then write the real program.
Read `Returns:` in `code_read_doc` first; never assume a `data` or `results` key.

## Small reusable transformations

```ruby
def active_ids(rows)
  rows.select { |row| row[:active] }.map { |row| row[:id] }
end
active_ids([{id: 1, active: true}, {id: 2, active: false}])
```

Keep writes separate from exploratory reads. Avoid large fan-outs, full response
logging, speculative API names and whole-script retries after partial effects.
