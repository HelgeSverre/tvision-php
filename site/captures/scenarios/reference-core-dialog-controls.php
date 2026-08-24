<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Dialogs\Button;
use HelgeSverre\TurboVision\Dialogs\ButtonFlag;
use HelgeSverre\TurboVision\Dialogs\CheckBoxes;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Dialogs\InputLine;
use HelgeSverre\TurboVision\Dialogs\Label;
use HelgeSverre\TurboVision\Dialogs\RadioButtons;
use HelgeSverre\TurboVision\Dialogs\SItem;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use TurboVisionDocs\Captures\CaptureScenario;

return new CaptureScenario(
    id: 'reference/core-dialog-controls',
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);
            $dialog = new Dialog(Rect::of(10, 3, 70, 22), 'Build options');

            $name = new InputLine(Rect::of(3, 3, 29, 4), 40);
            $name->setText('release');
            $dialog->insert(new Label(Rect::of(3, 2, 29, 3), '~N~ame', $name));
            $dialog->insert($name);

            $checks = new CheckBoxes(Rect::of(3, 6, 28, 9), SItem::list(
                '~D~ebug symbols',
                '~T~est suite',
                '~P~ackage archive',
            ));
            $checks->value = 0b101;
            $dialog->insert($checks);

            $radios = new RadioButtons(Rect::of(33, 6, 56, 9), SItem::list(
                '~F~ast',
                '~S~afe',
                '~C~ustom',
            ));
            $radios->value = 1;
            $dialog->insert($radios);

            $dialog->insert(new Button(
                Rect::of(22, 14, 34, 16),
                'O~K~',
                Cmd::Ok,
                ButtonFlag::Default,
            ));
            $dialog->insert(new Button(Rect::of(36, 14, 48, 16), 'Cancel', Cmd::Cancel));
            $desktop->insertWindow($dialog);

            return $desktop;
        }
    },
);
