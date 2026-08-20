<?php

declare(strict_types=1);

namespace HelgeSverre\TurboVision\Examples\Studio;

use JsonException;
use RuntimeException;

final class StudioProjectStore
{
    private const int MAX_FILE_BYTES = 2_000_000;

    public function save(string $path, StudioProject $project): void
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            throw new RuntimeException("Project directory does not exist: {$directory}");
        }

        try {
            $json = json_encode($project->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not encode the Studio project.', 0, $exception);
        }

        $temporary = tempnam($directory, '.studio-');
        if ($temporary === false) {
            throw new RuntimeException("Could not create a temporary project file in {$directory}.");
        }

        try {
            if (file_put_contents($temporary, $json . "\n", LOCK_EX) === false || ! rename($temporary, $path)) {
                throw new RuntimeException("Could not save Studio project to {$path}.");
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    public function load(string $path): StudioProject
    {
        $size = @filesize($path);
        if ($size !== false && $size > self::MAX_FILE_BYTES) {
            throw new RuntimeException("Studio project is larger than 2 MB: {$path}.");
        }
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Could not read Studio project from {$path}.");
        }

        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid Studio project JSON in {$path}.", 0, $exception);
        }
        if (! is_array($data) || array_is_list($data)) {
            throw new RuntimeException("Invalid Studio project data in {$path}.");
        }

        $projectData = [];
        foreach ($data as $key => $value) {
            if (! is_string($key)) {
                throw new RuntimeException("Invalid Studio project data in {$path}.");
            }
            $projectData[$key] = $value;
        }

        try {
            return StudioProject::fromArray($projectData);
        } catch (\InvalidArgumentException $exception) {
            throw new RuntimeException("Invalid Studio project data in {$path}: {$exception->getMessage()}", 0, $exception);
        }
    }
}
