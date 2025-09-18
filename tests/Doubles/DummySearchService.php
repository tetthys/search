<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Tetthys\Search\AbstractSearchService;

/**
 * DummySearchService
 *
 * Minimal concrete implementation for testing AbstractSearchService.
 * A (Action): before/after hooks flip flags & take timestamps (via now()).
 * C (Calculation): build a normalized query array (pure + deterministic).
 * D (Data): search in an in-memory index; supports a "limit" option.
 *
 * Testability hooks:
 * - Uses QueryCaptureTestSupportTrait (captured queries, test-mode fetcher, nowFn).
 * - Exposes flags/timestamps for assertions.
 */
final class DummySearchService extends AbstractSearchService
{
    /** @var array<string, list<array<string,mixed>>> */
    private array $index;

    /** @var bool */
    private bool $beforeCalled = false;

    /** @var bool */
    private bool $afterCalled = false;

    /** @var \DateTimeImmutable|null */
    private ?\DateTimeImmutable $beforeAt = null;

    /** @var \DateTimeImmutable|null */
    private ?\DateTimeImmutable $afterAt = null;

    /** @var bool */
    private bool $dataFetchRealCalled = false;

    /**
     * @param array<string, list<array<string,mixed>>> $index
     */
    public function __construct(array $index)
    {
        $this->index = $index;
    }

    // --------- Probe getters for tests ----------
    public function wasBeforeCalled(): bool
    {
        return $this->beforeCalled;
    }
    public function wasAfterCalled(): bool
    {
        return $this->afterCalled;
    }
    public function getBeforeAt(): ?\DateTimeImmutable
    {
        return $this->beforeAt;
    }
    public function getAfterAt(): ?\DateTimeImmutable
    {
        return $this->afterAt;
    }
    public function wasDataFetchRealCalled(): bool
    {
        return $this->dataFetchRealCalled;
    }

    // ---------------- A: Action ------------------
    protected function beforeSearch(mixed $input, ?array $options = null): void
    {
        $this->beforeCalled = true;
        $this->beforeAt = $this->now(); // testable clock
    }

    protected function afterSearch(mixed $input, ?array $options, iterable $results): void
    {
        $this->afterCalled = true;
        $this->afterAt = $this->now(); // testable clock
    }

    // ------------- C: Calculation ----------------
    /**
     * Pure normalized query builder.
     *
     * Input shapes supported:
     * - string: treated as the needle
     * - array:  ['q'=>string, 'fields'=>string[]]
     *
     * Options (read-only; may be null):
     * - limit?: int
     * - fields?: string[] (overrides input['fields'] when provided)
     */
    protected function calcBuildQuery(mixed $input, ?array $options = null): array
    {
        $needle = is_array($input) ? (string)($input['q'] ?? '') : (string)$input;
        $needle = mb_strtolower(trim($needle));

        /** @var list<string> $defaultFields */
        $defaultFields = ['title', 'body'];

        $fields = $defaultFields;
        if (is_array($input) && isset($input['fields']) && is_array($input['fields'])) {
            $fields = array_values(array_map('strval', $input['fields']));
        }
        if (isset($options['fields']) && is_array($options['fields'])) {
            $fields = array_values(array_map('strval', $options['fields']));
        }

        $limit = null;
        if (isset($options['limit'])) {
            $limit = (int)$options['limit'];
            if ($limit < 1) {
                $limit = 1; // minimal sane guard
            }
        }

        return [
            'q'      => $needle,              // normalized needle
            'fields' => $fields,              // fields to search on
            'limit'  => $limit,               // null or positive int
        ];
    }

    // --------------- D: Data ---------------------
    /**
     * In-memory "search": any field contains the needle (case-insensitive).
     * Honours the 'limit' part of the normalized query.
     *
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $options
     * @return iterable<array<string,mixed>>
     */
    protected function dataFetchReal(mixed $query, ?array $options = null): iterable
    {
        $this->dataFetchRealCalled = true;

        /** @var string $needle */
        $needle = (string)($query['q'] ?? '');
        /** @var list<string> $fields */
        $fields = (array)($query['fields'] ?? ['title', 'body']);
        /** @var int|null $limit */
        $limit  = $query['limit'] ?? null;

        $out = [];
        foreach ($this->index as $collection => $rows) {
            foreach ($rows as $row) {
                foreach ($fields as $f) {
                    if (!array_key_exists($f, $row)) {
                        continue;
                    }
                    $val = mb_strtolower((string)$row[$f]);
                    if ($needle === '' || mb_strpos($val, $needle) !== false) {
                        $out[] = $row + ['_collection' => $collection];
                        break; // match once per row
                    }
                }
                if ($limit !== null && count($out) >= $limit) {
                    break 2; // stop early when hitting limit
                }
            }
        }
        return $out;
    }
}
