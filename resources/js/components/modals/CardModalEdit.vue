<template>
    <div v-if="open" class="modal-backdrop" @click.self="$emit('close')">
        <div class="modal">
            <div class="modal-title">Editar tarea</div>

            <label class="lbl">Título</label>
            <input :value="title" class="input" @input="$emit('update:title', $event.target.value)" />

            <label class="lbl">Descripción</label>
            <textarea
                :value="description"
                class="ta"
                rows="4"
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
defineProps({
    open: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    title: { type: String, default: "" },
    description: { type: String, default: "" },
});
defineEmits(["close", "save", "update:title", "update:description"]);
</script>

<style scoped>

</style>
