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
    /** Turbo Vision's kbNoKey; useful for APIs that need an explicit sentinel. */
    case None = 0x0000;

    // ASCII control mnemonics. They intentionally differ from the extended
    // Tab, Enter, and Backspace key codes below, just as in tkeys.h.
    case CtrlA = 0x0001;
    case CtrlB = 0x0002;
    case CtrlC = 0x0003;
    case CtrlD = 0x0004;
    case CtrlE = 0x0005;
    case CtrlF = 0x0006;
    case CtrlG = 0x0007;
    case CtrlH = 0x0008;
    case CtrlI = 0x0009;
    case CtrlJ = 0x000A;
    case CtrlK = 0x000B;
    case CtrlL = 0x000C;
    case CtrlM = 0x000D;
    case CtrlN = 0x000E;
    case CtrlO = 0x000F;
    case CtrlP = 0x0010;
    case CtrlQ = 0x0011;
    case CtrlR = 0x0012;
    case CtrlS = 0x0013;
    case CtrlT = 0x0014;
    case CtrlU = 0x0015;
    case CtrlV = 0x0016;
    case CtrlW = 0x0017;
    case CtrlX = 0x0018;
    case CtrlY = 0x0019;
    case CtrlZ = 0x001A;

    // Extended control keys.
    case Esc = 0x011B;
    case AltSpace = 0x0200;
    case CtrlInsert = 0x0400;
    case ShiftInsert = 0x0500;
    case CtrlDelete = 0x0600;
    case ShiftDelete = 0x0700;
    case AltBackspace = 0x0800;
    case Enter = 0x1C0D;
    case Tab = 0x0F09;
    case ShiftTab = 0x0F00;
    case Backspace = 0x0E08;
    case CtrlBackspace = 0x0E7F;
    case CtrlEnter = 0x1C0A;

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
    case GrayMinus = 0x4A2D;
    case GrayPlus = 0x4E2B;

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

    // Shift + function keys.
    case ShiftF1 = 0x5400;
    case ShiftF2 = 0x5500;
    case ShiftF3 = 0x5600;
    case ShiftF4 = 0x5700;
    case ShiftF5 = 0x5800;
    case ShiftF6 = 0x5900;
    case ShiftF7 = 0x5A00;
    case ShiftF8 = 0x5B00;
    case ShiftF9 = 0x5C00;
    case ShiftF10 = 0x5D00;

    // Ctrl + function keys and navigation.
    case CtrlF1 = 0x5E00;
    case CtrlF2 = 0x5F00;
    case CtrlF3 = 0x6000;
    case CtrlF4 = 0x6100;
    case CtrlF5 = 0x6200;
    case CtrlF6 = 0x6300;
    case CtrlF7 = 0x6400;
    case CtrlF8 = 0x6500;
    case CtrlF9 = 0x6600;
    case CtrlF10 = 0x6700;
    case CtrlPrintScreen = 0x7200;
    case CtrlLeft = 0x7300;
    case CtrlRight = 0x7400;
    case CtrlEnd = 0x7500;
    case CtrlPageDown = 0x7600;
    case CtrlHome = 0x7700;
    case CtrlPageUp = 0x8400;

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

    // Alt + function keys.
    case AltF1 = 0x6800;
    case AltF2 = 0x6900;
    case AltF3 = 0x6A00;
    case AltF4 = 0x6B00;
    case AltF5 = 0x6C00;
    case AltF6 = 0x6D00;
    case AltF7 = 0x6E00;
    case AltF8 = 0x6F00;
    case AltF9 = 0x7000;
    case AltF10 = 0x7100;

    // Alt + digits and punctuation, primarily used for window selection.
    case Alt1 = 0x7800;
    case Alt2 = 0x7900;
    case Alt3 = 0x7A00;
    case Alt4 = 0x7B00;
    case Alt5 = 0x7C00;
    case Alt6 = 0x7D00;
    case Alt7 = 0x7E00;
    case Alt8 = 0x7F00;
    case Alt9 = 0x8000;
    case Alt0 = 0x8100;
    case AltMinus = 0x8200;
    case AltEqual = 0x8300;
}
