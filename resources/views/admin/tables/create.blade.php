<x-admin-layout title="Tambah Meja">
    <form method="POST" action="{{ route('admin.tables.store') }}" class="max-w-xl bg-white p-6 shadow sm:rounded-lg">
        @include('admin.tables._form')
    </form>
</x-admin-layout>
