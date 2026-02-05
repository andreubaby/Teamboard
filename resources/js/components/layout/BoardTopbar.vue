<template>
    <header class="topbar">
        <div>
            <div class="meta" v-if="project">
                Proyecto: <strong>{{ project.name }}</strong>
            </div>
            <div class="meta" v-else>Selecciona un board</div>
        </div>

        <div class="top-actions">
            <button class="btn" :disabled="disableAddColumn" @click="$emit('add-column')">
                + Columna
            </button>
        </div>
    </header>
</template>

<script setup>
defineProps({
    project: { type: Object, default: null },
    disableAddColumn: { type: Boolean, default: true },
});
defineEmits(["add-column"]);
</script>

<style scoped>

</style>
