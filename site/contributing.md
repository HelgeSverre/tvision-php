# Contributing

Contributions are welcome for the framework, examples, tests, and this documentation site. Keep each change focused, include the tests that demonstrate the behaviour, and update the relevant documentation when a public API or workflow changes.

## Set up the repository

The framework requires PHP 8.5 or later and the `mbstring` extension. Composer installs the PHP development tools. Interactive examples need a POSIX terminal with `stty`; `pcntl` and `posix` are optional but improve signal and TTY support.

```bash
git clone https://github.com/HelgeSverre/tvision-php.git
cd tvision-php
php -v
composer install
```

The docs site is a separate Node project in `site/`. Use a supported Node.js release and the checked-in lockfile:

```bash
cd site
npm ci
npm run dev
```

`npm run dev` starts the local VitePress server. Build the static site before submitting documentation changes:

```bash
npm run build
```

## Find the right place

| Change | Location |
| --- | --- |
| Framework implementation | `src/` |
| Unit and feature coverage | `tests/Unit/` and `tests/Feature/` |
| Real-terminal coverage | `tests/Integration/` |
| Pixel-level snapshots | `tests/Visual/` and `tests/Visual/__baselines__/` |
| Runnable PHP examples | `examples/php/` |
| Benchmark harness | `benchmarks/core.php` |
| Package scripts and dependencies | `composer.json` |
| Published documentation and theme | `site/` |
| Historical material and working notes | `docs/` |

Do not move internal material from `docs/` into the published site without checking that it is current and suitable for users.

## Write framework changes

Use strict PHP, typed properties and parameters, and the public namespace `HelgeSverre\TurboVision`. Keep terminal access behind the driver and screen abstractions so the same feature can run with `HeadlessDriver` in tests. Preserve UTF-8 and terminal-cell correctness: display width is not the same as PHP byte length.

Add focused coverage alongside the feature:

- Put isolated behaviour in the matching `tests/Unit/<subsystem>/` directory.
- Put application wiring and user-visible flows in `tests/Feature/`.
- Exercise a real PTY only when the behaviour depends on terminal setup, emitted bytes, or shutdown.
- Update a visual baseline only for an intentional rendered change.

Use existing tests and examples as the closest local pattern before adding a new subsystem. Keep examples runnable directly, with their entrypoint guarded so `bin/tv-render` can load the application class without starting its event loop.

## Run tests and analysis

Run the full suite for any framework change:

```bash
composer test
composer stan
```

The standard test configuration includes Unit and Feature suites. Visual and Integration suites are excluded by default.

Run a single test file while iterating:

```bash
vendor/bin/pest tests/Unit/Events/KeyTest.php
```

Run a named test or test suite when narrowing a failure:

```bash
vendor/bin/pest --filter="function keys decode"
vendor/bin/pest --testsuite=Unit
vendor/bin/pest --testsuite=Feature
```

Run terminal and visual checks when the changed code affects ANSI output, terminal setup, mouse protocols, or rendering:

```bash
vendor/bin/pest --group=integration
vendor/bin/pest --group=visual
```

Integration checks require `proc_open` and usable PTY support. Visual checks require Chrome or Chromium plus ImageMagick's `magick` command. They skip themselves when their prerequisites are unavailable.

Use test-impact analysis when PHP is running with PCOV or Xdebug coverage enabled:

```bash
composer test:tia
```

Without an enabled coverage driver, that command intentionally runs the complete suite. `composer test` remains the required full check.

Run static analysis after changing source, examples, test code, or command-line tools:

```bash
composer stan
```

PHPStan is configured at its maximum level for `src`, `tests`, `bin`, and `examples`, excluding the visual snapshot and real-terminal shell glue. There is no repository formatter command; match the surrounding PHP style and use trailing commas in multiline argument, array, and parameter lists.

## Exercise the tools

Run a representative interactive application in a terminal:

```bash
composer demo
composer demo:kitchensink
composer demo:studio
composer demo:calendar
composer demo:bios
composer demo:opencode
```

Other available example entrypoints are `composer demo:workbench` and `composer demo:html`. Exit interactive applications with their on-screen controls so terminal cleanup is exercised.

Render an application without taking over the terminal:

```bash
php bin/tv-render examples/php/tutorial/Guide05.php frame.html
php bin/tv-shot examples/php/tutorial/Guide05.php frame.png
```

`tv-render` writes a headless HTML frame. `tv-shot` converts the same rendering path to PNG and requires Chrome or Chromium. To refresh an intentional visual-test baseline, render the matching example into its committed baseline path, then run the visual group:

```bash
bin/tv-shot examples/php/tutorial/Guide01.php tests/Visual/__baselines__/guide01.png
vendor/bin/pest --group=visual
```

Compile a help source when changing the help format or its compiler:

```bash
php bin/tvhc source.txt output.tvhelp contexts.php
```

The third argument is optional; omit it when no generated PHP context file is needed.

Run the seeded fuzz harness for parser, drawing, lifecycle, I/O, geometry, widget, signal, and PTY-resize invariants:

```bash
composer fuzz
php bin/tv-fuzz --iterations 200 --seed 12345
```

Keep the reported seed when a fuzz run fails so the same case can be reproduced.

Run the dependency-free benchmark harness after a change in a hot path:

```bash
composer bench
php benchmarks/core.php --json
```

Compare measurements on the same machine and PHP configuration; the harness reports grapheme, drawing, input decoding, presentation, HTML rendering, screen flush, and nested-view cases.

## Update documentation

Published docs live entirely under `site/`. Update the relevant page whenever a public API, requirement, or workflow changes, and add new pages to the VitePress sidebar in `site/.vitepress/config.ts`.

Use code that matches the current public API. Include `declare(strict_types=1);`, imports, prerequisites, and a visible outcome where they help a reader run the example. Keep code blocks short enough to scan and ensure every linked page resolves from its final location.

For docs-only work, run the site from `site/` and finish with:

```bash
npm run build
```

For documentation that describes a changed framework API, run the relevant PHP tests and `composer stan` as well.

## Before opening a pull request

- Rebase or merge the current target branch as required by your workflow.
- Keep generated runtime files, dependency directories, and test output out of the change.
- Add or update the focused test coverage.
- Run `composer test` and `composer stan` for PHP changes.
- Run `npm run build` from `site/` for documentation or theme changes.
- Run the visual or integration group when the change reaches those boundaries.
- Describe the user-visible effect and the checks you ran in the pull request.

The CI workflow installs PHP 8.5 with `mbstring`, `pcntl`, and `posix`, then runs Composer validation, dependency auditing, the standard test suite, and PHPStan.
