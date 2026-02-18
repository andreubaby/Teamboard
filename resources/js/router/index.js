import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../stores/auth";

import Login from "../pages/Login.vue";
import Register from "../pages/Register.vue"; // <-- Importamos Register
import Board from "../pages/Board.vue";

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: "/signin", name: "signin", component: Login },

        // --- NUEVA RUTA DE REGISTRO ---
        { path: "/signup", name: "signup", component: Register },

        // Home de la app (sin id) -> Board redirige al primero
        { path: "/app", name: "boards", component: Board },

        // Board activo por id
        { path: "/app/:id", name: "board", component: Board },

        // fallback
        { path: "/:pathMatch(.*)*", redirect: "/app" },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    // Solo intentamos cargar el usuario si no lo tenemos en memoria
    if (auth.user === null) {
        try {
            await auth.fetchUser();
        } catch (e) {
            // Si falla silenciosamente (401), el store ya pone auth.user en null
        }
    }

    // Ahora consideramos TANTO login como register como páginas públicas
    const isAuthPage = to.name === "signin" || to.name === "signup";
    const isLogged = !!auth.user;

    // Si no está logueado y va a un sitio privado -> al login
    if (!isLogged && !isAuthPage) return { name: "signin" };

    // Si ya está logueado e intenta ir al login/registro -> a la app
    if (isLogged && isAuthPage) return { name: "boards" };
});

export default router;
