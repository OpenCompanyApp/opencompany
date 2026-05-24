const target = process.env.APP_URL || process.argv[2]

if (!target) {
  console.error('Set APP_URL or pass a URL, for example: npm run ngrok:check-assets -- https://example.ngrok-free.dev')
  process.exit(1)
}

const response = await fetch(target, {
  headers: { 'ngrok-skip-browser-warning': 'true' },
})

if (!response.ok) {
  console.error(`Asset check failed: ${target} returned HTTP ${response.status}`)
  process.exit(1)
}

const html = await response.text()
const forbidden = [
  'http://localhost:',
  'https://localhost:',
  'http://127.0.0.1:',
  'https://127.0.0.1:',
  'http://[::1]:',
  'https://[::1]:',
  '/@vite/client',
]

const match = forbidden.find(value => html.includes(value))
if (match) {
  console.error(`Asset check failed: page HTML still references local Vite assets (${match}). Run npm run ngrok:prepare before sharing ngrok.`)
  process.exit(1)
}

console.log(`Asset check passed: ${target} does not reference local Vite assets.`)
