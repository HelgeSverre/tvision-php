# Tutorials

Build the first working screen, add interaction, and then grow the same ideas into windows, scrolling views, forms, and tests. Every stage points to runnable programs from this repository.

Before starting, make sure you have:

- PHP 8.5 or later with `ext-mbstring`.
- Composer.
- A POSIX terminal with `stty` for the interactive tutorials. The headless test works without an interactive TTY.

Install the package in a new directory:

```bash
mkdir hello-turbovision
cd hello-turbovision
composer require helgesverre/turbovision
```

If you are working from a source checkout instead, run `composer install` in that checkout. The bundled examples under `examples/php/tutorial/` are additional runnable references.

## Build a small application

1. [Build your first application](./first-application) creates a windowed program and explains the application, desktop, and view coordinates.
2. [Add commands and a dialog](./interactive-application) adds one action that is available from a menu, a shortcut, and a modal message box.
3. [Test without a terminal](./headless-testing) renders the same application into memory with Pest.

For a specific job after these tutorials, browse the [cookbook](/cookbook/). The [application reference](/reference/application) and [events, keys, and commands reference](/reference/events-keys-commands) are useful while building larger applications.

## Continue to a complete application

The [guide](./guide/) continues in small steps from the framework shell to status shortcuts, menus, window factories, custom views, scrolling panes, resize rules, modal dialogs, controls, and form-data transfer.
