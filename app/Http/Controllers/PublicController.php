<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\BankAccount;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PublicController extends Controller
{
    public function index(Request $request)
    {
        $periodStart = Carbon::create(now()->month >= 7 ? now()->year : now()->year - 1, 7, 1)->startOfDay();
        $periodEnd = $periodStart->copy()->addYear()->subDay()->endOfDay();

        $fromInput = $request->get('from');
        $toInput = $request->get('to');

        $from = $fromInput
            ? Carbon::parse($fromInput)->startOfDay()
            : $periodStart->copy();
        $to = $toInput
            ? Carbon::parse($toInput)->endOfDay()
            : $periodEnd->copy();

        if ($from->lt($periodStart)) {
            $from = $periodStart->copy();
        }
        if ($to->gt($periodEnd)) {
            $to = $periodEnd->copy();
        }
        if ($from->gt($to)) {
            $from = $to->copy();
        }

        $isAdmin = session()->has('admin_authenticated');

        $bankAccounts = BankAccount::where('is_active', true)->get();
        $announcements = Announcement::where('is_active', true)->get();

        $income = Transaction::where('type', 'income')->sum('amount');
        $expense = Transaction::where('type', 'expense')->sum('amount');
        $balance = $income - $expense;

        $periodTransactions = Transaction::whereBetween('transaction_date', [
            $from->copy()->startOfDay(),
            $to->copy()->endOfDay(),
        ])->get();

        $labels = [];
        $incomeData = [];
        $expenseData = [];
        $dailyIncome = $periodTransactions->where('type', 'income')
            ->groupBy(fn ($t) => $t->transaction_date->format('Y-m-d'));
        $dailyExpense = $periodTransactions->where('type', 'expense')
            ->groupBy(fn ($t) => $t->transaction_date->format('Y-m-d'));

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d M');
            $incomeData[] = isset($dailyIncome[$key]) ? (float) $dailyIncome[$key]->sum('amount') : 0;
            $expenseData[] = isset($dailyExpense[$key]) ? (float) $dailyExpense[$key]->sum('amount') : 0;
        }

        $transactions = $this->paginatedTransactions();

        return view('public.index', compact(
            'bankAccounts',
            'announcements',
            'income',
            'expense',
            'balance',
            'labels',
            'incomeData',
            'expenseData',
            'transactions',
            'from',
            'to',
            'periodStart',
            'periodEnd',
            'isAdmin'
        ));
    }

    public function table(Request $request)
    {
        if (!$request->ajax()) {
            return redirect()->route('home', ['page' => $request->query('page', 1)]);
        }

        $isAdmin = session()->has('admin_authenticated');
        $transactions = $this->paginatedTransactions();

        return view('public._transactions', compact('transactions', 'isAdmin'));
    }

    private function paginatedTransactions()
    {
        return Transaction::orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withPath('/transactions/table');
    }

    public function downloadProof(Transaction $transaction)
    {
        if (!$transaction->proof_path || !storage_path('app/public/' . $transaction->proof_path)) {
            abort(404);
        }

        return response()->download(storage_path('app/public/' . $transaction->proof_path));
    }
}
