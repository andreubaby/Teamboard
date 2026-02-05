import { ref } from "vue";
import { makeEcho } from "../../lib/echo";
import { normalizePositions } from "../../utils/kanban";

export function useBoardRealtime({ auth, projects, project, columns }) {
    const echoRef = ref(null);
    let subscribedProjectId = null;
    let subscribedBoardsUserId = null;

    function ensureEcho() {
        if (!echoRef.value) echoRef.value = makeEcho();
    }

    function disconnectProjectChannel() {
        if (!echoRef.value || !subscribedProjectId) return;
        echoRef.value.leave(`project.${subscribedProjectId}`);
        subscribedProjectId = null;
    }

    function disconnectBoardsChannel() {
        if (!echoRef.value || !subscribedBoardsUserId) return;
        echoRef.value.leave(`boards.${subscribedBoardsUserId}`);
        subscribedBoardsUserId = null;
    }

    // OJO: esto requiere que tú emitas un evento BoardCreated en backend
    function subscribeBoardsRealtime(userId) {
        ensureEcho();
        if (subscribedBoardsUserId === userId) return;

        disconnectBoardsChannel();
        subscribedBoardsUserId = userId;

        echoRef.value
            .private(`boards.${userId}`)
            .listen(".BoardCreated", (e) => applyRemoteBoardCreated(e));
    }

    function subscribeProjectRealtime(projectId) {
        ensureEcho();
        if (subscribedProjectId === projectId) return;

        disconnectProjectChannel();
        subscribedProjectId = projectId;

        echoRef.value
            .private(`project.${projectId}`)
            .listen(".CardCreated", (e) => applyRemoteCardCreated(e))
            .listen(".CardMoved", (e) => applyRemoteCardMoved(e))
            .listen(".ColumnCreated", (e) => applyRemoteColumnCreated(e))
            .listen(".ColumnReordered", (e) => applyRemoteColumnReordered(e)); // ✅ NUEVO
    }

    function applyRemoteBoardCreated(e) {
        const p = e?.project ?? e;
        if (!p?.id) return;
        if (projects.value.some((x) => Number(x.id) === Number(p.id))) return;

        projects.value.unshift({
            id: p.id,
            name: p.name,
            owner_id: p.owner_id,
            created_at: p.created_at,
        });
    }

    function applyRemoteColumnCreated(e) {
        // ignora tu propio evento (si backend no usa toOthers)
        const senderId = e?.senderId ?? e?.sender_id ?? null;
        if (senderId && auth?.user?.id && Number(senderId) === Number(auth.user.id)) return;

        const col = e?.column ?? e;
        if (!col?.id) return;

        const current = project.value?.id;
        if (!current) return;

        // si backend manda project_id úsalo para filtrar
        if (col.project_id && Number(col.project_id) !== Number(current)) return;

        if (columns.value.some((c) => Number(c.id) === Number(col.id))) return;

        columns.value.push({
            id: col.id,
            name: col.name,
            position: col.position ?? columns.value.length,
            cards: col.cards ?? [],
        });

        columns.value.sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
        columns.value.forEach((c, i) => (c.position = i));
    }

    // ✅ NUEVO: aplicar reorder remoto
    function applyRemoteColumnReordered(e) {
        // ❌ NO ignores por senderId (en multi-tab mismo usuario lo bloquearías)

        const orderedIds = e?.orderedIds ?? e?.ordered_ids ?? [];
        if (!Array.isArray(orderedIds) || orderedIds.length === 0) return;

        const byId = new Map(columns.value.map((c) => [Number(c.id), c]));
        const next = [];

        for (const id of orderedIds) {
            const col = byId.get(Number(id));
            if (col) next.push(col);
        }

        // por si hay desync, mete las que falten al final
        if (next.length !== columns.value.length) {
            const orderedSet = new Set(orderedIds.map(Number));
            const missing = columns.value.filter((c) => !orderedSet.has(Number(c.id)));
            next.push(...missing);
        }

        columns.value = next;
        columns.value.forEach((c, i) => (c.position = i));
    }
    function applyRemoteCardCreated(e) {
        const payload = e?.card ? e.card : e;
        const colId =
            payload.board_column_id ?? e.board_column_id ?? e.column_id ?? payload.column_id;
        if (!colId) return;

        const col = columns.value.find((c) => Number(c.id) === Number(colId));
        if (!col) return;

        if (col.cards.some((c) => Number(c.id) === Number(payload.id))) return;

        const pos = Number.isInteger(payload.position) ? payload.position : col.cards.length;

        col.cards.splice(Math.max(0, Math.min(pos, col.cards.length)), 0, {
            id: payload.id,
            title: payload.title,
            description: payload.description ?? null,
            position: pos,
            board_column_id: Number(colId),
        });

        normalizePositions(col);
    }

    function applyRemoteCardMoved(e) {
        const cardId = e.card_id ?? e.cardId ?? e.id;
        const toColId = e.to_column_id ?? e.toColumnId;
        const toPos = e.to_position ?? e.toPosition ?? 0;

        if (!cardId || !toColId) return;

        const fromCol = columns.value.find((c) => c.cards.some((x) => Number(x.id) === Number(cardId)));
        if (!fromCol) return;

        const idx = fromCol.cards.findIndex((x) => Number(x.id) === Number(cardId));
        if (idx === -1) return;

        const [moved] = fromCol.cards.splice(idx, 1);
        normalizePositions(fromCol);

        const destCol = columns.value.find((c) => Number(c.id) === Number(toColId));
        if (!destCol) return;

        const safeIndex = Math.max(0, Math.min(Number(toPos) || 0, destCol.cards.length));
        destCol.cards.splice(safeIndex, 0, moved);
        normalizePositions(destCol);
    }

    function disconnectAll() {
        disconnectProjectChannel();
        disconnectBoardsChannel();
        if (echoRef.value) {
            echoRef.value.disconnect();
            echoRef.value = null;
        }
    }

    return {
        ensureEcho,
        subscribeBoardsRealtime,
        subscribeProjectRealtime,
        disconnectAll,
    };
}
