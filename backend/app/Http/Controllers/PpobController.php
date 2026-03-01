<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Midtrans\Config;
use Midtrans\Snap;
use Illuminate\Support\Str;

class PpobController extends Controller
{
    public function inquiry(Request $request)
    {
        // 1. Validasi Input Kritis
        $request->validate([
            'customer_number' => 'required|string|size:12|regex:/^[0-9]+$/',
        ], [
            'customer_number.size' => 'ID Pelanggan PLN wajib terdiri dari 12 digit.',
            'customer_number.regex' => 'ID Pelanggan PLN hanya dapat memuat angka.'
        ]);

        $customerNumber = $request->customer_number;

        // 2. Simulasi Kegagalan (Skenario Negatif)
        // Gunakan nomor 000000000000 untuk menguji tampilan error di Next.js nanti
        if ($customerNumber === '000000000000') {
            return response()->json([
                'success' => false,
                'message' => 'ID Pelanggan tidak ditemukan atau tagihan bulan ini sudah lunas.'
            ], 404);
        }

        // 3. Logika Data Simulasi
        $lastDigit = (int) substr($customerNumber, -1);
        $names = ['Budi Santoso', 'Siti Aminah', 'Andi Wijaya', 'Dewi Lestari', 'Agus Pratama', 'Ayu Ningsih', 'Rizky Maulana', 'Putri Sari', 'Hendra Gunawan', 'Nina Marlina'];
        $powers = ['900 VA', '1300 VA', '2200 VA', '3500 VA'];

        $customerName = $names[$lastDigit];
        $power = $powers[$lastDigit % 4];

        // Tagihan diacak antara Rp 50.000 - Rp 500.000 dengan kelipatan 1.000
        $billingAmount = rand(50, 500) * 1000;
        $adminFee = 2500; // Keuntungan platform konstan
        $totalAmount = $billingAmount + $adminFee;

        // 4. Pengembalian Data Format JSON
        return response()->json([
            'success' => true,
            'message' => 'Inquiry tagihan berhasil.',
            'data' => [
                'customer_number' => $customerNumber,
                'customer_name' => $customerName,
                'power' => $power,
                'billing_amount' => $billingAmount,
                'admin_fee' => $adminFee,
                'total_amount' => $totalAmount,
                'inquiry_ref' => 'INQ-' . time() . rand(100, 999)
            ]
        ], 200);
    }

    public function createPayment(Request $request)
    {
        $request->validate([
            'customer_number' => 'required|string|size:12|regex:/^[0-9]+$/',
            'amount' => 'required|numeric|min:10000'
        ]);

        $user = $request->user();
        $customerNumber = $request->customer_number;

        // Pada sistem nyata, Anda wajib memanggil API Inquiry PLN sekali lagi di sini
        // untuk memastikan tagihan belum dibayar oleh orang lain dalam jeda waktu tersebut.
        // Untuk simulasi ini, kita gunakan nominal yang dikirim klien namun kita asumsikan valid.
        $totalAmount = $request->amount;
        $adminFee = 2500;
        $billingAmount = $totalAmount - $adminFee;

        // 1. Rekam transaksi ke database lokal dengan status pending
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'customer_number' => $customerNumber,
            'billing_amount' => $billingAmount,
            'admin_fee' => $adminFee,
            'total_amount' => $totalAmount,
            'payment_status' => 'pending',
            'ppob_status' => 'pending',
        ]);

        // 2. Konfigurasi Kredensial Midtrans
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized = env('MIDTRANS_IS_SANITIZED', true);
        Config::$is3ds = env('MIDTRANS_IS_3DS', true);

        // 3. Susun Parameter Permintaan Snap
        $params = [
            'transaction_details' => [
                'order_id' => $transaction->id, // Menggunakan UUID transaksi
                'gross_amount' => $totalAmount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'item_details' => [
                [
                    'id' => 'PLN-POSTPAID',
                    'price' => $billingAmount,
                    'quantity' => 1,
                    'name' => 'Tagihan Listrik ' . $customerNumber
                ],
                [
                    'id' => 'ADMIN-FEE',
                    'price' => $adminFee,
                    'quantity' => 1,
                    'name' => 'Biaya Admin'
                ]
            ]
        ];

        // 4. Minta Token Pembayaran ke Midtrans
        try {
            $snapToken = Snap::getSnapToken($params);

            // Simpan token ke database untuk referensi
            $transaction->update(['midtrans_snap_token' => $snapToken]);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dibuat.',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'snap_token' => $snapToken
                ]
            ], 201);

        } catch (\Exception $e) {
            // Tangani kegagalan koneksi ke Midtrans
            $transaction->update(['payment_status' => 'failed']);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghubungkan ke server pembayaran.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function paymentCallback(Request $request)
    {
        $payload = $request->all();

        // 1. Ekstraksi data dari Midtrans
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $signatureKey = $payload['signature_key'] ?? null;
        $serverKey = env('MIDTRANS_SERVER_KEY');

        // 2. Validasi Keamanan (Signature Key)
        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if ($expectedSignature !== $signatureKey) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        // 3. Pencarian Transaksi di Database
        $transaction = Transaction::find($orderId);

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        // 4. Pembaruan Status Transaksi
        $transactionStatus = $payload['transaction_status'];

        if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
            $transaction->payment_status = 'paid';
            $transaction->ppob_status = 'success';
            // Simulasi penerbitan nomor referensi struk PLN
            $transaction->pln_receipt_ref = 'PLN-' . strtoupper(Str::random(12));
        } elseif ($transactionStatus == 'expire') {
            $transaction->payment_status = 'expired';
            $transaction->ppob_status = 'failed';
        } elseif ($transactionStatus == 'cancel' || $transactionStatus == 'deny') {
            $transaction->payment_status = 'failed';
            $transaction->ppob_status = 'failed';
        }

        $transaction->save();

        return response()->json(['success' => true, 'message' => 'Callback processed']);
    }
}
