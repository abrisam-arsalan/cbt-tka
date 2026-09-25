<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ImportPanel from '@/Admin/Shared/ImportPanel.vue';

defineProps({
    title: String,
    classes: { type: Array, default: () => [] },
    importErrors: { type: Array, default: () => [] },
});

const btnPrimary = 'bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow-sm transition duration-150 flex items-center justify-center text-center w-full sm:w-auto';
const btnPrimarySm = 'bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';
const btnDangerSm = 'bg-red-600 hover:bg-red-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';

const destroy = (classItem) => {
    if (!confirm(`Hapus kelas "${classItem.name}"?`)) return;
    router.delete(route('admin.classes.destroy', classItem.id));
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-bold text-gray-900">Manajemen Kelas</h2>
            <Link :href="route('admin.classes.create')" :class="btnPrimary">
                + Tambah Kelas
            </Link>
        </div>

        <ImportPanel
            template-route="admin.classes.template"
            import-route="admin.classes.import"
            :columns="['nama', 'tingkat', 'tahun_ajaran', 'deskripsi', 'aktif']"
            note="Kolom &quot;nama&quot; wajib. Kelas dengan nama + tahun ajaran yang sama tidak akan diduplikasi."
            :errors="importErrors"
        />

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Tingkat</th>
                        <th class="px-4 py-3">Tahun Ajaran</th>
                        <th class="px-4 py-3">Siswa</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="classItem in classes" :key="classItem.id" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ classItem.name }}</td>
                        <td class="px-4 py-3">{{ classItem.grade ?? '-' }}</td>
                        <td class="px-4 py-3">{{ classItem.academic_year ?? '-' }}</td>
                        <td class="px-4 py-3">{{ classItem.students_count }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="classItem.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ classItem.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <Link :href="route('admin.classes.edit', classItem.id)" :class="btnPrimarySm">Edit</Link>
                                <button :class="btnDangerSm" @click="destroy(classItem)">Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="classes.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada kelas.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
