import { http } from "../lib/http";

export async function apiLoadProjects() {
    const { data } = await http.get("/api/projects");
    return data.data || [];
}

export async function apiCreateBoard(name) {
    const res = await http.post("/api/projects", { name });
    return res.data.project;
}

export async function apiLoadBoard(projectId) {
    const res = await http.get(`/api/projects/${projectId}/board`);
    return { project: res.data.project, columns: res.data.columns || [] };
}

export async function apiCreateColumn(projectId, name) {
    const res = await http.post(`/api/projects/${projectId}/columns`, { name });
    return res.data.column;
}

export async function apiReorderColumns(projectId, orderedIds) {
    await http.patch(`/api/projects/${projectId}/columns/reorder`, {
        ordered_ids: orderedIds,
    });
}

export async function apiCreateCard(columnId, title) {
    const res = await http.post("/api/cards", {
        board_column_id: columnId,
        title,
    });
    return res.data.card;
}

export async function apiLoadTags() {
    const res = await http.get("/api/tags");
    return res.data;
}

// apiUpdateCard remains the same, just ensure it handles the payload correctly
export async function apiUpdateCard(cardId, payload) {
    const res = await http.patch(`/api/cards/${cardId}`, payload);
    return res.data.card;
}
export async function apiDeleteCard(cardId) {
    await http.delete(`/api/cards/${cardId}`);
}

export async function apiMoveCard(cardId, toColumnId, toPosition) {
    await http.patch(`/api/cards/${cardId}/move`, {
        to_board_column_id: toColumnId,
        to_position: toPosition,
    });
}

export async function apiRemoveMember(projectId, userId) {
    const res = await http.delete(`/api/projects/${projectId}/members/${userId}`);
    return res.data;
}

export async function apiAddComment(cardId, content) {
    const { data } = await http.post(`/api/cards/${cardId}/comments`, { content });
    return data.comment;
}
