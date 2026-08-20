<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Studio;

enum StudioComponentType: string
{
    case Panel = 'panel';
    case Label = 'label';
    case Button = 'button';
    case Input = 'input';
    case ListBox = 'list';
    case Checkbox = 'checkbox';
    case Separator = 'separator';
    case Radio = 'radio';
    case Progress = 'progress';
    case TextArea = 'textarea';

    public function label(): string
    {
        return match ($this) {
            self::Panel => 'Panel',
            self::Label => 'Label',
            self::Button => 'Button',
            self::Input => 'Text Input',
            self::ListBox => 'List Box',
            self::Checkbox => 'Checkbox',
            self::Separator => 'Separator',
            self::Radio => 'Radio',
            self::Progress => 'Progress',
            self::TextArea => 'Text Area',
        };
    }

    public function icon(): string
    {
        // Keep toolbox sigils to three printable ASCII columns. Emoji presentation
        // and Nerd Font private-use glyphs do not have portable terminal widths.
        return match ($this) {
            self::Panel => '[P]',
            self::Label => '[T]',
            self::Button => '[B]',
            self::Input => '[I]',
            self::ListBox => '[L]',
            self::Checkbox => '[X]',
            self::Separator => '[-]',
            self::Radio => '[R]',
            self::Progress => '[=]',
            self::TextArea => '[A]',
        };
    }

    public function defaultText(): string
    {
        return match ($this) {
            self::Panel => 'Panel',
            self::Label => 'New label',
            self::Button => 'Continue',
            self::Input => 'Type here…',
            self::ListBox => "First item|Second item|Third item",
            self::Checkbox => 'Enabled',
            self::Separator => '',
            self::Radio => 'Selected option',
            self::Progress => '65',
            self::TextArea => 'Notes|Add details here',
        };
    }

    /** @return array{int, int} */
    public function defaultSize(): array
    {
        return match ($this) {
            self::Panel => [24, 8],
            self::Label => [14, 1],
            self::Button => [14, 1],
            self::Input => [22, 1],
            self::ListBox => [22, 5],
            self::Checkbox => [18, 1],
            self::Separator => [22, 1],
            self::Radio => [20, 1],
            self::Progress => [22, 1],
            self::TextArea => [24, 5],
        };
    }

    /** @return array{int, int} */
    public function minimumSize(): array
    {
        return match ($this) {
            self::Panel => [8, 4],
            self::ListBox => [8, 3],
            self::Button => [6, 1],
            self::Input => [6, 1],
            self::Progress => [10, 1],
            self::TextArea => [8, 3],
            self::Label, self::Checkbox, self::Radio, self::Separator => [3, 1],
        };
    }
}
