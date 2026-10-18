<x-admin-layout title="Tambah Menu">
    <form method="POST" action="{{ route('admin.menus.store') }}" enctype="multipart/form-data"
        class="max-w-xl bg-white p-6 shadow sm:rounded-lg">
        @include('admin.menus._form')
    </form>
</x-admin-layout>
