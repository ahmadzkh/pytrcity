<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // Admin dashboard stats
    public function getDashboardStats(Request $request)
    {
        $totalRevenue = Transaction::where('payment_status', 'paid')->sum('total_amount');
        $successfulTransactions = Transaction::where('payment_status', 'paid')->count();
        $totalUsers = User::where('role', 'user')->count();

        $recentTransactions = Transaction::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($trx) {
                return [
                    'id' => $trx->order_id,
                    'user' => $trx->user->name ?? 'Anonim',
                    'customer_number' => $trx->customer_number,
                    'amount' => $trx->total_amount,
                    'status' => $trx->payment_status,
                    'date' => $trx->created_at->format('Y-m-d H:i')
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'revenue' => $totalRevenue,
                    'success_count' => $successfulTransactions,
                    'user_count' => $totalUsers
                ],
                'recent_transactions' => $recentTransactions
            ]
        ], 200);
    }

    // Admin dashboard transactions
    public function getAllTransactions(Request $request)
    {
        $query = Transaction::with('user:id,name')->orderBy('created_at', 'desc');

        // Terapkan filter status jika parameter dikirim oleh klien
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('payment_status', $request->status);
        }

        // Batasi 10 data per halaman
        $transactions = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $transactions
        ], 200);
    }

    // Admin dashboard users
    public function getUsers(Request $request)
    {
        $query = User::query();

        // Terapkan filter peran jika parameter spesifik dikirim
        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // Terapkan pencarian parsial pada nama atau email
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = strtolower($request->search);
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        $query->orderBy('created_at', 'desc');

        // Batasi 10 data per halaman
        $users = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $users
        ], 200);
    }
}
