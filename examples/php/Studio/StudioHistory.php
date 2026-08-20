<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Studio;

final class StudioHistory
{
    /** @var list<array<string, mixed>> */
    private array $undo = [];

    /** @var list<array<string, mixed>> */
    private array $redo = [];

    public function __construct(private readonly int $limit = 64) {}

    public function remember(StudioProject $project): void
    {
        $this->undo[] = $project->toArray();
        if (count($this->undo) > $this->limit) {
            array_shift($this->undo);
        }
        $this->redo = [];
    }

    public function undo(StudioProject $current): ?StudioProject
    {
        $snapshot = array_pop($this->undo);
        if ($snapshot === null) {
            return null;
        }
        $this->redo[] = $current->toArray();

        return StudioProject::fromArray($snapshot);
    }

    public function redo(StudioProject $current): ?StudioProject
    {
        $snapshot = array_pop($this->redo);
        if ($snapshot === null) {
            return null;
        }
        $this->undo[] = $current->toArray();

        return StudioProject::fromArray($snapshot);
    }

    public function undoCount(): int
    {
        return count($this->undo);
    }

    public function redoCount(): int
    {
        return count($this->redo);
    }

    public function clear(): void
    {
        $this->undo = [];
        $this->redo = [];
    }
}
