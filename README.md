# tetthys/search

> A lightweight PHP library for building **search services** with a clean A/C/D split:  
> - **Action** → lifecycle (`search()` is the entrypoint)  
> - **Calculation** → normalize input (`calcBuildQuery`)  
> - **Data** → perform real I/O (`dataFetchReal`)  

This separation makes your search logic **testable**, **reusable**, and **easy to extend**.

---

## Installation

```bash
composer require tetthys/search
````

---

## Core Concepts

* **Action (A)** – `search()` orchestrates the lifecycle.
* **Calculation (C)** – `calcBuildQuery(mixed $input): array`
  → pure, normalize raw input into a clean query array.
* **Data (D)** – `dataFetchReal(mixed $query, ?array $options = null): iterable`
  → impure, execute the real I/O (database, API, filesystem).

> The abstract class already includes `QueryCaptureTestSupportTrait`, which provides helpers for capturing queries, faking fetchers, and overriding time.

---

## Minimal Example

```php
<?php

use Tetthys\Search\AbstractSearchService;

final class BookSearchService extends AbstractSearchService
{
    /** Normalize input → query */
    protected function calcBuildQuery(mixed $input): array
    {
        $q = ['title' => strtolower(trim((string)($input['title'] ?? '')))];
        $this->captureQuery($q); // expose query to tests
        return $q;
    }

    /** Execute real fetch */
    protected function dataFetchReal(mixed $q, ?array $options = null): iterable
    {
        $books = [
            ['id' => 1, 'title' => 'The Hobbit'],
            ['id' => 2, 'title' => 'The Lord of the Rings'],
            ['id' => 3, 'title' => 'Clean Code'],
        ];

        if ($q['title'] === '') return $books;

        return array_values(array_filter($books, fn($row) =>
            str_contains(strtolower($row['title']), $q['title'])
        ));
    }
}

// Usage
$svc = new BookSearchService();
$results = $svc->search(['title' => 'lord']);
print_r($results);
```

Output:

```txt
Array
(
    [0] => Array
        (
            [id] => 2
            [title] => The Lord of the Rings
        )
)
```

---

## Testing Support

Since the abstract class includes the trait, you can:

```php
$svc = new BookSearchService();

// Enable test mode (bypass real fetch)
$svc->enableTestMode(true);

// Fake fetcher replaces dataFetchReal
$svc->setFakeFetcher(fn() => [['id' => 99, 'title' => 'Fake Result']]);

// Override clock
$svc->setNowFn(fn() => new DateTimeImmutable('2025-01-01T00:00:00Z'));

$out = $svc->search(['title' => 'x']);

// Assertions
assert($out[0]['id'] === 99);
assert($svc->capturedQueries()[0]['title'] === 'x');
```

---

## API Summary

* `search(mixed $input, ?array $options = null): iterable`
* `calcBuildQuery(mixed $input): array` *(implement in subclass)*
* `dataFetchReal(mixed $query, ?array $options = null): iterable` *(implement in subclass)*

**Test utilities (already included in AbstractSearchService):**

* `captureQuery(array $q): void` – record normalized queries
* `capturedQueries(): array` – get recorded queries
* `enableTestMode(bool $on): void` – switch to fake fetcher mode
* `setFakeFetcher(callable $cb): void` – inject fake fetcher
* `setNowFn(callable $clock): void` – deterministic clock

---

## Why use this?

* **Clarity** – separate input parsing from data access.
* **Testability** – verify query normalization without touching the database.
* **Flexibility** – plug in SQL, HTTP, files, or anything iterable.
* **Confidence** – deterministic, observable, easy to fake in tests.

---

## License

MIT © Tetthys