<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

/**
 * Editor search/replace flags, retaining the original ef* bit values.
 *
 * Honored today: CaseSensitive, WholeWordsOnly, BackupFiles. Replacement is
 * always whole-document (ReplaceAll semantics); PromptOnReplace and DoReplace
 * are reserved bit values accepted for source compatibility but not yet wired
 * to an interactive prompt — see Editor::setDialogHandler() for the extension
 * point applications can use for custom find/replace UIs.
 */
final class SearchOptions
{
    public const int CaseSensitive = 0x0001;
    public const int WholeWordsOnly = 0x0002;
    public const int PromptOnReplace = 0x0004;
    public const int ReplaceAll = 0x0008;
    public const int DoReplace = 0x0010;
    public const int BackupFiles = 0x0100;
}
