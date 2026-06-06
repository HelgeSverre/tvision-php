<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Views\Window;

/** wf* window flags (faithful to views.h). Default = all four. */
final class WindowFlags
{
    public const int Move = 0x01;
    public const int Grow = 0x02;
    public const int Close = 0x04;
    public const int Zoom = 0x08;
    public const int Default = self::Move | self::Grow | self::Close | self::Zoom;
}
