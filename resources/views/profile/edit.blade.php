@php
    $card = 'rounded-2xl border border-gray-200 bg-white p-6 sm:p-8';
@endphp

<x-visitor-layout title="Profil Saya" bg="bg-gray-50">
    <main class="mx-auto max-w-2xl px-4 py-8 sm:py-10">
        <h1 class="text-2xl font-extrabold tracking-tight">Profil Saya</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $user->email }}</p>

        {{-- Kabar hasil aksi: berhasil, tidak ada perubahan, atau gagal (alasannya dari pesan validasi). --}}
        @if (session('status') === 'password-updated')
            <x-feedback-modal title="Kata sandi berhasil diganti" message="Mulai sekarang masuk dengan kata sandi yang baru." />
        @elseif (session('status') === 'profile-updated')
            <x-feedback-modal title="Informasi akun berhasil diperbarui" message="Perubahan pada nama dan emailmu sudah tersimpan." />
        @endif
        @if ($feedback = session('feedback'))
            <x-feedback-modal :type="$feedback['type']" :title="$feedback['title']" :message="$feedback['message'] ?? null" />
        @endif
        @if ($errors->updatePassword->any())
            <x-feedback-modal type="error" title="Kata sandi belum bisa diganti" :message="$errors->updatePassword->first()" />
        @elseif ($errors->userDeletion->any())
            <x-feedback-modal type="error" title="Akun belum bisa dihapus" :message="$errors->userDeletion->first()" />
        @elseif ($errors->any())
            <x-feedback-modal type="error" title="Informasi akun belum bisa disimpan" :message="$errors->first()" />
        @endif

        {{-- Informasi akun --}}
        <section class="{{ $card }} mt-6">
            <h2 class="text-lg font-bold">Informasi akun</h2>

            <form method="POST" action="{{ route('profile.update') }}" class="mt-5 space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <x-input-label for="name" value="Nama pengguna" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <x-primary-button>Simpan</x-primary-button>
            </form>
        </section>

        {{-- Kata sandi --}}
        <section id="kata-sandi" class="{{ $card }} mt-6 scroll-mt-20">
            <h2 class="text-lg font-bold">{{ $user->hasPassword() ? 'Ubah kata sandi' : 'Buat kata sandi' }}</h2>
            @unless ($user->hasPassword())
                <p class="mt-1 text-sm text-gray-500">Akunmu masuk lewat Google dan belum punya kata sandi. Buat satu kalau mau bisa masuk dengan email juga.</p>
            @endunless

            <form method="POST" action="{{ route('password.update') }}" class="mt-5 space-y-4">
                @csrf
                @method('PUT')

                @if ($user->hasPassword())
                    <div>
                        <x-input-label for="current_password" value="Kata sandi saat ini" />
                        <x-text-input id="current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                        <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
                    </div>
                @endif

                <div>
                    <x-input-label for="password" value="Kata sandi baru" />
                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                    <p class="mt-1 text-xs text-gray-500">Minimal 8 karakter, memakai huruf besar, huruf kecil, dan angka.</p>
                    <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password_confirmation" value="Konfirmasi kata sandi baru" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                    <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
                </div>

                <x-primary-button>{{ $user->hasPassword() ? 'Ubah kata sandi' : 'Buat kata sandi' }}</x-primary-button>
            </form>
        </section>

        {{-- Hapus akun: hanya untuk pelanggan --}}
        @if ($user->canDeleteOwnAccount())
            <section class="mt-6 rounded-2xl border border-red-200 bg-white p-6 sm:p-8">
                <h2 class="text-lg font-bold text-red-700">Hapus akun</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Akunmu dihapus permanen dan <b>tidak bisa dipulihkan</b>. Riwayat pesanan tetap ada di restoran sebagai catatan keuangan,
                    tapi tidak lagi terhubung denganmu dan namamu dihapus dari sana.
                </p>

                @if ($user->hasPassword())
                    <form method="POST" action="{{ route('profile.destroy') }}" class="mt-5 space-y-3"
                        data-confirm-tone="hard" data-confirm-title="Hapus akunmu secara permanen?"
                        data-confirm="Akunmu dan datanya dihapus selamanya dan tidak bisa dipulihkan. Riwayat pesanan tetap tersimpan di restoran sebagai catatan keuangan, tapi tidak lagi terhubung denganmu."
                        data-confirm-label="Ya, Hapus Akun">
                        @csrf
                        @method('DELETE')
                        <div>
                            <x-input-label for="delete_password" value="Masukkan kata sandimu untuk konfirmasi" />
                            <x-text-input id="delete_password" name="password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                            <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                        </div>
                        <x-danger-button>Hapus akun</x-danger-button>
                    </form>
                @else
                    <p class="mt-4 text-sm text-gray-600">
                        Akunmu masuk lewat Google. Untuk konfirmasi, kamu akan diminta login ulang ke Google dan memilih akun yang sama.
                    </p>
                    <x-input-error :messages="$errors->userDeletion->get('google')" class="mt-2" />
                    {{-- Form GET (bukan link) supaya modal konfirmasi ikut berlaku --}}
                    <form method="GET" action="{{ route('auth.google.confirm-delete') }}"
                        data-confirm-tone="hard" data-confirm-title="Hapus akunmu secara permanen?"
                        data-confirm="Kamu akan diminta login ulang ke Google untuk konfirmasi. Setelah berhasil, akunmu dihapus selamanya dan tidak bisa dipulihkan."
                        data-confirm-label="Lanjut ke Google">
                        <button class="mt-4 inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-red-500">
                            Konfirmasi dengan Google dan hapus akun
                        </button>
                    </form>
                @endif
            </section>
        @endif

        <form method="POST" action="{{ route('logout') }}" class="mt-8 text-center">
            @csrf
            <button class="text-sm font-semibold text-gray-600 hover:underline">Keluar dari akun</button>
        </form>
    </main>

    <x-confirm-dialog />
</x-visitor-layout>
