<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Commands;

/**
 * An immutable set of command identifiers.
 *
 * This is the PHP counterpart to Turbo Vision's TCommandSet. Unlike the
 * original fixed 0..255 bitset, it accepts every integer command identifier:
 * application-defined commands are therefore just as safe to batch as the
 * framework's standard commands. Methods that would have mutated TCommandSet
 * return a new value instead.
 */
final readonly class CommandSet
{
    /** @var array<int, true> */
    private array $commands;

    /** @param iterable<mixed> $commands */
    private function __construct(iterable $commands)
    {
        $normalized = [];

        foreach ($commands as $command) {
            if (! is_int($command)) {
                throw new \InvalidArgumentException('A command set can contain only integer command identifiers.');
            }

            $normalized[$command] = true;
        }

        ksort($normalized, SORT_NUMERIC);
        $this->commands = $normalized;
    }

    /** Create an empty command set. */
    public static function none(): self
    {
        return new self([]);
    }

    /** Create a set from individual command identifiers. */
    public static function of(int ...$commands): self
    {
        return new self($commands);
    }

    /**
     * @param iterable<mixed> $commands Runtime validation keeps iterators and
     *                                      decoded configuration data safe.
     */
    public static function from(iterable $commands): self
    {
        return new self($commands);
    }

    public function contains(int $command): bool
    {
        return isset($this->commands[$command]);
    }

    public function isEmpty(): bool
    {
        return $this->commands === [];
    }

    public function count(): int
    {
        return count($this->commands);
    }

    /** @return list<int> command identifiers in deterministic numeric order */
    public function all(): array
    {
        return array_keys($this->commands);
    }

    /** Return the commands found in either set. */
    public function union(self $other): self
    {
        return new self([...$this->all(), ...$other->all()]);
    }

    /** Return the commands found in both sets. */
    public function intersect(self $other): self
    {
        return new self(array_keys(array_intersect_key($this->commands, $other->commands)));
    }

    /** Return this set excluding every command present in $other. */
    public function without(self $other): self
    {
        return new self(array_keys(array_diff_key($this->commands, $other->commands)));
    }

    /** Return this set with one additional command. */
    public function with(int $command): self
    {
        return $this->union(self::of($command));
    }

    /** Return this set with one command removed. */
    public function withoutCommand(int $command): self
    {
        return $this->without(self::of($command));
    }

    public function equals(self $other): bool
    {
        return $this->commands === $other->commands;
    }

    /** Enable every member command on a target in deterministic order. */
    public function enableOn(CommandTarget $target): void
    {
        foreach ($this->all() as $command) {
            $target->enableCommand($command);
        }
    }

    /** Disable every member command on a target in deterministic order. */
    public function disableOn(CommandTarget $target): void
    {
        foreach ($this->all() as $command) {
            $target->disableCommand($command);
        }
    }
}
