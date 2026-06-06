<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Bios;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

/**
 * Full-screen BIOS Setup Utility view. Handles rendering and all keyboard input.
 *
 * Colour constants (attr byte = fg | bg<<4):
 *   0x00 = black-on-black   0x07 = lightgray-on-black  0x0F = white-on-black
 *   0x70 = black-on-lgray   0x1F = white-on-blue        0x03 = cyan-on-black
 *   0x0B = lt-cyan-on-black 0x30 = black-on-cyan
 */
final class BiosScreen extends View
{
    // ── Colours ──────────────────────────────────────────────────────────────
    private const int C_BORDER      = 0x07; // frame / title
    private const int C_TITLE       = 0x0F; // bright title text
    private const int C_TAB_NORMAL  = 0x07; // inactive tab
    private const int C_TAB_ACTIVE  = 0x70; // active tab
    private const int C_DIVIDER     = 0x07;
    private const int C_LABEL       = 0x07; // field label
    private const int C_VALUE       = 0x0F; // field value
    private const int C_VALUE_INFO  = 0x07; // Info field
    private const int C_VALUE_ACT   = 0x0E; // Action label (yellow)
    private const int C_ROW_HL      = 0x70; // highlighted row (current)
    private const int C_CURSOR      = 0x70; // edit cursor char
    private const int C_HELP_HDR    = 0x0F; // help pane header
    private const int C_HELP        = 0x03; // help text (cyan-on-black)
    private const int C_LEGEND      = 0x07; // bottom key legend
    private const int C_STATUS      = 0x0E; // status message (yellow)
    private const int C_BG          = 0x00; // background fill

    // ── Tab / field state ────────────────────────────────────────────────────
    private int $tab     = 0;
    private int $row     = 0;
    private bool $editing = false;
    private string $statusMsg = '';

    /** Snapshot taken when entering edit mode (for cancel/revert).
     * @var array{value:string,cursor:int,optionIndex:int}|null
     */
    private ?array $editSnap = null;

    /** @param list<BiosTab> $tabs */
    public function __construct(
        Rect $bounds,
        private readonly array $tabs,
    ) {
        parent::__construct($bounds);
        $this->options |= State::Selectable;
    }

    // ── Test accessors ────────────────────────────────────────────────────────

    /** The display name of the currently active tab. */
    public function tabName(): string
    {
        return $this->tabs[$this->tab]->name;
    }

    /** The currently highlighted field. */
    public function currentField(): BiosField
    {
        return $this->tabs[$this->tab]->fields[$this->row];
    }

    /** Whether the view is currently in text-edit mode. */
    public function isEditing(): bool
    {
        return $this->editing;
    }

    /** The current transient status message ('' if none). */
    public function statusMessage(): string
    {
        return $this->statusMsg;
    }

    // ── Drawing ───────────────────────────────────────────────────────────────

    public function draw(): void
    {
        $w = $this->bounds->width();
        $h = $this->bounds->height();

        // Fill background black
        $this->fillRect(0, 0, $w, $h, ' ', self::C_BG);

        // Outer border
        $this->drawBorder($w, $h);

        // Title line (row 1 inside border)
        $title = ' TurboVision BIOS Setup Utility ';
        $this->drawCenteredStr(0, $w, $title, self::C_TITLE);

        // Tab bar (row 2)
        $this->drawTabBar(2, $w);

        // Horizontal divider after tab bar (row 3)
        $this->drawHLine(3, $w);

        // Body rows: 4..h-3 (leave rows h-2 and h-1 for legend/status + border)
        $bodyTop    = 4;
        $bodyBottom = $h - 2; // exclusive: legend on h-2, border on h-1
        $this->drawBody($bodyTop, $bodyBottom, $w);

        // Legend / status row
        $this->drawLegend($h - 2, $w);
    }

    // ── Drawing helpers ───────────────────────────────────────────────────────

    private function fillRect(int $x, int $y, int $w, int $h, string $char, int $attr): void
    {
        $b = new DrawBuffer($w);
        $b->moveChar(0, $char, $attr, $w);
        for ($row = 0; $row < $h; $row++) {
            $this->writeLine($x, $y + $row, $w, 1, $b);
        }
    }

    private function drawBorder(int $w, int $h): void
    {
        // Top/bottom horizontal lines
        $topBuf = new DrawBuffer($w);
        $topBuf->moveChar(0, '─', self::C_BORDER, $w);
        $topBuf->moveStr(0, '┌', self::C_BORDER);
        $topBuf->moveStr($w - 1, '┐', self::C_BORDER);
        $this->writeLine(0, 0, $w, 1, $topBuf);

        $botBuf = new DrawBuffer($w);
        $botBuf->moveChar(0, '─', self::C_BORDER, $w);
        $botBuf->moveStr(0, '└', self::C_BORDER);
        $botBuf->moveStr($w - 1, '┘', self::C_BORDER);
        $this->writeLine(0, $h - 1, $w, 1, $botBuf);

        // Side borders
        $sideBuf = new DrawBuffer($w);
        $sideBuf->moveChar(0, ' ', self::C_BG, $w);
        $sideBuf->moveStr(0, '│', self::C_BORDER);
        $sideBuf->moveStr($w - 1, '│', self::C_BORDER);
        for ($y = 1; $y < $h - 1; $y++) {
            $this->writeLine(0, $y, $w, 1, $sideBuf);
        }
    }

    private function drawCenteredStr(int $y, int $w, string $text, int $attr): void
    {
        $len = mb_strlen($text);
        $x   = max(1, intval(($w - $len) / 2));
        $this->writeStr($x, $y, $text, $attr);
    }

    private function drawTabBar(int $y, int $w): void
    {
        $b = new DrawBuffer($w);
        $b->moveChar(0, ' ', self::C_TAB_NORMAL, $w);

        // Distribute tabs evenly; start at col 2 (inside border)
        $x = 2;
        foreach ($this->tabs as $i => $tab) {
            $label = ' ' . $tab->name . ' ';
            $attr  = ($i === $this->tab) ? self::C_TAB_ACTIVE : self::C_TAB_NORMAL;
            $b->moveStr($x, $label, $attr);
            $x += mb_strlen($label) + 1;
        }

        $this->writeLine(0, $y, $w, 1, $b);
    }

    private function drawHLine(int $y, int $w): void
    {
        $b = new DrawBuffer($w);
        $b->moveChar(0, '─', self::C_DIVIDER, $w);
        $b->moveStr(0, '├', self::C_DIVIDER);
        $b->moveStr($w - 1, '┤', self::C_DIVIDER);
        $this->writeLine(0, $y, $w, 1, $b);
    }

    /**
     * Draw the body: left field area + vertical divider + right help pane.
     * Left ~60% of inner width, right ~40%.
     */
    private function drawBody(int $top, int $bottom, int $w): void
    {
        $innerW  = $w - 2; // inside border cols
        $leftW   = intval($innerW * 0.60);
        $divX    = $leftW + 1; // absolute col of vertical divider
        $rightX  = $divX + 1;
        $rightW  = $w - 1 - $rightX; // to border

        $currentTab = $this->tabs[$this->tab];
        $fields     = $currentTab->fields;

        $labelW  = intval($leftW * 0.45);
        $valueX  = $labelW + 2; // relative to col 1 (inside border)
        $valueW  = $leftW - $labelW - 1;

        // Draw vertical divider
        for ($y = $top; $y < $bottom; $y++) {
            $this->writeStr($divX, $y, '│', self::C_DIVIDER);
        }

        // Help pane header
        if ($top < $bottom) {
            $this->writeStr($rightX, $top, 'Item Help', self::C_HELP_HDR);
        }

        // Field rows
        foreach ($fields as $i => $field) {
            $rowY = $top + 1 + $i; // +1 to leave help header row
            if ($rowY >= $bottom) {
                break;
            }

            $isCurrent = ($i === $this->row);
            $rowAttr   = $isCurrent ? self::C_ROW_HL : self::C_BG;

            // Fill full row background (inside border, up to divider)
            $rowBuf = new DrawBuffer($leftW + 1);
            $rowBuf->moveChar(0, ' ', $rowAttr, $leftW + 1);
            $this->writeLine(1, $rowY, $leftW, 1, $rowBuf);

            // Label
            $labelAttr = $isCurrent ? self::C_ROW_HL : self::C_LABEL;
            $labelText = mb_substr($field->label, 0, $labelW);
            $this->writeStr(1, $rowY, $labelText, $labelAttr);

            // Value
            $this->drawFieldValue($field, $isCurrent, $valueX, $rowY, $valueW);

            // Help text for current row
            if ($isCurrent && $rightW > 0) {
                $helpText = mb_substr($field->help, 0, $rightW);
                $this->writeStr($rightX, $rowY, $helpText, self::C_HELP);
            }
        }
    }

    private function drawFieldValue(BiosField $field, bool $isCurrent, int $x, int $y, int $w): void
    {
        $baseAttr = $isCurrent ? self::C_ROW_HL : self::C_VALUE;

        match ($field->kind) {
            FieldKind::Text, FieldKind::Number => $this->drawEditableValue(
                $field, $isCurrent, $x, $y, $w, $baseAttr
            ),
            FieldKind::Cycle  => $this->writeStr($x, $y, '‹ ' . $field->value . ' ›', $baseAttr),
            FieldKind::Info   => $this->writeStr($x, $y, $field->value, $isCurrent ? self::C_ROW_HL : self::C_VALUE_INFO),
            FieldKind::Action => $this->writeStr($x, $y, '[ ' . $field->label . ' ]', $isCurrent ? self::C_ROW_HL : self::C_VALUE_ACT),
        };
    }

    private function drawEditableValue(BiosField $field, bool $isCurrent, int $x, int $y, int $w, int $baseAttr): void
    {
        $editing = $isCurrent && $this->editing;
        $value   = $field->value;
        $prefix  = '[ ';
        $suffix  = ' ]';
        $innerW  = max(0, $w - mb_strlen($prefix) - mb_strlen($suffix));
        $display = mb_substr($value, 0, $innerW);

        $this->writeStr($x, $y, $prefix, $baseAttr);
        $px = $x + mb_strlen($prefix);

        if ($editing) {
            $cursor = $field->cursorPos();
            $before = mb_substr($display, 0, $cursor);
            $atCur  = mb_substr($display, $cursor, 1);
            $after  = mb_substr($display, $cursor + 1);

            $this->writeStr($px, $y, $before, self::C_VALUE);
            $cx = $px + mb_strlen($before);

            if ($atCur !== '') {
                $this->writeStr($cx, $y, $atCur, self::C_CURSOR);
                $cx++;
            } else {
                // Cursor is past end: show underscore cursor
                $this->writeStr($cx, $y, '_', self::C_CURSOR);
                $cx++;
            }

            $this->writeStr($cx, $y, $after, self::C_VALUE);
        } else {
            $this->writeStr($px, $y, $display, $baseAttr);
        }

        $afterDisplay = $px + mb_strlen($display) + ($editing ? 1 : 0);
        $this->writeStr($afterDisplay, $y, $suffix, $baseAttr);
    }

    private function drawLegend(int $y, int $w): void
    {
        $b = new DrawBuffer($w);
        $b->moveChar(0, ' ', self::C_BG, $w);

        if ($this->statusMsg !== '') {
            $text = ' ' . $this->statusMsg;
            $b->moveStr(1, $text, self::C_STATUS);
        } else {
            $legend = ' ↑↓ Select   ←→ Tab   ↵ Edit   +/- Change   F10 Save   Esc Exit';
            $b->moveStr(0, mb_substr($legend, 0, $w - 2), self::C_LEGEND);
        }

        $this->writeLine(0, $y, $w, 1, $b);
    }

    // ── Event handling ────────────────────────────────────────────────────────

    public function handleEvent(Event $event): void
    {
        if ($event->what !== EventType::KeyDown) {
            return;
        }

        $key = $event->asKey();
        if ($key === null) {
            return;
        }

        $field = $this->currentFieldMut();

        // ── While editing a text/number field ────────────────────────────────
        if ($this->editing && $field->editable()) {
            if ($key->is(Key::Left)) {
                $field->moveCursor(-1);
                $this->clearEvent($event);

                return;
            }

            if ($key->is(Key::Right)) {
                $field->moveCursor(1);
                $this->clearEvent($event);

                return;
            }

            if ($key->is(Key::Backspace)) {
                $field->backspace();
                $this->clearEvent($event);

                return;
            }

            if ($key->is(Key::Enter)) {
                // Commit edit
                $this->editing   = false;
                $this->editSnap  = null;
                $this->statusMsg = '';
                $this->clearEvent($event);

                return;
            }

            if ($key->is(Key::Esc)) {
                // Cancel / revert
                if ($this->editSnap !== null) {
                    $field->restore($this->editSnap);
                }

                $this->editing   = false;
                $this->editSnap  = null;
                $this->clearEvent($event);

                return;
            }

            // Printable char
            if ($key->char !== '') {
                $field->insertChar($key->char);
                $this->clearEvent($event);

                return;
            }

            return;
        }

        // ── Navigation (not in edit mode) ─────────────────────────────────────

        if ($key->is(Key::Esc)) {
            $this->putEventUp(Event::command(Cmd::Quit));
            $this->clearEvent($event);

            return;
        }

        if ($key->is(Key::F10)) {
            $this->statusMsg = 'Settings saved.';
            $this->clearEvent($event);

            return;
        }

        if ($key->is(Key::Up)) {
            $this->moveRow(-1);
            $this->clearEvent($event);

            return;
        }

        if ($key->is(Key::Down)) {
            $this->moveRow(1);
            $this->clearEvent($event);

            return;
        }

        if ($key->is(Key::Left)) {
            $this->switchTab(-1);
            $this->clearEvent($event);

            return;
        }

        if ($key->is(Key::Right)) {
            $this->switchTab(1);
            $this->clearEvent($event);

            return;
        }

        if ($key->is(Key::Enter)) {
            $this->activateField($field);
            $this->clearEvent($event);

            return;
        }

        // +/- for Cycle
        if ($key->char === '+') {
            if ($field->kind === FieldKind::Cycle) {
                $field->cycle(1);
            }

            $this->clearEvent($event);

            return;
        }

        if ($key->char === '-') {
            if ($field->kind === FieldKind::Cycle) {
                $field->cycle(-1);
            }

            $this->clearEvent($event);

            return;
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Walk up the owner chain to the nearest Group and queue an event there.
     * Needed because View does not have putEvent(); only Group does.
     */
    private function putEventUp(Event $event): void
    {
        $node = $this->owner;
        while ($node !== null) {
            if ($node instanceof Group) {
                $node->putEvent($event);

                return;
            }

            $node = $node->owner;
        }
    }

    /** Returns the mutable current field (same object reference as currentField()). */
    private function currentFieldMut(): BiosField
    {
        return $this->tabs[$this->tab]->fields[$this->row];
    }

    private function moveRow(int $delta): void
    {
        $count = count($this->tabs[$this->tab]->fields);
        if ($count === 0) {
            return;
        }

        $this->row = max(0, min($count - 1, $this->row + $delta));
    }

    private function switchTab(int $delta): void
    {
        $n         = count($this->tabs);
        $this->tab = (($this->tab + $delta) % $n + $n) % $n;
        $this->row = 0;
        $this->editing  = false;
        $this->editSnap = null;
        $this->statusMsg = '';
    }

    private function activateField(BiosField $field): void
    {
        switch ($field->kind) {
            case FieldKind::Text:
            case FieldKind::Number:
                if (! $this->editing) {
                    $this->editSnap = $field->snapshot();
                    $field->setCursorToEnd();
                    $this->editing = true;
                } else {
                    $this->editing  = false;
                    $this->editSnap = null;
                }

                break;

            case FieldKind::Cycle:
                $field->cycle(1);
                break;

            case FieldKind::Action:
                if ($field->onActivate !== null) {
                    ($field->onActivate)();
                }

                break;

            case FieldKind::Info:
                // nothing
                break;
        }
    }
}
