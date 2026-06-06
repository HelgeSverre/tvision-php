# TurboVision for PHP — Roadmap

The durable north star for this project, so the end goal survives across sessions.
For the detailed per-milestone design, see `docs/superpowers/specs/`. For what the
original library is and can do, see `docs/references/` (especially `CLASS-INDEX.md`
and `CAPABILITIES.md`).

## North star

A complete, modern, idiomatic **PHP 8.5 port of Borland's Turbo Vision** — the classic
text-mode (TUI) application framework — published on Packagist as
`helgesverre/turbovision` (namespace `HelgeSverre\TurboVision`).

**Guiding principles (settled 2026-06-05):**

1. **Faithful core, modern skin.** Replicate the proven engine — view tree, event
   loop, draw buffer, palette chain, `execView` modality, command/event model — so
   behaviour matches the original and the C++ examples act as behavioral oracles.
   Expose a modern, ergonomic PHP API on top (typed enums, typed events, fluent
   construction, constructor promotion).
2. **No `T` prefix.** `TView` → `View`, etc. Lean on namespaces.
3. **Zero system dependencies.** Pure-PHP ANSI/termios terminal backend behind a
   `Driver` interface; a headless driver makes the whole stack unit-testable without a
   TTY. No ncurses / VCS / Gpm.
4. **UTF-8 + truecolor baseline**, degrading to 256/16-color/mono. CP437 semigraphics
   mapped to Unicode.
5. **The original examples are the test suite.** Each milestone's "done" is defined by
   the matching `tvguid*` / demo programs passing (headless snapshot tests).

**Definition of "complete":** a PHP developer can build the kind of full-screen
terminal applications Borland's IDEs were — windows, menus, modal dialogs with
validated forms, a text editor, file/color dialogs, context help — entirely in
idiomatic PHP, installable with a single `composer require`.

## Milestones

Each milestone is its own spec → plan → build cycle. Acceptance is the listed example
programs running (on a real terminal + headless snapshot tests).

| # | Milestone | Delivers | Acceptance examples | Status |
|---|-----------|----------|---------------------|--------|
| **M1** | **Walking skeleton** | Driver (ANSI + headless) · screen buffer + diff renderer · `View`/`Group` · event loop · `Application`/`Program`/`Desktop`/`Background`/`StaticText` · minimal `MenuBar`+`StatusLine` | `tvguid01–03` | **Complete** — all three plans built & green; 173 tests passed, PHPStan max clean |
| M2 | Windowing | `Window`, `Frame`, `ScrollBar`, `Scroller`, `ListViewer`; resize handling | `tvguid04–10` | **Complete** — all tasks built & green; PHPStan max clean; tvguid04–10 ported with headless snapshot tests |
| M3 | Menus-deep + Dialogs | Pull-down menu navigation; `Dialog`, `Button`, `InputLine`, `CheckBoxes`, `RadioButtons`, `Label`, `ListBox`, `MessageBox`; `setData`/`getData` | `tvguid11–16` | **Spike plan written** |
| M4 | Editor & files | `Validator` family; `Editor`/`FileEditor`/`EditWindow`/`Memo`; `FileDialog`/`ChDirDialog` | `validator.cc`, `tvedit.cc`, std dialogs | **Spike plan written** |
| M5 | Outline & color | `OutlineViewer`/`Outline`/`Node`; `ColorDialog` + selectors | outline + color demos | **Spike plan written** |
| M6 | Help & persistence | Help system (+ `tvhc` or a PHP-native help format); persistence via **PHP-native serialization** (binary streamer dropped — see spike) | demo app help; `load.cc` | **Spike plan written** |
| M7 | The full demo | The complete TVDEMO app wired end-to-end (calculator, calendar, puzzle, ASCII table, gadgets, mouse dialog, help) | `examples/cpp/demo/*` | Stretch |

All plans live in `docs/superpowers/plans/`. The two M1 build-plans (driver/renderer, views/application) are full TDD plans with complete code; M2–M6 are spike outlines to be promoted to full plans just before each is built.

## Cross-cutting tracks (advance alongside milestones)

- **Quality:** PHPStan max, Pest tests, GitHub Actions CI green from M1.
- **Docs:** README quick-start (M1); per-feature guides; an eventual docs site.
- **DX:** `bin/demo` runner from M1; published examples mirror in `examples/php/`.
- **Packaging/licensing:** MIT for our code + `NOTICE` crediting Borland (public
  domain) and Sergio Sigala (BSD port).

## Cross-milestone coordination notes (surfaced while spiking the plans)

These are the load-bearing dependencies between milestones — get them right early or pay a retrofit tax:

- **`Group::execView()` modal loop is the keystone for M3+.** Every dialog and message
  box hangs on a correct re-entrant modal sub-loop. It is built and headless-tested in
  **M1 Plan 3** (ahead of strict need) precisely to de-risk M3.
- **Broadcast fan-out + `Button` default protocol.** `Group` must fan `evBroadcast` to all
  subviews; M3's default-button behaviour fails silently without it. Built in M1 Plan 3.
- **`ScrollBar`/`ListViewer` must ship with stable interfaces in M2** — M3 (`ListBox`,
  dialog scroll bars) and M5 (`OutlineViewer`, color list views) all extend them.
- **Shared `wcwidth` utility lands in M4 (editor) but should be reusable** by `DrawBuffer`
  and any width-sensitive view, not re-solved per class. PHP has no built-in.
- **Persistence fork resolved → PHP-native serialization** (M6 spike): drop Borland's
  binary `*pstream` hierarchy; use `__serialize`/`__unserialize` on a `Streamable`
  interface + a `StreamableRegistry`. Watch-item: if we want round-trippable view trees,
  add a tiny `Streamable` marker to `View` in M1/M2 rather than retrofitting M2–M5 later.
- **`InputLine` validator hooks (M4)** — ensure M3's `InputLine` exposes a validator seam
  (`setValidator`/`valid()`), or M4 back-fills it first.

## Known deferred decisions (revisit at the relevant milestone)

- **PHP version floor** — currently `>=8.5` per the explicit target; revisit toward
  `>=8.3` if adoption outweighs bleeding-edge idioms.
- **Windows support** — out of scope initially (`stty`/POSIX assumed); a future
  driver could target Windows VT/conpty.
- **Wide/combining graphemes** (East-Asian width) — clean ASCII/BMP in M1; full
  wcwidth-style handling lands with the editor (M4).
- **Object streaming (M6)** — port Borland's binary streamer vs idiomatic PHP
  serialization; decided in M6's own spec.

## Where workflows / "ultracode" pay off (deferred parallel-heavy work)

Most build tasks are sequential TDD and don't warrant multi-agent orchestration. These
*do*, and should be run as workflows when we reach them:

1. **Escape-sequence decoder corpus (Plan 2).** Fan out agents to build + adversarially
   verify the terminal-input decoder against real byte sequences from xterm, kitty, tmux,
   screen, Windows Terminal, iTerm — each emits different bytes for the same key.
2. **Parallel example translation + faithfulness audit.** Translate the `tvguid*`/demo
   programs concurrently, each cross-checked against its C++ original for divergence.
3. **Whole-codebase faithfulness review** against `docs/references/source/` once enough
   surface exists to audit.

## Where we are

- ✅ **M1 complete:** views & application built and green; tvguid01–03 run on a real terminal and pass headless snapshot tests.
- ✅ **M2 complete:** windowing built and green; tvguid04–10 ported with headless snapshot tests + real-PTY case. Full suite: 278 passed, PHPStan max clean.
- ✅ Reference-gathering complete (`docs/references/`, `examples/cpp/`).
- ✅ Foundation design approved (`docs/superpowers/specs/2026-06-05-turbovision-foundation-design.md`).
- ✅ **M1 Plan 1 built & green** and merged to `main`: Geometry, Drawing, Events (47 tests, PHPStan max clean).
- ✅ **M1 Plan 2 built & green:** Drivers (ANSI + headless), EscapeDecoder, Rendering, Terminal\Screen.
- ✅ **M1 Plan 3 built & green:** Views (State/View/Group/StaticText/Background/Desktop), Menus (MenuBar/StatusLine + definitions), Application (Program/Application), examples Guide01–03 with headless Feature tests. Full suite: 173 passed, PHPStan max clean.
- ▶️ **Now:** M2 (windowing) built and green; next is M3 (dialogs & controls — `Dialog`, `Button`, `InputLine`, `ListBox` on the M2 `ListViewer`).
- 🛠️ Working directly on `main` (no feature branches by default).
