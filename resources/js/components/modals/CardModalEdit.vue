<template>
    <div v-if="open" class="modal-backdrop" @click.self="$emit('close')">
        <div class="modal">
            <div class="modal-title">Editar tarea</div>

            <div class="modal-scrollable-content">
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

                <label class="lbl">Asignado a</label>
                <select
                    :value="assigneeId || ''"
                    class="input"
                    @change="$emit('update:assigneeId', $event.target.value ? Number($event.target.value) : null)"
                >
                    <option value="">Sin asignar</option>
                    <option v-for="user in availableMembers" :key="user.id" :value="user.id">
                        {{ user.name }}
                    </option>
                </select>

                <label class="lbl">Fecha de Vencimiento</label>
                <input
                    type="datetime-local"
                    :value="dueDate"
                    class="input"
                    @input="$emit('update:dueDate', $event.target.value)"
                />

                <label class="lbl">Descripción</label>
                <textarea
                    :value="description"
                    class="ta"
                    rows="4"
                    placeholder="Añade detalles..."
                    @input="$emit('update:description', $event.target.value)"
                ></textarea>

                <hr class="divider" />
                <label class="lbl">Comentarios</label>

                <div class="comments-list">
                    <div v-if="comments.length === 0" class="no-comments">
                        No hay comentarios aún. ¡Sé el primero en escribir!
                    </div>
                    <div v-for="c in comments" :key="c.id" class="comment-item">
                        <div class="comment-avatar">
                            <img v-if="c.user?.avatar_url" :src="c.user.avatar_url" alt="avatar" />
                            <span v-else>{{ getInitials(c.user?.name) }}</span>
                        </div>
                        <div class="comment-content">
                            <div class="comment-header">
                                <span class="comment-author">{{ c.user?.name || 'Usuario' }}</span>
                                <span class="comment-time">{{ formatDate(c.created_at) }}</span>
                            </div>
                            <div class="comment-text">{{ c.content }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="new-comment">
                <textarea
                    v-model="newCommentText"
                    class="ta comment-ta"
                    rows="2"
                    placeholder="Escribe un comentario y pulsa Enter..."
                    @keydown.enter.prevent="submitComment"
                ></textarea>
                <button class="btn btn-small" @click="submitComment" :disabled="!newCommentText.trim()">
                    Enviar
                </button>
            </div>

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
import { ref } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    title: { type: String, default: "" },
    description: { type: String, default: "" },
    priority: { type: String, default: "normal" },
    tagIds: { type: Array, default: () => [] },
    assigneeId: { type: Number, default: null },
    dueDate: { type: String, default: "" },
    availableTags: { type: Array, default: () => [] },
    availableMembers: { type: Array, default: () => [] },
    comments: { type: Array, default: () => [] }
});

const emit = defineEmits([
    "close",
    "save",
    "update:title",
    "update:description",
    "update:priority",
    "update:tagIds",
    "update:assigneeId",
    "update:dueDate",
    "addComment"
]);

const priorities = [
    { value: 'low', label: 'Baja', color: '#9ca3af' },
    { value: 'normal', label: 'Normal', color: '#3b82f6' },
    { value: 'high', label: 'Alta', color: '#f59e0b' },
    { value: 'urgent', label: 'Urgente', color: '#ef4444' }
];

const newCommentText = ref("");

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

function submitComment() {
    const text = newCommentText.value.trim();
    if (!text) return;
    emit("addComment", text);
    newCommentText.value = "";
}

function getInitials(name) {
    if (!name) return '?';
    return name.substring(0, 2).toUpperCase();
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
</script>
