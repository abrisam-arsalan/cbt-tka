<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    edit: Boolean,
    exam: { type: Object, default: null },
    statusOptions: { type: Array, default: () => [] },
    antiCheatActionOptions: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    batches: { type: Array, default: () => [] },
});

const toLocalInput = (value) => {
    if (!value) return '';
    const d = new Date(value);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const form = useForm({
    title: props.exam?.title ?? '',
    description: props.exam?.description ?? '',
    class_id: props.exam?.class_id ?? '',
    grade: props.exam?.grade ?? '',
    batch_ids: [],
    duration_minutes: props.exam?.duration_minutes ?? 60,
    offline_grace_minutes: props.exam?.offline_grace_minutes ?? 10,
    start_at: props.exam?.start_at ? toLocalInput(props.exam.start_at) : '',
    end_at: props.exam?.end_at ? toLocalInput(props.exam.end_at) : '',
    anti_cheat_enabled: props.exam?.anti_cheat_enabled ?? false,
    anti_cheat_max_warnings: props.exam?.anti_cheat_max_warnings ?? 3,
    anti_cheat_action: props.exam?.anti_cheat_action ?? 'log_only',
    shuffle_questions: props.exam?.shuffle_questions ?? false,
    shuffle_options: props.exam?.shuffle_options ?? false,
});

// Hanya tampilkan bank yang cocok dengan kelas terpilih (atau bank tanpa kelas).
const visibleBatches = computed(() => {
    if (!form.class_id) return props.batches;
    const cls = props.classes.find((c) => String(c.value) === String(form.class_id));
    return props.batches.filter((b) => !b.class_name || (cls && b.class_name === cls.label));
});

// Target ujian: 'all' (semua siswa) | 'jenjang' (7/8/9) | 'rombel' (kelas tertentu).
const targetType = ref(
    props.exam?.class_id ? 'rombel' : (props.exam?.grade ? 'jenjang' : 'all'),
);

const jenjangOptions = ['7', '8', '9'];

const submit = () => {
    // Kosongkan field target yang tidak dipakai agar tidak saling bertabrakan.
    form.class_id = targetType.value === 'rombel' ? form.class_id : '';
    form.grade = targetType.value === 'jenjang' ? form.grade : '';

    if (props.edit) {
        form.transform(({ batch_ids, ...rest }) => rest).put(route('admin.exams.update', props.exam.id));
    } else {
        form.post(route('admin.exams.store'));
    }
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mx-auto max-w-2xl">
            <div class="mb-4 flex items-center gap-2">
                <Link :href="route('admin.exams.index')" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 transition hover:bg-slate-200" title="Kembali">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                </Link>
                <h2 class="text-xl font-bold text-slate-900">{{ title }}</h2>
            </div>

            <form @submit.prevent="submit" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Judul Ujian</label>
                    <input v-model="form.title" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-danger-600">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Deskripsi</label>
                    <textarea v-model="form.description" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Durasi (menit)</label>
                        <input v-model.number="form.duration_minutes" type="number" min="1" max="600" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                        <p v-if="form.errors.duration_minutes" class="mt-1 text-xs text-danger-600">{{ form.errors.duration_minutes }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Grace Period (menit)</label>
                        <input v-model.number="form.offline_grace_minutes" type="number" min="0" max="120" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                        <p class="mt-1 text-xs text-slate-400">Toleransi jawaban offline setelah waktu habis.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Mulai</label>
                        <input v-model="form.start_at" type="datetime-local" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Selesai</label>
                        <input v-model="form.end_at" type="datetime-local" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                        <p v-if="form.errors.end_at" class="mt-1 text-xs text-danger-600">{{ form.errors.end_at }}</p>
                    </div>
                </div>

                <!-- Target ujian -->
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Target Peserta</label>
                    <div class="grid grid-cols-3 gap-2">
                        <label
                            v-for="opt in [
                                { value: 'all', label: 'Semua Siswa' },
                                { value: 'jenjang', label: 'Per Jenjang' },
                                { value: 'rombel', label: 'Per Rombel' },
                            ]"
                            :key="opt.value"
                            class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 px-3 py-2.5 text-sm font-semibold"
                            :class="targetType === opt.value ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:border-slate-300'"
                        >
                            <input v-model="targetType" type="radio" name="target_type" :value="opt.value" class="hidden" />
                            {{ opt.label }}
                        </label>
                    </div>

                    <div v-if="targetType === 'jenjang'" class="mt-3">
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Jenjang</label>
                        <select v-model="form.grade" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                            <option value="">— pilih jenjang —</option>
                            <option v-for="g in jenjangOptions" :key="g" :value="g">Kelas {{ g }} (semua rombel)</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Ujian bisa diakses seluruh siswa kelas {{ form.grade || '?' }}, semua rombel.</p>
                    </div>

                    <div v-if="targetType === 'rombel'" class="mt-3">
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Rombel</label>
                        <select v-model="form.class_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                            <option value="">— pilih rombel —</option>
                            <option v-for="c in classes" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Ujian hanya bisa diakses siswa pada rombel ini.</p>
                    </div>

                    <p v-if="form.errors.class_id" class="mt-1 text-xs text-danger-600">{{ form.errors.class_id }}</p>
                    <p v-if="form.errors.grade" class="mt-1 text-xs text-danger-600">{{ form.errors.grade }}</p>
                </div>

                <!-- Pilih Bank Soal (hanya saat membuat) -->
                <div v-if="!edit">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Soal (pilih dari Bank Soal)</label>
                    <p class="mb-2 text-xs text-slate-400">Soal dari bank terpilih akan <b>disalin</b> ke ujian ini. Mengubah bank setelahnya tidak memengaruhi ujian ini.</p>
                    <select
                        v-if="visibleBatches.length"
                        v-model="form.batch_ids"
                        multiple
                        size="3"
                        class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none"
                    >
                        <option v-for="b in visibleBatches" :key="b.value" :value="b.value" class="px-2 py-1.5">
                            {{ b.label }} — {{ b.class_name ? b.class_name + ' · ' : '' }}{{ b.questions_count }} soal
                        </option>
                    </select>
                    <p v-else class="rounded-lg bg-slate-50 px-3 py-3 text-sm text-slate-500">Belum ada bank soal. Impor lewat menu <b>Bank Soal → Impor Soal</b> terlebih dahulu.</p>
                    <p class="mt-1 text-xs text-slate-400">Pilih satu atau lebih (tahan Ctrl / Cmd untuk memilih banyak).</p>
                    <p v-if="form.errors.batch_ids" class="mt-1 text-xs text-danger-600">{{ form.errors.batch_ids }}</p>
                </div>
                <div v-else class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">
                    Soal ujian diatur terpisah lewat halaman Bank Soal milik ujian ini.
                </div>

                <!-- Pengaturan ujian (satu kotak, urut ke bawah) -->
                <div class="space-y-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-100">
                    <div>
                        <label class="flex items-center gap-2 font-semibold text-slate-800">
                            <input v-model="form.anti_cheat_enabled" type="checkbox" class="h-4 w-4" />
                            Aktifkan Anti-Cheat
                        </label>
                        <div v-if="form.anti_cheat_enabled" class="mt-3 space-y-3 border-l-2 border-slate-200 pl-4">
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-slate-700">Tindakan Saat Batas Terlampaui</label>
                                <select v-model="form.anti_cheat_action" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                                    <option v-for="option in antiCheatActionOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-slate-700">Batas Peringatan</label>
                                <input v-model.number="form.anti_cheat_max_warnings" type="number" min="0" max="10" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                            </div>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input v-model="form.shuffle_questions" type="checkbox" class="h-4 w-4" />
                        Acak Soal
                    </label>

                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input v-model="form.shuffle_options" type="checkbox" class="h-4 w-4" />
                        Acak Opsi Jawaban
                    </label>
                </div>

                <button type="submit" :disabled="form.processing" class="h-12 w-full rounded-xl bg-brand-600 font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700 disabled:opacity-50">
                    {{ form.processing ? 'Menyimpan...' : (edit ? 'Simpan Perubahan' : 'Buat Ujian') }}
                </button>
            </form>
        </div>
    </AdminLayout>
</template>
