<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ImportPanel from '@/Admin/Shared/ImportPanel.vue';

defineProps({
    title: String,
    users: Object,
    filters: Object,
    classOptions: { type: Array, default: () => [] },
    importErrors: { type: Array, default: () => [] },
});

const btnPrimarySm = 'bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';
const btnDangerSm = 'bg-red-600 hover:bg-red-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';

const filterForm = useForm({
    search: props.filters?.search ?? '',
    class_id: props.filters?.class_id ?? '',
});

const submitFilter = () => {
    filterForm.get(route('admin.users.index'), { preserveState: true, replace: true });
};

const destroy = (user) => {
    if (!confirm(`Hapus siswa "${user.name}"?`)) return;
    router.delete(route('admin.users.destroy', user.id));
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Data Siswa</h2>
                <p class="text-sm text-slate-500">Kelola akun peserta didik.</p>
            </div>
            <Link :href="route('admin.users.create')" class="flex h-11 items-center justify-center rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700">
                + Tambah Siswa
            </Link>
        </div>

        <!-- Filter -->
        <form @submit.prevent="submitFilter" class="mb-4 flex flex-wrap gap-2">
            <select v-model="filterForm.class_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua kelas</option>
                <option v-for="c in classOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
            </select>
            <input
                v-model="filterForm.search"
                type="text"
                placeholder="Cari nama / username..."
                class="min-w-[12rem] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />
            <button type="submit" :class="btnPrimarySm">Filter</button>
        </form>

        <ImportPanel
            template-route="admin.users.template"
            import-route="admin.users.import"
            :columns="['username', 'nama', 'nisn', 'email', 'kelas', 'password', 'aktif']"
            note="Kolom &quot;username&quot; dan &quot;nama&quot; wajib. Kolom &quot;kelas&quot; diisi nama kelas yang sudah ada. Jika &quot;password&quot; kosong, memakai default &quot;siswa123&quot;. Semua akun dibuat sebagai Siswa."
            :errors="importErrors"
        />

        <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3">NISN</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users.data" :key="user.id" class="border-b border-slate-100 last:border-0 hover:bg-slate-50/60">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ user.name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ user.username }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ user.nisn ?? '—' }}</td>
                        <td class="px-4 py-3">{{ user.class_name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <Link :href="route('admin.users.edit', user.id)" :class="btnPrimarySm">Edit</Link>
                                <button :class="btnDangerSm" @click="destroy(user)">Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada siswa.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="users.links && users.links.length > 3" class="mt-4 flex justify-center gap-1">
            <Link
                v-for="link in users.links"
                :key="link.label"
                :href="link.url || '#'"
                :class="link.active ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'"
                class="rounded-lg px-3 py-1.5 text-sm"
                v-html="link.label"
            />
        </div>
    </AdminLayout>
</template>
