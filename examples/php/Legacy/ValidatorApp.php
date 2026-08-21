<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Legacy;

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Dialogs\Button;
use HelgeSverre\TurboVision\Dialogs\ButtonFlag;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Dialogs\InputLine;
use HelgeSverre\TurboVision\Dialogs\Label;
use HelgeSverre\TurboVision\Dialogs\ParamText;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Menus\SubMenu;
use HelgeSverre\TurboVision\Validators\PictureValidator;
use HelgeSverre\TurboVision\Validators\RangeValidator;

/** Compact validator.cc port that exposes a dialog factory for scripted tests. */
final class ValidatorApp extends Application
{
    public const int OpenDialog = Cmd::FirstSafeUser;

    public ?int $lastDialogResult = null;

    protected function initMenuBar(Rect $bounds): MenuBar
    {
        return new MenuBar($bounds, new SubMenu('~F~ile', Key::AltF)->items(
            new MenuItem('~D~ialog...', self::OpenDialog, Key::F2, 'F2'),
            MenuItem::separator(),
            new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Alt-X'),
        ));
    }

    protected function initStatusLine(Rect $bounds): StatusLine
    {
        return new StatusLine($bounds, StatusDef::all(
            new StatusItem('~F2~ Dialog', Key::F2, self::OpenDialog),
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        ));
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);
        if ($event->what === EventType::Command && $event->asMessage()?->command === self::OpenDialog) {
            $this->lastDialogResult = $this->executeDialog($this->createValidatorDialog());
            $this->clearEvent($event);
        }
    }

    public function createValidatorDialog(): Dialog
    {
        $dialog = new Dialog(Rect::of(0, 0, 48, 18), 'Validator example');
        $dialog->options |= \HelgeSverre\TurboVision\Views\State::Centered;

        $day = new InputLine(Rect::of(25, 2, 29, 3), 4, new RangeValidator(1, 31));
        $month = new InputLine(Rect::of(30, 2, 34, 3), 3, new RangeValidator(1, 12));
        $year = new InputLine(Rect::of(35, 2, 41, 3), 5, new RangeValidator(1950, 2050));
        $letters = new InputLine(Rect::of(25, 5, 42, 6), 3, new PictureValidator('&&'));
        $code = new InputLine(Rect::of(25, 7, 42, 8), 10, new PictureValidator('#####-###', true));
        $date = new InputLine(Rect::of(25, 9, 42, 10), 11, new PictureValidator('##/##/####', true));

        $dialog->insert(new Label(Rect::of(2, 2, 24, 3), 'Date style', $day));
        $dialog->insert($day);
        $dialog->insert(new ParamText(Rect::of(29, 2, 30, 3), '/'));
        $dialog->insert($month);
        $dialog->insert(new ParamText(Rect::of(34, 2, 35, 3), '/'));
        $dialog->insert($year);
        $dialog->insert(new Label(Rect::of(2, 5, 24, 6), 'Two letters', $letters));
        $dialog->insert($letters);
        $dialog->insert(new Label(Rect::of(2, 7, 24, 8), 'Fixed-length code', $code));
        $dialog->insert($code);
        $dialog->insert(new Label(Rect::of(2, 9, 24, 10), 'Another date style', $date));
        $dialog->insert($date);
        $dialog->insert(new Button(Rect::of(13, 13, 23, 15), 'O~K~', Cmd::Ok, ButtonFlag::Default));
        $dialog->insert(new Button(Rect::of(25, 13, 37, 15), '~C~ancel', Cmd::Cancel));

        return $dialog;
    }
}
