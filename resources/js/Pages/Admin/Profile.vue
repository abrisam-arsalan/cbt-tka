<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    user: Object,
});

const form = useForm({
    name: props.user?.name ?? '',
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put(route('admin.profile.update'), {
        onSuccess: () => form.reset('current_password', 'password', 'password_confirmation'),
        preserveScroll: true,
    });
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mx-auto max-w-xl">
            <h2 class="mb-1 text-xl font-bold text-slate-900">Profil Saya</h2>
            <p class="mb-5 text-sm text-slate-500">Ubah nama yang tampil dan password akun Anda.</p>

            <form @submit.prevent="submit" class="space-y-5">
                <!-- Identitas -->
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
                    <div class="mb-5 flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-orange-600 text-lg font-bold text-white">
                            {{ (user?.name || '?').trim().slice(0, 1).toUpperCase() }}
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">{{ user?.name }}</p>
                            <p class="text-sm text-slate-500">{{ user?.username }} · {{ user?.role_label }}</p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Username</label>
                            <input :value="user?.username" type="text" disabled class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-500" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Email</label>
                            <input :value="user?.email || '—'" type="text" disabled class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-500" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-slate-400">Login terakhir: {{ formatDate(user?.last_login_at) }}</p>
                </div>

                <!-- Nama tampil -->
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
                    <h3 class="mb-4 font-semibold text-slate-800">Nama yang Tampil</h3>
                    <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-danger-600">{{ form.errors.name }}</p>
                </div>

                <!-- Password -->
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
                    <h3 class="mb-1 font-semibold text-slate-800">Ganti Password</h3>
                    <p class="mb-4 text-xs text-slate-400">Kosongkan bila tidak ingin mengubah password.</p>

                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Password Saat Ini</label>
                            <input v-model="form.current_password" type="password" autocomplete="current-password" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                            <p v-if="form.errors.current_password" class="mt-1 text-xs text-danger-600">{{ form.errors.current_password }}</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-slate-700">Password Baru</label>
                                <input v-model="form.password" type="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                                <p v-if="form.errors.password" class="mt-1 text-xs text-danger-600">{{ form.errors.password }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-slate-700">Konfirmasi</label>
                                <input v-model="form.password_confirmation" type="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" :disabled="form.processing" class="h-12 w-full rounded-xl bg-brand-600 font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700 disabled:opacity-50">
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Profil' }}
                </button>
            </form>
        </div>
    </AdminLayout>
</template>
