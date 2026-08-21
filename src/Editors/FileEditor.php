<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\ScrollBar;

/** A file-backed Editor with explicit, non-throwing load/save error reporting. */
class FileEditor extends Editor
{
    public ?string $fileName;

    public ?string $lastError = null;

    public bool $isValid = true;

    public function __construct(
        Rect $bounds,
        ?ScrollBar $hScrollBar = null,
        ?ScrollBar $vScrollBar = null,
        ?Indicator $indicator = null,
        ?string $fileName = null,
    ) {
        parent::__construct($bounds, $hScrollBar, $vScrollBar, $indicator);
        $this->fileName = $fileName === null || $fileName === '' ? null : $fileName;
        if ($this->fileName !== null) {
            $this->isValid = $this->loadFile();
        }
    }

    public function loadFile(?string $path = null): bool
    {
        if ($path !== null && $path !== '') {
            $this->fileName = $path;
        }
        if ($this->fileName === null) {
            $this->setText('');

            return true;
        }
        if (! file_exists($this->fileName)) {
            // An absent path is an untitled/new document, matching TFileEditor's
            // historical behaviour while retaining the requested save target.
            $this->setText('');
            $this->isValid = true;
            $this->lastError = null;

            return true;
        }
        if (! is_file($this->fileName) || ! is_readable($this->fileName)) {
            return $this->fail(EditorDialogKind::ReadError, sprintf('Unable to read "%s".', $this->fileName));
        }
        $text = @file_get_contents($this->fileName);
        if ($text === false) {
            return $this->fail(EditorDialogKind::ReadError, sprintf('Unable to read "%s".', $this->fileName));
        }
        if (! mb_check_encoding($text, 'UTF-8')) {
            return $this->fail(EditorDialogKind::ReadError, sprintf('"%s" is not valid UTF-8 text.', $this->fileName));
        }
        $this->setText($text);
        $this->isValid = true;
        $this->lastError = null;

        return true;
    }

    public function save(): bool
    {
        if ($this->fileName === null) {
            return $this->fail(EditorDialogKind::SaveUntitled, 'Cannot save an untitled file; use saveAs().');
        }

        return $this->saveFile();
    }

    public function saveAs(string $path): bool
    {
        if ($path === '') {
            return $this->fail(EditorDialogKind::SaveAs, 'A save path is required.');
        }
        $oldPath = $this->fileName;
        $this->fileName = $path;
        if (! $this->saveFile()) {
            $this->fileName = $oldPath;

            return false;
        }
        $this->owner?->handleEvent(Event::broadcast(Cmd::UpdateTitle, $this));

        return true;
    }

    public function saveFile(): bool
    {
        if ($this->fileName === null) {
            return $this->fail(EditorDialogKind::SaveAs, 'A save path is required.');
        }
        $directory = dirname($this->fileName);
        if (! is_dir($directory) || ! is_writable($directory)) {
            return $this->fail(EditorDialogKind::CreateError, sprintf('Unable to create "%s".', $this->fileName));
        }
        if (($this->editorFlags & SearchOptions::BackupFiles) !== 0 && is_file($this->fileName)) {
            if (! $this->createBackup($this->fileName)) {
                return $this->fail(EditorDialogKind::CreateError, sprintf('Unable to create backup for "%s".', $this->fileName));
            }
        }
        if (! $this->writeAtomically($this->fileName, $this->text())) {
            return $this->fail(EditorDialogKind::WriteError, sprintf('Unable to write "%s".', $this->fileName));
        }
        $this->modified = false;
        $this->lastError = null;
        $this->isValid = true;
        $this->drawView();

        return true;
    }

    /**
     * Turbo Vision validation hook. Unsaved edits require an explicit decision;
     * call resolveUnsaved() from a dialog-backed close workflow to provide one.
     */
    public function valid(int $command = Cmd::Valid): bool
    {
        if (! $this->isValid || ! $this->modified) {
            return $this->isValid;
        }
        return false;
    }

    /** Call before closing a file editor. The resolver receives this editor. */
    public function resolveUnsaved(callable $unsavedResolver): bool
    {
        if (! $this->isValid || ! $this->modified) {
            return $this->isValid;
        }
        $decision = $unsavedResolver($this);
        if ($decision === Cmd::Yes || $decision === true || $decision === 'save') {
            return $this->save();
        }
        if ($decision === Cmd::No || $decision === 'discard') {
            $this->discardChanges();

            return true;
        }

        return false;
    }

    /** Explicitly acknowledge abandoning the in-memory edits. */
    public function discardChanges(): void
    {
        $this->modified = false;
        $this->isValid = true;
        $this->lastError = null;
        $this->drawView();
    }

    /** Write in the destination directory so rename() is an atomic replacement. */
    private function writeAtomically(string $path, string $text): bool
    {
        $temporary = tempnam(dirname($path), '.tvision-editor-');
        if ($temporary === false) {
            return false;
        }

        try {
            $written = @file_put_contents($temporary, $text, LOCK_EX);
            if ($written === false || $written !== strlen($text)) {
                return false;
            }

            if (is_file($path)) {
                $permissions = @fileperms($path);
                if (is_int($permissions)) {
                    @chmod($temporary, $permissions & 0777);
                }
            }

            return @rename($temporary, $path);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /** Replace the backup atomically without ever moving the live document. */
    private function createBackup(string $path): bool
    {
        $temporary = tempnam(dirname($path), '.tvision-backup-');
        if ($temporary === false) {
            return false;
        }

        try {
            if (! @copy($path, $temporary)) {
                return false;
            }

            return @rename($temporary, $path . '~');
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    public function handleEvent(Event $event): void
    {
        parent::handleEvent($event);
        if ($event->isCommand(Cmd::Save)) {
            $this->save();
            $this->clearEvent($event);
        } elseif ($event->isCommand(Cmd::SaveAs)) {
            $path = $event->asMessage()?->info;
            if (is_string($path)) {
                $this->saveAs($path);
            } else {
                $this->lastError = 'Save As requires a destination path.';
            }
            $this->clearEvent($event);
        }
    }

    private function fail(EditorDialogKind $kind, string $message): bool
    {
        $this->lastError = $message;
        $this->isValid = false;
        $this->notify($kind, ['path' => $this->fileName, 'message' => $message]);

        return false;
    }
}
