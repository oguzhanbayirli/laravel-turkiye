# laravel-turkiye

Laravel için T.C. kimlik no (TCKN), vergi kimlik no (VKN) ve IBAN doğrulama
kuralları, faturalar için tutarı yazıya çevirme ve TCMB'nin günlük döviz
kurları. Laravel 11, 12 ve 13 ile, PHP 8.2 ve üstünde çalışır.

[English README](README.md)

## Neden

Laravel'de TCKN kontrolü için uzun süre `epigra/tckimlik` kullanıldı. Paket
artık arşivde; numarayı nüfus kaydıyla karşılaştıran NVI web servisi de
30 Eylül 2025'te kapandı. Formların çoğunun ihtiyacı zaten çevrim dışı
algoritma kontrolü. Bu paket onu yapıyor, yanında fatura kodunda sürekli
yeniden yazılan birkaç işi de topluyor: VKN ve IBAN kontrolü, "Yalnız ... TL"
satırı, TCMB kurları. Hepsi Laravel dışında düz PHP sınıfı olarak da
kullanılabilir.

## Kurulum

```bash
composer require oguzhanbayirli/laravel-turkiye
```

Servis sağlayıcı otomatik bulunur. TCMB ayarlarını ya da hata mesajlarını
değiştirmek isterseniz yayımlayın:

```bash
php artisan vendor:publish --tag=turkiye-config
php artisan vendor:publish --tag=turkiye-lang
```

## Doğrulama

Dört kural var, her biri hem metin hem nesne olarak kullanılabilir:

| Metin           | Nesne                  | Kabul ettiği                                     |
|-----------------|------------------------|--------------------------------------------------|
| `tckn`          | `Rules\Tckn`           | 11 haneli T.C. kimlik no                         |
| `vkn`           | `Rules\Vkn`            | 10 haneli vergi kimlik no                        |
| `tckn_or_vkn`   | `Rules\TcknOrVkn`      | ikisinden biri (faturada alıcı alanı için)       |
| `tr_iban`       | `Rules\TurkishIban`    | TR IBAN; boşluk, tire ve küçük harf sorun değil  |

```php
use OguzhanBayirli\Turkiye\Rules\TurkishIban;

$request->validate([
    'tc_kimlik_no' => ['required', 'tckn'],
    'vergi_no'     => ['nullable', 'vkn'],
    'iban'         => ['required', new TurkishIban],
]);
```

Mesajlar Türkçe ve İngilizce gelir, uygulamanın diline göre seçilir.
Kendi `lang/tr/validation.php` dosyanızda `tckn`, `vkn`, `tckn_or_vkn` ya da
`tr_iban` satırı varsa o kullanılır.

Doğrulayıcı dışında:

```php
use OguzhanBayirli\Turkiye\Identity\Iban;
use OguzhanBayirli\Turkiye\Identity\Tckn;
use OguzhanBayirli\Turkiye\Identity\Vkn;

Tckn::isValid('10000000146');      // true
Vkn::isValid('4540536920');        // true
Iban::isValid('tr33 0006 1005 1978 6457 8413 26'); // true

Iban::format('TR330006100519786457841326');   // "TR33 0006 1005 1978 6457 8413 26"
Iban::bankCode('TR330006100519786457841326'); // "00061"

// Factory ve seeder'lar için:
Tckn::generate();
Vkn::generate();
Iban::generate('00010');
```

### Bu kontroller neyi söylemez

Üçü de çevrim dışı hesaplama. Kontrolden geçen bir TCKN'nin biçimi ve kontrol
haneleri doğrudur; o numaraya sahip birinin var olduğunu ya da yanına yazılan
adın tuttuğunu göstermez. VKN ve IBAN için de aynısı geçerli: hesap kapanmış
ya da başkasına ait olabilir. Bunun önemli olduğu yerde banka ya da GİB
üzerinden doğrulama gerekir.

## Tutarı yazıya çevirme

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

Yazım TDK'ya uyar: sayılar ayrı yazılır, "bir bin" ve "bir yüz" denmez, büyük
harfte noktalı İ kullanılır.

Biçimler değiştirilebilir:

```php
use OguzhanBayirli\Turkiye\Words\Casing;

$format = WordsFormat::plain()
    ->withLabels('lira', 'kuruş')
    ->withCasing(Casing::Lower)
    ->withZeroKurus();

AmountInWords::convert('1000', $format); // bin lira sıfır kuruş
```

Bilmeniz gerekenler:

- Kuruşun kesin olması gerekiyorsa tutarı metin olarak verin. Laravel'in
  `decimal:2` cast'i de zaten `"1234.56"` döndürür. Float da kabul edilir ama
  `number_format` ile yuvarlanır, float'un bilinen sapmaları geçerlidir.
- Ondalık ayırıcı nokta, binlik ayırıcı yok. `"1.234,56"` tahmin edilmez,
  `InvalidArgumentException` fırlatılır.
- Yuvarlama ikinci ondalıkta, yarım sıfırdan uzağa: `10.005` "On Türk Lirası
  Bir Kuruş", `999.995` "Bin Türk Lirası" olur.
- `0` "Sıfır Türk Lirası", `0.50` "Elli Kuruş"; eksi tutarlar "Eksi" ile başlar.
- Metin olarak verilen tutar `PHP_INT_MAX`'ı aşabilir; en büyük basamak
  desilyon (10^33).

## TCMB döviz kurları

```php
use OguzhanBayirli\Turkiye\Facades\Tcmb;
use OguzhanBayirli\Turkiye\Tcmb\RateType;

$bulten = Tcmb::today();
$bulten->date;                      // bültenin kendi tarihi
$bulten->get('USD')->forexSelling;  // 49.2152

Tcmb::on('2026-10-04')->date;       // pazar, bu yüzden 2026-10-02 (cuma)

$yen = Tcmb::rate('JPY');
$yen->unit;                         // 100, TCMB yeni 100'lük birimle verir
$yen->perUnit();                    // 1 yen
$yen->toLira(25000, RateType::ForexBuying);
```

Her `Rate` nesnesinde `forexBuying` (döviz alış), `forexSelling` (döviz
satış), `banknoteBuying` (efektif alış), `banknoteSelling` (efektif satış) ve
`crossRateUsd` var, hepsi `?float`. Bültende boş bırakılan fiyat null gelir;
SDR'nin (XDR) efektif kurları böyle.

Davranış:

- `today()` `today.xml`'i okur. TCMB 15:30 civarında yayımlar; ondan önce bir
  önceki iş gününün bülteni gelir. Varsayım yapmak yerine `$bulten->date`'e
  bakın.
- `on($tarih)` `YYYYMM/GGAAYYYY.xml`'i okur. Hafta sonu ve tatilde dosya
  yoktur (TCMB 404 döner), bu yüzden günü birer birer geri alır;
  `max_lookback_days` kadar (varsayılan 10, uzun bayram için yeterli).
- Yanıtlar Laravel cache'inde tutulur: `today.xml` 10 dakika, geçmiş
  bültenler 30 gün. Bülten olmayan geçmiş günler de hatırlanır, hafta sonu
  sorgusu 404'leri tekrarlamaz.
- Bağlantı hatası, zaman aşımı, 404 dışındaki HTTP hataları ve okunamayan XML
  `OguzhanBayirli\Turkiye\Tcmb\TcmbException` fırlatır. Pazartesi gelen bir
  500 hatası sessizce cuma kuruyla değiştirilmez.
- Gelecek tarih istek atılmadan `InvalidArgumentException` verir.

Ayarlar için `config/turkiye.php` dosyasını yayımlayın; seçenekler İngilizce
README'de ve dosyanın içindeki açıklamalarda.

Kendi testlerinizde HTTP istemcisini her zamanki gibi taklit edin:

```php
Http::fake(['www.tcmb.gov.tr/*' => Http::response($xml)]);
```

Bunlar TCMB'nin gösterge niteliğindeki kurları. Bir faturada hangi tarihin ve
dört fiyattan hangisinin kullanılacağı muhasebecinizin kararı; paket yalnızca
kurları getirir.

## Test

```bash
composer test
composer analyse
```

Testler ağa çıkmaz. TCMB testleri `tests/Fixtures` altındaki gerçek bültenleri
kullanır.

## Lisans

MIT. Ayrıntı [LICENSE](LICENSE) dosyasında.
