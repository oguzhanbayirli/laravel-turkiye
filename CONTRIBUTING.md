# Contributing

Bug reports and pull requests are welcome. Turkish or English, either is fine.

Before opening a pull request:

```bash
composer install
composer test
composer analyse
```

Both should pass. If you change behaviour, add a test that fails without your
change.

A few things to keep in mind:

- Do not put real identity numbers, tax numbers or IBANs in tests or issues.
  `Tckn::generate()`, `Vkn::generate()` and `Iban::generate()` produce valid
  test values.
- Tests must not call TCMB. If you need a new response, save it under
  `tests/Fixtures` and serve it with `Http::fake()`.
- If TCMB changes its XML format, please attach the new file to the issue.
