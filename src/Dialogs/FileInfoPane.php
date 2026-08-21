<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Collections\SearchRec;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drawing\TerminalText;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventMask;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\View;

/** Displays the selected filename, size and modification date in a FileDialog. */
final class FileInfoPane extends View
{
    private ?SearchRec $file = null;

    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        // The pane exists to mirror FileList focus broadcasts; without the mask
        // bit Group routing never delivers them to it.
        $this->eventMask |= EventMask::Broadcast;
    }

    public function getPalette(): Palette
    {
        return Palette::fromBytes("\x1E");
    }

    public function file(): ?SearchRec
    {
        return $this->file;
    }

    public function setFile(?SearchRec $file): void
    {
        $this->file = $file;
        $this->drawView();
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::Broadcast && $event->isCommand(FileCommand::Focused)) {
            $info = $event->asMessage()?->info;
            if ($info instanceof SearchRec || $info === null) {
                $this->setFile($info);
            }
        }
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $attr = $this->mapColor(1);
        $path = $this->owner instanceof FileDialog
            ? FilePath::join($this->owner->directory, $this->owner->wildCard)
            : '';
        $rows = [
            TerminalText::slice($path, 0, max(0, $width - 2)),
            $this->description(),
        ];

        for ($y = 0; $y < $height; $y++) {
            $buffer = new DrawBuffer($width);
            $buffer->moveChar(0, ' ', $attr, $width);
            if (isset($rows[$y])) {
                $buffer->moveStr(1, $rows[$y], $attr);
            }
            $this->writeLine(0, $y, $width, 1, $buffer);
        }
    }

    private function description(): string
    {
        if ($this->file === null) {
            return '';
        }
        $kind = $this->file->isDirectory() ? 'directory' : sprintf('%d bytes', $this->file->size);
        $date = $this->file->modifiedAt?->format('M d, Y H:i') ?? '';

        return trim($this->file->name . '  ' . $kind . '  ' . $date);
    }
}
