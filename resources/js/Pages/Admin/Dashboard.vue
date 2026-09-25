<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    stats: Object,
    activeExams: { type: Array, default: () => [] },
    recentLogs: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const firstName = computed(() => (user.value?.name || 'Admin').trim().split(/\s+/)[0]);

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 11) return 'Selamat pagi';
    if (h < 15) return 'Selamat siang';
    if (h < 18) return 'Selamat sore';
    return 'Selamat malam';
});

const today = computed(() =>
    new Date().toLocaleDateString('id-ID', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    }),
);

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
};

// Kartu statistik dengan aksen warna.
const statCards = computed(() => [
    { label: 'Total User', value: props.stats.users, icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z', tone: 'text-brand-600 bg-brand-50' },
    { label: 'Kelas', value: props.stats.classes, icon: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75', tone: 'text-sky-600 bg-sky-50' },
    { label: 'Total Ujian', value: props.stats.exams, icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z', tone: 'text-violet-600 bg-violet-50' },
    { label: 'Ujian Aktif', value: props.stats.active_exams, icon: 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z', tone: 'text-emerald-600 bg-emerald-50' },
    { label: 'Total Attempt', value: props.stats.attempts, icon: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z', tone: 'text-amber-600 bg-amber-50' },
]);

// Pintasan aksi cepat ala Figma (grid ikon).
const quickActions = [
    { label: 'Buat Ujian', route: 'admin.exams.create', icon: 'M12 4.5v15m7.5-7.5h-15', tone: 'bg-brand-500' },
    { label: 'Bank Soal', route: 'admin.bank.index', icon: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25', tone: 'bg-violet-500' },
    { label: 'Impor Soal', route: 'admin.bank.create', icon: 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5', tone: 'bg-sky-500' },
    { label: 'Monitoring', route: 'admin.monitoring.index', icon: 'M9.348 14.651a3.75 3.75 0 010-5.303m5.304 0a3.75 3.75 0 010 5.303m-7.425 2.122a6.75 6.75 0 010-9.546m9.546 0a6.75 6.75 0 010 9.546M5.106 18.894c-3.808-3.807-3.808-9.98 0-13.788m13.788 0c3.808 3.807 3.808 9.98 0 13.788M12 12h.008v.008H12V12z', tone: 'bg-emerald-500' },
    { label: 'Cetak Kartu', route: 'admin.cards.index', icon: 'M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z', tone: 'bg-rose-500' },
    { label: 'Hasil Ujian', route: 'admin.results.index', icon: 'M2.25 12.75c0 5.385 4.365 9.75 9.75 9.75 5.385 0 9.75-4.365 9.75-9.75 0-1.036-.162-2.034-.458-2.98a9.75 9.75 0 01-3.262 2.018L12 6.72v.001A7.5 7.5 0 002.25 12.75zM12.75 3.63A9.75 9.75 0 0121 11.25h-7.5V3.63z', tone: 'bg-amber-500' },
    { label: 'Kelola Siswa', route: 'admin.users.index', icon: 'M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z', tone: 'bg-teal-500' },
    { label: 'Pengaturan', route: 'admin.settings.index', icon: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28zM15 12a3 3 0 11-6 0 3 3 0 016 0z', tone: 'bg-slate-500' },
];
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <!-- Greeting -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">{{ greeting }}, {{ firstName }} 👋</h2>
            <p class="mt-1 text-sm text-slate-500">{{ today }} · Panel kendali Panglima CBT</p>
        </div>

        <!-- Banner -->
        <div class="mb-6 overflow-hidden rounded-2xl bg-gradient-to-r from-brand-500 to-orange-600 p-6 text-white shadow-lg shadow-brand-500/20 sm:p-7">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/70">Ringkasan Hari Ini</p>
                    <h3 class="mt-1 text-xl font-bold">
                        {{ stats.active_exams > 0 ? `${stats.active_exams} ujian sedang berlangsung` : 'Tidak ada ujian aktif' }}
                    </h3>
                    <p class="mt-1 text-sm text-white/80">
                        {{ stats.attempts }} total attempt tercatat · Pantau pelaksanaan ujian secara real-time.
                    </p>
                </div>
                <Link
                    :href="route('admin.monitoring.index')"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white/15 px-5 py-3 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/25"
                >
                    Buka Monitoring
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </Link>
            </div>
        </div>

        <!-- Statistik -->
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div v-for="card in statCards" :key="card.label" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
                <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl" :class="card.tone">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="card.icon" />
                    </svg>
                </div>
                <div class="text-2xl font-bold text-slate-900">{{ card.value }}</div>
                <div class="text-xs text-slate-500">{{ card.label }}</div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="mb-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wider text-slate-400">Aksi Cepat</h3>
            <div class="grid grid-cols-4 gap-3 sm:grid-cols-8">
                <Link
                    v-for="action in quickActions"
                    :key="action.route"
                    :href="route(action.route)"
                    class="group flex flex-col items-center gap-2 rounded-xl p-2 transition hover:bg-slate-50"
                >
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl text-white shadow-sm transition group-hover:scale-105" :class="action.tone">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="action.icon" />
                        </svg>
                    </span>
                    <span class="text-center text-[11px] font-medium leading-tight text-slate-600">{{ action.label }}</span>
                </Link>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Ujian aktif -->
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-900">Ujian Aktif</h3>
                    <Link :href="route('admin.exams.index')" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Lihat semua</Link>
                </div>
                <div v-if="activeExams.length === 0" class="rounded-xl bg-slate-50 py-8 text-center text-sm text-slate-500">
                    Tidak ada ujian yang sedang aktif.
                </div>
                <div v-else class="space-y-2">
                    <Link
                        v-for="exam in activeExams"
                        :key="exam.id"
                        :href="route('admin.exams.monitoring.index', exam.id)"
                        class="flex items-center justify-between rounded-xl border border-slate-100 p-3 transition hover:border-brand-200 hover:bg-brand-50/40"
                    >
                        <div>
                            <div class="text-sm font-medium text-slate-800">{{ exam.title }}</div>
                            <div class="text-xs text-slate-400">{{ exam.active_attempts_count }} siswa sedang mengerjakan</div>
                        </div>
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </span>
                    </Link>
                </div>
            </div>

            <!-- Log terbaru -->
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-900">Aktivitas Terbaru</h3>
                    <Link :href="route('admin.logs.index')" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Log penuh</Link>
                </div>
                <div v-if="recentLogs.length === 0" class="rounded-xl bg-slate-50 py-8 text-center text-sm text-slate-500">
                    Belum ada aktivitas.
                </div>
                <div v-else class="space-y-3">
                    <div v-for="(log, index) in recentLogs" :key="index" class="flex items-start gap-3 text-sm">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-400"></span>
                        <div class="min-w-0">
                            <p class="truncate text-slate-700">{{ log.description || log.action }}</p>
                            <p class="text-xs text-slate-400">{{ log.actor }} · {{ formatDate(log.created_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
