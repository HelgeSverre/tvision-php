<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Editors;

/**
 * Semantic editor-dialog requests, preserving the original ed* identifiers.
 *
 * The framework emits ReadError, WriteError, CreateError, SaveModified,
 * SaveUntitled, SaveAs, and SearchFailed. Find, Replace, ReplacePrompt, and
 * OutOfMemory are reserved identifiers applications may emit from their own
 * dialog handlers; the built-in editor never raises them yet.
 */
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
