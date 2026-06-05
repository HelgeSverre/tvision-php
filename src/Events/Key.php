<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Events;

/**
 * Special (non-printable) keys, by their original Turbo Vision kbXxx codes
 * (scan << 8 | ascii). Source: docs/references/source/tvision-0.8/lib/tkeys.h.
 * Printable characters are not enumerated here — they travel as KeyDownEvent::$char.
 */
enum Key: int
{
    // Control keys
    case Esc = 0x011B;
    case Enter = 0x1C0D;
    case Tab = 0x0F09;
    case ShiftTab = 0x0F00;
    case Backspace = 0x0E08;

    // Navigation
    case Up = 0x4800;
    case Down = 0x5000;
    case Left = 0x4B00;
    case Right = 0x4D00;
    case Home = 0x4700;
    case End = 0x4F00;
    case PageUp = 0x4900;
    case PageDown = 0x5100;
    case Insert = 0x5200;
    case Delete = 0x5300;

    // Function keys
    case F1 = 0x3B00;
    case F2 = 0x3C00;
    case F3 = 0x3D00;
    case F4 = 0x3E00;
    case F5 = 0x3F00;
    case F6 = 0x4000;
    case F7 = 0x4100;
    case F8 = 0x4200;
    case F9 = 0x4300;
    case F10 = 0x4400;
    case F11 = 0x8500;
    case F12 = 0x8600;

    // Alt + letter (menu hotkeys)
    case AltA = 0x1E00;
    case AltB = 0x3000;
    case AltC = 0x2E00;
    case AltD = 0x2000;
    case AltE = 0x1200;
    case AltF = 0x2100;
    case AltG = 0x2200;
    case AltH = 0x2300;
    case AltI = 0x1700;
    case AltJ = 0x2400;
    case AltK = 0x2500;
    case AltL = 0x2600;
    case AltM = 0x3200;
    case AltN = 0x3100;
    case AltO = 0x1800;
    case AltP = 0x1900;
    case AltQ = 0x1000;
    case AltR = 0x1300;
    case AltS = 0x1F00;
    case AltT = 0x1400;
    case AltU = 0x1600;
    case AltV = 0x2F00;
    case AltW = 0x1100;
    case AltX = 0x2D00;
    case AltY = 0x1500;
    case AltZ = 0x2C00;
}
