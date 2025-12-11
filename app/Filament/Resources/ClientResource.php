<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;
use Livewire\Component as Livewire;
use Cheesegrits\FilamentGoogleMaps\Fields\Map;
use Illuminate\Validation\Rule;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Cadastros';
    protected static ?int $navigationSort = 20;
    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('ClientTabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Dados Cadastrais')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Forms\Components\TextInput::make('taxNumber')
                                    ->label('CNPJ')
                                    ->required()
                                    ->maxLength(20)
                                    ->mask('99.999.999/9999-99')
                                    ->live(onBlur: true)
                                    ->rule(function (Get $get, $record) {
                                        $companyId = $record?->company_id ?? auth()->user()?->company_id;
                                        return Rule::unique('clients', 'taxNumber')
                                            ->where('company_id', $companyId)
                                            ->ignore($record?->uuid, 'uuid');
                                    })
                                    ->suffixAction(
                                        Forms\Components\Actions\Action::make('consultarCnpj')
                                            ->label('Consultar')
                                            ->icon(fn (Livewire $livewire) => $livewire->isLoadingCnpj ? 'heroicon-o-arrow-path fi-spin' : 'heroicon-o-magnifying-glass')
                                            ->disabled(fn (Livewire $livewire) => $livewire->isLoadingCnpj)
                                            ->action(function (Get $get, Livewire $livewire) {
                                                $cnpj = $get('taxNumber');
                                                if (!$cnpj) {
                                                    Notification::make()
                                                        ->title('CNPJ não informado')
                                                        ->warning()
                                                        ->send();
                                                    return;
                                                }
                                                $clean = preg_replace('/[^0-9]/', '', $cnpj);
                                                $livewire->dispatch('fetchCnpjClientData', cnpj: $clean);
                                            })
                                            ->color('gray')
                                    )
                                    ->placeholder('12.345.678/9012-34')
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('social_name')
                                    ->label('Razão Social')
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 3, 'lg' => 4]),
                                Forms\Components\TextInput::make('name')
                                    ->label('Nome Fantasia')
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 3, 'lg' => 5]),
                                Forms\Components\TextInput::make('state_registration')
                                    ->label('Inscrição Estadual')
                                    ->maxLength(20)
                                    ->rule(function (Get $get, $record) {
                                        $value = $get('state_registration');
                                        if (!$value) return null;
                                        $companyId = $record?->company_id ?? auth()->user()?->company_id;
                                        return Rule::unique('clients', 'state_registration')
                                            ->where('company_id', $companyId)
                                            ->ignore($record?->uuid, 'uuid');
                                    })
                                    ->columnSpan(['default' => 3, 'lg' => 4]),
                                Forms\Components\TextInput::make('municipal_registration')
                                    ->label('Inscrição Municipal')
                                    ->maxLength(20)
                                    ->columnSpan(['default' => 3, 'lg' => 4]),
                                Forms\Components\Select::make('status')
                                    ->options(Client::getStatusOptions())
                                    ->default(Client::STATUS_ACTIVE)
                                    ->columnSpan(['default' => 3, 'lg' => 4]),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->maxLength(255)
                                    ->rule(function (Get $get, $record) {
                                        $value = $get('email');
                                        if (!$value) return null;
                                        $companyId = $record?->company_id ?? auth()->user()?->company_id;
                                        return Rule::unique('clients', 'email')
                                            ->where('company_id', $companyId)
                                            ->ignore($record?->uuid, 'uuid');
                                    })
                                    ->columnSpan(['default' => 3, 'lg' => 4]),
                                Forms\Components\TextInput::make('phone_number')
                                    ->label('Telefone')
                                    ->tel()
                                    ->mask('(99) 99999-9999')
                                    ->maxLength(20)
                                    ->columnSpan(['default' => 3, 'lg' => 4]),
                                Forms\Components\Textarea::make('notes')
                                    ->label('Observações')
                                    ->columnSpanFull(),
                            ])
                            ->columns(['default' => 3, 'lg' => 12]),
                        Forms\Components\Tabs\Tab::make('Endereço e Localização')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Forms\Components\Group::make([
                                    Forms\Components\TextInput::make('address_zip_code')
                                        ->label('CEP')
                                        ->mask('99999-999')
                                        ->maxLength(9)
                                        ->suffixAction(
                                            Forms\Components\Actions\Action::make('consultarCep')
                                                ->label('Buscar')
                                                ->icon(fn (Livewire $livewire) => $livewire->isLoadingCep ? 'heroicon-o-arrow-path fi-spin' : 'heroicon-o-magnifying-glass')
                                                ->disabled(fn (Livewire $livewire) => $livewire->isLoadingCep)
                                                ->action(function (Get $get, Livewire $livewire) {
                                                    $cep = $get('address_zip_code');
                                                    if (!$cep) {
                                                        Notification::make()
                                                            ->title('CEP não informado')
                                                            ->warning()
                                                            ->send();
                                                        return;
                                                    }
                                                    $clean = preg_replace('/[^0-9]/', '', $cep);
                                                    if (strlen($clean) !== 8) {
                                                        Notification::make()
                                                            ->title('CEP inválido')
                                                            ->warning()
                                                            ->send();
                                                        return;
                                                    }
                                                    $livewire->dispatch('fetchCepData', cep: $clean);
                                                })
                                        )
                                        ->placeholder('12345-678')
                                        ->columnSpan(1),
                                    Forms\Components\TextInput::make('address_street')
                                        ->label('Logradouro')
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('address_number')
                                        ->label('Número')
                                        ->columnSpan(1),
                                    Forms\Components\TextInput::make('address_complement')
                                        ->label('Complemento')
                                        ->columnSpan(1),
                                    Forms\Components\TextInput::make('address_district')
                                        ->label('Bairro')
                                        ->columnSpan(1),
                                    Forms\Components\TextInput::make('address_city')
                                        ->label('Cidade')
                                        ->columnSpan(1),
                                    Forms\Components\TextInput::make('address_state')
                                        ->label('UF')
                                        ->length(2)
                                        ->columnSpan(1),
                                ])->columns(3),
                                Forms\Components\Group::make([
                                    Forms\Components\TextInput::make('latitude')
                                        ->label('Latitude')
                                        ->numeric()
                                        ->readOnly(),
                                    Forms\Components\TextInput::make('longitude')
                                        ->label('Longitude')
                                        ->numeric()
                                        ->readOnly(),
                                    Map::make('map_visualization')
                                        ->label('Localização')
                                        ->columnSpanFull()
                                        ->height('400px')
                                        ->draggable(false)
                                        ->clickable(false)
                                        ->reactive()
                                        ->defaultLocation(fn (Get $get) => [
                                            (float) ($get('latitude') ?? -23.55052),
                                            (float) ($get('longitude') ?? -46.633308)
                                        ])
                                        ->defaultZoom(fn (Get $get) => ($get('latitude') && $get('longitude')) ? 15 : 5)
                                ])->columns(2),
                            ])->columns(2),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('social_name')->label('Razão Social')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nome Fantasia')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('taxNumber')->label('CNPJ')->searchable(),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('phone_number')->label('Telefone'),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}