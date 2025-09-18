<?php

declare(strict_types=1);

namespace Tetthys\Search;

use Tetthys\Search\Supports\QueryCaptureTestSupportTrait;

/**
 * AbstractSearchService
 *
 * Functional-lean A/C/D split:
 * - A (Action): `actSearch()` orchestrates the flow and side-effects.
 * - C (Calculation): `calcBuildQuery()` is pure; transforms input -> query.
 * - D (Data): `dataFetch()` hits I/O; overridable, test-injectable.
 *
 * Extensibility:
 * - Subclasses implement domain-specific `calcBuildQuery()` and `dataFetchReal()`.
 * - Hook methods (e.g., `beforeSearch`, `afterSearch`) can be overridden if needed.
 *
 * Testability:
 * - Use trait methods to enable test mode, inject fake fetchers, and control time.
 * - Query capture allows assertions on normalized queries without hitting I/O.
 */
abstract class AbstractSearchService implements SearchServiceInterface
{
    use QueryCaptureTestSupportTrait;

    /**
     * Public entrypoint as required by the interface.
     *
     * @param mixed $input
     * @param array<string,mixed>|null $options
     * @return iterable<mixed>
     */
    final public function search(mixed $input, ?array $options = null): iterable
    {
        return $this->actSearch($input, $options);
    }

    /**
     * ========== A: ACTION LAYER ==========
     * Orchestrates the search lifecycle, no domain decisions here.
     *
     * @param mixed $input
     * @param array<string,mixed>|null $options
     * @return iterable<mixed>
     */
    protected function actSearch(mixed $input, ?array $options = null): iterable
    {
        $this->beforeSearch($input, $options);

        // C: Build a normalized query (pure calculation)
        $query = $this->calcBuildQuery($input, $options);

        // Capture for observability/tests
        $this->captureQuery($query);

        // D: Execute through data layer
        $results = $this->dataFetch($query, $options);

        $this->afterSearch($input, $options, $results);

        return $results;
    }

    /**
     * Hook: called before calculation begins.
     * Override to add metrics, logging, guards, etc.
     *
     * @param mixed $input
     * @param array<string,mixed>|null $options
     */
    protected function beforeSearch(mixed $input, ?array $options = null): void
    {
        // no-op by default
    }

    /**
     * ========== C: CALCULATION LAYER ==========
     * Pure transformation from raw input to a domain query structure.
     * Must be deterministic and side-effect free.
     *
     * @param mixed $input
     * @param array<string,mixed>|null $options
     * @return mixed A normalized "query" (array/DTO/spec) that the data layer understands.
     */
    abstract protected function calcBuildQuery(mixed $input, ?array $options = null): mixed;

    /**
     * ========== D: DATA LAYER ==========
     * Execute the query against storage/search engine/HTTP/etc.
     * Test-mode first tries an injected override, then falls back to real fetch.
     *
     * @param mixed $query
     * @param array<string,mixed>|null $options
     * @return iterable<mixed>
     */
    protected function dataFetch(mixed $query, ?array $options = null): iterable
    {
        $maybe = $this->tryTestFetcher($query, $options);
        if ($maybe !== null) {
            return $maybe;
        }
        return $this->dataFetchReal($query, $options);
    }

    /**
     * Perform the actual I/O. Subclasses implement this to call DB/HTTP/etc.
     *
     * @param mixed $query
     * @param array<string,mixed>|null $options
     * @return iterable<mixed>
     */
    abstract protected function dataFetchReal(mixed $query, ?array $options = null): iterable;

    /**
     * Hook: called after results are available.
     * Avoid mutating $results; treat it as read-only (or clone if needed).
     *
     * @param mixed $input
     * @param array<string,mixed>|null $options
     * @param iterable<mixed> $results
     */
    protected function afterSearch(mixed $input, ?array $options, iterable $results): void
    {
        // no-op by default
    }
}
