<x-admin-layout title="Tambah Kategori">
    <form method="POST" action="{{ route('admin.categories.store') }}" class="max-w-xl bg-white p-6 shadow sm:rounded-lg">
        @include('admin.categories._form')
    </form>
</x-admin-layout>
