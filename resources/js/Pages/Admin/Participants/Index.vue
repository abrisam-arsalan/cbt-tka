<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    exam: Object,
    participants: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    availableUsers: { type: Array, default: () => [] },
});

const addForm = useForm({ user_id: '' });
const bulkForm = useForm({ class_id: '' });

const addOne = () => {
    addForm.post(route('admin.exams.participants.store', props.exam.id), {
        onSuccess: () => addForm.reset(),
    });
};

const bulkAdd = () => {
    if (!bulkForm.class_id) {
        alert('Pilih kelas terlebih dahulu.');
        return;
    }
    if (!confirm('Tambahkan seluruh siswa kelas ini sebagai peserta beserta token ujiannya?')) return;
    bulkForm.post(route('admin.exams.participants.bulk', props.exam.id), {
        onSuccess: () => bulkForm.reset(),
    });
};

const generateTokens = () => {
    router.post(route('admin.exams.participants.generate-tokens', props.exam.id));
};

const regenerateToken = (participant) => {
    if (!confirm(`Generate ulang token untuk ${participant.name}? Token lama tidak berlaku.`)) return;
    router.post(route('admin.exams.participants.regenerate-token', { exam: props.exam.id, participant: participant.id }));
};

const destroy = (participant) => {
    if (!confirm(`Hapus peserta ${participant.name}?`)) return;
    router.delete(route('admin.exams.participants.destroy', { exam: props.exam.id, participant: participant.id }));
};

</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Peserta Ujian</h2>
                <p class="text-sm text-slate-500">{{ exam.title }}</p>
            </div>
            <button class="rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white" @click="generateTokens">
                Generate Token
            </button>
        </div>

        <!-- Tambah peserta -->
        <div class="mb-4 rounded-xl bg-white p-4 shadow-sm">
            <h3 class="mb-2 font-semibold text-slate-800">Tambah Peserta</h3>

            <div class="mb-3 flex gap-2">
                <select v-model="addForm.user_id" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— pilih siswa —</option>
                    <option v-for="user in availableUsers" :key="user.id" :value="user.id">{{ user.label }}</option>
                </select>
                <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white" :disabled="addForm.processing || !addForm.user_id" @click="addOne">
                    Tambah
                </button>
            </div>

            <div class="border-t border-slate-100 pt-3">
                <p class="mb-2 text-sm font-semibold text-slate-700">Tambah massal per kelas</p>
                <div class="flex gap-2">
                    <select v-model="bulkForm.class_id" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— pilih kelas —</option>
                        <option v-for="cls in classes" :key="cls.id" :value="cls.id" :disabled="cls.unregistered_count === 0">
                            {{ cls.name }} — {{ cls.unregistered_count }} dari {{ cls.students_count }} siswa belum terdaftar
                        </option>
                    </select>
                    <button
                        class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="bulkForm.processing || !bulkForm.class_id"
                        @click="bulkAdd"
                    >
                        {{ bulkForm.processing ? 'Menambahkan...' : 'Tambah' }}
                    </button>
                </div>
                <p v-if="classes.length === 0" class="mt-2 text-sm text-slate-400">Belum ada kelas. Buat kelas terlebih dahulu (mis. 7A, 7B, ...).</p>
            </div>
        </div>

        <!-- Tabel peserta -->
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Token</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="participant in participants" :key="participant.id" class="border-b border-slate-100 last:border-0">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ participant.name }}</td>
                        <td class="px-4 py-3">{{ participant.class_name }}</td>
                        <td class="px-4 py-3">
                            <span :class="participant.has_token ? 'text-success-600' : 'text-danger-600'">
                                {{ participant.has_token ? '✓ Tersedia' : '✗ Belum ada' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <button class="rounded-lg bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700" @click="regenerateToken(participant)">Regenerate Token</button>
                                <button class="rounded-lg bg-danger-50 px-2.5 py-1 text-xs font-semibold text-danger-700" @click="destroy(participant)">Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="participants.length === 0">
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">Belum ada peserta.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
