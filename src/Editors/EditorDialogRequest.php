<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

/**
 * A typed, application-owned replacement for TEditorDialog's varargs callback.
 * ReplacePrompt context contains `find`, `replace`, `match`, and zero-based
 * `offset`, `line`, and `column` values from the pre-replacement document.
 */
final readonly class EditorDialogRequest
{
    /** @param array<string, scalar|null> $context */
    public function __construct(
        public EditorDialogKind $kind,
        public array $context = [],
    ) {}
}
