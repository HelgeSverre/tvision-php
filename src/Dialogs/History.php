<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

/** History-picker button and process-local history storage. */
final class History extends View
{
    /** @var array<int, list<string>> */
    private static array $lists = [];

    public const string Icon = "\u{25BE}";

    public function __construct(Rect $bounds, public ?View $link, public int $historyId)
    {
        parent::__construct($bounds);
        $this->options |= State::PostProcess | State::FirstClick;
        $this->eventMask |= EventMask::Broadcast;
    }

    /** @return list<string> */
    public static function items(int $historyId): array
    {
        return self::$lists[$historyId] ?? [];
    }

    public static function clear(int $historyId): void
    {
        self::$lists[$historyId] = [];
    }

    public function recordHistory(string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }
        $items = array_values(array_filter(self::$lists[$this->historyId] ?? [], static fn (string $item): bool => $item !== $value));
        array_unshift($items, $value);
        self::$lists[$this->historyId] = array_slice($items, 0, 255);
    }

    public function draw(): void
    {
        $b = new DrawBuffer($this->bounds->width(), $this->mapColor(1));
        $b->moveStr(0, self::Icon, $this->mapColor(1));
        $this->writeLine(0, 0, $this->bounds->width(), $this->bounds->height(), $b);
    }

    public function getPalette(): Palette
    {
        return Palette::fromBytes("\x16\x17");
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::Broadcast) {
            $message = $event->asMessage();
            if (($message?->command === Cmd::ReleasedFocus && $message->info === $this->link)
                || $message?->command === Cmd::RecordHistory
            ) {
                $this->recordLinkedValue();
            }

            return;
        }

        $key = $event->asKey();
        $mouse = $event->asMouse();
        $openFromKeyboard = $event->what === EventType::KeyDown
            && $key !== null
            && $key->keyCode === Key::Down->value
            && $this->link?->getState(State::Focused) === true;
        $openFromMouse = $event->what === EventType::MouseDown
            && $mouse !== null
            && $this->mouseInView($mouse->where);
        if (!$openFromMouse && !$openFromKeyboard) {
            return;
        }
        $link = $this->link;
        if ($link === null || !$link->focus()) {
            $this->clearEvent($event);

            return;
        }
        $this->recordLinkedValue();
        if ($this->owner instanceof Group) {
            // HistoryWindow is executed by the immediate owner, so its bounds must
            // use that owner's local coordinate system rather than screen coordinates.
            $anchor = $this->getBounds()->a;
            $window = new HistoryWindow(Rect::of($anchor->x, $anchor->y + 1, $anchor->x + 32, $anchor->y + 10), $this->historyId);
            $result = $this->owner->execView($window);
            if ($result === Cmd::Ok) {
                $link->setData($window->getSelection());
            }
        }
        $this->clearEvent($event);
    }

    private function recordLinkedValue(): void
    {
        if ($this->link === null) {
            return;
        }

        $value = $this->link->getData();
        if (is_string($value)) {
            $this->recordHistory($value);
        } elseif ($value instanceof \Stringable) {
            $this->recordHistory((string) $value);
        }
    }
}
