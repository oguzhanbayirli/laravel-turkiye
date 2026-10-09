# Changelog

All notable changes to this package are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and versions follow
[Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-10-09

### Added

- `tckn`, `vkn`, `tckn_or_vkn` and `tr_iban` validation rules, as strings and
  as rule objects, with Turkish and English messages.
- `Identity\Tckn`, `Identity\Vkn` and `Identity\Iban` for use without the
  validator, including `generate()` helpers for test data.
- `Words\NumberToWords` and `Words\AmountInWords` with plain, invoice
  (`Yalnız #...TL,...Kr#`) and upper case invoice formats.
- `Tcmb\ExchangeRates` for today's bulletin and dated bulletins, with
  weekend and holiday fallback, caching and `TcmbException` on failures.
- `AmountInWords` and `Tcmb` facades.
