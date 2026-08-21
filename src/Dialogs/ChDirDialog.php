<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;

/** Standard Change Directory dialog. Validation applies the chosen cwd on OK. */
final class ChDirDialog extends Dialog
{
    public const int Normal = 0x0000;
    public const int NoLoadDir = 0x0001;
    public const int HelpButton = 0x0002;

    public InputLine $dirInput;

    public DirListBox $dirList;

    private string $directory;

    public function __construct(int $options = self::Normal, int $historyId = 0, ?string $directory = null)
    {
        parent::__construct(Rect::of(0, 0, 58, 20), 'Change Directory');
        $this->directory = FilePath::existingDirectory($directory);
        $this->dirInput = new InputLine(Rect::of(3, 3, 33, 4), 260);
        $this->insert($this->dirInput);
        $this->insert(new Label(Rect::of(2, 2, 21, 3), 'Directory ~n~ame', $this->dirInput));
        $this->insert(new History(Rect::of(33, 3, 36, 4), $this->dirInput, $historyId));
        $scrollBar = new ScrollBar(Rect::of(35, 6, 36, 16));
        $this->insert($scrollBar);
        $this->dirList = new DirListBox(Rect::of(3, 6, 35, 16), $scrollBar);
        $this->insert($this->dirList);
        $this->insert(new Label(Rect::of(2, 5, 21, 6), 'Directory ~t~ree', $this->dirList));
        $this->insert(new Button(Rect::of(39, 6, 51, 8), 'O~K~', Cmd::Ok, Button::Default));
        $this->insert(new Button(Rect::of(39, 9, 51, 11), '~C~hdir', FileCommand::ChangeDir));
        $this->insert(new Button(Rect::of(39, 12, 51, 14), '~R~evert', FileCommand::Revert));
        if (($options & self::HelpButton) !== 0) {
            $this->insert(new Button(Rect::of(39, 15, 51, 17), 'Help', Cmd::Help));
        }
        $this->setCurrent($this->dirInput);
        if (($options & self::NoLoadDir) === 0) {
            $this->setUpDialog();
        }
    }

    public function getData(): mixed
    {
        return $this->dirInput->text();
    }

    public function setData(mixed $data): void
    {
        if (! is_string($data)) {
            return;
        }
        $this->directory = FilePath::existingDirectory($data);
        $this->setUpDialog();
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);
        if ($event->what !== EventType::Command) {
            return;
        }
        $message = $event->asMessage();
        if ($message === null) {
            return;
        }
        $command = $message->command;
        if ($command === FileCommand::Revert) {
            $this->directory = FilePath::existingDirectory();
            $this->setUpDialog();
            $event->clear();
        } elseif ($command === FileCommand::ChangeDir) {
            $selected = $message->info;
            $entry = $this->dirList->entry($this->dirList->focused);
            $this->directory = $selected instanceof \HelgeSverre\TurboVision\Collections\DirEntry
                ? FilePath::existingDirectory($selected->dir)
                : ($entry === null ? $this->directory : $entry->dir);
            $this->setUpDialog();
            $this->dirList->select();
            $event->clear();
        }
    }

    public function valid(int $command): bool
    {
        if (! parent::valid($command) || $command !== Cmd::Ok) {
            return $command !== Cmd::Ok;
        }
        $path = FilePath::normalise($this->dirInput->text(), $this->directory);
        if (! is_dir($path) || ! is_readable($path)) {
            return false;
        }
        $resolved = FilePath::existingDirectory($path);
        if (! @chdir($resolved)) {
            return false;
        }
        $this->directory = $resolved;

        return true;
    }

    private function setUpDialog(): void
    {
        $this->dirList->newDirectory($this->directory);
        $this->dirInput->setText($this->directory);
    }
}
