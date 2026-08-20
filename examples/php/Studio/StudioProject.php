<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Studio;

use InvalidArgumentException;

final class StudioProject
{
    public const int MAX_WIDTH = 200;

    public const int MAX_HEIGHT = 80;

    public const int MAX_COMPONENTS = 256;

    /** @var list<StudioComponent> */
    private array $components;

    /** @param list<StudioComponent> $components */
    public function __construct(
        private string $name,
        private int $width,
        private int $height,
        array $components = [],
        private int $nextId = 1,
        private string $themeName = 'Graphite',
    ) {
        if ($width < 20 || $height < 8 || $width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
            throw new InvalidArgumentException('Studio canvases must be between 20 × 8 and 200 × 80.');
        }
        if (count($components) > self::MAX_COMPONENTS) {
            throw new InvalidArgumentException('Studio projects cannot contain more than 256 components.');
        }
        if ($nextId < 1 || $nextId > StudioComponent::MAX_ID + 1) {
            throw new InvalidArgumentException('Invalid next Studio component ID.');
        }
        if (StudioTheme::named($themeName) === null) {
            throw new InvalidArgumentException('Invalid Studio project theme.');
        }
        $this->name = self::cleanName($name);
        $this->nextId = $nextId;
        $this->components = $components;
        $ids = [];
        foreach ($this->components as $component) {
            if (isset($ids[$component->id])) {
                throw new InvalidArgumentException('Studio component IDs must be unique.');
            }
            $ids[$component->id] = true;
            $this->clamp($component);
            $this->nextId = max($this->nextId, $component->id + 1);
        }
    }

    public static function starter(): self
    {
        return new self('Welcome Dashboard', 52, 18, [
            new StudioComponent(1, StudioComponentType::Panel, 1, 1, 50, 16, 'Command Center'),
            new StudioComponent(2, StudioComponentType::Label, 4, 3, 31, 1, 'Welcome to Turbo Studio'),
            new StudioComponent(3, StudioComponentType::Input, 4, 5, 27, 1, 'Search commands…'),
            new StudioComponent(4, StudioComponentType::Button, 34, 5, 12, 1, 'Run'),
            new StudioComponent(5, StudioComponentType::Separator, 4, 7, 42, 1, ''),
            new StudioComponent(6, StudioComponentType::Checkbox, 4, 9, 18, 1, 'Live preview'),
            new StudioComponent(7, StudioComponentType::ListBox, 4, 11, 20, 4, 'Dashboard|Calendar|Logs'),
            new StudioComponent(8, StudioComponentType::Radio, 26, 9, 20, 1, 'Fast mode'),
            new StudioComponent(9, StudioComponentType::Progress, 27, 11, 19, 1, '65'),
            new StudioComponent(10, StudioComponentType::TextArea, 27, 12, 19, 4, 'Build notes|Ready to launch'),
        ], 11);
    }

    public static function blank(): self
    {
        return new self('Untitled Interface', 52, 18);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function themeName(): string
    {
        return $this->themeName;
    }

    public function setThemeName(string $themeName): void
    {
        if (StudioTheme::named($themeName) === null) {
            throw new InvalidArgumentException('Invalid Studio project theme.');
        }
        $this->themeName = $themeName;
    }

    /** @return list<StudioComponent> */
    public function components(): array
    {
        return $this->components;
    }

    public function component(int $id): ?StudioComponent
    {
        foreach ($this->components as $component) {
            if ($component->id === $id) {
                return $component;
            }
        }

        return null;
    }

    public function canAdd(): bool
    {
        return count($this->components) < self::MAX_COMPONENTS
            && $this->nextId <= StudioComponent::MAX_ID;
    }

    public function add(StudioComponentType $type): StudioComponent
    {
        if (! $this->canAdd()) {
            throw new InvalidArgumentException('The Studio project cannot accept another component.');
        }
        [$width, $height] = $type->defaultSize();
        $offset = count($this->components) % 8;
        $component = new StudioComponent(
            $this->nextId++,
            $type,
            2 + $offset * 2,
            1 + $offset,
            $width,
            $height,
            $type->defaultText(),
        );
        $this->clamp($component);
        $this->components[] = $component;

        return $component;
    }

    public function duplicate(int $id): ?StudioComponent
    {
        $source = $this->component($id);
        if ($source === null) {
            return null;
        }
        if (! $this->canAdd()) {
            throw new InvalidArgumentException('The Studio project cannot accept another component.');
        }

        $copy = new StudioComponent(
            $this->nextId++,
            $source->type,
            $source->x + 2,
            $source->y + 1,
            $source->width,
            $source->height,
            $source->text . ' copy',
        );
        $this->clamp($copy);
        $this->components[] = $copy;

        return $copy;
    }

    public function delete(int $id): bool
    {
        foreach ($this->components as $index => $component) {
            if ($component->id === $id) {
                array_splice($this->components, $index, 1);

                return true;
            }
        }

        return false;
    }

    public function move(int $id, int $x, int $y): void
    {
        $component = $this->component($id);
        if ($component === null) {
            return;
        }
        $component->x = $x;
        $component->y = $y;
        $this->clamp($component);
    }

    public function resize(int $id, int $width, int $height): void
    {
        $component = $this->component($id);
        if ($component === null) {
            return;
        }
        [$minimumWidth, $minimumHeight] = $component->type->minimumSize();
        $component->width = max($minimumWidth, $width);
        $component->height = max($minimumHeight, $height);
        $this->clamp($component);
    }

    public function setText(int $id, string $text): void
    {
        $component = $this->component($id);
        if ($component !== null) {
            $component->text = StudioComponent::cleanText($text);
        }
    }

    public function align(int $id, StudioAlignment $alignment): void
    {
        $component = $this->component($id);
        if ($component === null) {
            return;
        }

        [$x, $y] = match ($alignment) {
            StudioAlignment::Left => [0, $component->y],
            StudioAlignment::HorizontalCenter => [intdiv($this->width - $component->width, 2), $component->y],
            StudioAlignment::Top => [$component->x, 0],
            StudioAlignment::VerticalCenter => [$component->x, intdiv($this->height - $component->height, 2)],
        };
        $this->move($id, $x, $y);
    }

    public function bringForward(int $id): void
    {
        foreach ($this->components as $index => $component) {
            if ($component->id === $id && $index < count($this->components) - 1) {
                $moving = array_splice($this->components, $index, 1);
                array_splice($this->components, $index + 1, 0, $moving);

                return;
            }
        }
    }

    public function sendBackward(int $id): void
    {
        foreach ($this->components as $index => $component) {
            if ($component->id === $id && $index > 0) {
                $moving = array_splice($this->components, $index, 1);
                array_splice($this->components, $index - 1, 0, $moving);

                return;
            }
        }
    }

    /** @return array{version:int,name:string,width:int,height:int,theme:string,nextId:int,components:list<array{id:int,type:string,x:int,y:int,width:int,height:int,text:string}>} */
    public function toArray(): array
    {
        return [
            'version' => 1,
            'name' => $this->name,
            'width' => $this->width,
            'height' => $this->height,
            'theme' => $this->themeName,
            'nextId' => $this->nextId,
            'components' => array_map(
                static fn (StudioComponent $component): array => $component->toArray(),
                $this->components,
            ),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (($data['version'] ?? null) !== 1
            || ! isset($data['name'], $data['width'], $data['height'], $data['components'], $data['nextId'])
            || ! is_string($data['name'])
            || ! is_int($data['width'])
            || ! is_int($data['height'])
            || ! is_array($data['components'])
            || ! array_is_list($data['components'])
            || ! is_int($data['nextId'])
            || (array_key_exists('theme', $data) && ! is_string($data['theme']))
        ) {
            throw new InvalidArgumentException('Invalid Studio project data.');
        }

        $components = [];
        foreach ($data['components'] as $componentData) {
            if (! is_array($componentData)) {
                throw new InvalidArgumentException('Invalid Studio component entry.');
            }
            $normalizedComponent = [];
            foreach ($componentData as $key => $value) {
                if (! is_string($key)) {
                    throw new InvalidArgumentException('Invalid Studio component entry.');
                }
                $normalizedComponent[$key] = $value;
            }
            $components[] = StudioComponent::fromArray($normalizedComponent);
        }

        return new self(
            $data['name'],
            $data['width'],
            $data['height'],
            $components,
            $data['nextId'],
            $data['theme'] ?? 'Graphite',
        );
    }

    private function clamp(StudioComponent $component): void
    {
        [$minimumWidth, $minimumHeight] = $component->type->minimumSize();
        $component->width = min($this->width, max($minimumWidth, $component->width));
        $component->height = min($this->height, max($minimumHeight, $component->height));
        $component->x = max(0, min($this->width - $component->width, $component->x));
        $component->y = max(0, min($this->height - $component->height, $component->y));
    }

    private static function cleanName(string $name): string
    {
        $name = StudioComponent::cleanText($name);
        $name = trim($name);

        return mb_substr($name !== '' ? $name : 'Untitled Interface', 0, 80);
    }
}
