<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\OpenCode;

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Drivers\AnsiDriver;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Terminal\Screen;

final class OpenCodeApp extends Application
{
    private ?OpenCodeView $openCodeView = null;

    public function __construct(?Screen $screen = null)
    {
        parent::__construct($screen ?? new Screen(new AnsiDriver(trackMouseMotion: true)));
    }

    public function openCodeView(): OpenCodeView
    {
        return $this->openCodeView ?? throw new \LogicException('OpenCodeApp has not been laid out.');
    }

    protected function initMenuBar(Rect $bounds): ?MenuBar
    {
        return null;
    }

    protected function initStatusLine(Rect $bounds): ?StatusLine
    {
        return null;
    }

    protected function layout(): void
    {
        $bounds = Rect::of(0, 0, $this->screenObj->cols(), $this->screenObj->rows());
        $this->setBounds($bounds);
        $this->clearSubviews();
        $this->desktop = null;
        $this->menuBar = null;
        $this->statusLine = null;

        $this->openCodeView = new OpenCodeView($bounds);
        $this->insert($this->openCodeView);
        $this->setCurrent($this->openCodeView);
    }

    public function reflowDesktop(): void
    {
        $bounds = Rect::of(0, 0, $this->screenObj->cols(), $this->screenObj->rows());
        $this->setBounds($bounds);
        $this->openCodeView?->changeBounds($bounds);
    }

    public function dispatchForTest(Event $event): void
    {
        $this->handleEvent($event);
        $this->drawAndFlushForTest();
    }
}
