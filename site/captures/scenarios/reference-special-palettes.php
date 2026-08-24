<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Application\PaletteMode;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\Window;
use HelgeSverre\TurboVision\Views\Window\WindowPalette;
use TurboVisionDocs\Captures\CaptureScenario;

return new CaptureScenario(
    id: 'reference/special-palettes',
    columns: 80,
    rows: 25,
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        public function __construct(Screen $screen)
        {
            parent::__construct($screen);
            $this->setPaletteMode(PaletteMode::ClassicColor);
        }

        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);

            $blue = new Window(Rect::of(5, 3, 45, 13), 'Blue window', 1);
            $blue->insert(StaticText::centered(
                Rect::of(2, 2, 38, 7),
                "ClassicColor root palette\nwith the blue window mapping.",
            ));
            $desktop->insertWindow($blue);

            $cyan = new Window(Rect::of(34, 9, 75, 20), 'Cyan window', 2);
            $cyan->setPalette(WindowPalette::Cyan);
            $cyan->insert(StaticText::centered(
                Rect::of(2, 2, 39, 8),
                "A child palette remaps\nlogical view colors before\nthe root resolves them.",
            ));
            $desktop->insertWindow($cyan);

            return $desktop;
        }
    },
);
