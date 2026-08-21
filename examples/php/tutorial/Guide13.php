<?php

declare(strict_types=1);

/*
 * Guide13 — PHP port of tvguid13.cc. Adds the default OK and normal Cancel
 * buttons to the modal dialog from Guide12.
 */

use HelgeSverre\TurboVision\Dialogs\Button;
use HelgeSverre\TurboVision\Dialogs\ButtonFlag;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Geometry\Rect;

require_once __DIR__ . '/Guide12.php';

class Guide13App extends Guide12App
{
    protected ?Dialog $lastDialog = null;

    protected function buildDialog(): Dialog
    {
        $dialog = new Dialog(Rect::of(20, 6, 60, 19), 'Demo Dialog');
        $dialog->insert(new Button(Rect::of(15, 10, 25, 12), '~O~K', Cmd::Ok, ButtonFlag::Default));
        $dialog->insert(new Button(Rect::of(28, 10, 38, 12), '~C~ancel', Cmd::Cancel, ButtonFlag::Normal));
        $this->lastDialog = $dialog;

        return $dialog;
    }

    public function runDemoDialog(): int
    {
        $dialog = $this->buildDialog();

        return $this->desktopForTest()?->execView($dialog) ?? Cmd::Cancel;
    }

    public function dialogForTest(): ?Dialog
    {
        return $this->lastDialog;
    }
}

if (Guide13App::runningAsMain(__FILE__)) {
    exit((new Guide13App())->run());
}
