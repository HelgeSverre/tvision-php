<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Dialogs;

use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\StaticText;

/** Runtime-updatable, sprintf-formatted static text (Turbo Vision's TParamText). */
final class ParamText extends StaticText
{
    private string $value = '';

    public function __construct(Rect $bounds, string $text = '')
    {
        $this->value = $text;
        parent::__construct($bounds, $text);
    }

    public function setText(string $format, string|int|float|bool|null ...$args): void
    {
        $this->value = $args === [] ? $format : sprintf($format, ...$args);
        $this->text = $this->value;
        $this->drawView();
    }

    public function getText(): string
    {
        return $this->value;
    }

    public function getTextLen(): int
    {
        return mb_strlen($this->value);
    }
}
