<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

use Closure;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarOrientation;
use HelgeSverre\TurboVision\Views\Window;

/** Standard window composition for a FileEditor, both scroll bars and indicator. */
class EditWindow extends Window
{
    /** @var null|Closure(FileEditor):bool */
    private ?Closure $closeResolver = null;

    public readonly FileEditor $editor;

    public readonly ScrollBar $hScrollBar;

    public readonly ScrollBar $vScrollBar;

    public readonly Indicator $indicator;

    public function __construct(Rect $bounds, ?string $fileName = null, int $number = 0)
    {
        parent::__construct($bounds, '', $number);
        $extent = $this->getExtent();
        $this->indicator = new Indicator(Rect::of(1, $extent->b->y - 1, 2, $extent->b->y));
        $this->hScrollBar = new ScrollBar(
            Rect::of(2, $extent->b->y - 1, $extent->b->x - 2, $extent->b->y),
            ScrollBarOrientation::Horizontal,
        );
        $this->vScrollBar = new ScrollBar(
            Rect::of($extent->b->x - 1, 1, $extent->b->x, $extent->b->y - 1),
            ScrollBarOrientation::Vertical,
        );
        $this->editor = new FileEditor(
            Rect::of(1, 1, $extent->b->x - 1, $extent->b->y - 1),
            $this->hScrollBar,
            $this->vScrollBar,
            $this->indicator,
            $fileName,
        );
        $this->insert($this->indicator);
        $this->insert($this->hScrollBar);
        $this->insert($this->vScrollBar);
        $this->insert($this->editor);
        $this->setCurrent($this->editor);
    }

    public function frameTitle(): string
    {
        return $this->editor->fileName === null ? 'Untitled' : basename($this->editor->fileName);
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);
        if ($event->isCommand(Cmd::UpdateTitle)) {
            $this->drawView();
            $this->clearEvent($event);
        }
    }

    /**
     * Let an application provide its Save/Discard/Cancel close workflow.
     *
     * @param null|callable(FileEditor): bool $resolver
     */
    public function setCloseResolver(?callable $resolver): void
    {
        $this->closeResolver = $resolver === null
            ? null
            : static fn (FileEditor $editor): bool => $resolver($editor);
    }

    public function close(): void
    {
        if (! $this->editor->valid()
            && ($this->closeResolver === null || ! ($this->closeResolver)($this->editor))
        ) {
            return;
        }
        if (! $this->editor->valid()) {
            return;
        }
        parent::close();
    }
}
