<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Events\Cmd;

test('standard command codes match Turbo Vision', function (): void {
    expect(Cmd::Valid)->toBe(0)
        ->and(Cmd::Quit)->toBe(1)
        ->and(Cmd::Error)->toBe(2)
        ->and(Cmd::Menu)->toBe(3)
        ->and(Cmd::Close)->toBe(4)
        ->and(Cmd::Zoom)->toBe(5)
        ->and(Cmd::Resize)->toBe(6)
        ->and(Cmd::Next)->toBe(7)
        ->and(Cmd::Help)->toBe(9)
        ->and(Cmd::Ok)->toBe(10)
        ->and(Cmd::Cancel)->toBe(11)
        ->and(Cmd::Yes)->toBe(12)
        ->and(Cmd::No)->toBe(13)
        ->and(Cmd::Prev)->toBe(8)
        ->and(Cmd::Default)->toBe(14);
});

test('user commands begin at 100', function (): void {
    expect(Cmd::FirstUser)->toBe(100);
});

test('M2 broadcast and window command codes match Turbo Vision', function (): void {
    expect(Cmd::CloseAll)->toBe(37)
        ->and(Cmd::ReceivedFocus)->toBe(50)
        ->and(Cmd::ReleasedFocus)->toBe(51)
        ->and(Cmd::CommandSetChanged)->toBe(52)
        ->and(Cmd::ScrollBarChanged)->toBe(53)
        ->and(Cmd::ScrollBarClicked)->toBe(54)
        ->and(Cmd::SelectWindowNum)->toBe(55)
        ->and(Cmd::ListItemSelected)->toBe(56);
});
