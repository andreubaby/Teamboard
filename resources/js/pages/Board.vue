
<template>
    <div class="page">
        <header class="topbar">
            <div>
                <div class="brand">Teamboard</div>
                <div class="meta" v-if="project">
                    Proyecto: <strong>{{ project.name }}</strong>
                </div>
            </div>
            <button class="btn" @click="logout">Salir</button>
        </header>

        <main class="content">
            <div v-if="loading" class="loading">Cargando board...</div>

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
                            {{ card.title }}
                        </div>

                        <!-- Drop en “final” -->
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
const echoRef = ref(null);
let subscribedProjectId = null;

const project = ref(null);
const columns = ref([]);
const loading = ref(true);

const addingColumnId = ref(null);
const newTitle = ref("");

// dragging: { cardId, fromColumnId }
const dragging = ref(null);

// over: { columnId, index } o { columnId, end:true }
const over = ref(null);

function subscribeRealtime(projectId) {
    if (!echoRef.value) return;

    if (subscribedProjectId === projectId) return;

    // salir del canal anterior
    if (subscribedProjectId) {
        echoRef.value.leave(`private-project.${subscribedProjectId}`);
    }

    subscribedProjectId = projectId;

    echoRef.value
        .private(`project.${projectId}`)
        .listen(".CardCreated", (e) => applyRemoteCardCreated(e))
        .listen(".CardMoved", (e) => applyRemoteCardMoved(e));
}

function applyRemoteCardCreated(e) {
    // e = { project_id?, column_id?, card? ... } depende de tu payload
    const card = e.card ?? e; // por si emites directo
    const colId = card.board_column_id ?? e.board_column_id ?? e.column_id;
    if (!colId) return;

    const col = columns.value.find(c => c.id === colId);
    if (!col) return;

    // evita duplicados
    if (col.cards.some(c => c.id === card.id)) return;

    col.cards.splice(card.position ?? col.cards.length, 0, {
        id: card.id,
        title: card.title,
        description: card.description ?? null,
        position: card.position ?? col.cards.length,
        board_column_id: colId,
    });

    normalizePositions(col);
}

function applyRemoteCardMoved(e) {
    // Esperado: { card_id, from_column_id, to_column_id, to_position }
    const cardId = e.card_id ?? e.cardId ?? e.id;
    const fromColId = e.from_column_id ?? e.fromColumnId;
    const toColId = e.to_column_id ?? e.toColumnId;
    const toPos = e.to_position ?? e.toPosition ?? 0;

    if (!cardId || !toColId) return;

    // Encuentra la card en cualquier columna (por seguridad)
    const fromCol = columns.value.find(c => c.cards.some(x => x.id === cardId));
    if (!fromCol) return;

    const idx = fromCol.cards.findIndex(x => x.id === cardId);
    if (idx === -1) return;

    const [moved] = fromCol.cards.splice(idx, 1);
    normalizePositions(fromCol);

    const destCol = columns.value.find(c => c.id === toColId);
    if (!destCol) return;

    const safeIndex = Math.max(0, Math.min(toPos, destCol.cards.length));
    destCol.cards.splice(safeIndex, 0, moved);
    normalizePositions(destCol);
}

async function loadBoard() {
    loading.value = true;

    const { data } = await http.get("/api/projects");
    const firstProject = data.data[0];
    if (!firstProject) {
        loading.value = false;
        return;
    }

    const res = await http.get(`/api/projects/${firstProject.id}/board`);
    project.value = res.data.project;
    columns.value = res.data.columns;

    loading.value = false;
}

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

    col.cards.push({
        id: created.id,
        title: created.title,
        description: created.description,
        position: col.cards.length,
    });

    addingColumnId.value = null;
    newTitle.value = "";
    normalizePositions(col);
}

function onDragStart(card, column) {
    dragging.value = { cardId: card.id, fromColumnId: column.id };
}

function onDragEnd() {
    over.value = null;
    dragging.value = null;
}

function onDragOverCard(column, idx, e) {
    if (!dragging.value) return;
    e.preventDefault(); // blindado
    e.stopPropagation(); // extra seguridad (aunque ya usas .stop en template)

    const list = column.cards.filter((c) => c.id !== dragging.value.cardId);
     // la card sobre la que pasas (e.currentTarget) corresponde a column.cards[idx]
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
    // Si el target está dentro de una card, NO tocar over (la card manda)
    if (e?.target?.closest?.("[data-card]")) return;
    over.value = { columnId: column.id, end: true };
}

function isOver(columnId, idx) {
    return over.value && over.value.columnId === columnId && over.value.index === idx;
}
function isOverEnd(columnId) {
    return over.value && over.value.columnId === columnId && over.value.end === true;
}

// drop usando el over calculado
async function onDropAtOver() {
    if (!dragging.value || !over.value) return;

    const toColumnId = over.value.columnId;
    const col = columns.value.find((c) => c.id === toColumnId);
    if (!col) return;


    const stableListLen =
        (dragging.value.fromColumnId === toColumnId)
        ? col.cards.filter(c => c.id !== dragging.value.cardId).length : col.cards.length;

        const rawIndex = over.value.end === true ? stableListLen : over.value.index;
    const toIndex = Math.max(0, Math.min(rawIndex, stableListLen));

    await moveOptimistic(toColumnId, toIndex);
}

async function onDropColumn(targetColumn) {
        await onDropAtOver();
}

/** Helpers robustos **/
function findColumn(colId) {
    return columns.value.find((c) => c.id === colId) || null;
}

function findCardIndex(colId, cardId) {
    const col = findColumn(colId);
    if (!col) return -1;
    return col.cards.findIndex((c) => c.id === cardId);
}

function normalizePositions(col) {
    col.cards.forEach((c, i) => (c.position = i));
}

function removeFromColumn(colId, cardId) {
    const col = findColumn(colId);
    if (!col) throw new Error(`removeFromColumn: column not found col=${colId}`);

    const idx = col.cards.findIndex((c) => c.id === cardId);
    if (idx === -1) {
        throw new Error(
            `removeFromColumn out of range col=${colId} idx=-1 len=${col.cards.length}`
        );
    }

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
    return JSON.parse(JSON.stringify(columns.value));
}

function restore(snap) {
    columns.value = snap;
}

/** Movimiento optimista robusto **/
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

    // No-op (si misma columna y misma posición)
    if (realFromColId === toColumnId) {

            if (toIndexClamped === realFromIdx) {
                dragging.value = null;
                over.value = null;
                return;
            }
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


async function logout() {
    await auth.logout();
    router.push("/signin");
}

onMounted(async () => {
    if (!auth.user) await auth.fetchUser();
    if (!auth.user) {
        router.push("/signin");
        return;
    }

    await loadBoard();

    if (!project.value?.id) return;

    echoRef.value = makeEcho();
    subscribeRealtime(project.value.id);
});

onBeforeUnmount(() => {
    if (echoRef.value) {
        if (subscribedProjectId) {
            echoRef.value.leave(`private-project.${subscribedProjectId}`);
            subscribedProjectId = null;
        }
        echoRef.value.disconnect();
        echoRef.value = null;
    }
});
</script>

<style scoped>
/* ---------- Layout + fondo ---------- */
.page{
    min-height: 100vh;
    color:#e5e7eb;
    position: relative;
    background:
        radial-gradient(1000px 600px at 15% 10%, rgba(99,102,241,.25), transparent 60%),
        radial-gradient(900px 600px at 90% 20%, rgba(236,72,153,.18), transparent 55%),
        radial-gradient(900px 650px at 50% 120%, rgba(34,197,94,.12), transparent 55%),
        #070a16;
    overflow: hidden;
}

/* ---------- Topbar ---------- */
.topbar{
    position: sticky;
    top: 0;
    z-index: 10;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap: 16px;
    padding:16px 22px;

    background: rgba(15, 23, 42, 0.55);
    border-bottom: 1px solid rgba(255,255,255,0.10);
    backdrop-filter: blur(12px);

    box-shadow: 0 14px 40px rgba(0,0,0,0.35);
}

.brand{
    font-weight: 800;
    font-size: 18px;
    letter-spacing: .3px;
    line-height: 1.1;
}

.meta{
    margin-top: 4px;
    font-size: 12.5px;
    color: rgba(229,231,235,0.72);
}
.meta strong{
    color: rgba(229,231,235,0.95);
}

/* ---------- Botones (Salir + mini) ---------- */
.btn,
.mini{
    border: 1px solid rgba(255,255,255,0.14);
    color:#e5e7eb;
    cursor:pointer;
    border-radius: 12px;
    transition: transform .12s ease, filter .12s ease, box-shadow .12s ease, border-color .12s ease;
}

.btn{
    padding: 9px 14px;
    background: rgba(255,255,255,0.06);
    box-shadow: 0 10px 20px rgba(0,0,0,0.22);
}
.btn:hover{
    filter: brightness(1.06);
    border-color: rgba(255,255,255,0.22);
    transform: translateY(-1px);
}
.btn:active{ transform: translateY(0); }

.mini{
    padding: 6px 10px;
    font-size: 12px;
    background: rgba(255,255,255,0.06);
}
.mini:hover{
    filter: brightness(1.08);
    border-color: rgba(255,255,255,0.22);
}
.column-header .mini{
    width: 32px;
    height: 32px;
    padding: 0;
    display:grid;
    place-items:center;
    border-radius: 12px;
}

/* ---------- Contenido ---------- */
.content{
    padding: 18px 22px 24px;
}

.loading{
    opacity: .78;
    padding: 14px 0;
}

/* ---------- Board (scroll horizontal) ---------- */
.board{
    display:flex;
    gap: 16px;
    align-items:flex-start;
    overflow-x:auto;
    padding: 8px 4px 16px;

    scroll-snap-type: x proximity;
    -webkit-overflow-scrolling: touch;
}

/* scrollbar (webkit) */
.board::-webkit-scrollbar{ height: 10px; }
.board::-webkit-scrollbar-track{ background: rgba(255,255,255,0.06); border-radius: 999px; }
.board::-webkit-scrollbar-thumb{ background: rgba(255,255,255,0.16); border-radius: 999px; }
.board::-webkit-scrollbar-thumb:hover{ background: rgba(255,255,255,0.22); }

/* ---------- Columnas ---------- */
.column{
    width: 320px;
    padding: 12px;
    border-radius: 18px;
    scroll-snap-align: start;

    background: rgba(17, 24, 39, 0.58);
    border: 1px solid rgba(255,255,255,0.10);
    backdrop-filter: blur(10px);

    box-shadow:
        0 18px 45px rgba(0,0,0,0.40),
        0 1px 0 rgba(255,255,255,0.06) inset;

    transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
}
.column:hover{
    border-color: rgba(255,255,255,0.16);
    box-shadow:
        0 22px 55px rgba(0,0,0,0.45),
        0 1px 0 rgba(255,255,255,0.06) inset;
}

/* cabecera de columna */
.column-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap: 10px;
    font-weight: 700;
    margin-bottom: 10px;
    padding: 6px 6px 8px;
}
.column-header span{
    font-size: 13px;
    color: rgba(229,231,235,0.92);
    text-transform: uppercase;
    letter-spacing: .8px;
}

/* ---------- Añadir card ---------- */
.addbox{
    display:flex;
    gap: 8px;
    margin: 2px 4px 12px;
}

.input{
    flex: 1;
    background: rgba(15, 23, 42, 0.70);
    border: 1px solid rgba(255,255,255,0.12);
    color:#e5e7eb;
    border-radius: 14px;
    padding: 10px 12px;
    outline:none;
    transition: border-color .12s ease, box-shadow .12s ease, transform .12s ease;
}
.input::placeholder{ color: rgba(229,231,235,0.45); }
.input:focus{
    border-color: rgba(99,102,241,0.75);
    box-shadow: 0 0 0 4px rgba(99,102,241,0.18);
    transform: translateY(-1px);
}

/* ---------- Cards ---------- */
.cards{
    display:flex;
    flex-direction:column;
    gap: 10px;
    min-height: 70px;
    padding: 0 4px 4px;
}

.card{
    background: rgba(17,24,39,0.82);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 14px;
    padding: 12px 12px;
    font-size: 14px;
    line-height: 1.3;
    cursor: grab;
    user-select:none;

    box-shadow:
        0 10px 20px rgba(0,0,0,0.28),
        0 1px 0 rgba(255,255,255,0.04) inset;

    transition: transform .12s ease, border-color .12s ease, box-shadow .12s ease, filter .12s ease;
}
.card:hover{
    transform: translateY(-1px);
    border-color: rgba(255,255,255,0.16);
    filter: brightness(1.03);
    box-shadow:
        0 14px 28px rgba(0,0,0,0.35),
        0 1px 0 rgba(255,255,255,0.05) inset;
}
.card:active{ cursor: grabbing; transform: translateY(0); }

/* estado "over" al arrastrar */
.card.over{
    outline: none;
    border-color: rgba(99,102,241,0.75);
    box-shadow:
        0 0 0 4px rgba(99,102,241,0.18),
        0 14px 28px rgba(0,0,0,0.35);
}

/* ---------- Dropzone final ---------- */
.dropzone{
    border: 1px dashed rgba(255,255,255,0.18);
    border-radius: 14px;
    padding: 12px;
    min-height: 44px;
    display:flex;
    align-items:center;
    justify-content:center;

    color: rgba(229,231,235,0.60);
    background: rgba(255,255,255,0.03);

    transition: border-color .12s ease, box-shadow .12s ease, transform .12s ease, background .12s ease;
}
.dropzone.over{
    border-color: rgba(99,102,241,0.75);
    background: rgba(99,102,241,0.08);
    box-shadow: 0 0 0 4px rgba(99,102,241,0.16);
    transform: translateY(-1px);
}

.empty, .hint{
    font-size: 12.5px;
    opacity: .85;
}

/* ---------- Responsive ---------- */
@media (max-width: 520px){
    .content{ padding: 14px 14px 18px; }
    .topbar{ padding: 14px 14px; }
    .column{ width: 290px; border-radius: 16px; }
}
</style>
