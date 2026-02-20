<template>
    <aside class="sidebar" :class="{ 'is-collapsed': collapsed }">
        <div class="sb-top">
            <div class="sb-brand">
                <div class="logo">TB</div>
                <div v-if="!collapsed" class="titles">
                    <div class="name">Teamboard</div>
                    <div class="sub">Workspace</div>
                </div>
                <button class="sb-collapse" @click="$emit('toggle-collapse')">
                    {{ collapsed ? "›" : "‹" }}
                </button>
            </div>

            <button v-if="!collapsed" class="sb-btn-new" :disabled="disableCreate" @click="$emit('create')">
                <span>+</span> Nuevo board
            </button>
        </div>

        <div class="sb-list">
            <div v-if="!collapsed" class="sb-section-title">TUS PROYECTOS</div>

            <button
                v-for="p in projects"
                :key="p.id"
                class="sb-item"
                :class="{ active: activeId === p.id, compact: collapsed }"
                @click="$emit('select', p.id)"
                :title="collapsed ? p.name : ''"
            >
                <span class="icon-board">
                    {{ collapsed ? (p.name.charAt(0) || '?').toUpperCase() : '▶︎' }}
                </span>
                <span v-if="!collapsed" class="sb-item-name">{{ p.name }}</span>
                <span v-if="activeId === p.id && !collapsed" class="active-dot"></span>
            </button>

            <div v-if="!projects.length && !collapsed" class="sb-empty">
                Sin proyectos aún.
            </div>
        </div>

        <div class="sb-bottom">
            <Transition name="pop-up">
                <div v-if="showUserMenu" class="user-menu-popup" :class="{ 'popup-collapsed': collapsed }">
                    <div class="menu-item" @click="$emit('open-profile'); showUserMenu = false;">
                        <span class="m-icon">⚙️</span>
                        <span v-if="!collapsed">Ajustes</span>
                    </div>

                    <div class="menu-item" @click="toggleTheme">
                        <span class="m-icon">{{ isDark ? '☀️' : '🌙' }}</span>
                        <span v-if="!collapsed">
                            {{ isDark ? 'Modo Claro' : 'Modo Oscuro' }}
                        </span>
                    </div>

                    <div class="menu-divider"></div>

                    <div class="menu-item text-danger" @click="handleLogout">
                        <span class="m-icon">🚪</span>
                        <span v-if="!collapsed">Cerrar sesión</span>
                    </div>
                </div>
            </Transition>

            <div v-if="showUserMenu" class="menu-overlay" @click="showUserMenu = false"></div>

            <div class="user-profile" :class="{ 'compact': collapsed, 'active': showUserMenu }" @click="showUserMenu = !showUserMenu">
                <div class="avatar-sb" :class="{ 'has-img': user?.avatar_url && !user.avatar_url.includes('ui-avatars.com') }">
                    <img
                        v-if="user?.avatar_url && !user.avatar_url.includes('ui-avatars.com')"
                        :src="user.avatar_url + (user.avatar_url.includes('?') ? '&' : '?') + 't=' + imageTimestamp"
                        class="avatar-img"
                        @error="(e) => e.target.style.display = 'none'"
                    >
                    <span v-else class="avatar-initials">{{ userInitial }}</span>
                </div>

                <div v-if="!collapsed" class="user-info">
                    <div class="u-name">{{ user?.name || 'Cargando...' }}</div>
                    <div class="u-email">{{ user?.email || '...' }}</div>
                </div>

                <div v-if="!collapsed" class="u-options">⋮</div>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useAuthStore } from "../../stores/auth";

const props = defineProps({
    projects: { type: Array, default: () => [] },
    activeId: { type: Number, default: null },
    loading: { type: Boolean, default: false },
    disableCreate: { type: Boolean, default: true },
    collapsed: { type: Boolean, default: false },
});

const emit = defineEmits(["select", "logout", "create", "toggle-collapse", "open-profile"]);

const auth = useAuthStore();
const user = computed(() => auth.user);
const userInitial = computed(() => user.value?.name?.charAt(0).toUpperCase() || "U");

const showUserMenu = ref(false);
const imageTimestamp = ref(Date.now());

// Vigilamos el avatar para forzar recarga de imagen si cambia en el modal de perfil
watch(() => user.value?.avatar_url, () => {
    imageTimestamp.value = Date.now();
});

function handleLogout() {
    showUserMenu.value = false;
    emit("logout");
}

const isDark = ref(true);

function toggleTheme() {
    isDark.value = !isDark.value;
    localStorage.setItem('harvis_theme', isDark.value ? 'dark' : 'light');
    updateBodyClass();
}

function updateBodyClass() {
    if (isDark.value) document.body.classList.remove('light-mode');
    else document.body.classList.add('light-mode');
}

onMounted(() => {
    const savedTheme = localStorage.getItem('harvis_theme');
    isDark.value = savedTheme !== 'light';
    updateBodyClass();
});
</script>

<style scoped>
/* --- FIXES CRÍTICOS PARA EL AVATAR --- */
.avatar {
    width: 32px !important;
    height: 32px !important;
    min-width: 32px !important;
    border-radius: 50% !important;
    background-color: #334155 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    overflow: hidden !important; /* Recorta la imagen en círculo */
    flex-shrink: 0 !important;
    padding: 0 !important;
}

.avatar-img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important; /* Mantiene proporción sin estirar */
    display: block !important;
}

.avatar-initials {
    font-size: 13px;
    font-weight: 700;
    color: #cbd5e1;
}

/* Ajuste para sidebar colapsado */
.user-profile.compact .avatar {
    width: 40px !important;
    height: 40px !important;
}

/* --- RESTO DE ESTILOS --- */
.sb-bottom {
    margin-top: auto;
    padding: 12px;
    position: relative;
    z-index: 60;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: rgba(125, 125, 125, 0.1);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.user-profile:hover {
    background: var(--bg-hover);
}

.user-info {
    flex: 1;
    overflow: hidden;
}

.u-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.u-email {
    font-size: 11px;
    color: var(--text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Popup Menu */
.user-menu-popup {
    position: absolute;
    bottom: 100%;
    left: 12px;
    right: 12px;
    margin-bottom: 10px;
    background: var(--bg-sidebar);
    border: 1px solid var(--border-color);
    box-shadow: 0 -4px 20px var(--shadow-color);
    border-radius: 12px;
    padding: 6px;
    z-index: 100;
}

.user-menu-popup.popup-collapsed {
    left: 12px;
    right: auto;
    min-width: max-content;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
}

.menu-item:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.menu-item.text-danger { color: #f87171; }
</style>
