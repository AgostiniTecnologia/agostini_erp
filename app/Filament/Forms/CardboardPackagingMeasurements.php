<?php

namespace App\Filament\Forms;

use App\Enums\CardboardSheetType;
use App\Support\CardboardMeasurements;
use App\Support\CompanyMeasurementSettings;
use Closure;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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
                    Section::make('Parâmetros de cálculo deste produto')
                        ->description('Os valores vêm do padrão da empresa. Alterações feitas aqui valem somente para este produto.')
                        ->schema([
                            self::sheetType(),
                            self::foldMargin(),
                            self::calculationSetting('length_flap_default', 'Aba padrão', 'length_flap_default', 60),
                        ])
                        ->columns(['default' => 1, 'md' => 3]),
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
                        ->description('Calculada automaticamente e liberada para ajuste manual quando necessário.')
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
                        ->description('Calculada automaticamente e liberada para ajuste manual quando necessário.')
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

            if ($name === 'internal_height') {
                $input->afterStateHydrated(function (Get $get, Set $set): void {
                    if (! self::hasComposition($get)) {
                        self::recalculate($get, $set);
                    }
                });
            }
        }

        return $input;
    }

    private static function calculationSetting(
        string $name,
        string $label,
        string $companyAttribute,
        float $fallback,
    ): TextInput {
        return TextInput::make($name)
            ->label($label)
            ->suffix(fn (): string => CompanyMeasurementSettings::lengthUnit())
            ->numeric()
            ->minValue(0)
            ->required()
            ->default(fn (): mixed => CompanyMeasurementSettings::company()?->{$companyAttribute} ?? $fallback)
            ->afterStateHydrated(function (TextInput $component, mixed $state) use ($companyAttribute, $fallback): void {
                if (blank($state)) {
                    $component->state(CompanyMeasurementSettings::company()?->{$companyAttribute} ?? $fallback);
                }
            })
            ->live(onBlur: true)
            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculate($get, $set));
    }

    private static function sheetType(): Select
    {
        return Select::make('cardboard_sheet_type')
            ->label('Tipo de chapa')
            ->options(CardboardSheetType::class)
            ->default(CardboardSheetType::Simple->value)
            ->required()
            ->native(false)
            ->live()
            ->afterStateHydrated(function (Select $component, mixed $state): void {
                if (blank($state)) {
                    $component->state(CardboardSheetType::Simple->value);
                }
            })
            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                $set('fold_margin', self::companyFoldMargin($state));
                self::recalculate($get, $set);
            });
    }

    private static function foldMargin(): TextInput
    {
        return TextInput::make('fold_margin')
            ->label(fn (Get $get): string => self::sheetTypeValue($get('cardboard_sheet_type')) === CardboardSheetType::Double
                ? 'Margem de dobra dupla'
                : 'Margem de dobra simples')
            ->suffix(fn (): string => CompanyMeasurementSettings::lengthUnit())
            ->numeric()
            ->minValue(0)
            ->required()
            ->default(fn (Get $get): mixed => self::companyFoldMargin($get('cardboard_sheet_type')))
            ->afterStateHydrated(function (TextInput $component, mixed $state, Get $get): void {
                if (blank($state)) {
                    $component->state(self::companyFoldMargin($get('cardboard_sheet_type')));
                }
            })
            ->live(onBlur: true)
            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculate($get, $set));
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

    private static function hasComposition(Get $get): bool
    {
        $measurements = self::measurements($get);

        return collect([...CardboardMeasurements::LENGTH_FIELDS, ...CardboardMeasurements::WIDTH_FIELDS])
            ->contains(fn (string $field): bool => array_key_exists($field, $measurements));
    }

    private static function recalculate(Get $get, Set $set): void
    {
        $calculated = CardboardMeasurements::fromInternalDimensions(
            self::measurements($get),
            $get('fold_margin') ?? self::companyFoldMargin($get('cardboard_sheet_type')),
            $get('length_flap_default') ?? CompanyMeasurementSettings::company()?->length_flap_default ?? 60,
        );

        foreach ($calculated as $field => $value) {
            if (! str_starts_with($field, 'internal_')) {
                $set("cardboard_measurements.{$field}", $value);
            }
        }
    }

    private static function companyFoldMargin(mixed $sheetType): mixed
    {
        $company = CompanyMeasurementSettings::company();

        return self::sheetTypeValue($sheetType) === CardboardSheetType::Double
            ? $company?->fold_margin_double ?? $company?->fold_margin ?? 5
            : $company?->fold_margin ?? 5;
    }

    private static function sheetTypeValue(mixed $sheetType): CardboardSheetType
    {
        if ($sheetType instanceof CardboardSheetType) {
            return $sheetType;
        }

        return CardboardSheetType::tryFrom((string) $sheetType) ?? CardboardSheetType::Simple;
    }
}
