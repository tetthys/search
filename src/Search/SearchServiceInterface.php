<?php

declare(strict_types=1);

namespace Tetthys\Search;

/**
 * Contract for search services.
 *
 * Responsibilities:
 * - Expose a single entrypoint `search()`.
 * - Accept arbitrary input (array/DTO) and optional options.
 * - Return an iterable of results (array, Generator, LazyCollection, etc.).
 *
 * Notes:
 * - Keep this lean to stay framework-agnostic and easily mockable in tests.
 */
interface SearchServiceInterface
{
    /**
     * Perform a search with the given input and options.
     *
     * @param mixed $input   Arbitrary search input (array/DTO).
     * @param array<string,mixed>|null $options Optional flags (pagination, limits, etc.).
     * @return iterable<mixed> Result rows/records/documents.
     */
    public function search(mixed $input, ?array $options = null): iterable;
}
