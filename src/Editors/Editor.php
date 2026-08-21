<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Support\Clipboard;
use HelgeSverre\TurboVision\Events\KeyModifier;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;
use Closure;

/**
 * A reusable UTF-8 text editor, modelled after TEditor but with grapheme
 * positions instead of unsafe byte offsets. It uses a gap buffer, supports
 * selection, clipboard, undo, scrolling, search/replace and the standard
 * editor command set.
 */
class Editor extends View
{
    /** cpEditor: normal, selected, active selection, syntax/error spare. */
    private const string PALETTE = "\x1A\x1B\x1C\x1D";

    private const int TAB_WIDTH = 4;

    private const int MAX_UNDO = 100;

    /** Bound retained edit payloads so a large document cannot exhaust memory. */
    private const int MAX_UNDO_BYTES = 2_000_000;

    protected EditorBuffer $buffer;

    /** Absolute grapheme offset of the insertion point. */
    public int $curPtr = 0;

    public int $selStart = 0;

    public int $selEnd = 0;

    public bool $modified = false;

    public bool $overwrite = false;

    public bool $autoIndent = false;

    public bool $selecting = false;

    /** Horizontal and vertical scroll origins, in display columns and lines. */
    public int $deltaX = 0;

    public int $deltaY = 0;

    public string $findStr = '';

    public string $replaceStr = '';

    public int $editorFlags = SearchOptions::CaseSensitive;

    /** @var list<EditorUndoRecord> */
    private array $undoStack = [];

    /** @var list<EditorUndoRecord> */
    private array $redoStack = [];

    private int $undoBytes = 0;

    private int $redoBytes = 0;

    /** Version of EditorBuffer for which these document metrics were built. */
    private int $metricsVersion = -1;

    /** @var list<array{0:int,1:int}> Grapheme start/end (excluding newline) for each line. */
    private array $cachedLineRanges = [];

    private int $cachedWidestLine = 0;

    private int $metricBuildCount = 0;

    /** @var null|Closure(EditorDialogRequest):int */
    private ?Closure $dialogHandler = null;

    public ?EditorDialogRequest $lastDialog = null;

    public function __construct(
        Rect $bounds,
        protected ?ScrollBar $hScrollBar = null,
        protected ?ScrollBar $vScrollBar = null,
        protected ?Indicator $indicator = null,
        string $text = '',
    ) {
        parent::__construct($bounds);
        $this->buffer = new EditorBuffer($text);
        $this->curPtr = $this->buffer->length();
        $this->selStart = $this->curPtr;
        $this->selEnd = $this->curPtr;
        $this->options |= State::Selectable | State::FirstClick;
        $this->growMode = State::GrowHiX | State::GrowHiY;
        $this->syncChrome();
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    public function text(): string
    {
        return $this->buffer->text();
    }

    public function length(): int
    {
        return $this->buffer->length();
    }

    public function setText(string $text, bool $modified = false): void
    {
        $this->buffer->setText($text);
        $this->curPtr = $this->buffer->length();
        $this->selStart = $this->curPtr;
        $this->selEnd = $this->curPtr;
        $this->modified = $modified;
        $this->undoStack = [];
        $this->redoStack = [];
        $this->undoBytes = 0;
        $this->redoBytes = 0;
        $this->trackCursor();
        $this->syncChrome();
        $this->drawView();
    }

    public function hasSelection(): bool
    {
        return $this->selStart !== $this->selEnd;
    }

    public function undoDepth(): int
    {
        return count($this->undoStack);
    }

    public function undoByteSize(): int
    {
        return $this->undoBytes;
    }

    /** Exposes bounded history usage for status UIs and diagnostics. */
    public function undoByteBudget(): int
    {
        return self::MAX_UNDO_BYTES;
    }

    /** Number of document-metric rebuilds; unchanged while content is unchanged. */
    public function metricBuilds(): int
    {
        return $this->metricBuildCount;
    }

    public function selectedText(): string
    {
        [$start, $end] = $this->selectionRange();

        return $this->buffer->slice($start, $end - $start);
    }

    public function setSelect(int $start, int $end, bool $cursorAtStart = false): void
    {
        $limit = $this->length();
        $this->selStart = max(0, min($limit, $start));
        $this->selEnd = max(0, min($limit, $end));
        $this->curPtr = $cursorAtStart ? $this->selStart : $this->selEnd;
        $this->buffer->moveTo($this->curPtr);
        $this->trackCursor();
        $this->syncChrome();
        $this->drawView();
    }

    public function selectAll(): void
    {
        $this->setSelect(0, $this->length(), false);
    }

    public function hideSelect(): void
    {
        $this->selecting = false;
        $this->setSelect($this->curPtr, $this->curPtr);
    }

    public static function clipboard(): string
    {
        return Clipboard::get();
    }

    public static function setClipboard(string $text): void
    {
        Clipboard::set($text);
    }

    public function copy(): bool
    {
        if (! $this->hasSelection()) {
            return false;
        }
        Clipboard::set($this->selectedText());

        return true;
    }

    public function cut(): bool
    {
        if (! $this->copy()) {
            return false;
        }
        $this->deleteSelect();

        return true;
    }

    public function paste(): bool
    {
        if (Clipboard::get() === '') {
            return false;
        }

        return $this->insertText(Clipboard::get());
    }

    public function insertText(string $text, bool $selectText = false): bool
    {
        if ($text === '') {
            return true;
        }

        $before = $this->stateSnapshot();
        [$start, $end] = $this->hasSelection()
            ? $this->selectionRange()
            : [$this->curPtr, $this->curPtr];
        if ($start === $end && $this->overwrite && ! str_contains($text, "\n")) {
            $end = min($this->length(), $start + TerminalText::length($text));
        }
        $removed = $this->buffer->slice($start, $end - $start);
        $this->buffer->moveTo($start);
        $this->buffer->deleteForward($end - $start);
        $inserted = $this->buffer->insert($text);
        $this->curPtr = $start + $inserted;
        if ($selectText) {
            $this->selStart = $start;
            $this->selEnd = $this->curPtr;
        } else {
            $this->selStart = $this->curPtr;
            $this->selEnd = $this->curPtr;
        }
        $this->modified = true;
        $this->recordEdit($start, $removed, $end - $start, $text, $inserted, $before);
        $this->afterMutation();

        return true;
    }

    public function deleteSelect(): bool
    {
        if (! $this->hasSelection()) {
            return false;
        }
        [$start, $end] = $this->selectionRange();

        return $this->deleteRange($start, $end);
    }

    public function deleteRange(int $start, int $end): bool
    {
        $start = max(0, min($this->length(), $start));
        $end = max($start, min($this->length(), $end));
        if ($start === $end) {
            return false;
        }
        $before = $this->stateSnapshot();
        $removed = $this->buffer->slice($start, $end - $start);
        $this->buffer->moveTo($start);
        $this->buffer->deleteForward($end - $start);
        $this->curPtr = $start;
        $this->selStart = $start;
        $this->selEnd = $start;
        $this->modified = true;
        $this->recordEdit($start, $removed, $end - $start, '', 0, $before);
        $this->afterMutation();

        return true;
    }

    public function undo(): bool
    {
        $record = array_pop($this->undoStack);
        if ($record === null) {
            return false;
        }
        $this->undoBytes -= $record->retainedBytes();
        $this->applyUndoRecord($record, undo: true);
        $this->retainRecord($this->redoStack, $this->redoBytes, $record);

        return true;
    }

    public function redo(): bool
    {
        $record = array_pop($this->redoStack);
        if ($record === null) {
            return false;
        }
        $this->redoBytes -= $record->retainedBytes();
        $this->applyUndoRecord($record, undo: false);
        $this->retainRecord($this->undoStack, $this->undoBytes, $record);

        return true;
    }

    /** Search from the cursor and select the next match. */
    public function search(string $needle, ?int $options = null, bool $wrap = true): bool
    {
        if ($needle === '') {
            return false;
        }
        $options ??= $this->editorFlags;
        $haystack = TerminalText::graphemes($this->text());
        $query = TerminalText::graphemes($needle);
        if ($query === [] || count($query) > count($haystack)) {
            return false;
        }

        $starts = [$this->curPtr];
        if ($wrap && $this->curPtr > 0) {
            $starts[] = 0;
        }
        foreach ($starts as $from) {
            $last = count($haystack) - count($query);
            for ($at = $from; $at <= $last; $at++) {
                if ($at === $this->curPtr && $this->hasSelection()) {
                    $at++;
                }
                if ($at > $last || ! $this->matchesAt($haystack, $query, $at, $options)) {
                    continue;
                }
                if (($options & SearchOptions::WholeWordsOnly) !== 0 && ! $this->isWholeWordMatch($haystack, $at, count($query))) {
                    continue;
                }
                $this->setSelect($at, $at + count($query), false);

                return true;
            }
        }

        return false;
    }

    public function searchAgain(): bool
    {
        return $this->search($this->findStr, $this->editorFlags);
    }

    public function find(FindRequest $request): bool
    {
        $this->findStr = $request->find;
        $this->editorFlags = $request->options;

        return $this->search($request->find, $request->options, $request->wrap);
    }

    /** Replace every matching occurrence and return the number changed. */
    public function replaceAll(string $find, string $replace, ?int $options = null): int
    {
        if ($find === '') {
            return 0;
        }
        $options ??= $this->editorFlags;
        $haystack = TerminalText::graphemes($this->text());
        $query = TerminalText::graphemes($find);
        $replacement = TerminalText::graphemes($replace);
        $out = [];
        $changed = 0;
        for ($at = 0, $len = count($haystack), $qLen = count($query); $at < $len;) {
            if ($at <= $len - $qLen
                && $this->matchesAt($haystack, $query, $at, $options)
                && ((($options & SearchOptions::WholeWordsOnly) === 0) || $this->isWholeWordMatch($haystack, $at, $qLen))
            ) {
                array_push($out, ...$replacement);
                $at += $qLen;
                $changed++;
            } else {
                $out[] = $haystack[$at++];
            }
        }
        if ($changed === 0) {
            return 0;
        }
        $before = $this->stateSnapshot();
        $replacementText = implode('', $out);
        $this->buffer->setText($replacementText);
        $this->curPtr = min($this->curPtr, $this->length());
        $this->selStart = $this->curPtr;
        $this->selEnd = $this->curPtr;
        $this->modified = true;
        // Replace-all is deliberately one reversible range. It is bounded by
        // retained bytes like every other record, rather than creating N snapshots.
        $this->recordEdit(0, implode('', $haystack), count($haystack), $replacementText, count($out), $before);
        $this->afterMutation();

        return $changed;
    }

    public function replace(ReplaceRequest $request): int
    {
        $this->findStr = $request->find;
        $this->replaceStr = $request->replace;
        $this->editorFlags = $request->options;

        return $this->replaceAll($request->find, $request->replace, $request->options);
    }

    /** @param null|Closure(EditorDialogRequest):int $handler */
    public function setDialogHandler(?Closure $handler): void
    {
        $this->dialogHandler = $handler;
    }

    /**
     * Let the application turn a framework-level failure/prompt into its dialog UI.
     * The default is cmCancel, matching defEditorDialog in the original library.
     *
     * @param array<string, scalar|null> $context
     */
    protected function notify(EditorDialogKind $kind, array $context = []): int
    {
        $request = new EditorDialogRequest($kind, $context);
        $this->lastDialog = $request;

        return $this->dialogHandler !== null ? ($this->dialogHandler)($request) : Cmd::Cancel;
    }

    public function scrollTo(int $x, int $y): void
    {
        $this->deltaX = max(0, $x);
        $this->deltaY = max(0, min($this->lineCount() - 1, $y));
        $this->syncChrome();
        $this->drawView();
    }

    /** @return Point zero-based display column/line for a grapheme offset. */
    public function positionOf(int $pointer): Point
    {
        $pointer = max(0, min($this->length(), $pointer));
        $line = $this->lineIndexForPointer($pointer);
        [$start] = $this->lineRanges()[$line];
        $column = $this->displayColumn($start, $pointer);

        return new Point($column, $line);
    }

    public function pointerAt(int $line, int $column): int
    {
        $ranges = $this->lineRanges();
        $line = max(0, min(count($ranges) - 1, $line));
        $column = max(0, $column);
        [$pointer, $end] = $ranges[$line];
        $display = 0;
        while ($pointer < $end) {
            $ch = $this->buffer->graphemeAt($pointer);
            $next = $ch === "\t" ? $display + self::TAB_WIDTH - ($display % self::TAB_WIDTH) : $display + 1;
            if ($next > $column) {
                break;
            }
            $display = $next;
            $pointer++;
        }

        return $pointer;
    }

    public function lineStart(int $pointer): int
    {
        $pointer = max(0, min($this->length(), $pointer));

        return $this->lineRanges()[$this->lineIndexForPointer($pointer)][0];
    }

    public function lineEnd(int $pointer): int
    {
        $pointer = max(0, min($this->length(), $pointer));
        return $this->lineRanges()[$this->lineIndexForPointer($pointer)][1];
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::KeyDown) {
            $this->handleKey($event);

            return;
        }
        if ($event->what === EventType::MouseDown) {
            $mouse = $event->asMouse();
            if ($mouse !== null) {
                $local = $this->makeLocal($mouse->where);
                $this->setSelect($this->pointerAt($this->deltaY + $local->y, $this->deltaX + $local->x), $this->pointerAt($this->deltaY + $local->y, $this->deltaX + $local->x));
                $this->clearEvent($event);
            }

            return;
        }
        if ($event->what !== EventType::Command) {
            return;
        }
        $command = $event->asMessage()?->command;
        if ($command === null || ! $this->handleCommand($command)) {
            return;
        }
        $this->clearEvent($event);
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $normal = $this->getColor(1);
        $selection = $this->getColor($this->getState(State::Selected) ? 3 : 2);
        $lines = $this->lineRanges();
        for ($row = 0; $row < $height; $row++) {
            $b = new DrawBuffer($width);
            $b->moveChar(0, ' ', $normal, $width);
            $lineIndex = $this->deltaY + $row;
            if (isset($lines[$lineIndex])) {
                [$start, $end] = $lines[$lineIndex];
                $column = 0;
                for ($p = $start; $p < $end; $p++) {
                    $ch = $this->buffer->graphemeAt($p);
                    if ($ch === "\t") {
                        $until = $column + self::TAB_WIDTH - ($column % self::TAB_WIDTH);
                        for (; $column < $until; $column++) {
                            $x = $column - $this->deltaX;
                            if ($x >= 0 && $x < $width) {
                                $b->moveChar($x, ' ', $this->inSelection($p) ? $selection : $normal, 1);
                            }
                        }
                        continue;
                    }
                    $x = $column - $this->deltaX;
                    if ($x >= 0 && $x < $width) {
                        $b->moveStr($x, $ch, $this->inSelection($p) ? $selection : $normal);
                    }
                    $column++;
                }
            }
            $this->writeLine(0, $row, $width, 1, $b);
        }
        $this->placeCursor();
    }

    protected function handleCommand(int $command): bool
    {
        return match ($command) {
            Cmd::Undo => $this->undo(),
            Cmd::Cut => $this->cut(),
            Cmd::Copy => $this->copy(),
            Cmd::Paste => $this->paste(),
            Cmd::Clear => $this->deleteSelect(),
            Cmd::Find, Cmd::SearchAgain => $this->searchAgain(),
            Cmd::Replace => $this->replaceAll($this->findStr, $this->replaceStr, $this->editorFlags) > 0,
            Cmd::CharLeft => $this->moveCursor($this->curPtr - 1),
            Cmd::CharRight => $this->moveCursor($this->curPtr + 1),
            Cmd::WordLeft => $this->moveCursor($this->previousWord($this->curPtr)),
            Cmd::WordRight => $this->moveCursor($this->nextWord($this->curPtr)),
            Cmd::LineStart => $this->moveCursor($this->lineStart($this->curPtr)),
            Cmd::LineEnd => $this->moveCursor($this->lineEnd($this->curPtr)),
            Cmd::LineUp => $this->moveVertically(-1),
            Cmd::LineDown => $this->moveVertically(1),
            Cmd::PageUp => $this->moveVertically(-max(1, $this->bounds->height())),
            Cmd::PageDown => $this->moveVertically(max(1, $this->bounds->height())),
            Cmd::TextStart => $this->moveCursor(0),
            Cmd::TextEnd => $this->moveCursor($this->length()),
            Cmd::NewLine => $this->newLine(),
            Cmd::BackSpace => $this->backspace(),
            Cmd::DelChar => $this->deleteForward(),
            Cmd::DelWord => $this->deleteTo($this->nextWord($this->curPtr)),
            Cmd::DelStart => $this->deleteTo($this->lineStart($this->curPtr)),
            Cmd::DelEnd => $this->deleteTo($this->lineEnd($this->curPtr)),
            Cmd::DelLine => $this->deleteLine(),
            Cmd::InsMode => $this->toggleInsMode(),
            Cmd::StartSelect => $this->startSelect(),
            Cmd::HideSelect => $this->hideSelectAction(),
            default => false,
        };
    }

    protected function newLine(): bool
    {
        $indent = '';
        if ($this->autoIndent) {
            $line = $this->buffer->slice($this->lineStart($this->curPtr), $this->curPtr - $this->lineStart($this->curPtr));
            preg_match('/^[\t ]*/', $line, $match);
            $indent = $match[0] ?? '';
        }

        return $this->insertText("\n" . $indent);
    }

    protected function backspace(): bool
    {
        if ($this->hasSelection()) {
            return $this->deleteSelect();
        }
        if ($this->curPtr === 0) {
            return false;
        }
        return $this->deleteRange($this->curPtr - 1, $this->curPtr);
    }

    protected function deleteForward(): bool
    {
        if ($this->hasSelection()) {
            return $this->deleteSelect();
        }
        return $this->deleteRange($this->curPtr, $this->curPtr + 1);
    }

    protected function deleteTo(int $pointer): bool
    {
        return $pointer < $this->curPtr
            ? $this->deleteRange($pointer, $this->curPtr)
            : $this->deleteRange($this->curPtr, $pointer);
    }

    protected function deleteLine(): bool
    {
        $start = $this->lineStart($this->curPtr);
        $end = $this->lineEnd($this->curPtr);
        if ($end < $this->length()) {
            $end++;
        }

        return $this->deleteRange($start, $end);
    }

    protected function toggleInsMode(): bool
    {
        $this->overwrite = ! $this->overwrite;
        $this->setState(State::CursorIns, ! $this->overwrite);

        return true;
    }

    protected function startSelect(): bool
    {
        $this->selecting = true;
        $this->selStart = $this->curPtr;
        $this->selEnd = $this->curPtr;

        return true;
    }

    protected function moveCursor(int $pointer, bool $extend = false): bool
    {
        $pointer = max(0, min($this->length(), $pointer));
        if ($pointer === $this->curPtr && ! $extend && ! $this->hasSelection()) {
            return false;
        }
        if ($extend || $this->selecting) {
            if (! $this->hasSelection()) {
                $this->selStart = $this->curPtr;
            }
            $this->selEnd = $pointer;
        } else {
            $this->selStart = $pointer;
            $this->selEnd = $pointer;
        }
        $this->curPtr = $pointer;
        $this->buffer->moveTo($pointer);
        $this->trackCursor();
        $this->syncChrome();
        $this->drawView();

        return true;
    }

    protected function moveVertically(int $lines, bool $extend = false): bool
    {
        $pos = $this->positionOf($this->curPtr);

        return $this->moveCursor($this->pointerAt($pos->y + $lines, $pos->x), $extend);
    }

    protected function trackCursor(bool $center = false): void
    {
        $position = $this->positionOf($this->curPtr);
        $width = max(1, $this->bounds->width());
        $height = max(1, $this->bounds->height());
        if ($position->x < $this->deltaX) {
            $this->deltaX = $position->x;
        } elseif ($position->x >= $this->deltaX + $width) {
            $this->deltaX = max(0, $position->x - $width + 1);
        }
        if ($center) {
            $this->deltaY = max(0, $position->y - intdiv($height, 2));
        } elseif ($position->y < $this->deltaY) {
            $this->deltaY = $position->y;
        } elseif ($position->y >= $this->deltaY + $height) {
            $this->deltaY = max(0, $position->y - $height + 1);
        }
    }

    private function handleKey(Event $event): void
    {
        $key = $event->asKey();
        if ($key === null) {
            return;
        }
        $code = $key->keyCode;
        $extend = ($key->modifiers & KeyModifier::Shift) !== 0;
        $handled = match ($code) {
            Key::Left->value => $this->moveCursor($this->curPtr - 1, $extend),
            Key::Right->value => $this->moveCursor($this->curPtr + 1, $extend),
            Key::Home->value => $this->moveCursor($this->lineStart($this->curPtr), $extend),
            Key::End->value => $this->moveCursor($this->lineEnd($this->curPtr), $extend),
            Key::Up->value => $this->moveVertically(-1, $extend),
            Key::Down->value => $this->moveVertically(1, $extend),
            Key::PageUp->value => $this->moveVertically(-max(1, $this->bounds->height()), $extend),
            Key::PageDown->value => $this->moveVertically(max(1, $this->bounds->height()), $extend),
            Key::Backspace->value => $this->backspace(),
            Key::Delete->value => $this->deleteForward(),
            Key::Insert->value => $this->toggleInsMode(),
            Key::Enter->value => $this->newLine(),
            Key::CtrlLeft->value => $this->moveCursor($this->previousWord($this->curPtr), $extend),
            Key::CtrlRight->value => $this->moveCursor($this->nextWord($this->curPtr), $extend),
            Key::CtrlHome->value => $this->moveCursor(0, $extend),
            Key::CtrlEnd->value => $this->moveCursor($this->length(), $extend),
            Key::CtrlBackspace->value => $this->deleteTo($this->previousWord($this->curPtr)),
            Key::CtrlDelete->value => $this->deleteTo($this->nextWord($this->curPtr)),
            Key::CtrlInsert->value => $this->copy(),
            Key::ShiftInsert->value => $this->paste(),
            Key::ShiftDelete->value => $this->cut(),
            Key::CtrlA->value => $this->selectAllAction(),
            Key::CtrlC->value => $this->copy(),
            Key::CtrlV->value => $this->paste(),
            Key::CtrlX->value => $this->cut(),
            Key::CtrlZ->value => $this->undo(),
            default => $this->insertPrintable($key),
        };
        if ($handled) {
            $this->clearEvent($event);
        }
    }

    private function insertPrintable(KeyDownEvent $key): bool
    {
        if ($key->char === '' || preg_match('/[\p{Cc}\p{Cs}]/u', $key->char) === 1) {
            return false;
        }

        return $this->insertText($key->char);
    }

    private function selectAllAction(): bool
    {
        $this->selectAll();

        return true;
    }

    private function hideSelectAction(): bool
    {
        $this->hideSelect();

        return true;
    }

    /** @return array{0:int,1:int} */
    private function selectionRange(): array
    {
        return [min($this->selStart, $this->selEnd), max($this->selStart, $this->selEnd)];
    }

    private function stateSnapshot(): EditorStateSnapshot
    {
        return new EditorStateSnapshot($this->curPtr, $this->selStart, $this->selEnd, $this->modified);
    }

    private function recordEdit(
        int $start,
        string $removed,
        int $removedLength,
        string $inserted,
        int $insertedLength,
        EditorStateSnapshot $before,
    ): void {
        $record = new EditorUndoRecord(
            $start,
            $removed,
            $removedLength,
            $inserted,
            $insertedLength,
            $before,
            $this->stateSnapshot(),
        );
        $this->retainRecord($this->undoStack, $this->undoBytes, $record);
        $this->redoStack = [];
        $this->redoBytes = 0;
    }

    private function applyUndoRecord(EditorUndoRecord $record, bool $undo): void
    {
        $removeLength = $undo ? $record->insertedLength : $record->removedLength;
        $insert = $undo ? $record->removed : $record->inserted;
        $state = $undo ? $record->before : $record->after;

        $this->buffer->moveTo($record->start);
        $this->buffer->deleteForward($removeLength);
        $this->buffer->insert($insert);
        $this->curPtr = $state->cursor;
        $this->selStart = $state->selectionStart;
        $this->selEnd = $state->selectionEnd;
        $this->modified = $state->modified;
        $this->buffer->moveTo($this->curPtr);
        $this->afterMutation();
    }

    /** @param list<EditorUndoRecord> $stack */
    private function retainRecord(array &$stack, int &$bytes, EditorUndoRecord $record): void
    {
        $recordBytes = $record->retainedBytes();
        if ($recordBytes > self::MAX_UNDO_BYTES) {
            return;
        }
        $stack[] = $record;
        $bytes += $recordBytes;
        while (count($stack) > self::MAX_UNDO || $bytes > self::MAX_UNDO_BYTES) {
            $discarded = array_shift($stack);
            if ($discarded !== null) {
                $bytes -= $discarded->retainedBytes();
            }
        }
    }

    private function afterMutation(): void
    {
        $this->trackCursor();
        $this->syncChrome();
        $this->drawView();
    }

    private function syncChrome(): void
    {
        $position = $this->positionOf($this->curPtr);
        $this->indicator?->setValue($position, $this->modified);
        $this->hScrollBar?->setParams($this->deltaX, 0, max(0, $this->widestLine() - max(1, $this->bounds->width())), max(1, $this->bounds->width() - 1), 1);
        $this->vScrollBar?->setParams($this->deltaY, 0, max(0, $this->lineCount() - max(1, $this->bounds->height())), max(1, $this->bounds->height() - 1), 1);
    }

    private function placeCursor(): void
    {
        $position = $this->positionOf($this->curPtr);
        $x = $position->x - $this->deltaX;
        $y = $position->y - $this->deltaY;
        if ($x >= 0 && $x < $this->bounds->width() && $y >= 0 && $y < $this->bounds->height()) {
            $this->setCursor($x, $y);
            $this->setState(State::CursorVis, $this->getState(State::Focused));
        } else {
            $this->setState(State::CursorVis, false);
        }
    }

    /** @return list<array{0:int,1:int}> */
    private function lineRanges(): array
    {
        $this->ensureMetrics();

        return $this->cachedLineRanges;
    }

    /** Build line boundaries and width once for each content version. */
    private function ensureMetrics(): void
    {
        if ($this->metricsVersion === $this->buffer->version()) {
            return;
        }

        $ranges = [];
        $start = 0;
        $column = 0;
        $widest = 0;
        $length = $this->length();
        for ($i = 0; $i < $length; $i++) {
            $grapheme = $this->buffer->graphemeAt($i);
            if ($grapheme === "\n") {
                $ranges[] = [$start, $i];
                $start = $i + 1;
                $widest = max($widest, $column);
                $column = 0;
            } elseif ($grapheme === "\t") {
                $column += self::TAB_WIDTH - ($column % self::TAB_WIDTH);
            } else {
                $column++;
            }
        }
        $ranges[] = [$start, $length];
        $this->cachedLineRanges = $ranges;
        $this->cachedWidestLine = max($widest, $column);
        $this->metricsVersion = $this->buffer->version();
        $this->metricBuildCount++;
    }

    private function lineCount(): int
    {
        return count($this->lineRanges());
    }

    private function widestLine(): int
    {
        $this->ensureMetrics();

        return $this->cachedWidestLine;
    }

    private function lineIndexForPointer(int $pointer): int
    {
        $ranges = $this->lineRanges();
        $low = 0;
        $high = count($ranges) - 1;
        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            if ($pointer <= $ranges[$middle][1]) {
                $high = $middle;
            } else {
                $low = $middle + 1;
            }
        }

        return $low;
    }

    private function displayColumn(int $start, int $pointer): int
    {
        $column = 0;
        for ($position = $start; $position < $pointer; $position++) {
            $grapheme = $this->buffer->graphemeAt($position);
            if ($grapheme === "\t") {
                $column += self::TAB_WIDTH - ($column % self::TAB_WIDTH);
            } else {
                $column++;
            }
        }

        return $column;
    }

    /**
     * @param list<string> $haystack
     * @param list<string> $query
     */
    private function matchesAt(array $haystack, array $query, int $at, int $options): bool
    {
        foreach ($query as $index => $grapheme) {
            $actual = $haystack[$at + $index];
            if (($options & SearchOptions::CaseSensitive) === 0) {
                if (mb_strtolower($actual, 'UTF-8') !== mb_strtolower($grapheme, 'UTF-8')) {
                    return false;
                }
            } elseif ($actual !== $grapheme) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $text */
    private function isWholeWordMatch(array $text, int $start, int $length): bool
    {
        $word = static fn (string $g): bool => preg_match('/^[\p{L}\p{N}_]$/u', $g) === 1;
        $before = $start > 0 ? $text[$start - 1] : '';
        $after = $start + $length < count($text) ? $text[$start + $length] : '';

        return ! $word($before) && ! $word($after);
    }

    private function inSelection(int $pointer): bool
    {
        [$start, $end] = $this->selectionRange();

        return $pointer >= $start && $pointer < $end;
    }

    private function previousWord(int $pointer): int
    {
        $pointer = max(0, min($this->length(), $pointer));
        while ($pointer > 0 && $this->isWordGrapheme($this->buffer->graphemeAt($pointer - 1)) === false) {
            $pointer--;
        }
        while ($pointer > 0 && $this->isWordGrapheme($this->buffer->graphemeAt($pointer - 1))) {
            $pointer--;
        }

        return $pointer;
    }

    private function nextWord(int $pointer): int
    {
        $pointer = max(0, min($this->length(), $pointer));
        while ($pointer < $this->length() && $this->isWordGrapheme($this->buffer->graphemeAt($pointer))) {
            $pointer++;
        }
        while ($pointer < $this->length() && ! $this->isWordGrapheme($this->buffer->graphemeAt($pointer))) {
            $pointer++;
        }

        return $pointer;
    }

    private function isWordGrapheme(string $grapheme): bool
    {
        return preg_match('/^[\p{L}\p{N}_]$/u', $grapheme) === 1;
    }
}
