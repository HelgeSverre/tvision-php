<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Help;

use HelgeSverre\TurboVision\Drawing\TerminalText;

/** Compiles the documented `.topic Name[=number]` TVHC source subset into TVPHPHELP. */
final class HelpCompiler
{
    /** @return array<string,int> topic name => context */
    public function compile(string $source, string $output, ?string $symbolsOutput = null): array
    {
        $result = $this->parse($source);
        $result['file']->save($output);
        if ($symbolsOutput !== null) {
            $this->writeSymbols($symbolsOutput, $result['symbols']);
        }

        return $result['symbols'];
    }

    /** @return array{file:HelpFile,symbols:array<string,int>} */
    public function parse(string $source): array
    {
        // The original TVHC examples are DOS/Windows code-page text. Preserve the
        // modern UTF-8 contract while making those source files practical fixtures.
        if (! mb_check_encoding($source, 'UTF-8')) {
            $source = mb_convert_encoding($source, 'UTF-8', 'Windows-1252');
        }
        $lines = preg_split('/\R/u', $source) ?: [];
        $definitions = [];
        $nextContext = 2;
        foreach ($lines as $line) {
            if (preg_match('/^\.topic\s+(.+)$/i', trim($line), $matches) !== 1) {
                continue;
            }
            foreach (preg_split('/\s*,\s*/', $matches[1]) ?: [] as $definition) {
                if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(?:\s*=\s*(\d+))?$/', trim($definition), $parts) !== 1) {
                    throw new \UnexpectedValueException("Invalid .topic declaration '{$definition}'.");
                }
                $context = isset($parts[2]) ? (int) $parts[2] : $nextContext;
                if (isset($definitions[$parts[1]])) {
                    throw new \UnexpectedValueException("Duplicate help topic '{$parts[1]}'.");
                }
                $definitions[$parts[1]] = $context;
                $nextContext = max($nextContext, $context + 1);
            }
        }

        $file = new HelpFile();
        /** @var list<string> $currentNames */
        $currentNames = [];
        /** @var list<string> $body */
        $body = [];
        $flush = function () use (&$file, &$currentNames, &$body, &$definitions): void {
            if ($currentNames === []) {
                return;
            }
            $topic = $this->buildTopic($body, $definitions);
            foreach ($currentNames as $name) {
                $file->putTopic($definitions[$name], $topic);
            }
            $currentNames = [];
            $body = [];
        };
        foreach ($lines as $line) {
            if (preg_match('/^\.topic\s+(.+)$/i', trim($line), $matches) === 1) {
                $flush();
                $currentNames = [];
                foreach (preg_split('/\s*,\s*/', $matches[1]) ?: [] as $definition) {
                    if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)/', trim($definition), $parts) !== 1) {
                        throw new \LogicException('A validated topic declaration could not be parsed.');
                    }
                    $currentNames[] = $parts[1];
                }
                continue;
            }
            if ($currentNames !== []) {
                $body[] = $line;
            }
        }
        $flush();

        return ['file' => $file, 'symbols' => $definitions];
    }

    /**
     * @param list<string> $body
     * @param array<string,int> $definitions
     */
    private function buildTopic(array $body, array $definitions): HelpTopic
    {
        $topic = new HelpTopic();
        $offset = 0;
        /** @var list<string> $block */
        $block = [];
        $wrapped = true;
        foreach ($body as $line) {
            if (trim($line) === '') {
                $offset = $this->appendBlock($topic, $block, $wrapped, $offset, $definitions);
                $block = [];
                continue;
            }
            $lineWrapped = ! str_starts_with($line, ' ');
            if ($block !== [] && $lineWrapped !== $wrapped) {
                $offset = $this->appendBlock($topic, $block, $wrapped, $offset, $definitions);
                $block = [];
            }
            $wrapped = $lineWrapped;
            $block[] = $line;
        }
        $this->appendBlock($topic, $block, $wrapped, $offset, $definitions);

        return $topic;
    }

    /**
     * @param list<string> $block
     * @param array<string,int> $definitions
     */
    private function appendBlock(HelpTopic $topic, array $block, bool $wrapped, int $offset, array $definitions): int
    {
        if ($block === []) {
            return $offset;
        }
        $text = $wrapped
            ? implode(' ', array_map('trim', $block))
            : implode("\n", array_map(static fn (string $line): string => ltrim($line, ' '), $block));
        [$text, $references] = $this->extractReferences($text, $offset, $definitions);
        $topic->addParagraph(new HelpParagraph($text, $wrapped));
        foreach ($references as $reference) {
            $topic->addCrossRef($reference);
        }

        return $offset + TerminalText::length($text);
    }

    /**
     * @param array<string,int> $definitions
     * @return array{0:string,1:list<CrossRef>}
     */
    private function extractReferences(string $text, int $baseOffset, array $definitions): array
    {
        $references = [];
        $visible = '';
        $position = 0;
        while (preg_match('/\{([^{}]+)\}/u', $text, $match, PREG_OFFSET_CAPTURE, $position) === 1) {
            $before = substr($text, $position, $match[0][1] - $position);
            $visible .= str_replace('{{', '{', $before);
            $targetSpec = $match[1][0];
            $parts = explode(':', $targetSpec, 2);
            $label = $parts[0];
            $target = $parts[1] ?? $label;
            if (! isset($definitions[$target])) {
                throw new \UnexpectedValueException("Unknown help cross-reference '{$target}'.");
            }
            $label = str_replace('{{', '{', $label);
            $references[] = new CrossRef($definitions[$target], $baseOffset + TerminalText::length($visible), TerminalText::length($label), $label);
            $visible .= $label;
            $position = $match[0][1] + strlen($match[0][0]);
        }
        $visible .= str_replace('{{', '{', substr($text, $position));

        return [$visible, $references];
    }

    /** @param array<string,int> $symbols */
    private function writeSymbols(string $path, array $symbols): void
    {
        ksort($symbols);
        $lines = ["<?php", '', 'declare(strict_types=1);', '', '// Generated by bin/tvhc.'];
        foreach ($symbols as $name => $context) {
            $lines[] = "const hc{$name} = {$context};";
        }
        AtomicFileWriter::write($path, implode("\n", $lines) . "\n");
    }
}
