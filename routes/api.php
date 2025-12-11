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

 // RelatÃ³rios:
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

    //TransaÃ§Ã£o financeira
    Route::apiResource('financialTransaction', FinancialTransactionController::class);

    Route::post('ai/production/generate-pdf', [AiReportController::class, 'generatePdf']);

    // Endpoint de sincronizaÃ§Ã£o do modo offline
    Route::post('/offline-sync', [OfflineSyncController::class, 'sync']);

    // Opcional: rota para testar estado do servidor
    Route::get('/ping', function () {
        return response()->json(['status' => 'ok'], 200);
        });
    });

    Route::get('/sales-visits', function (Request $request) {
        $limit = $request->get('limit', 100);
        
        $visits = \App\Models\SalesVisit::query()
            ->where('company_id', auth()->user()->company_id)
            ->where('assigned_to_user_id', auth()->id())
            ->whereIn('status', [
                \App\Models\SalesVisit::STATUS_SCHEDULED,
                \App\Models\SalesVisit::STATUS_IN_PROGRESS
            ])
            ->with(['client', 'salesOrder'])
            ->orderBy('scheduled_at', 'desc')
            ->limit($limit)
            ->get();
        
        return response()->json(['data' => $visits]);
    });

    Route::post('/sales-visits', function (Request $request) {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,uuid',
            'assigned_to_user_id' => 'required|exists:users,uuid',
            'scheduled_at' => 'required|date',
        ]);
        
        $validated['company_id'] = auth()->user()->company_id;
        $validated['scheduled_by_user_id'] = auth()->id();
        $validated['status'] = \App\Models\SalesVisit::STATUS_SCHEDULED;
        
        $visit = \App\Models\SalesVisit::create($validated);
        
        return response()->json(['data' => $visit], 201);
    });

    // Pedidos de Venda
    Route::get('/sales-orders', function (Request $request) {
        $limit = $request->get('limit', 100);
        
        $orders = \App\Models\SalesOrder::query()
            ->where('company_id', auth()->user()->company_id)
            ->where('user_id', auth()->id())
            ->whereNot('status', \App\Models\SalesOrder::STATUS_DRAFT)
            ->with(['client', 'items'])
            ->orderBy('order_date', 'desc')
            ->limit($limit)
            ->get();
        
        return response()->json(['data' => $orders]);
    });

    Route::post('/sales-orders', function (Request $request) {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,uuid',
            'delivery_deadline' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
        
        $validated['company_id'] = auth()->user()->company_id;
        $validated['user_id'] = auth()->id();
        $validated['order_date'] = now();
        $validated['status'] = \App\Models\SalesOrder::STATUS_PENDING;
        $validated['order_number'] = 'PED-' . strtoupper(\Illuminate\Support\Str::random(4)) . '-' . time();
        
        $order = \App\Models\SalesOrder::create($validated);
        
        return response()->json(['data' => $order], 201);
});