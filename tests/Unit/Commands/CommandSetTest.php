<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Commands\CommandSet;
use HelgeSverre\TurboVision\Commands\CommandTarget;
use HelgeSverre\TurboVision\Events\Cmd;

final class CommandSetRecordingTarget implements CommandTarget
{
    /** @var list<string> */
    public array $calls = [];

    /** @var array<int, true> */
    private array $disabled = [];

    public function enableCommand(int $command): void
    {
        $this->calls[] = "enable:$command";
        unset($this->disabled[$command]);
    }

    public function disableCommand(int $command): void
    {
        $this->calls[] = "disable:$command";
        $this->disabled[$command] = true;
    }

    public function commandEnabled(int $command): bool
    {
        return ! isset($this->disabled[$command]);
    }
}

test('command sets are immutable, unique, and deterministically ordered', function (): void {
    $set = CommandSet::of(Cmd::Quit, Cmd::Close, Cmd::Quit, Cmd::FirstUser + 200);

    expect($set->all())->toBe([Cmd::Quit, Cmd::Close, Cmd::FirstUser + 200])
        ->and($set->count())->toBe(3)
        ->and($set->contains(Cmd::Close))->toBeTrue()
        ->and($set->contains(Cmd::Help))->toBeFalse()
        ->and($set->isEmpty())->toBeFalse();
});

test('command sets reject non-integer iterable values', function (): void {
    expect(fn () => CommandSet::from([Cmd::Quit, 'not-a-command']))
        ->toThrow(InvalidArgumentException::class, 'integer command identifiers');
});

test('command set algebra returns values without changing either operand', function (): void {
    $navigation = CommandSet::of(Cmd::Next, Cmd::Prev, Cmd::Close);
    $window = CommandSet::of(Cmd::Close, Cmd::Resize, Cmd::Zoom);

    expect($navigation->union($window)->all())->toBe([Cmd::Close, Cmd::Zoom, Cmd::Resize, Cmd::Next, Cmd::Prev])
        ->and($navigation->intersect($window)->all())->toBe([Cmd::Close])
        ->and($navigation->without($window)->all())->toBe([Cmd::Next, Cmd::Prev])
        ->and($navigation->with(Cmd::Help)->withoutCommand(Cmd::Close)->all())->toBe([Cmd::Next, Cmd::Prev, Cmd::Help])
        ->and($navigation->all())->toBe([Cmd::Close, Cmd::Next, Cmd::Prev])
        ->and(CommandSet::none()->isEmpty())->toBeTrue()
        ->and(CommandSet::of(Cmd::Prev, Cmd::Close)->equals(CommandSet::of(Cmd::Close, Cmd::Prev)))->toBeTrue();
});

test('a command set batches target state changes in deterministic command order', function (): void {
    $target = new CommandSetRecordingTarget();
    $commands = CommandSet::of(Cmd::Next, Cmd::Close, Cmd::Help);

    $commands->disableOn($target);
    expect($target->calls)->toBe(['disable:4', 'disable:7', 'disable:9'])
        ->and($target->commandEnabled(Cmd::Close))->toBeFalse();

    $commands->enableOn($target);
    expect($target->calls)->toBe([
        'disable:4', 'disable:7', 'disable:9',
        'enable:4', 'enable:7', 'enable:9',
    ])->and($target->commandEnabled(Cmd::Close))->toBeTrue();
});
