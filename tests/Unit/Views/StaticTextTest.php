<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\StaticText;
use HelgeSverre\TurboVision\Views\TextAlignment;

/** Root group exposing a Screen so StaticText can composite somewhere assertable. */
final class StaticTextRoot extends Group
{
    public function __construct(private readonly Screen $rootScreen)
    {
        parent::__construct(Rect::of(0, 0, $rootScreen->cols(), $rootScreen->rows()));
    }

    public function screen(): Screen
    {
        return $this->rootScreen;
    }
}

test('draws a single line of text at the view origin', function (): void {
    $screen = new Screen(new HeadlessDriver(10, 2));
    $screen->init();
    $root = new StaticTextRoot($screen);

    $text = new StaticText(Rect::of(1, 0, 9, 1), 'Hello');
    $root->insert($text);

    $text->draw();

    expect($screen->back()->rows())->toBe([' Hello    ', '          ']);
});

test('word-wraps text that exceeds the view width', function (): void {
    $screen = new Screen(new HeadlessDriver(12, 3));
    $screen->init();
    $root = new StaticTextRoot($screen);

    $text = new StaticText(Rect::of(0, 0, 6, 3), 'one two three');
    $root->insert($text);

    $text->draw();

    // width 6: "one " then "two " then "three" wrapped onto separate lines
    expect($screen->back()->rows()[0])->toBe('one         ')
        ->and($screen->back()->rows()[1])->toBe('two         ')
        ->and($screen->back()->rows()[2])->toBe('three       ');
});

test('word wrapping retains every chunk of a word wider than the view', function (): void {
    $screen = new Screen(new HeadlessDriver(8, 3));
    $screen->init();
    $root = new StaticTextRoot($screen);
    $text = new StaticText(Rect::of(0, 0, 4, 3), 'abcdefghij');
    $root->insert($text);

    $text->draw();

    expect($screen->back()->rows())->toBe([
        'abcd    ',
        'efgh    ',
        'ij      ',
    ]);
});

test('preserves explicit and blank lines while wrapping each line', function (): void {
    $screen = new Screen(new HeadlessDriver(10, 4));
    $screen->init();
    $root = new StaticTextRoot($screen);
    $text = StaticText::centered(Rect::of(0, 0, 9, 4), "Hello\n\nwide words");
    $root->insert($text);

    $text->draw();

    expect($screen->back()->rows())->toBe([
        '  Hello   ',
        '          ',
        '  wide    ',
        '  words   ',
    ]);
});

test('a leading \003 control char centers the line', function (): void {
    $screen = new Screen(new HeadlessDriver(10, 1));
    $screen->init();
    $root = new StaticTextRoot($screen);

    $text = new StaticText(Rect::of(0, 0, 9, 1), "\003Hi");
    $root->insert($text);

    $text->draw();

    // "Hi" is 2 wide in a 9-wide view -> left pad (9-2)/2 = 3 spaces
    expect($screen->back()->rows()[0])->toBe('   Hi     ');
});

test('supports typed alignment and named alignment factories', function (): void {
    $screen = new Screen(new HeadlessDriver(10, 3));
    $screen->init();
    $root = new StaticTextRoot($screen);

    $centered = new StaticText(
        Rect::of(0, 0, 9, 1),
        'Hi',
        alignment: TextAlignment::Center,
    );
    $factory = StaticText::centered(Rect::of(0, 1, 9, 2), 'Hi');
    $right = StaticText::rightAligned(Rect::of(0, 2, 9, 3), 'Hi');
    $root->insert($centered);
    $root->insert($factory);
    $root->insert($right);

    $root->draw();

    expect($factory)->toBeInstanceOf(StaticText::class)
        ->and($right)->toBeInstanceOf(StaticText::class)
        ->and($screen->back()->rows())->toBe([
            '   Hi     ',
            '   Hi     ',
            '       Hi ',
        ]);
});
