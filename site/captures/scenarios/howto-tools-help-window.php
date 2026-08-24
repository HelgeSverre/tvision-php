<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Help\HelpFile;
use HelgeSverre\TurboVision\Help\HelpParagraph;
use HelgeSverre\TurboVision\Help\HelpTopic;
use HelgeSverre\TurboVision\Help\HelpWindow;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\Window;
use TurboVisionDocs\Captures\CaptureScenario;

return new CaptureScenario(
    id: 'howto-tools/help-window',
    columns: 80,
    rows: 25,
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        private HelpFile $help;

        public function __construct(Screen $screen)
        {
            $this->help = new HelpFile([
                1010 => new HelpTopic([
                    new HelpParagraph('Products'),
                    new HelpParagraph('Use the arrow keys to choose a product. Press Enter to inspect its details.'),
                    new HelpParagraph('Press Esc to return to the product list.'),
                ]),
            ]);
            parent::__construct($screen);
        }

        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);
            $window = new Window(Rect::of(9, 4, 70, 19), 'Products', 1);
            $window->insert(StaticText::centered(
                Rect::of(3, 2, 57, 10),
                "Product list\n\n"
                . "  Terminal cable\n"
                . "  Blue floppy disks\n"
                . "  Desk reference stand",
            ));
            $desktop->insertWindow($window);

            return $desktop;
        }

        public function openHelpForCapture(): void
        {
            $this->desktopForTest()?->insertWindow(new HelpWindow($this->help, 1010));
        }
    },
    prepare: static function (Application $application): void {
        $application->openHelpForCapture();
    },
);
