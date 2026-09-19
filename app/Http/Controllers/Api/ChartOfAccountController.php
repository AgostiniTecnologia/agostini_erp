<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChartOfAccountController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $accounts = ChartOfAccount::where('company_id', $companyId)->get();

        return response()->json($accounts);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => 'required|uuid|exists:companies,uuid',
            'name' => 'required|string|max:100',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'parent_uuid' => 'nullable|uuid|exists:chart_of_accounts,uuid',
        ]);

        // 🔹 Agora não limitamos mais ao usuário logado,
        // basta enviar o UUID da empresa no request.

        $chartOfAccount = DB::transaction(function () use ($data): ChartOfAccount {
            $data['code'] = ChartOfAccount::generateNextCode(
                $data['company_id'],
                $data['parent_uuid'] ?? null,
            );

            return ChartOfAccount::create($data);
        });

        return response()->json($chartOfAccount, 201);
    }

    public function show(Request $request, string $uuid)
    {
        $companyId = $request->user()->company_id;

        $chartOfAccount = ChartOfAccount::where('company_id', $companyId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json($chartOfAccount);
    }

    public function update(Request $request, string $uuid)
    {
        $chartOfAccount = ChartOfAccount::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'company_id' => 'required|uuid|exists:companies,uuid',
            'name' => 'required|string|max:100',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'parent_uuid' => 'nullable|uuid|exists:chart_of_accounts,uuid',
        ]);

        if ($request->user()->company_id !== $data['company_id']) {
            return response()->json(['error' => 'Você não pode atualizar contas de outra empresa'], 403);
        }

        if (array_key_exists('parent_uuid', $data) && $data['parent_uuid'] !== $chartOfAccount->parent_uuid) {
            return response()->json([
                'error' => 'A conta pai não pode ser alterada depois da criação.',
            ], 422);
        }

        $chartOfAccount->update($data);

        return response()->json($chartOfAccount);
    }

    public function destroy(Request $request, string $uuid)
    {
        $companyId = $request->user()->company_id;

        $chartOfAccount = ChartOfAccount::where('company_id', $companyId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $chartOfAccount->delete();

        return response()->json(['message' => 'Plano de Conta removida com sucesso']);
    }
}
