<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    exam: Object,
    participants: { type: Array, default: () => [] },
    availableUsers: { type: Array, default: () => [] },
});

const addForm = useForm({ user_id: '' });
const bulkForm = useForm({ user_ids: [] });

const addOne = () => {
    addForm.post(route('admin.exams.participants.store', props.exam.id), {
        onSuccess: () => addForm.reset(),
    });
};

const bulkAdd = () => {
    if (bulkForm.user_ids.length === 0) {
        alert('Pilih minimal satu siswa.');
        return;
    }
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

const toggleUser = (userId) => {
    const index = bulkForm.user_ids.indexOf(userId);
    if (index === -1) bulkForm.user_ids.push(userId);
    else bulkForm.user_ids.splice(index, 1);
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

            <details class="mb-2">
                <summary class="cursor-pointer text-sm font-semibold text-slate-700">Tambah massal</summary>
                <div class="mt-2 max-h-48 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2">
                    <label v-for="user in availableUsers" :key="user.id" class="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-slate-50">
                        <input type="checkbox" class="h-4 w-4" :checked="bulkForm.user_ids.includes(user.id)" @change="toggleUser(user.id)" />
                        {{ user.label }}
                    </label>
                    <p v-if="availableUsers.length === 0" class="p-2 text-sm text-slate-400">Semua siswa sudah terdaftar.</p>
                </div>
                <button class="mt-2 rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" :disabled="bulkForm.processing" @click="bulkAdd">
                    Tambahkan Terpilih
                </button>
            </details>
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
