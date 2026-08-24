<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Help\CrossRef;
use HelgeSverre\TurboVision\Help\HelpFile;
use HelgeSverre\TurboVision\Help\HelpParagraph;
use HelgeSverre\TurboVision\Help\HelpTopic;
use HelgeSverre\TurboVision\Help\HelpWindow;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use TurboVisionDocs\Captures\CaptureScenario;

return new CaptureScenario(
    id: 'reference/special-help-window',
    columns: 80,
    rows: 25,
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);
            $text = 'Use the Getting started topic to learn the first controls. Tab selects a cross-reference and Enter follows it.';
            $file = new HelpFile([
                1 => new HelpTopic(
                    [new HelpParagraph($text)],
                    [new CrossRef(2, 8, 15, 'Getting started')],
                ),
                2 => new HelpTopic([new HelpParagraph('Build a view tree, then let the application draw and dispatch events.')]),
            ]);
            $desktop->insertWindow(new HelpWindow($file, 1));

            return $desktop;
        }
    },
);
