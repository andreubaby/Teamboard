import { ref } from "vue";
import { apiUpdateCard, apiDeleteCard } from "../../services/boardApi";

export function useCardEditor({ columns }) {
    const edit = ref({
        open: false,
        saving: false,
        cardId: null,
        columnId: null,
        title: "",
        description: "",
    });

    function openEdit(card, column) {
        edit.value.open = true;
        edit.value.saving = false;
        edit.value.cardId = card.id;
        edit.value.columnId = column.id;
        edit.value.title = card.title;
        edit.value.description = card.description ?? "";
    }

    function closeEdit() {
        edit.value.open = false;
        edit.value.saving = false;
        edit.value.cardId = null;
        edit.value.columnId = null;
        edit.value.title = "";
        edit.value.description = "";
    }

    async function saveEdit() {
        if (!edit.value.cardId) return;

        const title = edit.value.title.trim();
        if (!title) return;

        edit.value.saving = true;
        try {
            const updated = await apiUpdateCard(edit.value.cardId, {
                title,
                description: edit.value.description?.trim() || null,
            });

            const col = columns.value.find((c) => c.id === edit.value.columnId);
            if (col) {
                const c = col.cards.find((x) => x.id === edit.value.cardId);
                if (c) {
                    c.title = updated.title;
                    c.description = updated.description ?? null;
                }
            }

            closeEdit();
        } finally {
            edit.value.saving = false;
        }
    }

    async function removeCard(card, column) {
        const ok = confirm(`¿Eliminar "${card.title}"?`);
        if (!ok) return;

        const snap = JSON.parse(JSON.stringify(columns.value));
        try {
            const idx = column.cards.findIndex((c) => c.id === card.id);
            if (idx !== -1) column.cards.splice(idx, 1);
            await apiDeleteCard(card.id);
        } catch (e) {
            columns.value = snap;
            throw e;
        }
    }

    return { edit, openEdit, closeEdit, saveEdit, removeCard };
}
