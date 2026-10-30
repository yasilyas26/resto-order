<x-visitor-layout title="Verifikasi Email" :header="false">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-lg rounded-3xl border border-brand-100 bg-white p-7 shadow-[0_10px_40px_-12px_rgba(217,56,30,0.15)] sm:p-10">
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-3xl bg-brand-100 text-brand-700">
                <x-icon name="mail" class="h-12 w-12" />
            </div>

            <h1 class="mt-5 text-center text-2xl font-extrabold tracking-tight">Cek Email Kamu</h1>
            <p class="mt-1.5 text-center text-sm text-gray-500">
                Kami sudah mengirim link verifikasi ke
                <span class="font-semibold text-gray-800">{{ auth()->user()->email }}</span>.
                Klik link itu untuk mengaktifkan akunmu.
            </p>

            @if (session('status') == 'verification-link-sent')
                <div class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                    Link verifikasi baru sudah dikirim. Cek kotak masuk Gmail kamu.
                </div>
            @endif

            <div class="mt-6 rounded-2xl bg-brand-50 px-4 py-3 text-sm text-gray-600">
                Belum ada emailnya? Cek juga folder <span class="font-semibold">Spam</span> atau <span class="font-semibold">Promosi</span>, lalu tunggu satu-dua menit.
            </div>

            <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
                @csrf
                <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3.5 font-bold text-white shadow-lg shadow-brand-600/25 hover:bg-brand-700">
                    Kirim Ulang Email Verifikasi <x-icon name="arrow-right" class="h-4 w-4" />
                </button>
            </form>

            <div class="mt-6 border-t border-gray-100 pt-5 text-center text-sm text-gray-600">
                Salah memasukkan email?
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="font-bold text-brand-700 hover:underline">Keluar dan daftar ulang</button>
                </form>
            </div>
        </div>
    </main>
</x-visitor-layout>
