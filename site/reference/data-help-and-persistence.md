# Data, help, and persistence

## Validators

Validators provide `InputLine` with editable-input checks, final validation, optional formatting, and typed data transfer.

### Base contract

```php
class Validator
{
    public const int StatusOk = 0;
    public const int StatusSyntax = 1;
    public const int OptionFill = 0x0001;
    public const int OptionTransfer = 0x0002;

    public int $status = self::StatusOk;
    public int $options = 0;
    public private(set) ?string $lastError = null;

    public function isValidInput(string &$input, bool $suppressFill = false): bool;
    public function isValid(string $input): bool;
    public function validate(string $input): bool;
    public function error(): void;
    public function transfer(string &$input, mixed &$value, ValidatorTransfer $operation): int;
}
```

The base `Validator` accepts every input and completed value. `validate()` clears `lastError`, calls `isValid()`, and calls `error()` when validation fails. `error()` has no UI side effect by default. Subclasses use the protected `setError(string $message)` method to set `lastError`.

`isValidInput()` receives the editable text by reference. A validator may normalize it in place. `isValid()` is the completed-value check. `transfer()` returns zero when `InputLine` should use ordinary text transfer.

`ValidatorTransfer` selects a transfer operation:

| Case | Meaning |
| --- | --- |
| `DataSize` | Return the native value's logical size, or `0` for text transfer |
| `SetData` | Format `$value` into `$input` |
| `GetData` | Parse `$input` into `$value` |

### `FilterValidator`

```php
new FilterValidator(string $validChars)
```

`FilterValidator` accepts an input only when every grapheme occurs in `validChars`. Its public `validChars` property has a private setter. Invalid input records `Invalid character in input.` through `validate()`.

### `RangeValidator`

```php
new RangeValidator(int $min, int $max)
```

`RangeValidator` accepts signed PHP integers in the inclusive range. It rejects a minimum greater than the maximum with `InvalidArgumentException`. It sets `OptionTransfer` and permits only `+`, digits, and, when the minimum is negative, `-` during editing.

| Method | Contract |
| --- | --- |
| `isValidInput(string &$input, bool $suppressFill = false): bool` | Accepts integer prefixes, including an empty value and a lone `+`; a lone `-` is accepted only when `min < 0`. It does not apply the range until final validation. |
| `isValid(string $input): bool` | Requires complete integer syntax, rejects values outside PHP's native integer range, then applies the inclusive bounds. Leading zeroes and a leading plus sign are accepted. |
| `transfer(string &$input, mixed &$value, ValidatorTransfer $operation): int` | Returns `PHP_INT_SIZE` for `DataSize`. `SetData` requires an `int`, writes its decimal form, and returns `PHP_INT_SIZE`; otherwise it throws `InvalidArgumentException`. `GetData` writes an `int` only when the text is valid, then returns `PHP_INT_SIZE`; it returns `0` for invalid text. |

### `StringLookupValidator`

```php
new StringLookupValidator(iterable $strings = [])
```

`newStringList(iterable $strings): void` converts supplied values to strings, sorts them with `SORT_STRING`, and removes duplicates. `strings(): array` returns the sorted list. `lookup()` and `isValid()` use exact, case-sensitive matching. A failed `validate()` records `Input is not in the list of valid strings.`

`LookupValidator` is the abstract base for set-backed validators. Implement `lookup(string $input): bool`; its inherited `isValid()` delegates to that method.

### `PictureValidator`

```php
new PictureValidator(string $pictureMask, bool $autoFill = false)

public function picture(string &$input, bool $autoFill = false): PicResult
public function isValidInput(string &$input, bool $suppressFill = false): bool
public function isValid(string $input): bool
```

`PictureValidator` parses the mask during construction. Invalid syntax sets `status` to `Validator::StatusSyntax`. Passing `true` for `$autoFill` sets `OptionFill`.

| Mask form | Meaning |
| --- | --- |
| `#` | One Unicode number character |
| `?` | One Unicode letter |
| `&` | One Unicode letter, normalized to uppercase |
| `!` | One non-empty character, normalized to uppercase |
| `@` | One non-empty character |
| Literal | Matches case-insensitively and is emitted in the mask's spelling |
| `;x` | Quote `x` as a literal |
| `[ ... ]` | Optional group |
| `{ ... }` | Required group |
| `a,b` inside a group | Alternatives |
| `*atom` | Repeat an atom without a fixed count |
| `*Natom` | Repeat an atom exactly `N` times; `*0` is unbounded |

`picture()` returns `Complete`, `Incomplete`, `Empty`, `Error`, or `Syntax`. On complete and incomplete matches it writes normalized text back through `$input`; with auto-fill it appends only deterministic literal separators. `isValidInput()` accepts complete, incomplete, and empty states, rejecting only `Error` and `Syntax`. `isValid()` accepts only `Complete`.

Matching uses a bounded work budget. An ambiguous mask that exceeds the budget returns `PicResult::Error` rather than continuing unbounded backtracking. `PicResult::Ambiguous` and `PicResult::IncompleteWithoutFill` are retained enum cases but are not produced by the current matcher.

## Compiled help

### Help data

| Type | Constructor and API |
| --- | --- |
| `HelpParagraph` | `new HelpParagraph(string $text, bool $wrap = true)`; `toArray(): array` |
| `CrossRef` | `new CrossRef(int $ref, int $offset, int $length, ?string $label = null)`; `toArray(): array` |
| `HelpTopic` | `new HelpTopic(array $paragraphs = [], array $crossRefs = [])` |

`CrossRef` offsets and lengths are Unicode grapheme offsets across the topic paragraphs. Its offset must be non-negative and its length must be positive; otherwise construction throws `InvalidArgumentException`.

`HelpTopic` exposes `addParagraph()`, `addCrossRef()`, `paragraphs()`, `crossRefs()`, `setWidth()`, `getWidth()`, `getNumCrossRefs()`, `getCrossRef()`, `lines()`, `numLines()`, `getLine()`, `crossRefLocation()`, `toArray()`, and `fromArray()`.

`setWidth(int $width)` clamps the stored width to at least one. Passing no width to `lines()`, `numLines()`, `getLine()`, or `crossRefLocation()` uses the stored width. With no effective width, `lines()` returns an empty list. `getLine()` returns an empty string for an unavailable line; `getCrossRef()` throws `OutOfBoundsException` for an unavailable reference. `fromArray()` rejects malformed paragraph and reference data with `UnexpectedValueException`.

### `HelpFile`

```php
new HelpFile(array $topics = [])

public static function load(string $path): HelpFile
public function save(string $path): void
public function getTopic(int $context): HelpTopic
public function hasTopic(int $context): bool
public function putTopic(int $context, HelpTopic $topic): void
public function topics(): array
public function fallbackTopic(int $context): HelpTopic
```

`HelpFile::MAGIC` is the exact prefix `TVPHPHELP 1\n`. A saved file contains that prefix and UTF-8 JSON with `version: 1` and a context-keyed `topics` object. Saving orders contexts numerically and writes through the atomic file writer.

`load()` rejects files without the prefix, malformed JSON, unsupported schemas, and legacy Borland H32 files with `UnexpectedValueException`. It throws `RuntimeException` when the file cannot be read. `putTopic()` rejects negative contexts with `InvalidArgumentException`. `getTopic()` returns `fallbackTopic()` when the requested context has no entry; it does not throw for a missing topic.

### `HelpCompiler`

```php
public function compile(string $source, string $output, ?string $symbolsOutput = null): array
public function parse(string $source): array
```

`parse()` returns:

```php
['file' => HelpFile, 'symbols' => array<string, int>]
```

`compile()` parses the source, saves the help file to `$output`, optionally writes a PHP symbol file, and returns the symbol map. The generated symbol file declares `hc<Name>` constants.

The source format supports these forms:

```text
.topic Name
.topic Name=42
.topic Name, Alias
{Target}
{Visible label:Target}
```

Automatic contexts begin at `2`. `.topic` names must match `[A-Za-z_][A-Za-z0-9_]*`; a topic may have comma-separated aliases, and explicit contexts must be unique. Blank lines separate paragraphs. Lines beginning with a space form a preformatted paragraph; other paragraphs are wrapped. Links may point forward to topics declared later. Unknown links, duplicate names, duplicate contexts, and malformed declarations throw `UnexpectedValueException`. Source text that is not valid UTF-8 is converted from Windows-1252 before parsing.

Use two consecutive left braces in source text to emit a literal `{` rather than begin a link.

### Help views

```php
new HelpViewer(Rect $bounds, ?ScrollBar $hScrollBar, ?ScrollBar $vScrollBar, HelpFile $helpFile, int $context)
new HelpWindow(HelpFile $helpFile, int $context)
```

`HelpViewer` is a selectable `Scroller`. It renders the requested topic, exposes the current `HelpTopic` in `topic`, and exposes the zero-based selected reference in `selected` (`null` when no reference is selected). `switchToTopic(int $context)` replaces the topic, resets scrolling, and selects the first reference when available. Tab and Shift-Tab select links, Enter follows the selected link, Escape ends the containing modal with `Cmd::Close`, and a double-click follows a clicked link.

`HelpWindow` creates a centered 50×18 `Window` titled `Help`, with horizontal and vertical scroll bars and a public readonly `viewer`.

<DocCapture
  src="/captures/reference/special-help-window.png"
  alt="A Help window with a highlighted Getting started cross-reference and scroll bars"
  caption="The selected cross-reference is highlighted; Tab moves the selection and Enter follows it."
/>

## Stream persistence

### `Streamable` and registration

```php
interface Streamable
{
    public static function streamType(): string;
    public function streamData(): array;
    public static function fromStreamData(array $data): static;
}
```

`streamType()` is a stable, application-defined identifier. `streamData()` must return an associative map containing only JSON scalars, arrays, and other `Streamable` objects. `fromStreamData()` receives untrusted decoded data and must validate its own schema.

`StreamableType` implements `streamType()` from a class `STREAM_TYPE` constant. `StreamableClass::forClass(string $type, string $class): StreamableClass` creates a factory for a `Streamable` class and rejects a non-streamable class or a type that differs from the class's `streamType()`.

```php
$registry = new StreamableRegistry;
$registry->registerClass(MyResource::STREAM_TYPE, MyResource::class);
$codec = new StreamCodec($registry);
```

| `StreamableRegistry` method | Contract |
| --- | --- |
| `register(StreamableClass $class): self` | Adds one explicit type factory; duplicate types throw `InvalidArgumentException` |
| `registerClass(string $type, string $class): self` | Registers a matching `Streamable` class |
| `has(string $type): bool` | Tests whether a type is registered |
| `types(): array` | Returns registered type names in registration order |
| `create(string $type, array $data): Streamable` | Invokes the registered factory; unknown, throwing, or incompatible factories cause `PersistenceException` |

### `StreamCodec`

```php
new StreamCodec(
    StreamableRegistry $registry,
    int $maxDepth = 64,
    int $maxNodes = 10_000,
    int $maxBytes = 8_000_000,
)

public function encode(Streamable $object): string
public function decode(string $json): Streamable
public function encodeDocument(Streamable $object): array
public function decodeDocument(array $document): Streamable
```

The codec writes and reads JSON object graphs through the registry. It preserves shared object identity with object IDs and reference envelopes. It never calls PHP `unserialize()` and never resolves a type name into a class without registry registration.

Constructor limits must be positive; `maxDepth` must not exceed 500. The defaults are a 64-level graph depth, 10,000 nodes, and 8,000,000 bytes. Encoding and decoding enforce these limits. An empty or malformed JSON string, an invalid envelope, an unregistered type, a duplicate or unknown object ID, or a schema failure raises `PersistenceException`.

The codec accepts null, finite scalar values, arrays, and registered `Streamable` objects. It rejects resources, arbitrary objects, non-finite floats, cyclic graphs, `$tvision` in a data array, non-empty list-shaped object data, and non-list maps with integer keys. Cycles are not supported because registered factories construct objects from already decoded child data.

## Resource files

### `ResourceFile`

```php
public static function open(string $path, StreamCodec $codec): ResourceFile
public function path(): string
public function count(): int
public function has(string $key): bool
public function keys(): array
public function put(string $key, Streamable $resource): void
public function get(string $key): ?Streamable
public function require(string $key): Streamable
public function remove(string $key): bool
public function flush(): void
```

`ResourceFile` stores named streamable documents in a JSON file with format `turbovision-resource-file`, version `1`, and a top-level `resources` object. `open()` returns an empty in-memory store when the file does not exist. It rejects an empty path with `InvalidArgumentException`.

Keys must be non-empty UTF-8 strings of at most 255 bytes, contain no NUL byte, and not consist solely of decimal digits. `keys()` returns sorted keys. `get()` returns `null` for a missing key and wraps decode failures in `ResourceException`; `require()` throws `ResourceException` for a missing key. `remove()` returns whether an entry was removed.

`flush()` creates a missing parent directory with mode `0775`, acquires a sibling `.lock` file, reloads the latest disk state, merges independent key changes, and atomically replaces the target. Concurrent updates to different keys merge. Conflicting changes to the same key throw `ResourceException` and leave the caller to reload and resolve the conflict. File reads and writes reject unsupported schemas, malformed JSON, unreadable files, and documents over 8,000,000 bytes.

### Built-in string resources

`StringList` is an immutable `Streamable` list with type `tvision.string-list`.

```php
new StringList(iterable $strings = [])

public function count(): int
public function get(int $index): string
public function all(): array
public function contains(string $needle): bool
```

It also supports read-only `ArrayAccess` and iteration. Construction rejects non-string items with `InvalidArgumentException`; `get()` throws `OutOfBoundsException` for an unavailable index; writing or unsetting through `ArrayAccess` throws `LogicException`. Its stream data is exactly `['strings' => list<string>]`.

`StringListMaker` is the mutable builder:

```php
(new StringListMaker)
    ->add('one')
    ->addMany(['two', 'three'])
    ->build();
```

`add()`, `addMany()`, and `clear()` return the maker. `build()` returns an independent `StringList` snapshot.

## Declarative view resources

`ViewResource` persists a declarative tree, not a live view instance. It has stream type `tvision.view-resource`.

```php
new ViewResource(ViewResourceNode $root)

public function streamData(): array
public static function fromStreamData(array $data): static
public function build(ViewResourceRegistry $registry): View
```

The stream data is exactly `['root' => <node>]`. `fromStreamData()` rejects any other shape with `PersistenceException`. `build()` constructs a new tree through the supplied registry.

### Nodes and factories

```php
new ViewResourceNode(
    string $type,
    Rect $bounds,
    array $properties = [],
    array $children = [],
)

public function property(string $name, mixed $default = null): mixed
public function string(string $name): string
public function integer(string $name): int
public function toArray(): array
public static function fromArray(array $data, int $depth = 0): ViewResourceNode
```

Node types are application-owned identifiers, not PHP class names. A type must be non-empty after trimming and no more than 128 bytes. Root property names must be strings; children must be `ViewResourceNode` instances. Property values accept null, finite scalars, and nested arrays up to 32 levels. A node loaded from an array permits at most 64 levels of node nesting. Constructor violations throw `InvalidArgumentException`; malformed loaded data throws `PersistenceException`.

`string()` and `integer()` retrieve a required typed property and throw `ResourceException` when it is absent or has the wrong type. `property()` returns its default for an absent property.

```php
use HelgeSverre\TurboVision\Resources\ViewResourceNode;
use HelgeSverre\TurboVision\Resources\ViewResourceRegistry;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\View;

$views = new ViewResourceRegistry;
$views->register('demo.text', static fn (ViewResourceNode $node): View =>
    new StaticText($node->bounds, $node->string('text')),
);
```

| `ViewResourceRegistry` method | Contract |
| --- | --- |
| `register(string $type, Closure $factory): self` | Adds a factory for a non-empty type; duplicate types throw `InvalidArgumentException` |
| `types(): array` | Returns registered type names |
| `build(ViewResourceNode $node): View` | Calls the matching factory, then recursively inserts children into a returned `Group` |

An unregistered type, a throwing factory, a factory that does not return `View`, or children on a non-`Group` result raises `ResourceException`. Register only factories whose accepted properties are intended to be persisted.

<DocCapture
  src="/captures/reference/special-view-resource.png"
  alt="A saved dialog with text, a populated input field, and an OK button"
  caption="A declarative resource tree builds ordinary runtime controls with fresh ownership and frame state."
/>
