<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Help;

/** One source paragraph. Wrapped paragraphs flow at the viewer width. */
final readonly class HelpParagraph
{
    public function __construct(
        public string $text,
        public bool $wrap = true,
    ) {}

    /** @return array{text:string,wrap:bool} */
    public function toArray(): array
    {
        return ['text' => $this->text, 'wrap' => $this->wrap];
    }
}
