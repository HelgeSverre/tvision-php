<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Legacy;

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Text\Terminal;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\Window;

/** A headless-friendly terminal-output acceptance example for the tvlife-era TTY view. */
final class TerminalApp extends Application
{
    private ?Terminal $terminal = null;

    protected function initDeskTop(Rect $bounds): Desktop
    {
        $desktop = parent::initDeskTop($bounds);
        assert($desktop instanceof Desktop);

        $width = min(64, max(16, $bounds->width() - 4));
        $height = min(18, max(6, $bounds->height() - 2));
        $window = new Window(Rect::of(2, 1, 2 + $width, 1 + $height), 'Terminal scrollback');
        $this->terminal = new Terminal(Rect::of(1, 1, $width - 1, $height - 1), maxBytes: 4096, maxLines: 64);
        $window->insert($this->terminal);
        $desktop->insertWindow($window);

        return $desktop;
    }

    public function terminalForTest(): Terminal
    {
        assert($this->terminal instanceof Terminal);

        return $this->terminal;
    }

    public function writeDemoLog(): void
    {
        $this->terminalForTest()->write("Turbo Vision terminal demo\nboot: ok\nworker: ready\n");
    }
}
