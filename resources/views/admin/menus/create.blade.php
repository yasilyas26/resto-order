<x-admin-layout title="Tambah Menu">
    <form method="POST" action="{{ route('admin.menus.store') }}" enctype="multipart/form-data"
        class="max-w-xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        @include('admin.menus._form')
    </form>
</x-admin-layout>
