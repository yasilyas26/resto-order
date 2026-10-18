<x-admin-layout title="Ubah Meja">
    <form method="POST" action="{{ route('admin.tables.update', $table) }}" class="max-w-xl bg-white p-6 shadow sm:rounded-lg">
        @method('PUT')
        @include('admin.tables._form')
    </form>
</x-admin-layout>
