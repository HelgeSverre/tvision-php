<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Legacy;

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Dialogs\Button;
use HelgeSverre\TurboVision\Dialogs\ButtonFlag;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Dialogs\ListBox;
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
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarOrientation;

/** A reusable ListBox/Dialog acceptance example based on listbox.cc. */
final class ListBoxApp extends Application
{
    public const int OpenDialog = Cmd::FirstSafeUser;

    public ?int $lastDialogResult = null;

    /** @var list<string> */
    public const array ANIMALS = [
        'dog', 'cat', 'bird', 'fish', 'animal1', 'animal2', 'animal3', 'animal4',
        'animal5', 'animal6', 'animal7', 'animal8', 'human1', 'human2', 'human3',
        'human4', 'human5', 'human6', 'human7', 'human8',
    ];

    protected function initMenuBar(Rect $bounds): MenuBar
    {
        return new MenuBar($bounds,
            new SubMenu('~F~ile', Key::AltF)->items(
                new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Alt-X'),
            ),
            new SubMenu('~W~indow', Key::AltW)->items(
                new MenuItem('~D~ialog', self::OpenDialog, Key::F2, 'F2'),
            ),
        );
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
            $this->lastDialogResult = $this->executeDialog($this->createListDialog());
            $this->clearEvent($event);
        }
    }

    public function createListDialog(): Dialog
    {
        $dialog = new Dialog(Rect::of(0, 0, 42, 17), 'List box');
        $dialog->options |= \HelgeSverre\TurboVision\Views\State::Centered;
        $bar = new ScrollBar(Rect::of(21, 2, 22, 12), ScrollBarOrientation::Vertical);
        $list = new ListBox(Rect::of(2, 2, 20, 12), 1, $bar);
        $list->setData(['collection' => self::ANIMALS, 'selection' => 2]);
        $dialog->insert($bar);
        $dialog->insert($list);
        $dialog->insert(new Button(Rect::of(28, 6, 38, 8), '~O~K', Cmd::Ok, ButtonFlag::Default));
        $dialog->insert(new Button(Rect::of(28, 10, 38, 12), '~C~ancel', Cmd::Cancel));

        return $dialog;
    }
}
