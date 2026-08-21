<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Validators;

/**
 * Result states produced by PictureValidator::picture().
 *
 * Ambiguous and IncompleteWithoutFill are reserved historical states the
 * current matcher never produces; kept so downstream switches stay stable.
 */
enum PicResult
{
    case Complete;
    case Incomplete;
    case Empty;
    case Error;
    case Syntax;

    /** Reserved; never produced by the current matcher. */
    case Ambiguous;

    /** Reserved; never produced by the current matcher. */
    case IncompleteWithoutFill;
}
