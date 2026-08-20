# Bug Review: commit `a64951c` ("expand demos and harden terminal framework")

> Reviewed: 2026-08-20
> Scope: the ~3,500-line change set committed as `a64951c`.
> All 506 tests pass and `bin/tv-fuzz` passes (2000 iterations), so these findings
> are things the suite structurally cannot catch.

## Verification and resolution (2026-08-21)

| # | Result | Resolution |
|---|---|---|
| 1 | **Confirmed and fixed** | CSI-u Ctrl+letter and Alt+letter input is normalized back to the framework's legacy shortcut key codes while retaining modifier metadata. Shifted alternate key codes are now internally consistent. Decoder regressions cover Ctrl-C, Alt-X, and Alt-F; `Screen`/`Program` integration covers both quit paths. |
| 2 | **Confirmed for PTY/stdin disconnects; Ctrl-D claim corrected** | `AnsiDriver` now distinguishes EOF from a read failure with `InputClosedException`; `Program::run()` handles only that lifecycle exception gracefully and still propagates genuine driver faults. In raw mode Ctrl-D is delivered as byte `0x04`, not EOF. |
| 3 | **Confirmed and fixed** | Empty submenus are no longer openable, are skipped by F10/arrow traversal, and cannot install a `MenuPopup` click-catcher. |
| 4 | **Confirmed and fixed** | On a one-row terminal the menu owns row 0 and the status line receives a zero-height rectangle; initial layout and resize reflow use the same geometry. |
| 5 | **Verified as intentional; no defect fix** | Conservative protocol gating is the documented compatibility policy, backed by an explicit environment override. `TERM=xterm-256color` does not identify a terminal or version reliably enough to enable DECSET 2026 safely; broadening the allowlist remains a separate enhancement. |

The original findings remain below as the audit record.

---

## Findings

### [HIGH] Kitty keyboard mode silently disables the Ctrl-C escape hatch and every Alt-hotkey

**File:** `src/Drivers/EscapeDecoder.php:436-520` (`emitKittyKey`), interacting with `src/Application/Program.php:294` and `src/Menus/MenuBar.php:148`
**Category:** Correctness (regression)

**Issue:** `AnsiDriver` now pushes `\e[>1u` (kitty disambiguated mode) whenever
`TerminalCapabilities::$kittyKeyboard` is true — auto-detected on kitty and ghostty.
In that mode those terminals send `CSI u` sequences, and the decoder emits **ASCII
keyCodes plus modifier bits** instead of the legacy key codes the rest of the
codebase matches against. Verified empirically:

| Keypress | Legacy decode | Kitty-mode decode |
|---|---|---|
| Ctrl+C | keyCode `0x03` → quits | keyCode `0x63`, char `c`, mod `Ctrl` → **not handled** |
| Alt+X | `Key::AltX` (`0x2D00`) → fires command | keyCode `0x78`, char `x`, mod `Alt` → **no match** |
| Alt+F | `Key::AltF` → opens File menu | keyCode `0x66`, char `f`, mod `Alt` → **no match** |

- `Program::handleEvent()` quits only on `keyCode === 0x03`, so the documented
  "universal quit escape hatch… the app is always escapable" guarantee is dead on
  kitty/ghostty.
- `MenuBar`/`StatusLine` match hotkeys via `$key->is($item->key)` against
  `Key::AltX`-style enums, so Workbench's advertised Alt shortcuts and the tutorial
  menus never fire.
- Root cause: `modifiers` is produced by the decoder but the core shortcut paths
  do not consume it (`OpenCodeView` has its own Ctrl-aware helper), and
  `emitKittyKey` never translates back into the legacy domain. Related wart:
  Shift+letter yields a lowercase
  keyCode with uppercase `char`, inconsistent with legacy bytes.

**Why it matters:** Users on kitty/ghostty lose the guaranteed exit path and the
app's advertised shortcuts without any error — the worst kind of failure for a
TUI. Tests miss it because they inject synthetic `Key::AltF` events and legacy
byte sequences, never CSI-u sequences.

**Suggestion:** Translate to the legacy domain inside `emitKittyKey` before emitting, e.g.:

```php
// Ctrl+letter keeps its classic control ordinal (Ctrl+C -> 0x03)
if (($modifiers & KeyModifier::Ctrl) !== 0 && $codepoint >= 0x61 && $codepoint <= 0x7A) {
    $events[] = Event::keyDown(new KeyDownEvent($codepoint & 0x1F, '', $modifiers));
    return;
}
// Alt+letter maps onto the existing Key::AltX enum values
if (($modifiers & KeyModifier::Alt) !== 0 && ctype_alpha(mb_chr($codepoint))) {
    $alt = self::altKey('Alt' . strtoupper(mb_chr($codepoint)));
    if ($alt !== null) {
        $events[] = Event::keyDown(new KeyDownEvent($alt->value));
        return;
    }
}
```

…and add decoder tests feeding `\e[99;5u` / `\e[120;3u` through
`Screen::pollEvents()` into `Program`.

---

### [MEDIUM] stdin EOF now throws an uncaught exception out of `Program::run()`

**File:** `src/Drivers/AnsiDriver.php:352-357`
**Category:** Correctness / error handling

**Issue:** `pollInput()` now does
`if ($bytes === false || ($bytes === '' && feof($this->stdin))) { throw DriverException::readFailed(); }`.
`Program::run()` has `try/finally` but no `catch`, so an actual stdin/PTY EOF or
closed pipe unwinds as an uncaught `DriverException` after terminal restore,
dumping a stack trace. In raw mode Ctrl-D itself is an ordinary `0x04` input byte,
not EOF. (The old behavior — returning `''` forever on EOF — busy-spun, so throwing
is defensible, but it should be *handled*.)

**Suggestion:** Catch `DriverException` at the `run()`/`execView()` loop boundary
and treat EOF as a graceful end (e.g., `endModal(Cmd::Quit)`), or document that
callers must catch it.

---

### [LOW] Empty submenu creates an invisible full-screen click-catcher

**File:** `src/Menus/MenuPopup.php:31-36` + `src/Menus/MenuBar.php:341-360` (`openMenu`)
**Category:** Correctness / edge case

**Issue:** `openMenu()` inserts a `MenuPopup` covering the *entire* owner extent
for any submenu, including one with zero items. `draw()` early-returns when
`items === []`, so nothing is painted — yet the popup still intercepts and clears
every mouse event until the user clicks (dismiss) or presses Esc/F10. A
misconfigured menu looks like the app froze to mouse input.

**Suggestion:** In `openMenu()`, skip popup creation when `$subMenu->items() === []`
(or don't treat such items as submenus in `switchMenu`/`firstSubMenuIndex`).

---

### [LOW] On a 1-row terminal, menu bar and status line are both bound to row 0

**File:** `src/Application/Program.php:116-122, 210-233`
**Category:** Correctness / edge case

**Issue:** With `rows === 1`: `$menuBottom = 1`, `$statusTop = 1` gives the desktop
zero height (fine), but `statusRect = Rect::of(0, max(0, 0), cols, 1)` overlaps the
menu bar's `Rect::of(0, 0, cols, 1)`. Both draw to row 0; the status line
(inserted last) wins and the menu bar becomes invisible/unreachable by mouse.
No crash — purely degenerate-geometry cosmetics.

**Suggestion:** Give the status line precedence only when `rows >= 2`, e.g. skip
inserting the menu bar when `rows < 2`, or bound status to `[menuBottom, rows)`
when `rows === 1`.

---

### [LOW] Sync-update allowlist regresses tear-free rendering on common terminals

**File:** `src/Terminal/TerminalCapabilities.php:24-44`
**Category:** Performance / behavioral regression (possibly intentional)

**Issue:** `Screen::flush()` previously always wrapped frames in DECSET 2026 sync
pairs (harmless where unsupported); now only kitty/ghostty/contour get them.
WezTerm, iTerm2, VS Code, Alacritty etc. support 2026 but aren't detected, so
their users silently lose flicker-free frames. The class docstring says this
conservatism is deliberate and `TVISION_SYNC_UPDATE=1` can force it — flagged in
case the intent was "safe default," not "smaller feature surface."

**Suggestion:** Consider treating DECSET 2026 as universally-safe (as before) and
keep conservative detection only for the kitty keyboard protocol, which genuinely
changes input semantics.

---

## Summary

| # | Severity | File | Issue |
|---|----------|------|-------|
| 1 | **HIGH** | `EscapeDecoder.php:436` (+`Program.php:294`) | Kitty mode breaks Ctrl-C quit & all Alt-hotkeys; `modifiers` never consumed |
| 2 | MEDIUM | `AnsiDriver.php:352` | stdin EOF throws uncaught exception out of `run()` |
| 3 | LOW | `MenuPopup.php:31` | Empty submenu = invisible full-screen mouse trap |
| 4 | LOW | `Program.php:116` | 1-row terminal: menu bar and status line overlap on row 0 |
| 5 | LOW | `TerminalCapabilities.php:24` | Sync-update allowlist drops 2026 on supporting terminals |

**Verdict: Request changes** — finding #1 should be fixed before merge: it
silently removes the app's guaranteed exit path and breaks advertised shortcuts
on kitty/ghostty, and it's invisible to the current test suite. Findings 2–5 are
follow-ups.

## Clean areas

The defensive-hardening work itself checked out clean — traced edge cases found
no overflow or clipping holes, and the fuzz harness agrees:

- `src/Support/IntMath.php` saturating arithmetic (add/subtract/multiply verified correct)
- Buffer/cell limits (`Buffer::MAX_CELLS`, `View::assertValidBounds`, `Screen::resizeBuffers`)
- Clipping rewrites (`View::writeRows`/`writeRowCells`, `DrawBuffer::moveChar`)
- OSC/DCS/SOS/PM/APC control-string consumption in `EscapeDecoder`
- `Attribute::toCellValue()`/`fromCellValue()` extended-cell round-trip (incl. the `-1` sentinel)
