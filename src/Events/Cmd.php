<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Events;

/**
 * Standard command codes (faithful to Turbo Vision's cmXxx). Commands are plain
 * ints on the wire; application commands are integers >= Cmd::FirstUser.
 */
final class Cmd
{
    public const int Valid = 0;
    public const int Quit = 1;
    public const int Error = 2;
    public const int Menu = 3;
    public const int Close = 4;
    public const int Zoom = 5;
    public const int Resize = 6;
    public const int Next = 7;
    public const int Prev = 8;
    public const int Help = 9;
    public const int Ok = 10;
    public const int Cancel = 11;
    public const int Yes = 12;
    public const int No = 13;
    public const int Default = 14;

    /** Application-defined commands should start here. */
    public const int FirstUser = 100;

    // --- M2: window management (faithful to views.h) ---
    public const int CloseAll = 37;

    // --- M2: broadcast command codes (views.h cmReceivedFocus..cmListItemSelected) ---
    public const int ReceivedFocus = 50;
    public const int ReleasedFocus = 51;
    public const int CommandSetChanged = 52;
    public const int ScrollBarChanged = 53;
    public const int ScrollBarClicked = 54;
    public const int SelectWindowNum = 55;
    public const int ListItemSelected = 56;
}
