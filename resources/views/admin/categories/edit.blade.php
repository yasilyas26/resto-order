<x-admin-layout title="Ubah Kategori">
    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="max-w-xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        @method('PUT')
        @include('admin.categories._form')
    </form>
</x-admin-layout>
