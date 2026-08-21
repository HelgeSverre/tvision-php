<?php

declare(strict_types=1);

/*
 * Guide14 — PHP port of tvguid14.cc. The dialog now demonstrates the reusable
 * CheckBoxes, RadioButtons, SItem, and Label controls.
 */

use HelgeSverre\TurboVision\Dialogs\CheckBoxes;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Dialogs\Label;
use HelgeSverre\TurboVision\Dialogs\RadioButtons;
use HelgeSverre\TurboVision\Dialogs\SItem;
use HelgeSverre\TurboVision\Geometry\Rect;

require_once __DIR__ . '/Guide13.php';

class Guide14App extends Guide13App
{
    protected ?CheckBoxes $cheeses = null;

    protected ?RadioButtons $consistency = null;

    protected function buildDialog(): Dialog
    {
        $dialog = parent::buildDialog();

        $this->cheeses = new CheckBoxes(
            Rect::of(3, 3, 18, 6),
            SItem::list('~H~varti', '~T~ilset', '~J~arlsberg'),
        );
        $dialog->insert($this->cheeses);
        $dialog->insert(new Label(Rect::of(2, 2, 10, 3), 'Cheeses', $this->cheeses));

        $this->consistency = new RadioButtons(
            Rect::of(22, 3, 34, 6),
            SItem::list('~S~olid', '~R~unny', '~M~elted'),
        );
        $dialog->insert($this->consistency);
        $dialog->insert(new Label(Rect::of(21, 2, 33, 3), 'Consistency', $this->consistency));

        return $dialog;
    }

    public function cheesesForTest(): ?CheckBoxes
    {
        return $this->cheeses;
    }

    public function consistencyForTest(): ?RadioButtons
    {
        return $this->consistency;
    }
}

if (isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit((new Guide14App())->run());
}
