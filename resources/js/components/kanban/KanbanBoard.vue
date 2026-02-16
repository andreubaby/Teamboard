<template>
    <TransitionGroup
        ref="boardEl"
        name="col"
        tag="div"
        class="board"
        move-class="col-move"
    >
        <div
            v-for="(column, colIdx) in columns"
            :key="column.id"
            class="col-wrap"
            :class="{ dragging: draggingColumnId === column.id }"
        >
            <KanbanColumn
                :column="column"
                :adding="addingColumnId === column.id"
                :draft="addDraft[column.id] ?? ''"
                :isOver="(idx) => isOver(column.id, idx)"
                :isOverEnd="() => isOverEnd(column.id)"
                @col-pointerdown="({ e, columnId }) => onColPointerDown(e, columnId)"

                @toggle-add="$emit('toggle-add', column.id)"
                @update-draft="(v) => $emit('update-draft', { columnId: column.id, value: v })"
                @create-card="$emit('create-card', column)"
                @dragover-column="(e) => $emit('dragover-column', { column, e })"
                @drop-column="$emit('drop-column', column)"
                @dragover-end="(e) => $emit('dragover-end', { column, e })"
                @drop-at-over="$emit('drop-at-over')"
                @dragstart="(payload) => $emit('dragstart', payload)"
                @dragend="$emit('dragend')"
                @dragover-card="(payload) => $emit('dragover-card', payload)"
                @open-edit="$emit('open-edit', $event)"
                @remove-card="$emit('remove-card', $event)"

                @generate-ai="(payload) => $emit('generate-ai', payload)"
            />
        </div>
    </TransitionGroup>
</template>

<script setup>
import { ref } from "vue";
import KanbanColumn from "./KanbanColumn.vue";

const boardEl = ref(null);

defineProps({
    columns: { type: Array, default: () => [] },
    addingColumnId: { type: Number, default: null },
    addDraft: { type: Object, default: () => ({}) },
    isOver: { type: Function, required: true },
    isOverEnd: { type: Function, required: true },
    draggingColumnId: { type: Number, default: null },
});

const emit = defineEmits([
    "toggle-add",
    "update-draft",
    "create-card",
    "dragover-column",
    "drop-column",
    "dragover-end",
    "drop-at-over",
    "dragstart",
    "dragend",
    "dragover-card",
    "open-edit",
    "remove-card",
    "col-pointerdown",
    // ✅ Nuevo evento para pasar la petición de IA al padre (Board.vue)
    "generate-ai"
]);

function onColPointerDown(e, columnId) {
    // ✅ PASA EL DOM REAL, no el ref
    const domElement = boardEl.value?.$el || boardEl.value;

    emit("col-pointerdown", { e, columnId, boardEl: domElement });
}
</script>
