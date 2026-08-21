<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Legacy;

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Dialogs\FileDialog;
use HelgeSverre\TurboVision\Editors\EditWindow;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Desktop;

/**
 * Compact smoke port of tvedit.cc. It intentionally delegates editing and file
 * selection to the framework's EditWindow/FileDialog instead of reproducing them.
 */
final class TveditApp extends Application
{
    private ?EditWindow $editorWindow = null;

    protected function initDeskTop(Rect $bounds): Desktop
    {
        $desktop = parent::initDeskTop($bounds);
        assert($desktop instanceof Desktop);
        $this->editorWindow = $this->createEditorWindow();
        $desktop->insertWindow($this->editorWindow);

        return $desktop;
    }

    public function createEditorWindow(?string $fileName = null): EditWindow
    {
        return new EditWindow(Rect::of(2, 1, 76, 22), $fileName);
    }

    public function createOpenDialog(string $wildCard = '*.txt', ?string $directory = null): FileDialog
    {
        return new FileDialog($wildCard, 'Open file', '~F~ile name', FileDialog::OpenButton, 10, $directory);
    }

    public function editorWindowForTest(): EditWindow
    {
        assert($this->editorWindow instanceof EditWindow);

        return $this->editorWindow;
    }
}
