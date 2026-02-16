<template>
    <header class="topbar">
        <div class="breadcrumb">
            <div class="crumb-icon">⚡</div>
            <span class="crumb-parent">Workspace</span>
            <span class="crumb-sep">/</span>
            <span class="crumb-current" v-if="project">
                {{ project.name }}
            </span>
            <span class="crumb-placeholder" v-else>Selecciona un proyecto</span>
        </div>

        <div class="top-actions">
            <div class="members-stack" v-if="project">
                <div class="avatar-circle" style="background: #3b82f6; z-index:3">JM</div>
                <div class="avatar-circle" style="background: #10b981; z-index:2">A</div>
                <button class="add-member-btn" title="Invitar miembro">+</button>
            </div>

            <div class="divider-v"></div>

            <div class="ai-global-wrapper" v-if="project">
                <button
                    class="btn-ai-global"
                    @click="showAiInput = !showAiInput"
                    :class="{ 'active': showAiInput }"
                    title="Harvis: Comandos Globales"
                >
                    ✨ Harvis
                </button>

                <div v-if="showAiInput" class="ai-dropdown">
                    <textarea
                        v-model="prompt"
                        placeholder="Ej: Ordena todas las columnas por prioridad..."
                        rows="3"
                        class="ai-input"
                        ref="aiInputRef"
                        @keydown.enter.prevent="submitGlobalAi"
                    ></textarea>

                    <div class="ai-footer">
                        <span class="ai-label">AI COMMAND</span>
                        <button class="btn-go" @click="submitGlobalAi" :disabled="loading">
                            {{ loading ? 'Pensando...' : 'Ejecutar' }}
                        </button>
                    </div>
                </div>
            </div>

            <button
                class="btn-primary-action"
                :disabled="disableAddColumn"
                @click="$emit('add-column')"
            >
                <span class="btn-icon">+</span>
                <span>Nueva Columna</span>
            </button>
        </div>
    </header>
</template>

<script setup>
import { ref, nextTick, watch } from 'vue';
import { http } from '../../lib/http'; // Asegúrate de que la ruta es correcta según tu estructura

const props = defineProps({
    project: { type: Object, default: null },
    disableAddColumn: { type: Boolean, default: true },
});

// Añadimos 'refresh-board' para avisar al padre cuando la IA termine
const emit = defineEmits(["add-column", "refresh-board"]);

// --- Lógica de Harvis Global ---
const showAiInput = ref(false);
const prompt = ref('');
const loading = ref(false);
const aiInputRef = ref(null);

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

// Enfocar el textarea al abrir
watch(showAiInput, async (val) => {
    if (val) {
        await nextTick();
        aiInputRef.value?.focus();
    }
});

async function submitGlobalAi() {
    if (!prompt.value.trim() || !props.project?.id) return;

    loading.value = true;
    try {
        // Llamada al nuevo endpoint global
        const res = await http.post('/api/ai/global', {
            project_id: props.project.id,
            prompt: prompt.value
        });

        if (res.data.action === 'reordered') {
            // Éxito: Cerramos y avisamos para recargar
            showAiInput.value = false;
            prompt.value = '';
            emit('refresh-board');
        }

        const notice = buildAiNotice(res.data);
        if (notice) {
            alert(notice);
        }
    } catch (e) {
        console.error("Harvis Global Error:", e);
        const status = e?.response?.status;
        if (status === 429) {
            alert("Harvis esta en limite de cuota. Intenta mas tarde.");
            return;
        }
        const msg = e?.response?.data?.message || "Harvis tuvo un problema reorganizando el tablero.";
        alert(msg);
    } finally {
        loading.value = false;
    }
}
</script>
