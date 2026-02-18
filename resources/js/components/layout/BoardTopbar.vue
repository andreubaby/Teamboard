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
            <div class="members-stack" style="position: relative;" v-if="project && project.members">

                <div
                    class="avatar-circle member-avatar-wrapper"
                    :title="'Owner: ' + (project.owner?.name || 'Unknown')"
                    :class="{ active: selectedMemberId === project.owner_id }"
                    @click="toggleFilter(project.owner_id)"
                    style="z-index: 10; border: 2px solid #fbbf24;"
                >
                    <img v-if="project.owner?.avatar_url" :src="project.owner.avatar_url" alt="Owner" />
                    <span v-else>{{ getInitials(project.owner?.name) }}</span>
                </div>

                <div
                    v-for="(member, idx) in project.members"
                    :key="member.id"
                    class="avatar-circle member-avatar-wrapper"
                    :title="member.name"
                    :class="{ active: selectedMemberId === member.id }"
                    @click="toggleFilter(member.id)"
                    :style="{ zIndex: 9 - idx }"
                >
                    <img v-if="member.avatar_url" :src="member.avatar_url" :alt="member.name" />
                    <span v-else>{{ getInitials(member.name) }}</span>

                    <button
                        v-if="isOwner"
                        class="remove-member-btn"
                        title="Eliminar miembro"
                        @click.stop="removeMember(member)"
                    >
                        ✕
                    </button>
                </div>

                <button class="add-member-btn" title="Invitar miembro" @click="showInviteInput = !showInviteInput">+</button>

                <div v-if="showInviteInput" class="invite-dropdown">
                    <input
                        ref="inviteInputRef"
                        v-model="inviteEmail"
                        type="email"
                        class="invite-input"
                        placeholder="Email del usuario..."
                        @keydown.enter="submitInvite"
                    />

                    <div class="invite-actions">
                        <button class="btn ghost" @click="showInviteInput = false" style="font-size: 11px; padding: 4px 8px;">Cancelar</button>
                        <button class="btn-invite-confirm" :disabled="inviting || !inviteEmail" @click="submitInvite">
                            {{ inviting ? '...' : 'Invitar' }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="selectedMemberId" class="filter-badge">
                Filtrando por usuario
                <button class="clear-filter" @click="toggleFilter(null)">x</button>
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
import { ref, nextTick, watch, computed } from 'vue';
import { http } from '../../lib/http';
import { apiRemoveMember } from '../../services/boardApi';
import { useAuthStore } from '../../stores/auth';

const props = defineProps({
    project: { type: Object, default: null },
    disableAddColumn: { type: Boolean, default: true },
});

const emit = defineEmits(["add-column", "refresh-board", "filter-user"]);

const auth = useAuthStore();
const isOwner = computed(() => props.project?.owner_id === auth.user?.id);

// --- User Filtering ---
const selectedMemberId = ref(null);

function getInitials(name) {
    if (!name) return "?";
    return name.substring(0, 2).toUpperCase();
}

function toggleFilter(userId) {
    if (selectedMemberId.value === userId) {
        selectedMemberId.value = null;
    } else {
        selectedMemberId.value = userId;
    }
    emit('filter-user', selectedMemberId.value);
}

// --- Member Invitation ---
const showInviteInput = ref(false);
const inviteEmail = ref('');
const inviteInputRef = ref(null);
const inviting = ref(false);

watch(showInviteInput, async (val) => {
    if (val) {
        await nextTick();
        inviteInputRef.value?.focus();
    } else {
        inviteEmail.value = '';
    }
});

async function submitInvite() {
    if (!inviteEmail.value.trim() || !props.project) return;

    inviting.value = true;
    try {
        await http.post(`/api/projects/${props.project.id}/members`, { email: inviteEmail.value });
        alert("Usuario invitado correctamente.");
        inviteEmail.value = '';
        showInviteInput.value = false;
        emit('refresh-board');
    } catch (e) {
        alert(e.response?.data?.message || "Error al invitar usuario. Verifica que el email exista.");
    } finally {
        inviting.value = false;
    }
}

// --- Member Removal ---
async function removeMember(member) {
    if (!confirm(`¿Estás seguro de que quieres eliminar a ${member.name} del tablero?`)) return;

    try {
        await apiRemoveMember(props.project.id, member.id);
        emit('refresh-board');
    } catch (e) {
        alert(e.response?.data?.message || "Error al eliminar miembro.");
    }
}

// --- Global AI (Harvis) ---
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
        const res = await http.post('/api/ai/global', {
            project_id: props.project.id,
            prompt: prompt.value
        });

        if (res.data.action === 'reordered') {
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
