<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Validators;

/** Result states produced by PictureValidator::picture(). */
enum PicResult
{
    case Complete;
    case Incomplete;
    case Empty;
    case Error;
    case Syntax;
    case Ambiguous;
    case IncompleteWithoutFill;
}
