# Render and capture applications

Use the headless driver when you need a repeatable frame without opening an interactive terminal. This works well for CI artifacts, bug reports, visual regression tests, and checking a layout at a particular terminal size.

## Render one frame as HTML

`tv-render` loads an application, boots it with a `HeadlessDriver`, draws one frame, and writes the screen buffer as a self-contained HTML document.

```bash
php bin/tv-render examples/php/tutorial/Guide05.php frame.html
```

Pass the target size explicitly when the layout depends on available cells:

```bash
php bin/tv-render examples/php/tutorial/Guide05.php frame-120x40.html 120 40
```

The output preserves the framework's text, box-drawing characters, and CGA colour attributes. Open `frame.html` in a browser or attach it to a CI job.

The loaded script must define an `Application` subclass without starting it when included. Put the normal entry point behind `Application::runningAsMain()`:

```php
use HelgeSverre\TurboVision\Application\Application;

final class InventoryApp extends Application
{
    // ...
}

if (Application::runningAsMain(__FILE__)) {
    exit((new InventoryApp())->run());
}
```

`tv-render` finds the application class declared by that script and constructs it with an injected `Screen`. A script that calls `run()` unconditionally will take over the process instead of producing an artifact.

## Capture a PNG

`tv-shot` renders the same HTML frame and uses headless Chrome or Chromium to write a PNG:

```bash
php bin/tv-shot examples/php/tutorial/Guide05.php frame.png
php bin/tv-shot examples/php/tutorial/Guide05.php frame-120x40.png 120 40
```

<DocCapture
  src="/captures/howto-tools/render-capture.png"
  alt="A headless terminal frame with an Inventory report window showing three stock rows"
  caption="A fixed-size frame is a useful artifact for visual review: text, window chrome, and colour attributes are all part of the result."
  :width="1928"
  :height="896"
/>

On macOS the command first looks for Google Chrome in `/Applications`. On other systems it looks for `chromium`, `chromium-browser`, or `google-chrome` on `PATH`. If none is available it exits with status `3`; install one of those browsers before using PNG capture.

Use HTML when you need a portable, inspectable artifact. Use PNG when an issue tracker, visual diff service, or release notes need a fixed image.

## Render a buffer yourself

`HtmlRenderer` accepts a `Drawing\Buffer`, so it also works for a single component or a deliberately constructed scene:

```php
use HelgeSverre\TurboVision\Drawing\Buffer;
use HelgeSverre\TurboVision\Rendering\HtmlRenderer;

$buffer = new Buffer(80, 25);
// Draw cells into $buffer.
file_put_contents('frame.html', (new HtmlRenderer())->render($buffer));
```

See [`examples/php/html-render.php`](https://github.com/HelgeSverre/tvision-php/blob/main/examples/php/html-render.php) for a complete colour and box-drawing example.

## Test a frame without a browser

Inject a `Screen` backed by `HeadlessDriver`, boot the application, draw once, then assert on its back buffer:

```php
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;

$driver = new HeadlessDriver(80, 25);
$app = new InventoryApp(new Screen($driver));

$app->bootForTest();
$app->drawAndFlushForTest();

$frame = implode("\n", $app->backRowsForTest());
expect($frame)->toContain('Inventory');
```

`backRowsForTest()` returns one string per screen row. For a small, exact rendering contract, inspect a cell through `$app->screen()?->back()->at($x, $y)` and assert its character and attribute.

## Exercise a resize

Resize the driver, pump the same resize path used by the event loop, then redraw:

```php
$driver->resizeTo(120, 40);
$app->pumpResizeForTest();
$app->drawAndFlushForTest();

expect($app->screen()?->cols())->toBe(120);
expect($app->screen()?->rows())->toBe(40);
```

This catches layout code that only works at the initial terminal size. Do not replace `pumpResizeForTest()` with a direct call to a view's `changeBounds()`; the application also recomputes its menu, desktop, and status-line bands during a real resize.

## Choose assertions that survive useful change

- Assert visible strings for ordinary feature behaviour.
- Assert view state or command handling for interactions.
- Assert selected rows or cells for clipping, alignment, and colours.
- Keep full HTML or PNG snapshots for presentation contracts that genuinely cover the whole frame.

Avoid snapshots of a moving cursor, timestamps, random identifiers, or terminal-dependent content. Make those values deterministic before producing an artifact.

## Common failures

| Symptom | Cause and fix |
| --- | --- |
| `No Application subclass defined` | The supplied script did not declare an `Application` subclass when required. Pass the application entry script, not a helper file. |
| The script opens an interactive terminal | Guard its entry point with `Application::runningAsMain(__FILE__)`. |
| `tv-shot: no Chrome/Chromium found` | Install Chrome or Chromium where the command can find it. HTML rendering remains available without a browser. |
| The frame is blank or clipped | Check the requested columns and rows, then call `bootForTest()` before `drawAndFlushForTest()` in custom code. |
| A snapshot changes between runs | Remove nondeterministic text and test a specific geometry rather than the host terminal size. |
