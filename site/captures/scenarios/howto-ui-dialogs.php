<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Dialogs\Button;
use HelgeSverre\TurboVision\Dialogs\CheckBoxes;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Dialogs\InputLine;
use HelgeSverre\TurboVision\Dialogs\Label;
use HelgeSverre\TurboVision\Dialogs\ListBox;
use HelgeSverre\TurboVision\Dialogs\RadioButtons;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\StaticText;
use TurboVisionDocs\Captures\CaptureScenario;

return new CaptureScenario(
    id: 'how-to/ui-dialogs',
    columns: 80,
    rows: 25,
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);
            $dialog = new Dialog(Rect::of(0, 0, 66, 19), 'Profile');
            $dialog->options |= State::Centered;

            $name = new InputLine(Rect::of(16, 2, 61, 3), 81);
            $name->setText('Ada Lovelace');
            $dialog->insert(new Label(Rect::of(4, 2, 15, 3), '~N~ame:', $name));
            $dialog->insert($name);

            $age = new InputLine(Rect::of(16, 4, 25, 5), 6);
            $age->setText('36');
            $dialog->insert(new Label(Rect::of(4, 4, 15, 5), '~A~ge:', $age));
            $dialog->insert($age);

            $checks = new CheckBoxes(
                Rect::of(4, 7, 31, 10),
                ['~M~ouse support', '~U~nicode cells', '~A~uto save'],
            );
            $checks->setData(0b011);
            $dialog->insert($checks);

            $mode = new RadioButtons(
                Rect::of(35, 7, 59, 10),
                ['~F~ast', '~S~afe', '~D~ebug'],
            );
            $mode->setData(1);
            $dialog->insert($mode);

            $dialog->insert(new StaticText(Rect::of(35, 10, 59, 11), 'Publication state:'));
            $list = new ListBox(Rect::of(35, 11, 59, 14));
            $list->newList(['Draft', 'Review', 'Published']);
            $dialog->insert($list);

            $dialog->insert(new Button(Rect::of(22, 16, 33, 18), 'O~K~', Cmd::Ok, Button::Default));
            $dialog->insert(new Button(Rect::of(37, 16, 49, 18), 'Cancel', Cmd::Cancel));
            $dialog->setCurrent($name);
            $desktop->insertWindow($dialog);

            return $desktop;
        }
    },
);
