<?php

declare(strict_types=1);

/*
 * Guide15 — PHP port of tvguid15.cc. Adds the delivery-instructions InputLine
 * and its linked mnemonic Label to the Guide14 form.
 */

use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Dialogs\InputLine;
use HelgeSverre\TurboVision\Dialogs\Label;
use HelgeSverre\TurboVision\Geometry\Rect;

require_once __DIR__ . '/Guide14.php';

class Guide15App extends Guide14App
{
    protected ?InputLine $deliveryInstructions = null;

    protected function buildDialog(): Dialog
    {
        $dialog = parent::buildDialog();
        $this->deliveryInstructions = new InputLine(Rect::of(3, 8, 37, 9), 128);
        $dialog->insert($this->deliveryInstructions);
        $dialog->insert(new Label(
            Rect::of(2, 7, 24, 8),
            'Delivery Instructions',
            $this->deliveryInstructions,
        ));

        return $dialog;
    }

    public function deliveryInstructionsForTest(): ?InputLine
    {
        return $this->deliveryInstructions;
    }
}

if (Guide15App::runningAsMain(__FILE__)) {
    exit((new Guide15App())->run());
}
