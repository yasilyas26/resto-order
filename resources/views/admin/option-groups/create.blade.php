<x-admin-layout title="Tambah Grup Opsi">
    <div class="grid max-w-5xl gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Buat level cepat</h2>
            <p class="mt-1 mb-5 text-sm text-gray-500">
                Untuk level pedas atau deretan angka lain. Semua level dibuat sekaligus dalam satu grup, dan bisa diedit lagi sesudahnya.
            </p>
            @include('admin.option-groups._level-builder')
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Buat grup biasa</h2>
            <p class="mt-1 mb-5 text-sm text-gray-500">Untuk topping atau pilihan lain yang opsinya kamu isi sendiri satu per satu.</p>
            <form method="POST" action="{{ route('admin.option-groups.store') }}">
                @include('admin.option-groups._form')
            </form>
        </section>
    </div>
</x-admin-layout>
