<?php

namespace Tests\Unit;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateProductConnectionStatusTest extends TestCase
{
    #[Test]
    public function it_uses_the_browser_connection_status_instead_of_an_http_self_check(): void
    {
        $page = new class extends CreateProduct
        {
            public function browserIsOnline(): bool
            {
                return $this->checkOnlineStatus();
            }
        };

        $page->isOffline = false;
        $this->assertTrue($page->browserIsOnline());

        $page->isOffline = true;
        $this->assertFalse($page->browserIsOnline());
    }
}
