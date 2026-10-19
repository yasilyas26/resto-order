{{-- Modal konfirmasi untuk semua form ber-atribut data-confirm (lihat resources/js/admin/confirmDialog.js).
     Taruh sekali di layout/halaman; pemakaian dan atribut yang didukung dijelaskan di file JS itu. --}}
<div x-data="confirmDialog" @submit.document="intercept($event)" @keydown.escape.window="close()">
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="close()">
        <div role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message"
            class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                    :class="hard ? 'bg-red-100 text-brand-700' : 'bg-amber-100 text-amber-700'">
                    <x-icon name="trash" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h2 id="confirm-title" class="text-lg font-extrabold tracking-tight" x-text="title"></h2>
                    <p x-show="name" class="mt-1 break-words text-sm font-bold" x-text="name"></p>
                    <p id="confirm-message" class="mt-2 text-sm text-gray-600" x-text="message"></p>
                    <p x-show="hard" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-brand-700">Tindakan ini tidak bisa dibatalkan.</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" x-ref="cancel" @click="close()" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                <button type="button" @click="accept()" x-text="label"
                    class="rounded-xl px-4 py-2 text-sm font-bold text-white"
                    :class="hard ? 'bg-brand-700 hover:bg-brand-800' : 'bg-amber-600 hover:bg-amber-700'"></button>
            </div>
        </div>
    </div>
</div>
