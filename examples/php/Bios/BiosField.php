<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Bios;

use Closure;

/**
 * A single editable / display item in a BIOS tab.
 *
 * Public properties are intentionally mutable so BiosScreen can update them
 * directly as the user types or cycles values.
 */
final class BiosField
{
    /** Internal edit cursor position (byte index into $value, in characters). */
    private int $cursor = 0;

    /**
     * @param list<string> $options     Cycle option list (empty for non-Cycle fields).
     * @param int          $optionIndex Current cycle index.
     * @param string       $help        Right-pane help text.
     * @param Closure|null $onActivate  Callback for Action fields.
     */
    public function __construct(
        public string $label,
        public FieldKind $kind,
        public string $value = '',
        public array $options = [],
        public int $optionIndex = 0,
        public string $help = '',
        public ?Closure $onActivate = null,
    ) {}

    // ── Editability ──────────────────────────────────────────────────────────

    public function editable(): bool
    {
        return $this->kind === FieldKind::Text || $this->kind === FieldKind::Number;
    }

    // ── Cursor management ────────────────────────────────────────────────────

    public function cursorPos(): int
    {
        return $this->cursor;
    }

    public function setCursor(int $pos): void
    {
        $len = mb_strlen($this->value);
        $this->cursor = max(0, min($len, $pos));
    }

    public function setCursorToEnd(): void
    {
        $this->cursor = mb_strlen($this->value);
    }

    public function moveCursor(int $delta): void
    {
        $this->setCursor($this->cursor + $delta);
    }

    // ── Cycle ────────────────────────────────────────────────────────────────

    public function cycle(int $dir): void
    {
        if ($this->kind !== FieldKind::Cycle || $this->options === []) {
            return;
        }

        $n = count($this->options);
        $this->optionIndex = (($this->optionIndex + $dir) % $n + $n) % $n;
        $this->value = $this->options[$this->optionIndex];
    }

    // ── Text / Number editing ────────────────────────────────────────────────

    public function insertChar(string $char): void
    {
        if (! $this->editable()) {
            return;
        }

        if ($this->kind === FieldKind::Number && ! ctype_digit($char)) {
            return;
        }

        $before = mb_substr($this->value, 0, $this->cursor);
        $after  = mb_substr($this->value, $this->cursor);
        $this->value  = $before . $char . $after;
        $this->cursor++;
    }

    public function backspace(): void
    {
        if (! $this->editable() || $this->cursor === 0) {
            return;
        }

        $before = mb_substr($this->value, 0, $this->cursor - 1);
        $after  = mb_substr($this->value, $this->cursor);
        $this->value  = $before . $after;
        $this->cursor--;
    }

    // ── Snapshot / restore (for edit-cancel) ─────────────────────────────────

    /** @return array{value:string,cursor:int,optionIndex:int} */
    public function snapshot(): array
    {
        return [
            'value'       => $this->value,
            'cursor'      => $this->cursor,
            'optionIndex' => $this->optionIndex,
        ];
    }

    /** @param array{value:string,cursor:int,optionIndex:int} $snap */
    public function restore(array $snap): void
    {
        $this->value       = $snap['value'];
        $this->cursor      = $snap['cursor'];
        $this->optionIndex = $snap['optionIndex'];
    }
}
