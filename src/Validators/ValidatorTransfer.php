<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Validators;

/** The operation requested by an InputLine data-transfer hook. */
enum ValidatorTransfer
{
    /** Ask how many bytes the native representation occupies, or 0 for text. */
    case DataSize;

    /** Format a native value into the input line's text. */
    case SetData;

    /** Parse the input line's text into a native value. */
    case GetData;
}
