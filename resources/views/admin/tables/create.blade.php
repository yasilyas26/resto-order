<x-admin-layout title="Tambah Meja">
    <form method="POST" action="{{ route('admin.tables.store') }}" class="max-w-xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        @include('admin.tables._form')
    </form>
</x-admin-layout>
