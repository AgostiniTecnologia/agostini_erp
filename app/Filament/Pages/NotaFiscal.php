<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Auth\Authenticatable;

class NotaFiscal extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Contábil';

    protected static ?string $navigationLabel = 'Nota Fiscal';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Nota Fiscal';

    protected static ?string $slug = 'nota-fiscal';

    protected static string $view = 'filament.pages.nota-fiscal';

    public static function canAccess(): bool
    {
        /** @var Authenticatable|null $user */
        $user = auth()->user();

        return $user !== null && method_exists($user, 'hasAnyRole') && $user->hasAnyRole([
            'Contábil',
            config('filament-shield.super_admin.name'),
        ]);
    }

    public static function getNavigationUrl(): string
    {
        return (string) config('services.nfse.emitter_url');
    }

    public function mount(): void
    {
        $this->redirect(static::getNavigationUrl(), navigate: false);
    }
}
