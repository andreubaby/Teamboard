import { computed, ref } from "vue";
import { moveArrayItem } from "../../utils/array";
import { apiReorderColumns } from "../../services/boardApi";

export function useColumnDnD({ project, columns }) {
    const colDragging = ref(null); // { columnId }
    const liveIndex = ref(null);
    let initialOrder = null;

    const draggingColumnId = computed(() => colDragging.value?.columnId ?? null);

    function start(columnId) {
        colDragging.value = { columnId };

        liveIndex.value = columns.value.findIndex(
            (c) => Number(c.id) === Number(columnId)
        );

        // Guardamos el orden inicial para comparar luego
        initialOrder = columns.value.map((c) => Number(c.id));

        document.documentElement.classList.add("dragging-columns");
    }

    function end() {
        colDragging.value = null;
        liveIndex.value = null;
        initialOrder = null;
        document.documentElement.classList.remove("dragging-columns");
    }

    function computeTargetIndex(boardEl, clientX) {
        if (!boardEl) return null;
        const wraps = Array.from(boardEl.querySelectorAll(".col-wrap"));
        if (!wraps.length) return null;

        let bestIndex = 0;
        for (let i = 0; i < wraps.length; i++) {
            const rect = wraps[i].getBoundingClientRect();
            const midX = rect.left + rect.width / 2;

            if (clientX > midX) {
                bestIndex = i + 1;
            }
        }
        return Math.max(0, Math.min(bestIndex, wraps.length - 1));
    }

    function applySwap(to) {
        const from = liveIndex.value;
        if (from === null || from === -1) return;
        if (to === from) return;

        columns.value = moveArrayItem(columns.value, from, to);
        columns.value.forEach((c, i) => (c.position = i));

        liveIndex.value = to;
    }

    // 💾 GUARDAR EN BACKEND
    async function persist() {
        if (!project.value?.id) return;

        const orderedIds = columns.value.map((c) => Number(c.id));

        // Verificar si hubo cambios reales
        if (
            Array.isArray(initialOrder) &&
            initialOrder.length === orderedIds.length &&
            initialOrder.every((id, idx) => id === orderedIds[idx])
        ) {
            return;
        }

        const snap = JSON.parse(JSON.stringify(columns.value));

        try {
            // ✅ CORRECCIÓN FINAL:
            // Como tu boardApi.js ya hace { ordered_ids: ... },
            // aquí pasamos SOLO EL ARRAY PLANO.
            await apiReorderColumns(project.value.id, orderedIds);

        } catch (e) {
            console.error("Error guardando orden de columnas:", e);
            columns.value = snap; // Rollback visual si falla
        }
    }

    function onColumnPointerDown(e, columnId, boardEl) {
        if (e.cancelable) e.preventDefault();

        start(columnId);

        try {
            e?.currentTarget?.setPointerCapture?.(e.pointerId);
        } catch (_) {}

        const onMove = (ev) => {
            if (!colDragging.value) return;
            const to = computeTargetIndex(boardEl, ev.clientX);
            if (to !== null) {
                applySwap(to);
            }
        };

        const finish = async () => {
            window.removeEventListener("pointermove", onMove);
            window.removeEventListener("pointerup", onUp);
            window.removeEventListener("pointercancel", onCancel);

            // Persistir cambios al soltar
            await persist();

            end();
        };

        const onUp = () => finish();
        const onCancel = () => finish();

        window.addEventListener("pointermove", onMove, { passive: true });
        window.addEventListener("pointerup", onUp, { passive: true });
        window.addEventListener("pointercancel", onCancel, { passive: true });
    }

    return {
        draggingColumnId,
        onColumnPointerDown,
    };
}
