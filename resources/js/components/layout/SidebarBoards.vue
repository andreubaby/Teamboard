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
                    {{ collapsed ? (p.name.charAt(0) || '?').toUpperCase() : '🗂' }}
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
                    <div class="menu-item">
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

            <div
                class="user-profile"
                :class="{ 'compact': collapsed, 'active': showUserMenu }"
                :title="collapsed ? (user?.name || 'Usuario') : ''"
                @click="showUserMenu = !showUserMenu"
            >
                <div class="avatar">
                    <span>{{ userInitial }}</span>
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
import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "../../stores/auth";

const props = defineProps({
    projects: { type: Array, default: () => [] },
    activeId: { type: Number, default: null },
    loading: { type: Boolean, default: false },
    disableCreate: { type: Boolean, default: true },
    collapsed: { type: Boolean, default: false },
});

const emit = defineEmits(["select", "logout", "create", "toggle-collapse"]);

const auth = useAuthStore();
const user = computed(() => auth.user);
const userInitial = computed(() => user.value?.name?.charAt(0).toUpperCase() || "U");

// Estado del menú popup
const showUserMenu = ref(false);

function handleLogout() {
    showUserMenu.value = false;
    emit("logout");
}

// ---------------------------------------------------------
// LÓGICA DE TEMA (MODO OSCURO / CLARO)
// ---------------------------------------------------------
const isDark = ref(true); // Asumimos oscuro por defecto

function toggleTheme() {
    isDark.value = !isDark.value;

    // 1. Guardar preferencia
    localStorage.setItem('harvis_theme', isDark.value ? 'dark' : 'light');

    // 2. Aplicar cambios al DOM
    updateBodyClass();
}

function updateBodyClass() {
    if (isDark.value) {
        document.body.classList.remove('light-mode');
    } else {
        document.body.classList.add('light-mode');
    }
}

// Al cargar el componente, leemos la memoria
onMounted(() => {
    const savedTheme = localStorage.getItem('harvis_theme');
    // Si no existe o es 'dark', isDark = true. Si es 'light', isDark = false.
    isDark.value = savedTheme !== 'light';
    updateBodyClass();
});
</script>

<style scoped>
/* Aseguramos que el contenedor inferior sea relativo para
   que el popup absolute se posicione respecto a él
*/
.sb-bottom {
    margin-top: auto;
    padding: 12px;
    position: relative;
    z-index: 60; /* Asegura estar por encima de listas */
}

/* --- ESTILOS DEL POPUP (Usando Variables CSS) --- */
.user-menu-popup {
    position: absolute;
    bottom: 100%; /* Empuja hacia arriba */
    left: 12px;
    right: 12px;
    margin-bottom: 10px;

    /* Variables dinámicas definidas en main.css */
    background: var(--bg-sidebar);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);

    box-shadow: 0 -4px 20px var(--shadow-color);
    border-radius: 12px;
    padding: 6px;
    z-index: 100;
    overflow: hidden;
    min-width: 180px;
}

.user-menu-popup.popup-collapsed {
    left: 12px;
    right: auto;
    width: auto;
    min-width: max-content;
}

/* Items del menú */
.menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
    user-select: none;
}

.menu-item:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.menu-item.text-danger { color: #f87171; }
.menu-item.text-danger:hover { background: rgba(239, 68, 68, 0.1); }

.menu-divider {
    height: 1px;
    background: var(--border-color);
    margin: 4px 6px;
}

.m-icon {
    font-size: 16px;
    min-width: 20px;
    text-align: center;
}

/* Overlay invisible */
.menu-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 50;
    cursor: default;
}

/* Animaciones */
.pop-up-enter-active,
.pop-up-leave-active {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.pop-up-enter-from,
.pop-up-leave-to {
    opacity: 0;
    transform: translateY(10px) scale(0.95);
}
</style>
