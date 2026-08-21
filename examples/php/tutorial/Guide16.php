<?php

declare(strict_types=1);

/*
 * Guide16 — PHP port of tvguid16.cc. It demonstrates form data transfer by
 * pre-populating the three controls, then retaining their values after an
 * accepted modal result (Cancel deliberately leaves the saved data unchanged).
 */

use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Events\Cmd;

require_once __DIR__ . '/Guide15.php';

class Guide16App extends Guide15App
{
    /** @var array{0:int,1:int,2:string} Corresponds to checkbox/radio/input order. */
    private array $dialogData = [1, 2, 'Phone Mum!'];

    public function runDemoDialog(): int
    {
        $dialog = $this->buildDialog();
        $dialog->setData($this->dialogData);
        $result = $this->desktopForTest()?->execView($dialog) ?? Cmd::Cancel;
        if ($result !== Cmd::Cancel) {
            /** @var array{0:int,1:int,2:string} $data */
            $data = $dialog->getData();
            $this->dialogData = $data;
        }

        return $result;
    }

    public function buildDialogWithDataForTest(): Dialog
    {
        $dialog = $this->buildDialog();
        $dialog->setData($this->dialogData);

        return $dialog;
    }

    /** @return array{0:int,1:int,2:string} */
    public function dialogDataForTest(): array
    {
        return $this->dialogData;
    }

    /** Store an accepted dialog's normal reusable form-data payload. */
    public function acceptDialogForTest(Dialog $dialog, int $command = Cmd::Ok): void
    {
        if ($command !== Cmd::Cancel) {
            /** @var array{0:int,1:int,2:string} $data */
            $data = $dialog->getData();
            $this->dialogData = $data;
        }
    }
}

if (Guide16App::runningAsMain(__FILE__)) {
    exit((new Guide16App())->run());
}
