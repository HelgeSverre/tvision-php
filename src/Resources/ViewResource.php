<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Resources;

use HelgeSverre\TurboVision\Persistence\PersistenceException;
use HelgeSverre\TurboVision\Persistence\Streamable;
use HelgeSverre\TurboVision\Persistence\StreamableType;
use HelgeSverre\TurboVision\Views\View;

/**
 * A serializable declarative view tree.
 *
 * This stores constructor inputs and children, never a live View. In particular,
 * ownership, screen references, frames, focus, drag state, and cursor state are
 * recreated by the runtime and cannot enter a resource file.
 */
final readonly class ViewResource implements Streamable
{
    use StreamableType;

    public const string STREAM_TYPE = 'tvision.view-resource';

    public function __construct(public ViewResourceNode $root) {}

    /** @return array{root:array<string, mixed>} */
    public function streamData(): array
    {
        return ['root' => $this->root->toArray()];
    }

    /** @param array<string, mixed> $data */
    public static function fromStreamData(array $data): static
    {
        if (count($data) !== 1 || ! array_key_exists('root', $data) || ! is_array($data['root'])) {
            throw new PersistenceException('A view resource must contain exactly one root object.');
        }

        return new self(ViewResourceNode::fromArray($data['root']));
    }

    /** Build a fresh, fully owned runtime tree using explicitly registered factories. */
    public function build(ViewResourceRegistry $registry): View
    {
        return $registry->build($this->root);
    }
}
