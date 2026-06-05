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
| **M1** | **Walking skeleton** | Driver (ANSI + headless) · screen buffer + diff renderer · `View`/`Group` · event loop · `Application`/`Program`/`Desktop`/`Background`/`StaticText` · minimal `MenuBar`+`StatusLine` | `tvguid01–03` | **In progress** — Plan 1/3 (foundation primitives) built & green; Plans 2–3 next |
| M2 | Windowing | `Window`, `Frame`, `ScrollBar`, `Scroller`, `ListViewer`; resize handling | `tvguid04–10` | Planned |
| M3 | Menus-deep + Dialogs | Pull-down menu navigation; `Dialog`, `Button`, `InputLine`, `CheckBoxes`, `RadioButtons`, `Label`, `ListBox`, `MessageBox`; `setData`/`getData` | `tvguid11–16` | Planned |
| M4 | Editor & files | `Validator` family; `Editor`/`FileEditor`/`EditWindow`/`Memo`; `FileDialog`/`ChDirDialog` | `validator.cc`, `tvedit.cc`, std dialogs | Planned |
| M5 | Outline & color | `OutlineViewer`/`Outline`/`Node`; `ColorDialog` + selectors | outline + color demos | Planned |
| M6 | Help & persistence | Help system (+ `tvhc` or a PHP-native help format); object streaming/resources — or an idiomatic PHP serialization replacement | demo app help; `load.cc` | Planned |
| M7 | The full demo | The complete TVDEMO app wired end-to-end (calculator, calendar, puzzle, ASCII table, gadgets, mouse dialog, help) | `examples/cpp/demo/*` | Stretch |

## Cross-cutting tracks (advance alongside milestones)

- **Quality:** PHPStan max, Pest tests, GitHub Actions CI green from M1.
- **Docs:** README quick-start (M1); per-feature guides; an eventual docs site.
- **DX:** `bin/demo` runner from M1; published examples mirror in `examples/php/`.
- **Packaging/licensing:** MIT for our code + `NOTICE` crediting Borland (public
  domain) and Sergio Sigala (BSD port).

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

- ✅ Reference-gathering complete (`docs/references/`, `examples/cpp/`).
- ✅ Foundation design approved (`docs/superpowers/specs/2026-06-05-turbovision-foundation-design.md`).
- ✅ M1 implementation plan written (`docs/superpowers/plans/2026-06-05-m1-foundation-primitives.md`).
- ✅ **M1 Plan 1 built & green:** Geometry, Drawing, Events (47 tests, PHPStan max clean) on branch `feat/foundation-primitives`.
- ▶️ **Next:** M1 Plan 2 (driver & renderer) — `Driver`/`AnsiDriver`/`HeadlessDriver`, escape decoder, ANSI encoder, diff presenter.
