<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);

const sidebarOpen = ref(false);
const showDropdown = ref(false);
const dropdownRef = ref(null);

// Inisial nama untuk avatar.
const initials = computed(() => {
    const name = user.value?.name?.trim() ?? '';
    if (name === '') return '?';

    const parts = name.split(/\s+/).filter(Boolean);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();

    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
});

const icons = {
    'chart-bar': 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
    'academic-cap': 'M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5',
    'users': 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    'book-open': 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
    'arrow-up-tray': 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5',
    'clipboard-document-list': 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z',
    'ticket': 'M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z',
    'user-group': 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
    'signal': 'M9.348 14.651a3.75 3.75 0 010-5.303m5.304 0a3.75 3.75 0 010 5.303m-7.425 2.122a6.75 6.75 0 010-9.546m9.546 0a6.75 6.75 0 010 9.546M5.106 18.894c-3.808-3.807-3.808-9.98 0-13.788m13.788 0c3.808 3.807 3.808 9.98 0 13.788M12 12h.008v.008H12V12z',
    'chart-pie': 'M2.25 12.75c0 5.385 4.365 9.75 9.75 9.75 5.385 0 9.75-4.365 9.75-9.75 0-1.036-.162-2.034-.458-2.98a9.75 9.75 0 01-3.262 2.018L12 6.72v.001A7.5 7.5 0 002.25 12.75zM12.75 3.63A9.75 9.75 0 0121 11.25h-7.5V3.63z',
    'cog-6-tooth': 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
    'document-text': 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
};

// Struktur menu sidebar.
// activePrefix: menu aktif bila route saat ini dimulai dengan prefix ini
// (exact-ish matching — tidak ada parent matching yang menumpuk).
const menu = [
    { type: 'item', label: 'Dashboard', route: 'admin.dashboard', icon: 'chart-bar', activePrefix: null },
    { type: 'header', label: 'Persiapan' },
    { type: 'item', label: 'Kelas', route: 'admin.classes.index', icon: 'academic-cap', activePrefix: 'admin.classes.' },
    { type: 'item', label: 'Siswa', route: 'admin.users.index', icon: 'users', activePrefix: 'admin.users.' },
    { type: 'header', label: 'Konten' },
    { type: 'item', label: 'Bank Soal', route: 'admin.questions.index', icon: 'book-open', activePrefix: 'admin.questions.' },
    { type: 'header', label: 'Impor & Template' },
    {
        type: 'submenu',
        label: 'Impor Soal',
        icon: 'arrow-up-tray',
        activePrefixes: ['admin.import.', 'admin.templates.'],
        children: [
            { label: 'Impor Data Soal', route: 'admin.import.index' },
            { label: 'Template & Format', route: 'admin.templates.index' },
        ],
    },
    { type: 'header', label: 'Pelaksanaan' },
    { type: 'item', label: 'Ujian', route: 'admin.exams.index', icon: 'clipboard-document-list', activePrefix: 'admin.exams.' },
    { type: 'item', label: 'Cetak Kartu', route: 'admin.cards.index', icon: 'ticket', activePrefix: 'admin.cards.' },
    { type: 'item', label: 'Peserta', route: 'admin.participants.index', icon: 'user-group', activePrefix: 'admin.participants.' },
    { type: 'header', label: 'Monitoring & Hasil' },
    { type: 'item', label: 'Monitoring Ujian', route: 'admin.monitoring.index', icon: 'signal', activePrefix: 'admin.monitoring.' },
    { type: 'item', label: 'Hasil Ujian', route: 'admin.results.index', icon: 'chart-pie', activePrefix: 'admin.results.' },
    { type: 'header', label: 'Sistem' },
    { type: 'item', label: 'Pengaturan', route: 'admin.settings.index', icon: 'cog-6-tooth', activePrefix: 'admin.settings.' },
    { type: 'item', label: 'Log Aktivitas', route: 'admin.logs.index', icon: 'document-text', activePrefix: 'admin.logs.' },
];

const isActive = (item) => {
    const current = route().current();
    if (!current) return false;

    if (item.type === 'submenu') {
        return item.activePrefixes.some((prefix) => current.startsWith(prefix));
    }

    if (item.activePrefix) {
        return current.startsWith(item.activePrefix);
    }

    return current === item.route;
};

// State submenu (expand/collapse).
const openSubmenu = ref(null);
const toggleSubmenu = (label) => {
    openSubmenu.value = openSubmenu.value === label ? null : label;
};
const isSubmenuOpen = (item) => openSubmenu.value === item.label || isActive(item);

const isChildActive = (child) => route().current() === child.route;

// Tutup dropdown user saat klik di luar.
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        showDropdown.value = false;
    }
};

onMounted(() => document.addEventListener('click', handleClickOutside));
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside));
</script>

<template>
    <div class="flex min-h-screen bg-gray-50">
        <!-- Overlay mobile -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-black/50 lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 transform flex-col border-r border-gray-200 bg-white transition-transform lg:relative lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex items-center gap-3 border-b border-gray-200 px-5 py-4">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-sm font-bold text-white">
                    CBT
                </div>
                <div>
                    <p class="text-sm font-bold leading-tight text-gray-900">CBT TKA Sekolah</p>
                    <p class="text-xs text-gray-500">Panel Admin</p>
                </div>
            </div>

            <nav class="flex-1 space-y-0.5 overflow-y-auto px-2 py-2">
                <template v-for="(item, index) in menu" :key="index">
                    <!-- Group header -->
                    <p
                        v-if="item.type === 'header'"
                        class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-4 py-3 mt-4"
                    >
                        {{ item.label }}
                    </p>

                    <!-- Submenu (expandable) -->
                    <div v-else-if="item.type === 'submenu'">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-lg px-4 py-2.5 text-sm font-medium transition-colors"
                            :class="isActive(item) ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'"
                            @click="toggleSubmenu(item.label)"
                        >
                            <span class="flex items-center gap-3">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon]" />
                                </svg>
                                {{ item.label }}
                            </span>
                            <svg
                                class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                                :class="isSubmenuOpen(item) ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <Transition
                            enter-active-class="transition ease-out duration-150"
                            enter-from-class="opacity-0 -translate-y-1"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition ease-in duration-100"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 -translate-y-1"
                        >
                            <div v-if="isSubmenuOpen(item)" class="mt-1 space-y-0.5 pl-4">
                                <Link
                                    v-for="child in item.children"
                                    :key="child.route"
                                    :href="route(child.route)"
                                    class="flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                                    :class="isChildActive(child) ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'"
                                    @click="sidebarOpen = false"
                                >
                                    <span class="mr-3 h-1 w-1 rounded-full" :class="isChildActive(child) ? 'bg-blue-600' : 'bg-gray-300'"></span>
                                    {{ child.label }}
                                </Link>
                            </div>
                        </Transition>
                    </div>

                    <!-- Menu item biasa -->
                    <Link
                        v-else
                        :href="route(item.route)"
                        class="flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors"
                        :class="isActive(item) ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'"
                        @click="sidebarOpen = false"
                    >
                        <svg class="mr-3 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon]" />
                        </svg>
                        {{ item.label }}
                    </Link>
                </template>
            </nav>
        </aside>

        <!-- Main content -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Topbar -->
            <header class="sticky top-0 z-30 flex items-center justify-between border-b border-gray-200 bg-white px-4 py-2.5">
                <button
                    class="flex h-10 w-10 items-center justify-center rounded-lg text-gray-700 hover:bg-gray-100 lg:hidden"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <!-- User dropdown -->
                <div class="relative ml-auto" ref="dropdownRef">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 transition-colors hover:bg-gray-100"
                        @click="showDropdown = !showDropdown"
                    >
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">
                            {{ initials }}
                        </span>
                        <span class="hidden text-sm font-medium text-gray-700 sm:block">{{ user?.name }}</span>
                        <svg
                            class="h-4 w-4 text-gray-400 transition-transform"
                            :class="showDropdown ? 'rotate-180' : ''"
                            fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <Transition
                        enter-active-class="transition ease-out duration-150"
                        enter-from-class="transform opacity-0 scale-95"
                        enter-to-class="transform opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-100"
                        leave-from-class="transform opacity-100 scale-100"
                        leave-to-class="transform opacity-0 scale-95"
                    >
                        <div
                            v-if="showDropdown"
                            class="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-xl border border-gray-200 bg-white py-1 shadow-lg"
                        >
                            <div class="border-b border-gray-100 px-4 py-3">
                                <p class="truncate text-sm font-semibold text-gray-800">{{ user?.name }}</p>
                                <p class="truncate text-xs text-gray-500">{{ user?.username }}</p>
                            </div>

                            <Link
                                :href="route('admin.settings.index')"
                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50"
                                @click="showDropdown = false"
                            >
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Pengaturan Profil
                            </Link>

                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50"
                                @click="showDropdown = false"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Keluar
                            </Link>
                        </div>
                    </Transition>
                </div>
            </header>

            <!-- Flash -->
            <div v-if="flash?.success || flash?.error || flash?.warning" class="px-4 pt-3">
                <div v-if="flash?.success" class="rounded-lg bg-success-100 px-4 py-2 text-sm text-success-700">{{ flash.success }}</div>
                <div v-if="flash?.error" class="rounded-lg bg-danger-100 px-4 py-2 text-sm text-danger-700">{{ flash.error }}</div>
                <div v-if="flash?.warning" class="rounded-lg bg-warning-100 px-4 py-2 text-sm text-warning-700">{{ flash.warning }}</div>
            </div>

            <!-- Konten halaman -->
            <main class="flex-1 p-4 lg:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
