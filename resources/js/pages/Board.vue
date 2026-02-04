<template>
    <div class="page">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sb-top">
                <div class="sb-brand">
                    <div class="logo">TB</div>
                    <div class="titles">
                        <div class="name">Teamboard</div>
                        <div class="sub">Boards</div>
                    </div>
                </div>

                <button class="sb-btn" disabled title="TODO: endpoint crear proyecto">
                    + Nuevo board
                </button>
            </div>

            <div class="sb-list">
                <button
                    v-for="p in projects"
                    :key="p.id"
                    class="sb-item"
                    :class="{ active: project?.id === p.id }"
                    @click="selectProject(p.id)"
                >
                    <span class="dot"></span>
                    <span class="sb-item-name">{{ p.name }}</span>
                </button>

                <div v-if="!projects.length && !loading" class="sb-empty">
                    No tienes boards aún.
                </div>
            </div>

            <div class="sb-bottom">
                <button class="sb-btn danger" @click="logout">Salir</button>
            </div>
        </aside>

        <!-- Main -->
        <section class="main">
            <header class="topbar">
                <div>
                    <div class="meta" v-if="project">
                        Proyecto: <strong>{{ project.name }}</strong>
                    </div>
                    <div class="meta" v-else>Selecciona un board</div>
                </div>

                <div class="top-actions">
                    <button class="btn" disabled title="TODO: endpoint crear columna">
                        + Columna
                    </button>
                </div>
            </header>

            <main class="content">
                <div v-if="loading" class="loading">Cargando...</div>

                <div v-else-if="!project" class="empty-state">
                    Selecciona un board de la izquierda.
                </div>

                <div v-else class="board">
                    <div
                        v-for="column in columns"
                        :key="column.id"
                        class="column"
                        @dragover.prevent="onDragOverColumn(column, $event)"
                        @drop.prevent="onDropColumn(column)"
                    >
                        <div class="column-header">
                            <span>{{ column.name }}</span>
                            <button class="mini" @click="toggleAdd(column.id)">+</button>
                        </div>

                        <div v-if="addingColumnId === column.id" class="addbox">
                            <input
                                v-model="newTitle"
                                class="input"
                                placeholder="Nueva tarea..."
                                @keydown.enter="createCard(column)"
                            />
                            <button class="mini" @click="createCard(column)">Crear</button>
                        </div>

                        <div class="cards">
                            <div
                                v-for="(card, idx) in column.cards"
                                :key="card.id"
                                class="card"
                                data-card
                                draggable="true"
                                @dragstart="onDragStart(card, column)"
                                @dragend="onDragEnd"
                                @dragover.prevent.stop="onDragOverCard(column, idx, $event)"
                                @drop.prevent="onDropAtOver"
                                :class="{ over: isOver(column.id, idx) }"
                            >
                                <div class="card-row">
                                    <div class="card-title">{{ card.title }}</div>

                                    <div class="card-actions">
                                        <button class="icon" @click.stop="openEdit(card, column)">
                                            ✎
                                        </button>
                                        <button class="icon danger" @click.stop="removeCard(card, column)">
                                            🗑
                                        </button>
                                    </div>
                                </div>

                                <div v-if="card.description" class="card-desc">
                                    {{ card.description }}
                                </div>
                            </div>

                            <!-- Drop final -->
                            <div
                                class="dropzone"
                                @dragover.prevent="onDragOverEnd(column, $event)"
                                @drop.prevent="onDropAtOver"
                                :class="{ over: isOverEnd(column.id) }"
                            >
                                <span v-if="!column.cards.length" class="empty">Sin tareas</span>
                                <span v-else class="hint">Suelta aquí para poner al final</span>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </section>

        <!-- Modal Edit -->
        <div v-if="edit.open" class="modal-backdrop" @click.self="closeEdit">
            <div class="modal">
                <div class="modal-title">Editar tarea</div>

                <label class="lbl">Título</label>
                <input v-model="edit.title" class="input" />

                <label class="lbl">Descripción</label>
                <textarea v-model="edit.description" class="ta" rows="4"></textarea>

                <div class="modal-actions">
                    <button class="btn ghost" @click="closeEdit">Cancelar</button>
                    <button class="btn" :disabled="edit.saving" @click="saveEdit">
                        {{ edit.saving ? "Guardando..." : "Guardar" }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref } from "vue";
import { useRouter } from "vue-router";
import { http } from "../lib/http";
import { useAuthStore } from "../stores/auth";
import { makeEcho } from "../lib/echo";

const router = useRouter();
const auth = useAuthStore();

const loading = ref(true);

// Boards (projects)
const projects = ref([]);
const project = ref(null);
const columns = ref([]);

// Echo
const echoRef = ref(null);
let subscribedProjectId = null;

// UI state
const addingColumnId = ref(null);
const newTitle = ref("");

// Dragging
const dragging = ref(null); // { cardId, fromColumnId }
const over = ref(null); // { columnId, index } | { columnId, end:true }

// Edit modal
const edit = ref({
    open: false,
    saving: false,
    cardId: null,
    columnId: null,
    title: "",
    description: "",
});

async function ensureAuth() {
    if (!auth.user) await auth.fetchUser();
    if (!auth.user) {
        router.push("/signin");
        return false;
    }
    return true;
}

async function loadProjects() {
    const { data } = await http.get("/api/projects");
    projects.value = data.data || [];
}

async function loadBoard(projectId) {
    loading.value = true;

    const res = await http.get(`/api/projects/${projectId}/board`);
    project.value = res.data.project;
    columns.value = res.data.columns || [];

    loading.value = false;
}

function disconnectRealtime() {
    if (!echoRef.value) return;

    if (subscribedProjectId) {
        // OJO: canal que usas es private-project.{id} (según logs)
        echoRef.value.leave(`private-project.${subscribedProjectId}`);
        subscribedProjectId = null;
    }
}

function subscribeRealtime(projectId) {
    if (!echoRef.value) return;

    if (subscribedProjectId === projectId) return;

    disconnectRealtime();
    subscribedProjectId = projectId;

    // Según tu log de reverb: "private-project.1"
    echoRef.value
        .private(`project.${projectId}`)
        .listen(".CardCreated", (e) => applyRemoteCardCreated(e))
        .listen(".CardMoved", (e) => applyRemoteCardMoved(e));
}

async function selectProject(projectId) {
    if (project.value?.id === projectId) return;

    await loadBoard(projectId);

    // Re-subscribe en el nuevo board
    if (!echoRef.value) echoRef.value = makeEcho();
    if (project.value?.id) subscribeRealtime(project.value.id);
}

/* ---------------- Realtime apply ---------------- */

function applyRemoteCardCreated(e) {
    const payload = e?.card ? e.card : e;
    const colId =
        payload.board_column_id ?? e.board_column_id ?? e.column_id ?? payload.column_id;
    if (!colId) return;

    const col = columns.value.find((c) => c.id === colId);
    if (!col) return;

    if (col.cards.some((c) => c.id === payload.id)) return;

    const pos = Number.isInteger(payload.position) ? payload.position : col.cards.length;

    col.cards.splice(Math.max(0, Math.min(pos, col.cards.length)), 0, {
        id: payload.id,
        title: payload.title,
        description: payload.description ?? null,
        position: pos,
        board_column_id: colId,
    });

    normalizePositions(col);
}

function applyRemoteCardMoved(e) {
    // Esperado desde backend: (projectId, cardId, fromColumnId, toColumnId, toPos, senderId?) según tu Event
    const cardId = e.card_id ?? e.cardId ?? e.id;
    const toColId = e.to_column_id ?? e.toColumnId;
    const toPos = e.to_position ?? e.toPosition ?? 0;

    if (!cardId || !toColId) return;

    const fromCol = columns.value.find((c) => c.cards.some((x) => x.id === cardId));
    if (!fromCol) return;

    const idx = fromCol.cards.findIndex((x) => x.id === cardId);
    if (idx === -1) return;

    const [moved] = fromCol.cards.splice(idx, 1);
    normalizePositions(fromCol);

    const destCol = columns.value.find((c) => c.id === toColId);
    if (!destCol) return;

    const safeIndex = Math.max(0, Math.min(toPos, destCol.cards.length));
    destCol.cards.splice(safeIndex, 0, moved);
    normalizePositions(destCol);
}

/* ---------------- Create card ---------------- */

function toggleAdd(columnId) {
    if (addingColumnId.value === columnId) {
        addingColumnId.value = null;
        newTitle.value = "";
        return;
    }
    addingColumnId.value = columnId;
    newTitle.value = "";
}

async function createCard(column) {
    const title = newTitle.value.trim();
    if (!title) return;

    const res = await http.post("/api/cards", {
        board_column_id: column.id,
        title,
    });

    const created = res.data.card;

    const col = columns.value.find((c) => c.id === column.id);
    if (!col) return;

    // Optimista local (aunque luego llegue realtime)
    if (!col.cards.some((c) => c.id === created.id)) {
        col.cards.push({
            id: created.id,
            title: created.title,
            description: created.description ?? null,
            position: col.cards.length,
            board_column_id: column.id,
        });
        normalizePositions(col);
    }

    addingColumnId.value = null;
    newTitle.value = "";
}

/* ---------------- Edit/Delete card ---------------- */

function openEdit(card, column) {
    edit.value.open = true;
    edit.value.saving = false;
    edit.value.cardId = card.id;
    edit.value.columnId = column.id;
    edit.value.title = card.title;
    edit.value.description = card.description ?? "";
}

function closeEdit() {
    edit.value.open = false;
    edit.value.saving = false;
    edit.value.cardId = null;
    edit.value.columnId = null;
    edit.value.title = "";
    edit.value.description = "";
}

async function saveEdit() {
    if (!edit.value.cardId) return;

    const title = edit.value.title.trim();
    if (!title) return;

    edit.value.saving = true;

    try {
        const res = await http.patch(`/api/cards/${edit.value.cardId}`, {
            title,
            description: edit.value.description?.trim() || null,
        });

        const updated = res.data.card;

        const col = columns.value.find((c) => c.id === edit.value.columnId);
        if (col) {
            const c = col.cards.find((x) => x.id === edit.value.cardId);
            if (c) {
                c.title = updated.title;
                c.description = updated.description ?? null;
            }
        }

        closeEdit();
    } finally {
        edit.value.saving = false;
    }
}

async function removeCard(card, column) {
    const ok = confirm(`¿Eliminar "${card.title}"?`);
    if (!ok) return;

    // optimista
    const snap = snapshot();
    try {
        const idx = column.cards.findIndex((c) => c.id === card.id);
        if (idx !== -1) {
            column.cards.splice(idx, 1);
            normalizePositions(column);
        }

        await http.delete(`/api/cards/${card.id}`);
    } catch (e) {
        restore(snap);
        throw e;
    }
}

/* ---------------- Drag&drop ---------------- */

function onDragStart(card, column) {
    dragging.value = { cardId: card.id, fromColumnId: column.id };
}

function onDragEnd() {
    over.value = null;
    dragging.value = null;
}

function onDragOverCard(column, idx, e) {
    if (!dragging.value) return;
    e.preventDefault();
    e.stopPropagation();

    const list = column.cards.filter((c) => c.id !== dragging.value.cardId);
    const hoveredCardId = column.cards[idx]?.id;
    const hoverIndex = list.findIndex((c) => c.id === hoveredCardId);
    if (hoverIndex === -1) return;

    const rect = e.currentTarget.getBoundingClientRect();
    const mid = rect.top + rect.height / 2;
    const insertIndex = e.clientY > mid ? hoverIndex + 1 : hoverIndex;

    over.value = { columnId: column.id, index: insertIndex };
}

function onDragOverEnd(column, e) {
    if (!dragging.value) return;
    if (e) e.preventDefault();
    over.value = { columnId: column.id, end: true };
}

function onDragOverColumn(column, e) {
    if (!dragging.value) return;
    if (e) e.preventDefault();
    if (e?.target?.closest?.("[data-card]")) return;
    over.value = { columnId: column.id, end: true };
}

function isOver(columnId, idx) {
    return over.value && over.value.columnId === columnId && over.value.index === idx;
}

function isOverEnd(columnId) {
    return over.value && over.value.columnId === columnId && over.value.end === true;
}

async function onDropAtOver() {
    if (!dragging.value || !over.value) return;

    const toColumnId = over.value.columnId;
    const col = columns.value.find((c) => c.id === toColumnId);
    if (!col) return;

    const stableListLen =
        dragging.value.fromColumnId === toColumnId
            ? col.cards.filter((c) => c.id !== dragging.value.cardId).length
            : col.cards.length;

    const rawIndex = over.value.end === true ? stableListLen : over.value.index;
    const toIndex = Math.max(0, Math.min(rawIndex, stableListLen));

    await moveOptimistic(toColumnId, toIndex);
}

async function onDropColumn() {
    await onDropAtOver();
}

/* ---------------- Helpers ---------------- */

function findColumn(colId) {
    return columns.value.find((c) => c.id === colId) || null;
}

function normalizePositions(col) {
    col.cards.forEach((c, i) => (c.position = i));
}

function removeFromColumn(colId, cardId) {
    const col = findColumn(colId);
    if (!col) throw new Error(`removeFromColumn: column not found col=${colId}`);

    const idx = col.cards.findIndex((c) => c.id === cardId);
    if (idx === -1) throw new Error(`removeFromColumn out of range col=${colId}`);

    const [removed] = col.cards.splice(idx, 1);
    normalizePositions(col);
    return removed;
}

function insertIntoColumn(colId, index, card) {
    const col = findColumn(colId);
    if (!col) throw new Error(`insertIntoColumn: column not found col=${colId}`);

    const safeIndex = Math.max(0, Math.min(index, col.cards.length));
    col.cards.splice(safeIndex, 0, card);
    normalizePositions(col);
}

function snapshot() {
    return JSON.parse(JSON.stringify({ columns: columns.value, project: project.value, projects: projects.value }));
}

function restore(snap) {
    columns.value = snap.columns;
    project.value = snap.project;
    projects.value = snap.projects;
}

async function moveOptimistic(toColumnId, toIndex) {
    if (!dragging.value) return;

    const snap = snapshot();
    const { cardId } = dragging.value;

    const found = columns.value
        .map((c) => ({ colId: c.id, idx: c.cards.findIndex((x) => x.id === cardId) }))
        .find((x) => x.idx !== -1);

    if (!found) {
        dragging.value = null;
        over.value = null;
        return;
    }

    const realFromColId = found.colId;
    const realFromIdx = found.idx;

    const destCol = findColumn(toColumnId);
    if (!destCol) {
        dragging.value = null;
        over.value = null;
        return;
    }

    const toIndexClamped = Math.max(0, Math.min(toIndex, destCol.cards.length));

    // no-op
    if (realFromColId === toColumnId && toIndexClamped === realFromIdx) {
        dragging.value = null;
        over.value = null;
        return;
    }

    try {
        const moved = removeFromColumn(realFromColId, cardId);
        insertIntoColumn(toColumnId, toIndexClamped, moved);

        await http.patch(`/api/cards/${cardId}/move`, {
            to_board_column_id: toColumnId,
            to_position: toIndexClamped,
        });
    } catch (e) {
        restore(snap);
        throw e;
    } finally {
        dragging.value = null;
        over.value = null;
    }
}

/* ---------------- Logout & lifecycle ---------------- */

async function logout() {
    await auth.logout();
    router.push("/signin");
}

onMounted(async () => {
    const ok = await ensureAuth();
    if (!ok) return;

    loading.value = true;

    await loadProjects();

    // auto seleccionar el primero
    const first = projects.value[0];
    if (first) {
        await loadBoard(first.id);
    } else {
        loading.value = false;
        project.value = null;
        columns.value = [];
    }

    // realtime
    if (!echoRef.value) echoRef.value = makeEcho();
    if (project.value?.id) subscribeRealtime(project.value.id);

    loading.value = false;
});

onBeforeUnmount(() => {
    disconnectRealtime();
    if (echoRef.value) {
        echoRef.value.disconnect();
        echoRef.value = null;
    }
});
</script>

<style scoped>
.page {
    min-height: 100vh;
    color: #e5e7eb;
    background:
        radial-gradient(1000px 600px at 15% 10%, rgba(99,102,241,.25), transparent 60%),
        radial-gradient(900px 600px at 90% 20%, rgba(236,72,153,.18), transparent 55%),
        radial-gradient(900px 650px at 50% 120%, rgba(34,197,94,.12), transparent 55%),
        #070a16;
    display: grid;
    grid-template-columns: 280px 1fr;
    overflow: hidden;
}

/* Sidebar */
.sidebar {
    border-right: 1px solid rgba(255,255,255,0.10);
    background: rgba(15, 23, 42, 0.40);
    backdrop-filter: blur(12px);
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.sb-top {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.10);
}

.sb-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: rgba(99,102,241,0.22);
    border: 1px solid rgba(99,102,241,0.35);
    font-weight: 800;
}

.titles .name {
    font-weight: 800;
    letter-spacing: .2px;
}
.titles .sub {
    font-size: 12px;
    opacity: .7;
}

.sb-btn {
    border: 1px solid rgba(255,255,255,0.14);
    background: rgba(255,255,255,0.06);
    color: #e5e7eb;
    padding: 10px 12px;
    border-radius: 12px;
    cursor: pointer;
    transition: transform .12s ease, filter .12s ease, border-color .12s ease;
}
.sb-btn:hover {
    transform: translateY(-1px);
    filter: brightness(1.06);
    border-color: rgba(255,255,255,0.22);
}
.sb-btn:disabled {
    opacity: .45;
    cursor: not-allowed;
    transform: none;
}

.sb-btn.danger {
    border-color: rgba(239,68,68,0.25);
    background: rgba(239,68,68,0.10);
}

.sb-list {
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    overflow: auto;
}

.sb-item {
    width: 100%;
    text-align: left;
    border: 1px solid rgba(255,255,255,0.10);
    background: rgba(255,255,255,0.04);
    color: #e5e7eb;
    border-radius: 12px;
    padding: 10px 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: border-color .12s ease, filter .12s ease, transform .12s ease;
}
.sb-item:hover {
    transform: translateY(-1px);
    filter: brightness(1.05);
    border-color: rgba(255,255,255,0.16);
}
.sb-item.active {
    border-color: rgba(99,102,241,0.55);
    background: rgba(99,102,241,0.10);
}
.dot {
    width: 9px;
    height: 9px;
    border-radius: 999px;
    background: rgba(99,102,241,0.75);
    box-shadow: 0 0 0 4px rgba(99,102,241,0.12);
}
.sb-item-name {
    font-size: 13px;
    font-weight: 650;
    opacity: .95;
}

.sb-empty {
    padding: 12px;
    font-size: 12.5px;
    opacity: .7;
}

.sb-bottom {
    margin-top: auto;
    padding: 16px;
    border-top: 1px solid rgba(255,255,255,0.10);
}

/* Main */
.main {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.topbar {
    position: sticky;
    top: 0;
    z-index: 10;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 16px 22px;
    background: rgba(15, 23, 42, 0.55);
    border-bottom: 1px solid rgba(255,255,255,0.10);
    backdrop-filter: blur(12px);
    box-shadow: 0 14px 40px rgba(0,0,0,0.35);
}

.meta {
    font-size: 13px;
    color: rgba(229,231,235,0.75);
}
.meta strong {
    color: rgba(229,231,235,0.95);
}

.btn {
    border: 1px solid rgba(255,255,255,0.14);
    color: #e5e7eb;
    cursor: pointer;
    border-radius: 12px;
    padding: 9px 14px;
    background: rgba(255,255,255,0.06);
    box-shadow: 0 10px 20px rgba(0,0,0,0.22);
    transition: transform .12s ease, filter .12s ease, border-color .12s ease;
}
.btn:hover {
    filter: brightness(1.06);
    border-color: rgba(255,255,255,0.22);
    transform: translateY(-1px);
}
.btn:disabled {
    opacity: .45;
    cursor: not-allowed;
    transform: none;
}
.btn.ghost {
    background: transparent;
    box-shadow: none;
}

.content {
    padding: 18px 22px 24px;
    min-width: 0;
}

.loading {
    opacity: .8;
    padding: 12px 0;
}

.empty-state {
    padding: 18px 0;
    opacity: .8;
}

/* Board horizontal */
.board {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    overflow-x: auto;
    padding: 8px 4px 16px;
    scroll-snap-type: x proximity;
    -webkit-overflow-scrolling: touch;
}

.board::-webkit-scrollbar { height: 10px; }
.board::-webkit-scrollbar-track { background: rgba(255,255,255,0.06); border-radius: 999px; }
.board::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.16); border-radius: 999px; }
.board::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.22); }

/* Column */
.column {
    width: 320px;
    padding: 12px;
    border-radius: 18px;
    scroll-snap-align: start;
    background: rgba(17, 24, 39, 0.58);
    border: 1px solid rgba(255,255,255,0.10);
    backdrop-filter: blur(10px);
    box-shadow: 0 18px 45px rgba(0,0,0,0.40), 0 1px 0 rgba(255,255,255,0.06) inset;
    transition: box-shadow .12s ease, border-color .12s ease;
}
.column:hover {
    border-color: rgba(255,255,255,0.16);
    box-shadow: 0 22px 55px rgba(0,0,0,0.45), 0 1px 0 rgba(255,255,255,0.06) inset;
}

.column-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    margin-bottom: 10px;
    padding: 6px 6px 8px;
}
.column-header span {
    font-size: 13px;
    color: rgba(229,231,235,0.92);
    text-transform: uppercase;
    letter-spacing: .8px;
}

.mini {
    border: 1px solid rgba(255,255,255,0.14);
    color: #e5e7eb;
    cursor: pointer;
    border-radius: 12px;
    background: rgba(255,255,255,0.06);
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    transition: filter .12s ease, border-color .12s ease;
}
.mini:hover {
    filter: brightness(1.08);
    border-color: rgba(255,255,255,0.22);
}

.addbox {
    display: flex;
    gap: 8px;
    margin: 2px 4px 12px;
}

.input {
    flex: 1;
    background: rgba(15, 23, 42, 0.70);
    border: 1px solid rgba(255,255,255,0.12);
    color: #e5e7eb;
    border-radius: 14px;
    padding: 10px 12px;
    outline: none;
    transition: border-color .12s ease, box-shadow .12s ease, transform .12s ease;
}
.input::placeholder { color: rgba(229,231,235,0.45); }
.input:focus {
    border-color: rgba(99,102,241,0.75);
    box-shadow: 0 0 0 4px rgba(99,102,241,0.18);
    transform: translateY(-1px);
}

.cards {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-height: 70px;
    padding: 0 4px 4px;
}

.card {
    background: rgba(17,24,39,0.82);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 14px;
    padding: 12px;
    font-size: 14px;
    line-height: 1.3;
    cursor: grab;
    user-select: none;
    box-shadow: 0 10px 20px rgba(0,0,0,0.28), 0 1px 0 rgba(255,255,255,0.04) inset;
    transition: transform .12s ease, border-color .12s ease, box-shadow .12s ease, filter .12s ease;
}
.card:hover {
    transform: translateY(-1px);
    border-color: rgba(255,255,255,0.16);
    filter: brightness(1.03);
    box-shadow: 0 14px 28px rgba(0,0,0,0.35), 0 1px 0 rgba(255,255,255,0.05) inset;
}
.card:active { cursor: grabbing; transform: translateY(0); }
.card.over {
    border-color: rgba(99,102,241,0.75);
    box-shadow: 0 0 0 4px rgba(99,102,241,0.18), 0 14px 28px rgba(0,0,0,0.35);
}

.card-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.card-title {
    font-weight: 650;
    opacity: .95;
}
.card-desc {
    margin-top: 8px;
    font-size: 12.5px;
    opacity: .72;
    white-space: pre-wrap;
}

.card-actions {
    display: flex;
    gap: 6px;
}
.icon {
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.05);
    color: rgba(229,231,235,0.9);
    border-radius: 10px;
    padding: 6px 8px;
    cursor: pointer;
    transition: filter .12s ease, border-color .12s ease, transform .12s ease;
}
.icon:hover {
    filter: brightness(1.08);
    border-color: rgba(255,255,255,0.18);
    transform: translateY(-1px);
}
.icon.danger {
    border-color: rgba(239,68,68,0.22);
    background: rgba(239,68,68,0.08);
}

.dropzone {
    border: 1px dashed rgba(255,255,255,0.18);
    border-radius: 14px;
    padding: 12px;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(229,231,235,0.60);
    background: rgba(255,255,255,0.03);
    transition: border-color .12s ease, box-shadow .12s ease, transform .12s ease, background .12s ease;
}
.dropzone.over {
    border-color: rgba(99,102,241,0.75);
    background: rgba(99,102,241,0.08);
    box-shadow: 0 0 0 4px rgba(99,102,241,0.16);
    transform: translateY(-1px);
}
.empty, .hint { font-size: 12.5px; opacity: .85; }

/* Modal */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.55);
    backdrop-filter: blur(6px);
    display: grid;
    place-items: center;
    z-index: 50;
}
.modal {
    width: min(520px, calc(100vw - 24px));
    background: rgba(17, 24, 39, 0.92);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 18px;
    padding: 16px;
    box-shadow: 0 30px 80px rgba(0,0,0,0.55);
}
.modal-title {
    font-weight: 800;
    margin-bottom: 12px;
}
.lbl {
    font-size: 12px;
    opacity: .75;
    display: block;
    margin: 10px 0 6px;
}
.ta {
    width: 100%;
    background: rgba(15, 23, 42, 0.70);
    border: 1px solid rgba(255,255,255,0.12);
    color: #e5e7eb;
    border-radius: 14px;
    padding: 10px 12px;
    outline: none;
    resize: vertical;
}
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 14px;
}

@media (max-width: 880px) {
    .page { grid-template-columns: 240px 1fr; }
}
@media (max-width: 720px) {
    .page { grid-template-columns: 1fr; }
    .sidebar { display: none; } /* luego si quieres: drawer */
}
</style>
