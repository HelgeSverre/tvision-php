<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Studio;

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Drivers\AnsiDriver;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Terminal\Screen;
use InvalidArgumentException;
use RuntimeException;

class StudioApp extends Application
{
    private readonly StudioProjectStore $store;

    private readonly StudioPhpExporter $exporter;

    private ?StudioView $studioView = null;

    public function __construct(
        ?Screen $screen = null,
        private readonly string $projectPath = 'studio-project.json',
        private readonly string $exportPath = 'studio-generated.php',
    ) {
        if (StudioPathGuard::sameTarget($projectPath, $exportPath)) {
            throw new InvalidArgumentException('Studio project and PHP export paths must be different files.');
        }
        $this->store = new StudioProjectStore();
        $this->exporter = new StudioPhpExporter();
        parent::__construct($screen ?? new Screen(new AnsiDriver(trackMouseMotion: true)));
    }

    public function studioView(): StudioView
    {
        if ($this->studioView === null) {
            throw new \LogicException('StudioApp has not been laid out yet. Call bootForTest() first.');
        }

        return $this->studioView;
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

        $project = StudioProject::starter();
        $message = 'Starter project ready — drag components or double-click a tool.';
        $loaded = false;
        $loadError = false;
        if (is_file($this->projectPath)) {
            try {
                $project = $this->store->load($this->projectPath);
                $message = 'Loaded ' . basename($this->projectPath) . '.';
                $loaded = true;
            } catch (RuntimeException $exception) {
                $message = $exception->getMessage() . ' Using the starter project.';
                $loadError = true;
            }
        }

        $view = new StudioView(
            $bounds,
            $project,
            new StudioHistory(),
            $this->store,
            $this->exporter,
            $this->projectPath,
            $this->exportPath,
            ! $loaded,
            $loadError,
        );
        $view->showStatus($message, $loadError);
        $this->studioView = $view;
        $this->insert($view);
        $this->setCurrent($view);
    }

    public function reflowDesktop(): void
    {
        $bounds = Rect::of(0, 0, $this->screenObj->cols(), $this->screenObj->rows());
        $this->setBounds($bounds);
        $this->studioView?->changeBounds($bounds);
    }

    public function dispatchForTest(Event $event): void
    {
        $this->handleEvent($event);
        $this->drawAndFlushForTest();
    }
}
