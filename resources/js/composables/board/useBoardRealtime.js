import { ref } from "vue";
import { makeEcho } from "../../lib/echo";
import { normalizePositions } from "../../utils/kanban";
import { attachEchoToHttp } from "../../lib/http";

export function useBoardRealtime({ auth, projects, project, columns, loadBoard }) {
    const echoRef = ref(null);
    let subscribedProjectId = null;
    let subscribedBoardsUserId = null;

    function ensureEcho() {
        if (!echoRef.value) {
            echoRef.value = makeEcho();
            attachEchoToHttp(echoRef.value);
        }
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
            .listen(".ColumnReordered", (e) => applyRemoteColumnReordered(e))
            .listen(".CardUpdated", (e) => applyRemoteCardUpdated(e))
            .listen(".CardDeleted", (e) => applyRemoteCardDeleted(e))
            // 👇 NUEVO: Escuchamos cuando se añade un comentario
            .listen(".CommentAdded", (e) => applyRemoteCommentAdded(e))
            .listen(".BoardRefreshed", async () => {
                console.log("♻️ BoardRefreshed recibido. Recargando...");
                if (loadBoard && subscribedProjectId) {
                    await loadBoard(subscribedProjectId);
                }
            });
    }

    // --- Handlers ---

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
        const col = e?.column ?? e;
        if (!col?.id) return;

        const currentProjectId = project.value?.id;
        if (!currentProjectId) return;

        if (columns.value.some((c) => Number(c.id) === Number(col.id))) return;

        columns.value.push({
            id: col.id,
            name: col.name,
            position: col.position ?? columns.value.length,
            cards: col.cards ?? [],
        });
        columns.value.sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
    }

    function applyRemoteColumnReordered(e) {
        const orderedIds = e?.orderedIds ?? e?.ordered_ids ?? [];
        if (!Array.isArray(orderedIds) || orderedIds.length === 0) return;

        const byId = new Map(columns.value.map((c) => [Number(c.id), c]));
        const next = [];

        for (const id of orderedIds) {
            const col = byId.get(Number(id));
            if (col) next.push(col);
        }

        if (next.length !== columns.value.length) {
            const orderedSet = new Set(orderedIds.map(Number));
            const missing = columns.value.filter((c) => !orderedSet.has(Number(c.id)));
            next.push(...missing);
        }

        columns.value = next;
        columns.value.forEach((c, i) => (c.position = i));
    }

    function applyRemoteCardCreated(e) {
        const payload = e?.card ?? e;
        if (!payload || !payload.id) return;

        const colId = payload.board_column_id ?? e.columnId;
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
            tags: payload.tags || [],
            priority: payload.priority || 'normal',
            assignee_id: payload.assignee_id ?? null,
            assignee: payload.assignee ?? null,
            comments: payload.comments || [], // 👇 AÑADIDO: Preparamos el array de comentarios
        });

        normalizePositions(col);
    }

    function applyRemoteCardMoved(e) {
        const cardId = e.cardId ?? e.card_id;
        const toColId = e.toColumnId ?? e.to_column_id;
        const toPos = e.toPosition ?? e.to_position ?? 0;

        if (!cardId || !toColId) return;

        let fromCol = null;
        let cardObj = null;

        for (const col of columns.value) {
            const found = col.cards.find(c => Number(c.id) === Number(cardId));
            if (found) {
                fromCol = col;
                cardObj = found;
                break;
            }
        }

        if (!fromCol || !cardObj) return;

        const idx = fromCol.cards.indexOf(cardObj);
        if (idx > -1) fromCol.cards.splice(idx, 1);
        normalizePositions(fromCol);

        const destCol = columns.value.find((c) => Number(c.id) === Number(toColId));
        if (destCol) {
            cardObj.board_column_id = Number(toColId);
            cardObj.position = toPos;

            const safeIndex = Math.max(0, Math.min(Number(toPos), destCol.cards.length));
            destCol.cards.splice(safeIndex, 0, cardObj);
            normalizePositions(destCol);
        }
    }

    function applyRemoteCardUpdated(e) {
        const cardData = e.card ?? e;
        if (!cardData || !cardData.id) return;

        console.log("📡 EVENTO RECIBIDO (CardUpdated):", cardData);

        for (const col of columns.value) {
            const card = col.cards.find(c => Number(c.id) === Number(cardData.id));
            if (card) {
                Object.assign(card, cardData);
                return;
            }
        }
    }

    function applyRemoteCardDeleted(e) {
        const cardId = e.cardId ?? e.card_id ?? e.id;
        if (!cardId) return;

        console.log("🗑️ Aplicando borrado remoto para ID:", cardId);

        for (const col of columns.value) {
            const idx = col.cards.findIndex(c => Number(c.id) === Number(cardId));
            if (idx !== -1) {
                col.cards.splice(idx, 1);
                normalizePositions(col);
                return;
            }
        }
    }

    // 👇 AÑADIDO: Manejador para nuevos comentarios recibidos por WebSocket
    function applyRemoteCommentAdded(e) {
        const comment = e.comment;
        if (!comment || !comment.card_id) return;

        console.log("💬 EVENTO RECIBIDO (CommentAdded):", comment);

        for (const col of columns.value) {
            const card = col.cards.find(c => Number(c.id) === Number(comment.card_id));
            if (card) {
                if (!card.comments) card.comments = [];
                // Solo lo insertamos si no existe ya en el array (evita duplicados si tú mismo lo enviaste)
                if (!card.comments.some(c => Number(c.id) === Number(comment.id))) {
                    card.comments.push(comment);
                }
                return;
            }
        }
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
