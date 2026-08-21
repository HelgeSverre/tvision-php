<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Events;

/**
 * Standard command codes (faithful to Turbo Vision's cmXxx). Commands are plain
 * ints on the wire. The historical FirstUser marker is kept for compatibility,
 * but 100-103 and the other documented ranges below are framework-reserved.
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

    // Standard edit, desktop, and application commands (views.h).
    public const int Cut = 20;
    public const int Copy = 21;
    public const int Paste = 22;
    public const int Undo = 23;
    public const int Clear = 24;
    public const int Tile = 25;
    public const int Cascade = 26;

    public const int New = 30;
    public const int Open = 31;
    public const int Save = 32;
    public const int SaveAs = 33;
    public const int SaveAll = 34;
    public const int ChDir = 35;
    public const int DosShell = 36;

    // Internal program commands (the 0.8 port's views.h extensions).
    public const int SysRepaint = 38;
    public const int SysResize = 39;
    public const int SysWakeup = 40;

    // Dialog and button broadcasts (dialogs.h and TButton.cc).
    public const int RecordHistory = 60;
    public const int GrabDefault = 61;
    public const int ReleaseDefault = 62;

    // Interactive colour selector commands (colorsel.h).
    public const int ColorForegroundChanged = 71;
    public const int ColorBackgroundChanged = 72;
    public const int ColorSet = 73;
    public const int NewColorItem = 74;
    public const int NewColorIndex = 75;
    public const int SaveColorIndex = 76;

    // Editor commands (editors.h).
    public const int Find = 82;
    public const int Replace = 83;
    public const int SearchAgain = 84;

    /**
     * Historical application-command marker retained for source compatibility.
     * Prefer FirstSafeUser for new commands: file-dialog broadcasts occupy
     * 102-103 and file-dialog return values start at 1001.
     */
    public const int FirstUser = 100;

    /** First collision-free application command value. */
    public const int FirstSafeUser = 200;

    // Window management (faithful to views.h).
    public const int CloseAll = 37;

    // Broadcast commands (views.h cmReceivedFocus..cmListItemSelected).
    public const int ReceivedFocus = 50;
    public const int ReleasedFocus = 51;
    public const int CommandSetChanged = 52;
    public const int ScrollBarChanged = 53;
    public const int ScrollBarClicked = 54;
    public const int SelectWindowNum = 55;
    public const int ListItemSelected = 56;

    // Standard file-dialog broadcasts (stddlg.h).
    public const int FileFocused = 102;
    public const int FileDoubleClicked = 103;

    // Standard file-dialog return and internal commands (stddlg.h).
    public const int FileOpen = 1001;
    public const int FileReplace = 1002;
    public const int FileClear = 1003;
    public const int FileInit = 1004;
    public const int ChangeDir = 1005;
    public const int Revert = 1006;
    public const int DirSelection = 1007;

    // Outline viewer broadcast (outline.h).
    public const int OutlineItemSelected = 301;

    // Internal editor actions (editors.h). These are intentionally separate
    // from the application command range because controls consume them.
    public const int CharLeft = 500;
    public const int CharRight = 501;
    public const int WordLeft = 502;
    public const int WordRight = 503;
    public const int LineStart = 504;
    public const int LineEnd = 505;
    public const int LineUp = 506;
    public const int LineDown = 507;
    public const int PageUp = 508;
    public const int PageDown = 509;
    public const int TextStart = 510;
    public const int TextEnd = 511;
    public const int NewLine = 512;
    public const int BackSpace = 513;
    public const int DelChar = 514;
    public const int DelWord = 515;
    public const int DelStart = 516;
    public const int DelEnd = 517;
    public const int DelLine = 518;
    public const int InsMode = 519;
    public const int StartSelect = 520;
    public const int HideSelect = 521;
    public const int IndentMode = 522;
    public const int UpdateTitle = 523;
}
