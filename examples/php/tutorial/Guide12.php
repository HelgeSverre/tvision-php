<?php

declare(strict_types=1);

/*
 * Guide12 — PHP port of tvguid12.cc. The same dialog is now executed modally.
 */

use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Rect;

require_once __DIR__ . '/Guide11.php';

class Guide12App extends Guide11App
{
    /** Execute (rather than insert) the dialog, faithfully demonstrating modal flow. */
    protected function handleNewDialog(): void
    {
        $this->runDemoDialog();
    }

    public function runDemoDialog(): int
    {
        $dialog = new Dialog(Rect::of(20, 6, 60, 19), 'Demo Dialog');

        return $this->desktopForTest()?->execView($dialog) ?? Cmd::Cancel;
    }

    /** @return int The command that completed the modal dialog. */
    public function openModalDialogForTest(int $command = Cmd::Cancel): int
    {
        $this->putEvent(Event::command($command));

        return $this->runDemoDialog();
    }
}

if (Guide12App::runningAsMain(__FILE__)) {
    exit((new Guide12App())->run());
}
