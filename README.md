# TurboVision for PHP

A modern PHP 8.5 re-implementation of **Turbo Vision** — Borland's classic text-mode
(TUI) application framework — intended to ship as a Packagist library under the
namespace `HelgeSverre\TurboVision`.

> **Project status: reference-gathering phase.** No PHP code exists yet. By design,
> we are first assembling a faithful, offline snapshot of the original library and
> its examples from primary sources, then designing the PHP library's shape from that
> material. Naming convention for the port: **drop the `T*` prefix** and lean on
> namespaces (`TView` → `View`, `TWindow` → `Window`, `TApplication` → `Application`).

## What's here now

```
docs/references/   ← gathered upstream reference material (see its README)
  ├── README.md                    provenance, licensing, navigation
  ├── CLASS-INDEX.md               every class, grouped by module, + proposed PHP names
  ├── CAPABILITIES.md              narrative tour of what the library does + port priorities
  ├── installation-handbook.md     keyboard / screen / mouse / events / env-var behaviour
  ├── installation-handbook.texi   original Texinfo source
  ├── UPSTREAM-COPYRIGHT.txt       licensing provenance
  ├── source/tvision-0.8/          full extracted C++ source (the authoritative reference)
  └── sigala-site/                 full mirror of the upstream site (doxygen class reference, etc.)

examples/          ← original programs to translate (= future acceptance tests)
  ├── README.md                    catalog + recommended translation order
  └── cpp/{tutorial,demo}/         the C++ originals
```

**Start at [`docs/references/README.md`](docs/references/README.md).**

## Source of the port

Based on **Sergio Sigala's UNIX port of Turbo Vision 2.0** (TVision 0.8), itself a
port of Borland's public-domain Turbo Vision. Upstream:
<http://www.sigala.it/sergio/tvision/>. See `docs/references/` for full provenance
and the layered licensing (Borland public domain + Sigala BSD-style + contributors).

## Next (deferred — not started)

Once the references are reviewed, the design phase decides the library's shape:
namespace/module layout, the terminal driver strategy (ANSI/termios vs FFI-ncurses),
how object streaming maps onto PHP, the public API surface, `composer.json`, and the
test approach (translated examples as fixtures).
