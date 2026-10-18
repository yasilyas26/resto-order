<x-admin-layout title="Ubah Menu">
    <form method="POST" action="{{ route('admin.menus.update', $menu) }}" enctype="multipart/form-data"
        class="max-w-xl bg-white p-6 shadow sm:rounded-lg">
        @method('PUT')
        @include('admin.menus._form')
    </form>
</x-admin-layout>
