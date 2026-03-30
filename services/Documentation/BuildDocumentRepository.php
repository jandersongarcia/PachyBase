<?php

declare(strict_types=1);

namespace PachyBase\Services\Documentation;

use JsonException;
use PachyBase\Config;
use PachyBase\Utils\BooleanParser;

final class BuildDocumentRepository
{
    public function __construct(
        private readonly ?string $basePath = null
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadOpenApi(): ?array
    {
        return $this->loadDocument(
            'openapi.json',
            static fn(array $document): bool => isset($document['openapi'], $document['paths'])
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadAiSchema(): ?array
    {
        return $this->loadDocument(
            'ai-schema.json',
            static fn(array $document): bool => isset($document['schema_version'], $document['entities'])
        );
    }

    /**
     * @param callable(array<string, mixed>): bool $validator
     * @return array<string, mixed>|null
     */
    private function loadDocument(string $filename, callable $validator): ?array
    {
        if (!$this->shouldLoadBuildDocuments()) {
            return null;
        }

        $path = $this->buildPath($filename);

        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if (!is_string($contents) || trim($contents) === '') {
            return null;
        }

        try {
            $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($document) || !$validator($document)) {
            return null;
        }

        return $document;
    }

    private function shouldLoadBuildDocuments(): bool
    {
        return BooleanParser::fromMixed(Config::get('APP_PREFER_BUILD_DOCS', true));
    }

    private function buildPath(string $filename): string
    {
        return ($this->basePath ?? dirname(__DIR__, 2))
            . DIRECTORY_SEPARATOR
            . 'build'
            . DIRECTORY_SEPARATOR
            . $filename;
    }
}
