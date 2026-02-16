<script setup>
import { ref, computed, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";

const router = useRouter();
const auth = useAuthStore();

const name = ref("");
const email = ref("");
const password = ref("");
const password_confirmation = ref("");
const localError = ref("");

const uiError = computed(() => localError.value || auth.error);

// Sanitización básica en cliente
function sanitizeInput(input) {
    if (!input) return "";
    return input.replace(/[<>]/g, "").trim();
}

watch([name, email, password, password_confirmation], () => {
    if (localError.value) localError.value = "";
    if (auth.error) auth.error = null;
});

async function submit() {
    localError.value = "";

    const cleanName = sanitizeInput(name.value);
    const cleanEmail = sanitizeInput(email.value);
    const cleanPassword = password.value;
    const cleanPasswordConf = password_confirmation.value;

    // Validación básica de frontend
    if (!cleanName) {
        localError.value = "El nombre es obligatorio.";
        return;
    }
    if (!cleanEmail || !cleanEmail.includes('@')) {
        localError.value = "Por favor, introduce un correo electrónico válido.";
        return;
    }
    if (!cleanPassword || cleanPassword.length < 8) {
        localError.value = "La contraseña debe tener al menos 8 caracteres.";
        return;
    }
    if (cleanPassword !== cleanPasswordConf) {
        localError.value = "Las contraseñas no coinciden.";
        return;
    }

    try {
        await auth.register(cleanName, cleanEmail, cleanPassword, cleanPasswordConf);
        router.push("/app");
    } catch (e) {
        // El store maneja el error y lo pone en auth.error, que se refleja en uiError
    }
}
</script>

<template>
    <div class="page">
        <div class="ambient-light"></div>

        <div class="card-glass">
            <header class="header">
                <div class="logo-box">TB</div>
                <div class="texts">
                    <h1>Teamboard</h1>
                    <p>Crea tu cuenta gratis</p>
                </div>
            </header>

            <form @submit.prevent="submit" class="form-stack">

                <div class="input-group">
                    <label>Nombre Completo</label>
                    <div class="field-wrapper">
                        <span class="field-icon">👤</span>
                        <input
                            v-model.trim="name"
                            type="text"
                            placeholder="Tu nombre"
                            autofocus
                        />
                    </div>
                </div>

                <div class="input-group">
                    <label>Correo Electrónico</label>
                    <div class="field-wrapper">
                        <span class="field-icon">✉️</span>
                        <input
                            v-model.trim="email"
                            type="email"
                            placeholder="nombre@ejemplo.com"
                        />
                    </div>
                </div>

                <div class="input-group">
                    <label>Contraseña</label>
                    <div class="field-wrapper">
                        <span class="field-icon">🔒</span>
                        <input
                            v-model="password"
                            type="password"
                            placeholder="Mínimo 8 caracteres"
                        />
                    </div>
                </div>

                <div class="input-group">
                    <label>Confirmar Contraseña</label>
                    <div class="field-wrapper">
                        <span class="field-icon">🔒</span>
                        <input
                            v-model="password_confirmation"
                            type="password"
                            placeholder="Repite la contraseña"
                        />
                    </div>
                </div>

                <button class="btn-primary" :disabled="auth.loading">
                    <span v-if="auth.loading" class="spinner"></span>
                    <span>{{ auth.loading ? "Creando cuenta..." : "Registrarse" }}</span>
                </button>

                <div v-if="uiError" class="error-banner">
                    ⚠️ {{ uiError }}
                </div>
            </form>

            <footer class="footer">
                ¿Ya tienes una cuenta?
                <router-link to="/signin">Inicia Sesión</router-link>
            </footer>
        </div>
    </div>
</template>

<style scoped>
/* LOS ESTILOS SON EXACTAMENTE LOS MISMOS QUE EN LOGIN.VUE */

.page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #0f172a;
    position: relative;
    overflow: hidden;
    font-family: 'Inter', sans-serif;
    padding: 20px;
}

.ambient-light {
    position: absolute;
    top: 50%; left: 50%;
    width: 120vw; height: 120vh;
    background:
        radial-gradient(circle at 80% 30%, rgba(99, 102, 241, 0.15), transparent 50%),
        radial-gradient(circle at 20% 70%, rgba(236, 72, 153, 0.15), transparent 50%);
    transform: translate(-50%, -50%);
    pointer-events: none;
    animation: pulse 10s ease-in-out infinite alternate;
}

.card-glass {
    width: 100%;
    max-width: 400px;
    background: rgba(30, 41, 59, 0.7);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 24px;
    padding: 32px;
    box-shadow:
        0 25px 50px -12px rgba(0, 0, 0, 0.5),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px; /* Un poco menos de margen que en login para que quepa bien */
}
.logo-box {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, #6366f1, #a855f7);
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-weight: 800;
    color: white;
    font-size: 18px;
    box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
}
.texts h1 { margin: 0; font-size: 20px; color: #f8fafc; font-weight: 600; }
.texts p { margin: 4px 0 0; font-size: 13px; color: #94a3b8; }

.form-stack { display: grid; gap: 16px; } /* Gap reducido para que el formulario no sea tan largo */

.input-group label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    color: #cbd5e1;
    margin-bottom: 6px;
    margin-left: 4px;
}

.field-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.field-icon {
    position: absolute;
    left: 14px;
    opacity: 0.6;
    font-size: 16px;
    transition: 0.2s;
}
.field-wrapper input {
    width: 100%;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    padding: 12px 16px 12px 42px;
    color: white;
    font-size: 14px;
    transition: all 0.2s ease;
}
.field-wrapper input:focus {
    outline: none;
    border-color: #818cf8;
    background: rgba(15, 23, 42, 0.9);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}
.field-wrapper input:focus + .field-icon { opacity: 1; transform: scale(1.1); }

.btn-primary {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 14px;
    background: linear-gradient(to right, #4f46e5, #7c3aed);
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.1s, filter 0.2s;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    font-size: 15px;
    margin-top: 8px; /* Margen extra arriba del botón */
}
.btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); }
.btn-primary:active { transform: translateY(1px); }
.btn-primary:disabled { opacity: 0.7; cursor: not-allowed; filter: grayscale(0.5); }

.error-banner {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
    padding: 10px;
    border-radius: 10px;
    font-size: 13px;
    text-align: center;
    border: 1px solid rgba(239, 68, 68, 0.2);
    animation: shake 0.4s ease-in-out;
}

.footer {
    margin-top: 24px;
    text-align: center;
    font-size: 13px;
    color: #64748b;
}
.footer a { color: #818cf8; font-weight: 600; text-decoration: none; transition: color 0.2s;}
.footer a:hover { color: #a5b4fc; text-decoration: underline; }

/* Animaciones */
@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes pulse {
    0% { transform: translate(-50%, -50%) scale(1); opacity: 0.6; }
    100% { transform: translate(-50%, -50%) scale(1.1); opacity: 0.9; }
}
@keyframes spin { to { transform: rotate(360deg); } }
.spinner {
    width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite;
}
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-4px); }
    75% { transform: translateX(4px); }
}
</style>
