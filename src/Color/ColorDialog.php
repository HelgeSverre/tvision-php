<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Color;

use Closure;
use HelgeSverre\TurboVision\Dialogs\Button;
use HelgeSverre\TurboVision\Dialogs\ButtonFlag;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\StaticText;

/**
 * Modal palette editor modelled after Turbo Vision's TColorDialog.
 *
 * Edits always land in an internal working copy. `commit()` returns a new Palette,
 * and an optional commit callback is the only way the caller's live application
 * palette changes. `cancel()` restores the working copy without changing the source.
 */
final class ColorDialog extends Dialog
{
    /** @var array<int, int> 1-based logical palette index => classic attribute byte */
    private array $originalEntries;

    /** @var array<int, int> 1-based logical palette index => classic attribute byte */
    private array $workingEntries;

    /** @var Closure(Palette):void|null */
    private readonly ?Closure $onCommit;

    private int $groupIndex = 0;

    public readonly ColorGroupList $groups;

    public readonly ColorItemList $items;

    public readonly ColorDisplay $display;

    public readonly ?ColorSelector $foregroundSelector;

    public readonly ?ColorSelector $backgroundSelector;

    public readonly ?MonoSelector $monoSelector;

    /**
     * @param iterable<mixed> $groups
     * @param callable(Palette):void|null $onCommit
     */
    public function __construct(
        Palette $palette,
        iterable $groups,
        bool $monochrome = false,
        ?callable $onCommit = null,
    ) {
        parent::__construct(Rect::of(0, 0, 79, 18), 'Colors');
        $this->options |= \HelgeSverre\TurboVision\Views\State::Centered;
        $this->onCommit = $onCommit === null ? null : Closure::fromCallable($onCommit);
        $this->originalEntries = self::entriesFrom($palette);
        $this->workingEntries = $this->originalEntries;

        $colorGroups = self::normalizeGroups($groups);

        $groupBar = new ScrollBar(Rect::of(27, 3, 28, 14));
        $itemBar = new ScrollBar(Rect::of(59, 3, 60, 14));
        $this->groups = new ColorGroupList(Rect::of(3, 3, 27, 14), $groupBar, $colorGroups);
        $this->items = new ColorItemList(Rect::of(30, 3, 59, 14), $itemBar, $colorGroups[0] ?? null);
        $this->display = new ColorDisplay(
            Rect::of(62, 12, 76, 14),
            'Text ',
            0x07,
            function (int $attribute): void {
                $this->setCurrentAttribute($attribute);
            },
        );

        $this->insert($groupBar);
        $this->insert($this->groups);
        $this->insert(new StaticText(Rect::of(3, 2, 27, 3), '~G~roup'));
        $this->insert($itemBar);
        $this->insert($this->items);
        $this->insert(new StaticText(Rect::of(30, 2, 59, 3), '~I~tem'));
        $this->insert($this->display);

        if ($monochrome) {
            $this->foregroundSelector = null;
            $this->backgroundSelector = null;
            $this->monoSelector = new MonoSelector(
                Rect::of(62, 3, 77, 7),
                onChanged: function (int $attribute): void {
                    $this->display->setColor($attribute);
                },
            );
            $this->insert(new StaticText(Rect::of(62, 2, 77, 3), '~C~olor'));
            $this->insert($this->monoSelector);
        } else {
            $this->foregroundSelector = new ColorSelector(Rect::of(63, 3, 75, 7), ColorSelectorType::Foreground);
            $this->backgroundSelector = new ColorSelector(Rect::of(63, 9, 75, 11), ColorSelectorType::Background);
            $this->monoSelector = null;
            $this->insert(new StaticText(Rect::of(63, 2, 75, 3), '~F~oreground'));
            $this->insert($this->foregroundSelector);
            $this->insert(new StaticText(Rect::of(63, 8, 75, 9), '~B~ackground'));
            $this->insert($this->backgroundSelector);
        }

        $this->insert(new Button(Rect::of(51, 15, 61, 17), 'O~K~', Cmd::Ok, ButtonFlag::Default));
        $this->insert(new Button(Rect::of(63, 15, 73, 17), 'Cancel', Cmd::Cancel));

        if ($colorGroups !== []) {
            $this->selectGroup(0);
        }
    }

    public function dataSize(): int
    {
        return count($this->workingEntries);
    }

    public function getData(): Palette
    {
        return $this->workingPalette();
    }

    public function setData(mixed $data): void
    {
        if (! $data instanceof Palette) {
            throw new \InvalidArgumentException('ColorDialog data must be a Palette.');
        }
        $this->originalEntries = self::entriesFrom($data);
        $this->workingEntries = $this->originalEntries;
        $this->syncDisplayToCurrentItem();
    }

    /** Return and publish the working copy. The original Palette remains immutable. */
    public function commit(): Palette
    {
        $palette = $this->workingPalette();
        if ($this->onCommit !== null) {
            ($this->onCommit)($palette);
        }

        return $palette;
    }

    /** Discard every uncommitted attribute change and redraw the current preview. */
    public function cancel(): void
    {
        $this->workingEntries = $this->originalEntries;
        $this->syncDisplayToCurrentItem();
    }

    public function getIndexes(): ColorIndex
    {
        $indexes = [];
        for ($i = 0; $i < $this->groups->getNumGroups(); $i++) {
            $indexes[] = $this->groups->getGroupIndex($i);
        }

        return new ColorIndex($this->groupIndex, $indexes);
    }

    public function setIndexes(ColorIndex $indexes): void
    {
        for ($i = 0; $i < $this->groups->getNumGroups(); $i++) {
            $this->groups->setGroupIndex($i, $indexes->itemIndexes[$i] ?? 0);
        }
        $this->selectGroup($indexes->groupIndex);
    }

    public function handleEvent(Event $event): void
    {
        // Handle completion before Dialog consumes the command to ensure an OK button
        // commits and Cancel restores the visual working copy in non-modal use too.
        if ($event->what === EventType::Command && $event->isCommand(Cmd::Ok)) {
            $this->commit();
        } elseif ($event->what === EventType::Command && $event->isCommand(Cmd::Cancel)) {
            $this->cancel();
        }

        parent::handleEvent($event);

        if ($event->what !== EventType::Broadcast) {
            return;
        }
        if ($event->isCommand(ColorCommand::NewItem)) {
            $this->groupIndex = $this->groups->focused;
        } elseif ($event->isCommand(ColorCommand::NewIndex)) {
            $index = $event->asMessage()?->info;
            if (! is_int($index)) {
                return;
            }
            $this->display->setColor($this->workingEntries[$index] ?? 0x07);
        }
    }

    private function selectGroup(int $index): void
    {
        if ($this->groups->getNumGroups() === 0) {
            return;
        }
        $index = min(max(0, $index), $this->groups->getNumGroups() - 1);
        $this->groupIndex = $index;
        $this->groups->focusItem($index);
        $this->syncDisplayToCurrentItem();
    }

    private function syncDisplayToCurrentItem(): void
    {
        $item = $this->items->currentGroup()?->item($this->items->focused);
        if ($item === null) {
            return;
        }
        $this->display->setColor($this->workingEntries[$item->index] ?? 0x07);
    }

    private function setCurrentAttribute(int $attribute): void
    {
        $item = $this->items->currentGroup()?->item($this->items->focused);
        if ($item !== null) {
            $this->workingEntries[$item->index] = $attribute & 0xFF;
        }
    }

    /** @return array<int, int> */
    private static function entriesFrom(Palette $palette): array
    {
        $entries = [];
        for ($index = 1; $index <= $palette->size(); $index++) {
            $entries[$index] = $palette->get($index) & 0xFF;
        }

        return $entries;
    }

    private function workingPalette(): Palette
    {
        $bytes = '';
        $count = count($this->workingEntries);
        for ($index = 1; $index <= $count; $index++) {
            $bytes .= chr(($this->workingEntries[$index] ?? 0x07) & 0xFF);
        }

        return Palette::fromBytes($bytes);
    }

    /**
     * @param iterable<mixed> $groups
     * @return list<ColorGroup>
     */
    private static function normalizeGroups(iterable $groups): array
    {
        $validated = [];
        foreach ($groups as $group) {
            if (! $group instanceof ColorGroup) {
                throw new \InvalidArgumentException('ColorDialog groups must contain ColorGroup instances.');
            }
            $validated[] = $group;
        }

        return $validated;
    }

}
