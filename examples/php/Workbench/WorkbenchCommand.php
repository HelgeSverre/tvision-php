<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Workbench;

use HelgeSverre\TurboVision\Events\Cmd;

final class WorkbenchCommand
{
    public const int NewWorkspace = Cmd::FirstUser + 300;
    public const int OpenTasks = Cmd::FirstUser + 301;
    public const int OpenActivity = Cmd::FirstUser + 302;
    public const int SaveSnapshot = Cmd::FirstUser + 303;
    public const int CyclePalette = Cmd::FirstUser + 304;
    public const int KeyboardHelp = Cmd::FirstUser + 305;
    public const int About = Cmd::FirstUser + 306;
    public const int ConfirmNew = Cmd::FirstUser + 307;
    public const int CancelDialog = Cmd::FirstUser + 308;
    public const int TaskDetails = Cmd::FirstUser + 309;
    public const int Undo = Cmd::FirstUser + 310;
    public const int Redo = Cmd::FirstUser + 311;
    public const int CommandPalette = Cmd::FirstUser + 312;
}
