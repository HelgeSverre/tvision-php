<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

/**
 * Editor search/replace flags, retaining the original ef* bit values.
 *
 * CaseSensitive and WholeWordsOnly affect matching. ReplaceRequest additionally
 * honors DoReplace, ReplaceAll, and PromptOnReplace; prompt decisions are supplied
 * through Editor::setDialogHandler(). BackupFiles is consumed by FileEditor.
 * Editor::replaceAll() remains an explicit unconditional bulk operation.
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
