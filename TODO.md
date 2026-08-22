# TODO — Whole-Project Code Review (2026-08-21 → 2026-08-22)

Status after the fix campaign: **all 7 HIGH bugs fixed**, **12 of 13 MEDIUM bugs
fixed**, all duplication/refactor/API items either landed or explicitly deferred
below. Baseline at review time: Pest 730 passed / PHPStan max clean.
Final state: **Pest 756+ passed / PHPStan max clean** after every commit.

## Commit map

| Commit | Contents |
|---|---|
| `4f6ded5` | Batched-event preprocessing; modal EOF grace; bands() dedup; Key::CtrlC; core-hook docs |
| `ae65209` | Terminal UTF-8 discard fix; incremental decoder carry; O(n²) CR rewrite; relayout dedup |
| `cf7a578` | Decoder double-Esc flush rescue; key-code tables; altKey map; injectable clock |
| `fb243fb` | Outline folded Ctrl keys; allocation-free Floyd cycle guard |
| `77609ba` | FileInputLine/FileInfoPane broadcast masks |
| `999c83f` | Shared Support\Clipboard; Key::Ctrl* constants |
| `e8d6b3d` | Editor goal column |
| `a2ad4f7` | stty raw verification; Screen cursor-state rollback on failed flush; MAX_CELLS dedup; EINTR branch collapse; forStdio |
| `2e52562` | Help context dup rejection + single-pass compiler; Support\AtomicFileWriter (+chmod); ResourceFile routed through it; ViewResourceNode ctor validation; StreamCodec int-key rejection |
| `2cff8c4` | Menu layout walks ×5 → topLayout()/hintLayout(); Mnemonic::hotKey/visibleLength; itemEnabled→MenuView; SubMenu::toMenuItem; Cluster Alt-mnemonics unfocused; MultiCheckBoxes/RadioButtons clamps; PictureValidator dead param; SearchFailed notification; SearchOptions/EditorDialogKind honesty docs |
| `f62d4f9` | IntMath::clamp; Rect/Point conveniences; Cell blank/sentinel/with; Buffer row()+Countable+iteration; DrawBuffer helpers; View::localToGlobal; Group iteration+windows(); SizeLimits VO migration; Window fluent setters; CommandSet Countable/IteratorAggregate; SortedCollection search/remove; ghost-pixel repaint on remove() |
| `109109c` | PictureNodeType enum; HelpTopic layout cache; runningAsMain guard across 20 examples; HelpFile ctor validation + fallbackTopic rename; FilePath::join canonicalization; ListViewer FQCN cleanup |
| `e71fca3` | IntMath::clamp adoption at hotspots; View::fillExtent() shared primitive; doc hardening (@throws, root-origin caveat, divider-reserved note) |

## ✅ Completed

### 🔴 High bugs — all seven fixed
- [x] Batched events bypass preprocessing (`Program.php` getEvent)
- [x] Invalid UTF-8 discards output (`Text/Terminal.php` lossy byte-wise fallback)
- [x] O(n²) carriage-return rewrite (tail-cell cache; ~1100× → linear)
- [x] Dead Ctrl+PageUp/PageDown handlers (`OutlineViewer.php`)
- [x] File-dialog mirroring dead code (`EventMask::Broadcast` added)
- [x] Split static clipboards (`Support\Clipboard`)
- [x] Editor goal-column loss (ragged-line drift)

### 🟠 Medium bugs
- [x] Ghost pixels under modals / programmatic removals (`Group::remove()` exposure redraw)
- [x] Silent stty raw-mode failure (`AnsiDriver::init()` verifies transition)
- [x] Modal loops ignore closed-input policy (`Group::execView` catches InputClosedException)
- [x] Duplicate help contexts overwrite topics (`HelpCompiler` throws)
- [x] ViewResourceNode ctor skips validation (load-path contract enforced in ctor)
- [x] Held `ESC ESC [` destroyed at flush (now emits Esc presses)
- [x] Cursor state committed before fallible write (snapshot/restore)
- [x] Cluster mnemonics unreachable when unfocused (Alt+letter via PreProcess routing)
- [x] MultiCheckBoxes shift overflow guard; RadioButtons setData clamps value
- [x] Atomic writes promote 0600 perms (chmod before rename)
- [x] Numeric-keyed persisted maps silently change shape (rejected at encode)
- [x] Root-origin hazard documented (`absoluteOrigin()`)

### 🔁 Duplication
- [x] Clamps → `IntMath::clamp()` adopted at hotspot sites
- [x] Atomic-write copy eliminated (ResourceFile → AtomicFileWriter)
- [x] Menu layout walk ×5 → `topLayout()` / `hintLayout()`
- [x] Mnemonic regex/measure ×4 → `Mnemonic::hotKey()/visibleLength()`
- [x] Fill-extent boilerplate ×3 → `View::fillExtent()`
- [x] SubMenu→MenuItem lowering ×3 → `SubMenu::toMenuItem()`
- [x] Outline cycle guards ×2 inline copies → shared Floyd check
- [x] Terminal relayout-redraw ×4 → `relayout()`; retention check ×2 → `enforceWriteBudget()`
- [x] itemEnabled verbatim duplicate → hoisted to MenuView
- [x] Run-guard boilerplate → `Application::runningAsMain()` (20 of 22 files migrated)

### 🔧 Refactoring
- [x] `legacyKeyCode()` triple match → constant tables; altKey linear scan → name map
- [x] PictureValidator always-null `$terminator` param removed
- [x] Bare clipboard keycodes → `Key::Ctrl*`; literal `0x03` → `Key::CtrlC`
- [x] HelpViewer per-ref×row word-wrap → width-keyed layout cache
- [x] FQCN inline references → imports (Group, ListViewer)

### 📝 PHPDoc
- [x] Core hook contracts documented (pumpEvent/handleModalEvent/getEvent/run/executeDialog)
- [x] Contradictions fixed (doSputn contract restored by the fix itself; Driver::init wording; TextDevice write semantics)
- [x] Missing @throws added where behavior changed or review named them (Driver interface, Memo, RangeValidator)
- [x] Stale annotations removed (unused @phpstan-type; ListViewer divider entry marked reserved)

### 🧩 API additions — all landed
- [x] IntMath::clamp · Rect intersects/union/containsRect/inset/centeredIn/clampInto/fromSize/size/__toString
- [x] Point scale/negate/min/max/clampTo · SizeLimits value object (5 sites migrated)
- [x] CommandSet Countable+IteratorAggregate · SortedCollection search/remove
- [x] Group IteratorAggregate + windows() · Buffer row()/Countable/row-iteration
- [x] DrawBuffer putCell/cellAt/moveBuffer/__toString · View::localToGlobal
- [x] Cell blank/sentinel/with · Window fluent setters returning static
- [x] PictureNodeType enum · AnsiDriver::forStdio · injectable decoder clock
- [x] Application::runningAsMain · Support\Clipboard

## ⏸ Deferred (deliberate, with reasons)

- [ ] **Occlusion row-math consolidation** (`View.php` vs `Group.php`) — hottest render path; behavior is test-pinned but the merge deserves a dedicated pass with visual snapshots.
- [ ] **Desktop's unreachable tile branch** — removal needs the missing `mostEqualDivisors` regression test written first.
- [ ] **`focusNext()`/`focusPrevious()` alias removal** — public-API surface decision for the owner.
- [ ] **View::writeRowCells clip-context caching** — LOW perf item; O(rows×depth) recomputation stands until profiling justifies it.
- [ ] **Remaining one-off inline clamps** (MessageBox/Button/Frame single-use sites) — clamp there adds an import for no readability gain.
- [ ] **0x07 default-attr sweep to `Cell::blank()`** — named constructors exist; sweeping every draw call is cosmetic churn.
- [ ] **SearchOptions::PromptOnReplace honored interactively** — documented as reserved; real implementation wants a prompt-callback design discussion.
- [ ] **calendar.php / studio.php custom guards** — they pass constructor args; genuinely bespoke entry points, not boilerplate.

## Verification

```
composer test   # 756 passed, 1 skipped (real-terminal suite opt-in), per-commit green
composer stan   # PHPStan level max: No errors
composer fuzz   # seeded suites available; decoder/screen changes covered by regression tests above
```
