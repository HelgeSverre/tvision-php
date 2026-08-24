# Test without a terminal

The same `HelloApp` can run against an in-memory terminal. This lets you test the actual view tree and drawing code without switching the test process into terminal mode.

## Install Pest

From the project directory created in the first tutorial:

```bash
composer require --dev pestphp/pest:^5.0
./vendor/bin/pest --init
```

Keep `hello.php` next to `composer.json`. Its `HelloApp::runningAsMain(__FILE__)` guard is important: loading the class from a test must not start the interactive event loop.

## Render a deterministic frame

Create `tests/HelloAppTest.php`:

```php
<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

require_once __DIR__ . '/../hello.php';

it('renders the welcome window', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new HelloApp(new Screen($driver));

    $app->bootForTest();
    $app->drawAndFlushForTest();

    $frame = implode("\n", $app->backRowsForTest());

    expect($frame)
        ->toContain('Hello, PHP')
        ->toContain('TurboVision for PHP lives!')
        ->toContain('Help');
});
```

Run the test:

```bash
./vendor/bin/pest
```

The `HeadlessDriver` reports the dimensions you pass to its constructor, accepts scripted input, and captures output without terminal I/O. `Screen` uses that driver exactly as it uses the real ANSI driver.

`bootForTest()` initializes the screen and builds the menu, desktop, status line, and window tree. `drawAndFlushForTest()` performs one normal draw and presentation pass. `backRowsForTest()` returns the character rows from the current back buffer.

## Test the About command

You can route an application command directly. Feed the Enter byte before dispatching so the modal message box accepts its default button instead of waiting for interactive input.

Add this import to the same test file:

```php
use HelgeSverre\TurboVision\Events\Event;
```

Then add this test:

```php
it('opens and dismisses the About dialog', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new HelloApp(new Screen($driver));
    $app->bootForTest();

    $driver->feedInput("\r");
    $event = Event::command(HelloApp::About);
    $app->handleEvent($event);

    expect($event->isNothing())->toBeTrue();
});
```

The command constant is public in the previous tutorial so this test can use it directly. It can remain private when tests exercise the action through a menu or shortcut instead.

## Test a resize

`HeadlessDriver` can also report a resize. This test confirms that the application reflows into the new dimensions without opening a real terminal:

```php
it('reflows after a terminal resize', function (): void {
    $driver = new HeadlessDriver(80, 25);
    $app = new HelloApp(new Screen($driver));
    $app->bootForTest();

    $driver->resizeTo(100, 30);
    $app->pumpResizeForTest();
    $app->drawAndFlushForTest();

    expect($app->backRowsForTest())
        ->toHaveCount(30)
        ->and(mb_strlen($app->backRowsForTest()[0]))->toBe(100);
});
```

Use `backRowsForTest()` for visible output and `desktopForTest()` when a test needs to inspect the constructed desktop or window state. For protocol-level input tests, `HeadlessDriver::feedInput()` accepts the terminal bytes that the real driver would receive.

## Choose stable assertions

Assert meaningful text, command effects, geometry, or selected view state for normal feature tests. Complete-frame snapshots are appropriate when clipping, palette selection, or layout is the behavior under test, but they make ordinary copy changes more expensive.

The repository also includes `php bin/tv-render` and `php bin/tv-shot` for deterministic HTML and PNG captures from loadable application scripts. See [render and capture applications](/cookbook/render-and-capture) for those commands.

You have now built, connected, and tested one TurboVision application. Use the [cookbook](/cookbook/) for focused recipes and the [reference](/reference/) for API details.
