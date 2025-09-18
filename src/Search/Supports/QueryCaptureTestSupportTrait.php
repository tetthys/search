<?php

declare(strict_types=1);

namespace Tetthys\Search\Supports;

/**
 * Trait: QueryCaptureTestSupportTrait
 *
 * Purpose:
 * - Capture built queries for observability and debugging.
 * - Provide test-only knobs to inject fakes/spies for data fetching and time.
 *
 * Use cases:
 * - In unit/feature tests, call `enableTestMode()` and inject a fake fetcher.
 * - In production, leave test mode off; fetcher override is ignored.
 */
trait QueryCaptureTestSupportTrait
{
    /** @var list<mixed> */
    private array $capturedQueries = [];

    /** @var bool */
    private bool $testMode = false;

    /**
     * Optional test-only data fetcher override.
     * Signature: function(mixed $query, ?array $options): iterable
     * @var null|callable
     */
    private $dataFetcherOverride = null;

    /**
     * Optional test-only clock function.
     * Signature: function(): \DateTimeImmutable
     * @var null|callable
     */
    private $nowFn = null;

    /**
     * Capture the normalized/built query for later inspection.
     * @param mixed $query
     */
    protected function captureQuery(mixed $query): void
    {
        $this->capturedQueries[] = $query;
    }

    /**
     * Get all captured queries (FIFO).
     * @return list<mixed>
     */
    public function getCapturedQueries(): array
    {
        return $this->capturedQueries;
    }

    /**
     * Clear captured queries (helpful between tests).
     */
    public function clearCapturedQueries(): void
    {
        $this->capturedQueries = [];
    }

    /**
     * Enable test mode; allows overrides to be honored.
     */
    public function enableTestMode(): void
    {
        $this->testMode = true;
    }

    /**
     * Disable test mode; ignores overrides and uses real data paths.
     */
    public function disableTestMode(): void
    {
        $this->testMode = false;
    }

    /**
     * For tests: inject a data fetcher (fake/spy).
     * @param callable|null $fetcher function(mixed $query, ?array $options): iterable
     */
    public function setDataFetcherOverride(?callable $fetcher): void
    {
        $this->dataFetcherOverride = $fetcher;
    }

    /**
     * For tests: inject a deterministic clock.
     * @param callable|null $nowFn function(): \DateTimeImmutable
     */
    public function setNowFn(?callable $nowFn): void
    {
        $this->nowFn = $nowFn;
    }

    /**
     * Get the current time using the injected clock (if any).
     */
    protected function now(): \DateTimeImmutable
    {
        if ($this->nowFn !== null) {
            /** @var callable $fn */
            $fn = $this->nowFn;
            return $fn();
        }
        return new \DateTimeImmutable('now');
    }

    /**
     * Execute via override if in test mode and override is set; otherwise null.
     * @param mixed $query
     * @param array<string,mixed>|null $options
     * @return iterable<mixed>|null
     */
    protected function tryTestFetcher(mixed $query, ?array $options = null): ?iterable
    {
        if ($this->testMode && $this->dataFetcherOverride !== null) {
            /** @var callable $fetcher */
            $fetcher = $this->dataFetcherOverride;
            /** @var iterable<mixed> $out */
            $out = $fetcher($query, $options);
            return $out;
        }
        return null;
    }
}
