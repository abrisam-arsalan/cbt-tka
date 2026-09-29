<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    rows: { type: Array, default: () => [] },
    exams: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const exam = ref(props.filters.exam ? String(props.filters.exam) : '');
const kelas = ref(props.filters.kelas ? String(props.filters.kelas) : '');

const apply = () => {
    const query = {};
    if (exam.value) query.exam = exam.value;
    if (kelas.value) query.kelas = kelas.value;
    router.get(route('admin.results.index'), query, { preserveState: true, replace: true });
};

// Query string aktif untuk URL cetak/unduh (satu sumber kebenaran dg tabel).
const activeQuery = computed(() => {
    const parts = [];
    if (exam.value) parts.push(`exam=${exam.value}`);
    if (kelas.value) parts.push(`kelas=${kelas.value}`);
    return parts.length ? '?' + parts.join('&') : '';
});

const cetakUrl = computed(() => route('admin.results.print') + activeQuery.value);
const unduhUrl = computed(() => route('admin.results.export') + activeQuery.value);

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Hasil Ujian</h2>
                <p class="text-xs text-slate-400">{{ rows.length }} baris ditampilkan (500 hasil terbaru bila tanpa filter).</p>
            </div>

            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Ujian</label>
                    <select v-model="exam" class="h-11 rounded-lg border border-slate-300 px-3 text-sm" @change="apply">
                        <option value="">Semua ujian</option>
                        <option v-for="e in exams" :key="e.value" :value="e.value">{{ e.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Kelas</label>
                    <select v-model="kelas" class="h-11 rounded-lg border border-slate-300 px-3 text-sm" @change="apply">
                        <option value="">Semua kelas</option>
                        <option v-for="c in classes" :key="c.value" :value="c.value">{{ c.label }}</option>
                    </select>
                </div>

                <!-- <a> biasa (bukan Link Inertia): cetak dibuka di tab baru,
                     unduh harus berupa navigasi file agar tidak diintersep SPA. -->
                <a
                    :href="cetakUrl"
                    target="_blank"
                    rel="noopener"
                    class="flex h-11 items-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                >
                    🖨 Cetak (PDF)
                </a>
                <a
                    :href="unduhUrl"
                    class="flex h-11 items-center gap-2 rounded-xl bg-success-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-success-700"
                >
                    ⬇ Unduh Excel
                </a>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Siswa</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Ujian</th>
                        <th class="px-4 py-3">Nilai</th>
                        <th class="px-4 py-3">Benar / Salah / Kosong</th>
                        <th class="px-4 py-3">Dikumpulkan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ row.name }}</td>
                        <td class="px-4 py-3">{{ row.class_name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ row.exam_title }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="(row.score ?? 0) >= 75 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                            >
                                {{ row.score ?? '-' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ row.correct_count }} / {{ row.wrong_count }} / {{ row.unanswered_count }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(row.submitted_at) }}</td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada hasil ujian.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
