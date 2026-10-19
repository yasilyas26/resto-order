<x-admin-layout title="Ubah Meja">
    <form method="POST" action="{{ route('admin.tables.update', $table) }}" class="max-w-xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        @method('PUT')
        @include('admin.tables._form')
    </form>
</x-admin-layout>
