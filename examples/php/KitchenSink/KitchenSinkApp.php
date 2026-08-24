<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\KitchenSink;

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Application\PaletteMode;
use HelgeSverre\TurboVision\Color\ColorDialog;
use HelgeSverre\TurboVision\Color\ColorGroup;
use HelgeSverre\TurboVision\Color\ColorItem;
use HelgeSverre\TurboVision\Commands\CommandSet;
use HelgeSverre\TurboVision\Dialogs\Button;
use HelgeSverre\TurboVision\Dialogs\ChDirDialog;
use HelgeSverre\TurboVision\Dialogs\CheckBoxes;
use HelgeSverre\TurboVision\Dialogs\Dialog;
use HelgeSverre\TurboVision\Dialogs\FileCommand;
use HelgeSverre\TurboVision\Dialogs\FileDialog;
use HelgeSverre\TurboVision\Dialogs\History;
use HelgeSverre\TurboVision\Dialogs\InputLine;
use HelgeSverre\TurboVision\Dialogs\Label;
use HelgeSverre\TurboVision\Dialogs\ListBox;
use HelgeSverre\TurboVision\Dialogs\MessageBox;
use HelgeSverre\TurboVision\Dialogs\MsgBoxFlag;
use HelgeSverre\TurboVision\Dialogs\MultiCheckBoxes;
use HelgeSverre\TurboVision\Dialogs\ParamText;
use HelgeSverre\TurboVision\Dialogs\RadioButtons;
use HelgeSverre\TurboVision\Drawing\Palette;
use HelgeSverre\TurboVision\Drivers\AnsiDriver;
use HelgeSverre\TurboVision\Editors\EditWindow;
use HelgeSverre\TurboVision\Editors\EditorDialogKind;
use HelgeSverre\TurboVision\Editors\EditorDialogRequest;
use HelgeSverre\TurboVision\Editors\FileEditor;
use HelgeSverre\TurboVision\Editors\FindRequest;
use HelgeSverre\TurboVision\Editors\Indicator;
use HelgeSverre\TurboVision\Editors\Memo;
use HelgeSverre\TurboVision\Editors\ReplaceRequest;
use HelgeSverre\TurboVision\Editors\SearchOptions;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Help\CrossRef;
use HelgeSverre\TurboVision\Help\HelpFile;
use HelgeSverre\TurboVision\Help\HelpParagraph;
use HelgeSverre\TurboVision\Help\HelpTopic;
use HelgeSverre\TurboVision\Help\HelpWindow;
use HelgeSverre\TurboVision\Menus\Menu;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\MenuBox;
use HelgeSverre\TurboVision\Menus\MenuItem;
use HelgeSverre\TurboVision\Menus\MenuPopup;
use HelgeSverre\TurboVision\Menus\StatusDef;
use HelgeSverre\TurboVision\Menus\StatusItem;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Menus\SubMenu;
use HelgeSverre\TurboVision\Outline\Node;
use HelgeSverre\TurboVision\Outline\Outline;
use HelgeSverre\TurboVision\Persistence\StreamCodec;
use HelgeSverre\TurboVision\Persistence\StreamableRegistry;
use HelgeSverre\TurboVision\Resources\ResourceFile;
use HelgeSverre\TurboVision\Resources\StringList;
use HelgeSverre\TurboVision\Resources\StringListMaker;
use HelgeSverre\TurboVision\Resources\ViewResource;
use HelgeSverre\TurboVision\Resources\ViewResourceNode;
use HelgeSverre\TurboVision\Resources\ViewResourceRegistry;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Text\Terminal;
use HelgeSverre\TurboVision\Validators\FilterValidator;
use HelgeSverre\TurboVision\Validators\PictureValidator;
use HelgeSverre\TurboVision\Validators\RangeValidator;
use HelgeSverre\TurboVision\Validators\StringLookupValidator;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\ScrollBar;
use HelgeSverre\TurboVision\Views\ScrollBar\ScrollBarOrientation;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\TextAlignment;
use HelgeSverre\TurboVision\Views\View;
use HelgeSverre\TurboVision\Views\Window;

/**
 * An intentionally maximal application that demonstrates the public framework as
 * one cohesive desktop. Every menu item opens a real, reusable framework feature.
 */
final class KitchenSinkApp extends Application
{
    private const int MIN_DESKTOP_WIDTH = 80;

    private const int MIN_DESKTOP_HEIGHT = 23;

    public const int HelpOverview = 1_000;
    public const int HelpControls = 1_010;
    public const int HelpEditor = 1_020;
    public const int HelpResources = 1_030;

    /** @var list<string> */
    private const array FEATURE_LABELS = [
        '01  Forms + validators',
        '02  Message boxes',
        '03  Memo editor',
        '04  File editor',
        '05  Open file dialog',
        '06  Change directory',
        '07  Scrollable canvas',
        '08  Outline tree',
        '09  Terminal stream',
        '10  Palette editor',
        '11  Resource round-trip',
        '12  Context menu',
    ];

    /** @var list<int> */
    private const array FEATURE_COMMANDS = [
        KitchenSinkCommand::Controls,
        KitchenSinkCommand::MessageBoxes,
        KitchenSinkCommand::Memo,
        KitchenSinkCommand::Editor,
        KitchenSinkCommand::FileDialog,
        KitchenSinkCommand::ChangeDirectory,
        KitchenSinkCommand::Canvas,
        KitchenSinkCommand::Outline,
        KitchenSinkCommand::Terminal,
        KitchenSinkCommand::Colors,
        KitchenSinkCommand::Resources,
        KitchenSinkCommand::ContextMenu,
    ];

    private int $placementSequence = 0;

    private int $resourceSequence = 0;

    private int $activitySequence = 0;

    private bool $advancedEnabled = true;

    private ?Terminal $activityTerminal = null;

    private bool $compact = false;

    private ?StaticText $compactNotice = null;

    /** @var list<Window> Windows detached intact while the terminal is compact. */
    private array $compactWindows = [];

    /** Desktop being populated before Program has assigned its desktop property. */
    private ?Desktop $buildingDesktop = null;

    /** @var list<mixed>|null */
    private ?array $lastControlsData = null;

    private readonly HelpFile $helpFile;

    public function __construct(?Screen $screen = null)
    {
        $this->helpFile = $this->makeHelpFile();
        parent::__construct($screen ?? new Screen(new AnsiDriver(trackMouseMotion: true)));
    }

    protected function initDeskTop(Rect $bounds): Desktop
    {
        $desktop = new Desktop($bounds);
        $this->compact = ! $this->supportsFullDesktop($desktop->getExtent());
        if ($this->compact) {
            $this->installCompactNotice($desktop);
        } else {
            $this->openInitialWindows($desktop);
        }

        return $desktop;
    }

    public function reflowDesktop(): void
    {
        $desktop = $this->desktopForTest();
        $nextExtent = Rect::of(
            0,
            0,
            $this->screen()?->cols() ?? 0,
            max(0, ($this->screen()?->rows() ?? 0) - 2),
        );
        $nextCompact = ! $this->supportsFullDesktop($nextExtent);

        if ($desktop !== null && ! $this->compact && $nextCompact) {
            $this->compactWindows = $this->detachWindows($desktop);
        } elseif ($desktop !== null && $this->compact && ! $nextCompact && $this->compactNotice !== null) {
            $desktop->remove($this->compactNotice);
            $this->compactNotice = null;
        }

        parent::reflowDesktop();
        $desktop = $this->desktopForTest();
        if ($desktop === null) {
            return;
        }
        if ($nextCompact === $this->compact) {
            if (! $nextCompact) {
                $this->fitLiveWindows($desktop);
            }

            return;
        }

        $this->compact = $nextCompact;
        if ($this->compact) {
            $this->installCompactNotice($desktop);

            return;
        }

        if ($this->compactWindows === []) {
            $this->openInitialWindows($desktop);
        } else {
            foreach ($this->compactWindows as $window) {
                $window->changeBounds($this->fitInside($window->getBounds(), $desktop->getExtent()));
                $desktop->insertWindow($window);
            }
            $this->compactWindows = [];
        }
        $this->fitLiveWindows($desktop);
    }

    protected function initMenuBar(Rect $bounds): MenuBar
    {
        return new MenuBar(
            $bounds,
            new SubMenu('~F~ile', Key::AltF)->items(
                new MenuItem('~N~ew editor', KitchenSinkCommand::Editor, Key::F3, 'F3'),
                new MenuItem('~O~pen file…', KitchenSinkCommand::FileDialog, Key::F4, 'F4'),
                new MenuItem('~S~ave', Cmd::Save, Key::CtrlS, 'Ctrl-S'),
                new MenuItem('Save ~a~s…', Cmd::SaveAs),
                new MenuItem('~C~hange directory…', KitchenSinkCommand::ChangeDirectory),
                new MenuItem('~R~esource round-trip', KitchenSinkCommand::Resources, Key::F9, 'F9'),
                MenuItem::separator(),
                new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Alt-X'),
            ),
            new SubMenu('~E~dit', Key::AltE, self::HelpEditor)->items(
                new MenuItem('~U~ndo', Cmd::Undo, Key::CtrlZ, 'Ctrl-Z'),
                MenuItem::separator(),
                new MenuItem('Cu~t~', Cmd::Cut, Key::ShiftDelete, 'Shift-Del'),
                new MenuItem('~C~opy', Cmd::Copy, Key::CtrlInsert, 'Ctrl-Ins'),
                new MenuItem('~P~aste', Cmd::Paste, Key::ShiftInsert, 'Shift-Ins'),
                new MenuItem('C~l~ear selection', Cmd::Clear),
                MenuItem::separator(),
                new MenuItem('~F~ind…', Cmd::Find, Key::CtrlF, 'Ctrl-F'),
                new MenuItem('Find ~n~ext', Cmd::SearchAgain),
                new MenuItem('~R~eplace…', Cmd::Replace),
            ),
            new SubMenu('~L~abs', Key::AltL, self::HelpOverview)->items(
                new MenuItem('~C~ontrol gallery…', KitchenSinkCommand::Controls, Key::F2, 'F2', helpCtx: self::HelpControls),
                new MenuItem('~M~essage boxes…', KitchenSinkCommand::MessageBoxes),
                new MenuItem('M~e~mo editor…', KitchenSinkCommand::Memo),
                new MenuItem('File e~d~itor', KitchenSinkCommand::Editor, Key::F3, 'F3', helpCtx: self::HelpEditor),
                MenuItem::separator(),
                new SubMenu('~D~ata views')->items(
                    new MenuItem('Scrollable ~c~anvas', KitchenSinkCommand::Canvas),
                    new MenuItem('~O~utline tree', KitchenSinkCommand::Outline),
                    new MenuItem('~T~erminal stream', KitchenSinkCommand::Terminal, Key::F7, 'F7'),
                ),
                new SubMenu('~F~iles + data')->items(
                    new MenuItem('~O~pen file dialog…', KitchenSinkCommand::FileDialog, Key::F4, 'F4'),
                    new MenuItem('~C~hange directory…', KitchenSinkCommand::ChangeDirectory),
                    new MenuItem('~R~esource round-trip', KitchenSinkCommand::Resources, Key::F9, 'F9', helpCtx: self::HelpResources),
                ),
            ),
            new SubMenu('~T~heme', Key::AltT)->items(
                new MenuItem('~D~ark modern', KitchenSinkCommand::ThemeDark),
                new MenuItem('~C~lassic color', KitchenSinkCommand::ThemeClassic),
                new MenuItem('~B~lack + white', KitchenSinkCommand::ThemeBlackWhite),
                new MenuItem('~M~onochrome', KitchenSinkCommand::ThemeMonochrome),
                MenuItem::separator(),
                new MenuItem('Cycle ~t~heme', KitchenSinkCommand::CycleTheme, Key::F8, 'F8'),
                new MenuItem('Palette ~e~ditor…', KitchenSinkCommand::Colors),
            ),
            new SubMenu('~W~indow', Key::AltW)->items(
                new MenuItem('~N~ext', Cmd::Next, Key::F6, 'F6'),
                new MenuItem('~P~revious', Cmd::Prev),
                new MenuItem('~Z~oom', Cmd::Zoom, Key::F5, 'F5'),
                new MenuItem('~C~lose', Cmd::Close),
                new MenuItem('Close ~a~ll', Cmd::CloseAll),
                MenuItem::separator(),
                new MenuItem('~T~ile', Cmd::Tile),
                new MenuItem('C~a~scade', Cmd::Cascade),
                new MenuItem('~R~eset desktop', KitchenSinkCommand::ResetDesktop),
            ),
            new SubMenu('T~o~ols', Key::AltO)->items(
                new MenuItem('~C~ontext menu…', KitchenSinkCommand::ContextMenu),
                new MenuItem('Toggle ~a~dvanced labs', KitchenSinkCommand::ToggleAdvanced),
            ),
            new SubMenu('~H~elp', Key::AltH, self::HelpOverview)->items(
                new MenuItem('~K~itchen Sink help', Cmd::Help, Key::F1, 'F1', helpCtx: self::HelpOverview),
                new MenuItem('~A~bout', KitchenSinkCommand::About),
                MenuItem::separator(),
                new MenuItem('E~x~it', Cmd::Quit, Key::AltX, 'Alt-X'),
            ),
        );
    }

    protected function initStatusLine(Rect $bounds): StatusLine
    {
        return new StatusLine($bounds, StatusDef::all(
            new StatusItem('~F1~ Help', Key::F1, Cmd::Help),
            new StatusItem('~F2~ Controls', Key::F2, KitchenSinkCommand::Controls),
            new StatusItem('~F3~ Editor', Key::F3, KitchenSinkCommand::Editor),
            new StatusItem('~F5~ Zoom', Key::F5, Cmd::Zoom),
            new StatusItem('~F6~ Next', Key::F6, Cmd::Next),
            new StatusItem('~F8~ Theme', Key::F8, KitchenSinkCommand::CycleTheme),
            new StatusItem('~F10~ Menu', Key::F10, Cmd::Menu),
            new StatusItem('~Alt-X~ Exit', Key::AltX, Cmd::Quit),
        ));
    }

    protected function createHelpView(int $context): View
    {
        return new HelpWindow($this->helpFile, $this->helpFile->hasTopic($context) ? $context : self::HelpOverview);
    }

    public function handleEvent(Event $event): void
    {
        if ($this->interceptQuitWithModifiedEditors($event)) {
            return;
        }
        $editorCommand = $event->what === EventType::Command
            ? $event->asMessage()?->command
            : null;
        if ($editorCommand !== null
            && in_array($editorCommand, [Cmd::Find, Cmd::Replace, Cmd::Save, Cmd::SaveAs], true)
        ) {
            $this->handleEditorCommand($editorCommand);
            $this->clearEvent($event);

            return;
        }

        parent::handleEvent($event);
        if ($event->what !== EventType::Command) {
            return;
        }
        $message = $event->asMessage();
        if ($message === null) {
            return;
        }
        if ($message->command === Cmd::Tile || $message->command === Cmd::Cascade) {
            $this->arrangeWindows($message->command);
            $this->clearEvent($event);

            return;
        }
        if ($message->command === Cmd::CloseAll) {
            $this->closeAllWindows();
            $this->clearEvent($event);

            return;
        }
        if (! $this->isKitchenSinkCommand($message->command)) {
            return;
        }
        if ($this->compact && $this->requiresFullDesktop($message->command)) {
            $this->clearEvent($event);

            return;
        }
        if (! $this->commandEnabled($message->command)) {
            $this->log('Command blocked by CommandSet: advanced labs are disabled.');
            $this->clearEvent($event);

            return;
        }

        match ($message->command) {
            KitchenSinkCommand::Controls => $this->showControls(),
            KitchenSinkCommand::MessageBoxes => $this->showMessageBoxes(),
            KitchenSinkCommand::Memo => $this->showMemo(),
            KitchenSinkCommand::Editor => $this->openEditor(),
            KitchenSinkCommand::FileDialog => $this->showFileDialog(),
            KitchenSinkCommand::ChangeDirectory => $this->showChangeDirectory(),
            KitchenSinkCommand::Canvas => $this->openCanvas(),
            KitchenSinkCommand::Outline => $this->openOutline(),
            KitchenSinkCommand::Terminal => $this->openTerminal(),
            KitchenSinkCommand::Colors => $this->showColors(),
            KitchenSinkCommand::Resources => $this->roundTripResources(),
            KitchenSinkCommand::ContextMenu => $this->showContextMenu($message->info),
            KitchenSinkCommand::About => $this->showAbout(),
            KitchenSinkCommand::ResetDesktop, KitchenSinkCommand::ContextReset => $this->resetDesktop(),
            KitchenSinkCommand::ToggleAdvanced => $this->toggleAdvanced(),
            KitchenSinkCommand::CycleTheme => $this->cycleTheme(),
            KitchenSinkCommand::ThemeDark => $this->applyTheme(PaletteMode::Color),
            KitchenSinkCommand::ThemeClassic => $this->applyTheme(PaletteMode::ClassicColor),
            KitchenSinkCommand::ThemeBlackWhite => $this->applyTheme(PaletteMode::BlackWhite),
            KitchenSinkCommand::ThemeMonochrome => $this->applyTheme(PaletteMode::Monochrome),
            KitchenSinkCommand::ContextInspect => $this->showAbout(),
            default => null,
        };
        $this->clearEvent($event);
    }

    public function dispatchForTest(Event $event): void
    {
        $this->handleEvent($event);
        $this->drawAndFlushForTest();
    }

    public function windowCount(): int
    {
        return count(array_filter(
            $this->desktopForTest()?->subviews() ?? [],
            static fn (View $view): bool => $view instanceof Window,
        ));
    }

    public function advancedLabsEnabled(): bool
    {
        return $this->advancedEnabled;
    }

    /** @return list<mixed>|null */
    public function controlsDataForTest(): ?array
    {
        return $this->lastControlsData;
    }

    /** Exposed for deterministic feature tests without entering a modal loop. */
    public function controlsDialogForTest(): Dialog
    {
        return $this->makeControlsDialog();
    }

    private function openInitialWindows(Desktop $desktop): void
    {
        $previous = $this->buildingDesktop;
        $this->buildingDesktop = $desktop;
        try {
            $extent = $desktop->getExtent();
            $desktop->insertWindow($this->makeDashboardWindow($extent));
            [$terminalWindow, $terminal] = $this->makeTerminalWindow($extent, 'Live Event Terminal');
            $this->activityTerminal = $terminal;
            $desktop->insertWindow($terminalWindow);
            $desktop->insertWindow($this->makeNavigatorWindow($extent));
            $this->log('Runtime online · menus, mouse, resize, palettes, events.');
            $this->log('Choose a lab with Enter/Space/double-click, or press F10.');
        } finally {
            $this->buildingDesktop = $previous;
        }
    }

    private function makeDashboardWindow(Rect $extent): Window
    {
        $width = min(66, max(34, $extent->width() - 18));
        $height = min(18, max(10, $extent->height() - 5));
        $window = $this->window(Rect::of(1, 1, min($extent->width(), 1 + $width), min($extent->height(), 1 + $height)), 'Ultra Super Demo');
        $window->insert(new KitchenSinkDashboard($window->getExtent()->grow(-1, -1)));

        return $window;
    }

    private function makeNavigatorWindow(Rect $extent): Window
    {
        $width = min(36, max(32, intdiv($extent->width(), 3)));
        $height = min(20, max(9, $extent->height() - 3));
        $x = max(0, $extent->width() - $width - 1);
        $window = $this->window(Rect::of($x, 1, $x + $width, min($extent->height(), 1 + $height)), 'Feature Navigator');
        // Keep the launcher wide enough for its frame chrome while anchoring it to
        // the right edge. Its list then grows vertically and scrolls as height changes.
        $window->growMode = State::GrowLoX | State::GrowHiX;
        $vertical = $window->standardScrollBar(ScrollBarOrientation::Vertical, true);
        $inner = $window->getExtent();
        $header = new StaticText(Rect::of(2, 1, $inner->b->x - 2, 3), "EVERYTHING LAB\nEnter · Space · double-click");
        $header->growMode = State::GrowHiX;
        $window->insert($header);
        $list = new FeatureNavigator(Rect::of(2, 4, $inner->b->x - 1, $inner->b->y - 1), $vertical, self::FEATURE_COMMANDS);
        $list->newList(self::FEATURE_LABELS);
        $list->helpCtx = self::HelpOverview;
        $window->insert($list);
        $window->setCurrent($list);

        return $window;
    }

    /** @return array{Window,Terminal} */
    private function makeTerminalWindow(Rect $extent, string $title): array
    {
        $width = min(76, max(30, $extent->width() - 10));
        $height = min(10, max(6, intdiv($extent->height(), 2)));
        $x = max(0, min(7, $extent->width() - $width));
        $y = max(0, $extent->height() - $height - 1);
        $window = $this->window(Rect::of($x, $y, $x + $width, $y + $height), $title);
        $hBar = $window->standardScrollBar(ScrollBarOrientation::Horizontal, true);
        $vBar = $window->standardScrollBar(ScrollBarOrientation::Vertical, true);
        $inner = $window->getExtent();
        $terminal = new Terminal(Rect::of(1, 1, $inner->b->x - 1, $inner->b->y - 1), $hBar, $vBar, maxBytes: 32_768, maxLines: 512);
        $window->insert($terminal);
        $window->setCurrent($terminal);

        return [$window, $terminal];
    }

    private function showControls(): void
    {
        $dialog = $this->makeControlsDialog();
        $result = $this->executeDialog($dialog);
        if ($result !== Cmd::Ok) {
            $this->lastControlsData = null;
            $this->log('Controls lab cancelled; no form data was transferred.');

            return;
        }

        $data = $dialog->getData();
        $this->lastControlsData = is_array($data) ? array_values($data) : [];
        $this->log(sprintf(
            'Controls accepted: validation passed and %d typed form values transferred.',
            count($this->lastControlsData),
        ));
    }

    private function makeControlsDialog(): Dialog
    {
        $dialog = new Dialog(Rect::of(0, 0, 76, 23), 'Controls + Validation Laboratory');
        $dialog->options |= State::Centered;
        $dialog->helpCtx = self::HelpControls;

        $name = new InputLine(Rect::of(3, 3, 29, 4), 48, new FilterValidator('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ -'));
        $name->setText('Ada Lovelace');
        $dialog->insert(new Label(Rect::of(3, 2, 20, 3), '~N~ame (filter)', $name));
        $dialog->insert($name);
        $dialog->insert(new History(Rect::of(29, 3, 32, 4), $name, 701));

        $age = new InputLine(Rect::of(3, 6, 18, 7), 5, new RangeValidator(0, 130));
        $age->setText('36');
        $dialog->insert(new Label(Rect::of(3, 5, 23, 6), '~A~ge (0..130)', $age));
        $dialog->insert($age);

        $code = new InputLine(Rect::of(20, 6, 32, 7), 8, new PictureValidator('*3{#}'));
        $code->setText('824');
        $dialog->insert(new Label(Rect::of(20, 5, 34, 6), '~C~ode (*3#)', $code));
        $dialog->insert($code);

        $mode = new InputLine(Rect::of(3, 9, 32, 10), 16, new StringLookupValidator(['dark', 'classic', 'mono']));
        $mode->setText('dark');
        $dialog->insert(new Label(Rect::of(3, 8, 32, 9), '~M~ode (lookup validator)', $mode));
        $dialog->insert($mode);

        $checks = new CheckBoxes(Rect::of(3, 12, 24, 15), ['~M~ouse support', '~U~nicode cells', '~A~uto save']);
        $checks->setData(0b011);
        $dialog->insert($checks);
        $radio = new RadioButtons(Rect::of(26, 12, 43, 15), ['~F~ast', '~S~afe', '~D~ebug']);
        $radio->setData(1);
        $dialog->insert($radio);
        $multi = new MultiCheckBoxes(Rect::of(45, 12, 70, 15), ['Alpha', 'Beta', 'Stable'], 3, (2 << 8) | 0x03, ' .X');
        $multi->setData(0b10_01_00);
        $dialog->insert($multi);

        $scroll = new ScrollBar(Rect::of(70, 3, 71, 10), ScrollBarOrientation::Vertical);
        $dialog->insert($scroll);
        $list = new ListBox(Rect::of(37, 3, 70, 10), 2, $scroll);
        $list->newList(['ListBox', 'Label', 'History', 'InputLine', 'Button', 'Checks', 'Radio', 'Multi-state', 'Validator', 'ParamText']);
        $dialog->insert($list);

        $summary = new ParamText(Rect::of(3, 17, 70, 18));
        $summary->setText('%s · %d reusable controls · modal validation enabled', 'READY', 10);
        $dialog->insert($summary);
        $dialog->insert(new StaticText(Rect::of(3, 18, 48, 19), 'Tab changes focus · mnemonics focus labels · mouse drag selects text'));
        $dialog->insert(new Button(Rect::of(49, 19, 59, 21), 'O~K~', Cmd::Ok, Button::Default));
        $dialog->insert(new Button(Rect::of(61, 19, 72, 21), 'Cancel', Cmd::Cancel));
        $dialog->setCurrent($name);

        return $dialog;
    }

    private function showMessageBoxes(): void
    {
        $desktop = $this->desktopForTest();
        if ($desktop === null) {
            return;
        }
        $result = MessageBox::show(
            $desktop,
            "This is a real modal MessageBox.\nTry keyboard mnemonics, Enter, Escape, or a mouse click.",
            MsgBoxFlag::Confirmation | MsgBoxFlag::YesNoCancel,
        );
        $this->log('MessageBox result: ' . $result . '.');
    }

    private function showMemo(): void
    {
        $dialog = new Dialog(Rect::of(0, 0, 72, 21), 'Memo + Indicator');
        $dialog->options |= State::Centered;
        $hBar = $dialog->standardScrollBar(ScrollBarOrientation::Horizontal, true);
        $vBar = $dialog->standardScrollBar(ScrollBarOrientation::Vertical, true);
        $indicator = new Indicator(Rect::of(2, 17, 13, 18));
        $memo = new Memo(
            Rect::of(2, 2, 69, 17),
            $hBar,
            $vBar,
            $indicator,
            "Memo is a form-friendly Editor.\n\nUnicode: æ ø å · arrows, selection, clipboard, undo and redo all work.\nTab intentionally returns to dialog focus traversal.",
        );
        $dialog->insert($indicator);
        $dialog->insert($memo);
        $dialog->insert(new Button(Rect::of(47, 18, 57, 20), 'O~K~', Cmd::Ok, Button::Default));
        $dialog->insert(new Button(Rect::of(59, 18, 69, 20), 'Cancel', Cmd::Cancel));
        $dialog->setCurrent($memo);
        $result = $this->executeDialog($dialog);
        $this->log(sprintf('Memo closed with %d; payload is %d UTF-8 bytes.', $result, $memo->dataSize()));
    }

    private function openEditor(?string $fileName = null): ?EditWindow
    {
        $bounds = $this->nextWindowBounds(78, 22);
        $window = new EditWindow($bounds, $fileName, $this->allocateWindowNumber());
        $window->options |= State::Tileable;
        $window->helpCtx = self::HelpEditor;
        $window->editor->helpCtx = self::HelpEditor;
        $this->configureEditorWindow($window);

        if (! $window->editor->isValid) {
            $this->showEditorError($window->editor->lastError ?? 'The selected file could not be opened.');

            return null;
        }
        if ($fileName === null) {
            $window->editor->setText(<<<'PHP'
<?php

declare(strict_types=1);

// FileEditor: Unicode-safe editing, search/replace, clipboard,
// bounded delta undo, indicator, scrollbars, and atomic file saves.
final class KitchenSink
{
    public function run(): string
    {
        return 'Everything is connected.';
    }
}
PHP);
        }
        $this->insertWindow($window);
        $this->log($fileName === null
            ? 'Opened EditWindow with a seeded FileEditor buffer.'
            : 'Opened ' . $fileName . ' in a FileEditor window.');

        return $window;
    }

    private function showFileDialog(): void
    {
        $dialog = new FileDialog('*.php', 'Open a PHP file', '~F~ile name', FileDialog::OpenButton | FileDialog::HelpButton, 702);
        $dialog->options |= State::Centered;
        $dialog->helpCtx = self::HelpEditor;
        $result = $this->executeDialog($dialog);
        if ($result !== FileCommand::Open) {
            $this->log('FileDialog cancelled.');

            return;
        }

        $this->openEditor($dialog->getFileName());
    }

    private function showChangeDirectory(): void
    {
        $dialog = new ChDirDialog(ChDirDialog::HelpButton, 703);
        $dialog->options |= State::Centered;
        $dialog->helpCtx = self::HelpResources;
        $result = $this->executeDialog($dialog);
        $this->log($result === Cmd::Ok ? 'Working directory changed.' : 'Change-directory dialog cancelled safely.');
    }

    private function openCanvas(): void
    {
        $window = $this->window($this->nextWindowBounds(76, 21), 'Scroller + Logical Canvas');
        $hBar = $window->standardScrollBar(ScrollBarOrientation::Horizontal, true);
        $vBar = $window->standardScrollBar(ScrollBarOrientation::Vertical, true);
        $inner = $window->getExtent();
        $canvas = new KitchenSinkCanvas(Rect::of(1, 1, $inner->b->x - 1, $inner->b->y - 1), $hBar, $vBar);
        $window->insert($canvas);
        $window->setCurrent($canvas);
        $this->insertWindow($window);
        $this->log('Opened a 120×40 logical Scroller with synchronized bars.');
    }

    private function openOutline(): void
    {
        $window = $this->window($this->nextWindowBounds(62, 21), 'Outline + Tree Navigation');
        $hBar = $window->standardScrollBar(ScrollBarOrientation::Horizontal, true);
        $vBar = $window->standardScrollBar(ScrollBarOrientation::Vertical, true);
        $root = new Node('Turbo Vision PHP', Node::siblings(
            new Node('Application shell', Node::siblings(new Node('Program'), new Node('Desktop'), new Node('Palette modes'))),
            new Node('Views', Node::siblings(new Node('Window + Frame'), new Node('Scroller'), new Node('ListViewer'))),
            new Node('Dialogs', Node::siblings(new Node('Controls'), new Node('Validators'), new Node('History'))),
            new Node('Data systems', Node::siblings(new Node('Editor'), new Node('Help'), new Node('Resources'), new Node('Terminal'))),
        ));
        $inner = $window->getExtent();
        $outline = new Outline(Rect::of(1, 1, $inner->b->x - 1, $inner->b->y - 1), $hBar, $vBar, $root);
        $window->insert($outline);
        $window->setCurrent($outline);
        $this->insertWindow($window);
        $this->log('Opened Outline: click graph glyphs or use +, -, *, arrows, and Enter.');
    }

    private function openTerminal(): void
    {
        $desktop = $this->desktopForTest();
        if ($desktop === null) {
            return;
        }
        [$window, $terminal] = $this->makeTerminalWindow($desktop->getExtent(), 'Bounded Terminal Device');
        $terminal->output()->writeln('OutputTextStream attached.');
        $terminal->output()->printf("Retention: %d bytes / %d logical lines\n", $terminal->maxScrollbackBytes(), $terminal->maxScrollbackLines());
        $terminal->output()->writeln("Control handling: CR, LF, TAB, backspace · UTF-8 safe\nResize this window to watch old output reflow.");
        $this->insertWindow($window);
        $this->log('Opened another bounded, reflowing Terminal text device.');
    }

    private function showColors(): void
    {
        $palette = $this->getPalette() ?? new Palette([1 => 0x07]);
        $groups = [
            new ColorGroup('Application chrome', [new ColorItem('Desktop', 1), new ColorItem('Normal text', 2), new ColorItem('Accent', 3), new ColorItem('Selection', 4)]),
            new ColorGroup('Windows + dialogs', [new ColorItem('Frame', 5), new ColorItem('Title', 6), new ColorItem('Control', 7), new ColorItem('Disabled', 8)]),
        ];
        $dialog = new ColorDialog(
            $palette,
            $groups,
            monochrome: $this->paletteMode() === PaletteMode::Monochrome,
            onCommit: function (Palette $committed): void {
                $this->setPalette($committed);
                $this->log('ColorDialog committed an immutable custom root palette.');
            },
        );
        $result = $this->executeDialog($dialog);
        $this->log($result === Cmd::Ok ? 'Palette edit committed.' : 'Palette working copy discarded.');
    }

    private function roundTripResources(): void
    {
        $directory = sys_get_temp_dir() . '/tvision-kitchen-' . bin2hex(random_bytes(6));
        $path = $directory . '/showcase.json';

        try {
            $streamables = new StreamableRegistry;
            $streamables->registerClass(StringList::STREAM_TYPE, StringList::class);
            $streamables->registerClass(ViewResource::STREAM_TYPE, ViewResource::class);
            $codec = new StreamCodec($streamables);
            $file = ResourceFile::open($path, $codec);
            $indexMaker = new StringListMaker;
            $file->put('feature-index', $indexMaker->addMany(self::FEATURE_LABELS)->build());
            $file->put('live-window', $this->resourceWindow());
            $file->flush();

            $reopened = ResourceFile::open($path, $codec);
            $index = $reopened->require('feature-index');
            $viewResource = $reopened->require('live-window');
            if (! $index instanceof StringList || ! $viewResource instanceof ViewResource) {
                throw new \LogicException('Kitchen Sink resources decoded to unexpected types.');
            }
            $view = $viewResource->build($this->resourceFactories());
            if (! $view instanceof Window) {
                throw new \LogicException('Kitchen Sink declarative resource did not build a Window.');
            }
            $view->setNumber($this->allocateWindowNumber());
            $view->options |= State::Tileable;
            $view->helpCtx = self::HelpResources;
            $this->insertWindow($view);
            $this->resourceSequence++;
            $this->log(sprintf('ResourceFile #%d: atomically stored/reloaded %d names and rebuilt an owned view tree.', $this->resourceSequence, count($index)));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            if (is_file($path . '.lock')) {
                unlink($path . '.lock');
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    private function resourceWindow(): ViewResource
    {
        return new ViewResource(new ViewResourceNode(
            'kitchen.window',
            $this->nextWindowBounds(58, 14),
            ['title' => 'Rebuilt Resource Tree'],
            [
                new ViewResourceNode('kitchen.text', Rect::of(3, 2, 53, 7), [
                    'text' => "RESOURCEFILE → STREAMCODEC → VIEWRESOURCE\n\nNo unserialize. No class-name loading. Explicit allow-list factories only.",
                    'alignment' => 'center',
                ]),
                new ViewResourceNode('kitchen.input', Rect::of(6, 9, 50, 10), ['capacity' => 64, 'text' => 'Fresh runtime owner/focus/frame state']),
            ],
        ));
    }

    private function resourceFactories(): ViewResourceRegistry
    {
        $registry = new ViewResourceRegistry;
        $registry->register('kitchen.window', function (ViewResourceNode $node): Window {
            return $this->window($node->bounds, $node->string('title'), numbered: false);
        });
        $registry->register('kitchen.text', static function (ViewResourceNode $node): StaticText {
            $alignment = $node->property('alignment') === 'center' ? TextAlignment::Center : TextAlignment::Left;

            return new StaticText($node->bounds, $node->string('text'), $alignment);
        });
        $registry->register('kitchen.input', static function (ViewResourceNode $node): InputLine {
            $input = new InputLine($node->bounds, $node->integer('capacity'));
            $input->setText($node->string('text'));

            return $input;
        });

        return $registry;
    }

    private function showContextMenu(mixed $info): void
    {
        $desktop = $this->desktopForTest();
        if ($desktop === null) {
            return;
        }
        $menu = new Menu([
            new MenuItem('~I~nspect system', KitchenSinkCommand::ContextInspect),
            new MenuItem('Open ~c~anvas', KitchenSinkCommand::Canvas),
            new MenuItem('Open ~o~utline', KitchenSinkCommand::Outline),
            MenuItem::separator(),
            new MenuItem('~R~eset desktop', KitchenSinkCommand::ContextReset),
        ]);
        $anchor = $info instanceof Point
            ? $desktop->makeLocal($info)
            : new Point(max(0, $desktop->getExtent()->width() - 30), 3);
        $popup = new MenuPopup(MenuBox::boundsFor($desktop->getExtent(), $menu, $anchor), $menu);
        $result = $this->executeDialog($popup);
        $this->log('Context MenuPopup returned command ' . $result . '.');
    }

    private function showAbout(): void
    {
        $desktop = $this->desktopForTest();
        if ($desktop === null) {
            return;
        }
        MessageBox::show(
            $desktop,
            "ULTRA SUPER DEMO // KITCHEN SINK\n\nApplication · events · menus · windows · dialogs · validators\neditors · files · scrollbars · outline · terminal · colors\nhelp · persistence · resources · Unicode-safe rendering",
            MsgBoxFlag::Information | MsgBoxFlag::OkButton,
        );
    }

    private function toggleAdvanced(): void
    {
        $advanced = CommandSet::of(
            KitchenSinkCommand::FileDialog,
            KitchenSinkCommand::ChangeDirectory,
            KitchenSinkCommand::Colors,
            KitchenSinkCommand::Resources,
        );
        if ($this->advancedEnabled) {
            $this->disableCommands($advanced);
        } else {
            $this->enableCommands($advanced);
        }
        $this->advancedEnabled = ! $this->advancedEnabled;
        $this->log('Advanced lab CommandSet is now ' . ($this->advancedEnabled ? 'enabled.' : 'disabled; menus redrew their disabled state.'));
    }

    private function cycleTheme(): void
    {
        $modes = PaletteMode::cases();
        $index = array_search($this->paletteMode(), $modes, true);
        $this->applyTheme($modes[((is_int($index) ? $index : 0) + 1) % count($modes)]);
    }

    private function applyTheme(PaletteMode $mode): void
    {
        $this->setPalette(null);
        $this->setPaletteMode($mode);
        $this->drawView();
        $this->log('PaletteMode → ' . $mode->value . '.');
    }

    private function resetDesktop(): void
    {
        $desktop = $this->desktopForTest();
        if ($desktop === null || ! $this->resolveModifiedEditors()) {
            return;
        }

        $this->removeAllWindows($desktop);
        $this->compactWindows = [];
        $this->activityTerminal = null;
        $this->placementSequence = 0;
        if ($this->compact) {
            $this->installCompactNotice($desktop);
        } else {
            $this->openInitialWindows($desktop);
        }
    }

    private function arrangeWindows(int $command): void
    {
        $desktop = $this->desktopForTest();
        if ($desktop === null) {
            return;
        }
        $extent = $desktop->getExtent();
        if ($command === Cmd::Tile) {
            $desktop->tile($extent);
            $this->log('Desktop tiled every visible tileable window.');

            return;
        }

        $desktop->cascade($extent);
        $this->log('Desktop cascaded every visible tileable window.');
    }

    private function closeAllWindows(): void
    {
        $desktop = $this->desktopForTest();
        if ($desktop === null || ! $this->resolveModifiedEditors()) {
            return;
        }
        foreach ($desktop->subviews() as $view) {
            if ($view instanceof Window) {
                $view->close();
            }
        }
        $this->compactWindows = [];
        $this->activityTerminal = null;
    }

    private function window(Rect $bounds, string $title, bool $numbered = true): Window
    {
        $window = new Window($bounds, $title, $numbered ? $this->allocateWindowNumber() : 0);
        $window->options |= State::Tileable;

        return $window;
    }

    private function nextWindowBounds(int $desiredWidth, int $desiredHeight): Rect
    {
        $extent = $this->desktopForTest()?->getExtent() ?? $this->getExtent();
        $width = min($desiredWidth, $extent->width());
        $height = min($desiredHeight, $extent->height());
        $offset = 1 + ((++$this->placementSequence * 2) % 8);
        $x = min($offset, max(0, $extent->width() - $width));
        $y = min(1 + intdiv($offset, 2), max(0, $extent->height() - $height));

        return Rect::of($x, $y, $x + $width, $y + $height);
    }

    private function supportsFullDesktop(Rect $extent): bool
    {
        return $extent->width() >= self::MIN_DESKTOP_WIDTH
            && $extent->height() >= self::MIN_DESKTOP_HEIGHT;
    }

    private function installCompactNotice(Desktop $desktop): void
    {
        if ($this->compactNotice?->owner === $desktop) {
            return;
        }

        $notice = new StaticText(
            $desktop->getExtent(),
            "\n\nULTRA SUPER KITCHEN SINK\n\n"
            . "This comprehensive demo needs at least " . self::MIN_DESKTOP_WIDTH . '×' . (self::MIN_DESKTOP_HEIGHT + 2) . " terminal cells.\n"
            . "Resize the terminal to restore every live window without losing its state.\n\n"
            . 'Ctrl-C and Alt-X remain available.',
            TextAlignment::Center,
        );
        $notice->growMode = State::GrowHiX | State::GrowHiY;
        $notice->options |= State::Selectable | State::TopSelect;
        $notice->helpCtx = self::HelpOverview;
        $desktop->insert($notice);
        $desktop->setCurrent($notice);
        $this->compactNotice = $notice;
    }

    /** @return list<Window> */
    private function detachWindows(Desktop $desktop): array
    {
        $windows = [];
        foreach ($desktop->subviews() as $view) {
            if ($view instanceof Window) {
                $windows[] = $view;
                $desktop->remove($view);
            }
        }

        return $windows;
    }

    private function fitInside(Rect $bounds, Rect $extent): Rect
    {
        $width = min($bounds->width(), $extent->width());
        $height = min($bounds->height(), $extent->height());
        $x = max(0, min($bounds->a->x, $extent->width() - $width));
        $y = max(0, min($bounds->a->y, $extent->height() - $height));

        return Rect::of($x, $y, $x + $width, $y + $height);
    }

    private function fitLiveWindows(Desktop $desktop): void
    {
        foreach ($desktop->subviews() as $view) {
            if ($view instanceof Window) {
                $fitted = $this->fitInside($view->getBounds(), $desktop->getExtent());
                if (! $fitted->equals($view->getBounds())) {
                    $view->changeBounds($fitted);
                }
            }
        }
    }

    private function requiresFullDesktop(int $command): bool
    {
        return in_array($command, [
            KitchenSinkCommand::Controls,
            KitchenSinkCommand::MessageBoxes,
            KitchenSinkCommand::Memo,
            KitchenSinkCommand::Editor,
            KitchenSinkCommand::FileDialog,
            KitchenSinkCommand::ChangeDirectory,
            KitchenSinkCommand::Canvas,
            KitchenSinkCommand::Outline,
            KitchenSinkCommand::Terminal,
            KitchenSinkCommand::Colors,
            KitchenSinkCommand::Resources,
            KitchenSinkCommand::ContextMenu,
            KitchenSinkCommand::About,
            KitchenSinkCommand::ContextInspect,
        ], true);
    }

    private function allocateWindowNumber(): int
    {
        $used = [];
        foreach ($this->allWindows() as $window) {
            $number = $window->frameNumber();
            if ($number >= 1 && $number <= 9) {
                $used[$number] = true;
            }
        }
        for ($number = 1; $number <= 9; $number++) {
            if (! isset($used[$number])) {
                return $number;
            }
        }

        return 0;
    }

    /** @return list<Window> */
    private function allWindows(): array
    {
        $windows = $this->compactWindows;
        $desktops = array_filter([$this->desktopForTest(), $this->buildingDesktop]);
        foreach ($desktops as $desktop) {
            foreach ($desktop->subviews() as $view) {
                if ($view instanceof Window && ! in_array($view, $windows, true)) {
                    $windows[] = $view;
                }
            }
        }

        return $windows;
    }

    private function removeAllWindows(Desktop $desktop): void
    {
        foreach ($desktop->subviews() as $view) {
            if ($view instanceof Window) {
                $desktop->remove($view);
            }
        }
    }

    private function selectedEditor(): ?FileEditor
    {
        $current = $this->desktopForTest()?->current();

        return $current instanceof EditWindow ? $current->editor : null;
    }

    private function configureEditorWindow(EditWindow $window): void
    {
        $window->setCloseResolver(fn (FileEditor $editor): bool => $this->resolveEditorChanges($editor));
        $window->editor->setDialogHandler(function (EditorDialogRequest $request): int {
            if ($request->kind === EditorDialogKind::ReplacePrompt) {
                $desktop = $this->desktopForTest();
                if ($desktop === null) {
                    return Cmd::Cancel;
                }
                $match = $request->context['match'] ?? '';
                $replace = $request->context['replace'] ?? '';

                return MessageBox::show(
                    $desktop,
                    sprintf('Replace "%s" with "%s"?', $match, $replace),
                    MsgBoxFlag::Confirmation | MsgBoxFlag::YesNoCancel,
                );
            }

            $message = $request->context['message'] ?? 'The editor operation could not be completed.';
            $this->showEditorError(is_string($message) ? $message : 'The editor operation could not be completed.');

            return Cmd::Cancel;
        });
    }

    private function handleEditorCommand(int $command): void
    {
        $editor = $this->selectedEditor();
        if ($editor === null) {
            $this->log('Select a FileEditor window before using editor commands.');

            return;
        }

        match ($command) {
            Cmd::Find => $this->findInEditor($editor),
            Cmd::Replace => $this->replaceInEditor($editor),
            Cmd::Save => $this->saveEditor($editor),
            Cmd::SaveAs => $this->saveEditor($editor, true),
            default => null,
        };
    }

    private function findInEditor(FileEditor $editor): void
    {
        $desktop = $this->desktopForTest();
        if ($desktop === null) {
            return;
        }
        $query = MessageBox::input($desktop, 'Find', 'Find text', $editor->findStr, 96);
        if ($query === null) {
            return;
        }
        if ($query === '') {
            $this->log('Find needs a non-empty search value.');

            return;
        }

        $found = $editor->find(new FindRequest($query, $editor->editorFlags, true));
        $this->log($found ? 'Find selected the next match.' : 'Find reached the end without a match.');
    }

    private function replaceInEditor(FileEditor $editor): void
    {
        $desktop = $this->desktopForTest();
        if ($desktop === null) {
            return;
        }
        $query = MessageBox::input($desktop, 'Replace', 'Find text', $editor->findStr, 96);
        if ($query === null || $query === '') {
            return;
        }
        $replacement = MessageBox::input($desktop, 'Replace', 'Replace with', $editor->replaceStr, 96);
        if ($replacement === null) {
            return;
        }

        $options = $editor->editorFlags
            | SearchOptions::DoReplace
            | SearchOptions::ReplaceAll
            | SearchOptions::PromptOnReplace;
        $count = $editor->replace(new ReplaceRequest($query, $replacement, $options));
        $this->log(sprintf('Replace changed %d match%s.', $count, $count === 1 ? '' : 'es'));
    }

    private function saveEditor(FileEditor $editor, bool $saveAs = false): bool
    {
        $path = $editor->fileName;
        if ($saveAs || $path === null) {
            $path = $this->chooseSavePath($editor);
            if ($path === null) {
                return false;
            }
            if (is_file($path) && $path !== $editor->fileName && ! $this->confirmReplace($path)) {
                return false;
            }
            $saved = $editor->saveAs($path);
        } else {
            $saved = $editor->save();
        }
        if ($saved) {
            $this->log('Saved ' . ($editor->fileName ?? 'the editor buffer') . ' atomically.');
        }

        return $saved;
    }

    private function chooseSavePath(FileEditor $editor): ?string
    {
        $dialog = new FileDialog('*.php', 'Save PHP file as', '~F~ile name', FileDialog::OkButton | FileDialog::HelpButton, 704);
        $dialog->options |= State::Centered;
        $dialog->helpCtx = self::HelpEditor;
        $dialog->setData($editor->fileName ?? 'untitled.php');

        return $this->executeDialog($dialog) === FileCommand::Open ? $dialog->getFileName() : null;
    }

    private function confirmReplace(string $path): bool
    {
        $desktop = $this->desktopForTest();
        if ($desktop === null) {
            return false;
        }

        return MessageBox::show(
            $desktop,
            'Replace the existing file?' . "\n" . $path,
            MsgBoxFlag::Confirmation | MsgBoxFlag::YesNoCancel,
        ) === Cmd::Yes;
    }

    private function resolveEditorChanges(FileEditor $editor): bool
    {
        $discard = $this->editorDiscardDecision($editor);
        if ($discard === null) {
            return false;
        }
        if ($discard) {
            $editor->discardChanges();
        }

        return true;
    }

    /**
     * Decide how to resolve one editor without prematurely discarding its buffer.
     *
     * @return bool|null true to discard, false when clean/saved, null to abort
     */
    private function editorDiscardDecision(FileEditor $editor): ?bool
    {
        if ($editor->valid()) {
            return false;
        }
        $desktop = $this->desktopForTest();
        if ($desktop === null) {
            return null;
        }
        $name = $editor->fileName === null ? 'Untitled' : basename($editor->fileName);
        $result = MessageBox::show(
            $desktop,
            "Save changes to {$name}?",
            MsgBoxFlag::Confirmation | MsgBoxFlag::YesNoCancel,
        );
        if ($result === Cmd::Yes) {
            return $this->saveEditor($editor) ? false : null;
        }
        if ($result === Cmd::No) {
            return true;
        }

        return null;
    }

    private function resolveModifiedEditors(): bool
    {
        $toDiscard = [];
        foreach ($this->allWindows() as $window) {
            if (! $window instanceof EditWindow) {
                continue;
            }
            $discard = $this->editorDiscardDecision($window->editor);
            if ($discard === null) {
                return false;
            }
            if ($discard) {
                $toDiscard[] = $window->editor;
            }
        }
        foreach ($toDiscard as $editor) {
            $editor->discardChanges();
        }

        return true;
    }

    private function interceptQuitWithModifiedEditors(Event $event): bool
    {
        $isQuit = ($event->what === EventType::KeyDown && $event->asKey()?->keyCode === 0x03)
            || $event->isCommand(Cmd::Quit);
        if (! $isQuit || $this->resolveModifiedEditors()) {
            return false;
        }

        $this->clearEvent($event);

        return true;
    }

    private function showEditorError(string $message): void
    {
        $desktop = $this->desktopForTest();
        if ($desktop !== null) {
            MessageBox::show($desktop, $message, MsgBoxFlag::Error | MsgBoxFlag::OkButton);
        }
        $this->log('Editor error: ' . $message);
    }

    private function isAttachedTerminal(?Terminal $terminal): bool
    {
        return $terminal?->owner instanceof Window
            && $terminal->owner->owner instanceof Desktop;
    }

    private function findActivityTerminal(): ?Terminal
    {
        $views = $this->desktopForTest()?->subviews() ?? [];
        for ($index = count($views) - 1; $index >= 0; $index--) {
            $window = $views[$index];
            if (! $window instanceof Window) {
                continue;
            }
            foreach ($window->subviews() as $child) {
                if ($child instanceof Terminal) {
                    return $child;
                }
            }
        }

        return null;
    }

    private function isKitchenSinkCommand(int $command): bool
    {
        return $command >= KitchenSinkCommand::Controls && $command <= KitchenSinkCommand::ContextReset;
    }

    private function log(string $message): void
    {
        $terminal = $this->activityTerminal;
        if (! $this->compact && ! $this->isAttachedTerminal($terminal)) {
            $terminal = $this->findActivityTerminal();
            $this->activityTerminal = $terminal;
        }
        $terminal?->output()->writeln(sprintf('[%02d] %s', ++$this->activitySequence, $message));
    }

    private function makeHelpFile(): HelpFile
    {
        return new HelpFile([
            self::HelpOverview => new HelpTopic(
                [new HelpParagraph('Kitchen Sink is a complete interactive map of the library. Open Controls to explore data transfer and validation, then use the Labs menu for every other subsystem.')],
                [new CrossRef(self::HelpControls, 64, 8, 'Controls')],
            ),
            self::HelpControls => new HelpTopic([
                new HelpParagraph('Controls laboratory: Tab and Shift-Tab traverse focus. Labels expose mnemonics. InputLine supports selection, clipboard, history, overwrite, mouse dragging, and transaction-safe validators.'),
            ]),
            self::HelpEditor => new HelpTopic([
                new HelpParagraph('EditWindow combines FileEditor, horizontal and vertical ScrollBars, Indicator, selection, clipboard, search/replace, bounded undo records, and atomic saves.'),
            ]),
            self::HelpResources => new HelpTopic([
                new HelpParagraph('The resource lab writes a StringList and declarative ViewResource through an explicit StreamableRegistry. ResourceFile uses locking and atomic replacement, then rebuilds runtime ownership safely.'),
            ]),
        ]);
    }
}
