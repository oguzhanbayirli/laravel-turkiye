<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Words;

/**
 * How an amount is written out. Use one of the presets and adjust with the
 * with*() methods, or call the constructor with named arguments.
 */
final class WordsFormat
{
    public function __construct(
        public readonly Casing $casing = Casing::Title,
        public readonly string $wordSeparator = ' ',
        public readonly string $liraLabel = 'Türk Lirası',
        public readonly string $kurusLabel = 'Kuruş',
        public readonly string $labelSeparator = ' ',
        public readonly string $partSeparator = ' ',
        public readonly bool $showZeroKurus = false,
        public readonly string $prefix = '',
        public readonly string $suffix = '',
    ) {}

    /**
     * "Bin İki Yüz Otuz Dört Türk Lirası Elli Altı Kuruş"
     */
    public static function plain(): self
    {
        return new self;
    }

    /**
     * "Yalnız #BinİkiYüzOtuzDörtTL,ElliAltıKr#"
     *
     * The words are run together and fenced with "#" so nothing can be
     * squeezed in front of or after the amount on a printed invoice.
     */
    public static function invoice(): self
    {
        return new self(
            wordSeparator: '',
            liraLabel: 'TL',
            kurusLabel: 'Kr',
            labelSeparator: '',
            partSeparator: ',',
            prefix: 'Yalnız #',
            suffix: '#',
        );
    }

    /**
     * "YALNIZ: BİN İKİ YÜZ OTUZ DÖRT TÜRK LİRASI ELLİ ALTI KURUŞ"
     */
    public static function invoiceUpper(): self
    {
        return new self(casing: Casing::Upper, prefix: 'YALNIZ: ');
    }

    public function withCasing(Casing $casing): self
    {
        return $this->copy(['casing' => $casing]);
    }

    public function withWordSeparator(string $separator): self
    {
        return $this->copy(['wordSeparator' => $separator]);
    }

    public function withLabels(string $lira, string $kurus): self
    {
        return $this->copy(['liraLabel' => $lira, 'kurusLabel' => $kurus]);
    }

    public function withPrefix(string $prefix): self
    {
        return $this->copy(['prefix' => $prefix]);
    }

    public function withSuffix(string $suffix): self
    {
        return $this->copy(['suffix' => $suffix]);
    }

    public function withZeroKurus(bool $show = true): self
    {
        return $this->copy(['showZeroKurus' => $show]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function copy(array $changes): self
    {
        /** @var array{casing: Casing, wordSeparator: string, liraLabel: string, kurusLabel: string, labelSeparator: string, partSeparator: string, showZeroKurus: bool, prefix: string, suffix: string} $args */
        $args = array_merge(get_object_vars($this), $changes);

        return new self(...$args);
    }
}
