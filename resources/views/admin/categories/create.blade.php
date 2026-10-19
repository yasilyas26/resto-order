<x-admin-layout title="Tambah Kategori">
    <form method="POST" action="{{ route('admin.categories.store') }}" class="max-w-xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        @include('admin.categories._form')
    </form>
</x-admin-layout>
