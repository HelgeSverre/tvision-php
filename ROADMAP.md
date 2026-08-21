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
| M3 | Menus-deep + Dialogs | Nested pull-down menus; `Dialog`, `Button`, `InputLine`, clusters, labels, lists, history, message boxes; data/validation hooks | `tvguid11–16` | **Complete** — reusable controls and all six tutorials are ported and covered |
| M4 | Editor & files | Validator family; `Editor`/`FileEditor`/`EditWindow`/`Memo`; file and directory dialogs | `validator.cc`, `tvedit.cc`, std dialogs | **Complete** — focused legacy editor, validator, and picker acceptance demos included |
| M5 | Outline & color | `OutlineViewer`/`Outline`/`Node`; `ColorDialog` and selectors | outline + color demos | **Complete** — reusable outline and colour subsystems covered by headless tests |
| M6 | Help & persistence | Compiled PHP help format + viewer; safe PHP-native resources and string lists | demo help; `load.cc` | **Complete** — `bin/tvhc` produces the documented help format; resources include explicit allow-listed declarative view trees, not binary stream compatibility |
| M7 | The full demo | The complete historic TVDEMO app wired end-to-end (calculator, calendar, puzzle, ASCII table, gadgets, mouse dialog, help) | `examples/cpp/demo/*` | **Stretch** — supporting framework families and focused legacy acceptance demos are present; the monolithic original app is not yet ported |

All plans live in `docs/superpowers/plans/`. The two M1 build-plans (driver/renderer,
views/application) are full TDD plans; the later milestone spikes now serve as
implementation and compatibility notes for the completed PHP-native subsystems.

## Cross-cutting tracks (advance alongside milestones)

- **Quality:** PHPStan max, Pest tests, GitHub Actions CI green from M1.
- **Docs:** README quick-start (M1); per-feature guides; an eventual docs site.
- **DX:** `bin/demo` runner from M1; published examples mirror in `examples/php/`.
- **Packaging/licensing:** MIT for our code + `NOTICE` crediting Borland (public
  domain) and Sergio Sigala (BSD port).

## Cross-milestone implementation notes

These were the load-bearing dependencies between milestones and now describe the
implemented seams:

- **Nested modality and broadcast fan-out.** `Group::execView()` supports re-entrant
  dialogs, while broadcast delivery and the default-button protocol support reusable
  dialog controls.
- **Shared scrolling contracts.** `ScrollBar` and `ListViewer` are the base contracts
  used by dialog list boxes, outlines, and colour lists.
- **Width-aware text.** The editor and text device share Unicode-aware width and
  clipping behaviour rather than treating terminal cells as PHP byte offsets.
- **Deliberate resource boundary.** Resource files use an explicit `Streamable`
  allow-list and bounded JSON codec. Named `ViewResource` trees rebuild runtime
  ownership through registered factories; live owners/screens/cursors and Turbo
  Vision binary `*pstream` compatibility remain deliberately outside the format.
- **Input validation seam.** `InputLine` exposes validator and transfer hooks used by
  the reusable validator family.

### Deferred follow-ups from the faithfulness audit

All constant families audited byte-for-byte faithful vs the C++ source (Cmd/EventType/
EventMask/Key/sf-of-gf-dm/all palettes/CGA→ANSI/palette chain). Remaining non-urgent item:

- **Scroll-bar track glyph.** `Glyphs::SCROLL_TRACK = '░'` speckles on crisp fonts just like
  the old desktop did. Consider `▒`/`▓` (or a configurable track glyph) for consistency with
  the new `▓` desktop.

## Known deferred decisions (revisit at the relevant milestone)

- **PHP version floor** — currently `>=8.5` per the explicit target; revisit toward
  `>=8.3` if adoption outweighs bleeding-edge idioms.
- **Windows support** — out of scope initially (`stty`/POSIX assumed); a future
  driver could target Windows VT/conpty.
- **Full historic TVDEMO port** — framework parity and small acceptance demos are in
  place, but the original all-in-one demo application remains a separately scoped
  exercise.

## Where workflows / "ultracode" pay off (future parallel-heavy work)

Most build tasks are sequential TDD and don't warrant multi-agent orchestration. These
*do*, and should be run as workflows when we reach them:

1. **Escape-sequence decoder corpus (Plan 2).** Fan out agents to build + adversarially
   verify the terminal-input decoder against real byte sequences from xterm, kitty, tmux,
   screen, Windows Terminal, iTerm — each emits different bytes for the same key.
2. **TVDEMO translation.** Translate its independent calculator, calendar, puzzle,
   table, gadget, and mouse-dialog screens concurrently, each cross-checked against
   its C++ original for divergence.
3. **Whole-codebase faithfulness review** against `docs/references/source/` after a
   future TVDEMO pass.

## Where we are

- ✅ **M1–M2 complete:** foundation and windowing, including the `tvguid01–10`
  acceptance path, are implemented and continuously covered headlessly.
- ✅ **M3 complete:** deep menus, reusable dialogs/controls/history/message boxes, and
  `tvguid11–16` are implemented.
- ✅ **M4 complete:** validators, editor family, and file/directory dialogs are
  implemented, with focused legacy acceptance examples.
- ✅ **M5 complete:** outline and colour-dialog subsystems are implemented.
- ✅ **M6 complete:** compiled help/viewer, bounded PHP-native resource persistence,
  string lists, and terminal text devices are implemented.
- ✅ Reference-gathering complete (`docs/references/`, `examples/cpp/`).
- ✅ Foundation design approved (`docs/superpowers/specs/2026-06-05-turbovision-foundation-design.md`).
- ✅ **M1 Plan 1 built & green** and merged to `main`: Geometry, Drawing, Events (47 tests, PHPStan max clean).
- ✅ **M1 Plan 2 built & green:** Drivers (ANSI + headless), EscapeDecoder, Rendering, Terminal\Screen.
- ✅ **M1 Plan 3 built & green:** Views (State/View/Group/StaticText/Background/Desktop), Menus (MenuBar/StatusLine + definitions), Application (Program/Application), examples Guide01–03 with headless Feature tests.
- ▶️ **Now:** integrate and harden the completed framework surface; the complete
  historic TVDEMO remains the next substantial parity project.
- 🛠️ Working directly on `main` (no feature branches by default).
