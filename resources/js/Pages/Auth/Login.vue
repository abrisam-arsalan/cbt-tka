<script setup>
import { Head, useForm } from '@inertiajs/vue3';

defineProps({ title: String });

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login.attempt'), {
        onFinish: () => {
            if (!form.hasErrors) {
                form.reset('password');
            }
        },
    });
};
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md">
            <!-- Card login -->
            <div class="rounded-2xl bg-white p-8 shadow-xl sm:p-10">
                <div class="mb-8 text-center">
                    <h1 class="text-2xl font-bold text-brand-700">CBT TKA Sekolah</h1>
                    <p class="mt-2 text-sm text-slate-500">Silakan masuk dengan akun Anda</p>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">Username</label>
                        <input
                            v-model="form.username"
                            type="text"
                            required
                            autocomplete="username"
                            class="block w-full rounded-xl border border-slate-300 px-4 py-3 text-base shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                            :class="{ 'border-danger-500': form.errors.username }"
                            placeholder="Masukkan username"
                        />
                        <p v-if="form.errors.username" class="mt-1 text-sm text-danger-600">{{ form.errors.username }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">Password</label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="block w-full rounded-xl border border-slate-300 px-4 py-3 text-base shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                            :class="{ 'border-danger-500': form.errors.password }"
                            placeholder="Masukkan password"
                        />
                        <p v-if="form.errors.password" class="mt-1 text-sm text-danger-600">{{ form.errors.password }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex h-12 w-full items-center justify-center rounded-xl bg-brand-600 font-semibold text-white transition-colors hover:bg-brand-700 disabled:opacity-50"
                    >
                        <span v-if="form.processing">Memproses...</span>
                        <span v-else>Masuk</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
