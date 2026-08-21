<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Dialogs\History;
use HelgeSverre\TurboVision\Dialogs\InputLine;
use HelgeSverre\TurboVision\Events\Cmd;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\EventType;
use HelgeSverre\TurboVision\Events\MouseEvent;
use HelgeSverre\TurboVision\Geometry\Point;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\View;

final class HistoryModalCaptureGroup extends Group
{
    public ?Rect $modalBounds = null;

    public function execView(View $modal): int
    {
        $this->modalBounds = $modal->getBounds();

        return Cmd::Cancel;
    }
}

test('history records its linked input on focus release and explicit record broadcasts', function (): void {
    $historyId = 731;
    History::clear($historyId);
    $group = new Group(Rect::of(0, 0, 60, 10));
    $input = new InputLine(Rect::of(1, 1, 30, 2), 40);
    $history = new History(Rect::of(31, 1, 32, 2), $input, $historyId);
    $group->insert($input);
    $group->insert($history);

    $input->setText(' first query ');
    $group->handleEvent(Event::broadcast(Cmd::ReleasedFocus, $input));
    expect(History::items($historyId))->toBe(['first query']);

    $input->setText('second query');
    $group->handleEvent(Event::broadcast(Cmd::RecordHistory));
    expect(History::items($historyId))->toBe(['second query', 'first query']);
});

test('history records through a real group focus transition', function (): void {
    $historyId = 733;
    History::clear($historyId);
    $group = new Group(Rect::of(0, 0, 60, 10));
    $input = new InputLine(Rect::of(1, 1, 30, 2), 40);
    $history = new History(Rect::of(31, 1, 32, 2), $input, $historyId);
    $other = new InputLine(Rect::of(1, 3, 30, 4), 40);
    $group->insert($input);
    $group->insert($history);
    $group->insert($other);
    $group->setCurrent($input);

    $input->setText('remember me');
    $group->setCurrent($other);

    expect(History::items($historyId))->toBe(['remember me']);
});

test('history keeps the newest duplicate once and bounds a list to 255 items', function (): void {
    $historyId = 732;
    History::clear($historyId);
    $history = new History(Rect::of(0, 0, 1, 1), null, $historyId);
    for ($i = 0; $i < 260; $i++) {
        $history->recordHistory("entry {$i}");
    }
    $history->recordHistory('entry 40');

    $items = History::items($historyId);
    expect($items)->toHaveCount(255)
        ->and($items[0])->toBe('entry 40')
        ->and(array_keys(array_filter($items, static fn (string $item): bool => $item === 'entry 40')))->toHaveCount(1);
});

test('history positions its popup in its immediate owner coordinate system', function (): void {
    $root = new Group(Rect::of(0, 0, 80, 25));
    $owner = new HistoryModalCaptureGroup(Rect::of(20, 5, 70, 20));
    $input = new InputLine(Rect::of(2, 2, 30, 3), 40);
    $history = new History(Rect::of(30, 2, 33, 3), $input, 734);
    $owner->insert($input);
    $owner->insert($history);
    $root->insert($owner);

    $owner->handleEvent(Event::mouse(
        EventType::MouseDown,
        new MouseEvent(new Point(51, 7)),
    ));

    expect($owner->modalBounds)->toEqual(Rect::of(30, 3, 62, 12));
});
