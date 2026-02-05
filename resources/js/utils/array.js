export function moveArrayItem(arr, from, to) {
    const a = arr.slice();
    const [item] = a.splice(from, 1);
    a.splice(to, 0, item);
    return a;
}
