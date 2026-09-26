<script setup>
import { Head } from '@inertiajs/vue3';
import StudentLayout from '@/Layouts/StudentLayout.vue';

const props = defineProps({
    title: String,
    attempt: Object,
    details: { type: Array, default: () => [] },
});

const selectedOptionIds = (detail) => {
    if (!detail.answer_payload) return [];
    if (detail.type === 'pg' && detail.answer_payload.option_id != null) {
        return [detail.answer_payload.option_id];
    }
    if (detail.type === 'pgk' && Array.isArray(detail.answer_payload.option_ids)) {
        return detail.answer_payload.option_ids;
    }
    if (detail.type === 'boolean' && detail.answer_payload.value != null) {
        const target = detail.answer_payload.value ? 'true' : 'false';
        return detail.options.filter((o) => o.label === target).map((o) => o.id);
    }
    return [];
};

const optionClass = (detail, option) => {
    const selected = selectedOptionIds(detail).includes(option.id);
    if (option.is_correct) return 'border-success-500 bg-success-50';
    if (selected) return 'border-danger-500 bg-danger-50';
    return 'border-slate-200';
};

const statusOf = (detail) => {
    if (!detail.answered) return { label: 'Tidak dijawab', cls: 'bg-slate-200 text-slate-600' };
    if (detail.answer_payload && detail.answer_payload.late_sync_flag) {
        return { label: 'Terlambat sinkron', cls: 'bg-warning-100 text-warning-700' };
    }
    if (detail.is_correct) return { label: 'Benar', cls: 'bg-success-100 text-success-700' };
    return { label: 'Salah', cls: 'bg-danger-100 text-danger-700' };
};
</script>

<template>
    <StudentLayout>
        <Head :title="title" />

        <div class="mx-auto max-w-2xl">
            <!-- Ringkasan skor -->
            <div class="mb-4 rounded-2xl bg-white p-6 text-center shadow-sm">
                <p class="mb-1 text-sm text-slate-500">{{ attempt.exam_title }}</p>
                <div class="mb-3 text-5xl font-bold text-brand-700">{{ attempt.score ?? '-' }}</div>
                <div class="grid grid-cols-3 gap-3 text-sm">
                    <div class="rounded-xl bg-success-50 p-3">
                        <div class="text-xl font-bold text-success-600">{{ attempt.correct_count }}</div>
                        <div class="text-xs text-slate-500">Benar</div>
                    </div>
                    <div class="rounded-xl bg-danger-50 p-3">
                        <div class="text-xl font-bold text-danger-600">{{ attempt.wrong_count }}</div>
                        <div class="text-xs text-slate-500">Salah</div>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <div class="text-xl font-bold text-slate-600">{{ attempt.unanswered_count }}</div>
                        <div class="text-xs text-slate-500">Kosong</div>
                    </div>
                </div>
                <p v-if="attempt.submit_reason_label" class="mt-3 text-xs text-slate-400">
                    {{ attempt.submit_reason_label }}
                </p>
            </div>

            <!-- Pembahasan per soal -->
            <div class="space-y-3">
                <div v-for="detail in details" :key="detail.number" class="rounded-xl bg-white p-4 shadow-sm">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-semibold text-slate-700">Soal {{ detail.number }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs" :class="statusOf(detail).cls">
                            {{ statusOf(detail).label }}
                        </span>
                    </div>

                    <img v-if="detail.media_url" :src="detail.media_url" alt="Gambar soal" class="mb-2 max-h-56 rounded-lg" />
                    <p class="mb-3 whitespace-pre-line text-sm text-slate-800">{{ detail.question_text }}</p>

                    <!-- PG / PGK / Boolean -->
                    <div v-if="detail.options.length > 0" class="space-y-1.5">
                        <div
                            v-for="option in detail.options"
                            :key="option.id"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                            :class="optionClass(detail, option)"
                        >
                            <span class="shrink-0">
                                {{ option.is_correct ? '✓' : (selectedOptionIds(detail).includes(option.id) ? '✗' : '') }}
                            </span>
                            <span>{{ option.label ? option.label + '. ' : '' }}{{ option.text }}</span>
                        </div>
                    </div>

                    <!-- Matching -->
                    <div v-else-if="detail.pairs.length > 0" class="space-y-1.5 text-sm">
                        <div v-for="pair in detail.pairs" :key="pair.id" class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                            <span>{{ pair.left }} → {{ pair.right }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </StudentLayout>
</template>
