{{-- Modal pilih opsi menu. Dibuka lewat event window "open-menu" ({id} atau {id, key} untuk mengubah item). --}}
<div x-data="menuModal(@js($menus))" x-cloak
    @open-menu.window="open($event.detail)"
    @keydown.escape.window="close()">

    <div x-show="menu"
        x-transition:enter="transition-opacity ease-out duration-300 motion-reduce:transition-none"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-200 motion-reduce:transition-none"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-end justify-center bg-gray-900/50 sm:items-center sm:p-4"
        @click.self="close()">

        {{-- HP: lembar naik dari bawah layar. Layar lebar: muncul di tengah dengan zoom halus. --}}
        <div x-show="menu"
            x-transition:enter="transition ease-out duration-300 motion-reduce:transition-none"
            x-transition:enter-start="translate-y-full sm:translate-y-4 sm:scale-95 sm:opacity-0"
            x-transition:enter-end="translate-y-0 sm:scale-100 sm:opacity-100"
            x-transition:leave="transition ease-in duration-200 motion-reduce:transition-none"
            x-transition:leave-start="translate-y-0 sm:scale-100 sm:opacity-100"
            x-transition:leave-end="translate-y-full sm:translate-y-4 sm:scale-95 sm:opacity-0"
            class="relative flex max-h-[94vh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:max-w-2xl sm:rounded-3xl"
            role="dialog" aria-modal="true" :aria-label="menu ? menu.name : ''">

            <template x-if="menu">
                <div class="flex min-h-0 flex-1 flex-col">
                    {{-- Foto --}}
                    <div class="relative h-48 shrink-0 bg-brand-50 sm:h-72">
                        <span class="absolute left-1/2 top-2 z-10 h-1.5 w-10 -translate-x-1/2 rounded-full bg-white/90 shadow sm:hidden" aria-hidden="true"></span>
                        <template x-if="menu.image">
                            {{-- :key supaya skeleton diulang tiap ganti menu --}}
                            <div x-data="{ loaded: false }" :key="menu.id" class="relative h-full w-full">
                                <x-skeleton x-show="!loaded" class="absolute inset-0 h-full w-full rounded-none" />
                                <img :src="menu.image" :alt="menu.name" @load="loaded = true"
                                    x-init="$nextTick(() => { if ($el.complete && $el.naturalWidth) loaded = true })"
                                    class="h-full w-full object-cover transition-opacity duration-500" :class="loaded ? 'opacity-100' : 'opacity-0'">
                            </div>
                        </template>
                        <template x-if="!menu.image">
                            <div class="flex h-full items-center justify-center text-brand-300">
                                <x-icon name="bowl" class="h-20 w-20" />
                            </div>
                        </template>
                        <button type="button" @click="close()"
                            class="absolute right-4 top-4 rounded-full bg-white p-2 text-gray-700 shadow-md hover:bg-gray-100" aria-label="Tutup">
                            <x-icon name="x" class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-7">
                        <div class="flex items-start justify-between gap-4">
                            <h2 class="text-2xl font-bold" x-text="menu.name"></h2>
                            <div class="whitespace-nowrap text-2xl font-bold text-brand-700" x-text="$rupiah(menu.price)"></div>
                        </div>
                        <p class="mt-1 text-gray-500" x-show="menu.description" x-text="menu.description"></p>
                        <div class="mt-4 border-t border-gray-100"></div>

                        {{-- Grup opsi --}}
                        <template x-for="group in menu.groups" :key="group.id">
                            <div class="mt-6">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-bold" x-text="group.name + ' (' + rule(group) + ')'"></h3>
                                    </div>
                                    <span class="shrink-0 rounded-full px-3 py-0.5 text-xs font-semibold"
                                        :class="group.min >= 1 ? 'bg-brand-100 text-brand-700' : 'bg-indigo-50 text-gray-600'"
                                        x-text="group.min >= 1 ? 'Wajib' : 'Opsional'"></span>
                                </div>

                                {{-- Pilih satu: kartu --}}
                                <template x-if="group.max === 1">
                                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        <template x-for="option in group.options" :key="option.id">
                                            <button type="button" @click="toggle(group, option)" :disabled="!option.available"
                                                :aria-pressed="isSelected(option.id)"
                                                class="rounded-xl border-2 px-3 py-3 text-center transition disabled:cursor-not-allowed disabled:opacity-40"
                                                :class="isSelected(option.id) ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-gray-200 bg-white hover:border-brand-300'">
                                                <div class="font-semibold" x-text="option.name"></div>
                                                <div class="mt-1 text-xs font-semibold text-brand-600" x-text="option.available ? priceLabel(option.price) : 'Habis'"></div>
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                {{-- Pilih banyak: daftar centang --}}
                                <template x-if="group.max !== 1">
                                    <div class="mt-3 space-y-2.5">
                                        <template x-for="option in group.options" :key="option.id">
                                            <button type="button" @click="toggle(group, option)" :disabled="!option.available"
                                                :aria-pressed="isSelected(option.id)"
                                                class="flex w-full items-center justify-between gap-3 rounded-xl border px-4 py-3 text-left transition disabled:cursor-not-allowed disabled:opacity-40"
                                                :class="isSelected(option.id) ? 'border-brand-500 bg-brand-50' : 'border-gray-200 bg-white hover:border-brand-300'">
                                                <span class="flex items-center gap-3">
                                                    <span class="flex h-5 w-5 items-center justify-center rounded border"
                                                        :class="isSelected(option.id) ? 'border-brand-700 bg-brand-700 text-white' : 'border-gray-300 bg-white'">
                                                        <x-icon name="check" class="h-3.5 w-3.5" x-show="isSelected(option.id)" />
                                                    </span>
                                                    <span class="font-medium" x-text="option.name"></span>
                                                </span>
                                                <span class="shrink-0 whitespace-nowrap text-sm font-semibold text-brand-700" x-text="option.available ? priceLabel(option.price) : 'Habis'"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                <p class="mt-2 text-sm text-red-600" x-show="submitted && errors[group.id]" x-text="errors[group.id]" role="alert"></p>
                            </div>
                        </template>

                        {{-- Catatan --}}
                        <div class="mt-7">
                            <div class="flex items-center justify-between">
                                <label for="menu-note" class="font-bold">Catatan untuk Dapur</label>
                                <span class="text-xs text-gray-400" x-text="note.length + '/' + noteMax"></span>
                            </div>
                            <textarea id="menu-note" x-model="note" :maxlength="noteMax" rows="2"
                                placeholder="Contoh: daun bawang dipisah, tidak pakai kecap…"
                                class="mt-2 block w-full rounded-xl border border-gray-200 text-sm placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-200"></textarea>
                        </div>
                    </div>

                    {{-- Jumlah + tombol tambah --}}
                    <div class="flex items-center gap-3 border-t border-gray-100 bg-white px-5 py-4 sm:px-7">
                        <div class="flex items-center rounded-xl bg-brand-50 p-1">
                            <button type="button" @click="changeQty(-1)" class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-brand-700 shadow-sm" aria-label="Kurangi">
                                <x-icon name="minus" class="h-4 w-4" />
                            </button>
                            <span class="w-10 text-center font-bold" x-text="qty"></span>
                            <button type="button" @click="changeQty(1)" class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-brand-700 shadow-sm" aria-label="Tambah">
                                <x-icon name="plus" class="h-4 w-4" />
                            </button>
                        </div>

                        <button type="button" @click="submit()"
                            class="flex-1 rounded-xl bg-brand-700 px-4 py-3.5 text-center text-sm font-bold text-white hover:bg-brand-800 sm:text-base"
                            :class="submitted && !valid ? 'ring-2 ring-red-300' : ''"
                            x-text="(editKey ? 'Simpan Perubahan' : 'Tambah ke Keranjang') + ' - ' + totalLabel"></button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
