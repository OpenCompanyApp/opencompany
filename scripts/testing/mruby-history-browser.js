async page => {
  // UI-only fixture: all browser traffic is intercepted. The static server must
  // expose this checkout's public directory, not a live Laravel application.
  const origin = 'http://opencompany.test';
  const assets = 'http://127.0.0.1:18743';
  const manifest = await (await page.request.get(`${assets}/build/manifest.json`)).json();
  const entry = manifest['resources/js/app.ts'];
  const workspace = { id: 'fixture-workspace', slug: 'fixture', name: 'UI fixture', owner_id: 'fixture-user' };
  const agent = { id: 'fixture-agent', name: 'Fixture agent', type: 'agent', status: 'idle' };
  const automation = {
    id: 'fixture-automation', name: 'Ruby history fixture', executionType: 'script',
    agentId: agent.id, script: 'puts "current source"', scriptRuntime: 'opencompany-code-v1',
    cronExpression: '0 9 * * 1-5', timezone: 'UTC', keepHistory: true, isActive: false,
  };
  const revision = { id: 'fixture-revision', source_digest: 'a'.repeat(64), runtime: 'lua', status: 'archived', created_at: '2026-09-08T10:00:00Z' };
  const source = '  -- exact legacy source\nprint("<script>window.fixtureInjected=true</script>")\n\n';
  const state = { component: 'Automation/Edit', props: { automationId: automation.id, auth: { user: { id: 'fixture-user', name: 'Fixture human', email: 'fixture@example.invalid' } }, workspace, workspaces: [workspace], workspaceRole: 'owner', errors: {} }, url: '/fixture/automation/fixture-automation/edit', version: 'ui-fixture', clearHistory: false, encryptHistory: false };
  const html = `<!doctype html><html><head><meta name="csrf-token" content="fixture-only">${(entry.css || []).map(path => `<link rel="stylesheet" href="/build/${path}">`).join('')}</head><body><div id="app" data-page="${JSON.stringify(state).replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;')}"></div><script type="module" src="/build/${entry.file}"></script></body></html>`;
  const requests = [];
  await page.route('**/*', async route => {
    const request = route.request();
    const rawUrl = request.url();
    const url = { origin: rawUrl.split('/').slice(0, 3).join('/'), pathname: '/' + rawUrl.split('/').slice(3).join('/').split('?')[0] };
    requests.push({ method: request.method(), path: url.pathname });
    if (url.origin !== origin) return route.abort();
    if (url.pathname.startsWith('/build/')) {
      return route.fulfill({ response: await page.request.get(`${assets}${url.pathname}`) });
    }
    if (request.isNavigationRequest()) return route.fulfill({ contentType: 'text/html', body: html });
    if (request.method() !== 'GET') return route.fulfill({ status: 409, json: { message: 'UI fixture forbids mutations' } });
    if (url.pathname.endsWith('/script-revisions/fixture-revision')) return route.fulfill({ json: { ...revision, source } });
    if (url.pathname.endsWith('/script-revisions')) return route.fulfill({ json: { data: [revision], last_page: 2 } });
    if (url.pathname === '/api/automations/fixture-automation') return route.fulfill({ json: automation });
    if (url.pathname.endsWith('/agents')) return route.fulfill({ json: [agent] });
    if (url.pathname.startsWith('/api/')) return route.fulfill({ json: [] });
    return route.fulfill({ status: 404, body: 'UI fixture: no passthrough' });
  });
  await page.goto(`${origin}${state.url}`);
  await page.getByRole('region', { name: 'Script source history' }).waitFor();
  if (requests.some(request => request.path.endsWith('/fixture-revision'))) throw new Error('History fetched a source body before inspection');
  await page.getByRole('button', { name: 'aaaaaaaa · lua · archived' }).click();
  const preserved = await page.getByRole('dialog').locator('pre').textContent();
  if (preserved !== source) throw new Error('Source whitespace or escaping changed');
  if (await page.evaluate(() => window.fixtureInjected === true)) throw new Error('Archived source executed as HTML');
  await page.getByRole('button', { name: 'Replace editor text with this source' }).click();
  await page.getByRole('dialog').waitFor({ state: 'hidden' });
  await page.getByRole('button', { name: 'Next', exact: true }).click();
  await page.getByText('2 / 2', { exact: true }).waitFor();
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.getByRole('button', { name: 'aaaaaaaa · lua · archived' }).click();
  await page.getByRole('dialog').waitFor();
  if (!await page.evaluate(() => document.documentElement.classList.contains('dark'))) throw new Error('Dark theme did not apply');
  if (requests.some(request => request.method !== 'GET' && request.path.startsWith('/api/automations/'))) throw new Error('Inspect/load triggered an automation mutation');
  return { mode: 'mocked APIs; real built Vue assets', assertions: ['metadata-only initial history', 'exact escaped source', 'no HTML execution', 'explicit editor replacement closes dialog', 'pagination', 'dark mode', 'no automation mutation'], requests };
}
