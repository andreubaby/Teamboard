<template>
    <aside class="sidebar" :class="{ 'is-collapsed': collapsed }">
        <div class="sb-top">
            <div class="sb-brand">
                <div class="logo">TB</div>

                <!-- Solo mostramos títulos si NO está colapsada (no ocupa espacio) -->
                <div v-if="!collapsed" class="titles">
                    <div class="name">Teamboard</div>
                    <div class="sub">Boards</div>
                </div>

                <button
                    class="sb-collapse"
                    type="button"
                    :aria-label="collapsed ? 'Expandir sidebar' : 'Colapsar sidebar'"
                    @click="$emit('toggle-collapse')"
                >
                    {{ collapsed ? "›" : "‹" }}
                </button>
            </div>

            <!-- En colapsado lo quitamos del layout para que NO “colapse hacia arriba” -->
            <button
                v-if="!collapsed"
                class="sb-btn"
                :disabled="disableCreate"
                @click="$emit('create')"
            >
                + Nuevo board
            </button>
        </div>

        <div class="sb-list">
            <button
                v-for="p in projects"
                :key="p.id"
                class="sb-item"
                :class="{ active: activeId === p.id, compact: collapsed }"
                @click="$emit('select', p.id)"
                :title="collapsed ? p.name : undefined"
            >
                <span class="dot"></span>
                <span v-if="!collapsed" class="sb-item-name">{{ p.name }}</span>
            </button>

            <div v-if="!projects.length && !loading && !collapsed" class="sb-empty">
                No tienes boards aún.
            </div>
        </div>

        <div class="sb-bottom">
            <button class="sb-btn danger" @click="$emit('logout')">
                <span v-if="!collapsed">Salir</span>
                <span v-else title="Salir">⎋</span>
            </button>
        </div>
    </aside>
</template>

<script setup>
defineProps({
    projects: { type: Array, default: () => [] },
    activeId: { type: Number, default: null },
    loading: { type: Boolean, default: false },
    disableCreate: { type: Boolean, default: true },
    collapsed: { type: Boolean, default: false },
});

defineEmits(["select", "logout", "create", "toggle-collapse"]);
</script>
