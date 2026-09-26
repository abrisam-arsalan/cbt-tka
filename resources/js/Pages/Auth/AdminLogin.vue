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
    form.post(route('admin.login.attempt'), {
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

    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-slate-800 via-slate-900 to-slate-950 px-4 py-10">
        <!-- Dekorasi latar -->
        <div class="pointer-events-none absolute -top-24 -right-24 h-72 w-72 rounded-full bg-brand-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-20 h-80 w-80 rounded-full bg-brand-400/10 blur-3xl"></div>

        <div class="relative w-full max-w-md">
            <!-- Brand header -->
            <div class="mb-8 flex flex-col items-center text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 p-2 ring-1 ring-white/20">
                    <img src="/images/logo.png" alt="Logo" class="h-full w-full object-contain" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-white">Panglima CBT</h1>
                <p class="mt-1 text-sm font-medium text-brand-300">Area Administrator — Masuk Panitia Ujian</p>
            </div>

            <!-- Card login -->
            <div class="rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl backdrop-blur-sm sm:p-10">
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-200">Username Admin</label>
                        <input
                            v-model="form.username"
                            type="text"
                            required
                            autocomplete="username"
                            class="block w-full rounded-xl border border-white/10 bg-white/10 py-3 px-4 text-base text-white shadow-sm transition placeholder:text-slate-400 focus:border-brand-400 focus:bg-white/15 focus:ring-2 focus:ring-brand-400/30 focus:outline-none"
                            :class="{ 'border-danger-500': form.errors.username }"
                            placeholder="Username"
                        />
                        <p v-if="form.errors.username" class="mt-1.5 text-sm text-danger-400">{{ form.errors.username }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-200">Password</label>
                        <div class="relative">
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                class="block w-full rounded-xl border border-white/10 bg-white/10 py-3 pl-4 pr-11 text-base text-white shadow-sm transition placeholder:text-slate-400 focus:border-brand-400 focus:bg-white/15 focus:ring-2 focus:ring-brand-400/30 focus:outline-none"
                                :class="{ 'border-danger-500': form.errors.password }"
                                placeholder="Password"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-200"
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
                        <p v-if="form.errors.password" class="mt-1.5 text-sm text-danger-400">{{ form.errors.password }}</p>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2.5 select-none">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="h-4.5 w-4.5 rounded border-white/20 bg-white/10 text-brand-500 focus:ring-brand-400/30"
                        />
                        <span class="text-sm text-slate-300">Ingat saya</span>
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex h-12 w-full items-center justify-center rounded-xl bg-brand-600 text-base font-semibold text-white shadow-lg shadow-brand-900/40 transition-all hover:bg-brand-500 active:scale-[0.99] disabled:opacity-60"
                    >
                        <svg v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>{{ form.processing ? 'Memproses...' : 'Masuk Admin' }}</span>
                    </button>
                </form>
            </div>

            <p class="mt-8 text-center text-xs text-slate-500">
                Akses terbatas untuk panitia ujian. Aktivitas dicatat dalam audit log.
            </p>
        </div>
    </div>
</template>
