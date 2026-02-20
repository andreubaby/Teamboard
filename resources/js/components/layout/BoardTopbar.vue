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

        <div class="search-wrapper" v-if="project">
            <div class="search-inner">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input
                    type="text"
                    :value="search"
                    @input="$emit('update:search', $event.target.value)"
                    placeholder="Buscar en este tablero..."
                    class="topbar-search-input"
                />
                <button v-if="search" class="clear-search" @click="$emit('update:search', '')">✕</button>
            </div>
        </div>

        <div class="top-actions">

            <div class="notifications-wrapper" @focusout="handleFocusOut" tabindex="0">
                <button class="btn-icon bell-btn" @click="toggleNotifications" title="Notificaciones">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span v-if="unreadCount > 0" class="badge">{{ unreadCount }}</span>
                </button>

                <div v-if="showDropdown" class="dropdown-menu">
                    <div class="dropdown-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Notificaciones</span>
                        <button v-if="notifications.length > 0" @click="markAllAsRead" style="background: none; border: none; color: #6366f1; font-size: 12px; cursor: pointer;">
                            Marcar todas leídas
                        </button>
                    </div>

                    <div class="dropdown-body">
                        <div v-if="notifications.length === 0" class="empty-noti">
                            No tienes notificaciones nuevas.
                        </div>

                        <div
                            v-for="noti in notifications"
                            :key="noti.id"
                            class="noti-item"
                            @click="markAsRead(noti.id)"
                        >
                            <img
                                v-if="getNotiData(noti).assigner_avatar"
                                :src="getNotiData(noti).assigner_avatar"
                                class="noti-avatar"
                                alt="Avatar"
                            />
                            <div v-else class="noti-avatar-placeholder">👤</div>

                            <div class="noti-content">
                                <p class="noti-text">{{ getNotiData(noti).message || 'Tienes una nueva notificación' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="divider-v"></div>
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
import { ref, nextTick, watch, computed, onMounted, onBeforeUnmount } from 'vue';
import { http } from '../../lib/http';
import { makeEcho } from "../../lib/echo";
import { apiRemoveMember } from '../../services/boardApi';
import { useAuthStore } from '../../stores/auth';

const props = defineProps({
    project: { type: Object, default: null },
    disableAddColumn: { type: Boolean, default: true },
    search: { type: String, default: "" }, // 🔍 Recibe el searchQuery de Board.vue
});

const emit = defineEmits(["add-column", "refresh-board", "filter-user", "open-card", "update:search"]);

const auth = useAuthStore();
const isOwner = computed(() => props.project?.owner_id === auth.user?.id);

// ==========================================
// 🔔 LOGICA DE NOTIFICACIONES
// ==========================================
const notifications = ref([]);
const showDropdown = ref(false);
let echoInstance = null;

const unreadCount = computed(() => notifications.value.length);

function toggleNotifications() {
    showDropdown.value = !showDropdown.value;
}

function handleFocusOut(e) {
    if (!e.currentTarget.contains(e.relatedTarget)) {
        showDropdown.value = false;
    }
}

async function fetchNotifications() {
    try {
        const { data } = await http.get('/api/notifications');
        if (Array.isArray(data)) {
            notifications.value = data;
        } else {
            notifications.value = [];
        }
    } catch (e) {
        console.error("Error cargando notificaciones", e);
        notifications.value = [];
    }
}

async function markAsRead(notiId) {
    try {
        // Buscamos la notificación en nuestro array antes de borrarla
        const noti = notifications.value.find(n => n.id === notiId);
        const data = getNotiData(noti);

        await http.post(`/api/notifications/${notiId}/read`);
        notifications.value = notifications.value.filter(n => n.id !== notiId);
        showDropdown.value = false;

        // Si la notificación tiene un card_id, avisamos para abrirla
        if (data && data.card_id) {
            emit('open-card', data.card_id);
        }
    } catch (e) {
        console.error("Error al procesar notificación", e);
    }
}

// NUEVO: Función para limpiar todas de golpe
async function markAllAsRead() {
    try {
        await http.post('/api/notifications/read-all');
        notifications.value = [];
        showDropdown.value = false;
    } catch (e) {
        console.error("Error marcando todas como leídas", e);
    }
}

function getNotiData(noti) {
    if (!noti) return {};
    if (noti.data && typeof noti.data === 'object') return noti.data;
    return noti;
}

onMounted(() => {
    fetchNotifications();

    if (auth.user?.id) {
        echoInstance = makeEcho();
        echoInstance.private(`App.Models.User.${auth.user.id}`)
            .notification((notification) => {
                // 1. Primero actualizamos la UI para que sea instantáneo
                notifications.value.unshift({
                    id: notification.id,
                    data: notification
                });

                console.log("🔔 Nueva Notificación en vivo!", notification);

                // 2. Intentamos reproducir el sonido
                // Nota: El navegador solo lo permitirá si ya has hecho clic en la página
                const audio = new Audio('/sounds/notification.mp3');
                audio.volume = 0.4; // Un volumen sutil para no asustar

                audio.play().catch(() => {
                    // Simplemente registramos un log informativo en lugar de un warn molesto
                    console.log("🔊 Sonido en espera: El navegador bloqueó el audio. Sonará tras tu primera interacción con la web.");
                });
            });
    }
});

onBeforeUnmount(() => {
    if (echoInstance && auth.user?.id) {
        echoInstance.leave(`App.Models.User.${auth.user.id}`);
    }
});

// ==========================================
// 👤 LOGICA DE MIEMBROS Y FILTROS
// ==========================================
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

async function removeMember(member) {
    if (!confirm(`¿Estás seguro de que quieres eliminar a ${member.name} del tablero?`)) return;

    try {
        await apiRemoveMember(props.project.id, member.id);
        emit('refresh-board');
    } catch (e) {
        alert(e.response?.data?.message || "Error al eliminar miembro.");
    }
}

// ==========================================
// ✨ LOGICA DE IA GLOBAL (HARVIS)
// ==========================================
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

<style scoped>
/* LOS ESTILOS QUE YA TENÍAS PARA LA CAMPANA SIGUEN VIVOS DESDE TU CSS GLOBAL/EXTERNO */
</style>
