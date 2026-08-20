<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\OpenCode;

use HelgeSverre\TurboVision\Drawing\Attribute;
use HelgeSverre\TurboVision\Drawing\Color;
use HelgeSverre\TurboVision\Drawing\DrawBuffer;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Events\KeyDownEvent;
use HelgeSverre\TurboVision\Events\KeyModifier;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\State;
use HelgeSverre\TurboVision\Views\View;

/**
 * A deterministic, offline study of OpenCode's TUI composition.
 *
 * The screen follows the real application's centered home route, four-row logo,
 * left-accented prompt, sparse session transcript, and modal selection surfaces.
 */
final class OpenCodeView extends View
{
    private const int CANVAS = 0x00;
    private const int TEXT = 0x07;
    private const int BRIGHT = 0x0F;
    private const int MUTED = 0x08;
    private const int PRIMARY = 0x0E;
    private const int SECONDARY = 0x09;
    private const int ACCENT = 0x0D;
    private const int ERROR = 0x0C;
    private const int PANEL = 0x08;
    private const int SELECTED = 0x60;

    private OpenCodeDemoState $demoState = OpenCodeDemoState::Home;

    private string $prompt = '';

    private int $modelIndex = 0;

    private bool $planMode = false;

    private ?OpenCodeOverlay $overlay = null;

    private int $commandIndex = 0;

    private int $variantIndex = 2;

    private OpenCodeDemoState $modelReturnState = OpenCodeDemoState::Home;

    /** @var list<array{category:string,title:string}> */
    private const array COMMANDS = [
        ['category' => 'Session', 'title' => 'New session'],
        ['category' => 'Session', 'title' => 'Show working state'],
        ['category' => 'Model', 'title' => 'Select model'],
        ['category' => 'Agent', 'title' => 'Switch agent'],
        ['category' => 'Demo', 'title' => 'Show permission request'],
        ['category' => 'Demo', 'title' => 'Show provider error'],
    ];

    /** @var list<string> */
    private const array VARIANTS = ['Default', 'Low', 'High', 'Max'];

    /** @var list<array{category:string,name:string,provider:string,free:bool}> */
    private const array MODELS = [
        ['category' => 'Recent', 'name' => 'Hy3 (8x usage)', 'provider' => 'OpenCode Go', 'free' => false],
        ['category' => 'OpenCode Zen', 'name' => 'Big Pickle', 'provider' => '', 'free' => true],
        ['category' => 'OpenCode Zen', 'name' => 'GLM-4.7', 'provider' => '', 'free' => true],
        ['category' => 'OpenCode Zen', 'name' => 'Grok Code Fast 1', 'provider' => '', 'free' => true],
        ['category' => 'OpenCode Zen', 'name' => 'MiniMax M2.1', 'provider' => '', 'free' => true],
        ['category' => 'Anthropic', 'name' => 'Claude Haiku 3.5', 'provider' => '', 'free' => false],
        ['category' => 'Anthropic', 'name' => 'Claude Sonnet 4.5', 'provider' => '', 'free' => false],
        ['category' => 'Anthropic', 'name' => 'Claude Opus 4.1', 'provider' => '', 'free' => false],
    ];

    public function __construct(Rect $bounds)
    {
        parent::__construct($bounds);
        $this->options |= State::Selectable;
    }

    public function demoState(): OpenCodeDemoState
    {
        return $this->demoState;
    }

    public function prompt(): string
    {
        return $this->prompt;
    }

    public function draw(): void
    {
        $width = $this->bounds->width();
        $height = $this->bounds->height();
        $this->fillRect(0, 0, $width, $height, ' ', self::CANVAS);

        if ($width < 68 || $height < 24) {
            $this->drawCompact($width, $height);

            return;
        }

        match ($this->demoState) {
            OpenCodeDemoState::Home => $this->drawHome($width, $height),
            OpenCodeDemoState::Session => $this->drawSession($width, $height, false),
            OpenCodeDemoState::Working => $this->drawSession($width, $height, true),
            OpenCodeDemoState::ModelPicker => $this->drawModelPicker($width, $height),
            OpenCodeDemoState::Permission => $this->drawPermission($width, $height),
            OpenCodeDemoState::Error => $this->drawError($width, $height),
        };

        match ($this->overlay) {
            OpenCodeOverlay::Commands => $this->drawCommandPalette($width, $height),
            OpenCodeOverlay::Mcps => $this->drawMcpDialog($width, $height),
            OpenCodeOverlay::Variants => $this->drawVariantPicker($width, $height),
            null => null,
        };
    }

    public function handleEvent(Event $event): void
    {
        if ($event->what !== EventType::KeyDown) {
            return;
        }
        $key = $event->asKey();
        if ($key === null) {
            return;
        }

        if ($key->is(Key::AltX)) {
            $this->queue(Event::command(Cmd::Quit));
        } elseif ($this->overlay !== null) {
            $this->handleOverlayKey($key);
        } elseif ($this->isCtrl($key, 'p')) {
            $this->overlay = OpenCodeOverlay::Commands;
        } elseif ($this->isCtrl($key, 't')) {
            $this->overlay = OpenCodeOverlay::Variants;
        } elseif ($key->is(Key::Esc)) {
            $this->demoState = match ($this->demoState) {
                OpenCodeDemoState::ModelPicker => $this->modelReturnState,
                OpenCodeDemoState::Permission, OpenCodeDemoState::Error => OpenCodeDemoState::Session,
                OpenCodeDemoState::Working => OpenCodeDemoState::Session,
                default => OpenCodeDemoState::Home,
            };
        } elseif ($key->is(Key::F3)) {
            $this->demoState = $this->demoState->next();
        } elseif ($key->is(Key::Tab) || $key->is(Key::ShiftTab)) {
            $this->planMode = ! $this->planMode;
        } elseif ($key->is(Key::F2)) {
            $this->openModelPicker();
        } elseif ($this->demoState === OpenCodeDemoState::ModelPicker && $key->is(Key::Up)) {
            $this->modelIndex = max(0, $this->modelIndex - 1);
        } elseif ($this->demoState === OpenCodeDemoState::ModelPicker && $key->is(Key::Down)) {
            $this->modelIndex = min(count(self::MODELS) - 1, $this->modelIndex + 1);
        } elseif ($key->is(Key::Backspace)) {
            $this->prompt = mb_substr($this->prompt, 0, -1);
        } elseif ($key->is(Key::Enter)) {
            if ($this->demoState === OpenCodeDemoState::ModelPicker) {
                $this->demoState = $this->modelReturnState;
            } elseif ($this->demoState === OpenCodeDemoState::Permission) {
                $this->demoState = OpenCodeDemoState::Working;
            } elseif (trim($this->prompt) === '/quit') {
                $this->prompt = '';
                $this->queue(Event::command(Cmd::Quit));
            } elseif (trim($this->prompt) === '/mcps') {
                $this->prompt = '';
                $this->overlay = OpenCodeOverlay::Mcps;
            } else {
                $this->demoState = $this->demoState === OpenCodeDemoState::Home
                    ? OpenCodeDemoState::Session
                    : OpenCodeDemoState::Working;
                $this->prompt = '';
            }
        } elseif ($key->char !== '' && preg_match('/[^\p{Cc}]/u', $key->char) === 1) {
            $this->prompt .= $key->char;
        }

        $this->clearEvent($event);
    }

    private function handleOverlayKey(KeyDownEvent $key): void
    {
        if ($this->overlay === OpenCodeOverlay::Mcps) {
            if ($key->is(Key::Esc) || $key->is(Key::Enter)) {
                $this->overlay = null;
            }

            return;
        }

        if ($key->is(Key::Esc)) {
            $this->overlay = null;

            return;
        }

        $count = $this->overlay === OpenCodeOverlay::Commands
            ? count(self::COMMANDS)
            : count(self::VARIANTS);
        $index = $this->overlay === OpenCodeOverlay::Commands
            ? $this->commandIndex
            : $this->variantIndex;

        if ($key->is(Key::Up)) {
            $index = ($index - 1 + $count) % $count;
        } elseif ($key->is(Key::Down)) {
            $index = ($index + 1) % $count;
        } elseif ($key->is(Key::Enter)) {
            if ($this->overlay === OpenCodeOverlay::Commands) {
                $this->runCommand($this->commandIndex);
            } else {
                $this->overlay = null;
            }

            return;
        } else {
            return;
        }

        if ($this->overlay === OpenCodeOverlay::Commands) {
            $this->commandIndex = $index;
        } else {
            $this->variantIndex = $index;
        }
    }

    private function runCommand(int $index): void
    {
        $this->overlay = null;
        switch ($index) {
            case 0:
                $this->demoState = OpenCodeDemoState::Home;
                break;
            case 1:
                $this->demoState = OpenCodeDemoState::Working;
                break;
            case 2:
                $this->openModelPicker();
                break;
            case 3:
                $this->planMode = ! $this->planMode;
                break;
            case 4:
                $this->demoState = OpenCodeDemoState::Permission;
                break;
            case 5:
                $this->demoState = OpenCodeDemoState::Error;
                break;
        }
    }

    private function openModelPicker(): void
    {
        if ($this->demoState === OpenCodeDemoState::ModelPicker) {
            return;
        }
        $this->modelReturnState = $this->demoState;
        $this->demoState = OpenCodeDemoState::ModelPicker;
    }

    private function isCtrl(KeyDownEvent $key, string $letter): bool
    {
        $controlCode = ord(strtoupper($letter)) - 64;

        return $key->keyCode === $controlCode
            || (($key->modifiers & KeyModifier::Ctrl) !== 0
                && ($key->keyCode === ord($letter)
                    || $key->keyCode === ord(strtoupper($letter))
                    || strtolower($key->char) === $letter));
    }

    private function drawHome(int $width, int $height): void
    {
        $logoY = max(3, intdiv($height, 2) - 8);
        $this->drawLogo($width, $logoY);

        $composerWidth = min(75, $width - 8);
        $composerX = intdiv($width - $composerWidth, 2);
        $this->drawComposer($composerX, $logoY + 6, $composerWidth, false, true);

        $this->drawMcpToast($width, $height);
        $this->writeClipped(2, $height - 1, '⊙ 1 MCP failed /mcps', max(0, $width - 24), self::ERROR);
        $this->writeRight($width - 2, $height - 1, '0.0.0-beta-17728', self::MUTED);
    }

    private function drawLogo(int $width, int $y): void
    {
        $left = [
            '                   ',
            '█▀▀█ █▀▀█ █▀▀█ █▀▀▄',
            '█__█ █__█ █^^^ █__█',
            '▀▀▀▀ █▀▀▀ ▀▀▀▀ ▀~~▀',
        ];
        $right = [
            '             ▄     ',
            '█▀▀▀ █▀▀█ █▀▀█ █▀▀█',
            '█___ █__█ █__█ █^^^',
            '▀▀▀▀ ▀▀▀▀ ▀▀▀▀ ▀▀▀▀',
        ];
        $logoWidth = max(...array_map(
            static fn (int $index): int => mb_strlen($left[$index]) + 1 + mb_strlen($right[$index]),
            array_keys($left),
        ));
        $x = max(0, intdiv($width - $logoWidth, 2));

        foreach ($left as $row => $line) {
            $this->drawLogoPart($x, $y + $row, $line, self::MUTED);
            $this->drawLogoPart($x + mb_strlen($line) + 1, $y + $row, $right[$row], self::BRIGHT);
        }
    }

    private function drawLogoPart(int $x, int $y, string $encoded, int $attr): void
    {
        $foreground = $attr === self::MUTED ? Color::DarkGray : Color::White;
        $shadow = $attr === self::MUTED ? Color::Black : Color::DarkGray;
        $faceOnShadow = new Attribute($foreground, $shadow)->toCellValue();
        $shadowOnShadow = new Attribute($shadow, $shadow)->toCellValue();
        $shadowOnly = new Attribute($shadow)->toCellValue();

        foreach (mb_str_split($encoded) as $offset => $char) {
            [$glyph, $color] = match ($char) {
                '_' => [' ', $shadowOnShadow],
                '^' => ['▀', $faceOnShadow],
                '~' => ['▀', $shadowOnly],
                ',' => ['▄', $shadowOnly],
                default => [$char, $attr],
            };
            $this->writeClipped($x + $offset, $y, $glyph, 1, $color);
        }
    }

    private function drawSession(int $width, int $height, bool $working): void
    {
        $contentWidth = min(108, $width - 8);
        $x = intdiv($width - $contentWidth, 2);
        $this->drawPanel($x, 1, $contentWidth, 3, self::MUTED);
        $this->writeClipped($x + 3, 2, '# Rebuild the OpenCode demo from the real interface', max(0, $contentWidth - 30), self::BRIGHT);
        $this->writeRight($x + $contentWidth - 2, 2, '12,840  8%  ($0.04)', self::MUTED);

        $this->drawPanel($x, 5, $contentWidth, 4, self::SECONDARY);
        $this->writeClipped($x + 3, 6, 'Make the demo actually look and behave like OpenCode.', $contentWidth - 6, self::BRIGHT);

        $row = 10;
        $this->writeClipped($x + 3, $row, "I'll map the official home, prompt, session, and model-dialog components.", $contentWidth - 6, self::TEXT);
        $this->writeClipped($x + 3, $row + 2, '✱ Grep  "Prompt|DialogModel|UserMessage"  packages/tui/src', $contentWidth - 6, self::MUTED);
        $this->writeClipped($x + 3, $row + 3, '→ Read  packages/tui/src/routes/home.tsx', $contentWidth - 6, self::MUTED);
        $this->writeClipped($x + 3, $row + 4, '→ Read  packages/tui/src/component/prompt/index.tsx', $contentWidth - 6, self::MUTED);

        if ($working) {
            $this->writeClipped($x + 3, $row + 6, 'The composition is now source-driven: centered home, sparse transcript, and modal picker.', $contentWidth - 6, self::TEXT);
            $this->writeClipped($x + 3, $row + 8, '✱ Edit  examples/php/OpenCode/OpenCodeView.php', $contentWidth - 6, self::MUTED);
            $this->writeClipped($x + 3, $row + 9, '∴ Running tests…', $contentWidth - 6, self::PRIMARY);
        } else {
            $this->writeClipped($x + 3, $row + 6, 'The home route centers a four-row logo above a prompt capped at 75 columns.', $contentWidth - 6, self::TEXT);
            $this->writeClipped($x + 3, $row + 8, '▣  Build  ·  Claude Opus 4.5  ·  3.2s', $contentWidth - 6, self::MUTED);
        }

        $this->drawComposer($x, $height - 8, $contentWidth, $working);
    }

    private function drawComposer(int $x, int $y, int $width, bool $working, bool $home = false): void
    {
        $panel = $this->promptAttribute(Color::DarkGray);
        $muted = $this->promptAttribute(Color::LightGray);
        $bright = $this->promptAttribute(Color::White);
        $accent = $this->promptAttribute($this->planMode ? Color::Brown : Color::LightBlue);
        $primary = $this->promptAttribute(Color::Yellow);

        $rail = new Attribute($this->planMode ? Color::Brown : Color::LightBlue)->toCellValue();
        $this->fillRect($x, $y, $width, 4, ' ', $panel);
        $this->fillRect($x, $y, 1, 4, '┃', $accent);
        $this->writeClipped($x, $y + 4, '╹', 1, $rail);
        $this->fillRect($x + 1, $y + 4, $width - 1, 1, '▀', self::PANEL);
        $text = $this->prompt === ''
            ? 'Ask anything... "Fix a TODO in the codebase"'
            : $this->prompt . '▌';
        $this->writeClipped($x + 3, $y + 1, $text, max(0, $width - 6), $this->prompt === '' ? $muted : $bright);
        $this->writeClipped($x + 3, $y + 3, $this->planMode ? 'Plan' : 'Build', 5, $accent);
        $this->writeClipped($x + 9, $y + 3, '·', 1, $muted);
        $model = self::MODELS[$this->modelIndex];
        $provider = $model['provider'] !== '' ? $model['provider'] : $model['category'];
        $this->writeClipped($x + 11, $y + 3, $model['name'], max(0, $width - 14), $bright);
        $providerX = $x + 12 + mb_strlen($model['name']);
        $this->writeClipped($providerX, $y + 3, $provider, max(0, $x + $width - $providerX - 2), $muted);
        if ($this->variantIndex > 0) {
            $variant = '· ' . strtolower(self::VARIANTS[$this->variantIndex]);
            $variantX = $providerX + mb_strlen($provider) + 1;
            $this->writeClipped($variantX, $y + 3, $variant, max(0, $x + $width - $variantX - 2), $primary);
        }

        $hintY = $y + 5;
        if ($home) {
            $this->writeClipped($x, $hintY, '~/code/tvision-php:main', max(0, $width - 35), self::MUTED);
        } elseif ($working) {
            $this->writeClipped($x + 1, $hintY, '⋯⋯⋯⋯  esc interrupt', 28, self::MUTED);
        }
        $shortcuts = $working
            ? 'ctrl+t variants   tab agents   ctrl+p commands'
            : 'shift+tab agents  ctrl+p commands';
        $this->writeRight($x + $width, $hintY, $shortcuts, self::MUTED);
    }

    private function promptAttribute(Color $foreground): int
    {
        return new Attribute($foreground, Color::DarkGray)->toCellValue();
    }

    private function drawModelPicker(int $width, int $height): void
    {
        if ($this->modelReturnState === OpenCodeDemoState::Home) {
            $this->drawHome($width, $height);
        } else {
            $this->drawSession($width, $height, $this->modelReturnState === OpenCodeDemoState::Working);
        }
        $dialogWidth = min(72, $width - 8);
        $dialogHeight = min(24, $height - 4);
        $x = intdiv($width - $dialogWidth, 2);
        $y = intdiv($height - $dialogHeight, 2);
        $this->fillRect($x, $y, $dialogWidth, $dialogHeight, ' ', self::CANVAS);
        $this->drawBox($x, $y, $dialogWidth, $dialogHeight, self::MUTED);
        $this->writeClipped($x + 3, $y + 2, 'Select model', $dialogWidth - 12, self::BRIGHT);
        $this->writeRight($x + $dialogWidth - 3, $y + 2, 'esc', self::MUTED);
        $this->writeClipped($x + 3, $y + 4, 'Search', $dialogWidth - 6, self::MUTED);

        $row = $y + 6;
        $category = '';
        foreach (self::MODELS as $index => $model) {
            if ($model['category'] !== $category) {
                $category = $model['category'];
                $this->writeClipped($x + 3, $row++, $category, $dialogWidth - 6, self::ACCENT);
            }
            $selected = $index === $this->modelIndex;
            if ($selected) {
                $this->fillRect($x + 1, $row, $dialogWidth - 2, 1, ' ', self::SELECTED);
            }
            $attr = $selected ? self::SELECTED : self::TEXT;
            $this->writeClipped($x + 3, $row, ($selected ? '● ' : '  ') . $model['name'], $dialogWidth - 16, $attr);
            $footer = $model['free'] ? 'Free' : $model['provider'];
            if ($footer !== '') {
                $this->writeRight($x + $dialogWidth - 4, $row, $footer, $selected ? self::SELECTED : self::MUTED);
            }
            $row++;
        }

        $this->writeClipped($x + 3, $y + $dialogHeight - 2, 'Connect provider', 20, self::BRIGHT);
        $this->writeClipped($x + 22, $y + $dialogHeight - 2, 'ctrl+a', 8, self::MUTED);
        $this->writeClipped($x + 32, $y + $dialogHeight - 2, 'Favorite', 12, self::BRIGHT);
        $this->writeClipped($x + 42, $y + $dialogHeight - 2, 'ctrl+f', 8, self::MUTED);
    }

    private function drawPermission(int $width, int $height): void
    {
        $this->drawSession($width, $height, false);
        $boxWidth = min(68, $width - 12);
        $x = intdiv($width - $boxWidth, 2);
        $y = $height - 15;
        $this->fillRect($x, $y, $boxWidth, 7, ' ', self::CANVAS);
        $this->drawBox($x, $y, $boxWidth, 7, self::PRIMARY);
        $this->writeClipped($x + 3, $y + 1, 'Permission required', $boxWidth - 6, self::PRIMARY);
        $this->writeClipped($x + 3, $y + 3, 'Run composer test?', $boxWidth - 6, self::BRIGHT);
        $this->writeClipped($x + 3, $y + 5, 'enter allow once     a always allow     esc deny', $boxWidth - 6, self::MUTED);
    }

    private function drawError(int $width, int $height): void
    {
        $this->drawSession($width, $height, false);
        $contentWidth = min(108, $width - 8);
        $x = intdiv($width - $contentWidth, 2);
        $y = $height - 15;
        $this->drawPanel($x, $y, $contentWidth, 4, self::ERROR);
        $this->writeClipped($x + 3, $y + 1, 'Provider connection closed before the response completed.', $contentWidth - 6, self::TEXT);
        $this->writeClipped($x + 3, $y + 2, 'enter retry   /connect change provider', $contentWidth - 6, self::MUTED);
    }

    private function drawCommandPalette(int $width, int $height): void
    {
        $dialogWidth = min(68, $width - 8);
        $dialogHeight = min(20, $height - 4);
        $x = intdiv($width - $dialogWidth, 2);
        $y = intdiv($height - $dialogHeight, 2);
        $this->fillRect($x, $y, $dialogWidth, $dialogHeight, ' ', self::CANVAS);
        $this->drawBox($x, $y, $dialogWidth, $dialogHeight, self::MUTED);
        $this->writeClipped($x + 3, $y + 2, 'Commands', $dialogWidth - 12, self::BRIGHT);
        $this->writeRight($x + $dialogWidth - 3, $y + 2, 'esc', self::MUTED);
        $this->writeClipped($x + 3, $y + 4, 'Search commands', $dialogWidth - 6, self::MUTED);

        $row = $y + 6;
        $category = '';
        foreach (self::COMMANDS as $index => $command) {
            if ($command['category'] !== $category) {
                $category = $command['category'];
                $this->writeClipped($x + 3, $row++, $category, $dialogWidth - 6, self::ACCENT);
            }
            $selected = $index === $this->commandIndex;
            if ($selected) {
                $this->fillRect($x + 1, $row, $dialogWidth - 2, 1, ' ', self::SELECTED);
            }
            $this->writeClipped(
                $x + 3,
                $row++,
                ($selected ? '● ' : '  ') . $command['title'],
                $dialogWidth - 6,
                $selected ? self::SELECTED : self::TEXT,
            );
        }

        $this->writeClipped($x + 3, $y + $dialogHeight - 2, '↑↓ navigate   enter select   esc close', $dialogWidth - 6, self::MUTED);
    }

    private function drawVariantPicker(int $width, int $height): void
    {
        $dialogWidth = min(48, $width - 8);
        $dialogHeight = 12;
        $x = intdiv($width - $dialogWidth, 2);
        $y = intdiv($height - $dialogHeight, 2);
        $this->fillRect($x, $y, $dialogWidth, $dialogHeight, ' ', self::CANVAS);
        $this->drawBox($x, $y, $dialogWidth, $dialogHeight, self::MUTED);
        $this->writeClipped($x + 3, $y + 2, 'Select variant', $dialogWidth - 12, self::BRIGHT);
        $this->writeRight($x + $dialogWidth - 3, $y + 2, 'esc', self::MUTED);

        foreach (self::VARIANTS as $index => $variant) {
            $row = $y + 4 + $index;
            $selected = $index === $this->variantIndex;
            if ($selected) {
                $this->fillRect($x + 1, $row, $dialogWidth - 2, 1, ' ', self::SELECTED);
            }
            $this->writeClipped(
                $x + 3,
                $row,
                ($selected ? '● ' : '  ') . $variant,
                $dialogWidth - 6,
                $selected ? self::SELECTED : self::TEXT,
            );
        }

        $this->writeClipped($x + 3, $y + $dialogHeight - 2, '↑↓ navigate   enter select', $dialogWidth - 6, self::MUTED);
    }

    private function drawMcpDialog(int $width, int $height): void
    {
        $dialogWidth = min(62, $width - 8);
        $dialogHeight = 14;
        $x = intdiv($width - $dialogWidth, 2);
        $y = intdiv($height - $dialogHeight, 2);
        $panel = $this->promptAttribute(Color::DarkGray);
        $bright = $this->promptAttribute(Color::White);
        $muted = $this->promptAttribute(Color::LightGray);
        $error = $this->promptAttribute(Color::LightRed);

        $this->fillRect($x, $y, $dialogWidth, $dialogHeight, ' ', $panel);
        $this->drawBox($x, $y, $dialogWidth, $dialogHeight, $error);
        $this->writeClipped($x + 3, $y + 2, 'MCP servers', $dialogWidth - 12, $bright);
        $this->writeRight($x + $dialogWidth - 3, $y + 2, 'esc', $muted);
        $this->writeClipped($x + 3, $y + 5, '●  asana', 24, $error);
        $this->writeRight($x + $dialogWidth - 4, $y + 5, 'failed', $error);
        $this->writeClipped($x + 6, $y + 7, 'Connection closed while starting the MCP server.', $dialogWidth - 10, $muted);
        $this->writeClipped($x + 3, $y + $dialogHeight - 2, 'enter close   esc close', $dialogWidth - 6, $muted);
    }

    private function drawMcpToast(int $width, int $height): void
    {
        if ($width < 100 || $height < 28) {
            return;
        }

        $toastWidth = min(52, $width - 6);
        $toastHeight = 5;
        $x = $width - $toastWidth - 2;
        $y = 2;
        $panel = $this->promptAttribute(Color::DarkGray);
        $bright = $this->promptAttribute(Color::White);
        $muted = $this->promptAttribute(Color::LightGray);
        $error = $this->promptAttribute(Color::LightRed);

        $this->fillRect($x, $y, $toastWidth, $toastHeight, ' ', $panel);
        $this->fillRect($x, $y, 1, $toastHeight, '┃', $error);
        $this->fillRect($x + $toastWidth - 1, $y, 1, $toastHeight, '┃', $error);
        $title = 'MCP server failed: asana';
        $this->writeClipped($x + 3, $y + 1, $title, $toastWidth - 6, $bright);
        $this->writeClipped($x + 5 + mb_strlen($title), $y + 1, '› Open MCP servers', 18, $muted);
        $this->writeClipped($x + 3, $y + 3, 'Run /mcps to view details.', $toastWidth - 6, $muted);
    }

    private function drawCompact(int $width, int $height): void
    {
        $this->writeClipped(2, 2, 'opencode', max(0, $width - 4), self::BRIGHT);
        $this->writeClipped(2, 4, 'Resize to at least 68 × 24.', max(0, $width - 4), self::TEXT);
        $this->writeClipped(2, 6, 'F3 changes screen · Alt-X quits', max(0, $width - 4), self::MUTED);
    }

    private function drawPanel(int $x, int $y, int $width, int $height, int $accent): void
    {
        if ($width <= 0 || $height <= 0) {
            return;
        }
        $this->fillRect($x, $y, $width, $height, ' ', self::PANEL);
        $this->fillRect($x, $y, 1, $height, '│', $accent);
    }

    private function fillRect(int $x, int $y, int $width, int $height, string $char, int $attr): void
    {
        if ($width <= 0 || $height <= 0) {
            return;
        }
        $buffer = new DrawBuffer($width);
        $buffer->moveChar(0, $char, $attr, $width);
        for ($row = 0; $row < $height; $row++) {
            $this->writeLine($x, $y + $row, $width, 1, $buffer);
        }
    }

    private function drawBox(int $x, int $y, int $width, int $height, int $attr): void
    {
        if ($width < 2 || $height < 2) {
            return;
        }
        $this->fillRect($x + 1, $y, $width - 2, 1, '─', $attr);
        $this->fillRect($x + 1, $y + $height - 1, $width - 2, 1, '─', $attr);
        for ($row = $y + 1; $row < $y + $height - 1; $row++) {
            $this->writeClipped($x, $row, '│', 1, $attr);
            $this->writeClipped($x + $width - 1, $row, '│', 1, $attr);
        }
        $this->writeClipped($x, $y, '┌', 1, $attr);
        $this->writeClipped($x + $width - 1, $y, '┐', 1, $attr);
        $this->writeClipped($x, $y + $height - 1, '└', 1, $attr);
        $this->writeClipped($x + $width - 1, $y + $height - 1, '┘', 1, $attr);
    }

    private function writeRight(int $right, int $y, string $text, int $attr): void
    {
        $this->writeClipped(max(0, $right - mb_strlen($text)), $y, $text, mb_strlen($text), $attr);
    }

    private function writeClipped(int $x, int $y, string $text, int $width, int $attr): void
    {
        if ($width <= 0 || $y < 0 || $y >= $this->bounds->height()) {
            return;
        }
        $this->writeStr($x, $y, mb_substr($text, 0, $width), $attr);
    }

    private function queue(Event $event): void
    {
        $owner = $this->owner;
        while ($owner !== null) {
            if ($owner instanceof Group) {
                $owner->putEvent($event);

                return;
            }
            $owner = $owner->owner;
        }
    }
}
