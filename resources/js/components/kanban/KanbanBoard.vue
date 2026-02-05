<template>
    <TransitionGroup name="col" tag="div" class="board" move-class="col-move">
        <div
            v-for="(column, colIdx) in columns"
            :key="column.id"
            class="col-wrap"
            @dragover.prevent="(e) => $emit('col-dragover', { index: colIdx, e })"
            @drop.prevent="$emit('col-drop')"
            :class="{ dragging: draggingColumnId === column.id }"
        >
            <KanbanColumn
                :column="column"
                :adding="addingColumnId === column.id"
                :draft="addDraft[column.id] ?? ''"
                :isOver="(idx) => isOver(column.id, idx)"
                :isOverEnd="() => isOverEnd(column.id)"

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
                @col-dragstart="() => emit('col-dragstart', column.id)"
                @col-dragend="() => emit('col-dragend')"
            />
        </div>
    </TransitionGroup>
</template>

<script setup>
import KanbanColumn from "./KanbanColumn.vue";

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

    // columnas
    "col-dragstart",
    "col-dragend",
    "col-dragover",
    "col-drop",
]);
</script>
