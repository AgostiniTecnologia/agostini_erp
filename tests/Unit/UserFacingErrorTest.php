<?php

namespace Tests\Unit;

use App\Support\UserFacingError;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UserFacingErrorTest extends TestCase
{
    #[Test]
    public function it_translates_decryption_failures_without_exposing_the_technical_message(): void
    {
        $exception = new DecryptException('The payload is invalid and contains a secret value.');
        $summary = app(UserFacingError::class)->summarize($exception, Request::create('/producao', 'POST'));

        $this->assertSame('QR Code não reconhecido', $summary['title']);
        $this->assertSame('Código inválido ou incompleto', $summary['category']);
        $this->assertStringContainsString('incompleto', $summary['cause']);
        $this->assertSame('/producao', $summary['path']);
        $this->assertStringNotContainsString('secret value', implode(' ', $summary));
    }

    #[Test]
    public function it_uses_the_friendly_validation_message(): void
    {
        $exception = ValidationException::withMessages(['scan' => 'O QR Code não contém os dados necessários para iniciar a produção.']);
        $summary = app(UserFacingError::class)->summarize($exception);

        $this->assertSame('A operação foi interrompida porque uma informação não passou pela validação.', $summary['message']);
        $this->assertSame('O QR Code não contém os dados necessários para iniciar a produção.', $summary['cause']);
    }

    #[Test]
    public function it_shows_the_real_cause_but_redacts_sensitive_values(): void
    {
        $summary = app(UserFacingError::class)->summarize(
            new \RuntimeException('Falha na integração: token=valor-secreto em /var/www/app/Service.php:42'),
        );

        $this->assertStringContainsString('Falha na integração', $summary['cause']);
        $this->assertStringContainsString('token=[protegido]', $summary['cause']);
        $this->assertStringContainsString('[caminho interno]', $summary['cause']);
        $this->assertStringNotContainsString('valor-secreto', $summary['cause']);
        $this->assertStringNotContainsString('/var/www', $summary['cause']);
    }

    #[Test]
    public function it_translates_a_duplicate_product_sku_into_an_actionable_message(): void
    {
        $databaseException = new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: products.sku');
        $exception = new QueryException('sqlite', 'insert into products', [], $databaseException);

        $summary = app(UserFacingError::class)->summarize($exception);

        $this->assertSame('Não foi possível cadastrar este produto porque o SKU informado já pertence a outro produto.', $summary['cause']);
        $this->assertStringContainsString('pesquise o SKU informado na lista de produtos', $summary['guidance']);
        $this->assertStringContainsString('informe um SKU diferente', $summary['guidance']);
        $this->assertStringNotContainsString('SQLSTATE', implode(' ', $summary));
        $this->assertStringNotContainsString('products.sku', implode(' ', $summary));
    }

    #[Test]
    public function it_translates_the_database_error_after_laravel_wraps_it_as_http_500(): void
    {
        $databaseException = new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: products.sku');
        $queryException = new QueryException('sqlite', 'insert into products', [], $databaseException);
        $wrappedException = new HttpException(500, $queryException->getMessage(), $queryException);

        $summary = app(UserFacingError::class)->summarize($wrappedException);

        $this->assertSame('Não foi possível cadastrar este produto porque o SKU informado já pertence a outro produto.', $summary['cause']);
        $this->assertStringNotContainsString('SQLSTATE', implode(' ', $summary));
    }

    #[Test]
    public function the_production_error_view_does_not_render_exception_details(): void
    {
        config(['app.debug' => false]);
        $html = view('errors.500', ['exception' => new \RuntimeException('Falha ao calcular a quantidade produzida.')])->render();

        $this->assertStringContainsString('Consultar servidor', $html);
        $this->assertStringContainsString('Não foi possível concluir a operação', $html);
        $this->assertStringContainsString('Falha ao calcular a quantidade produzida.', $html);
        $this->assertStringContainsString('Como resolver:', $html);
        $this->assertStringNotContainsString('RuntimeException', $html);
    }

    #[Test]
    public function an_unhandled_production_exception_uses_the_friendly_error_screen(): void
    {
        config(['app.debug' => false]);
        Route::get('/test-production-error-screen', fn () => throw new \RuntimeException('A quantidade informada é maior que o saldo disponível.'));

        $response = $this->get('/test-production-error-screen');

        $response
            ->assertStatus(500)
            ->assertSee('Consultar servidor')
            ->assertSee('Não foi possível concluir a operação')
            ->assertSee('A quantidade informada é maior que o saldo disponível.')
            ->assertDontSee('RuntimeException');
    }
}
