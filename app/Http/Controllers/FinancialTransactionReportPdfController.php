<?php

namespace App\Http\Controllers;

use App\Models\FinancialTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class FinancialTransactionReportPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $transactions = FinancialTransaction::query()
            ->with('chartOfAccount')
            ->whereBetween('transaction_date', [$validated['start_date'], $validated['end_date']])
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->get();

        $incomeTransactions = $transactions->where('type', FinancialTransaction::TYPE_INCOME)->values();
        $expenseTransactions = $transactions->where('type', FinancialTransaction::TYPE_EXPENSE)->values();
        $incomeTotal = (float) $incomeTransactions->sum('amount') / 100;
        $expenseTotal = (float) $expenseTransactions->sum('amount') / 100;

        return Pdf::loadView('pdf.financial_transactions_report', [
            'company' => $request->user()->company,
            'startDate' => $validated['start_date'],
            'endDate' => $validated['end_date'],
            'incomeTransactions' => $incomeTransactions,
            'expenseTransactions' => $expenseTransactions,
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'generalTotal' => $incomeTotal - $expenseTotal,
        ])->stream('relatorio_lancamentos_financeiros_'.now()->format('Y-m-d-H-i').'.pdf');
    }
}
