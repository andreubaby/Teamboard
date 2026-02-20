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
            await http.get("/sanctum/csrf-cookie");
        },

        async fetchUser() {
            try {
                const { data } = await http.get("/api/user");
                this.user = data;
                return data;
            } catch (e) {
                if (e?.response?.status === 401) {
                    this.user = null;
                    return null;
                }
                throw e;
            }
        },

        async updateProfile(formData) {
            this.loading = true;
            this.error = null;

            try {
                // Petición al endpoint de Laravel
                const { data } = await http.post("/api/profile/update", formData);

                // Actualizamos el estado global del usuario.
                // Esto dispara la reactividad en todos los componentes que usen 'user'.
                this.user = data.user;

                return data;
            } catch (e) {
                if (e?.response?.status === 422 && e.response.data.errors) {
                    const errors = e.response.data.errors;
                    this.error = Object.values(errors)[0][0];
                } else {
                    this.error = e?.response?.data?.message || "Error al actualizar el perfil";
                }
                throw e;
            } finally {
                this.loading = false;
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

        async register(name, email, password, password_confirmation) {
            this.loading = true;
            this.error = null;

            try {
                await this.csrf();

                await http.post("/api/register", {
                    name,
                    email,
                    password,
                    password_confirmation,
                });

                await this.fetchUser();
                return true;
            } catch (e) {
                if (e?.response?.status === 422 && e.response.data.errors) {
                    const errors = e.response.data.errors;
                    this.error = Object.values(errors)[0][0];
                } else {
                    this.error = e?.response?.data?.message || "Error al registrarse";
                }
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
