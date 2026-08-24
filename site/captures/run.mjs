import { spawnSync } from 'node:child_process'

const candidates = [process.env.TVISION_PHP, 'php', 'php85'].filter(Boolean)
let php = null

for (const candidate of candidates) {
  const probe = spawnSync(candidate, ['-r', 'echo PHP_VERSION_ID;'], {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'ignore'],
  })

  if (probe.status === 0 && Number.parseInt(probe.stdout, 10) >= 80500) {
    php = candidate
    break
  }
}

if (php === null) {
  console.error('capture: PHP 8.5 or newer is required; set TVISION_PHP to its executable')
  process.exit(1)
}

const result = spawnSync(php, ['captures/generate.php', ...process.argv.slice(2)], {
  stdio: 'inherit',
})

if (result.error) {
  console.error(`capture: ${result.error.message}`)
  process.exit(1)
}

process.exit(result.status ?? 1)
