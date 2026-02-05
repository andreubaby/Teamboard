export function normalizePositions(col) {
    if (!col?.cards) return;
    col.cards.forEach((c, i) => (c.position = i));
}

export function findColumn(columnsRef, colId) {
    return columnsRef.value.find((c) => c.id === colId) || null;
}
