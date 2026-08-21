<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Legacy;

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Dialogs\ParamText;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;
use HelgeSverre\TurboVision\Views\Window;

/**
 * A deliberately compact, custom-content port of tvlife.cc.
 *
 * The board is application content rather than a framework control; the window,
 * focus, palette and redraw lifecycle all come from the reusable framework.
 */
final class LifeBoard extends View
{
    /** @var array<string, true> */
    private array $alive = [];

    private int $generation = 0;

    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        $this->options |= State::Selectable | State::FirstClick;
        foreach ([[1, 0], [2, 1], [0, 2], [1, 2], [2, 2]] as [$x, $y]) {
            $this->alive[$this->key($x, $y)] = true;
        }
    }

    public function getPalette(): Palette
    {
        return Palette::fromBytes("\x06\x0A");
    }

    public function generation(): int
    {
        return $this->generation;
    }

    public function advance(): void
    {
        $neighbors = [];
        foreach (array_keys($this->alive) as $key) {
            [$x, $y] = array_map('intval', explode(':', $key));
            for ($dy = -1; $dy <= 1; $dy++) {
                for ($dx = -1; $dx <= 1; $dx++) {
                    if ($dx === 0 && $dy === 0) {
                        continue;
                    }
                    $nx = $x + $dx;
                    $ny = $y + $dy;
                    if ($nx < 0 || $ny < 0 || $nx >= $this->bounds->width() || $ny >= $this->bounds->height()) {
                        continue;
                    }
                    $neighborKey = $this->key($nx, $ny);
                    $neighbors[$neighborKey] = ($neighbors[$neighborKey] ?? 0) + 1;
                }
            }
        }
        $next = [];
        foreach ($neighbors as $key => $count) {
            if ($count === 3 || ($count === 2 && isset($this->alive[$key]))) {
                $next[$key] = true;
            }
        }
        $this->alive = $next;
        $this->generation++;
        $this->drawView();
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $normal = $this->mapColor(1);
        $live = $this->mapColor(2);
        for ($y = 0; $y < $this->bounds->height(); $y++) {
            $buffer = new DrawBuffer($width);
            $buffer->moveChar(0, '·', $normal, $width);
            foreach ($this->alive as $key => $_) {
                [$x, $cellY] = array_map('intval', explode(':', $key));
                if ($cellY === $y) {
                    $buffer->moveChar($x, '●', $live, 1);
                }
            }
            $this->writeLine(0, $y, $width, 1, $buffer);
        }
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what === EventType::KeyDown && $event->asKey()?->char === ' ') {
            $this->advance();
            $event->clear();
        }
    }

    private function key(int $x, int $y): string
    {
        return $x . ':' . $y;
    }
}

final class LifeApp extends Application
{
    private ?LifeBoard $board = null;

    protected function initDeskTop(Rect $bounds): Desktop
    {
        $desktop = parent::initDeskTop($bounds);
        assert($desktop instanceof Desktop);
        $width = min(48, max(16, $bounds->width() - 4));
        $height = min(18, max(8, $bounds->height() - 2));
        $window = new Window(Rect::of(2, 1, 2 + $width, 1 + $height), 'Conway life');
        $this->board = new LifeBoard(Rect::of(1, 1, $width - 1, $height - 3));
        $window->insert($this->board);
        $window->insert(new ParamText(Rect::of(2, $height - 2, $width - 2, $height - 1), 'Space advances generation'));
        $desktop->insertWindow($window);

        return $desktop;
    }

    public function boardForTest(): LifeBoard
    {
        assert($this->board instanceof LifeBoard);

        return $this->board;
    }
}
