import { ref } from "vue";
import { apiUpdateCard, apiDeleteCard, apiLoadTags } from "../../services/boardApi";

export function useCardEditor({ columns }) {
    const edit = ref({
        open: false,
        saving: false,
        cardId: null,
        columnId: null,
        title: "",
        description: "",
        priority: "normal",
        tagIds: [],
    });

    const availableTags = ref([]);

    // Load tags once
    async function loadAllTags() {
        if (availableTags.value.length === 0) {
            try {
                availableTags.value = await apiLoadTags();
            } catch (e) {
                console.error("Error loading tags", e);
            }
        }
    }
    loadAllTags();

    function openEdit(card, column) {
        edit.value.open = true;
        edit.value.saving = false;
        edit.value.cardId = card.id;
        edit.value.columnId = column.id;
        edit.value.title = card.title;
        edit.value.description = card.description ?? "";
        edit.value.priority = card.priority ?? "normal";
        // Map tag objects to just IDs for the form
        edit.value.tagIds = card.tags ? card.tags.map(t => t.id) : [];
    }

    function closeEdit() {
        edit.value.open = false;
        edit.value.saving = false;
        edit.value.cardId = null;
        edit.value.columnId = null;
        edit.value.title = "";
        edit.value.description = "";
        edit.value.priority = "normal";
        edit.value.tagIds = [];
    }

    async function saveEdit() {
        if (!edit.value.cardId) return;

        const title = edit.value.title.trim();
        if (!title) return;

        edit.value.saving = true;
        try {
            const updatedCard = await apiUpdateCard(edit.value.cardId, {
                title,
                description: edit.value.description?.trim() || null,
                priority: edit.value.priority,
                tags: edit.value.tagIds,
            });

            const col = columns.value.find((c) => c.id === edit.value.columnId);
            if (col) {
                const c = col.cards.find((x) => x.id === edit.value.cardId);
                if (c) {
                    // Update local state completely
                    Object.assign(c, updatedCard);
                }
            }

            closeEdit();
        } catch (e) {
            console.error(e);
            alert("Error al guardar");
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

    return {
        edit,
        availableTags,
        openEdit,
        closeEdit,
        saveEdit,
        removeCard
    };
}
