<template>
    <div class="column" @dragover.prevent="$emit('dragover-column', $event)" @drop.prevent="$emit('drop-column')">
        <div class="column-header">
            <div
                class="col-drag-handle"
                title="Mover columna"
                @pointerdown.stop.prevent="$emit('col-pointerdown', { e: $event, columnId: column.id })"
            >
                ⋮⋮
            </div>

            <div class="header-title-group">
                <span class="col-name">{{ column.name }}</span>
                <span class="col-count">{{ column.cards.length }}</span>
            </div>

            <div class="header-actions">
                <button
                    class="mini ai-trigger"
                    type="button"
                    title="Generar tareas con IA"
                    @click="toggleAi"
                    :class="{ 'active': aiMode }"
                >
                    ✨
                </button>
                <button class="mini" type="button" @click="$emit('toggle-add')">+</button>
            </div>
        </div>

        <div v-if="aiMode" class="ai-box">
            <textarea
                ref="aiInputRef"
                v-model="aiPrompt"
                class="input ai-input"
                placeholder="Ej: Crea tareas para el Login con Google..."
                rows="2"
                @keydown.enter.prevent="submitAi"
            ></textarea>
            <div class="ai-actions">
                <span class="ai-hint">Harvis AI 🤖</span>
                <button class="mini btn-generate" @click="submitAi" :disabled="!aiPrompt.trim()">
                    Generar
                </button>
            </div>
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
                <div class="card-tags">
                    <span v-for="tag in card.tags" :key="tag.id" class="tag" :style="{ color: tag.color, backgroundColor: tag.color + '20' }">
                        {{ tag.name }}
                    </span>
                </div>

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

                <div class="card-footer-meta">
                    <div class="meta-date">
                        <div v-if="card.due_date"
                             class="due-date-badge"
                             :class="{ 'overdue': isOverdue(card.due_date) }"
                             :title="'Vence el: ' + formatFullDate(card.due_date)"
                        >
                            <span class="calendar-icon">📅</span>
                            {{ formatShortDate(card.due_date) }}
                        </div>
                    </div>

                    <div class="mini-avatar"
                         v-if="card.assignee"
                         :title="'Asignado a ' + card.assignee.name"
                         :class="{ 'has-img': card.assignee.avatar_url }"
                    >
                        <img v-if="card.assignee.avatar_url" :src="card.assignee.avatar_url" :alt="card.assignee.name">
                        <span v-else>{{ getInitials(card.assignee.name) }}</span>
                    </div>
                </div>
            </div>

            <div
                class="dropzone"
                @dragover.prevent="$emit('dragover-end', $event)"
                @drop.prevent.stop="$emit('drop-at-over')"
                :class="{ over: isOverEnd() }"
            >
                <span v-if="!column.cards.length" class="empty">Sin tareas</span>
                <span v-else class="hint">Soltar al final</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, nextTick } from "vue";

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
    "col-pointerdown",
    "generate-ai"
]);

function getInitials(name) {
    if (!name) return "?";
    return name.substring(0, 2).toUpperCase();
}

// --- Lógica IA ---
const aiMode = ref(false);
const aiPrompt = ref("");
const aiInputRef = ref(null);

function toggleAi() {
    aiMode.value = !aiMode.value;
    if (aiMode.value) {
        nextTick(() => aiInputRef.value?.focus());
    }
}

// --- Lógica de Fechas ---
function isOverdue(dateStr) {
    if (!dateStr) return false;
    return new Date(dateStr) < new Date();
}

function formatShortDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString([], { day: '2-digit', month: 'short' });
}

function formatFullDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString([], {
        dateStyle: 'short',
        timeStyle: 'short'
    });
}

function submitAi() {
    if (!aiPrompt.value.trim()) return;
    emit("generate-ai", {
        columnId: props.column.id,
        prompt: aiPrompt.value
    });
    aiPrompt.value = "";
    aiMode.value = false;
}
</script>

<style scoped>
.header-actions {
    display: flex;
    gap: 4px;
}

.ai-trigger {
    color: #fbbf24;
    background: rgba(251, 191, 36, 0.1);
    border: 1px solid rgba(251, 191, 36, 0.2);
}
.ai-trigger:hover, .ai-trigger.active {
    background: rgba(251, 191, 36, 0.2);
    box-shadow: 0 0 8px rgba(251, 191, 36, 0.4);
}

.ai-box {
    margin: 0 8px 12px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(168, 85, 247, 0.1));
    border: 1px solid rgba(168, 85, 247, 0.3);
    border-radius: 12px;
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    animation: slideDown 0.2s ease-out;
}

.ai-input {
    background: rgba(15, 23, 42, 0.6);
    border: none;
    resize: none;
    font-size: 13px;
    color: white;
}
.ai-input:focus {
    box-shadow: none;
    background: rgba(15, 23, 42, 0.8);
}

.ai-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.ai-hint {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    color: #a855f7;
    letter-spacing: 0.5px;
}

.btn-generate {
    background: linear-gradient(135deg, #6366f1, #a855f7);
    border: none;
    color: white;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
}
.btn-generate:disabled { opacity: 0.5; cursor: not-allowed; }

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ESTILOS DE FECHA Y FOOTER */
.card-footer-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
    min-height: 24px;
}

.due-date-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    background-color: rgba(255, 255, 255, 0.05);
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.2s;
}

.due-date-badge.overdue {
    background-color: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border-color: rgba(239, 68, 68, 0.3);
}

.calendar-icon {
    font-size: 11px;
    filter: grayscale(1) brightness(1.5);
}

.card:hover .due-date-badge {
    border-color: rgba(255, 255, 255, 0.2);
}
</style>
