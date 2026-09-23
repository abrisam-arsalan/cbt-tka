<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

const props = defineProps({
    title: String,
    exam: Object,
    participant: Object,
    attempt: { type: Object, default: null },
    questions: { type: Array, default: () => [] },
    answers: { type: Object, default: () => ({}) },
    canStart: Boolean,
});

const page = usePage();
const clientConfig = computed(() => page.props.clientConfig);
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ------------------------------------------------------------------
// Navigasi soal
// ------------------------------------------------------------------
const currentIndex = ref(0);
const currentQuestion = computed(() => props.questions[currentIndex.value] ?? null);
const totalQuestions = computed(() => props.questions.length);
const gridOpen = ref(false);

const goTo = (index) => {
    if (index < 0 || index >= totalQuestions.value) return;
    currentIndex.value = index;
    gridOpen.value = false;
    window.scrollTo({ top: 0 });
};

// ------------------------------------------------------------------
// Status jawaban lokal: blank | saving | synced | queued | failed
// ------------------------------------------------------------------
const answerState = reactive({});
let maxSeq = 0;

for (const [questionId, answer] of Object.entries(props.answers ?? {})) {
    answerState[questionId] = {
        payload: answer.answer_payload,
        seq: answer.client_seq,
        status: 'synced',
    };
    maxSeq = Math.max(maxSeq, answer.client_seq);
}

const stateOf = (questionId) => answerState[questionId] ?? { payload: null, seq: 0, status: 'blank' };
const payloadOf = (questionId) => stateOf(questionId).payload;

function isFilled(question, payload) {
    if (!payload) return false;
    if (question.type === 'pg') return payload.option_id != null;
    if (question.type === 'pgk') return Array.isArray(payload.option_ids) && payload.option_ids.length > 0;
    if (question.type === 'boolean') return payload.value === true || payload.value === false;
    if (question.type === 'matching') {
        return payload.mapping && Object.keys(payload.mapping).length === question.pairs.length;
    }
    return false;
}

const answeredCount = computed(() => props.questions.filter((q) => isFilled(q, payloadOf(q.id))).length);
const progressPercent = computed(() => totalQuestions.value === 0 ? 0 : Math.round((answeredCount.value / totalQuestions.value) * 100));

// ------------------------------------------------------------------
// Outbox offline (localStorage)
// ------------------------------------------------------------------
const outboxKey = computed(() => `cbt_outbox_${props.attempt?.id ?? 0}`);
const outbox = ref([]);

const loadOutbox = () => {
    if (!props.attempt) return;
    try {
        outbox.value = JSON.parse(localStorage.getItem(outboxKey.value) ?? '[]');
    } catch {
        outbox.value = [];
    }
    for (const item of outbox.value) {
        const local = answerState[item.question_id];
        if (!local || item.client_seq > local.seq) {
            answerState[item.question_id] = { payload: item.answer_payload, seq: item.client_seq, status: 'queued' };
            maxSeq = Math.max(maxSeq, item.client_seq);
        }
    }
};

const persistOutbox = () => {
    try {
        localStorage.setItem(outboxKey.value, JSON.stringify(outbox.value));
    } catch {
        // localStorage penuh / private mode: outbox bertahan di memori sesi ini.
    }
};

// ------------------------------------------------------------------
// Sinkronisasi
// ------------------------------------------------------------------
const online = ref(navigator.onLine);
const globalSyncStatus = ref('online');

const markOnline = () => {
    online.value = true;
    globalSyncStatus.value = outbox.value.length > 0 ? 'saving' : 'online';
    scheduleFlush(300);
};
const markOffline = () => {
    online.value = false;
    globalSyncStatus.value = 'offline';
};

const syncLabel = computed(() => {
    if (!online.value) return 'Offline';
    if (globalSyncStatus.value === 'saving') return 'Menyimpan';
    if (globalSyncStatus.value === 'failed') return 'Gagal';
    if (globalSyncStatus.value === 'synced') return 'Tersinkron';
    return 'Online';
});

const syncDotClass = computed(() => {
    if (!online.value) return 'bg-slate-400';
    if (globalSyncStatus.value === 'saving') return 'bg-warning-500 animate-pulse';
    if (globalSyncStatus.value === 'failed') return 'bg-danger-500';
    return 'bg-success-500';
});

let saveTimer = null;
let flushing = false;

function setAnswer(question, payload) {
    if (!props.attempt) return;

    maxSeq += 1;
    const seq = maxSeq;
    const key = `${props.attempt.id}-${question.id}-${seq}-${Math.random().toString(36).slice(2, 8)}`;

    answerState[question.id] = { payload, seq, status: online.value ? 'saving' : 'queued' };

    // Satu baris outbox per soal: versi terbaru menimpa versi lama.
    outbox.value = outbox.value.filter((i) => i.question_id !== question.id);
    outbox.value.push({ question_id: question.id, idempotency_key: key, client_seq: seq, answer_payload: payload });
    persistOutbox();

    globalSyncStatus.value = online.value ? 'saving' : 'offline';
    scheduleFlush(clientConfig.value.autosaveDebounceMs ?? 1000);
}

function scheduleFlush(delayMs) {
    if (!props.attempt) return;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(flushOutbox, delayMs);
}

async function flushOutbox() {
    if (!props.attempt || flushing || outbox.value.length === 0) return;
    if (!navigator.onLine) {
        globalSyncStatus.value = 'offline';
        return;
    }

    flushing = true;
    globalSyncStatus.value = 'saving';

    const batchSize = clientConfig.value.maxSyncBatch ?? 50;
    const batch = outbox.value.slice(0, batchSize);

    try {
        const response = await fetch(route('student.exam.sync', props.exam.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ items: batch }),
        });

        applySyncResult(batch, await response.json());
    } catch {
        for (const item of batch) {
            if (answerState[item.question_id]) answerState[item.question_id].status = 'queued';
        }
        globalSyncStatus.value = navigator.onLine ? 'failed' : 'offline';
    } finally {
        flushing = false;
        if (outbox.value.length > 0 && navigator.onLine) {
            scheduleFlush(clientConfig.value.outboxFlushIntervalMs ?? 3000);
        }
    }
}

function applySyncResult(batch, data) {
    const resultMap = new Map((data.items ?? []).map((item) => [item.question_id, item]));
    const retryableKeys = new Set(data.retryable_keys ?? []);
    const remain = [];

    for (const item of batch) {
        const result = resultMap.get(item.question_id);

        if (result === undefined) {
            remain.push(item);
            continue;
        }

        if (['accepted', 'duplicate', 'stale'].includes(result.status)) {
            if (answerState[item.question_id]) answerState[item.question_id].status = 'synced';
            continue;
        }

        if (retryableKeys.has(item.idempotency_key)) {
            remain.push(item);
            if (answerState[item.question_id]) answerState[item.question_id].status = 'queued';
        } else if (answerState[item.question_id]) {
            answerState[item.question_id].status = 'failed';
        }
    }

    outbox.value = remain;
    persistOutbox();

    if (outbox.value.length === 0) {
        globalSyncStatus.value = 'synced';
    } else if (navigator.onLine) {
        globalSyncStatus.value = 'saving';
    }

    if (data.attempt_status === 'submitted') redirectToResult();
}

// ------------------------------------------------------------------
// Heartbeat
// ------------------------------------------------------------------
let heartbeatTimer = null;

async function sendHeartbeat() {
    if (!props.attempt) return;

    const seqs = Object.values(answerState).map((s) => s.seq);
    const body = {
        status: navigator.onLine ? 'online' : 'offline',
        last_client_seq: seqs.length > 0 ? Math.max(...seqs) : 0,
        outbox_pending: outbox.value.length,
    };

    try {
        await fetch(route('student.exam.heartbeat', props.exam.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(body),
        });

        if (navigator.onLine && outbox.value.length > 0) scheduleFlush(500);
    } catch {
        // Heartbeat gagal senyap; status online dipegang navigator.onLine.
    }
}

// ------------------------------------------------------------------
// Timer server-authoritatif
// ------------------------------------------------------------------
const serverTime = computed(() => page.props.serverTime);
const clockOffset = serverTime.value - Date.now();
const deadlineEpoch = props.attempt ? serverTime.value + props.attempt.remaining_seconds * 1000 : 0;

const nowMs = ref(Date.now());
let clockTimer = null;
let submitted = false;

const remainingSeconds = computed(() => Math.max(0, Math.floor((deadlineEpoch - (nowMs.value + clockOffset)) / 1000)));
const timerCritical = computed(() => remainingSeconds.value <= 60 && remainingSeconds.value > 0);

const timerLabel = computed(() => {
    const total = remainingSeconds.value;
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    const pad = (n) => String(n).padStart(2, '0');
    return h > 0 ? `${pad(h)}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
});

function autoSubmitIfExpired() {
    if (submitted || !props.attempt) return;
    if (remainingSeconds.value > 0) return;
    submitted = true;
    showSubmitModal.value = false;
    router.post(route('student.exam.submit', props.exam.id));
}

// ------------------------------------------------------------------
// Anti-cheat
// ------------------------------------------------------------------
const antiCheatQueue = [];
let antiCheatTimer = null;
const showAntiCheatModal = ref(false);
const antiCheatInfo = ref({ warnings: 0, max: 0, message: '' });

async function flushAntiCheat() {
    if (!props.exam.anti_cheat_enabled || antiCheatQueue.length === 0) return;

    const events = antiCheatQueue.splice(0, 20);

    try {
        const response = await fetch(route('student.exam.anti-cheat', props.exam.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ events }),
        });

        const data = await response.json();

        antiCheatInfo.value = {
            warnings: data.warnings_count ?? 0,
            max: data.max_warnings ?? 0,
            message: data.enforced
                ? 'Batas peringatan terlampaui. Tindakan sudah diterapkan oleh server.'
                : 'Anda terdeteksi meninggalkan halaman ujian. Tetaplah di halaman ini.',
        };

        if (data.auto_submitted) redirectToResult();
    } catch {
        antiCheatQueue.unshift(...events);
    }
}

function onVisibilityChange() {
    if (!props.exam.anti_cheat_enabled) return;

    if (document.visibilityState === 'hidden') {
        antiCheatQueue.push({ type: 'visibility_hidden', message: 'Tab / aplikasi berpindah atau diminimalkan.', meta: { at: new Date().toISOString() } });
    } else {
        antiCheatQueue.push({ type: 'returned', message: 'Kembali ke halaman ujian.', meta: { at: new Date().toISOString() } });

        if (props.attempt?.status === 'in_progress') {
            antiCheatInfo.value = {
                warnings: props.attempt.warnings_count ?? 0,
                max: props.exam.anti_cheat_max_warnings ?? 0,
                message: 'Anda terdeteksi meninggalkan halaman ujian. Tetaplah di halaman ini.',
            };
            showAntiCheatModal.value = true;
        }
    }

    clearTimeout(antiCheatTimer);
    antiCheatTimer = setTimeout(flushAntiCheat, 1500);
}

function onWindowBlur() {
    if (!props.exam.anti_cheat_enabled) return;

    antiCheatQueue.push({ type: 'window_blur', message: 'Jendela ujian kehilangan fokus.', meta: { at: new Date().toISOString() } });
    clearTimeout(antiCheatTimer);
    antiCheatTimer = setTimeout(flushAntiCheat, 1500);
}

// ------------------------------------------------------------------
// Submit
// ------------------------------------------------------------------
const showSubmitModal = ref(false);
const unansweredCount = computed(() => totalQuestions.value - answeredCount.value);

async function doSubmit() {
    if (submitted) return;
    submitted = true;
    showSubmitModal.value = false;

    try {
        await flushOutbox();
    } catch {
        // Tetap submit; outbox masih bisa masuk via grace period.
    }

    router.post(route('student.exam.submit', props.exam.id));
}

function redirectToResult() {
    if (submitted || !props.attempt) return;
    submitted = true;
    router.visit(route('student.history.show', props.attempt.id));
}

// ------------------------------------------------------------------
// Interaksi jawaban per tipe soal
// ------------------------------------------------------------------
function selectPg(question, optionId) {
    setAnswer(question, { option_id: optionId });
}

function togglePgk(question, optionId) {
    const current = payloadOf(question.id)?.option_ids ?? [];
    const next = current.includes(optionId) ? current.filter((id) => id !== optionId) : [...current, optionId];
    setAnswer(question, { option_ids: next });
}

function selectBoolean(question, value) {
    setAnswer(question, { value });
}

function selectPair(question, leftId, rightId) {
    const current = { ...(payloadOf(question.id)?.mapping ?? {}) };
    if (rightId === null || rightId === '' || rightId === undefined) {
        delete current[leftId];
    } else {
        current[leftId] = Number(rightId);
    }
    setAnswer(question, { mapping: current });
}

const rightShuffles = new Map();
function rightOptionsOf(question) {
    if (!rightShuffles.has(question.id)) {
        const items = [...question.pairs];
        let seed = question.id * 2654435761;
        for (let i = items.length - 1; i > 0; i--) {
            seed = (seed * 1103515245 + 12345) & 0x7fffffff;
            const j = seed % (i + 1);
            [items[i], items[j]] = [items[j], items[i]];
        }
        rightShuffles.set(question.id, items);
    }
    return rightShuffles.get(question.id);
}

const gridClass = (question) => {
    const state = stateOf(question.id);
    if (state.status === 'failed') return 'bg-danger-500 text-white';
    if (state.status === 'saving' || state.status === 'queued') return 'bg-warning-500 text-white';
    if (isFilled(question, state.payload)) return 'bg-success-500 text-white';
    return 'bg-slate-200 text-slate-600';
};

// ------------------------------------------------------------------
// Lifecycle
// ------------------------------------------------------------------
onMounted(() => {
    loadOutbox();

    if (props.attempt) {
        clockTimer = setInterval(() => {
            nowMs.value = Date.now();
            autoSubmitIfExpired();
        }, 1000);

        const hbMs = (clientConfig.value.heartbeatIntervalSeconds ?? 20) * 1000;
        heartbeatTimer = setInterval(sendHeartbeat, hbMs);
        setTimeout(sendHeartbeat, 3000);

        window.addEventListener('online', markOnline);
        window.addEventListener('offline', markOffline);
        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('blur', onWindowBlur);

        if (outbox.value.length > 0) scheduleFlush(1000);
    }
});

onBeforeUnmount(() => {
    clearInterval(clockTimer);
    clearInterval(heartbeatTimer);
    clearTimeout(saveTimer);
    clearTimeout(antiCheatTimer);
    window.removeEventListener('online', markOnline);
    window.removeEventListener('offline', markOffline);
    document.removeEventListener('visibilitychange', onVisibilityChange);
    window.removeEventListener('blur', onWindowBlur);
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-100">
        <Head :title="title" />

        <!-- ===================== Layar Mulai ===================== -->
        <div v-if="!attempt && canStart" class="flex flex-1 items-center justify-center p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-lg">
                <div class="mb-4 text-5xl">📝</div>
                <h1 class="mb-2 text-xl font-bold text-slate-900">{{ exam.title }}</h1>
                <p class="mb-6 text-sm text-slate-500">
                    {{ totalQuestions }} soal · {{ exam.duration_minutes }} menit
                </p>
                <ul class="mb-6 space-y-2 text-left text-sm text-slate-600">
                    <li>✅ Jawaban tersimpan otomatis, termasuk saat offline.</li>
                    <li>⏱ Waktu dihitung server, bukan jam HP Anda.</li>
                    <li v-if="exam.anti_cheat_enabled">👁 Ujian ini mengaktifkan deteksi perpindahan tab.</li>
                    <li>⏳ Setelah waktu habis, jawaban dikumpulkan otomatis.</li>
                </ul>
                <form :action="route('student.exam.start', exam.id)" method="post">
                    <input type="hidden" name="_token" :value="csrfToken()" />
                    <button
                        type="submit"
                        class="flex h-14 w-full items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white"
                    >
                        Mulai Ujian
                    </button>
                </form>
            </div>
        </div>

        <!-- ===================== Layar Ujian ===================== -->
        <div v-else-if="attempt" class="flex flex-1 flex-col">
            <!-- Header: timer + progress + sync -->
            <header class="safe-top sticky top-0 z-30 border-b border-slate-200 bg-white">
                <div class="flex items-center justify-between px-4 py-2">
                    <span
                        class="rounded-full px-3 py-1 text-sm font-bold tabular-nums"
                        :class="timerCritical ? 'bg-danger-500 text-white animate-pulse' : 'bg-brand-100 text-brand-700'"
                    >
                        ⏱ {{ timerLabel }}
                    </span>

                    <span class="flex items-center gap-1.5 text-xs text-slate-600">
                        <span class="h-2.5 w-2.5 rounded-full" :class="syncDotClass"></span>
                        {{ syncLabel }}
                    </span>

                    <button
                        class="rounded-lg bg-danger-500 px-3 py-1.5 text-sm font-semibold text-white"
                        @click="showSubmitModal = true"
                    >
                        Kumpulkan
                    </button>
                </div>

                <div class="h-1.5 w-full bg-slate-200">
                    <div
                        class="h-1.5 bg-success-500 transition-all"
                        :style="{ width: progressPercent + '%' }"
                    ></div>
                </div>
            </header>

            <!-- Area soal -->
            <main class="flex-1 px-4 py-4">
                <div v-if="currentQuestion" class="mx-auto max-w-2xl">
                    <!-- Stimulus -->
                    <div
                        v-if="currentQuestion.stimulus"
                        class="mb-4 whitespace-pre-line rounded-xl bg-white p-4 text-sm text-slate-700 shadow-sm"
                    >
                        {{ currentQuestion.stimulus }}
                    </div>

                    <!-- Nomor + tipe -->
                    <div class="mb-2 flex items-center gap-2">
                        <span class="rounded-full bg-brand-600 px-3 py-1 text-xs font-semibold text-white">
                            Soal {{ currentIndex + 1 }} / {{ totalQuestions }}
                        </span>
                        <span class="rounded-full bg-slate-200 px-3 py-1 text-xs text-slate-600">
                            {{ currentQuestion.type_label }}
                        </span>
                    </div>

                    <p class="mb-1 text-xs text-slate-400">{{ currentQuestion.instruction }}</p>

                    <!-- Pertanyaan -->
                    <div class="mb-4 rounded-xl bg-white p-4 shadow-sm">
                        <p v-if="currentQuestion.media_url" class="mb-3">
                            <img :src="currentQuestion.media_url" class="max-h-64 rounded-lg" alt="media" />
                        </p>
                        <h2 class="whitespace-pre-line text-base font-semibold leading-relaxed text-slate-900">
                            {{ currentQuestion.question_text }}
                        </h2>
                    </div>

                    <!-- Jawaban -->
                    <div class="space-y-2">
                        <!-- PG -->
                        <button
                            v-if="currentQuestion.type === 'pg'"
                            v-for="option in currentQuestion.options"
                            :key="option.id"
                            class="flex min-h-12 w-full items-center gap-3 rounded-xl border-2 bg-white px-4 py-3 text-left text-sm transition-colors"
                            :class="payloadOf(currentQuestion.id)?.option_id === option.id
                                ? 'border-brand-600 bg-brand-50'
                                : 'border-slate-200'"
                            @click="selectPg(currentQuestion, option.id)"
                        >
                            <span
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2"
                                :class="payloadOf(currentQuestion.id)?.option_id === option.id ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-300'"
                            >
                                <span v-if="payloadOf(currentQuestion.id)?.option_id === option.id">✓</span>
                            </span>
                            <span>{{ option.label ? option.label + '. ' : '' }}{{ option.text }}</span>
                        </button>

                        <!-- PGK -->
                        <button
                            v-if="currentQuestion.type === 'pgk'"
                            v-for="option in currentQuestion.options"
                            :key="option.id"
                            class="flex min-h-12 w-full items-center gap-3 rounded-xl border-2 bg-white px-4 py-3 text-left text-sm transition-colors"
                            :class="(payloadOf(currentQuestion.id)?.option_ids ?? []).includes(option.id)
                                ? 'border-brand-600 bg-brand-50'
                                : 'border-slate-200'"
                            @click="togglePgk(currentQuestion, option.id)"
                        >
                            <span
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded border-2"
                                :class="(payloadOf(currentQuestion.id)?.option_ids ?? []).includes(option.id) ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-300'"
                            >
                                <span v-if="(payloadOf(currentQuestion.id)?.option_ids ?? []).includes(option.id)">✓</span>
                            </span>
                            <span>{{ option.label ? option.label + '. ' : '' }}{{ option.text }}</span>
                        </button>

                        <!-- Boolean -->
                        <div v-if="currentQuestion.type === 'boolean'" class="grid grid-cols-2 gap-3">
                            <button
                                class="flex h-20 items-center justify-center rounded-xl border-2 text-lg font-semibold"
                                :class="payloadOf(currentQuestion.id)?.value === true ? 'border-success-500 bg-success-50 text-success-700' : 'border-slate-200 bg-white text-slate-600'"
                                @click="selectBoolean(currentQuestion, true)"
                            >
                                ✓ Benar
                            </button>
                            <button
                                class="flex h-20 items-center justify-center rounded-xl border-2 text-lg font-semibold"
                                :class="payloadOf(currentQuestion.id)?.value === false ? 'border-danger-500 bg-danger-50 text-danger-700' : 'border-slate-200 bg-white text-slate-600'"
                                @click="selectBoolean(currentQuestion, false)"
                            >
                                ✗ Salah
                            </button>
                        </div>

                        <!-- Matching -->
                        <div v-if="currentQuestion.type === 'matching'" class="rounded-xl bg-white p-4 shadow-sm">
                            <div v-for="pair in currentQuestion.pairs" :key="pair.id" class="mb-3 last:mb-0">
                                <div class="mb-1 text-sm font-medium text-slate-700">{{ pair.left_text }}</div>
                                <select
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm"
                                    :value="(payloadOf(currentQuestion.id)?.mapping ?? {})[pair.id] ?? ''"
                                    @change="selectPair(currentQuestion, pair.id, $event.target.value)"
                                >
                                    <option value="">— pilih pasangan —</option>
                                    <option
                                        v-for="right in rightOptionsOf(currentQuestion)"
                                        :key="right.id"
                                        :value="right.id"
                                    >
                                        {{ right.right_text }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Bottom navigation -->
            <nav class="safe-bottom sticky bottom-0 z-30 border-t border-slate-200 bg-white">
                <div class="flex items-center gap-2 px-4 py-3">
                    <button
                        class="h-12 flex-1 rounded-xl bg-slate-100 font-semibold text-slate-700 disabled:opacity-40"
                        :disabled="currentIndex === 0"
                        @click="goTo(currentIndex - 1)"
                    >
                        ← Sebelumnya
                    </button>

                    <button
                        class="flex h-12 w-16 items-center justify-center rounded-xl bg-slate-200 font-semibold text-slate-700"
                        @click="gridOpen = !gridOpen"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <button
                        v-if="currentIndex < totalQuestions - 1"
                        class="h-12 flex-1 rounded-xl bg-brand-600 font-semibold text-white"
                        @click="goTo(currentIndex + 1)"
                    >
                        Berikutnya →
                    </button>
                    <button
                        v-else
                        class="h-12 flex-1 rounded-xl bg-success-600 font-semibold text-white"
                        @click="showSubmitModal = true"
                    >
                        Selesai ✓
                    </button>
                </div>
            </nav>

            <!-- Grid navigasi -->
            <div v-if="gridOpen" class="fixed inset-0 z-40 flex items-end justify-center bg-black/50" @click.self="gridOpen = false">
                <div class="safe-bottom max-h-[70vh] w-full max-w-2xl overflow-y-auto rounded-t-2xl bg-white p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="font-semibold text-slate-900">Navigasi Soal</h3>
                        <button class="text-slate-500" @click="gridOpen = false">✕</button>
                    </div>

                    <div class="grid grid-cols-6 gap-2">
                        <button
                            v-for="(question, index) in questions"
                            :key="question.id"
                            class="flex h-11 items-center justify-center rounded-lg text-sm font-semibold"
                            :class="[
                                gridClass(question),
                                index === currentIndex ? 'ring-2 ring-brand-600 ring-offset-2' : '',
                            ]"
                            @click="goTo(index)"
                        >
                            {{ index + 1 }}
                        </button>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3 text-xs text-slate-500">
                        <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-success-500"></span> Dijawab</span>
                        <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-warning-500"></span> Menyimpan</span>
                        <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-danger-500"></span> Gagal</span>
                        <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-slate-300"></span> Belum</span>
                    </div>
                </div>
            </div>

            <!-- Modal anti-cheat -->
            <div v-if="showAntiCheatModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center">
                    <div class="mb-3 text-4xl">⚠️</div>
                    <h3 class="mb-2 text-lg font-bold text-slate-900">Peringatan</h3>
                    <p class="mb-4 text-sm text-slate-600">{{ antiCheatInfo.message }}</p>
                    <p class="mb-4 text-xs text-slate-500">
                        Peringatan {{ antiCheatInfo.warnings }} dari {{ antiCheatInfo.max }}.
                        Melanggar lebih lanjut dapat mengunci atau mengumpulkan jawaban otomatis.
                    </p>
                    <button
                        class="h-12 w-full rounded-xl bg-brand-600 font-semibold text-white"
                        @click="showAntiCheatModal = false"
                    >
                        Saya Mengerti
                    </button>
                </div>
            </div>

            <!-- Modal submit -->
            <div v-if="showSubmitModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                <div class="w-full max-w-sm rounded-2xl bg-white p-6">
                    <h3 class="mb-3 text-lg font-bold text-slate-900">Kumpulkan Jawaban?</h3>
                    <p class="mb-2 text-sm text-slate-600">
                        Anda telah menjawab <strong>{{ answeredCount }}</strong> dari
                        <strong>{{ totalQuestions }}</strong> soal.
                    </p>
                    <p v-if="unansweredCount > 0" class="mb-4 text-sm text-warning-700">
                        Masih ada <strong>{{ unansweredCount }}</strong> soal belum dijawab.
                    </p>
                    <p v-else class="mb-4 text-sm text-success-700">
                        Semua soal sudah dijawab. Siap dikumpulkan.
                    </p>

                    <div class="flex gap-2">
                        <button
                            class="h-12 flex-1 rounded-xl bg-slate-100 font-semibold text-slate-700"
                            @click="showSubmitModal = false"
                        >
                            Batal
                        </button>
                        <button
                            class="h-12 flex-1 rounded-xl bg-success-600 font-semibold text-white"
                            @click="doSubmit"
                        >
                            Ya, Kumpulkan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== Tidak bisa mulai ===================== -->
        <div v-else class="flex flex-1 items-center justify-center p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-lg">
                <div class="mb-3 text-4xl">🔒</div>
                <p class="text-slate-600">Ujian tidak dapat diakses saat ini.</p>
            </div>
        </div>
    </div>
</template>
