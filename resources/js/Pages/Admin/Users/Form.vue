<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    edit: Boolean,
    user: { type: Object, default: null },
    classes: { type: Array, default: () => [] },
    roleOptions: { type: Array, default: () => [] },
});

const form = useForm({
    username: props.user?.username ?? '',
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    role: props.user?.role ?? 'siswa',
    class_id: props.user?.class_id ?? '',
    nisn: props.user?.nisn ?? '',
    phone: props.user?.phone ?? '',
    is_active: props.user?.is_active ?? true,
});

const submit = () => {
    if (props.edit) {
        form.put(route('admin.users.update', props.user.id));
    } else {
        form.post(route('admin.users.store'));
    }
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mx-auto max-w-lg">
            <h2 class="mb-4 text-xl font-bold text-slate-900">{{ title }}</h2>

            <form @submit.prevent="submit" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Username</label>
                    <input v-model="form.username" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                    <p v-if="form.errors.username" class="mt-1 text-xs text-danger-600">{{ form.errors.username }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Nama Lengkap</label>
                    <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-danger-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Email (opsional)</label>
                    <input v-model="form.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-danger-600">{{ form.errors.email }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Password</label>
                        <input v-model="form.password" type="password" :required="!edit" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-danger-600">{{ form.errors.password }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Konfirmasi</label>
                        <input v-model="form.password_confirmation" type="password" :required="!edit" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Kelas</label>
                    <select v-model="form.class_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="">— Tanpa Kelas —</option>
                        <option v-for="classItem in classes" :key="classItem.value" :value="classItem.value">{{ classItem.label }}</option>
                    </select>
                    <p v-if="form.errors.class_id" class="mt-1 text-xs text-danger-600">{{ form.errors.class_id }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">NISN (opsional)</label>
                        <input v-model="form.nisn" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                        <p v-if="form.errors.nisn" class="mt-1 text-xs text-danger-600">{{ form.errors.nisn }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">No. HP (opsional)</label>
                        <input v-model="form.phone" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4" />
                    Akun aktif
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="h-12 w-full rounded-xl bg-brand-600 font-semibold text-white disabled:opacity-50"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                </button>
            </form>
        </div>
    </AdminLayout>
</template>
