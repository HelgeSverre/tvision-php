<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Collections\SearchRec;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;

/** Standard open/save file dialog, using PHP filesystem APIs only. */
final class FileDialog extends Dialog
{
    public const int OkButton = 0x0001;
    public const int OpenButton = 0x0002;
    public const int ReplaceButton = 0x0004;
    public const int ClearButton = 0x0008;
    public const int HelpButton = 0x0010;
    public const int NoLoadDir = 0x0100;

    public FileInputLine $fileName;

    public FileList $fileList;

    public FileInfoPane $fileInfoPane;

    public string $wildCard;

    public string $directory;

    public function __construct(
        string $wildCard = '*',
        string $title = 'Open file',
        string $inputName = '~F~ile name',
        int $options = self::OpenButton,
        int $historyId = 0,
        ?string $directory = null,
    ) {
        parent::__construct(Rect::of(0, 0, 64, 20), $title);
        $split = FilePath::splitPattern($wildCard, $directory);
        $this->directory = $split['directory'];
        $this->wildCard = $split['pattern'];

        $this->fileName = new FileInputLine(Rect::of(3, 3, 34, 4), 260);
        $this->fileName->setText($this->wildCard);
        $this->insert($this->fileName);
        $this->insert(new Label(Rect::of(2, 2, 22, 3), $inputName, $this->fileName));
        $this->insert(new History(Rect::of(34, 3, 37, 4), $this->fileName, $historyId));

        $scrollBar = new ScrollBar(Rect::of(34, 6, 35, 14));
        $this->insert($scrollBar);
        $this->fileList = new FileList(Rect::of(3, 6, 34, 14), $scrollBar);
        $this->insert($this->fileList);
        $this->insert(new Label(Rect::of(2, 5, 12, 6), '~F~iles', $this->fileList));

        $buttonY = 3;
        $default = Button::Default;
        foreach ($this->buttonsFor($options) as [$caption, $command]) {
            $this->insert(new Button(Rect::of(38, $buttonY, 51, $buttonY + 2), $caption, $command, $default));
            $default = Button::Normal;
            $buttonY += 3;
        }
        $this->insert(new Button(Rect::of(38, $buttonY, 51, $buttonY + 2), 'Cancel', Cmd::Cancel));
        $buttonY += 3;
        if (($options & self::HelpButton) !== 0) {
            $this->insert(new Button(Rect::of(38, $buttonY, 51, $buttonY + 2), '~H~elp', Cmd::Help));
        }

        $this->fileInfoPane = new FileInfoPane(Rect::of(2, 16, 62, 18));
        $this->insert($this->fileInfoPane);
        $this->setCurrent($this->fileName);

        if (($options & self::NoLoadDir) === 0) {
            $this->readDirectory();
        }
    }

    public function getFileName(): string
    {
        $text = trim($this->fileName->text());
        if ($text === '') {
            return '';
        }

        return FilePath::normalise($text, $this->directory);
    }

    public function getData(): mixed
    {
        return $this->getFileName();
    }

    public function setData(mixed $data): void
    {
        if (! is_string($data)) {
            return;
        }
        $this->fileName->setText($data);
        if (FilePath::hasWildcard($data)) {
            $this->valid(FileCommand::Init);
        }
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);
        if ($event->what === EventType::Broadcast && $event->isCommand(FileCommand::DoubleClicked)) {
            $entry = $event->asMessage()?->info;
            if ($entry instanceof SearchRec) {
                $this->activateEntry($entry);
            }
            $event->clear();

            return;
        }
        if ($event->what !== EventType::Command) {
            return;
        }
        $command = $event->asMessage()?->command;
        if (! in_array($command, [FileCommand::Open, FileCommand::Replace, FileCommand::Clear], true)) {
            return;
        }
        if ($command === FileCommand::Clear || $this->valid($command)) {
            $this->endModal($command);
        }
        $event->clear();
    }

    public function valid(int $command): bool
    {
        if (! parent::valid($command) || in_array($command, [Cmd::Cancel, FileCommand::Clear], true)) {
            return $command === Cmd::Cancel || $command === FileCommand::Clear;
        }
        $text = trim($this->fileName->text());
        if ($text === '') {
            return false;
        }

        $name = $this->getFileName();
        if (FilePath::hasWildcard($text)) {
            $split = FilePath::splitPattern($text, $this->directory);
            if (! is_dir($split['directory'])) {
                return false;
            }
            $this->directory = $split['directory'];
            $this->wildCard = $split['pattern'];
            $this->readDirectory();

            return false;
        }
        if (is_dir($name)) {
            $this->directory = FilePath::existingDirectory($name);
            $this->readDirectory();

            return false;
        }
        if (str_contains($name, "\0") || !is_dir(dirname($name))) {
            return false;
        }

        return basename($name) !== '' && basename($name) !== '.' && basename($name) !== '..';
    }

    private function readDirectory(): void
    {
        $this->fileList->readDirectory($this->directory, $this->wildCard);
    }

    private function activateEntry(SearchRec $entry): void
    {
        if ($entry->isDirectory()) {
            $this->directory = FilePath::existingDirectory($entry->path);
            $this->fileName->setText($this->wildCard);
            $this->readDirectory();

            return;
        }
        $this->fileName->setText($entry->name);
        $this->endModal(FileCommand::Open);
    }

    /** @return list<array{string, int}> */
    private function buttonsFor(int $options): array
    {
        $buttons = [];
        if (($options & self::OpenButton) !== 0) {
            $buttons[] = ['~O~pen', FileCommand::Open];
        }
        if (($options & self::OkButton) !== 0) {
            $buttons[] = ['O~K~', FileCommand::Open];
        }
        if (($options & self::ReplaceButton) !== 0) {
            $buttons[] = ['~R~eplace', FileCommand::Replace];
        }
        if (($options & self::ClearButton) !== 0) {
            $buttons[] = ['~C~lear', FileCommand::Clear];
        }

        return $buttons;
    }
}
