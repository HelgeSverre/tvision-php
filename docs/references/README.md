# Turbo Vision — Reference Material

This directory collects the upstream reference material for **Turbo Vision**, the
classic text-mode application framework, gathered so we can re-implement it in
modern PHP 8.5 as `HelgeSverre\TurboVision`.

> **Status:** Reference-gathering phase. Nothing here is PHP yet. The goal of this
> directory is to be a faithful, offline, citable snapshot of the original library
> so we can design the PHP port from primary sources rather than memory.

## What Turbo Vision is

Turbo Vision (TV) is an object-oriented, character-mode (TUI) application framework
created by Borland (~1990) for Turbo Pascal and Borland C++. Borland used it to
build its own DOS-era IDEs. It gives you windows, menus, dialogs, scrollers, list
boxes, input lines, validators, a text editor, an object-streaming persistence
layer, and a help system — all driven by an event loop and a view hierarchy. The
C++ sources were released to the public domain by Borland around 1997.

The specific port we are working from is **Sergio Sigala's UNIX port (TVision 0.8)**,
which adapts Borland's Turbo Vision 2.0 to generic Unix (Linux/FreeBSD) on top of
`ncurses`. We chose it because it is clean, self-contained, well-documented
(doxygen-annotated headers + a Texinfo handbook), and the closest thing to a
canonical "modern-ish C++" rendering of the original API.

- Upstream homepage: <http://www.sigala.it/sergio/tvision/index.html>
- Version captured: **TVision 0.8** (released 2001)

## Directory map

| Path | What it is | Why it's here |
|------|-----------|---------------|
| `sigala-site/` | Full `wget` mirror of the upstream site | Doxygen **class reference** (`sigala-site/html/`), screenshots, and the community resource archive |
| `sigala-site/html/` | Doxygen-generated HTML class reference (424 files) | Per-class docs: methods, members, hierarchy. Start at `html/index.html` |
| `sigala-site/techinfo/` | Borland's original Technical Information notes (`ti*.txt`) | Primary-source design notes from Borland itself |
| `sigala-site/{borland,dlg,dsktop,gadgets,...}/` | Community code archive (mostly DOS-era `.zip`) | Worked examples of specific techniques (custom dialogs, gadgets, palettes, help) |
| `source/tvision-0.8/` | Full extracted C++ source of the port | **The authoritative reference.** Headers = class declarations; `.cc` = behaviour to translate |
| `source/tvision-0.8/lib/` | The library itself (`*.h` + `*.cc`) | What we are actually porting |
| `source/tvision-0.8/doc/html/` | The same doxygen docs, bundled with the source | Offline copy travelling with the code |
| `installation-handbook.texi` | Texinfo source of the handbook | Prose on keyboard / screen / mouse / events / env vars |
| `installation-handbook.md` | Markdown conversion of the above | Readable version of the handbook |
| `UPSTREAM-COPYRIGHT.txt` | The port's copyright notice | Licensing provenance |
| `CLASS-INDEX.md` | **Systematic list of every class**, grouped by module | The map of what we have to build |
| `CAPABILITIES.md` | Narrative overview of the library's capabilities | The "what can it do" tour |

Example programs to translate later live one level up in [`../../examples/`](../../examples/).

## How to navigate the source for porting

`source/tvision-0.8/lib/` is organised by header. Each header declares a cluster
of related classes; the matching `T*.cc` / `*.cc` files hold the implementations.
The single include `lib/tv.h` is the umbrella header — applications `#define
Uses_TXxx` before including it to pull in only the classes they use. The header
groupings are the natural module boundaries and are exactly how `CLASS-INDEX.md`
is organised:

```
objects.h   geometry + collections      views.h    view hierarchy (TView…TWindow)
app.h       application/desktop          dialogs.h  dialogs + controls
menus.h     menus + status line          editors.h  text editor
textview.h  terminal/TTY view            validate.h input validators
stddlg.h    file/dir standard dialogs    colorsel.h color-picker dialog
outline.h   tree/outline viewer          help.h     help system
helpbase.h  help file format             msgbox.h   message boxes
resource.h  resource files/string lists  system.h   screen/event/mouse drivers
tobjstrm.h  object streaming (persist)   buffers.h  memory manager
```

## Licensing & provenance (important for a Packagist release)

Three layers of rights stack here, and we need to honour all of them in the PHP port:

1. **Original Turbo Vision** — released to the **public domain** by Borland/Inprise
   (~1997). See `sigala-site/techinfo/` and the headers in `source/tvision-0.8/lib/`
   (e.g. `tv.h`) for Borland's notices.
2. **Sigala's UNIX port changes** — **BSD-style license** (3-clause-ish, see
   `UPSTREAM-COPYRIGHT.txt` and the `Copyright` chapter of the handbook). Attribution
   required.
3. **Other contributors** — retain their respective copyrights (see the Credits
   chapter of the handbook).

Our PHP re-implementation is a **clean re-implementation of a public-domain API**.
We are translating behaviour, not copy-pasting BSD source, but we should still
credit Borland and Sigala in the final library's README/NOTICE. The chosen library
license (MIT, etc.) is a design-phase decision — flagged in `CLASS-INDEX.md` /
project notes, not decided here.

## Provenance notes

- Mirrored with `wget -r -np -k -p -E` from `http://www.sigala.it/sergio/tvision/`
  on 2026-06-05. The site serves over plain HTTP with an expired TLS cert; links
  were rewritten for offline browsing.
- Source extracted from `mysource/tvision-0.8.tar.gz` (the latest release listed).
- Eight historical release tarballs (0.1–0.8) are preserved under
  `sigala-site/mysource/` should we need to diff the API's evolution.
