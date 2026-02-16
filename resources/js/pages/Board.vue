<template>
    <div class="page" :class="{ 'sb-collapsed': sidebarCollapsed }">
        <SidebarBoards
            :projects="projects"
            :activeId="project?.id ?? null"
            :loading="loading"
            :disableCreate="false"
            :collapsed="sidebarCollapsed"
            @toggle-collapse="sidebarCollapsed = !sidebarCollapsed"
            @select="goToBoard"
            @logout="logout"
            @create="createBoard"
        />

        <section class="main">
            <BoardTopbar
                :project="project"
                :disableAddColumn="!project"
                @add-column="createColumn"
                @refresh-board="() => loadBoard(project.id)"
            />

            <main class="content">
                <div v-if="loading" class="loading">Cargando...</div>

                <div v-else-if="!project" class="empty-state">
                    Selecciona un board de la izquierda.
                </div>

                <KanbanBoard
                    :columns="columns"
                    :addingColumnId="addingColumnId"
                    :addDraft="addDraft"
                    :isOver="isOver"
                    :isOverEnd="isOverEnd"
                    :draggingColumnId="draggingColumnId"
                    @col-pointerdown="({ e, columnId, boardEl }) => onColumnPointerDown(e, columnId, boardEl)"
                    @toggle-add="toggleAdd"
                    @generate-ai="handleAiGeneration"
                    @update-draft="({ columnId, value }) => updateDraft(columnId, value)"
                    @create-card="createCard"
                    @dragover-column="({ column, e }) => onDragOverColumn(column, e)"
                    @drop-column="() => onDropColumn()"
                    @dragover-end="({ column, e }) => onDragOverEnd(column, e)"
                    @drop-at-over="onDropAtOver"
                    @dragstart="({ card, column }) => onDragStart(card, column)"
                    @dragend="onDragEnd"
                    @dragover-card="({ column, idx, e }) => onDragOverCard(column, idx, e)"
                    @open-edit="({ card, column }) => openEdit(card, column)"
                    @remove-card="({ card, column }) => removeCard(card, column)"
                />
            </main>
        </section>

        <CardModalEdit
            :open="edit.open"
            :saving="edit.saving"
            v-model:title="edit.title"
            v-model:description="edit.description"
            v-model:priority="edit.priority"
            v-model:tagIds="edit.tagIds"
            :availableTags="availableTags"
            @close="closeEdit"
            @save="saveEdit"
        />
    </div>
</template>

<script setup>
import SidebarBoards from "../components/layout/SidebarBoards.vue";
import BoardTopbar from "../components/layout/BoardTopbar.vue";
import KanbanBoard from "../components/kanban/KanbanBoard.vue";
import CardModalEdit from "../components/modals/CardModalEdit.vue";
import { http } from "../lib/http";

import { onMounted, onBeforeUnmount, ref, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "../stores/auth";

import { useBoardData } from "../composables/board/useBoardData";
import { useBoardRealtime } from "../composables/board/useBoardRealtime";
import { useColumnDnD } from "../composables/board/useColumnDnD";
import { useCardDnD } from "../composables/board/useCardDnD";
import { useCardEditor } from "../composables/board/useCardEditor";

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

// --- LÓGICA DE PERSISTENCIA DEL SIDEBAR ---
// 1. Leemos el estado guardado (si existe)
const storedState = localStorage.getItem('harvis_sb_collapsed');

// 2. Inicializamos: si es 'true' empieza cerrado, si no, abierto.
// (Aquí he borrado la línea duplicada que tenías antes)
const sidebarCollapsed = ref(storedState === 'true');

// 3. Vigilamos los cambios para guardar la preferencia
watch(sidebarCollapsed, (newValue) => {
    localStorage.setItem('harvis_sb_collapsed', newValue);
});

// --- AUTH ---
async function ensureAuth() {
    if (!auth.user) await auth.fetchUser();
    if (!auth.user) {
        router.push("/signin");
        return false;
    }
    return true;
}

async function logout() {
    await auth.logout();
    router.push("/signin");
}

// Data
const {
    loading,
    projects,
    project,
    columns,
    loadProjects,
    loadBoard,
    createBoard,
    createColumn,
    goToBoard,
} = useBoardData({ router });

// Realtime
const {
    ensureEcho,
    subscribeBoardsRealtime,
    subscribeProjectRealtime,
    disconnectAll,
} = useBoardRealtime({ auth, projects, project, columns, loadBoard });

// Column DnD
const { draggingColumnId, onColumnPointerDown } = useColumnDnD({ project, columns });

// Card DnD + create
const {
    addingColumnId,
    addDraft,
    toggleAdd,
    updateDraft,
    createCard,
    onDragStart,
    onDragEnd,
    onDragOverCard,
    onDragOverColumn,
    onDragOverEnd,
    onDropAtOver,
    onDropColumn,
    isOver,
    isOverEnd,
} = useCardDnD({ columns });

// Card editor
const { edit, openEdit, closeEdit, saveEdit, removeCard } = useCardEditor({ columns });

// Route -> load board + subscribe project channel
watch(
    () => route.params.id,
    async (id) => {
        if (!id) return;

        const projectId = Number(id);
        if (!projectId || Number.isNaN(projectId)) return;
        if (project.value?.id === projectId) return;

        await loadBoard(projectId);

        ensureEcho();
        if (project.value?.id) subscribeProjectRealtime(project.value.id);
    },
    { immediate: true }
);

onMounted(async () => {
    const ok = await ensureAuth();
    if (!ok) return;

    loading.value = true;

    await loadProjects();

    ensureEcho();
    if (auth.user?.id) subscribeBoardsRealtime(auth.user.id);

    if (!route.params.id) {
        const first = projects.value[0];
        if (first) router.replace({ name: "board", params: { id: first.id } });
        else {
            project.value = null;
            columns.value = [];
        }
    }

    loading.value = false;
});

onBeforeUnmount(() => {
    disconnectAll();
});

// --- LÓGICA DE HARVIS (IA) ---
function buildAiNotice(payload) {
    if (!payload || typeof payload !== "object") return "";

    const warnings = [];
    if (payload.context_truncated) {
        warnings.push(payload.context_warning || "El contexto enviado a la IA fue truncado.");
    }

    const failed = payload.failed_operations || [];
    if (Array.isArray(failed) && failed.length) {
        const preview = failed
            .slice(0, 3)
            .map((item) => `#${item.index}: ${item.reason}`)
            .join(" | ");
        warnings.push(`Operaciones fallidas: ${preview}`);
    }

    return warnings.join("\n");
}

async function handleAiGeneration({ columnId, prompt }) {
    if (!prompt.trim()) return;

    try {
        // console.log("🤖 Harvis pensando...");

        const response = await http.post('/api/ai/handle', {
            column_id: columnId,
            prompt: prompt
        });

        const { action } = response.data;

        // Si Harvis creó tareas o reordenó, recargamos el tablero
        if (action === 'reordered' || action === 'created') {
            await loadBoard(project.value.id);
        }

        const notice = buildAiNotice(response.data);
        if (notice) {
            alert(notice);
        }

    } catch (error) {
        console.error("Error con Harvis AI:", error);
        const status = error?.response?.status;
        if (status === 429) {
            alert("Harvis esta en limite de cuota. Intenta mas tarde.");
            return;
        }
        const msg = error?.response?.data?.message || "Harvis tuvo un problema procesando tu solicitud.";
        alert(msg);
    }
}
</script>

<style></style>
