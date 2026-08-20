<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\OpenCode;

enum OpenCodeDemoState: string
{
    case Home = 'HOME';
    case Session = 'SESSION';
    case Working = 'WORKING';
    case ModelPicker = 'MODEL PICKER';
    case Permission = 'PERMISSION';
    case Error = 'ERROR';

    public function next(): self
    {
        return match ($this) {
            self::Home => self::Session,
            self::Session => self::Working,
            self::Working => self::ModelPicker,
            self::ModelPicker => self::Permission,
            self::Permission => self::Error,
            self::Error => self::Home,
        };
    }
}
