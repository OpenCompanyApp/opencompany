async page => {
  // UI-only fixture: browser traffic is intercepted and the static server must
  // expose this checkout's built public directory, never a Laravel application.
  const origin = 'http://opencompany.test';
  const assets = 'http://127.0.0.1:18743';
  const manifest = await (await page.request.get(`${assets}/build/manifest.json`)).json();
  const entry = manifest['resources/js/app.ts'];
  const workspace = { id: 'fixture-workspace', slug: 'fixture', name: 'Console fixture', owner_id: 'fixture-user' };
  const state = {
    component: 'Developer/CodeConsole',
    props: {
      auth: { user: { id: 'fixture-user', name: 'Fixture human', email: 'fixture@example.invalid' } },
      workspace,
      workspaces: [workspace],
      workspaceRole: 'admin',
      errors: {},
    },
    url: '/w/fixture/developer/code-console',
    version: 'ui-fixture',
  };
  const html = `<!doctype html><html><head><meta name="csrf-token" content="fixture-only">${(entry.css || []).map(path => `<link rel="stylesheet" href="/build/${path}">`).join('')}</head><body><div id="app" data-page="${JSON.stringify(state).replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;')}"></div><script type="module" src="/build/${entry.file}"></script></body></html>`;
  const requests = [];
  const blockedBackgroundRequests = [];
  const validationSource = '42';
  const executionSource = 'puts "console smoke"\n42';
  const invalidSource = 'puts "before error"\ninvalid(';

  await page.addInitScript(source => sessionStorage.setItem('code-console-code', source), validationSource);
  await page.route('**/*', async route => {
    const request = route.request();
    const rawUrl = request.url();
    const url = { origin: rawUrl.split('/').slice(0, 3).join('/'), pathname: '/' + rawUrl.split('/').slice(3).join('/').split('?')[0] };
    // AppLayout starts a presence heartbeat. Block the attempt before it can
    // leave this fixture; it is background UI noise, not a console effect.
    if (url.origin === origin && url.pathname === '/api/users/fixture-user/presence' && request.method() === 'PATCH') {
      blockedBackgroundRequests.push({ method: request.method(), path: url.pathname, reason: 'presence heartbeat' });
      return route.abort();
    }
    requests.push({ method: request.method(), path: url.pathname });
    if (url.origin !== origin) return route.abort();
    if (url.pathname.startsWith('/build/')) {
      return route.fulfill({ response: await page.request.get(`${assets}${url.pathname}`) });
    }
    if (request.isNavigationRequest()) return route.fulfill({ contentType: 'text/html', body: html });
    if (url.pathname === '/api/code/execute' && request.method() === 'POST') {
      const payload = request.postDataJSON();
      if (payload.mode === 'validate' && payload.code === validationSource) {
        return route.fulfill({ json: { executionId: 'fixture-validate', validatedOnly: true, output: '', result: null, error: null, executionTime: 4 } });
      }
      if (payload.mode === 'execute' && payload.code === executionSource) {
        return route.fulfill({ json: { executionId: 'fixture-execute', validatedOnly: false, output: 'console smoke', result: 42, error: null, executionTime: 7 } });
      }
      if (payload.mode === 'execute' && payload.code === invalidSource) {
        return route.fulfill({ json: {
          executionId: 'fixture-error', validatedOnly: false, output: '', result: null, executionTime: 3,
          error: { type: 'syntax_error', message: 'unexpected end of input', line: 2, column: 8, suggestion: 'Close the expression.', retryable: true, effectStatus: 'none' },
        } });
      }
      return route.fulfill({ status: 422, json: { message: 'Unexpected fixture console request' } });
    }
    if (url.pathname.startsWith('/api/')) {
      if (request.method() !== 'GET') return route.fulfill({ status: 409, json: { message: 'UI fixture forbids mutations' } });
      return route.fulfill({ json: [] });
    }
    return route.fulfill({ status: 404, body: 'UI fixture: no passthrough' });
  });

  async function replaceEditor(source) {
    const input = page.locator('.monaco-editor textarea').first();
    // Monaco draws its cursor over the hidden textarea. Focus the accessible
    // input for keyboard editing instead of trying to click through that layer.
    await input.focus();
    await input.press('ControlOrMeta+A');
    await page.keyboard.insertText(source);
  }

  await page.goto(`${origin}${state.url}`);
  await page.getByRole('heading', { name: 'Code Console' }).waitFor();
  await page.getByRole('button', { name: 'Validate' }).click();
  await page.getByText('Validation passed. Nothing was executed.').waitFor();
  if (await page.getByText('Return:', { exact: false }).count()) throw new Error('Validation rendered an execution return value');
  if (await page.getByText('console smoke', { exact: true }).count()) throw new Error('Validation rendered execution output');

  await replaceEditor(executionSource);
  await page.getByRole('button', { name: 'Run' }).click();
  await page.getByText('console smoke', { exact: true }).waitFor();
  await page.getByText('Return: 42', { exact: true }).waitFor();

  await replaceEditor(invalidSource);
  await page.getByRole('button', { name: 'Run' }).click();
  await page.getByText('[syntax_error] at 2:8: unexpected end of input', { exact: true }).waitFor();
  await page.getByText('Repair: Close the expression.', { exact: true }).waitFor();
  await page.locator('.monaco-editor .squiggly-error').first().waitFor();

  // The output remains the most recent result, but its location marker belongs
  // only to the submitted source and must disappear after any editor change.
  await replaceEditor('42 # repaired');
  await page.waitForFunction(() => document.querySelectorAll('.monaco-editor .squiggly-error').length === 0);

  const consoleCalls = requests.filter(request => request.path === '/api/code/execute');
  if (consoleCalls.length !== 3 || consoleCalls.some(request => request.method !== 'POST')) {
    throw new Error(`Expected exactly three mocked console POSTs, received ${JSON.stringify(consoleCalls)}`);
  }
  if (requests.some(request => request.method !== 'GET' && request.path !== '/api/code/execute')) {
    throw new Error('UI fixture observed an unexpected mutation request');
  }
  return {
    mode: 'mocked API; real built Vue assets',
    assertions: ['validate success has no execution output', 'execute renders log and return 42', 'located error renders repair and marker', 'source edit clears stale marker'],
    requests,
    blockedBackgroundRequests,
  };
}
