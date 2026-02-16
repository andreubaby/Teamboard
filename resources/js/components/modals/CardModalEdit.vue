<template>
    <div v-if="open" class="modal-backdrop" @click.self="$emit('close')">
        <div class="modal">
            <div class="modal-title">Editar tarea</div>

            <label class="lbl">Título</label>
            <input
                :value="title"
                class="input"
                placeholder="Nombre de la tarea..."
                @input="$emit('update:title', $event.target.value)"
            />

            <label class="lbl">Prioridad</label>
            <div class="priority-selector">
                <button
                    v-for="p in priorities"
                    :key="p.value"
                    class="priority-btn"
                    :class="{ active: priority === p.value }"
                    @click="$emit('update:priority', p.value)"
                >
                    <span class="priority-dot" :style="{ background: p.color }"></span>
                    {{ p.label }}
                </button>
            </div>

            <label class="lbl">Etiquetas</label>
            <div class="tags-container">
                <button
                    v-for="tag in availableTags"
                    :key="tag.id"
                    class="tag-btn"
                    :class="{ selected: tagIds.includes(tag.id) }"
                    :style="tagIds.includes(tag.id) ? { backgroundColor: tag.color + '20', color: tag.color } : {}"
                    @click="toggleTag(tag.id)"
                >
                    {{ tag.name }}
                    <span v-if="tagIds.includes(tag.id)">✓</span>
                </button>
                <span v-if="availableTags.length === 0" style="font-size:12px; color:var(--text-secondary);">
                    No hay etiquetas disponibles.
                </span>
            </div>

            <label class="lbl">Descripción</label>
            <textarea
                :value="description"
                class="ta"
                rows="5"
                placeholder="Añade detalles..."
                @input="$emit('update:description', $event.target.value)"
            ></textarea>

            <div class="modal-actions">
                <button class="btn ghost" @click="$emit('close')">Cancelar</button>
                <button class="btn" :disabled="saving" @click="$emit('save')">
                    {{ saving ? "Guardando..." : "Guardar" }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    open: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    title: { type: String, default: "" },
    description: { type: String, default: "" },
    priority: { type: String, default: "normal" },
    tagIds: { type: Array, default: () => [] },
    availableTags: { type: Array, default: () => [] }
});

const emit = defineEmits([
    "close",
    "save",
    "update:title",
    "update:description",
    "update:priority",
    "update:tagIds"
]);

const priorities = [
    { value: 'low', label: 'Baja', color: '#9ca3af' },      // Gray
    { value: 'normal', label: 'Normal', color: '#3b82f6' }, // Blue
    { value: 'high', label: 'Alta', color: '#f59e0b' },     // Orange
    { value: 'urgent', label: 'Urgente', color: '#ef4444' } // Red
];

function toggleTag(id) {
    const newTags = [...props.tagIds];
    const index = newTags.indexOf(id);
    if (index === -1) {
        newTags.push(id);
    } else {
        newTags.splice(index, 1);
    }
    emit('update:tagIds', newTags);
}
</script>

<style scoped>
/* Estilos en 07-modal.css */
</style>
