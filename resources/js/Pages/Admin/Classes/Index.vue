<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    classes: { type: Array, default: () => [] },
    importErrors: { type: Array, default: () => [] },
});

// Kelompokkan rombel per jenjang (7/8/9) untuk tampilan daftar.
const jenjangLabels = { 7: 'Jenjang Kelas 7 (Satu SMP)', 8: 'Jenjang Kelas 8 (Dua SMP)', 9: 'Jenjang Kelas 9 (Tiga SMP)' };

const grouped = computed(() => {
    const map = {};
    for (const cls of props.classes) {
        const key = cls.grade != null && cls.grade !== '' ? String(cls.grade) : '—';
        (map[key] ??= []).push(cls);
    }
    return Object.entries(map).sort(([a], [b]) => {
        if (a === '—') return 1;
        if (b === '—') return -1;
        return Number(a) - Number(b);
    });
});

const btnPrimarySm = 'bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';
const btnDangerSm = 'bg-red-600 hover:bg-red-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';

const syncing = ref(false);

const syncFromPortal = () => {
    if (confirm('Sinkronkan seluruh data kelas dari Portal Panglima ke Ujian?')) {
        syncing.value = true;
        router.post(route('admin.classes.sync'), {}, {
            onFinish: () => {
                syncing.value = false;
            },
        });
    }
};

const destroy = (classItem) => {
    if (!confirm(`Hapus kelas "${classItem.name}"?`)) return;
    router.delete(route('admin.classes.destroy', classItem.id));
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Manajemen Kelas</h2>
                <p class="text-sm text-gray-500">Kelola rombel & jenjang kelas yang disinkronkan dari Portal Panglima.</p>
            </div>
            <button
                type="button"
                @click="syncFromPortal"
                :disabled="syncing"
                class="flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700 disabled:opacity-50"
            >
                <svg v-if="syncing" class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span v-if="syncing">Mensinkronkan...</span>
                <span v-else>🔄 Sinkronkan Kelas dari Portal</span>
            </button>
        </div>

        <div class="mb-4 rounded-xl border border-sky-100 bg-sky-50/70 p-4 text-xs text-sky-800">
            <div class="flex items-start gap-2">
                <span class="text-base">ℹ️</span>
                <div>
                    <p class="font-semibold text-sky-900">Manajemen Kelas Dikelola Terpusat di Portal Panglima</p>
                    <p class="mt-0.5 text-sky-700">
                        Sesuai konfigurasi sistem, penambahan dan pengelolaan data kelas (nama rombel & jenjang) dilakukan terpusat melalui <b>Portal Panglima</b> (Master Data & Impor). Gunakan tombol <b>"Sinkronkan Kelas dari Portal"</b> di atas untuk menarik pembaruan kelas terbaru ke CBT.
                    </p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Tingkat</th>
                        <th class="px-4 py-3">Tahun Ajaran</th>
                        <th class="px-4 py-3">Siswa</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="[jenjang, rombels] in grouped" :key="jenjang">
                        <tr class="border-y border-brand-100 bg-brand-50/60">
                            <td colspan="6" class="px-4 py-2 text-xs font-bold uppercase tracking-wide text-brand-700">
                                {{ jenjangLabels[jenjang] ?? `Jenjang ${jenjang}` }} — {{ rombels.length }} rombel
                            </td>
                        </tr>
                        <tr v-for="classItem in rombels" :key="classItem.id" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ classItem.name }}</td>
                        <td class="px-4 py-3">{{ classItem.grade ?? '-' }}</td>
                        <td class="px-4 py-3">{{ classItem.academic_year ?? '-' }}</td>
                        <td class="px-4 py-3">{{ classItem.students_count }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="classItem.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ classItem.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <Link :href="route('admin.classes.edit', classItem.id)" :class="btnPrimarySm">Edit</Link>
                                <button :class="btnDangerSm" @click="destroy(classItem)">Hapus</button>
                            </div>
                        </td>
                        </tr>
                    </template>
                    <tr v-if="classes.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada kelas.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
