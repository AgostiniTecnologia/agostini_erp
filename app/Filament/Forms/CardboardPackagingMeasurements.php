<?php

namespace App\Filament\Forms;

use App\Support\CardboardMeasurements;
use Closure;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;

class CardboardPackagingMeasurements
{
    public static function schema(): array
    {
        return [
            Section::make('Medidas da embalagem')
                ->schema([
                    Section::make('Medidas internas')
                        ->schema([
                            self::measurement('internal_length', 'Comprimento interno'),
                            self::measurement('internal_width', 'Largura interna'),
                            self::measurement('internal_height', 'Altura interna'),
                        ])
                        ->columns(['default' => 1, 'md' => 3]),
                    Section::make('Composição do comprimento da chapa')
                        ->schema([
                            self::measurement('left_flap', 'Aba esquerda'),
                            self::measurement('left_height', 'Altura esquerda'),
                            self::measurement('sheet_length', 'Comprimento'),
                            self::measurement('right_height', 'Altura direita'),
                            self::measurement('right_flap', 'Aba direita'),
                            self::total('Comprimento total', CardboardMeasurements::LENGTH_FIELDS),
                        ])
                        ->columns(['default' => 1, 'md' => 3, 'xl' => 6]),
                    Section::make('Composição da largura da chapa')
                        ->schema([
                            self::measurement('top_flap', 'Aba superior'),
                            self::measurement('top_height', 'Altura superior'),
                            self::measurement('sheet_width', 'Largura'),
                            self::measurement('bottom_height', 'Altura inferior'),
                            self::measurement('bottom_flap', 'Aba inferior'),
                            self::total('Largura total', CardboardMeasurements::WIDTH_FIELDS),
                        ])
                        ->columns(['default' => 1, 'md' => 3, 'xl' => 6]),
                    Placeholder::make('sheet_size')
                        ->label('Tamanho da chapa')
                        ->content(function (Get $get): HtmlString {
                            $measurements = self::measurements($get);
                            $length = CardboardMeasurements::format(CardboardMeasurements::lengthTotal($measurements));
                            $width = CardboardMeasurements::format(CardboardMeasurements::widthTotal($measurements));

                            return new HtmlString("<strong>{$length} × {$width} mm</strong>");
                        })
                        ->columnSpanFull(),
                ]),
        ];
    }

    private static function measurement(string $name, string $label): TextInput
    {
        return TextInput::make("cardboard_measurements.{$name}")
            ->label($label)
            ->suffix('mm')
            ->inputMode('decimal')
            ->live(debounce: 300)
            ->rule(static function (): Closure {
                return static function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        CardboardMeasurements::normalize($value);
                    } catch (\InvalidArgumentException) {
                        $fail('A medida deve ser um número maior ou igual a zero.');
                    }
                };
            })
            ->dehydrateStateUsing(fn (mixed $state): ?string => CardboardMeasurements::normalize($state))
            ->extraInputAttributes([
                'x-on:keydown.enter.prevent' => <<<'JS'
                    (() => {
                        const scope = $el.closest('[role="tabpanel"]') || $el.closest('form');
                        const inputs = [...scope.querySelectorAll('input:not([disabled]):not([readonly])')];
                        const next = inputs[inputs.indexOf($el) + 1];
                        if (next) next.focus();
                    })()
                    JS,
            ]);
    }

    private static function total(string $label, array $fields): Placeholder
    {
        return Placeholder::make(str($label)->snake()->toString())
            ->label($label)
            ->content(function (Get $get) use ($fields): string {
                return CardboardMeasurements::format(
                    CardboardMeasurements::total(self::measurements($get), $fields),
                ).' mm';
            });
    }

    private static function measurements(Get $get): array
    {
        return (array) ($get('cardboard_measurements') ?? []);
    }
}
