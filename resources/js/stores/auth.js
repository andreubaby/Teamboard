import { defineStore } from "pinia";
import { http } from "../lib/http";

export const useAuthStore = defineStore("auth", {
    state: () => ({
        user: null,
        loading: false,
        error: null,
    }),

    actions: {
        async csrf() {
            // Necesario para Sanctum cookie-based auth
            await http.get("/sanctum/csrf-cookie");
        },

        async fetchUser() {
            try {
                const { data } = await http.get("/api/user");
                this.user = data;
                return data;
            } catch (e) {
                // 401 = NO logueado (normal al cargar la app)
                if (e?.response?.status === 401) {
                    this.user = null;
                    return null;
                }
                // cualquier otro error sí es importante
                throw e;
            }
        },

        async login(email, password, remember = false) {
            this.loading = true;
            this.error = null;

            try {
                await this.csrf();

                await http.post("/api/login", {
                    email,
                    password,
                    remember,
                });

                // tras login, esto debe devolver 200
                await this.fetchUser();

                return true;
            } catch (e) {
                const msg =
                    e?.response?.data?.message ??
                    (e?.response?.status ? `HTTP ${e.response.status}` : "Login error");
                this.error = msg;
                throw e;
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            this.loading = true;
            this.error = null;

            try {
                await http.post("/api/logout");
                this.user = null;
                return true;
            } catch (e) {
                // si ya no hay sesión, lo tratamos como logout ok
                if (e?.response?.status === 401) {
                    this.user = null;
                    return true;
                }
                throw e;
            } finally {
                this.loading = false;
            }
        },
    },
});
