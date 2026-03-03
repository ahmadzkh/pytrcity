"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import api from "../../lib/api";
import Script from "next/script";
import Link from "next/link";

export default function DashboardPage() {
  const router = useRouter();
  const [user, setUser] = useState<{ name: string; email: string } | null>(
    null,
  );
  const [customerNumber, setCustomerNumber] = useState("");
  const [billData, setBillData] = useState<any>(null);
  const [error, setError] = useState("");
  const [isLoading, setIsLoading] = useState(false);

  // 1. Validasi Sesi Pengguna
  useEffect(() => {
    const fetchUser = async () => {
      try {
        const response = await api.get("/user");
        setUser(response.data);
      } catch (err) {
        // Jika token tidak valid atau kedaluwarsa, kembalikan ke login
        localStorage.removeItem("access_token");
        router.push("/login");
      }
    };
    fetchUser();
  }, [router]);

  // 2. Logika Pengecekan Tagihan (Inquiry)
  const handleInquiry = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setBillData(null);
    setIsLoading(true);

    try {
      const response = await api.post("/ppob/inquiry", {
        customer_number: customerNumber,
      });
      setBillData(response.data.data);
    } catch (err: any) {
      if (err.response && err.response.data && err.response.data.message) {
        setError(err.response.data.message);
      } else {
        setError("Gagal memeriksa tagihan. Pastikan server berjalan.");
      }
    } finally {
      setIsLoading(false);
    }
  };

  // 3. Logika Pembayaran & Eksekusi Midtrans Snap
  const handlePayment = async () => {
    setError("");
    setIsLoading(true);

    try {
      const response = await api.post("/ppob/payment", {
        customer_number: billData.customer_number,
        amount: billData.total_amount,
      });

      const snapToken = response.data.data.snap_token;

      // Memanggil antarmuka Snap Midtrans
      // @ts-ignore - Mengabaikan validasi tipe TypeScript untuk objek global window.snap
      window.snap.pay(snapToken, {
        onSuccess: function (result: any) {
          alert("Pembayaran Berhasil!");
          setBillData(null);
          setCustomerNumber("");
        },
        onPending: function (result: any) {
          alert("Menunggu pembayaran Anda!");
        },
        onError: function (result: any) {
          setError("Pembayaran gagal dilakukan.");
        },
        onClose: function () {
          setError("Anda menutup jendela pembayaran sebelum menyelesaikannya.");
        },
      });
    } catch (err: any) {
      setError("Gagal membuat transaksi pembayaran.");
    } finally {
      setIsLoading(false);
    }
  };

  // 4. Logika Keluar (Logout)
  const handleLogout = async () => {
    try {
      await api.post("/logout");
    } catch (err) {
      console.error("Logout API failed", err);
    } finally {
      localStorage.removeItem("access_token");
      router.push("/login");
    }
  };

  if (!user)
    return (
      <div className="min-h-screen flex items-center justify-center">
        Memuat antarmuka...
      </div>
    );

  return (
    <div className="min-h-screen bg-gray-100 p-8">
      {/* Injeksi Skrip Midtrans Snap */}
      <Script
        src="https://app.sandbox.midtrans.com/snap/snap.js"
        data-client-key={process.env.NEXT_PUBLIC_MIDTRANS_CLIENT_KEY}
        strategy="lazyOnload"
      />

      <div className="max-w-3xl mx-auto">
        <header className="flex justify-between items-center bg-white shadow-md rounded-lg p-6 mb-8 border border-gray-100">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              Dasbor Pytricity
            </h1>
            <p className="text-gray-600">
              Selamat datang,{" "}
              <span className="font-semibold text-emerald-600">
                {user.name}
              </span>
            </p>
          </div>
          <div className="flex gap-3">
            <Link
              href="/transactions"
              className="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium py-2 px-4 rounded-md transition-colors text-sm flex items-center"
            >
              Riwayat Transaksi
            </Link>
            <button
              onClick={handleLogout}
              className="bg-red-50 hover:bg-red-100 text-red-600 font-medium py-2 px-4 rounded-md transition-colors text-sm"
            >
              Keluar
            </button>
          </div>
        </header>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
          {/* Panel Cek Tagihan */}
          <div className="bg-white shadow-md rounded-lg p-6 h-fit">
            <h2 className="text-xl font-bold text-gray-800 mb-4">
              Cek Tagihan PLN
            </h2>
            {error && (
              <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-sm">
                {error}
              </div>
            )}
            <form onSubmit={handleInquiry}>
              <div className="mb-4">
                <label className="block text-gray-700 text-sm font-bold mb-2">
                  Nomor Pelanggan (12 Digit)
                </label>
                <input
                  type="text"
                  maxLength={12}
                  required
                  className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  value={customerNumber}
                  onChange={(e) =>
                    setCustomerNumber(e.target.value.replace(/\D/g, ""))
                  }
                />
              </div>
              <button
                type="submit"
                disabled={isLoading || customerNumber.length !== 12}
                className={`w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded focus:outline-none transition-colors ${
                  isLoading || customerNumber.length !== 12
                    ? "opacity-50 cursor-not-allowed"
                    : ""
                }`}
              >
                {isLoading ? "Mengecek..." : "Cek Tagihan"}
              </button>
            </form>
          </div>

          {/* Panel Rincian Tagihan */}
          <div className="bg-white shadow-md rounded-lg p-6">
            <h2 className="text-xl font-bold text-gray-800 mb-4">
              Rincian Tagihan
            </h2>
            {!billData ? (
              <p className="text-gray-500 text-sm italic">
                Masukkan nomor pelanggan untuk melihat rincian tagihan.
              </p>
            ) : (
              <div className="space-y-3">
                <div className="flex justify-between text-sm border-b pb-2">
                  <span className="text-gray-600">Nama Pelanggan</span>
                  <span className="font-semibold">
                    {billData.customer_name}
                  </span>
                </div>
                <div className="flex justify-between text-sm border-b pb-2">
                  <span className="text-gray-600">Daya Listrik</span>
                  <span className="font-semibold">{billData.power}</span>
                </div>
                <div className="flex justify-between text-sm border-b pb-2">
                  <span className="text-gray-600">Jumlah Tagihan</span>
                  <span className="font-semibold">
                    Rp {billData.billing_amount.toLocaleString("id-ID")}
                  </span>
                </div>
                <div className="flex justify-between text-sm border-b pb-2">
                  <span className="text-gray-600">Biaya Admin</span>
                  <span className="font-semibold">
                    Rp {billData.admin_fee.toLocaleString("id-ID")}
                  </span>
                </div>
                <div className="flex justify-between text-base font-bold pt-2">
                  <span className="text-gray-800">Total Pembayaran</span>
                  <span className="text-emerald-600">
                    Rp {billData.total_amount.toLocaleString("id-ID")}
                  </span>
                </div>

                <button
                  onClick={handlePayment}
                  disabled={isLoading}
                  className={`w-full mt-6 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded focus:outline-none transition-colors ${
                    isLoading ? "opacity-50 cursor-not-allowed" : ""
                  }`}
                >
                  {isLoading ? "Memproses..." : "Bayar Sekarang"}
                </button>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
