import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../stores/auth";

import Login from "../pages/Login.vue";
import Board from "../pages/Board.vue";

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: "/signin", component: Login },
        { path: "/app", component: Board },
        { path: "/:pathMatch(.*)*", redirect: "/app" },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    if (auth.user === null) await auth.fetchUser();

    const isAuthPage = to.path === "/signin";
    const isLogged = !!auth.user;

    if (!isLogged && !isAuthPage) return "/signin";
    if (isLogged && isAuthPage) return "/app";
});

export default router;
