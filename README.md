# tetthys/search

> Extensible, testable **search abstraction** for PHP.
> Interface + Abstract Class + Trait with clean **A/C/D (Action / Calculation / Data)** separation.

---

## ✨ Features

* **Interface** – minimal contract:
  `search(mixed $input, ?array $options = null): iterable`
* **Abstract Class** – orchestrates A/C/D split:

  * **Action**: lifecycle
  * **Calculation**: input → normalized query
  * **Data**: real I/O
* **Trait** – test helpers:

  * Query capture
  * Test mode & fake fetchers
  * Deterministic `nowFn`

---

## Installation

```bash
composer require tetthys/search
```

---

## Quick Example

```php
final class ProductSearchService extends AbstractSearchService
{
    use QueryCaptureTestSupportTrait;

    protected function calculate(mixed $input): array
    {
        $query = ['name' => trim((string)($input['name'] ?? ''))];
        $this->captureQuery($query);
        return $query;
    }

    protected function fetch(mixed $query, ?array $options = null): iterable
    {
        // Replace with DB/HTTP/etc
        return [['id' => 1, 'name' => 'Demo']];
    }
}

$svc = new ProductSearchService();
$results = $svc->search(['name' => 'demo']);
```

---

## Testing

```php
$svc->enableTestMode(true);
$svc->setFakeFetcher(fn() => [['id' => 99]]);
$results = $svc->search(['name' => 'x']);
expect($svc->capturedQueries())->toHaveCount(1);
```

Run with:

```bash
./vendor/bin/pest
```

---

## License

MIT © Tetthys