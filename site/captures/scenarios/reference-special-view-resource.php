<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Dialogs\Button;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Dialogs\InputLine;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Resources\ViewResource;
use HelgeSverre\TurboVision\Resources\ViewResourceNode;
use HelgeSverre\TurboVision\Resources\ViewResourceRegistry;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\View;
use TurboVisionDocs\Captures\CaptureScenario;

return new CaptureScenario(
    id: 'reference/special-view-resource',
    columns: 80,
    rows: 25,
    factory: static fn (Screen $screen): Application => new class($screen) extends Application
    {
        protected function initDeskTop(Rect $bounds): Desktop
        {
            $desktop = new Desktop($bounds);
            $resource = new ViewResource(new ViewResourceNode(
                'demo.dialog',
                Rect::of(17, 3, 63, 16),
                ['title' => 'Saved dialog'],
                [
                    new ViewResourceNode('demo.text', Rect::of(3, 2, 40, 5), [
                        'text' => "This dialog was rebuilt from\na declarative resource tree.",
                    ]),
                    new ViewResourceNode('demo.input', Rect::of(4, 7, 35, 8), [
                        'capacity' => 32,
                        'text' => 'stored field value',
                    ]),
                    new ViewResourceNode('demo.button', Rect::of(30, 9, 42, 11), [
                        'title' => '~O~K',
                        'command' => Cmd::Ok,
                    ]),
                ],
            ));

            $registry = new ViewResourceRegistry;
            $registry->register('demo.dialog', static fn (ViewResourceNode $node): View => new Dialog($node->bounds, $node->string('title')));
            $registry->register('demo.text', static fn (ViewResourceNode $node): View => new StaticText($node->bounds, $node->string('text')));
            $registry->register('demo.input', static function (ViewResourceNode $node): View {
                $input = new InputLine($node->bounds, $node->integer('capacity'));
                $input->setText($node->string('text'));

                return $input;
            });
            $registry->register('demo.button', static fn (ViewResourceNode $node): View => new Button(
                $node->bounds,
                $node->string('title'),
                $node->integer('command'),
            ));

            $dialog = $resource->build($registry);
            if (! $dialog instanceof Dialog) {
                throw new LogicException('The demo resource must create a dialog.');
            }
            $desktop->insertWindow($dialog);

            return $desktop;
        }
    },
);
