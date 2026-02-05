import { ref } from "vue";
import { apiCreateCard, apiMoveCard } from "../../services/boardApi";
import { normalizePositions, findColumn } from "../../utils/kanban";

export function useCardDnD({ columns }) {
    const addingColumnId = ref(null);
    const addDraft = ref({});

    const dragging = ref(null);
    const over = ref(null);

    function toggleAdd(columnId) {
        if (addingColumnId.value === columnId) {
            addingColumnId.value = null;
            return;
        }
        addingColumnId.value = columnId;
        if (addDraft.value[columnId] === undefined) addDraft.value[columnId] = "";
    }

    function updateDraft(columnId, value) {
        addDraft.value[columnId] = value;
    }

    async function createCard(column) {
        const title = (addDraft.value[column.id] || "").trim();
        if (!title) return;

        const created = await apiCreateCard(column.id, title);

        const col = columns.value.find((c) => c.id === column.id);
        if (!col) return;

        if (!col.cards.some((c) => c.id === created.id)) {
            col.cards.push({
                id: created.id,
                title: created.title,
                description: created.description ?? null,
                position: col.cards.length,
                board_column_id: column.id,
            });
            normalizePositions(col);
        }

        addingColumnId.value = null;
        addDraft.value[column.id] = "";
    }

    function onDragStart(card, column) {
        dragging.value = { cardId: card.id, fromColumnId: column.id };
    }

    function onDragEnd() {
        over.value = null;
        dragging.value = null;
    }

    function onDragOverCard(column, idx, e) {
        if (!dragging.value) return;
        e.preventDefault();
        e.stopPropagation();

        const list = column.cards.filter((c) => c.id !== dragging.value.cardId);
        const hoveredCardId = column.cards[idx]?.id;
        const hoverIndex = list.findIndex((c) => c.id === hoveredCardId);
        if (hoverIndex === -1) return;

        const rect = e.currentTarget.getBoundingClientRect();
        const mid = rect.top + rect.height / 2;
        const insertIndex = e.clientY > mid ? hoverIndex + 1 : hoverIndex;

        over.value = { columnId: column.id, index: insertIndex };
    }

    function onDragOverEnd(column, e) {
        if (!dragging.value) return;
        e?.preventDefault?.();
        over.value = { columnId: column.id, end: true };
    }

    function onDragOverColumn(column, e) {
        if (!dragging.value) return;
        e?.preventDefault?.();
        if (e?.target?.closest?.("[data-card]")) return;
        over.value = { columnId: column.id, end: true };
    }

    function isOver(columnId, idx) {
        return over.value && over.value.columnId === columnId && over.value.index === idx;
    }

    function isOverEnd(columnId) {
        return over.value && over.value.columnId === columnId && over.value.end === true;
    }

    async function onDropAtOver() {
        if (!dragging.value || !over.value) return;

        const toColumnId = over.value.columnId;
        const col = columns.value.find((c) => c.id === toColumnId);
        if (!col) return;

        const stableListLen =
            dragging.value.fromColumnId === toColumnId
                ? col.cards.filter((c) => c.id !== dragging.value.cardId).length
                : col.cards.length;

        const rawIndex = over.value.end === true ? stableListLen : over.value.index;
        const toIndex = Math.max(0, Math.min(rawIndex, stableListLen));

        await moveOptimistic(toColumnId, toIndex);
    }

    async function onDropColumn() {
        await onDropAtOver();
    }

    function snapshot() {
        return JSON.parse(JSON.stringify({ columns: columns.value }));
    }

    function restore(snap) {
        columns.value = snap.columns;
    }

    function removeFromColumn(colId, cardId) {
        const col = findColumn(columns, colId);
        if (!col) throw new Error(`removeFromColumn: column not found col=${colId}`);

        const idx = col.cards.findIndex((c) => c.id === cardId);
        if (idx === -1) throw new Error(`removeFromColumn out of range col=${colId}`);

        const [removed] = col.cards.splice(idx, 1);
        normalizePositions(col);
        return removed;
    }

    function insertIntoColumn(colId, index, card) {
        const col = findColumn(columns, colId);
        if (!col) throw new Error(`insertIntoColumn: column not found col=${colId}`);

        const safeIndex = Math.max(0, Math.min(index, col.cards.length));
        col.cards.splice(safeIndex, 0, card);
        normalizePositions(col);
    }

    async function moveOptimistic(toColumnId, toIndex) {
        if (!dragging.value) return;

        const snap = snapshot();
        const { cardId } = dragging.value;

        const found = columns.value
            .map((c) => ({ colId: c.id, idx: c.cards.findIndex((x) => x.id === cardId) }))
            .find((x) => x.idx !== -1);

        if (!found) {
            dragging.value = null;
            over.value = null;
            return;
        }

        const realFromColId = found.colId;
        const realFromIdx = found.idx;

        const destCol = findColumn(columns, toColumnId);
        if (!destCol) {
            dragging.value = null;
            over.value = null;
            return;
        }

        const toIndexClamped = Math.max(0, Math.min(toIndex, destCol.cards.length));
        if (realFromColId === toColumnId && toIndexClamped === realFromIdx) {
            dragging.value = null;
            over.value = null;
            return;
        }

        try {
            const moved = removeFromColumn(realFromColId, cardId);
            insertIntoColumn(toColumnId, toIndexClamped, moved);
            await apiMoveCard(cardId, toColumnId, toIndexClamped);
        } catch (e) {
            restore(snap);
            throw e;
        } finally {
            dragging.value = null;
            over.value = null;
        }
    }

    return {
        addingColumnId,
        addDraft,
        toggleAdd,
        updateDraft,
        createCard,
        onDragStart,
        onDragEnd,
        onDragOverCard,
        onDragOverColumn,
        onDragOverEnd,
        onDropAtOver,
        onDropColumn,
        isOver,
        isOverEnd,
    };
}
