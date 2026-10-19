<x-admin-layout title="Ubah Menu">
    <form method="POST" action="{{ route('admin.menus.update', $menu) }}" enctype="multipart/form-data"
        class="max-w-xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        @method('PUT')
        @include('admin.menus._form')
    </form>
</x-admin-layout>
