<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Bios;

use Closure;
use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Menus\MenuBar;
use HelgeSverre\TurboVision\Menus\StatusLine;
use HelgeSverre\TurboVision\Views\Desktop;

/**
 * Full-screen BIOS Setup Utility application.
 *
 * Overrides layout() to produce a menu-bar-free desktop that is fully
 * covered by the BiosScreen view. No menu bar; no status line — the BiosScreen
 * renders its own key legend.
 */
final class BiosApp extends Application
{
    private ?BiosScreen $biosScreenView = null;

    // ── Test accessors ────────────────────────────────────────────────────────

    /** Returns the live BiosScreen (post-layout). */
    public function biosScreen(): BiosScreen
    {
        if ($this->biosScreenView === null) {
            throw new \LogicException('BiosApp has not been laid out yet. Call bootForTest() first.');
        }

        return $this->biosScreenView;
    }

    /**
     * Test helper: dispatch a single event through the full view tree, then redraw.
     * Mirrors one iteration of the run() event loop without blocking I/O.
     */
    public function dispatchForTest(\HelgeSverre\TurboVision\Events\Event $event): void
    {
        $this->handleEvent($event);
        $this->drawAndFlushForTest();
    }

    // ── Factory overrides ─────────────────────────────────────────────────────

    /** No menu bar — BIOS draws its own tab bar. */
    protected function initMenuBar(Rect $bounds): ?MenuBar
    {
        return null;
    }

    /** No status line — BIOS draws its own key legend. */
    protected function initStatusLine(Rect $bounds): ?StatusLine
    {
        return null;
    }

    /** Full-screen desktop with black background. */
    protected function initDeskTop(Rect $bounds): Desktop
    {
        return new BlackDesktop($bounds);
    }

    /** Override layout() to build a full-screen setup. */
    protected function layout(): void
    {
        $cols = $this->screenObj->cols();
        $rows = $this->screenObj->rows();
        $this->setBounds(Rect::of(0, 0, $cols, $rows));

        $this->clearSubviews();
        $this->desktop      = null;
        $this->menuBar      = null;
        $this->statusLine   = null;

        // Full-screen backdrop (never visible under BiosScreen)
        $this->desktop = $this->initDeskTop(Rect::of(0, 0, $cols, $rows));
        $this->insert($this->desktop);

        // BiosScreen fills the entire screen
        $screen = new BiosScreen(Rect::of(0, 0, $cols, $rows), $this->buildTabs($cols, $rows));
        $this->biosScreenView = $screen;
        $this->insert($screen);
        $this->setCurrent($screen);
    }

    /** Keep both full-screen layers aligned with the terminal after SIGWINCH. */
    public function reflowDesktop(): void
    {
        $cols = $this->screenObj->cols();
        $rows = $this->screenObj->rows();
        $bounds = Rect::of(0, 0, $cols, $rows);

        $this->setBounds($bounds);
        $this->desktop?->changeBounds($bounds);
        $this->biosScreenView?->changeBounds($bounds);
    }

    // ── Tab / field definitions ───────────────────────────────────────────────

    /** @return list<BiosTab> */
    private function buildTabs(int $cols, int $rows): array
    {
        // Keep a reference to self for Action closures
        $app = $this;

        return [
            new BiosTab('Main', [
                new BiosField(
                    'System Date',
                    FieldKind::Text,
                    '06/06/2026',
                    help: 'The system date in MM/DD/YYYY format.',
                ),
                new BiosField(
                    'System Time',
                    FieldKind::Text,
                    '03:14:22',
                    help: 'The system time in HH:MM:SS format (24-hour).',
                ),
                new BiosField(
                    'Hostname',
                    FieldKind::Text,
                    'turbo-pc',
                    help: 'Network hostname for this machine.',
                ),
                new BiosField(
                    'BIOS Version',
                    FieldKind::Info,
                    'Opus 4.8',
                    help: 'Read-only: installed firmware revision.',
                ),
                new BiosField(
                    'CPU',
                    FieldKind::Info,
                    'TurboVision @ 66 MHz',
                    help: 'Read-only: detected processor model.',
                ),
                new BiosField(
                    'Total Memory',
                    FieldKind::Info,
                    '640 KB',
                    help: 'Read-only: usable base memory.',
                ),
            ]),

            new BiosTab('Advanced', [
                new BiosField(
                    'Virtualization',
                    FieldKind::Cycle,
                    'Enabled',
                    ['Enabled', 'Disabled'],
                    help: 'Enable or disable hardware virtualization extensions.',
                ),
                new BiosField(
                    'Hyper-Threading',
                    FieldKind::Cycle,
                    'Enabled',
                    ['Enabled', 'Disabled'],
                    help: 'Enable simultaneous multi-threading on supported CPUs.',
                ),
                new BiosField(
                    'Fan Mode',
                    FieldKind::Cycle,
                    'Auto',
                    ['Auto', 'Full', 'Silent'],
                    help: 'Controls cooling fan speed profile.',
                ),
                new BiosField(
                    'CPU Multiplier',
                    FieldKind::Number,
                    '8',
                    help: 'CPU clock multiplier (integer digits only).',
                ),
            ]),

            new BiosTab('Boot', [
                new BiosField(
                    'Boot Option #1',
                    FieldKind::Cycle,
                    'Hard Disk',
                    ['Hard Disk', 'USB', 'Network', 'CD-ROM'],
                    help: 'Primary boot device.',
                ),
                new BiosField(
                    'Boot Option #2',
                    FieldKind::Cycle,
                    'USB',
                    ['Hard Disk', 'USB', 'Network', 'CD-ROM'],
                    1,
                    help: 'Secondary boot device.',
                ),
                new BiosField(
                    'Fast Boot',
                    FieldKind::Cycle,
                    'Enabled',
                    ['Enabled', 'Disabled'],
                    help: 'Skip POST memory tests to reduce boot time.',
                ),
                new BiosField(
                    'Num Lock',
                    FieldKind::Cycle,
                    'On',
                    ['On', 'Off'],
                    help: 'Initial Num Lock state after boot.',
                ),
            ]),

            new BiosTab('Security', [
                new BiosField(
                    'Supervisor Password',
                    FieldKind::Text,
                    '',
                    help: 'Password required to access BIOS settings.',
                ),
                new BiosField(
                    'User Password',
                    FieldKind::Text,
                    '',
                    help: 'Password required to boot the system.',
                ),
                new BiosField(
                    'Secure Boot',
                    FieldKind::Cycle,
                    'Enabled',
                    ['Enabled', 'Disabled'],
                    help: 'Prevents loading unsigned boot software.',
                ),
            ]),

            new BiosTab('Save & Exit', [
                new BiosField(
                    'Save Changes and Exit',
                    FieldKind::Action,
                    '',
                    onActivate: static function () use ($app): void {
                        $app->putEvent(Event::command(Cmd::Quit));
                    },
                    help: 'Write all changes to NVRAM and reboot.',
                ),
                new BiosField(
                    'Discard Changes and Exit',
                    FieldKind::Action,
                    '',
                    onActivate: static function () use ($app): void {
                        $app->putEvent(Event::command(Cmd::Quit));
                    },
                    help: 'Abandon all changes and reboot.',
                ),
                new BiosField(
                    'Load Defaults',
                    FieldKind::Action,
                    '',
                    onActivate: static function (): void {
                        // Restoring factory defaults would reset all field values.
                        // This is a stub — a real implementation would iterate
                        // over all tabs/fields and restore their default values.
                    },
                    help: 'Restore factory-default settings.',
                ),
            ]),
        ];
    }
}
