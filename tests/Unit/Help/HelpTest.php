<?php

declare(strict_types=1);

use HelgeSverre\TurboVision\Drivers\HeadlessDriver;
use HelgeSverre\TurboVision\Events\Event;
use HelgeSverre\TurboVision\Events\Key;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Help\CrossRef;
use HelgeSverre\TurboVision\Support\AtomicFileWriter;
use HelgeSverre\TurboVision\Help\HelpCompiler;
use HelgeSverre\TurboVision\Help\HelpFile;
use HelgeSverre\TurboVision\Help\HelpParagraph;
use HelgeSverre\TurboVision\Help\HelpTopic;
use HelgeSverre\TurboVision\Help\HelpViewer;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Group;

final class HelpTestRoot extends Group
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

test('topics wrap text and locate cross references in rendered coordinates', function (): void {
    $topic = new HelpTopic(
        [new HelpParagraph('Read Guide now.', true)],
        [new CrossRef(2, 5, 5, 'Guide')],
    );
    $topic->setWidth(8);

    expect($topic->lines())->toBe(['Read', 'Guide', 'now.'])
        ->and($topic->crossRefLocation(0))->toEqual(new \HelgeSverre\TurboVision\Geometry\Point(0, 1));
});

test('help files round trip as documented UTF-8 TVPHPHELP files', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'tvhelp-');
    expect($path)->not->toBeFalse();
    $file = new HelpFile([7 => new HelpTopic([new HelpParagraph('Hei 👋', false)])]);
    $file->save($path);
    $loaded = HelpFile::load($path);
    @unlink($path);

    expect($loaded->getTopic(7)->getLine(0, 40))->toBe('Hei 👋')
        ->and($loaded->getTopic(99)->getLine(0, 40))->toContain('99');
});

test('compiler resolves forward references, aliases, preformatted paragraphs and symbols', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/Help/forward-links.txt');
    if ($source === false) {
        throw new RuntimeException('Unable to load help compiler fixture.');
    }
    $output = tempnam(sys_get_temp_dir(), 'tvhelp-');
    $symbols = tempnam(sys_get_temp_dir(), 'tvhelp-');
    expect($output)->not->toBeFalse()->and($symbols)->not->toBeFalse();
    $contexts = (new HelpCompiler())->compile($source, $output, $symbols);
    $file = HelpFile::load($output);
    $symbolText = file_get_contents($symbols);
    @unlink($output);
    @unlink($symbols);

    expect($contexts)->toBe(['Start' => 10, 'Guide' => 11])
        ->and($file->getTopic(10)->getLine(0, 80))->toBe('Read the guide.')
        ->and($file->getTopic(10)->getCrossRef(0)->ref)->toBe(11)
        ->and($file->getTopic(10)->getLine(1, 80))->toBe('code line')
        ->and($symbolText)->toContain('const hcGuide = 11;');
});

test('viewer renders links and follows the selected reference with Enter', function (): void {
    $screen = new Screen(new HeadlessDriver(20, 5));
    $screen->init();
    $root = new HelpTestRoot($screen);
    $file = new HelpFile([
        1 => new HelpTopic([new HelpParagraph('Go next', false)], [new CrossRef(2, 3, 4)]),
        2 => new HelpTopic([new HelpParagraph('Destination', false)]),
    ]);
    $viewer = new HelpViewer(Rect::of(0, 0, 20, 5), null, null, $file, 1);
    $root->insert($viewer);
    $viewer->draw();

    expect($screen->back()->rows()[0])->toStartWith('Go next')
        ->and($viewer->selected)->toBe(0);

    $viewer->handleEvent(Event::key(Key::Enter));

    expect($viewer->topic->getLine(0, 20))->toBe('Destination')
        ->and($viewer->selected)->toBeNull();
});

test('compiler rejects unknown links and legacy help files fail safely', function (): void {
    expect(fn (): array => (new HelpCompiler())->parse(".topic Start\n{Missing}\n"))
        ->toThrow(UnexpectedValueException::class, 'Unknown help cross-reference');
    $path = tempnam(sys_get_temp_dir(), 'tvhelp-');
    file_put_contents($path, 'FBHF');
    expect(fn (): HelpFile => HelpFile::load($path))
        ->toThrow(UnexpectedValueException::class, 'legacy H32');
    @unlink($path);
});

test('atomic writer replaces complete artifacts and leaves an existing directory target intact on failure', function (): void {
    $directory = sys_get_temp_dir() . '/tvhelp-' . bin2hex(random_bytes(6));
    mkdir($directory);
    $target = $directory . '/artifact.tvhelp';
    file_put_contents($target, 'old');

    AtomicFileWriter::write($target, 'new complete payload');

    expect(file_get_contents($target))->toBe('new complete payload');

    $directoryTarget = $directory . '/preserve-me';
    mkdir($directoryTarget);
    expect(fn (): null => AtomicFileWriter::write($directoryTarget, 'must not replace a directory'))
        ->toThrow(RuntimeException::class, 'is a directory')
        ->and(is_dir($directoryTarget))->toBeTrue()
        ->and(glob($directory . '/.tvphp-*'))->toBe([]);

    unlink($target);
    rmdir($directoryTarget);
    rmdir($directory);
});

it('rejects duplicate explicit help contexts instead of silently overwriting topics', function (): void {
    expect(fn (): array => (new HelpCompiler())->parse(".topic Alpha=3\nalpha body\n.topic Beta=3\nbeta body\n"))
        ->toThrow(\UnexpectedValueException::class, 'already assigned to topic');
});

it('still resolves forward cross-references across topics after the single-pass rework', function (): void {
    $result = (new HelpCompiler())->parse(".topic First\nsee {Later:Second}\n.topic Second\nlater body\n");
    // 'First' auto-assigns context 2; 'Second' becomes context 3 even though
    // it is referenced before it is declared.
    $first = $result['file']->getTopic(2);
    expect($first->paragraphs()[0]->text)->toBe('see Later')
        ->and($first->crossRefs()[0]->ref)->toBe(3)
        ->and($result['file']->getTopic(3)->paragraphs()[0]->text)->toBe('later body');
});
