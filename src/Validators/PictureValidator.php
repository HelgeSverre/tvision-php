<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Validators;

use HelgeSverre\TurboVision\Drawing\TerminalText;

/**
 * Paradox-compatible picture-mask validator.
 *
 * The grammar deliberately follows Turbo Vision: # digit, ? letter, & uppercase
 * letter, ! uppercase arbitrary character, @ arbitrary character, [] optional
 * group, {} required group, commas for alternatives, and ';' to quote a literal.
 * A leading `*` repeats the next atom; `*N` requires exactly N repetitions.
 * This implementation keeps the public result states while using a bounded
 * backtracking matcher rather than C++'s pointer-index parser.
 */
final class PictureValidator extends Validator
{
    /** Caps all recursive matcher branches per call, preventing ambiguous masks from exploding. */
    private const int MatchWorkBudget = 4096;

    /** @var list<PictureNode> */
    private array $tree = [];

    private int $workRemaining = 0;

    private bool $workExceeded = false;

    public function __construct(public readonly string $pictureMask, bool $autoFill = false)
    {
        if ($autoFill) {
            $this->options |= self::OptionFill;
        }

        $offset = 0;
        $this->tree = $this->parseSequence($offset);
        if ($offset !== strlen($pictureMask) || $this->status !== self::StatusOk) {
            $this->status = self::StatusSyntax;
        }
    }

    public function isValidInput(string &$input, bool $suppressFill = false): bool
    {
        $result = $this->picture($input, !$suppressFill && ($this->options & self::OptionFill) !== 0);

        return $result !== PicResult::Error && $result !== PicResult::Syntax;
    }

    public function isValid(string $input): bool
    {
        return $this->picture($input, false) === PicResult::Complete;
    }

    public function error(): void
    {
        $this->setError($this->status === self::StatusSyntax
            ? sprintf('Error in picture format: %s', $this->pictureMask)
            : sprintf('Input does not match picture format: %s', $this->pictureMask));
    }

    /** Match, normalize, and optionally fill literal separators in `$input`. */
    public function picture(string &$input, bool $autoFill = false): PicResult
    {
        if ($this->status !== self::StatusOk) {
            return PicResult::Syntax;
        }
        if ($input === '') {
            return PicResult::Empty;
        }

        $this->workRemaining = self::MatchWorkBudget;
        $this->workExceeded = false;
        $characters = TerminalText::graphemes($input);
        $matches = $this->matchSequence($this->tree, $characters, 0);
        if ($this->workExceeded) {
            return PicResult::Error;
        }
        $length = count($characters);
        $complete = array_values(array_filter($matches, static fn (PictureMatch $match): bool => $match->pos === $length && $match->complete));
        if ($complete !== []) {
            /** @var list<string> $normalized */
            $normalized = $complete[0]->out;
            $input = implode('', $normalized);

            return PicResult::Complete;
        }

        $prefixes = array_values(array_filter($matches, static fn (PictureMatch $match): bool => $match->pos === $length));
        if ($prefixes === []) {
            return PicResult::Error;
        }

        /** @var list<string> $normalized */
        $normalized = $prefixes[0]->out;
        if ($autoFill) {
            $normalized = $this->appendDeterministicLiterals($this->tree, $normalized, $length);
        }
        $input = implode('', $normalized);

        return PicResult::Incomplete;
    }

    /**
     * `complete` says whether every required atom in the remaining mask was supplied.
     *
     * @param list<PictureNode> $nodes
     * @param list<string> $input
     * @return list<PictureMatch>
     * @phpstan-impure Mutates the per-call bounded-work counter.
     */
    private function matchSequence(array $nodes, array $input, int $position): array
    {
        /** @var list<PictureMatch> $states */
        $states = [new PictureMatch($position, [], true)];
        foreach ($nodes as $node) {
            /** @var list<PictureMatch> $next */
            $next = [];
            foreach ($states as $state) {
                foreach ($this->matchNode($node, $input, $state->pos) as $match) {
                    if (!$this->reserveWork()) {
                        return [];
                    }
                    $next[] = new PictureMatch(
                        $match->pos,
                        [...$state->out, ...$match->out],
                        $state->complete && $match->complete,
                    );
                }
            }
            $states = $this->uniqueStates($next);
            if ($states === []) {
                return [];
            }
        }

        return $states;
    }

    /**
     * @param list<string> $input
     * @return list<PictureMatch>
     */
    private function matchNode(PictureNode $node, array $input, int $position): array
    {
        $character = $input[$position] ?? null;
        if (($node->type === PictureNodeType::Literal || $node->type === PictureNodeType::Slot) && $node->value !== null) {
            return $node->type === PictureNodeType::Literal
                ? $this->matchLiteral($node->value, $character, $position)
                : $this->matchSlot($node->value, $character, $position);
        }
        if ($node->type === PictureNodeType::Group) {
            return $this->matchGroup($node, $input, $position);
        }
        if ($node->type === PictureNodeType::Repeat && $node->node !== null) {
            return $this->matchRepeat($node->node, $input, $position, $node->repeatCount);
        }

        return [];
    }

    /** @return list<PictureMatch> */
    private function matchLiteral(string $literal, ?string $character, int $position): array
    {
        if ($character === null) {
            return [new PictureMatch($position, [], false)];
        }
        if (mb_strtoupper($character, 'UTF-8') !== mb_strtoupper($literal, 'UTF-8')) {
            return [];
        }

        return [new PictureMatch($position + 1, [$literal], true)];
    }

    /** @return list<PictureMatch> */
    private function matchSlot(string $slot, ?string $character, int $position): array
    {
        if ($character === null) {
            return [new PictureMatch($position, [], false)];
        }

        $valid = match ($slot) {
            '#' => preg_match('/^\p{N}$/u', $character) === 1,
            '?', '&' => preg_match('/^\p{L}$/u', $character) === 1,
            '!', '@' => $character !== '',
            default => false,
        };
        if (!$valid) {
            return [];
        }

        $out = ($slot === '&' || $slot === '!') ? mb_strtoupper($character, 'UTF-8') : $character;

        return [new PictureMatch($position + 1, [$out], true)];
    }

    /**
     * @param list<string> $input
     * @return list<PictureMatch>
     */
    private function matchGroup(PictureNode $node, array $input, int $position): array
    {
        /** @var list<PictureMatch> $matches */
        $matches = [];
        foreach ($node->alternatives as $alternative) {
            foreach ($this->matchSequence($alternative, $input, $position) as $match) {
                if (!$this->reserveWork()) {
                    return [];
                }
                $matches[] = $match;
            }
        }

        if ($node->optional) {
            if (!$this->reserveWork()) {
                return [];
            }
            $matches[] = new PictureMatch($position, [], true);
        }

        return $this->uniqueStates($matches);
    }

    /**
     * @param list<string> $input
     * @return list<PictureMatch>
     */
    private function matchRepeat(PictureNode $node, array $input, int $position, ?int $repeatCount): array
    {
        if ($repeatCount !== null && $repeatCount > 0) {
            return $this->matchExactRepeat($node, $input, $position, $repeatCount);
        }

        /** @var list<PictureMatch> $states */
        $states = [new PictureMatch($position, [], true)];
        $frontier = $states;
        // Never iterate more than input characters + one. The extra iteration is
        // enough to report an unfinished first atom, while preventing empty-group loops.
        for ($iterations = 0, $limit = count($input) + 1; $iterations < $limit; $iterations++) {
            /** @var list<PictureMatch> $next */
            $next = [];
            foreach ($frontier as $state) {
                foreach ($this->matchNode($node, $input, $state->pos) as $match) {
                    if ($match->pos === $state->pos) {
                        continue;
                    }
                    if (!$this->reserveWork()) {
                        return [];
                    }
                    $combined = new PictureMatch(
                        $match->pos,
                        [...$state->out, ...$match->out],
                        $state->complete && $match->complete,
                    );
                    $next[] = $combined;
                    $states[] = $combined;
                }
            }
            if ($next === []) {
                break;
            }
            $frontier = $this->uniqueStates($next);
        }

        return $this->uniqueStates($states);
    }

    /**
     * Match a `*N` sequence. A completed zero-width optional item is idempotent,
     * so it can return early; an incomplete item cannot become valid in later
     * iterations and is likewise safe to return early. Both cases prevent a huge
     * but valid numeric count from becoming a CPU-sized loop.
     *
     * @param list<string> $input
     * @return list<PictureMatch>
     */
    private function matchExactRepeat(PictureNode $node, array $input, int $position, int $repeatCount): array
    {
        /** @var list<PictureMatch> $states */
        $states = [new PictureMatch($position, [], true)];
        for ($iteration = 0; $iteration < $repeatCount; $iteration++) {
            /** @var list<PictureMatch> $next */
            $next = [];
            foreach ($states as $state) {
                foreach ($this->matchNode($node, $input, $state->pos) as $match) {
                    if (!$this->reserveWork()) {
                        return [];
                    }
                    $next[] = new PictureMatch(
                        $match->pos,
                        [...$state->out, ...$match->out],
                        $state->complete && $match->complete,
                    );
                }
            }
            $states = $this->uniqueStates($next);
            if ($states === []) {
                return [];
            }

            $madeProgress = false;
            $hasIncomplete = false;
            foreach ($states as $state) {
                $madeProgress = $madeProgress || $state->pos > $position;
                $hasIncomplete = $hasIncomplete || !$state->complete;
            }
            if ($hasIncomplete || !$madeProgress) {
                return $states;
            }
            $position = min(array_map(static fn (PictureMatch $state): int => $state->pos, $states));
        }

        return $states;
    }

    private function reserveWork(): bool
    {
        if ($this->workRemaining <= 0) {
            $this->workExceeded = true;

            return false;
        }
        $this->workRemaining--;

        return true;
    }

    /**
     * @param list<PictureMatch> $states
     * @return list<PictureMatch>
     */
    private function uniqueStates(array $states): array
    {
        /** @var array<string, PictureMatch> $unique */
        $unique = [];
        foreach ($states as $state) {
            $key = $state->pos . '|' . ($state->complete ? '1' : '0') . '|' . implode('', $state->out);
            $unique[$key] = $state;
        }

        return array_values($unique);
    }

    /**
     * Auto-fill only deterministic literal prefixes after the consumed text. This
     * is intentionally conservative around alternatives, optional, and repeated
     * groups: guessing there would corrupt a user's in-progress edit.
     *
     * @param list<PictureNode> $nodes
     * @param list<string> $output
     * @return list<string>
     */
    private function appendDeterministicLiterals(array $nodes, array $output, int $consumed): array
    {
        $position = 0;
        foreach ($nodes as $node) {
            if ($node->type === PictureNodeType::Literal) {
                if ($position < $consumed) {
                    $position++;
                } else {
                    if ($node->value !== null) {
                        $output[] = $node->value;
                    }
                }
                continue;
            }
            if ($node->type === PictureNodeType::Slot) {
                if ($position >= $consumed) {
                    break;
                }
                $position++;
                continue;
            }
            // Groups and repetitions can have more than one valid continuation.
            break;
        }

        return $output;
    }

    /** @return list<PictureNode> */
    private function parseSequence(int &$offset): array
    {
        $nodes = [];
        $length = strlen($this->pictureMask);
        while ($offset < $length) {
            $character = $this->pictureMask[$offset];
            if ($character === ']' || $character === '}') {
                $this->status = self::StatusSyntax;
                return [];
            }
            if ($character === ',') {
                // The containing parser consumes alternatives; at top level a comma
                // is simply a syntax error.
                $this->status = self::StatusSyntax;
                return [];
            }
            $offset++;
            if ($character === ';') {
                if ($offset >= $length) {
                    $this->status = self::StatusSyntax;
                    return [];
                }
                $nodes[] = new PictureNode(PictureNodeType::Literal, $this->pictureMask[$offset++]);
                continue;
            }
            if ($character === '[' || $character === '{') {
                $close = $character === '[' ? ']' : '}';
                $nodes[] = new PictureNode(PictureNodeType::Group, optional: $character === '[', alternatives: $this->parseAlternatives($offset, $close));
                continue;
            }
            if ($character === '*') {
                $nodes[] = $this->parseRepeat($offset);
                continue;
            }
            $nodes[] = in_array($character, ['#', '?', '&', '!', '@'], true)
                ? new PictureNode(PictureNodeType::Slot, $character)
                : new PictureNode(PictureNodeType::Literal, $character);
        }

        return $nodes;
    }

    /** @return list<list<PictureNode>> */
    private function parseAlternatives(int &$offset, string $terminator): array
    {
        /** @var list<list<PictureNode>> $alternatives */
        $alternatives = [];
        /** @var list<PictureNode> $current */
        $current = [];
        $length = strlen($this->pictureMask);
        while ($offset < $length) {
            if ($this->pictureMask[$offset] === $terminator) {
                $offset++;
                $alternatives[] = $current;

                return $alternatives;
            }
            if ($this->pictureMask[$offset] === ',') {
                $offset++;
                $alternatives[] = $current;
                $current = [];
                continue;
            }
            $current[] = $this->parseAtom($offset);
        }
        $this->status = self::StatusSyntax;

        return [];
    }

    /** @return PictureNode */
    private function parseAtom(int &$offset): PictureNode
    {
        $length = strlen($this->pictureMask);
        if ($offset >= $length) {
            $this->status = self::StatusSyntax;

            return new PictureNode(PictureNodeType::Literal, '');
        }
        $character = $this->pictureMask[$offset++];
        if ($character === ';') {
            if ($offset >= $length) {
                $this->status = self::StatusSyntax;

                return new PictureNode(PictureNodeType::Literal, '');
            }

            return new PictureNode(PictureNodeType::Literal, $this->pictureMask[$offset++]);
        }
        if ($character === '[' || $character === '{') {
            return new PictureNode(
                PictureNodeType::Group,
                optional: $character === '[',
                alternatives: $this->parseAlternatives($offset, $character === '[' ? ']' : '}'),
            );
        }
        if ($character === '*') {
            return $this->parseRepeat($offset);
        }

        return in_array($character, ['#', '?', '&', '!', '@'], true)
            ? new PictureNode(PictureNodeType::Slot, $character)
            : new PictureNode(PictureNodeType::Literal, $character);
    }

    private function parseRepeat(int &$offset): PictureNode
    {
        $length = strlen($this->pictureMask);
        $count = 0;
        $hasCount = false;
        while ($offset < $length && ctype_digit($this->pictureMask[$offset])) {
            $hasCount = true;
            $digit = ord($this->pictureMask[$offset]) - ord('0');
            if ($count > intdiv(PHP_INT_MAX - $digit, 10)) {
                $this->status = self::StatusSyntax;
                // Consume the numeric token before returning a harmless placeholder,
                // preserving deterministic parser progress for malformed input.
                while ($offset < $length && ctype_digit($this->pictureMask[$offset])) {
                    $offset++;
                }

                return new PictureNode(PictureNodeType::Repeat, node: new PictureNode(PictureNodeType::Literal, ''), repeatCount: 0);
            }
            $count = $count * 10 + $digit;
            $offset++;
        }
        $repeatCount = $hasCount ? max(0, $count) : null;
        if ($offset >= $length) {
            $this->status = self::StatusSyntax;

            return new PictureNode(PictureNodeType::Repeat, node: new PictureNode(PictureNodeType::Literal, ''), repeatCount: $repeatCount);
        }

        return new PictureNode(
            PictureNodeType::Repeat,
            node: $this->parseAtom($offset),
            repeatCount: $repeatCount,
        );
    }
}
