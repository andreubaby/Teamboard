<script setup>
import { ref, computed, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";

const router = useRouter();
const auth = useAuthStore();

const email = ref("");
const password = ref("");
const remember = ref(false);
const localError = ref("");

const uiError = computed(() => localError.value || auth.error);

watch([email, password], () => {
    if (localError.value) localError.value = "";
    if (auth.error) auth.error = null;
});

async function submit() {
    localError.value = "";
    try {
        await auth.login(email.value, password.value, remember.value);
        router.push("/app");
    } catch (e) {
        const data = e?.response?.data;
        localError.value = data?.message || "Credenciales incorrectas";
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
                    <p>Gestiona tus proyectos con fluidez</p>
                </div>
            </header>

            <form @submit.prevent="submit" class="form-stack">
                <div class="input-group">
                    <label>Correo Electrónico</label>
                    <div class="field-wrapper">
                        <span class="field-icon">✉️</span>
                        <input
                            v-model.trim="email"
                            type="email"
                            placeholder="nombre@ejemplo.com"
                            autofocus
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
                            placeholder="••••••••"
                        />
                    </div>
                </div>

                <div class="actions">
                    <label class="checkbox-wrapper">
                        <input v-model="remember" type="checkbox" />
                        <span>Recordarme</span>
                    </label>
                    <a href="#" class="forgot-link">¿Olvidaste la contraseña?</a>
                </div>

                <button class="btn-primary" :disabled="auth.loading">
                    <span v-if="auth.loading" class="spinner"></span>
                    <span>{{ auth.loading ? "Accediendo..." : "Iniciar Sesión" }}</span>
                </button>

                <div v-if="uiError" class="error-banner">
                    ⚠️ {{ uiError }}
                </div>
            </form>

            <footer class="footer">
                ¿Aún no tienes cuenta? <a href="#">Regístrate gratis</a>
            </footer>
        </div>
    </div>
</template>

<style scoped>
/* --- Layout & Background --- */
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

/* Luz ambiental animada */
.ambient-light {
    position: absolute;
    top: 50%; left: 50%;
    width: 120vw; height: 120vh;
    background:
        radial-gradient(circle at 20% 30%, rgba(99, 102, 241, 0.15), transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(236, 72, 153, 0.15), transparent 50%);
    transform: translate(-50%, -50%);
    pointer-events: none;
    animation: pulse 10s ease-in-out infinite alternate;
}

/* --- Glass Card --- */
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

/* --- Header --- */
.header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 32px;
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

/* --- Forms --- */
.form-stack { display: grid; gap: 20px; }

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

/* --- Actions --- */
.actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
}
.checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #94a3b8;
    cursor: pointer;
}
.forgot-link {
    color: #818cf8;
    text-decoration: none;
    transition: color 0.2s;
}
.forgot-link:hover { color: #a5b4fc; text-decoration: underline; }

/* --- Button --- */
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
}
.btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); }
.btn-primary:active { transform: translateY(1px); }
.btn-primary:disabled { opacity: 0.7; cursor: not-allowed; filter: grayscale(0.5); }

/* --- Error & Footer --- */
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
.footer a { color: #e2e8f0; font-weight: 600; text-decoration: none; }

/* --- Animations --- */
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
