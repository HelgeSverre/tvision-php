<?php

declare(strict_types=1);

namespace TurboVisionDocs\Captures;

use Closure;
use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Terminal\Screen;
use InvalidArgumentException;

final readonly class CaptureScenario
{
    /** @var Closure(Screen, HeadlessDriver): Application */
    public Closure $factory;

    /** @var null|Closure(Application, HeadlessDriver): void */
    public ?Closure $prepare;

    /**
     * @param callable(Screen, HeadlessDriver): Application $factory
     * @param null|callable(Application, HeadlessDriver): void $prepare
     */
    public function __construct(
        public string $id,
        callable $factory,
        public int $columns = 80,
        public int $rows = 25,
        ?callable $prepare = null,
    ) {
        if (preg_match('#^[a-z0-9]+(?:[/-][a-z0-9]+)*$#', $id) !== 1) {
            throw new InvalidArgumentException("Invalid capture id: {$id}");
        }

        if ($columns < 40 || $columns > 200 || $rows < 10 || $rows > 80) {
            throw new InvalidArgumentException("Capture {$id} has unsupported dimensions {$columns}x{$rows}");
        }

        $this->factory = Closure::fromCallable($factory);
        $this->prepare = $prepare === null ? null : Closure::fromCallable($prepare);
    }

    public function publicPath(): string
    {
        return "/captures/{$this->id}.png";
    }
}
