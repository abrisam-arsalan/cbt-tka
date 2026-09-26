<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ title: String });

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

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

    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-brand-50 via-orange-50 to-amber-100 px-4 py-10">
        <!-- Dekorasi latar -->
        <div class="pointer-events-none absolute -top-24 -right-24 h-72 w-72 rounded-full bg-brand-300/30 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-20 h-80 w-80 rounded-full bg-amber-300/30 blur-3xl"></div>

        <div class="relative w-full max-w-md">
            <!-- Brand header -->
            <div class="mb-8 flex flex-col items-center text-center">
                <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-2xl bg-white p-2 shadow-lg shadow-brand-600/20 ring-1 ring-slate-100">
                    <img src="/images/logo.png" alt="Logo" class="h-full w-full object-contain" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Panglima CBT</h1>
                <p class="mt-1 text-sm text-slate-500">Masuk memakai akun pada kartu ujian — bisa dari HP juga</p>
            </div>

            <!-- Card login -->
            <div class="rounded-3xl border border-white/60 bg-white/80 p-8 shadow-xl shadow-brand-900/5 backdrop-blur-sm sm:p-10">
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">Username (NISN)</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                            </span>
                            <input
                                v-model="form.username"
                                type="text"
                                required
                                autocomplete="username"
                                class="block w-full rounded-xl border border-slate-200 bg-slate-50/50 py-3 pl-11 pr-4 text-base text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20 focus:outline-none"
                                :class="{ 'border-danger-500': form.errors.username }"
                                placeholder="mis. 0011223344"
                            />
                        </div>
                        <p v-if="form.errors.username" class="mt-1.5 text-sm text-danger-600">{{ form.errors.username }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">PIN</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                            </span>
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                inputmode="numeric"
                                autocomplete="current-password"
                                class="block w-full rounded-xl border border-slate-200 bg-slate-50/50 py-3 pl-11 pr-11 text-base text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20 focus:outline-none"
                                :class="{ 'border-danger-500': form.errors.password }"
                                placeholder="Masukkan PIN dari kartu ujian"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600"
                                @click="showPassword = !showPassword"
                            >
                                <svg v-if="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A9.047 9.047 0 003 12c0 4.97 3.03 8.5 9 8.5a9.7 9.7 0 003.4-1.022M6.228 6.228A9.047 9.047 0 0112 3c6.97 0 9 4.03 9 9a9.047 9.047 0 01-1.022 4.128M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1.5 text-sm text-danger-600">{{ form.errors.password }}</p>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2.5 select-none">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="h-4.5 w-4.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30"
                        />
                        <span class="text-sm text-slate-600">Ingat saya</span>
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex h-12 w-full items-center justify-center rounded-xl bg-brand-600 text-base font-semibold text-white shadow-lg shadow-brand-600/25 transition-all hover:bg-brand-700 hover:shadow-brand-700/30 active:scale-[0.99] disabled:opacity-60"
                    >
                        <svg v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>{{ form.processing ? 'Memproses...' : 'Masuk' }}</span>
                    </button>
                </form>
            </div>

            <p class="mt-8 text-center text-xs text-slate-400">
                &copy; {{ new Date().getFullYear() }} Panglima CBT. Untuk keperluan pendidikan.
            </p>
        </div>
    </div>
</template>
