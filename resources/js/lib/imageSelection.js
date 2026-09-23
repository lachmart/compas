// When an image is removed from the combined list, keep the "correct"
// radio pointing at the same image (or clear it if that image went away).
export function correctIndexAfterRemoval(correctIndex, removedIndex) {
    if (correctIndex === null || correctIndex === undefined) return null;
    if (correctIndex === removedIndex) return null;

    return correctIndex > removedIndex ? correctIndex - 1 : correctIndex;
}
