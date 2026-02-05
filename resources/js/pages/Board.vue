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
                    @col-dragstart="onColumnDragStart"
                    @col-dragend="onColumnDragEnd"
                    @col-dragover="({ index, e }) => onColumnDragOver(index, e)"
                    @col-drop="onColumnDrop"
                    @toggle-add="toggleAdd"
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

const sidebarCollapsed = ref(false);

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
} = useBoardRealtime({ auth, projects, project, columns });

// Column DnD
const {
    draggingColumnId,
    onColumnDragStart,
    onColumnDragEnd,
    onColumnDragOver,
    onColumnDrop,
} = useColumnDnD({ project, columns });

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
</script>

<style></style>
