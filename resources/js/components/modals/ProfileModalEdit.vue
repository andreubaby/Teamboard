<template>
    <div class="profile-card">
        <Transition name="toast">
            <div v-if="showToast" class="toast-notification" :class="toastType">
                <span class="toast-icon">{{ toastType === 'success' ? '✅' : '❌' }}</span>
                {{ toastMessage }}
            </div>
        </Transition>

        <h3 class="modal-title-alt">Mi Perfil</h3>

        <div class="avatar-edit-wrapper">
            <div class="avatar-preview-sq" @click="$refs.fileInput.click()">
                <img
                    v-if="previewUrl || user.avatar_url"
                    :src="previewUrl || (user.avatar_url + '?t=' + timestamp)"
                    alt="Avatar"
                >
                <div v-else class="initials">{{ initials }}</div>

                <div class="edit-overlay">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                </div>
            </div>
            <input type="file" ref="fileInput" hidden @change="onFileSelected" accept="image/*">
        </div>

        <div class="form">
            <div class="input-group">
                <label class="lbl">Nombre de usuario</label>
                <input v-model="form.name" class="input pro-input" type="text" placeholder="Tu nombre...">
            </div>

            <div class="input-group">
                <label class="lbl">Nueva Contraseña</label>
                <input v-model="form.password" class="input pro-input" type="password" placeholder="••••••••">
                <span class="input-hint">Dejar vacío para no cambiar</span>
            </div>

            <div class="input-group">
                <label class="lbl">Confirmar Contraseña</label>
                <input v-model="form.password_confirmation" class="input pro-input" type="password" placeholder="••••••••">
            </div>

            <button class="btn btn-pro" @click="save" :disabled="auth.loading">
                <span v-if="!auth.loading">Guardar Cambios</span>
                <span v-else class="loader-row">
                    <span class="spinner"></span> Actualizando...
                </span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const user = computed(() => auth.user);
const previewUrl = ref(null);
const timestamp = ref(Date.now()); // Estado para forzar la recarga de la imagen

// Estados para el Toast
const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref('success');

const form = ref({
    name: user.value?.name || '',
    email: user.value?.email || '',
    password: '',
    password_confirmation: '',
    avatar: null
});

const initials = computed(() => user.value?.name?.substring(0, 2).toUpperCase() || 'U');

function triggerToast(message, type = 'success') {
    toastMessage.value = message;
    toastType.value = type;
    showToast.value = true;
    setTimeout(() => { showToast.value = false; }, 3000);
}

function onFileSelected(e) {
    const file = e.target.files[0];
    if (!file) return;
    form.value.avatar = file;
    // Liberamos memoria de la URL anterior si existía
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = URL.createObjectURL(file);
}

async function save() {
    // Log 1: Verificar qué se va a enviar
    console.log("--- INICIANDO GUARDADO (FRONTEND) ---");
    console.log("Datos en el form:", {
        name: form.value.name,
        email: form.value.email,
        hasAvatar: !!form.value.avatar,
        passwordSet: !!form.value.password
    });

    const fd = new FormData();
    fd.append('name', form.value.name);
    fd.append('email', form.value.email);

    if (form.value.password) {
        fd.append('password', form.value.password);
        fd.append('password_confirmation', form.value.password_confirmation);
    }

    if (form.value.avatar) {
        fd.append('avatar', form.value.avatar);
        console.log("Archivo avatar adjunto:", form.value.avatar.name);
    }

    try {
        // Log 2: Petición en curso
        const response = await auth.updateProfile(fd);

        // Log 3: Respuesta del servidor
        console.log("🟢 RESPUESTA EXITOSA:");
        console.log("Usuario devuelto por API:", response.user);
        console.log("URL del Avatar en la respuesta:", response.user?.avatar_url);

        // Actualizamos el timestamp para forzar el refresco visual en Sidebar/Topbar
        timestamp.value = Date.now();

        // Emitir evento global si es necesario o actualizar el store localmente
        // Si usas Pinia y updateProfile ya actualiza el estado, esto debería bastar.

        triggerToast("¡Perfil actualizado correctamente!");

        // Limpiar campos sensibles
        form.value.password = '';
        form.value.password_confirmation = '';
        previewUrl.value = null;

    } catch (e) {
        // Log 4: Error detallado
        console.error("🔴 ERROR EN SAVE():");
        if (e.response) {
            console.error("Data del error:", e.response.data);
            console.error("Status del error:", e.response.status);
        } else {
            console.error("Mensaje de error:", e.message);
        }

        triggerToast(auth.error || "Error al actualizar", 'error');
    } finally {
        console.log("--- FIN DEL PROCESO ---");
    }
}
</script>
