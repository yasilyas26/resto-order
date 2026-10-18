<x-admin-layout title="Ubah Kategori">
    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="max-w-xl bg-white p-6 shadow sm:rounded-lg">
        @method('PUT')
        @include('admin.categories._form')
    </form>
</x-admin-layout>
