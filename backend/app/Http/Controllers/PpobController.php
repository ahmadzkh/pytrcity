<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PpobController extends Controller
{
    /**
     * Inisialisasi konfigurasi global Midtrans pada setiap instansiasi pengontrol.
     */
    public function __construct()
    {
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        \Midtrans\Config::$isSanitized = env('MIDTRANS_IS_SANITIZED', true);
        \Midtrans\Config::$is3ds = env('MIDTRANS_IS_3DS', true);
    }

    /**
     * 1. INQUIRY: Memeriksa tagihan berdasarkan nomor pelanggan.
     */
    public function inquiry(Request $request)
    {
        $request->validate([
            'customer_number' => 'required|numeric|digits:12',
        ]);

        // Simulasi respons server PPOB. Dalam skenario produksi,
        // bagian ini melakukan panggilan HTTP ke API pihak ketiga (misal: Digiflazz/Alterra).
        $billingAmount = rand(100000, 500000);
        $adminFee = 2500;

        return response()->json([
            'success' => true,
            'data' => [
                'customer_number' => $request->customer_number,
                'customer_name' => 'Andi Wijaya', // Data Fiktif
                'power' => '2200 VA',
                'billing_amount' => $billingAmount,
                'admin_fee' => $adminFee,
                'total_amount' => $billingAmount + $adminFee,
            ]
        ], 200);
    }

    /**
     * 2. PAYMENT: Mencatat transaksi dan menghasilkan Snap Token Midtrans.
     */
    public function payment(Request $request)
    {
        $request->validate([
            'customer_number' => 'required|numeric|digits:12',
            'amount' => 'required|numeric|min:10000',
        ]);

        $orderId = 'TRX-' . time() . '-' . Str::random(5);

        // Pencatatan awal transaksi dengan status pending
        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'order_id' => $orderId,
            'customer_number' => $request->customer_number,
            'total_amount' => $request->amount,
            'payment_status' => 'pending',
            'ppob_status' => 'pending',
        ]);

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $request->amount,
            ],
            'customer_details' => [
                'first_name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ];

        try {
            $snapToken = \Midtrans\Snap::getSnapToken($params);

            // Pembaruan token pada basis data untuk keperluan audit
            $transaction->update(['midtrans_snap_token' => $snapToken]);

            return response()->json([
                'success' => true,
                'data' => [
                    'snap_token' => $snapToken,
                    'order_id' => $orderId,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Midtrans Snap Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal berinteraksi dengan gerbang pembayaran.'
            ], 500);
        }
    }

    /**
     * 3. CALLBACK: Menangani webhook asinkron dari server Midtrans.
     */
    public function callback(Request $request)
    {
        $serverKey = env('MIDTRANS_SERVER_KEY');

        // Validasi keaslian permintaan menggunakan algoritma SHA512
        $hashed = hash("sha512", $request->order_id . $request->status_code . $request->gross_amount . $serverKey);

        if ($hashed !== $request->signature_key) {
            Log::warning('Midtrans Webhook: Invalid Signature detected.');
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $transaction = Transaction::where('order_id', $request->order_id)->first();

        if (!$transaction) {
            Log::warning('Midtrans Webhook: Transaction ' . $request->order_id . ' not found.');
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $transactionStatus = $request->transaction_status;

        // Evaluasi status transaksi dan pembaruan basis data
        if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
            $transaction->update([
                'payment_status' => 'paid',
                'ppob_status' => 'success',
                'pln_receipt_ref' => 'PLN-' . strtoupper(Str::random(10)) // Simulasi nomor referensi token listrik
            ]);
        } elseif ($transactionStatus == 'deny' || $transactionStatus == 'cancel' || $transactionStatus == 'expire') {
            $transaction->update([
                'payment_status' => 'failed',
                'ppob_status' => 'failed'
            ]);
        }

        return response()->json(['message' => 'Callback processed successfully'], 200);
    }

    /**
     * 4. HISTORY: Mengambil daftar transaksi khusus untuk pengguna yang sedang masuk.
     */
    public function getTransactionHistory(Request $request)
    {
        $transactions = Transaction::where('user_id', $request->user()->id)
                            ->orderBy('created_at', 'desc')
                            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil riwayat transaksi',
            'data' => $transactions
        ], 200);
    }
}
