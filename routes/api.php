<?php 

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ChartOfAccountController;
use App\Http\Controllers\Api\FinancialTransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Http\Controllers\Api\AiReportController;
use App\Http\Controllers\Api\TransporteReportController;
use App\Http\Controllers\Api\SalesReportController;
use App\Http\Controllers\OfflineSyncController;

// LOGIN (fora do middleware)
Route::post('/login', function (Request $request) {
    $request->validate([
        'username' => 'required|string',
        'password' => 'required|string',
    ]);

    $user = User::where('username', $request->username)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return response()->json([
            'message' => 'Credenciais invÃ¡lidas'
        ], 401);
    }

    $token = $user->createToken('api_token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => [
            'uuid' => $user->uuid,
            'username' => $user->username,
            'name' => $user->name,
        ]
    ]);
});

 // Relatários:
Route::get('/relatorio/transporte/pdf', [TransporteReportController::class, 'gerarPdf'])
    ->name('transporte.relatorio.pdf');
Route::get('/relatorio/vendas/pdf', [SalesReportController::class, 'gerarPdf'])
    ->name('sales.performance.pdf');

// ROTAS PROTEGIDAS
Route::middleware('auth:sanctum')->group(function () {

    // Logout
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout realizado com sucesso'
        ]);
    });

    //Produtos
    Route::apiResource('products', ProductController::class);

    //FuncionÃ¡rios
    Route::apiResource('users', UserController::class);

    //Clientes
    Route::apiResource('clients', ClientController::class);

    //Empresas
    Route::apiResource('companies', CompanyController::class);

    //Plano de contas
    Route::apiResource('chartOfAccounts', ChartOfAccountController::class);

    //Transação financeira
    Route::apiResource('financialTransaction', FinancialTransactionController::class);

    //Relatório do dashboard de produção (IA)
    Route::post('ai/production/generate-pdf', [AiReportController::class, 'generatePdf']);

    // Endpoint de sincronização do modo offline
    Route::post('/offline-sync', [OfflineSyncController::class, 'sync']);

    // Opcional: rota para testar estado do servidor
    Route::get('/ping', function () {
        return response()->json(['status' => 'ok'], 200);
        });
    });