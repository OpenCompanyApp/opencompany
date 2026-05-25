# Wayfinder Routing

OpenCompany uses Laravel Wayfinder for frontend route and action generation.
Ziggy is not part of the current routing stack.

## Source of Truth

- Laravel routes and controller actions remain the source of truth.
- Generated Vue/TypeScript sources live in:
  - `resources/js/routes`
  - `resources/js/actions`
  - `resources/js/wayfinder`
- Regenerate after route/controller changes with:

```bash
npm run wayfinder:generate
```

## Frontend Rules

- Import generated page routes from `@/routes` and route groups such as `@/routes/tasks`.
- Import generated API actions from `@/actions/App/Http/Controllers/Api/...`.
- Use `wayfinderRequest()` for Axios requests and `wayfinderFetch()` when a native `Response`, streaming body, `FormData`, or blob is needed.
- Use `useWorkspace()` helpers for workspace-scoped navigation instead of rebuilding `/w/{workspace_slug}` paths.
- Keep literal `/api/...` strings only for display text, public webhook examples, or generated Wayfinder dictionary keys.

## Build And Docker

The Vite Wayfinder plugin normally runs `php artisan wayfinder:generate` before building. The production Docker build cannot run that command in the Node-only asset stage, so Docker now:

1. Installs Composer dependencies.
2. Supplies the sibling integrations repo as a named Docker build context because Composer path repositories resolve through `../integrations`.
3. Generates Wayfinder sources in a PHP 8.4 stage.
4. Copies those generated sources into the Node asset stage.
5. Runs Vite with `WAYFINDER_SKIP_GENERATE=true`.

This preserves production parity while keeping the Node stage small.

When building the Docker image, pass the integrations context:

```bash
docker build --build-context integrations=../integrations -t opencompany .
```

The Compose files declare the same named build context with `additional_contexts`,
so Compose builds do not need a separate flag:

```bash
docker compose build app
docker compose -f docker-compose.local.yml build app
```

## Verification

Known useful checks:

```bash
npm run wayfinder:generate
npm run build
WAYFINDER_SKIP_GENERATE=true npm run build
rg -n "route\\(|Ziggy|@routes|ziggy|workspacePath|visitWorkspacePath" resources/js resources/views
```
