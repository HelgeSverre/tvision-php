<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Legacy;

use HelgeSverre\TurboVision\Application\Application;
use HelgeSverre\TurboVision\Dialogs\ParamText;
use HelgeSverre\TurboVision\Geometry\Rect;
use HelgeSverre\TurboVision\Persistence\StreamCodec;
use HelgeSverre\TurboVision\Persistence\StreamableRegistry;
use HelgeSverre\TurboVision\Resources\ResourceFile;
use HelgeSverre\TurboVision\Resources\StringList;
use HelgeSverre\TurboVision\Terminal\Screen;
use HelgeSverre\TurboVision\Views\Desktop;
use HelgeSverre\TurboVision\Views\Window;

/**
 * Modern PHP counterpart to load.cc: a small system-load window plus an explicit,
 * safe ResourceFile round-trip instead of a global C++ streamer registry.
 */
final class LoadApp extends Application
{
    /** @param list<string> $loads */
    public function __construct(?Screen $screenOverride = null, private array $loads = ['0.18', '0.31', '0.44'])
    {
        parent::__construct($screenOverride);
    }

    protected function initDeskTop(Rect $bounds): Desktop
    {
        $desktop = parent::initDeskTop($bounds);
        assert($desktop instanceof Desktop);
        $desktop->insertWindow($this->createLoadWindow());

        return $desktop;
    }

    public function createLoadWindow(): Window
    {
        $window = new Window(Rect::of(3, 2, 38, 12), 'System load');
        foreach (['1 min', '5 min', '15 min'] as $index => $label) {
            $text = new ParamText(Rect::of(3, 1 + $index * 2, 32, 2 + $index * 2));
            $text->setText('%-8s %s', $label, $this->loads[$index] ?? 'n/a');
            $window->insert($text);
        }
        $window->insert(new ParamText(Rect::of(3, 8, 32, 9), 'Resource-backed sample'));

        return $window;
    }

    /** @param list<string> $loads */
    public static function saveLoads(string $path, array $loads): void
    {
        $resource = self::resourceFile($path);
        $resource->put('system-load-labels', new StringList($loads));
        $resource->flush();
    }

    /** @return list<string> */
    public static function loadLoads(string $path): array
    {
        $stored = self::resourceFile($path)->get('system-load-labels');

        return $stored instanceof StringList ? $stored->all() : [];
    }

    private static function resourceFile(string $path): ResourceFile
    {
        $registry = new StreamableRegistry;
        $registry->registerClass(StringList::STREAM_TYPE, StringList::class);

        return ResourceFile::open($path, new StreamCodec($registry));
    }
}
