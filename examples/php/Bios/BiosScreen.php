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
 * Palette follows the iconic Phoenix-era setup utility: cyan title and legend
 * bands, a blue tab strip, a light-gray work surface, black rules, and blue values.
 */
final class BiosScreen extends View
{
    // ── Colours ──────────────────────────────────────────────────────────────
    private const int C_BORDER      = 0x70; // black on light gray
    private const int C_TITLE       = 0x30; // black on cyan
    private const int C_TAB_NORMAL  = 0x1F; // white on blue
    private const int C_TAB_ACTIVE  = 0x70; // black on light gray
    private const int C_DIVIDER     = 0x70;
    private const int C_LABEL       = 0x70;
    private const int C_VALUE       = 0x71; // blue on light gray
    private const int C_VALUE_INFO  = 0x70;
    private const int C_VALUE_ACT   = 0x74; // red on light gray
    private const int C_ROW_HL      = 0x1F; // white on blue
    private const int C_CURSOR      = 0x1E; // yellow on blue
    private const int C_HELP_HDR    = 0x70;
    private const int C_HELP        = 0x70;
    private const int C_LEGEND      = 0x3F; // white on cyan
    private const int C_STATUS      = 0x3E; // yellow on cyan
    private const int C_BG          = 0x70;

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

        if ($w <= 0 || $h <= 0) {
            return;
        }

        $this->fillRect(0, 0, $w, $h, ' ', self::C_BG);

        // Cyan title band, matching the classic centered firmware heading.
        $this->fillRect(0, 0, $w, 1, ' ', self::C_TITLE);
        $title = 'PhoenixBIOS Setup Utility';
        $this->drawCenteredStr(0, $w, $title, self::C_TITLE);

        // Blue navigation strip directly below the title.
        $this->drawTabBar(1, $w);

        // Gray work area enclosed by a thin black frame.
        $bodyTop = 2;
        $bodyBottom = max($bodyTop + 1, $h - 2);
        $this->drawBodyBorder($bodyTop, $bodyBottom, $w);
        $this->drawBody($bodyTop, $bodyBottom, $w);

        // Two cyan legend rows are a defining part of the original setup utility.
        $this->drawLegend(max(0, $h - 2), $w);
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

    private function drawBodyBorder(int $top, int $bottom, int $w): void
    {
        $topBuf = new DrawBuffer($w);
        $topBuf->moveChar(0, '─', self::C_BORDER, $w);
        $topBuf->moveStr(0, '┌', self::C_BORDER);
        $topBuf->moveStr(max(0, $w - 1), '┐', self::C_BORDER);
        $this->writeLine(0, $top, $w, 1, $topBuf);

        $botBuf = new DrawBuffer($w);
        $botBuf->moveChar(0, '─', self::C_BORDER, $w);
        $botBuf->moveStr(0, '└', self::C_BORDER);
        $botBuf->moveStr(max(0, $w - 1), '┘', self::C_BORDER);
        $this->writeLine(0, $bottom - 1, $w, 1, $botBuf);

        for ($y = $top + 1; $y < $bottom - 1; $y++) {
            $this->writeStr(0, $y, '│', self::C_BORDER);
            $this->writeStr(max(0, $w - 1), $y, '│', self::C_BORDER);
        }
    }

    private function drawCenteredStr(int $y, int $w, string $text, int $attr): void
    {
        $len = mb_strlen($text);
        $x = max(0, intdiv(max(0, $w - $len), 2));
        $this->writeStr($x, $y, $text, $attr);
    }

    private function drawTabBar(int $y, int $w): void
    {
        $b = new DrawBuffer($w);
        $b->moveChar(0, ' ', self::C_TAB_NORMAL, $w);

        $x = max(1, intdiv(max(0, $w - array_sum(array_map(
            static fn (BiosTab $tab): int => mb_strlen($tab->name) + 3,
            $this->tabs,
        ))), 2));
        foreach ($this->tabs as $i => $tab) {
            $label = ' ' . $tab->name . ' ';
            $attr  = ($i === $this->tab) ? self::C_TAB_ACTIVE : self::C_TAB_NORMAL;
            $b->moveStr($x, $label, $attr);
            $x += mb_strlen($label) + 1;
        }

        $this->writeLine(0, $y, $w, 1, $b);
    }

    /**
     * Draw the body: left field area + vertical divider + right help pane.
     * Left ~60% of inner width, right ~40%.
     */
    private function drawBody(int $top, int $bottom, int $w): void
    {
        $contentTop = $top + 1;
        $contentBottom = $bottom - 1;
        $innerW  = max(0, $w - 2);
        $leftW   = intval($innerW * 0.67);
        $divX    = $leftW + 1; // absolute col of vertical divider
        $rightX  = $divX + 1;
        $rightW  = $w - 1 - $rightX; // to border

        $currentTab = $this->tabs[$this->tab];
        $fields     = $currentTab->fields;

        $labelW  = intval($leftW * 0.45);
        $valueX  = $labelW + 2; // relative to col 1 (inside border)
        $valueW  = $leftW - $labelW - 1;

        // Draw vertical divider
        for ($y = $contentTop; $y < $contentBottom; $y++) {
            $this->writeStr($divX, $y, '│', self::C_DIVIDER);
        }

        if ($contentTop < $contentBottom) {
            $header = ' Item Specific Help ';
            $this->writeStr($rightX, $contentTop, mb_substr($header, 0, $rightW), self::C_HELP_HDR);
            if ($contentTop + 1 < $contentBottom) {
                $this->writeStr($rightX, $contentTop + 1, str_repeat('─', $rightW), self::C_DIVIDER);
            }
        }

        // Field rows
        foreach ($fields as $i => $field) {
            $rowY = $contentTop + 2 + $i;
            if ($rowY >= $contentBottom) {
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

        }

        $help = $this->currentField()->help . "\n\n"
            . '↑/↓ selects an item. Enter edits or expands it. '
            . '+/- changes values. ←/→ selects a menu.';
        $this->drawWrappedText($rightX + 1, $contentTop + 3, max(0, $rightW - 2), max(0, $contentBottom - $contentTop - 4), $help);
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
        $this->fillRect(0, $y, $w, min(2, $this->bounds->height() - $y), ' ', self::C_TITLE);

        if ($this->statusMsg !== '') {
            $this->writeStr(2, $y, mb_substr($this->statusMsg, 0, max(0, $w - 4)), self::C_STATUS);
        } else {
            $first = ' F1 Help    ↑↓ Select Item    -/+ Change Values      F9 Setup Defaults';
            $second = ' Esc Exit    ←→ Select Menu    Enter Select / Edit    F10 Save and Exit';
            $this->writeStr(0, $y, mb_substr($first, 0, $w), self::C_LEGEND);
            if ($y + 1 < $this->bounds->height()) {
                $this->writeStr(0, $y + 1, mb_substr($second, 0, $w), self::C_LEGEND);
            }
        }
    }

    private function drawWrappedText(int $x, int $y, int $width, int $height, string $text): void
    {
        if ($width <= 0 || $height <= 0) {
            return;
        }

        $lines = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $paragraph) {
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }
            foreach (explode("\n", wordwrap($paragraph, $width, "\n", true)) as $line) {
                $lines[] = $line;
            }
        }
        foreach (array_slice($lines, 0, $height) as $offset => $line) {
            $this->writeStr($x, $y + $offset, mb_substr($line, 0, $width), self::C_HELP);
        }
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
