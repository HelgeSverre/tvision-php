<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Application;

use HelgeSverre\TurboVision\Drivers\AnsiDriver;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Menus\SubMenu;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;

/**
 * The class users subclass (faithful to TApplication). Provides default desktop, menu
 * bar, and status line factories so `final class HelloApp extends Application {}` then
 * `(new HelloApp())->run()` works. Accepts an optional injected Screen for headless tests.
 */
class Application extends Program
{
    public function __construct(private readonly ?Screen $screenOverride = null)
    {
        parent::__construct();
    }

    /**
     * Whether $file was the script actually executed — the guard every example
     * wraps its `exit(new static()->run())` in, so require'ing the file from a
     * test or tool never hijacks the terminal.
     */
    public static function runningAsMain(string $file): bool
    {
        $argv = $_SERVER['argv'] ?? null;
        if (! is_array($argv)) {
            return false;
        }
        $entry = $argv[0] ?? null;
        if (! is_string($entry)) {
            return false;
        }

        return realpath($entry) === realpath($file);
    }

    protected function initScreen(): Screen
    {
        return $this->screenOverride ?? new Screen(new AnsiDriver());
    }

    protected function initDeskTop(Rect $bounds): ?Desktop
    {
        return new Desktop($bounds);
    }

    protected function initMenuBar(Rect $bounds): ?MenuBar
    {
        return new MenuBar($bounds, new SubMenu('~F~ile', Key::AltF)->items(
            new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Exit the program'),
        ));
    }

    protected function initStatusLine(Rect $bounds): ?StatusLine
    {
        return new StatusLine($bounds, StatusDef::all(
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        ));
    }
}
