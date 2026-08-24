# Changelog

All notable changes to TurboVision for PHP will be documented in this file.

## Unreleased

## 0.1.2 - 2026-08-24

- Reused invariant screen, origin, and ancestor-clipping context across multi-row
  view writes while retaining row-specific sibling occlusion.
- Added a repeatable deep-view rendering benchmark; the targeted 120×30 write
  through eight nested owners improved by about 40% in the release pass.

## 0.1.1 - 2026-08-24

- Pinned Desktop tiling across prime and composite view counts in both row-first
  and column-first orientations, then removed its unreachable uneven-grid path.
- Consolidated opaque and transparent view occlusion onto one overflow-safe row
  clipping primitive, with coverage for nested groups and integer-edge bounds.
- Kept PTY resize fuzz children on the same PHP runtime as the parent harness.

## 0.1.0 - 2026-08-24

Initial preview release.

- Added the core view tree, event loop, palettes, window management, menus,
  status lines, dialogs, controls, scrolling, and responsive terminal layout.
- Added editors, file and directory dialogs, validators, outlines, colour
  configuration, compiled context help, and bounded resource persistence.
- Added ANSI and headless drivers, incremental terminal input decoding,
  double-buffered diff rendering, HTML rendering, and Unicode-aware drawing.
- Added runnable tutorial, Workbench, Kitchen Sink, Studio, Calendar, BIOS, and
  OpenCode examples.
- Added deterministic unit, feature, visual, fuzz, and real-terminal test
  coverage.

The `0.x` series is a preview: public APIs may still change between minor
releases while the framework is exercised by downstream applications.
