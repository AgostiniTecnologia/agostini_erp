<?php

namespace App\Filament\Forms;

use App\Support\CardboardMeasurements;
use App\Support\CompanyMeasurementSettings;
use Closure;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
                            self::measurement('internal_length', 'Comprimento interno', true),
                            self::measurement('internal_width', 'Largura interna', true),
                            self::measurement('internal_height', 'Altura interna', true),
                            Actions::make([
                                Action::make('clear_measurements')
                                    ->label('Limpar medidas')
                                    ->icon('heroicon-o-trash')
                                    ->color('danger')
                                    ->requiresConfirmation()
                                    ->modalHeading('Limpar todas as medidas?')
                                    ->modalDescription('As medidas internas e todos os cálculos automáticos serão removidos.')
                                    ->action(fn (Set $set) => $set(
                                        'cardboard_measurements',
                                        CardboardMeasurements::emptyState(),
                                    )),
                            ])->columnSpanFull(),
                        ])
                        ->columns(['default' => 1, 'md' => 3]),
                    Section::make('Composição do comprimento da chapa')
                        ->schema([
                            self::measurement('left_flap', 'Aba esquerda')->readOnly(),
                            self::measurement('left_height', 'Altura esquerda')->readOnly(),
                            self::measurement('sheet_length', 'Comprimento')->readOnly(),
                            self::measurement('right_height', 'Altura direita')->readOnly(),
                            self::measurement('right_flap', 'Aba direita')->readOnly(),
                            self::total('Comprimento total', CardboardMeasurements::LENGTH_FIELDS),
                        ])
                        ->columns(['default' => 1, 'md' => 3, 'xl' => 6]),
                    Section::make('Composição da largura da chapa')
                        ->schema([
                            self::measurement('top_flap', 'Aba superior')->readOnly(),
                            self::measurement('top_height', 'Altura superior')->readOnly(),
                            self::measurement('sheet_width', 'Largura')->readOnly(),
                            self::measurement('bottom_height', 'Altura inferior')->readOnly(),
                            self::measurement('bottom_flap', 'Aba inferior')->readOnly(),
                            self::total('Largura total', CardboardMeasurements::WIDTH_FIELDS),
                        ])
                        ->columns(['default' => 1, 'md' => 3, 'xl' => 6]),
                    Placeholder::make('sheet_size')
                        ->label('Tamanho da chapa')
                        ->content(function (Get $get): HtmlString {
                            $measurements = self::measurements($get);
                            $length = CardboardMeasurements::format(CardboardMeasurements::lengthTotal($measurements));
                            $width = CardboardMeasurements::format(CardboardMeasurements::widthTotal($measurements));
                            $unit = CompanyMeasurementSettings::lengthUnit();

                            return new HtmlString("<strong>{$length} × {$width} {$unit}</strong>");
                        })
                        ->columnSpanFull(),
                ]),
        ];
    }

    private static function measurement(string $name, string $label, bool $recalculate = false): TextInput
    {
        $input = TextInput::make("cardboard_measurements.{$name}")
            ->label($label)
            ->suffix(fn (): string => CompanyMeasurementSettings::lengthUnit())
            ->inputMode('decimal')
            ->live(onBlur: true)
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

        if ($recalculate) {
            $input->afterStateUpdated(fn (Get $get, Set $set) => self::recalculate($get, $set));
            $input->afterStateHydrated(fn (Get $get, Set $set) => self::recalculate($get, $set));
        }

        return $input;
    }

    private static function total(string $label, array $fields): Placeholder
    {
        return Placeholder::make(str($label)->snake()->toString())
            ->label($label)
            ->content(function (Get $get) use ($fields): string {
                return CardboardMeasurements::format(
                    CardboardMeasurements::total(self::measurements($get), $fields),
                ).' '.CompanyMeasurementSettings::lengthUnit();
            });
    }

    private static function measurements(Get $get): array
    {
        return (array) ($get('cardboard_measurements') ?? []);
    }

    private static function recalculate(Get $get, Set $set): void
    {
        $company = CompanyMeasurementSettings::company();
        $calculated = CardboardMeasurements::fromInternalDimensions(
            self::measurements($get),
            $company?->fold_margin ?? 5,
            $company?->length_flap_default ?? 60,
        );

        foreach ($calculated as $field => $value) {
            if (! str_starts_with($field, 'internal_')) {
                $set("cardboard_measurements.{$field}", $value);
            }
        }
    }
}
