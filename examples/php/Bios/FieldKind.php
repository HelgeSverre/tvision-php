<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Bios;

/**
 * The kind of a BIOS setup field.
 *
 * Info   – read-only display.
 * Text   – free-text edit.
 * Number – digit-only edit.
 * Cycle  – picks from a fixed list (← → or +/−).
 * Action – pressing Enter invokes a callback.
 */
enum FieldKind
{
    case Info;
    case Text;
    case Number;
    case Cycle;
    case Action;
}
