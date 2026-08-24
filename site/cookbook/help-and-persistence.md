# Add compiled help and safe persistence

## Compile context help

Write help in a UTF-8 text file, compile it as part of your build, and ship the generated `.tvhelp` file with the application.

```text
.topic Overview=1000
Welcome to the inventory application. Open {Products} to browse stock.

.topic Products=1010
Use the arrow keys to select a product. Press Enter to inspect it.
```

Compile it with `tvhc`:

```bash
php bin/tvhc resources/help.txt resources/help.tvhelp resources/help-contexts.php
```

The optional third argument writes PHP constants named `hcName` for the topic names. Topic headers use `.topic Name=42`; omit `=42` to assign the next available context automatically. Comma-separated names may share one topic. Normal paragraphs wrap, while lines beginning with a space are preserved as preformatted text. Links use `{label:Target}`; `{Target}` uses the topic name as both label and target.

The compiler validates duplicate names and contexts, unknown link targets, and the resulting UTF-8 topic data. It emits the `TVPHPHELP 1` format, not a legacy Turbo Vision H32 stream.

## Open help from F1

Keep a `HelpFile` on the application and return a `HelpWindow` from `createHelpView()`. `Program` handles `Cmd::Help` by asking the active view for its help context and executing the returned view modally.

```php
use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Help\HelpFile;
use HelgeSverre\TurboVision\Help\HelpWindow;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\View;

final class InventoryApp extends Application
{
    private HelpFile $help;

    public function __construct(?Screen $screen = null)
    {
        $this->help = HelpFile::load(__DIR__ . '/resources/help.tvhelp');
        parent::__construct($screen);
    }

    protected function createHelpView(int $context): ?View
    {
        return new HelpWindow($this->help, $context);
    }
}
```

Give every help-enabled surface a stable numeric context:

```php
$productList->helpCtx = 1010;
$productWindow->helpCtx = 1010;
```

`HelpFile::getTopic()` provides a readable fallback when a context is absent. If you want one known topic rather than that fallback, check `hasTopic($context)` and choose your overview context before creating the window:

```php
protected function createHelpView(int $context): ?View
{
    $topic = $this->help->hasTopic($context) ? $context : 1000;

    return new HelpWindow($this->help, $topic);
}
```

Menu items and submenus also accept `helpCtx`, so F1 can describe the highlighted item as well as the active view.

<DocCapture
  src="/captures/howto-tools/help-window.png"
  alt="A centered Help window above a Products window, showing the Products help topic"
  caption="The Help window keeps the current application visible while presenting the topic for its active context."
/>

## Keep help loading recoverable

`HelpFile::load()` rejects unreadable files, invalid JSON, unsupported schema versions, and old H32 files. Catch those errors at the application boundary and either use a small in-memory fallback or disable the help command; do not prevent the terminal UI from starting because a documentation asset is missing.

```php
try {
    $this->help = HelpFile::load($path);
} catch (\RuntimeException | \UnexpectedValueException) {
    $this->help = new HelpFile();
}
```

## Persist one application value

Persist values that implement `Streamable`. Give each one a stable application-owned type string, return a scalar/array/streamable data map, and validate every field when restoring it.

```php
use HelgeSverre\TurboVision\Persistence\PersistenceException;
use HelgeSverre\TurboVision\Persistence\Streamable;
use HelgeSverre\TurboVision\Persistence\StreamableType;

final readonly class Preferences implements Streamable
{
    use StreamableType;

    public const string STREAM_TYPE = 'inventory.preferences';

    public function __construct(
        public string $sort,
        public bool $showDiscontinued,
    ) {}

    public function streamData(): array
    {
        return [
            'sort' => $this->sort,
            'showDiscontinued' => $this->showDiscontinued,
        ];
    }

    public static function fromStreamData(array $data): static
    {
        if (count($data) !== 2
            || ! is_string($data['sort'] ?? null)
            || ! is_bool($data['showDiscontinued'] ?? null)
        ) {
            throw new PersistenceException('Invalid inventory preferences.');
        }

        return new self($data['sort'], $data['showDiscontinued']);
    }
}
```

Register only the types the application is prepared to restore, then encode and decode them:

```php
use HelgeSverre\TurboVision\Persistence\StreamCodec;
use HelgeSverre\TurboVision\Persistence\StreamableRegistry;
use HelgeSverre\TurboVision\Support\AtomicFileWriter;

$registry = (new StreamableRegistry)
    ->registerClass(Preferences::STREAM_TYPE, Preferences::class);
$codec = new StreamCodec($registry);

$json = $codec->encode(new Preferences('sku', false));
AtomicFileWriter::write(__DIR__ . '/var/preferences.json', $json . "\n");

$saved = file_get_contents(__DIR__ . '/var/preferences.json');
if ($saved === false) {
    throw new \RuntimeException('Could not read saved preferences.');
}
$preferences = $codec->decode($saved);
if (! $preferences instanceof Preferences) {
    throw new \LogicException('Preferences decoded to the wrong type.');
}
```

`AtomicFileWriter` writes a temporary sibling, flushes it, and atomically replaces the target. The destination directory must already exist. A failed write leaves the existing file in place.

## Preserve shared references, not cycles

`StreamCodec` can encode repeated references in an object graph and restores them as the same object. Cycles are rejected because constructor-based factories cannot safely materialize them. Persist a separate identifier instead of a back-reference when your domain contains a cycle.

The codec also rejects unregistered types, malformed JSON, excessive nesting, excessive node counts, files over its byte limit, non-finite floats, arbitrary objects, reserved `$tvision` keys, and maps with integer keys that JSON could not restore losslessly.

Never use PHP `unserialize()` for application state and never use PHP class names as stream types. The explicit registry is the boundary that decides what input is allowed to construct.

## Store named resources

Use `ResourceFile` when several registered values need named entries in one file. `flush()` creates the parent directory if needed, locks a sibling `.lock` file, merges independent key changes, and writes atomically.

```php
use HelgeSverre\TurboVision\Persistence\StreamCodec;
use HelgeSverre\TurboVision\Persistence\StreamableRegistry;
use HelgeSverre\TurboVision\Resources\ResourceFile;
use HelgeSverre\TurboVision\Resources\StringList;

$registry = (new StreamableRegistry)
    ->registerClass(StringList::STREAM_TYPE, StringList::class);
$file = ResourceFile::open('var/workspace.json', new StreamCodec($registry));

$file->put('recent-files', new StringList(['src/App.php', 'README.md']));
$file->flush();

$recent = $file->require('recent-files');
if (! $recent instanceof StringList) {
    throw new \LogicException('Unexpected resource type.');
}
```

Use non-empty, non-numeric-only resource names. When two open instances change different names, both changes merge. When they change the same name differently, `flush()` throws `ResourceException`; reopen the file, resolve the conflict in application terms, and retry.

## Rebuild declarative view trees

`ViewResource` and `ViewResourceRegistry` are for view definitions, not live view instances. Register each application-owned node type with a factory that validates the node properties and returns the matching view. Only `Group` subclasses may own child nodes.

```php
use HelgeSverre\TurboVision\Resources\ViewResourceNode;
use HelgeSverre\TurboVision\Resources\ViewResourceRegistry;
use HelgeSverre\TurboVision\Views\StaticText;

$views = new ViewResourceRegistry;
$views->register('inventory.label', static function (ViewResourceNode $node): StaticText {
    return new StaticText($node->bounds, $node->string('text'));
});
```

Do not register a general-purpose factory or accept a class name from a resource file. Register the small set of view shapes your application actually supports.

## Verify saved data

Test both the successful round trip and hostile input:

```php
$decoded = $codec->decode($codec->encode(new Preferences('name', true)));
expect($decoded)->toBeInstanceOf(Preferences::class);

expect(fn () => $codec->decode('{not json}'))
    ->toThrow(PersistenceException::class);
```

Also test a missing help file, a missing resource key, an unregistered persisted type, and a simultaneous update to the same resource name. Those cases ensure the UI can show a useful error without accepting unsafe data or overwriting a user's previous save.
