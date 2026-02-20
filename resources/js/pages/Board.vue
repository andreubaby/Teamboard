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
            @open-profile="showProfileModal = true"
        />

        <section class="main">
            <BoardTopbar
                :project="project"
                :disableAddColumn="!project"
                @add-column="createColumn"
                @refresh-board="() => loadBoard(project.id)"
                @filter-user="setFilterUser"
                @open-card="handleOpenCardFromNoti"
                v-model:search="searchQuery"
            />

            <main class="content">
                <div v-if="loading" class="loading">Cargando...</div>

                <div v-else-if="!project" class="empty-state">
                    Selecciona un board de la izquierda.
                </div>

                <KanbanBoard
                    :columns="filteredColumns"
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
            v-model:assigneeId="edit.assigneeId"

            v-model:dueDate="edit.dueDate" :availableTags="availableTags"
            :availableMembers="[project?.owner, ...(project?.members || [])].filter(Boolean)"
            :comments="liveComments"
            @addComment="sendComment"
            @close="closeEdit"
            @save="saveEdit"
        />
    </div>
    <div v-if="showProfileModal" class="modal-backdrop" @click.self="showProfileModal = false">
        <div class="modal">
            <div class="modal-header-with-close">
                <button class="close-btn" @click="showProfileModal = false">✕</button>
            </div>
            <ProfileModalEdit @close="showProfileModal = false" />
        </div>
    </div>
</template>

<script setup>
import SidebarBoards from "../components/layout/SidebarBoards.vue";
import BoardTopbar from "../components/layout/BoardTopbar.vue";
import KanbanBoard from "../components/kanban/KanbanBoard.vue";
import CardModalEdit from "../components/modals/CardModalEdit.vue";
import { http } from "../lib/http";

import { onMounted, onBeforeUnmount, ref, watch, computed } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "../stores/auth";

import { useBoardData } from "../composables/board/useBoardData";
import { useBoardRealtime } from "../composables/board/useBoardRealtime";
import { useColumnDnD } from "../composables/board/useColumnDnD";
import { useCardDnD } from "../composables/board/useCardDnD";
import { useCardEditor } from "../composables/board/useCardEditor";
import ProfileModalEdit from "../components/modals/ProfileModalEdit.vue";

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

// --- ESTADOS DE FILTROS ---
const searchQuery = ref("");
const selectedUserId = ref(null);

const showProfileModal = ref(false);

// --- LÓGICA DE PERSISTENCIA DEL SIDEBAR ---
const storedState = localStorage.getItem('harvis_sb_collapsed');
const sidebarCollapsed = ref(storedState === 'true');

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

// --- DATA Y COMPOSABLES ---
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

const {
    ensureEcho,
    subscribeBoardsRealtime,
    subscribeProjectRealtime,
    disconnectAll,
} = useBoardRealtime({ auth, projects, project, columns, loadBoard });

const { draggingColumnId, onColumnPointerDown } = useColumnDnD({ project, columns });

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

const {
    edit,
    openEdit,
    closeEdit,
    saveEdit,
    removeCard,
    sendComment,
    availableTags
} = useCardEditor({ columns });

// --- LÓGICA DE FILTRADO UNIFICADA ---
// Filtra por usuario Y por texto simultáneamente
const filteredColumns = computed(() => {
    const hasActiveUserFilter = !!selectedUserId.value;
    const hasActiveTextFilter = !!searchQuery.value.trim();

    if (!hasActiveUserFilter && !hasActiveTextFilter) {
        return columns.value;
    }

    const query = searchQuery.value.toLowerCase().trim();

    return columns.value.map(col => ({
        ...col,
        cards: col.cards.filter(card => {
            // Validación de Usuario
            const matchesUser = !hasActiveUserFilter || card.assignee_id === selectedUserId.value;

            // Validación de Texto (Título o Descripción)
            const matchesText = !hasActiveTextFilter ||
                card.title.toLowerCase().includes(query) ||
                (card.description && card.description.toLowerCase().includes(query));

            return matchesUser && matchesText;
        })
    }));
});

// --- ACTIONS ---
function setFilterUser(userId) {
    // Si el usuario vuelve a hacer clic en el mismo, limpiamos el filtro
    if (selectedUserId.value === userId) {
        selectedUserId.value = null;
    } else {
        selectedUserId.value = userId;
    }
}

function handleOpenCardFromNoti(cardId) {
    for (const col of columns.value) {
        const card = col.cards.find(c => Number(c.id) === Number(cardId));
        if (card) {
            openEdit(card, col);
            break;
        }
    }
}

// --- WATCHERS & LIFECYCLE ---
const liveComments = computed(() => {
    if (!edit.value.cardId || !edit.value.columnId) return [];
    const col = columns.value.find(c => c.id === edit.value.columnId);
    if (!col) return [];
    const card = col.cards.find(c => c.id === edit.value.cardId);
    return card?.comments || [];
});

watch(
    () => route.params.id,
    async (id) => {
        if (!id) return;
        const projectId = Number(id);
        if (!projectId || Number.isNaN(projectId)) return;
        if (project.value?.id === projectId) return;

        await loadBoard(projectId);
        ensureEcho();
        if (project.value) subscribeProjectRealtime(project.value.id);
    },
    { immediate: true }
);

onMounted(async () => {
    const ok = await ensureAuth();
    if (!ok) return;

    loading.value = true;
    try {
        await loadProjects();
    } catch (error) {
        console.error("Error cargando proyectos:", error);
    }

    ensureEcho();
    if (auth.user?.id) {
        subscribeBoardsRealtime(auth.user.id);
    }

    if (!route.params.id) {
        const first = projects.value[0];
        if (first) {
            router.replace({ name: "board", params: { id: first.id } });
        } else {
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

<style scoped>
.page {
    display: flex;
    height: 100vh;
}

.main {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.content {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
}

.loading {
    text-align: center;
    padding: 2rem;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: #888;
}
</style>
