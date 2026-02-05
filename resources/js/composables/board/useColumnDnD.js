import { computed, ref } from "vue";
import { moveArrayItem } from "../../utils/array";
import { apiReorderColumns } from "../../services/boardApi";

export function useColumnDnD({ project, columns }) {
    const colDragging = ref(null); // { columnId }
    const liveIndex = ref(null);

    // throttle
    let raf = 0;
    let lastTo = null;

    const draggingColumnId = computed(() => colDragging.value?.columnId ?? null);

    function onColumnDragStart(columnId) {
        colDragging.value = { columnId };
        liveIndex.value = columns.value.findIndex((c) => Number(c.id) === Number(columnId));
        lastTo = liveIndex.value;
    }

    function onColumnDragEnd() {
        colDragging.value = null;
        liveIndex.value = null;
        lastTo = null;
        if (raf) cancelAnimationFrame(raf);
        raf = 0;
    }

    // ✅ swap SOLO cuando cruzas la mitad del target
    function onColumnDragOver(targetIndex, e) {
        if (!colDragging.value) return;
        e?.preventDefault?.();

        // ✅ ayuda a que el navegador permita el drop
        try { e.dataTransfer.dropEffect = "move"; } catch (_) {}

        if (raf) return;
        raf = requestAnimationFrame(() => {
            raf = 0;

            const from = liveIndex.value;
            if (from === null || from === -1) return;

            const to = Math.max(0, Math.min(targetIndex, columns.value.length - 1));
            if (to === from) return;
            if (to === lastTo) return;

            const el = e?.currentTarget;
            if (!el?.getBoundingClientRect) return;

            const rect = el.getBoundingClientRect();
            const midX = rect.left + rect.width / 2;
            const x = e.clientX ?? 0;

            const movingRight = to > from;
            const passed = movingRight ? x > midX : x < midX;
            if (!passed) return;

            columns.value = moveArrayItem(columns.value, from, to);
            columns.value.forEach((c, i) => (c.position = i));
            liveIndex.value = to;
            lastTo = to;
        });
    }


    async function onColumnDrop() {
        if (!project.value?.id) return;
        if (!colDragging.value) return;

        const snap = JSON.parse(JSON.stringify(columns.value));

        try {
            await apiReorderColumns(project.value.id, columns.value.map((c) => c.id));
        } catch (e) {
            columns.value = snap;
            throw e;
        } finally {
            onColumnDragEnd();
        }
    }

    return {
        draggingColumnId,
        onColumnDragStart,
        onColumnDragEnd,
        onColumnDragOver,
        onColumnDrop,
    };
}
