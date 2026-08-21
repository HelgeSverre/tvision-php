<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use InvalidArgumentException;

/** A form-friendly Editor whose value is simply its UTF-8 text. */
class Memo extends Editor
{
    private const string PALETTE = "\x1A\x1B";

    public function __construct(
        Rect $bounds,
        ?ScrollBar $hScrollBar = null,
        ?ScrollBar $vScrollBar = null,
        ?Indicator $indicator = null,
        string $text = '',
    ) {
        parent::__construct($bounds, $hScrollBar, $vScrollBar, $indicator, $text);
    }

    public function getPalette(): ?Palette
    {
        return Palette::fromBytes(self::PALETTE);
    }

    /** The PHP form-data equivalent of Turbo Vision's TMemoData record. */
    public function getData(): string
    {
        return $this->text();
    }

    public function setData(mixed $data): void
    {
        if (is_string($data)) {
            $this->setText($data);

            return;
        }
        if (is_array($data)) {
            $text = $data['text'] ?? '';
            if (! is_string($text)) {
                throw new InvalidArgumentException('Memo form data text must be a string.');
            }
            $this->setText($text);

            return;
        }

        throw new InvalidArgumentException('Memo form data must be a string or an array with a string text entry.');
    }

    /** UTF-8 byte size of the current form payload. */
    public function dataSize(): int
    {
        return strlen($this->text());
    }

    /** Tab is reserved for dialog traversal, just like TMemo. */
    public function handleEvent(Event $event): void
    {
        if ($event->isKey(Key::Tab)) {
            return;
        }

        parent::handleEvent($event);
    }
}
