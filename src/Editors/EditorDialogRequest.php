<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

/** A typed, application-owned replacement for TEditorDialog's varargs callback. */
final readonly class EditorDialogRequest
{
    /** @param array<string, scalar|null> $context */
    public function __construct(
        public EditorDialogKind $kind,
        public array $context = [],
    ) {}
}
