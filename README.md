# laravel-turkiye

Validation rules for Turkish identity numbers (TCKN), tax numbers (VKN) and
IBANs, amounts written out in Turkish words for invoices, and a client for the
daily exchange rates published by the Central Bank of the Republic of Türkiye
(TCMB). Works with Laravel 12 on PHP 8.2+ and Laravel 13 on PHP 8.3+. Laravel 11 is left out
because it no longer gets security fixes and Composer refuses to install it by
default.

[Türkçe README](README.tr.md)

## Why another package

For years `epigra/tckimlik` was the usual way to check a TCKN in Laravel. It is
archived now. According to the NVI notice quoted in its README, NVI's free
public verification service (KPSPublic) was scheduled to close on 30 September
2025; checking a number against the population register now requires KPS
membership. The offline check
is still what most forms need, so this package does that, along with the other
small Turkey-specific chores that keep coming up in invoicing code: VKN and
IBAN checks, "Yalnız ... TL" lines and TCMB rates. Every part also works as a
plain PHP class if you are not inside a Laravel request.

## Installation

```bash
composer require oguzhanbayirli/laravel-turkiye
```

The service provider is discovered automatically. To change the TCMB settings
or the validation messages, publish them:

```bash
php artisan vendor:publish --tag=turkiye-config
php artisan vendor:publish --tag=turkiye-lang
```

## Validation

Four rules are registered, each available as a string and as a rule object:

| String          | Object                     | Accepts                                     |
|-----------------|----------------------------|---------------------------------------------|
| `tckn`          | `Rules\Tckn`               | 11-digit T.C. kimlik no                     |
| `vkn`           | `Rules\Vkn`                | 10-digit vergi kimlik no                    |
| `tckn_or_vkn`   | `Rules\TcknOrVkn`          | either of the above (invoice buyer field)   |
| `tr_iban`       | `Rules\TurkishIban`        | TR IBAN, spaces, dashes and lower case okay |

```php
use OguzhanBayirli\Turkiye\Rules\TurkishIban;

$request->validate([
    'national_id' => ['required', 'tckn'],
    'tax_number'  => ['nullable', 'vkn'],
    'iban'        => ['required', new TurkishIban],
]);
```

Messages ship in Turkish and English and follow the app locale:

```
national id geçerli bir T.C. kimlik numarası olmalıdır.
The iban field must be a valid Turkish IBAN.
```

If your own `lang/{locale}/validation.php` has a `tckn`, `vkn`, `tckn_or_vkn`
or `tr_iban` line, that one wins.

Outside of the validator:

```php
use OguzhanBayirli\Turkiye\Identity\Iban;
use OguzhanBayirli\Turkiye\Identity\Tckn;
use OguzhanBayirli\Turkiye\Identity\Vkn;

Tckn::isValid('10000000146');               // true
Vkn::isValid('4540536920');                 // true
Iban::isValid('tr33 0006 1005 1978 6457 8413 26'); // true

Iban::normalize('tr33 0006 1005 ...');     // "TR330006100519..."
Iban::format('TR330006100519786457841326'); // "TR33 0006 1005 1978 6457 8413 26"
Iban::bankCode('TR330006100519786457841326'); // "00061"

// For factories and seeders:
Tckn::generate();
Vkn::generate();
Iban::generate('00010');
```

### What these checks do not tell you

All three checks are offline arithmetic. A TCKN that passes has the right
shape and check digits; it does not mean a person with that number exists, or
that the name typed next to it matches. The same goes for VKN and IBAN: the
account may be closed, or belong to someone else. When that matters, ask the
bank or GİB.

## Amounts in words

```php
use OguzhanBayirli\Turkiye\Facades\AmountInWords;
use OguzhanBayirli\Turkiye\Words\WordsFormat;

AmountInWords::convert('1234.56');
// Bin İki Yüz Otuz Dört Türk Lirası Elli Altı Kuruş

AmountInWords::invoice('1234.56');
// Yalnız #BinİkiYüzOtuzDörtTL,ElliAltıKr#

AmountInWords::convert('1234.56', WordsFormat::invoiceUpper());
// YALNIZ: BİN İKİ YÜZ OTUZ DÖRT TÜRK LİRASI ELLİ ALTI KURUŞ
```

Numbers follow TDK spelling: "bin" never becomes "bir bin" and "yüz" never
becomes "bir yüz". Plain text keeps the words apart; the invoice preset joins
them, as TDK prescribes for amounts on commercial documents. Capitalisation
follows common invoice practice, with the dotted capital İ.

Presets can be adjusted:

```php
use OguzhanBayirli\Turkiye\Words\Casing;

$format = WordsFormat::plain()
    ->withLabels('lira', 'kuruş')
    ->withCasing(Casing::Lower)
    ->withZeroKurus();

AmountInWords::convert('1000', $format); // bin lira sıfır kuruş
```

Without the facade: `(new \OguzhanBayirli\Turkiye\Words\AmountInWords)->convert(...)`.
`NumberToWords::spell(1234)` gives the bare number, "bin iki yüz otuz dört".

Some details:

- Pass amounts as strings when every kuruş matters. `"1234.56"` is also what
  Laravel's `decimal:2` cast returns. Floats work but are rounded through
  `number_format`, so the usual float surprises apply.
- Input uses a dot for decimals and no thousands separator. `"1.234,56"` is
  rejected with an `InvalidArgumentException` instead of being guessed at.
- Rounding is half away from zero at the second decimal: `10.005` is
  "On Türk Lirası Bir Kuruş", `999.995` is "Bin Türk Lirası".
- `0` is "Sıfır Türk Lirası", `0.50` is "Elli Kuruş", negatives start with "Eksi".
- Strings can go well past `PHP_INT_MAX`; the largest scale is desilyon (10^33).

## TCMB exchange rates

```php
use OguzhanBayirli\Turkiye\Facades\Tcmb;
use OguzhanBayirli\Turkiye\Tcmb\RateType;

$sheet = Tcmb::today();
$sheet->date;                       // CarbonImmutable, the bulletin's own date
$sheet->get('USD')->forexSelling;   // 49.2152

Tcmb::on('2026-10-04')->date;       // Sunday, so 2026-10-02 (Friday)

$jpy = Tcmb::rate('JPY');
$jpy->unit;                         // 100, TCMB quotes yen per hundred
$jpy->perUnit();                    // price of one yen
$jpy->toLira(25000, RateType::ForexBuying);
```

Each `Rate` has `forexBuying`, `forexSelling`, `banknoteBuying`,
`banknoteSelling` and `crossRateUsd`, all `?float`. A price is null when the
bulletin leaves it empty, which happens for SDR (XDR) banknote rates.

How it behaves:

- `today()` reads `today.xml`. TCMB publishes around 15:30 Istanbul time, so
  before that you get the previous working day's bulletin. Check
  `$sheet->date` rather than assuming.
- `on($date)` reads `YYYYMM/DDMMYYYY.xml`. Weekends and holidays have no file
  (TCMB answers 404), so it steps back one day at a time, up to
  `max_lookback_days` (10 by default, enough for a long bayram).
- Responses are cached through Laravel's cache. `today.xml` for 10 minutes,
  archived bulletins for 30 days. Past days without a bulletin are remembered
  too, so a weekend lookup does not repeat its 404s.
- Network problems, timeouts, non-404 HTTP errors and unreadable XML throw
  `OguzhanBayirli\Turkiye\Tcmb\TcmbException`. A 500 on Monday is not quietly
  replaced by Friday's rates.
- Future dates throw `InvalidArgumentException` without making a request.

Configuration, after publishing `config/turkiye.php`:

```php
'tcmb' => [
    'base_url' => env('TCMB_BASE_URL', 'https://www.tcmb.gov.tr/kurlar'),
    'timeout' => (int) env('TCMB_TIMEOUT', 10),
    'connect_timeout' => (int) env('TCMB_CONNECT_TIMEOUT', 5),
    'retries' => (int) env('TCMB_RETRIES', 1), // only for connection errors
    'max_lookback_days' => 10,
    'cache' => [
        'store' => env('TCMB_CACHE_STORE'), // null = default store
        'prefix' => 'turkiye.tcmb',
        'today_ttl' => 600,
        'archive_ttl' => 2592000,  // null = forever
    ],
],
```

In your own tests, fake the HTTP client as usual:

```php
Http::fake(['www.tcmb.gov.tr/*' => Http::response($xml)]);
```

These are TCMB's indicative rates. Which date and which of the four prices
apply to a given invoice is a question for your accountant; the package only
fetches them.

## Testing

```bash
composer test
composer analyse
```

The test suite never touches the network. The TCMB tests replay real
bulletins saved under `tests/Fixtures`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT. See [LICENSE](LICENSE).
