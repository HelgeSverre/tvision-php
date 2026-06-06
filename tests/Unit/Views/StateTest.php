<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Views\State;

test('state flags carry their faithful sf* values', function (): void {
    expect(State::Visible)->toBe(0x001)
        ->and(State::CursorVis)->toBe(0x002)
        ->and(State::Shadow)->toBe(0x008)
        ->and(State::Active)->toBe(0x010)
        ->and(State::Selected)->toBe(0x020)
        ->and(State::Focused)->toBe(0x040)
        ->and(State::Disabled)->toBe(0x100)
        ->and(State::Modal)->toBe(0x200)
        ->and(State::Exposed)->toBe(0x800);
});

test('option flags carry their faithful of* values', function (): void {
    expect(State::Selectable)->toBe(0x001)
        ->and(State::TopSelect)->toBe(0x002)
        ->and(State::FirstClick)->toBe(0x004)
        ->and(State::Framed)->toBe(0x008)
        ->and(State::PreProcess)->toBe(0x010)
        ->and(State::PostProcess)->toBe(0x020)
        ->and(State::Centered)->toBe(0x300);
});

test('grow-mode flags carry their faithful gf* values', function (): void {
    expect(State::GrowLoX)->toBe(0x01)
        ->and(State::GrowLoY)->toBe(0x02)
        ->and(State::GrowHiX)->toBe(0x04)
        ->and(State::GrowHiY)->toBe(0x08)
        ->and(State::GrowAll)->toBe(0x0f);
});

test('drag-mode flags match Turbo Vision dm* values', function (): void {
    expect(State::DragMove)->toBe(0x01)
        ->and(State::DragGrow)->toBe(0x02)
        ->and(State::LimitLoX)->toBe(0x10)
        ->and(State::LimitLoY)->toBe(0x20)
        ->and(State::LimitHiX)->toBe(0x40)
        ->and(State::LimitHiY)->toBe(0x80)
        ->and(State::LimitAll)->toBe(0xF0);
});
