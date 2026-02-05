<template>
    <div class="column" @dragover.prevent="$emit('dragover-column', $event)" @drop.prevent="$emit('drop-column')">
        <div class="column-header">
            <div
                class="col-drag-handle"
                title="Mover columna"
                draggable="true"
                @dragstart.stop="onHandleDragStart"
                @dragend.stop="$emit('col-dragend')"
            >
                ⋮⋮
            </div>

            <span>{{ column.name }}</span>
            <button class="mini" type="button" @click="$emit('toggle-add')">+</button>
        </div>

        <div v-if="adding" class="addbox">
            <input
                class="input"
                :value="draft"
                placeholder="Nueva tarea..."
                @input="$emit('update-draft', $event.target.value)"
                @keydown.enter="$emit('create-card')"
            />
            <button class="mini" @click="$emit('create-card')">Crear</button>
        </div>

        <div class="cards">
            <div
                v-for="(card, idx) in column.cards"
                :key="card.id"
                class="card"
                data-card
                draggable="true"
                @dragstart.stop="$emit('dragstart', { card, column })"
                @dragend.stop="$emit('dragend')"
                @dragover.prevent.stop="$emit('dragover-card', { column, idx, e: $event })"
                @drop.prevent.stop="$emit('drop-at-over')"
                :class="{ over: isOver(idx) }"
            >
                <div class="card-row">
                    <div class="card-title">{{ card.title }}</div>

                    <div class="card-actions">
                        <button class="icon" @click.stop="$emit('open-edit', { card, column })">✎</button>
                        <button class="icon danger" @click.stop="$emit('remove-card', { card, column })">🗑</button>
                    </div>
                </div>

                <div v-if="card.description" class="card-desc">
                    {{ card.description }}
                </div>
            </div>

            <div
                class="dropzone"
                @dragover.prevent="$emit('dragover-end', $event)"
                @drop.prevent.stop="$emit('drop-at-over')"
                :class="{ over: isOverEnd() }"
            >
                <span v-if="!column.cards.length" class="empty">Sin tareas</span>
                <span v-else class="hint">Suelta aquí para poner al final</span>
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    column: { type: Object, required: true },
    adding: { type: Boolean, default: false },
    draft: { type: String, default: "" },
    isOver: { type: Function, required: true },
    isOverEnd: { type: Function, required: true },
});

const emit = defineEmits([
    "toggle-add",
    "update-draft",
    "create-card",
    "dragover-column",
    "drop-column",
    "dragstart",
    "dragend",
    "dragover-card",
    "drop-at-over",
    "dragover-end",
    "open-edit",
    "remove-card",

    // columnas
    "col-dragstart",
    "col-dragend",
]);

function onHandleDragStart(e) {
    // ✅ esto hace que el drag ARRANQUE seguro
    try {
        const img = new Image();
        img.src =
            "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==";
        e.dataTransfer.setDragImage(img, 0, 0);

        e.dataTransfer.effectAllowed = "move";
        e.dataTransfer.dropEffect = "move";

        // ✅ obligatorio en muchos navegadores
        e.dataTransfer.setData("text/plain", String(props.column.id));
    } catch (_) {}

    emit("col-dragstart");
}
</script>
