<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyModifier;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Validators\Validator;
use HelgeSverre\TurboVision\Validators\ValidatorTransfer;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;
use InvalidArgumentException;
use Stringable;

/**
 * A faithful, Unicode-safe TInputLine-style single-line editor.
 *
 * Positions are grapheme offsets, while drawing uses the framework's one-cell
 * rendering model. The field reserves its first/last columns for scroll arrows.
 */
class InputLine extends View
{
    private const string LeftArrow = '‹';

    private const string RightArrow = '›';

    private static string $clipboard = '';

    private string $value = '';

    private ?int $anchor = null;

    /** Maximum editable graphemes (Turbo Vision constructor capacity minus NUL). */
    public private(set) int $maxLen;

    public private(set) int $curPos = 0;

    public private(set) int $firstPos = 0;

    public private(set) int $selStart = 0;

    public private(set) int $selEnd = 0;

    public function __construct(Rect $bounds, int $maxLen, private ?Validator $validator = null)
    {
        if ($maxLen < 1) {
            throw new InvalidArgumentException('InputLine capacity must be at least one (including its terminator).');
        }
        parent::__construct($bounds);
        $this->maxLen = $maxLen - 1;
        $this->state |= State::CursorVis;
        $this->options |= State::Selectable | State::FirstClick;
        $this->eventMask |= EventMask::Mouse;
    }

    public function getPalette(): Palette
    {
        // cpInputLine: normal, focused, selected, scroll indicator.
        return new Palette([1 => 0x13, 2 => 0x13, 3 => 0x14, 4 => 0x15]);
    }

    public function validator(): ?Validator
    {
        return $this->validator;
    }

    public function setValidator(?Validator $validator): void
    {
        $this->validator = $validator;
    }

    public function text(): string
    {
        return $this->value;
    }

    public function setText(string $text): void
    {
        $this->value = $this->truncate($text);
        $this->curPos = $this->length();
        $this->firstPos = 0;
        $this->clearSelection();
        $this->ensureCursorVisible();
        $this->drawView();
    }

    public static function clipboard(): string
    {
        return self::$clipboard;
    }

    public static function setClipboard(string $text): void
    {
        self::$clipboard = $text;
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        if ($width <= 0 || $this->bounds->height() <= 0) {
            return;
        }
        $color = $this->getState(State::Focused) ? $this->getColor(2) : $this->getColor(1);
        $buffer = new DrawBuffer($width);
        $buffer->moveChar(0, ' ', $color, $width);

        $visible = max(0, $width - 2);
        $buffer->moveStr(1, TerminalText::slice($this->value, $this->firstPos, $visible), $color);
        if ($this->canScroll(1)) {
            $buffer->moveChar($width - 1, self::RightArrow, $this->getColor(4), 1);
        }
        if ($this->canScroll(-1)) {
            $buffer->moveChar(0, self::LeftArrow, $this->getColor(4), 1);
        }

        if ($this->getState(State::Selected)) {
            $start = max(0, $this->selStart - $this->firstPos);
            $end = min($visible, $this->selEnd - $this->firstPos);
            for ($x = $start; $x < $end; $x++) {
                $buffer->putAttribute($x + 1, $this->getColor(3));
            }
        }

        $this->writeLine(0, 0, $width, $this->bounds->height(), $buffer);
        $this->setCursor(min($width - 1, max(0, $this->curPos - $this->firstPos + 1)), 0);
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::Command) {
            $command = $event->asMessage()?->command;
            if ($command !== null && $this->handleCommand($command)) {
                $event->clear();
            }

            return;
        }
        if ($event->what->inMask(EventType::MouseDown->value | EventType::MouseMove->value | EventType::MouseUp->value)) {
            if ($this->handleMouse($event)) {
                $event->clear();
            }

            return;
        }
        if ($event->what !== EventType::KeyDown || !$this->getState(State::Selected)) {
            return;
        }

        $key = $event->asKey();
        if ($key === null) {
            return;
        }
        if ($this->handleKey($key->keyCode, $key->char, $key->modifiers)) {
            $event->clear();
        }
    }

    public function selectAll(bool $enable = true): void
    {
        $this->selStart = 0;
        $this->curPos = $this->selEnd = $enable ? $this->length() : 0;
        $this->firstPos = max(0, $this->curPos - $this->visibleWidth());
        $this->anchor = $enable ? 0 : null;
        $this->drawView();
    }

    /** Ask the owning group to focus this field. */
    public function select(): void
    {
        if ($this->owner instanceof Group) {
            $this->owner->setCurrent($this);
        }
    }

    public function setState(int $flag, bool $enable): void
    {
        parent::setState($flag, $enable);
        if (($flag & State::Selected) !== 0) {
            $this->selectAll($enable);
        }
    }

    public function dataSize(): int
    {
        $value = null;
        $transferred = $this->validator?->transfer($this->value, $value, ValidatorTransfer::DataSize) ?? 0;

        return $transferred !== 0 ? $transferred : $this->maxLen + 1;
    }

    public function getData(): mixed
    {
        $value = null;
        if (($this->validator?->transfer($this->value, $value, ValidatorTransfer::GetData) ?? 0) !== 0) {
            return $value;
        }

        return $this->value;
    }

    public function setData(mixed $data): void
    {
        $text = $this->value;
        if (($this->validator?->transfer($text, $data, ValidatorTransfer::SetData) ?? 0) !== 0) {
            $this->value = $this->truncate($text);
        } else {
            $this->value = $this->truncate(match (true) {
                is_string($data) => $data,
                is_int($data), is_float($data), is_bool($data) => (string) $data,
                $data === null => '',
                $data instanceof Stringable => (string) $data,
                default => throw new InvalidArgumentException('InputLine data must be scalar or stringable.'),
            });
        }
        $this->selectAll();
    }

    public function valid(int $command = Cmd::Valid): bool
    {
        if ($this->validator === null) {
            return true;
        }
        if ($command === Cmd::Valid) {
            return $this->validator->status === Validator::StatusOk;
        }
        if ($command === Cmd::Cancel) {
            return true;
        }
        if (!$this->validator->validate($this->value)) {
            $this->select();

            return false;
        }

        return true;
    }

    private function handleCommand(int $command): bool
    {
        return match ($command) {
            Cmd::Copy => $this->copySelection(),
            Cmd::Cut => $this->cutSelection(),
            Cmd::Paste => $this->insertText(self::$clipboard),
            Cmd::Clear => $this->clearText(),
            default => false,
        };
    }

    private function handleKey(int $keyCode, string $character, int $modifiers): bool
    {
        // Terminals commonly emit raw ASCII controls for these Ctrl shortcuts.
        if ($keyCode === 1 || ($modifiers & KeyModifier::Ctrl) !== 0 && strtolower($character) === 'a') {
            $this->selectAll();

            return true;
        }
        if ($keyCode === 3 || ($modifiers & KeyModifier::Ctrl) !== 0 && strtolower($character) === 'c') {
            return $this->copySelection();
        }
        if ($keyCode === 22 || ($modifiers & KeyModifier::Ctrl) !== 0 && strtolower($character) === 'v') {
            return $this->insertText(self::$clipboard);
        }
        if ($keyCode === 24 || ($modifiers & KeyModifier::Ctrl) !== 0 && strtolower($character) === 'x') {
            return $this->cutSelection();
        }
        if ($keyCode === 25) {
            return $this->clearText();
        }

        $shift = ($modifiers & KeyModifier::Shift) !== 0;
        $old = $this->curPos;
        if (in_array($keyCode, [Key::Left->value, Key::Right->value, Key::Home->value, Key::End->value], true)) {
            if ($shift && $this->anchor === null) {
                $this->anchor = $this->curPos;
            }
            $this->curPos = match ($keyCode) {
                Key::Left->value => max(0, $this->curPos - 1),
                Key::Right->value => min($this->length(), $this->curPos + 1),
                Key::Home->value => 0,
                Key::End->value => $this->length(),
            };
            if ($shift) {
                $this->adjustSelection();
            } else {
                // Arrow navigation collapses an existing block towards its direction.
                if ($keyCode === Key::Left->value && $this->hasSelection()) {
                    $this->curPos = $this->selStart;
                } elseif ($keyCode === Key::Right->value && $this->hasSelection()) {
                    $this->curPos = $this->selEnd;
                }
                $this->clearSelection();
            }
            if ($old !== $this->curPos || $shift) {
                $this->ensureCursorVisible();
                $this->drawView();
            }

            return true;
        }
        if ($keyCode === Key::Insert->value) {
            $this->setState(State::CursorIns, !$this->getState(State::CursorIns));
            $this->drawView();

            return true;
        }
        if ($keyCode === Key::Backspace->value) {
            if ($this->hasSelection()) {
                if (!$this->deleteSelection()) {
                    return true;
                }
            } elseif ($this->curPos > 0) {
                if (!$this->tryReplace($this->curPos - 1, $this->curPos, '', $this->curPos - 1)) {
                    return true;
                }
            }
            $this->clearSelection();
            $this->ensureCursorVisible();
            $this->drawView();

            return true;
        }
        if ($keyCode === Key::Delete->value) {
            if ($this->hasSelection()) {
                if (!$this->deleteSelection()) {
                    return true;
                }
            } elseif ($this->curPos < $this->length()) {
                if (!$this->tryReplace($this->curPos, $this->curPos + 1, '', $this->curPos)) {
                    return true;
                }
            }
            $this->clearSelection();
            $this->ensureCursorVisible();
            $this->drawView();

            return true;
        }
        if ($character !== '' && !preg_match('/[\p{Cc}\p{Cf}]/u', $character)) {
            return $this->insertText($character);
        }

        return false;
    }

    private function handleMouse(Event $event): bool
    {
        $mouse = $event->asMouse();
        if ($mouse === null) {
            return false;
        }
        if ($event->what === EventType::MouseDown) {
            $local = $this->makeLocal($mouse->where);
            if ($local->x <= 0 && $this->canScroll(-1)) {
                $this->firstPos--;
                $this->drawView();

                return true;
            }
            if ($local->x >= $this->bounds->width() - 1 && $this->canScroll(1)) {
                $this->firstPos++;
                $this->drawView();

                return true;
            }
            $this->anchor = $this->mousePosition($mouse->where);
            $this->curPos = $this->anchor;
            $this->adjustSelection();
            $this->setState(State::Dragging, true);
            $this->drawView();

            return true;
        }
        if (!$this->getState(State::Dragging)) {
            return false;
        }
        if ($event->what === EventType::MouseMove) {
            $this->curPos = $this->mousePosition($mouse->where);
            $this->adjustSelection();
            $this->ensureCursorVisible();
            $this->drawView();

            return true;
        }
        if ($event->what === EventType::MouseUp) {
            $this->curPos = $this->mousePosition($mouse->where);
            $this->adjustSelection();
            $this->setState(State::Dragging, false);
            $this->ensureCursorVisible();
            $this->drawView();

            return true;
        }

        return false;
    }

    private function insertText(string $text): bool
    {
        $value = $this->value;
        $cursor = $this->curPos;
        $selectionStart = $this->selStart;
        $selectionEnd = $this->selEnd;
        $changed = false;
        foreach (TerminalText::graphemes($text) as $character) {
            $hasSelection = $selectionStart < $selectionEnd;
            $start = $hasSelection ? $selectionStart : $cursor;
            $end = $hasSelection ? $selectionEnd : $cursor;
            if (!$hasSelection && $this->getState(State::CursorIns) && $cursor < TerminalText::length($value)) {
                $end++;
            }
            $candidate = $this->replaceTextRange($value, $start, $end, $character);
            if (TerminalText::length($candidate) > $this->maxLen) {
                break;
            }
            if (!$this->isValidInput($candidate, true)) {
                continue;
            }
            $value = $candidate;
            $cursor = $start + TerminalText::length($character);
            $selectionStart = $selectionEnd = $cursor;
            $changed = true;
        }
        if ($changed) {
            $lengthBeforeFormatting = TerminalText::length($value);
            $cursorWasAtEnd = $cursor >= $lengthBeforeFormatting;
            $formatted = $value;
            if ($this->isValidInput($formatted, false)) {
                $value = $this->truncate($formatted);
                $cursor = $cursorWasAtEnd && TerminalText::length($value) > $lengthBeforeFormatting
                    ? TerminalText::length($value)
                    : min($cursor, TerminalText::length($value));
            }
            $this->value = $value;
            $this->curPos = $cursor;
            $this->clearSelection();
            $this->ensureCursorVisible();
            $this->drawView();
        }

        return true;
    }

    private function copySelection(): bool
    {
        if (!$this->hasSelection()) {
            return true;
        }
        self::$clipboard = TerminalText::slice($this->value, $this->selStart, $this->selEnd - $this->selStart);

        return true;
    }

    private function cutSelection(): bool
    {
        if ($this->hasSelection()) {
            $cut = TerminalText::slice($this->value, $this->selStart, $this->selEnd - $this->selStart);
            if (!$this->deleteSelection()) {
                return true;
            }
            self::$clipboard = $cut;
            $this->clearSelection();
            $this->ensureCursorVisible();
            $this->drawView();
        }

        return true;
    }

    private function clearText(): bool
    {
        if (!$this->tryReplace(0, $this->length(), '', 0)) {
            return true;
        }
        $this->firstPos = 0;
        $this->clearSelection();
        $this->drawView();

        return true;
    }

    private function isValidInput(string &$text, bool $suppressFill): bool
    {
        return $this->validator?->isValidInput($text, $suppressFill) ?? true;
    }

    private function deleteSelection(): bool
    {
        if (!$this->hasSelection()) {
            return true;
        }

        return $this->tryReplace($this->selStart, $this->selEnd, '', $this->selStart);
    }

    private function tryReplace(int $start, int $end, string $replacement, int $cursor): bool
    {
        $candidate = $this->replaceTextRange($this->value, $start, $end, $replacement);
        if (!$this->isValidInput($candidate, true)) {
            return false;
        }
        $this->value = $candidate;
        $this->curPos = min($cursor, $this->length());

        return true;
    }

    private function replaceTextRange(string $value, int $start, int $end, string $replacement): string
    {
        $parts = TerminalText::graphemes($value);
        array_splice($parts, $start, max(0, $end - $start), TerminalText::graphemes($replacement));

        return implode('', $parts);
    }

    private function truncate(string $text): string
    {
        return TerminalText::slice($text, 0, $this->maxLen);
    }

    private function length(): int
    {
        return TerminalText::length($this->value);
    }

    private function visibleWidth(): int
    {
        return max(0, $this->bounds->width() - 2);
    }

    private function canScroll(int $delta): bool
    {
        return $delta < 0
            ? $this->firstPos > 0
            : ($delta > 0 && $this->length() - $this->firstPos > $this->visibleWidth());
    }

    private function ensureCursorVisible(): void
    {
        if ($this->curPos < $this->firstPos) {
            $this->firstPos = $this->curPos;
        }
        $this->firstPos = max(0, min($this->firstPos, max(0, $this->curPos - $this->visibleWidth() + 1)));
    }

    private function hasSelection(): bool
    {
        return $this->selStart < $this->selEnd;
    }

    private function clearSelection(): void
    {
        $this->selStart = $this->selEnd = $this->curPos;
        $this->anchor = null;
    }

    private function adjustSelection(): void
    {
        if ($this->anchor === null) {
            $this->clearSelection();

            return;
        }
        $this->selStart = min($this->anchor, $this->curPos);
        $this->selEnd = max($this->anchor, $this->curPos);
    }

    private function mousePosition(\HelgeSverre\TurboVision\Geometry\Point $global): int
    {
        $local = $this->makeLocal($global);

        return min($this->length(), max(0, $local->x + $this->firstPos - 1));
    }
}
