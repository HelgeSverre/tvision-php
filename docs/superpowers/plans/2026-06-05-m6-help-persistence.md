# M6 Help & Persistence — Spike Plan (outline)

> Spike-level plan: scope, classes, task outline, acceptance, risks. NOT full TDD code.
> Promote to a detailed plan right before building, once M1-M5 exist.

**Milestone goal:** Deliver two separable but related tracks: (A) a fully functional
online help system — context-sensitive, scrollable, cross-referenced, wired to views and
menus via `hcXxx` help-context integers — plus a PHP-native help-source compiler
(`tvhc` replacement); and (B) a resolved persistence story: either a faithful binary
object streamer (`Streamable` / `ResourceFile` / `StringList`) or an idiomatic PHP
serialization layer, plus whichever of `ResourceFile` and `StringList` remain useful on
top of that foundation. The two tracks can be developed and shipped independently;
persistence is the higher-risk fork.

**Depends on:** M1 (`View`, `Group`, `Program`, `Event`, `Cmd`, `DrawBuffer`); M2
(`Window`, `Scroller`, `ScrollBar`); M3 (`Dialog`, `Button`, status-line help-context
wiring); optionally M4 for `StringList` as an i18n primitive in file dialogs.

**Acceptance examples:**
- `demo/help.php` opens the demo app (PHP port of tvdemo), presses F1, and a
  `HelpWindow` appears with the correct topic for the active context; Tab cycles
  `CrossRef` hotspots; Enter / click navigates to the referenced topic; Esc closes.
- `bin/tvhc` (PHP CLI) compiles `DEMOHELP.TXT` (or a `.tvhelp` PHP-native source) into
  a loadable help data structure; the viewer renders it identically.
- `examples/php/persistence/load.php` — PHP port of `load.cc` — builds a `LoadWindow`
  with progress bars, or (under the idiomatic-PHP option) demonstrates
  `ResourceFile::put()` / `ResourceFile::get()` round-tripping a view by string key.
- All acceptance tests run headless (via `HeadlessDriver`) and on a real terminal.

---

## Decision required: persistence approach

The original TV persistence layer (`TStreamable` / `opstream` / `ipstream` / `fpstream`)
is a 1994 pre-STL binary serializer with a global name registry, pointer-dedup tables,
and a rigid class-name tag format. Porting it faithfully to PHP is possible but costly
and produces an API that feels alien next to PHP's own serialization primitives.

### Option 1 — Faithful binary object streamer
Port `Streamable` (interface), `StreamableClass` (registration record + builder
callable), `StreamableTypes` (global sorted registry), `InStream` / `OutStream` /
`IoStream` / `FileInStream` / `FileOutStream` / `FileStream` as a class hierarchy.
Each view class implements `streamableName(): string`, `write(OutStream)`, `read(InStream)`.
`ResourceFile` wraps a `FileStream`; `StringList` / `StringListMaker` sit on top.

Pros: binary `.h32` help files and Borland-era `.dsk` desktop saves can be read/written
interchangeably with the C++ originals; the `HelpFile` parser is a direct port; full
semantic parity.

Cons: ~15 extra classes, a global mutable registry, pointer-dedup logic that maps
poorly to PHP's object graph (no raw pointers), zero ecosystem benefit (no tool reads
this format outside TV), fragile binary layout across platforms.

### Option 2 — Idiomatic PHP serialization (RECOMMENDED)
Define `Streamable` as a PHP interface with `__serialize(): array` and
`__unserialize(array): void` (standard PHP 8+ native serialization), plus a
`StreamableRegistry` that maps class names to factory callables (replacing the global
`TStreamableTypes` registry). Build `ResourceFile` as a string-keyed store backed by a
plain PHP array serialized to JSON/NDJSON or PHP's native `serialize()`. Build
`StringList` as a simple `int → string` map loadable from a PHP array literal or JSON
file. The `HelpFile` reader remains a custom binary parser (the `.h32` / `.tvhelp` format
is specific to the help system and not a general persistence problem).

Pros: zero new stream-class hierarchy; `__serialize` is understood by every PHP
developer; `ResourceFile` becomes a thin wrapper around `file_get_contents` +
`unserialize` or JSON; `StringList` is trivially testable; no global mutable state.

Cons: binary-incompatible with the original C++ `.dsk` files (irrelevant for a PHP
app); `StringList` loses the "strings stored externally on a stream" lazy-loading trick
(not meaningful in PHP's memory model); view-tree round-trip requires every `View`
subclass to implement `__serialize` — a non-trivial retrofit if not planned from M1.

**Recommendation: Option 2.** The binary streamer's complexity buys nothing in a PHP
context. Replace it with `__serialize` / `__unserialize` on views (ideally retrofitted
as a `Streamable` interface on `View` from M1 or early M2), a `StreamableRegistry` for
factory-driven reconstruction, and a `ResourceFile` backed by PHP serialization. The
`HelpFile` binary format is handled separately by the help track's own reader/compiler.

**Cross-milestone note:** `View` should implement `Streamable` from M1 or at the latest
M2. Deferring this to M6 forces a wide retrofit across the entire view tree. Flag this
as a small M1/M2 design debt to address before M6 begins.

---

## Classes to build (new this milestone)

### Track A — Help system (`Help\` namespace)

| PHP class | Original TV class | Responsibility | Key methods / notes |
|---|---|---|---|
| `Help\HelpContext` | `hcXxx` constants | Typed int-backed enum of built-in contexts; apps extend with their own int constants | `NoContext = 0`; user-defined contexts are plain `int`s |
| `Help\Paragraph` | `TParagraph` | One wrappable (or pre-formatted) block of help text | `string $text`, `bool $wrap`; linked into a `HelpTopic` |
| `Help\CrossRef` | `TCrossRef` | Hyperlink hotspot inside help text | `int $ref` (target context), `int $offset`, `int $length`; position within rendered line |
| `Help\HelpTopic` | `THelpTopic` | One help topic: paragraphs + cross-refs; knows how to reflow to a given width | `paragraphs()`, `crossRefs()`, `getLine(int, int): string`, `numLines(int): int`, `setCrossRef()` |
| `Help\HelpIndex` | `THelpIndex` | Context-id → byte-offset map for a help file | `position(int): int`, `add(int, int): void` |
| `Help\HelpFile` | `THelpFile` | Reads/writes the compiled help data; topic lookup by context id | `getTopic(int): HelpTopic`, `putTopic(HelpTopic): void`, `invalidTopic(): HelpTopic` |
| `Help\HelpViewer` | `THelpViewer` | Scrollable `Scroller` subview rendering a `HelpTopic`; Tab/Enter navigate cross-refs | `switchToTopic(int): void`, `makeSelectVisible(...)`, `handleEvent(Event): void` |
| `Help\HelpWindow` | `THelpWindow` | `Window` wrapping a `HelpViewer` + scroll bars; opened by F1 / `cmHelp` | Constructor takes `HelpFile` + initial context; own palette |
| `Help\CrossRefHandler` | `TCrossRefHandler` | Callable type for resolving a cross-ref id during help compilation | Replaces C function pointer; implemented as `Closure` or interface |

**Help compiler (CLI tool):**

| PHP class / script | Original | Responsibility | Notes |
|---|---|---|---|
| `Help\Compiler\HelpSource` | `tvhc.cc` parser | Parses `.tvhelp` (PHP-native text format, compatible superset of `.TXT`) into a topic graph | `.topic Symbol=N` lines, `{text:alias}` cross-refs, wrap vs pre-formatted paragraphs |
| `Help\Compiler\HelpCompiler` | `tvhc.cc` compiler | Walks the parsed topic graph, resolves forward refs, writes a `HelpFile` | Emits warnings for undefined cross-ref targets |
| `bin/tvhc` | `tvhc` binary | CLI entry point: `tvhc source.tvhelp output.tvhelp.php` | Outputs a PHP-native help data file (or binary `.h32` if compat needed) |

### Track B — Persistence (`Persistence\` namespace)

| PHP class | Original TV class | Responsibility | Key methods / notes |
|---|---|---|---|
| `Persistence\Streamable` | `TStreamable` | Interface: `__serialize(): array`, `__unserialize(array): void`; marks a class as persistable | Implemented by `View` (retrofit) and non-view streamable classes |
| `Persistence\StreamableRegistry` | `TStreamableTypes` + `TStreamableClass` | Maps class-name strings to factory `callable`: `register(string, callable): void`, `build(string): Streamable` | Replaces global C++ static registry; can be a singleton or injected |
| `Persistence\OutStream` | `opstream` / `ofpstream` | Write a `Streamable` object graph to a file/string | `write(Streamable): void`; uses `__serialize` + registry for type tags |
| `Persistence\InStream` | `ipstream` / `ifpstream` | Read a `Streamable` object graph from a file/string | `read(): Streamable`; uses registry to reconstruct typed objects |
| `Persistence\ResourceFile` | `TResourceFile` | String-keyed store of `Streamable` objects backed by a file | `put(Streamable, string): void`, `get(string): ?Streamable`, `flush(): void`, `count(): int`, `keyAt(int): string` |
| `Persistence\ResourceCollection` | `TResourceCollection` | Internal sorted index of `ResourceItem` entries | Internal to `ResourceFile`; not public API |
| `Persistence\StringList` | `TStringList` | Read-only `int → string` lookup; i18n primitive | `get(int): string`; backed by a PHP array loaded from JSON / array literal |
| `Persistence\StringListMaker` | `TStrListMaker` | Builder that accumulates `int → string` pairs and writes a `StringList` file | `put(int, string): void`, `flush(string $path): void` |

---

## Builds on (existing)

- `Views\View`, `Views\Group` — `HelpViewer` extends `Scroller`; `HelpWindow` extends `Window`
- `Views\Scroller`, `Views\ScrollBar` — needed by `HelpViewer` (M2)
- `Views\Window` — needed by `HelpWindow` (M2)
- `Events\Event`, `Events\Cmd`, `Events\Key` — F1 / `cmHelp` / Tab / Enter dispatch (M1)
- `Menus\StatusLine`, `Menus\StatusDef` — help-context wiring: `StatusDef(hcLow, hcHigh)` maps contexts to status items (M1/M3)
- `Application\Program` — `helpFile` property, `showHelp(int $context)` hook (M1 skeleton; M6 fills it in)

---

## Task outline (build order)

**Phase 1 — Help system track**

1. **Retrofit `Streamable` interface on `View` (small M1/M2 debt).** Add
   `Persistence\Streamable` interface with `__serialize` / `__unserialize`; make `View`
   implement it. Files: `src/Persistence/Streamable.php`, `src/Views/View.php`. Test:
   confirm existing M1 snapshot tests still pass.

2. **Define `HelpContext` and `hcXxx` constants.** Typed backed-int enum with
   `NoContext = 0`; explain how app-defined contexts are plain `int`s.
   File: `src/Help/HelpContext.php`. Test: enum cases resolve correctly.

3. **Implement `Paragraph` and `CrossRef` value objects.** Immutable data containers;
   no rendering logic yet. Files: `src/Help/Paragraph.php`, `src/Help/CrossRef.php`.
   Tests: construction, property access.

4. **Implement `HelpTopic`.** Paragraph chain + cross-ref array; `getLine(int $line,
   int $width): string` that reflows wrappable paragraphs; `numLines(int $width): int`;
   `getCrossRef(int $i, int $width): array{Point, int, int}`. File:
   `src/Help/HelpTopic.php`. Tests: line reflow, pre-formatted (non-wrap) lines,
   cross-ref coordinate calculation.

5. **Implement `HelpIndex`.** Context-id → file-offset map; `position(int): int`,
   `add(int, int): void`. File: `src/Help/HelpIndex.php`. Tests: add/lookup, missing
   context returns -1 or 0.

6. **Implement `HelpFile` reader/writer.** Reads the compiled help data (PHP-native
   format: a serialized `['magic' => ..., 'index' => [...], 'topics' => [...]]`
   structure). `getTopic(int): HelpTopic`, `putTopic(HelpTopic): void`,
   `invalidTopic(): HelpTopic`. File: `src/Help/HelpFile.php`. Tests: round-trip a
   single topic; invalid context returns the "no help available" topic.

7. **Decision task — help file format.** Choose between (a) PHP-native serialized array
   file (`.tvhelp.php` or `.tvhelp.json`) and (b) faithful binary `.h32` format.
   Recommendation: PHP-native (simpler, no bit-packing, readable in version control).
   Document the format spec as a short PHPDoc block on `HelpFile`. No code change if
   PHP-native is chosen.

8. **Implement `HelpViewer`.** Extends `Scroller`; renders `HelpTopic` lines via
   `DrawBuffer`; highlights the selected `CrossRef` hotspot; handles Tab (next
   cross-ref), Shift-Tab (prev), Enter/click (navigate), Esc (close).
   File: `src/Help/HelpViewer.php`. Tests: headless — draw a topic, assert buffer
   snapshot; Tab moves selection; Enter posts a `cmHelp` with new context.

9. **Implement `HelpWindow`.** Extends `Window`; constructs a `HelpViewer` + two
   `ScrollBar`s inside; own palette (`cHelpWindow`). `Program::showHelp(int $context)`
   opens / reuses the window.
   File: `src/Help/HelpWindow.php`. Tests: headless open, close, context switch.

10. **Wire help contexts to the application.** Add `int $helpContext` property to
    `View`; `Program::handleEvent` catches F1 / `cmHelp`, walks the focused chain to
    find the active context, opens `HelpWindow`. Files: `src/Views/View.php`,
    `src/Application/Program.php`. Tests: headless — set help context on a view, press
    F1, assert `HelpWindow` opens with correct topic.

11. **Implement `HelpSource` parser and `HelpCompiler`.** Parse `.tvhelp` text format
    (`.topic Symbol=N` / `{text:alias}` cross-refs / wrap vs pre-formatted lines);
    resolve forward references; emit a `HelpFile`-writable data structure.
    Files: `src/Help/Compiler/HelpSource.php`, `src/Help/Compiler/HelpCompiler.php`.
    Tests: parse `DEMOHELP.TXT` (adapted); assert correct topic count, cross-ref count,
    context numbers; assert warning on undefined cross-ref target.

12. **Wire `bin/tvhc` CLI tool.** Thin script: `tvhc source.tvhelp output.tvhelp.php`.
    File: `bin/tvhc`. Tests: run on DEMOHELP-adapted source; output loads back into
    `HelpFile` and resolves topics.

**Phase 2 — Persistence track**

13. **Implement `StreamableRegistry`.** `register(string $name, callable $factory): void`;
    `build(string $name): Streamable`; `has(string $name): bool`. Singleton or
    injectable. File: `src/Persistence/StreamableRegistry.php`. Tests: register /
    build round-trip; unknown name throws typed exception.

14. **Implement `OutStream` and `InStream`.** `OutStream::write(Streamable): string`
    (serialize to tagged byte string using `__serialize` + class-name tag);
    `InStream::read(string): Streamable` (deserialize via registry). Files:
    `src/Persistence/OutStream.php`, `src/Persistence/InStream.php`. Tests: round-trip
    a simple `Streamable` object; round-trip a `View` subclass.

15. **Implement `ResourceFile`.** String-keyed store: `put(Streamable, string): void`,
    `get(string): ?Streamable`, `flush(): void`, `remove(string): void`, `count(): int`,
    `keyAt(int): string`. Backed by a local file (JSON index + per-entry serialized blobs,
    or a single PHP `serialize()` file). File: `src/Persistence/ResourceFile.php`.
    Tests: put/get round-trip by key; remove; flush then reload; missing key returns null.

16. **Implement `StringList` and `StringListMaker`.** `StringListMaker::put(int, string)`,
    `flush(string $path)` writes a JSON / PHP array file. `StringList::get(int): string`
    loads it lazily. Files: `src/Persistence/StringList.php`,
    `src/Persistence/StringListMaker.php`. Tests: maker → file → list round-trip;
    missing key returns `''`.

17. **Write `examples/php/persistence/load.php`** — PHP equivalent of `load.cc`: builds
    a custom `LoadWindow` with progress bars; demonstrates `ResourceFile::put()` /
    `get()` saving and restoring a view by string key.
    File: `examples/php/persistence/load.php`. Tests: headless run, assert window
    appears after restore.

---

## Key design decisions / risks

- **Help file format (binary vs PHP-native).** The original `.h32` is a compact
  binary with magic number `0x46484246` ("FBHF"), indexed topic offsets, and
  binary-serialized `HelpTopic` / `HelpIndex` objects. A PHP-native format (JSON or
  a PHP array literal) is far easier to implement, diff in version control, and debug,
  at the cost of binary incompatibility with Borland's original files. This is
  acceptable since no PHP developer will have `.h32` files to reuse.

- **`HelpTopic` reflow algorithm.** TV wraps at `HelpViewer` width, tracking
  `lastOffset` / `lastLine` for a cursor cache. In PHP this must be re-implemented as
  a pure function (no C pointer arithmetic); performance is not a concern at TUI scale
  but correctness (wrap boundaries, cross-ref coordinate mapping after reflow) is subtle.

- **Cross-ref hotspot navigation.** `TCrossRef::offset` is a character offset within
  the *rendered* (reflowed) line, not the raw paragraph text. Coordinate mapping between
  raw source, reflowed lines, and screen cells must stay consistent when the viewer is
  resized.

- **Help contexts wiring to views / menus / status.** Every `View` needs an
  `int $helpContext` (default `HelpContext::NoContext = 0`). `StatusDef(hcLow, hcHigh)`
  already scopes status items by context range (M1/M3). `Program::handleEvent` needs a
  focused-chain walk to find the innermost non-zero context. This integration touches M1
  (`View`) and M3 (`Program`) code.

- **`Streamable` retrofit on `View`.** If `View` does not implement `Streamable` before
  M6, every `View` subclass written in M2–M5 must be retrofitted — a wide, low-value
  change. The safest path is to add the interface stub (empty default implementations
  of `__serialize` / `__unserialize`) to `View` in M1 or M2 without requiring subclasses
  to do anything until M6.

- **`ResourceFile` file locking.** A resource file open for write must be flushed
  atomically (index at end + header at start). PHP's `flock` provides advisory locking;
  the implementation must flush to a temp file and rename to avoid corruption.

- **`StringList` as an i18n primitive.** The original design uses `TStringList` to
  externalize UI strings so they can be replaced without recompiling. In PHP this maps
  naturally to a JSON/PHP array file; wire it to `MessageBox` button labels (`MsgBoxText`)
  in M3 as the first consumer.

- **`crossRefHandler` global.** The C++ `extern TCrossRefHandler crossRefHandler` is a
  global function pointer invoked during help compilation for unresolved refs. Replace
  with a configurable `Closure` on `HelpCompiler`, not a global.

---

## Out of scope / possibly-drop

- **Binary `.h32` compatibility.** No PHP user will have existing `.h32` files. A
  PHP-native format is strictly better for this port; the binary reader is not worth
  implementing.

- **The full `opstream` / `ipstream` / `fpstream` / `fpbase` class hierarchy (Option 1
  above).** ~10 classes of C++ stream machinery map to PHP's native `serialize()` with
  none of the ecosystem value. Drop entirely in favor of `OutStream` / `InStream` backed
  by `__serialize`.

- **`TPWrittenObjects` / `TPReadObjects` pointer-dedup tables.** These exist to handle
  shared-pointer graphs (the same object referenced from multiple places). PHP's object
  model and GC make this a non-issue: serialize/unserialize handle object identity
  natively via PHP's own serialization format.

- **`TTextDevice` / `TTerminal` / `otstream`.** A TTY output view; not part of M6 scope
  and not needed by help or persistence. Defer or drop.

- **Desktop save/restore (`OSaveDesktop` / `ORestoreDesktop` from DEMOHELP.TXT).** TV
  demo saves all open windows to `TVDEMO.DSK` via the object streamer. In the PHP port
  this is a nice-to-have demonstration of `ResourceFile`; include it in `load.php` as
  a stretch goal, not a hard acceptance criterion.

- **`TStrIndexRec` internal struct.** Dissolves into a PHP array entry inside
  `StringListMaker`; no separate class needed.
