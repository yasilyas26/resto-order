<x-admin-layout title="Tambah Grup Opsi">
    <form method="POST" action="{{ route('admin.option-groups.store') }}" class="max-w-xl bg-white p-6 shadow sm:rounded-lg">
        @include('admin.option-groups._form')
    </form>
</x-admin-layout>
