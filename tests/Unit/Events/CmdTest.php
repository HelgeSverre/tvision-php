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

test('broadcast and window command codes match Turbo Vision', function (): void {
    expect(Cmd::CloseAll)->toBe(37)
        ->and(Cmd::ReceivedFocus)->toBe(50)
        ->and(Cmd::ReleasedFocus)->toBe(51)
        ->and(Cmd::CommandSetChanged)->toBe(52)
        ->and(Cmd::ScrollBarChanged)->toBe(53)
        ->and(Cmd::ScrollBarClicked)->toBe(54)
        ->and(Cmd::SelectWindowNum)->toBe(55)
        ->and(Cmd::ListItemSelected)->toBe(56);
});

test('standard application, dialog, colour, file, outline, and editor command families match Turbo Vision', function (): void {
    expect(Cmd::Cut)->toBe(20)
        ->and(Cmd::Cascade)->toBe(26)
        ->and(Cmd::New)->toBe(30)
        ->and(Cmd::DosShell)->toBe(36)
        ->and(Cmd::SysWakeup)->toBe(40)
        ->and(Cmd::RecordHistory)->toBe(60)
        ->and(Cmd::GrabDefault)->toBe(61)
        ->and(Cmd::ReleaseDefault)->toBe(62)
        ->and(Cmd::ColorForegroundChanged)->toBe(71)
        ->and(Cmd::SaveColorIndex)->toBe(76)
        ->and(Cmd::Find)->toBe(82)
        ->and(Cmd::SearchAgain)->toBe(84)
        ->and(Cmd::FileFocused)->toBe(102)
        ->and(Cmd::FileDoubleClicked)->toBe(103)
        ->and(Cmd::FileOpen)->toBe(1001)
        ->and(Cmd::DirSelection)->toBe(1007)
        ->and(Cmd::OutlineItemSelected)->toBe(301)
        ->and(Cmd::CharLeft)->toBe(500)
        ->and(Cmd::UpdateTitle)->toBe(523);
});

test('new applications can use a non-colliding command range', function (): void {
    expect(Cmd::FirstUser)->toBe(100)
        ->and(Cmd::FirstSafeUser)->toBe(200);
});
