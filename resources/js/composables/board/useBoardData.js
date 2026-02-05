import { ref } from "vue";
import {
    apiLoadProjects,
    apiCreateBoard,
    apiLoadBoard,
    apiCreateColumn,
} from "../../services/boardApi";

export function useBoardData({ router }) {
    const loading = ref(true);

    const projects = ref([]);
    const project = ref(null);
    const columns = ref([]);

    async function loadProjects() {
        projects.value = await apiLoadProjects();
    }

    async function loadBoard(projectId) {
        loading.value = true;
        const { project: p, columns: cols } = await apiLoadBoard(projectId);
        project.value = p;
        columns.value = cols;
        loading.value = false;
    }

    async function createBoard() {
        const name = prompt("Nombre del nuevo board:");
        if (!name || !name.trim()) return;

        const created = await apiCreateBoard(name.trim());

        // optimista (esta pestaña)
        if (!projects.value.some((x) => x.id === created.id)) {
            projects.value.unshift(created);
        }

        router.push({ name: "board", params: { id: created.id } });
    }

    async function createColumn() {
        if (!project.value?.id) return;

        const name = prompt("Nombre de la nueva columna:");
        if (!name || !name.trim()) return;

        const col = await apiCreateColumn(project.value.id, name.trim());

        if (!columns.value.some((c) => c.id === col.id)) {
            columns.value.push({
                id: col.id,
                name: col.name,
                position: col.position,
                cards: [],
            });
            columns.value.sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
        }
    }

    function goToBoard(id) {
        router.push({ name: "board", params: { id } });
    }

    return {
        loading,
        projects,
        project,
        columns,
        loadProjects,
        loadBoard,
        createBoard,
        createColumn,
        goToBoard,
    };
}
