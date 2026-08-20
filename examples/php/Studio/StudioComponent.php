<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Studio;

use InvalidArgumentException;

final class StudioComponent
{
    public const int MAX_TEXT_LENGTH = 512;

    public const int MAX_ID = 999_999;

    public function __construct(
        public readonly int $id,
        public StudioComponentType $type,
        public int $x,
        public int $y,
        public int $width,
        public int $height,
        public string $text,
    ) {
        if ($id < 1 || $id > self::MAX_ID) {
            throw new InvalidArgumentException('Studio component IDs must be between 1 and 999999.');
        }
        $this->text = self::cleanText($text);
    }

    /** @return array{id:int,type:string,x:int,y:int,width:int,height:int,text:string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'height' => $this->height,
            'text' => $this->text,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        foreach (['id', 'type', 'x', 'y', 'width', 'height', 'text'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw new InvalidArgumentException('Invalid Studio component data.');
            }
        }
        $type = is_string($data['type']) ? StudioComponentType::tryFrom($data['type']) : null;
        if ($type === null
            || ! is_int($data['id'])
            || ! is_int($data['x'])
            || ! is_int($data['y'])
            || ! is_int($data['width'])
            || ! is_int($data['height'])
            || ! is_string($data['text'])
        ) {
            throw new InvalidArgumentException('Invalid Studio component data.');
        }

        return new self(
            $data['id'],
            $type,
            $data['x'],
            $data['y'],
            $data['width'],
            $data['height'],
            $data['text'],
        );
    }

    public static function cleanText(string $text): string
    {
        $text = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $text) ?? '';

        return mb_substr($text, 0, self::MAX_TEXT_LENGTH);
    }
}
