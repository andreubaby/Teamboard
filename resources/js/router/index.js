import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../stores/auth";

import Login from "../pages/Login.vue";
import Board from "../pages/Board.vue";

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: "/signin", name: "signin", component: Login },

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
    if (auth.user === null) await auth.fetchUser();

    const isAuthPage = to.name === "signin";
    const isLogged = !!auth.user;

    if (!isLogged && !isAuthPage) return { name: "signin" };
    if (isLogged && isAuthPage) return { name: "boards" };
});

export default router;
