<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Help;

use HelgeSverre\TurboVision\Support\AtomicFileWriter;
/**
 * Portable, UTF-8 help-file store. Files are JSON prefixed by `TVPHPHELP 1\n` so they
 * are unmistakably not the legacy Borland H32 binary stream. The schema is deliberately
 * small and stable: `{version:1, topics:{context: {paragraphs, crossRefs}}}`.
 */
final class HelpFile
{
    public const string MAGIC = "TVPHPHELP 1\n";

    /** @var array<int,HelpTopic> */
    private array $topics = [];

    /**
     * @param array<int|string, HelpTopic> $topics keyed by help context; string keys
     *                                      must be canonical integer strings (the load path validates the same way)
     */
    public function __construct(array $topics = [])
    {
        foreach ($topics as $context => $topic) {
            // The docblock types promise valid input; these guards run for the
            // dynamically-constructed case (decoded config, tests) where that
            // promise cannot be checked statically.
            if (! is_int($context) && preg_match('/^\d+$/', $context) !== 1) {
                throw new \InvalidArgumentException("Help context key '{$context}' is not a valid integer context.");
            }
            if (! $topic instanceof HelpTopic) { // @phpstan-ignore-line runtime guard for dynamically-built maps
                throw new \InvalidArgumentException('Help file topics must be ' . HelpTopic::class . ' instances.');
            }
            $this->putTopic((int) $context, $topic);
        }
    }

    public static function load(string $path): self
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Unable to read help file '{$path}'.");
        }
        if (! str_starts_with($contents, self::MAGIC)) {
            throw new \UnexpectedValueException("'{$path}' is not a TVPHPHELP v1 file; legacy H32 help files are not supported.");
        }
        try {
            $payload = json_decode(substr($contents, strlen(self::MAGIC)), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \UnexpectedValueException("Invalid TVPHPHELP JSON in '{$path}'.", previous: $exception);
        }
        if (! is_array($payload) || ($payload['version'] ?? null) !== 1 || ! is_array($payload['topics'] ?? null)) {
            throw new \UnexpectedValueException("Unsupported TVPHPHELP schema in '{$path}'.");
        }
        $topics = [];
        foreach ($payload['topics'] as $context => $topic) {
            if (filter_var($context, FILTER_VALIDATE_INT) === false || ! is_array($topic)) {
                throw new \UnexpectedValueException("Invalid topic entry in '{$path}'.");
            }
            $topics[(int) $context] = HelpTopic::fromArray($topic);
        }

        return new self($topics);
    }

    public function save(string $path): void
    {
        $topics = [];
        ksort($this->topics, SORT_NUMERIC);
        foreach ($this->topics as $context => $topic) {
            $topics[(string) $context] = $topic->toArray();
        }
        try {
            $encoded = json_encode(['version' => 1, 'topics' => $topics], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Unable to encode help file.', previous: $exception);
        }
        AtomicFileWriter::write($path, self::MAGIC . $encoded . "\n");
    }

    public function getTopic(int $context): HelpTopic
    {
        return $this->topics[$context] ?? $this->fallbackTopic($context);
    }

    public function hasTopic(int $context): bool
    {
        return isset($this->topics[$context]);
    }

    public function putTopic(int $context, HelpTopic $topic): void
    {
        if ($context < 0) {
            throw new \InvalidArgumentException('Help contexts cannot be negative.');
        }
        $this->topics[$context] = $topic;
    }

    /** @return array<int,HelpTopic> */
    public function topics(): array
    {
        return $this->topics;
    }

    /** The friendly "no help" fallback shown when a context has no topic. */
    public function fallbackTopic(int $context): HelpTopic
    {
        return new HelpTopic([new HelpParagraph("No help is available for context {$context}.", false)]);
    }
}
