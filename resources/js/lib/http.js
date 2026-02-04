import axios from "axios";

const baseURL = import.meta.env.VITE_BACKEND_URL || "http://localhost";

export const http = axios.create({
    baseURL,
    withCredentials: true,
    headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
    },
});

// Hook opcional: añade X-Socket-ID cuando tengas Echo creado
let socketInterceptorAttached = false;

export function attachEchoToHttp(echoInstance) {
    if (!echoInstance || socketInterceptorAttached) return;

    http.interceptors.request.use((config) => {
        const sid = echoInstance?.socketId?.();
        if (sid) config.headers["X-Socket-ID"] = sid;
        return config;
    });

    socketInterceptorAttached = true;
}
