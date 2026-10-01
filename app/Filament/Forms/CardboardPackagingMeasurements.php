<?php

namespace App\Filament\Forms;

use App\Enums\CardboardProductType;
use App\Enums\CardboardSheetType;
use App\Support\BriefcaseMeasurements;
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
            Section::make('Medidas e peso padrão do Agostini')
                ->description('Usa o cadastro padrão de medidas, mesmo com o perfil de cartonagem ativo.')
                ->visible(fn (Get $get): bool => self::productType($get) === CardboardProductType::Standard)
                ->schema(self::standardSchema())
                ->columns(['default' => 1, 'lg' => 4]),
            Section::make('Medidas da embalagem')
                ->visible(fn (Get $get): bool => self::productType($get) === CardboardProductType::Box)
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
            Section::make('Medidas da maleta')
                ->visible(fn (Get $get): bool => self::productType($get) === CardboardProductType::Briefcase)
                ->schema([
                    Section::make('Parâmetros de cálculo deste produto')
                        ->description('Os valores vêm do padrão da empresa. Alterações feitas aqui valem somente para este produto.')
                        ->schema([
                            self::sheetType('briefcase_measurements'),
                            self::foldMargin('briefcase_measurements'),
                        ])
                        ->columns(['default' => 1, 'md' => 2]),
                    Section::make('Medidas')
                        ->schema([
                            self::measurement('internal_length', 'Comprimento', true, 'briefcase_measurements'),
                            self::measurement('internal_width', 'Largura', true, 'briefcase_measurements'),
                            self::measurement('internal_height', 'Altura', true, 'briefcase_measurements'),
                            self::measurement('auxiliary_height', 'Altura auxiliar', true, 'briefcase_measurements'),
                            Actions::make([
                                Action::make('clear_briefcase_measurements')
                                    ->label('Limpar medidas')
                                    ->icon('heroicon-o-trash')
                                    ->color('danger')
                                    ->requiresConfirmation()
                                    ->modalHeading('Limpar todas as medidas da maleta?')
                                    ->modalDescription('As medidas internas e todos os cálculos automáticos serão removidos.')
                                    ->action(fn (Set $set) => $set(
                                        'briefcase_measurements',
                                        BriefcaseMeasurements::emptyState(),
                                    )),
                            ])->columnSpanFull(),
                        ])
                        ->columns(['default' => 1, 'md' => 2, 'lg' => 4]),
                    Section::make('Composição do comprimento da chapa')
                        ->description('Calculada automaticamente e liberada para ajuste manual quando necessário.')
                        ->schema([
                            self::measurement('left_flap', 'Aba', false, 'briefcase_measurements'),
                            self::measurement('left_width', 'Larg.', false, 'briefcase_measurements'),
                            self::measurement('sheet_length', 'Comprimento', false, 'briefcase_measurements'),
                            self::measurement('right_width', 'Larg.', false, 'briefcase_measurements'),
                            self::measurement('second_length', 'Comp.', false, 'briefcase_measurements'),
                            self::total('Comprimento total', BriefcaseMeasurements::LENGTH_FIELDS, 'briefcase_measurements'),
                        ])
                        ->columns(['default' => 1, 'md' => 3, 'xl' => 6]),
                    Section::make('Composição da largura da chapa')
                        ->description('Calculada automaticamente e liberada para ajuste manual quando necessário.')
                        ->schema([
                            self::measurement('top_flap', 'Aba', false, 'briefcase_measurements'),
                            self::measurement('height', 'Altura', false, 'briefcase_measurements'),
                            self::measurement('width_auxiliary_height', 'Altura aux.', false, 'briefcase_measurements'),
                            self::measurement('bottom_flap', 'Aba', false, 'briefcase_measurements'),
                            self::total('Largura total', BriefcaseMeasurements::WIDTH_FIELDS, 'briefcase_measurements'),
                        ])
                        ->columns(['default' => 1, 'md' => 3, 'xl' => 6]),
                    Placeholder::make('briefcase_sheet_size')
                        ->label('Tamanho da chapa')
                        ->content(function (Get $get): HtmlString {
                            $measurements = self::measurements($get, 'briefcase_measurements');
                            $length = CardboardMeasurements::format(BriefcaseMeasurements::lengthTotal($measurements));
                            $width = CardboardMeasurements::format(BriefcaseMeasurements::widthTotal($measurements));
                            $unit = CompanyMeasurementSettings::lengthUnit();

                            return new HtmlString("<strong>{$length} × {$width} {$unit}</strong>");
                        })
                        ->columnSpanFull(),
                ]),
            Section::make('Medidas da chapa')
                ->visible(fn (Get $get): bool => self::productType($get) === CardboardProductType::Sheet)
                ->schema([
                    self::measurement('simple_sheet_length', 'Comprimento'),
                    self::measurement('simple_sheet_width', 'Largura'),
                    self::sheetSize(['simple_sheet_length'], ['simple_sheet_width']),
                ])
                ->columns(['default' => 1, 'md' => 2]),
            Section::make('Medidas da cantoneira')
                ->visible(fn (Get $get): bool => self::productType($get) === CardboardProductType::Corner)
                ->schema([
                    self::measurement('corner_length', 'Comprimento'),
                    Section::make('Composição da largura da chapa')
                        ->schema([
                            self::measurement('corner_height_1', 'Altura 1'),
                            self::measurement('corner_width', 'Largura'),
                            self::measurement('corner_height_2', 'Altura 2'),
                            self::total('Largura total', ['corner_height_1', 'corner_width', 'corner_height_2']),
                        ])
                        ->columns(['default' => 1, 'md' => 4]),
                    Placeholder::make('corner_length_total')
                        ->label('Comprimento total')
                        ->content(fn (Get $get): string => CardboardMeasurements::format(
                            CardboardMeasurements::cornerLengthTotal(self::measurements($get)),
                        ).' '.CompanyMeasurementSettings::lengthUnit()),
                    self::sheetSize(['corner_length'], ['corner_height_1', 'corner_width', 'corner_height_2']),
                ]),
        ];
    }

    private static function standardSchema(): array
    {
        return [
            TextInput::make('weight_net')->label('Peso líquido')->suffix(fn (): string => CompanyMeasurementSettings::weightUnit())->numeric(),
            TextInput::make('weight')->label('Peso bruto')->suffix(fn (): string => CompanyMeasurementSettings::weightUnit())->numeric(),
            TextInput::make('length')->label('Comprimento')->suffix(fn (): string => CompanyMeasurementSettings::lengthUnit())->numeric(),
            TextInput::make('width')->label('Largura')->suffix(fn (): string => CompanyMeasurementSettings::lengthUnit())->numeric(),
            TextInput::make('height')->label('Altura')->suffix(fn (): string => CompanyMeasurementSettings::lengthUnit())->numeric(),
        ];
    }

    private static function sheetSize(array $lengthFields, array $widthFields): Placeholder
    {
        return Placeholder::make('calculated_sheet_size_'.implode('_', $lengthFields))
            ->label('Tamanho da chapa')
            ->content(function (Get $get) use ($lengthFields, $widthFields): HtmlString {
                $measurements = self::measurements($get);
                $length = CardboardMeasurements::format(CardboardMeasurements::total($measurements, $lengthFields));
                $width = CardboardMeasurements::format(CardboardMeasurements::total($measurements, $widthFields));
                $unit = CompanyMeasurementSettings::lengthUnit();

                return new HtmlString("<strong>{$length} × {$width} {$unit}</strong>");
            })
            ->columnSpanFull();
    }

    private static function productType(Get $get): CardboardProductType
    {
        $type = $get('cardboard_product_type');

        return $type instanceof CardboardProductType
            ? $type
            : CardboardProductType::tryFrom((string) $type) ?? CardboardProductType::Box;
    }

    private static function measurement(
        string $name,
        string $label,
        bool $recalculate = false,
        string $statePath = 'cardboard_measurements',
    ): TextInput {
        $input = TextInput::make("{$statePath}.{$name}")
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
            $input->afterStateUpdated(fn (Get $get, Set $set) => self::recalculate($get, $set, $statePath));

            if ($name === 'internal_height') {
                $input->afterStateHydrated(function (Get $get, Set $set) use ($statePath): void {
                    if (! self::hasComposition($get, $statePath)) {
                        self::recalculate($get, $set, $statePath);
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
        string $statePath = 'cardboard_measurements',
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
            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculate($get, $set, $statePath));
    }

    private static function sheetType(string $statePath = 'cardboard_measurements'): Select
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
            ->afterStateUpdated(function (mixed $state, Get $get, Set $set) use ($statePath): void {
                $set('fold_margin', self::companyFoldMargin($state));
                self::recalculate($get, $set, $statePath);
            });
    }

    private static function foldMargin(string $statePath = 'cardboard_measurements'): TextInput
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
            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculate($get, $set, $statePath));
    }

    private static function total(string $label, array $fields, string $statePath = 'cardboard_measurements'): Placeholder
    {
        return Placeholder::make(str($label)->snake()->toString())
            ->label($label)
            ->content(function (Get $get) use ($fields, $statePath): string {
                return CardboardMeasurements::format(
                    CardboardMeasurements::total(self::measurements($get, $statePath), $fields),
                ).' '.CompanyMeasurementSettings::lengthUnit();
            });
    }

    private static function measurements(Get $get, string $statePath = 'cardboard_measurements'): array
    {
        return (array) ($get($statePath) ?? []);
    }

    private static function hasComposition(Get $get, string $statePath = 'cardboard_measurements'): bool
    {
        $measurements = self::measurements($get, $statePath);

        return collect([...CardboardMeasurements::LENGTH_FIELDS, ...CardboardMeasurements::WIDTH_FIELDS])
            ->contains(fn (string $field): bool => array_key_exists($field, $measurements));
    }

    private static function recalculate(Get $get, Set $set, string $statePath = 'cardboard_measurements'): void
    {
        $calculated = $statePath === 'briefcase_measurements'
            ? BriefcaseMeasurements::fromDimensions(
                self::measurements($get, $statePath),
                $get('fold_margin') ?? self::companyFoldMargin($get('cardboard_sheet_type')),
            )
            : CardboardMeasurements::fromInternalDimensions(
                self::measurements($get, $statePath),
                $get('fold_margin') ?? self::companyFoldMargin($get('cardboard_sheet_type')),
                $get('length_flap_default') ?? CompanyMeasurementSettings::company()?->length_flap_default ?? 60,
            );

        foreach ($calculated as $field => $value) {
            if (! str_starts_with($field, 'internal_') && $field !== 'auxiliary_height') {
                $set("{$statePath}.{$field}", $value);
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
