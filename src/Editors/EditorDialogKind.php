<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

/** Semantic editor-dialog requests, preserving the original ed* identifiers. */
enum EditorDialogKind: int
{
    case OutOfMemory = 0;
    case ReadError = 1;
    case WriteError = 2;
    case CreateError = 3;
    case SaveModified = 4;
    case SaveUntitled = 5;
    case SaveAs = 6;
    case Find = 7;
    case SearchFailed = 8;
    case Replace = 9;
    case ReplacePrompt = 10;
}
