<script setup>
import { ref, computed, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";

const router = useRouter();
const auth = useAuthStore();

const email = ref("");
const password = ref("");
const remember = ref(false);      // ✅ NUEVO
const localError = ref("");

const uiError = computed(() => localError.value || auth.error);

// ✅ opcional: limpia error al escribir
watch([email, password], () => {
    if (localError.value) localError.value = "";
    if (auth.error) auth.error = null;
});

async function submit() {
    localError.value = "";
    try {
        await auth.login(email.value, password.value, remember.value); // ✅ pasa remember
        router.push("/app");
    } catch (e) {
        const data = e?.response?.data;
        if (data?.errors) {
            localError.value = Object.values(data.errors).flat().join("\n");
        } else if (data?.message) {
            localError.value = data.message;
        } else {
            localError.value = "Login fallido";
        }
    }
}
</script>

<template>
    <div class="page">
        <span class="blob blob-1" aria-hidden="true"></span>
        <span class="blob blob-2" aria-hidden="true"></span>

        <div class="card" role="dialog" aria-label="Login">
            <div class="brand">
                <div class="logo" aria-hidden="true">TB</div>
                <div>
                    <h1 class="title">Teamboard</h1>
                    <p class="subtitle">Inicia sesión para continuar</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="form" novalidate>
                <label class="label">
                    <span>Email</span>
                    <div class="field">
                        <span class="icon" aria-hidden="true">✉️</span>
                        <input
                            v-model.trim="email"
                            type="email"
                            class="input"
                            autocomplete="email"
                            placeholder="tu@email.com"
                        />
                    </div>
                </label>

                <label class="label">
                    <span>Password</span>
                    <div class="field">
                        <span class="icon" aria-hidden="true">🔒</span>
                        <input
                            v-model="password"
                            type="password"
                            class="input"
                            autocomplete="current-password"
                            placeholder="••••••••"
                        />
                    </div>
                </label>

                <div class="row">
                    <label class="remember">
                        <input v-model="remember" type="checkbox" />
                        <span>Recordarme</span>
                    </label>

                    <a class="link" href="#" @click.prevent>¿Olvidaste tu contraseña?</a>
                </div>

                <button class="btn" :disabled="auth.loading">
                    <span v-if="auth.loading" class="spinner" aria-hidden="true"></span>
                    <span>{{ auth.loading ? "Entrando..." : "Entrar" }}</span>
                </button>

                <pre v-if="uiError" class="error">{{ uiError }}</pre>

                <p class="foot">
                    ¿No tienes cuenta?
                    <a class="link" href="#" @click.prevent>Crear una</a>
                </p>
            </form>
        </div>
    </div>
</template>

<style scoped>
.page {
    position: relative; /* <-- importante para los blobs */
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 24px;
    color: #e5e7eb;
    background:
        radial-gradient(1200px 700px at 20% 10%, rgba(99,102,241,0.30), transparent 60%),
        radial-gradient(900px 600px at 90% 30%, rgba(236,72,153,0.20), transparent 55%),
        radial-gradient(800px 600px at 40% 110%, rgba(34,197,94,0.12), transparent 55%),
        #070a16;
    overflow: hidden;
}

.blob {
    position: absolute;
    width: 520px;
    height: 520px;
    filter: blur(55px);
    opacity: 0.55;
    pointer-events: none;
}
.blob-1 {
    background: radial-gradient(circle at 30% 30%, rgba(99,102,241,0.9), transparent 60%);
    top: -220px;
    left: -160px;
}
.blob-2 {
    background: radial-gradient(circle at 60% 40%, rgba(236,72,153,0.75), transparent 60%);
    bottom: -240px;
    right: -180px;
}

.card {
    width: 100%;
    max-width: 420px;
    position: relative;
    border-radius: 20px;
    padding: 22px;
    background: rgba(17, 24, 39, 0.55);
    border: 1px solid rgba(255,255,255,0.10);
    box-shadow: 0 20px 60px rgba(0,0,0,0.55), 0 1px 0 rgba(255,255,255,0.05) inset;
    backdrop-filter: blur(12px);
}

.brand {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 14px;
}
.logo {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-weight: 800;
    letter-spacing: 0.5px;
    background: linear-gradient(135deg, rgba(99,102,241,0.95), rgba(236,72,153,0.85));
    box-shadow: 0 12px 30px rgba(99,102,241,0.25);
}

.title { margin: 0; font-size: 22px; line-height: 1.1; }
.subtitle { margin: 4px 0 0; color: rgba(229,231,235,0.72); font-size: 13px; }

.form { display: grid; gap: 12px; margin-top: 10px; }
.label { display: grid; gap: 6px; font-size: 12.5px; color: rgba(229,231,235,0.9); }

.field {
    display: grid;
    grid-template-columns: 34px 1fr;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border-radius: 14px;
    background: rgba(15, 23, 42, 0.65);
    border: 1px solid rgba(255,255,255,0.10);
    transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
}
.field:focus-within {
    border-color: rgba(99,102,241,0.75);
    box-shadow: 0 0 0 4px rgba(99,102,241,0.18);
    transform: translateY(-1px);
}

.icon {
    width: 34px;
    height: 34px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    font-size: 14px;
}

.input {
    width: 100%;
    background: transparent;
    border: none;
    color: #e5e7eb;
    outline: none;
    font-size: 14px;
}
.input::placeholder { color: rgba(229,231,235,0.45); }

.row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-top: 2px;
}
.remember {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    color: rgba(229,231,235,0.8);
}
.remember input { accent-color: #6366f1; }

.link { color: rgba(167, 174, 255, 0.95); text-decoration: none; font-size: 12.5px; }
.link:hover { text-decoration: underline; }

.btn {
    margin-top: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    border: none;
    color: white;
    border-radius: 14px;
    padding: 11px 14px;
    cursor: pointer;
    font-weight: 700;
    letter-spacing: 0.2px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    box-shadow: 0 14px 30px rgba(79,70,229,0.28), 0 1px 0 rgba(255,255,255,0.10) inset;
    transition: transform .12s ease, filter .12s ease;
}
.btn:hover { filter: brightness(1.05); transform: translateY(-1px); }
.btn:active { transform: translateY(0px); }
.btn:disabled { opacity: 0.65; cursor: not-allowed; filter: grayscale(0.2); }

.spinner {
    width: 16px;
    height: 16px;
    border-radius: 999px;
    border: 2px solid rgba(255,255,255,0.35);
    border-top-color: rgba(255,255,255,0.95);
    animation: spin 0.9s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.error {
    background: rgba(239,68,68,0.10);
    border: 1px solid rgba(239,68,68,0.35);
    padding: 10px 12px;
    border-radius: 14px;
    margin: 0;
    white-space: pre-wrap;
    font-size: 12px;
    box-shadow: 0 10px 22px rgba(0,0,0,0.25);
}
.foot {
    margin: 4px 0 0;
    font-size: 12.5px;
    color: rgba(229,231,235,0.72);
    text-align: center;
}

@media (max-width: 420px) {
    .card { padding: 18px; border-radius: 18px; }
    .row { flex-direction: column; align-items: flex-start; }
}
</style>
