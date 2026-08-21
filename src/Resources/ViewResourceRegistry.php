<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Resources;

use Closure;
use HelgeSverre\TurboVision\Views\Group;
use HelgeSverre\TurboVision\Views\View;
use InvalidArgumentException;

/**
 * Explicit allow-list of factories that may materialize ViewResource nodes.
 *
 * Type strings are application-owned stable identifiers, not PHP class names.
 * Register only constructors whose properties you have intentionally designed
 * for persisted input.
 */
final class ViewResourceRegistry
{
    /** @var array<string, Closure> */
    private array $factories = [];

    /** @param Closure(ViewResourceNode): View $factory */
    public function register(string $type, Closure $factory): self
    {
        if (trim($type) === '') {
            throw new InvalidArgumentException('A view resource factory type must not be empty.');
        }
        if (isset($this->factories[$type])) {
            throw new InvalidArgumentException("A view resource factory is already registered for '{$type}'.");
        }
        $this->factories[$type] = $factory;

        return $this;
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->factories);
    }

    public function build(ViewResourceNode $node): View
    {
        $factory = $this->factories[$node->type] ?? null;
        if ($factory === null) {
            throw new ResourceException("View resource type '{$node->type}' is not registered.");
        }
        try {
            $view = $factory($node);
        } catch (ResourceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ResourceException("Could not build view resource type '{$node->type}'.", 0, $exception);
        }
        if (! $view instanceof View) {
            throw new ResourceException("View resource factory '{$node->type}' did not return a View.");
        }
        if ($node->children === []) {
            return $view;
        }
        if (! $view instanceof Group) {
            throw new ResourceException("View resource type '{$node->type}' cannot own children because it is not a Group.");
        }
        foreach ($node->children as $child) {
            $view->insert($this->build($child));
        }

        return $view;
    }
}
