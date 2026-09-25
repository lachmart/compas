import { pluralize } from './format';

export const DEFAULT_RADIUS_M = 15;

// The game editor's side panel is one of three views. `draft` is the
// position + radius shared (v-model) between the location form and the map.
export const listState = () => ({ panel: 'list', selectedId: null, draft: null });

export const editState = (location) => ({
    panel: 'edit',
    selectedId: location.id,
    draft: { lat: location.lat, lng: location.lng, arrival_radius_m: location.arrival_radius_m },
});

export const addState = () => ({
    panel: 'add',
    selectedId: null,
    draft: { lat: null, lng: null, arrival_radius_m: DEFAULT_RADIUS_M },
});

// A new station already placed where the dozor clicked.
export const addStateAt = (lat, lng) => {
    const state = addState();

    return { ...state, draft: { ...state.draft, lat, lng } };
};

// What a click on an empty spot of the editor map does. Stations only move
// by dragging their marker; the click is "save and add another":
//   'place'         the new station has no position yet — put it here
//   'save-then-add' save the open form, then start a new station here
//   'add'           nothing to save — start a new station here
export function mapClickAction(state, unsavedChanges) {
    if (state.panel === 'add') return state.draft?.lat == null ? 'place' : 'save-then-add';
    if (state.panel === 'edit' && unsavedChanges) return 'save-then-add';

    return 'add';
}

export const mapModeFor = (panel) => (panel === 'list' ? 'view' : panel === 'add' ? 'place' : 'edit');

// After the locations prop changes (save, delete, reorder) keep the current
// view, unless the location being edited no longer exists.
export function reconcileWithLocations(state, locations) {
    if (state.panel === 'edit' && !locations.some((l) => l.id === state.selectedId)) return listState();

    return state;
}

export function deleteGameWarning(playersCount) {
    return playersCount > 0
        ? `Smažou se i všechna stanoviště, ${pluralize(playersCount, 'hráč', 'hráči', 'hráčů')} a jejich fotky. Tato akce je nevratná.`
        : 'Smažou se i všechna stanoviště. Tato akce je nevratná.';
}

// The radius slider covers 5–100 m, but older stations may have been saved
// with up to 200 m — widen the range rather than silently clamping them.
export const radiusSliderMax = (radius) => Math.max(100, Number(radius) || 0);

// Would switching away from the open form drop the dozor's work?
// An add form counts only once a position is placed or a field was touched.
export function hasUnsavedChanges(state, location, formDirty) {
    if (state.panel === 'add') return state.draft?.lat != null || Boolean(formDirty);
    if (state.panel !== 'edit') return false;
    if (formDirty) return true;
    if (!location || !state.draft) return false;

    const { draft } = state;

    return draft.lat !== location.lat || draft.lng !== location.lng || draft.arrival_radius_m !== location.arrival_radius_m;
}
