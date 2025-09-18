<?php

declare(strict_types=1);

use Tests\Doubles\DummySearchService;

beforeEach(function () {
    $this->index = [
        'books' => [
            ['id' => 1, 'title' => 'Laravel Tips', 'body' => 'Search patterns and testing'],
            ['id' => 2, 'title' => 'Domain-Driven PHP', 'body' => 'Aggregates and Repositories'],
            ['id' => 3, 'title' => 'Functional PHP', 'body' => 'Pure functions and composition'],
        ],
        'notes' => [
            ['id' => 10, 'title' => 'pest intro', 'body' => 'expectations and matchers'],
            ['id' => 11, 'title' => 'Meilisearch cheatsheet', 'body' => 'index keys & ranking'],
        ],
    ];
    $this->svc = new DummySearchService($this->index);
});

it('builds a deterministic normalized query (C layer) and captures it', function () {
    $results = $this->svc->search(['q' => 'Php', 'fields' => ['title']]);
    $captured = $this->svc->getCapturedQueries();
    expect($captured)->toHaveCount(1);

    /** @var array $q */
    $q = $captured[0];
    expect($q)->toMatchArray([
        'q'      => 'php',
        'fields' => ['title'],
        'limit'  => null,
    ]);

    $out = is_array($results) ? $results : iterator_to_array($results);
    $ids = array_map(fn($r) => $r['id'], $out);
    expect($ids)->toContain(2);
});

it('trims whitespace and respects options override for fields', function () {
    $this->svc->search(['q' => '   test   ', 'fields' => ['body']], ['fields' => ['title']]);
    $q = $this->svc->getCapturedQueries()[0];

    expect($q['q'])->toBe('test');
    expect($q['fields'])->toBe(['title']);
});

it('honors the limit option in data layer', function () {
    $results = $this->svc->search('a', ['limit' => 1]);
    $out = is_array($results) ? $results : iterator_to_array($results);
    expect($out)->toHaveCount(1);
});

it('invokes before/after hooks (A layer) and uses testable clock', function () {
    $fixed = new DateTimeImmutable('2025-01-02T03:04:05+00:00');
    $this->svc->setNowFn(fn() => $fixed);

    $this->svc->search('php');

    expect($this->svc->wasBeforeCalled())->toBeTrue();
    expect($this->svc->wasAfterCalled())->toBeTrue();

    expect($this->svc->getBeforeAt())->not->toBeNull();
    expect($this->svc->getAfterAt())->not->toBeNull();

    expect($this->svc->getBeforeAt()->format(DATE_ATOM))->toBe($fixed->format(DATE_ATOM));
    expect($this->svc->getAfterAt()->format(DATE_ATOM))->toBe($fixed->format(DATE_ATOM));
});

it('uses the test fetcher override when test mode is enabled (D layer)', function () {
    $this->svc->enableTestMode();
    $this->svc->setDataFetcherOverride(function (mixed $query, ?array $options): iterable {
        expect($query)->toMatchArray(['q' => 'dummy']);
        return [
            ['id' => 999, 'title' => 'Fake Result', '_collection' => 'fake'],
        ];
    });

    $results = $this->svc->search('dummy');
    $out = is_array($results) ? $results : iterator_to_array($results);

    expect($out)->toHaveCount(1)
        ->and($out[0]['id'])->toBe(999)
        ->and($this->svc->wasDataFetchRealCalled())->toBeFalse();
});

it('falls back to real data path when test mode is disabled', function () {
    $this->svc->disableTestMode();
    $this->svc->setDataFetcherOverride(function () {
        throw new RuntimeException('Should not be called in non-test mode');
    });

    $results = $this->svc->search('php');
    $out = is_array($results) ? $results : iterator_to_array($results);

    // collect() 대신 순수 PHP: array_map/array_column
    $ids = array_map(fn($r) => $r['id'], $out);
    expect($this->svc->wasDataFetchRealCalled())->toBeTrue()
        ->and($ids)->toContain(2);
});

it('captures multiple queries FIFO and can be cleared between runs', function () {
    $this->svc->search('a');
    $this->svc->search('b');
    $captured = $this->svc->getCapturedQueries();

    expect($captured)->toHaveCount(2)
        ->and($captured[0]['q'])->toBe('a')
        ->and($captured[1]['q'])->toBe('b');

    $this->svc->clearCapturedQueries();
    expect($this->svc->getCapturedQueries())->toHaveCount(0);
});

it('is pure in calcBuildQuery sense (same input -> same normalized query)', function () {
    $this->svc->search(['q' => '   Laravel  ', 'fields' => ['title', 'body']]);
    $this->svc->search(['q' => 'Laravel', 'fields' => ['title', 'body']]);
    [$q1, $q2] = $this->svc->getCapturedQueries();
    expect($q1)->toMatchArray($q2);
});
