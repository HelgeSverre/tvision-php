<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Legacy;

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Dialogs\Button;
use HelgeSverre\TurboVision\Dialogs\ButtonFlag;
use HelgeSverre\TurboVision\Dialogs\CheckBoxes;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Dialogs\InputLine;
use HelgeSverre\TurboVision\Dialogs\Label;
use HelgeSverre\TurboVision\Dialogs\MessageBox;
use HelgeSverre\TurboVision\Dialogs\MsgBoxFlag;
use HelgeSverre\TurboVision\Dialogs\RadioButtons;
use HelgeSverre\TurboVision\Dialogs\SItem;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\StatusLine;

/** The no-menu/no-status ordering-dialog pattern from nomenus.cc. */
final class NoMenusApp extends Application
{
    public ?int $lastDialogResult = null;

    protected function initMenuBar(Rect $bounds): ?MenuBar
    {
        return null;
    }

    protected function initStatusLine(Rect $bounds): ?StatusLine
    {
        return null;
    }

    public function createOrderDialog(): Dialog
    {
        $dialog = new Dialog(Rect::of(0, 0, 44, 17), 'Cheese order');
        $dialog->options |= \HelgeSverre\TurboVision\Views\State::Centered;

        $cheeses = new CheckBoxes(Rect::of(3, 3, 18, 6), SItem::list('~H~avarti', '~T~ilset', '~J~arlsberg'));
        $consistency = new RadioButtons(Rect::of(23, 3, 40, 6), SItem::list('~S~olid', '~R~unny', '~M~elted'));
        $instructions = new InputLine(Rect::of(3, 9, 40, 10), 128);
        $dialog->insert(new Label(Rect::of(3, 2, 18, 3), 'Cheeses', $cheeses));
        $dialog->insert($cheeses);
        $dialog->insert(new Label(Rect::of(23, 2, 40, 3), 'Consistency', $consistency));
        $dialog->insert($consistency);
        $dialog->insert(new Label(Rect::of(3, 8, 28, 9), 'Delivery instructions', $instructions));
        $dialog->insert($instructions);
        $dialog->insert(new Button(Rect::of(15, 12, 25, 14), '~O~K', Cmd::Ok, ButtonFlag::Default));
        $dialog->insert(new Button(Rect::of(28, 12, 40, 14), '~C~ancel', Cmd::Cancel));
        $dialog->setData([1, 2, 'By box']);

        return $dialog;
    }

    /** Build the one-call welcome dialog without requiring an interactive modal loop. */
    public function welcomeDialogForTest(): Dialog
    {
        return MessageBox::dialog(Rect::of(0, 0, 36, 8), 'Welcome to the cheese ordering system', MsgBoxFlag::Information | MsgBoxFlag::OkButton);
    }

    /** Run the original one-shot welcome/order flow without a menu or status line. */
    public function runOrder(): int
    {
        try {
            $this->bootForTest();
            $this->executeDialog($this->welcomeDialogForTest());
            $this->lastDialogResult = $this->executeDialog($this->createOrderDialog());

            return 0;
        } finally {
            $this->suspend();
        }
    }
}
