import { describe, expect, test } from 'vitest';
import {
    DEFAULT_RADIUS_M,
    addState,
    deleteGameWarning,
    editState,
    hasUnsavedChanges,
    listState,
    mapModeFor,
    radiusSliderMax,
    reconcileWithLocations,
    addStateAt,
    mapClickAction,
} from './editorPanel';

const location = { id: 7, lat: 50.1, lng: 14.4, arrival_radius_m: 25, name: 'Dub' };

describe('panel states', () => {
    test('list clears selection and draft', () => {
        expect(listState()).toEqual({ panel: 'list', selectedId: null, draft: null });
    });

    test('edit selects the location and copies its position and radius', () => {
        expect(editState(location)).toEqual({
            panel: 'edit',
            selectedId: 7,
            draft: { lat: 50.1, lng: 14.4, arrival_radius_m: 25 },
        });
    });

    test('add starts with an unplaced draft and the default radius', () => {
        expect(addState()).toEqual({
            panel: 'add',
            selectedId: null,
            draft: { lat: null, lng: null, arrival_radius_m: 15 },
        });
    });

    test('each call returns a fresh draft object', () => {
        expect(addState().draft).not.toBe(addState().draft);
    });
});

test('map mode follows the panel', () => {
    expect(mapModeFor('list')).toBe('view');
    expect(mapModeFor('add')).toBe('place');
    expect(mapModeFor('edit')).toBe('edit');
});

describe('reconcileWithLocations', () => {
    test('keeps editing while the location still exists', () => {
        const state = editState(location);
        expect(reconcileWithLocations(state, [location])).toBe(state);
    });

    test('falls back to the list when the edited location is gone', () => {
        expect(reconcileWithLocations(editState(location), [])).toEqual(listState());
    });

    test('leaves list and add views alone', () => {
        const list = listState();
        const add = addState();
        expect(reconcileWithLocations(list, [])).toBe(list);
        expect(reconcileWithLocations(add, [])).toBe(add);
    });
});

test('delete game warning mentions players only when there are some', () => {
    expect(deleteGameWarning(0)).toBe('Smažou se i všechna stanoviště. Tato akce je nevratná.');
    expect(deleteGameWarning(1)).toBe('Smažou se i všechna stanoviště, 1 hráč a jejich fotky. Tato akce je nevratná.');
    expect(deleteGameWarning(3)).toBe('Smažou se i všechna stanoviště, 3 hráči a jejich fotky. Tato akce je nevratná.');
    expect(deleteGameWarning(12)).toBe('Smažou se i všechna stanoviště, 12 hráčů a jejich fotky. Tato akce je nevratná.');
});

test('radius slider max widens for legacy radii', () => {
    expect(radiusSliderMax(15)).toBe(100);
    expect(radiusSliderMax(100)).toBe(100);
    expect(radiusSliderMax(150)).toBe(150);
    expect(radiusSliderMax(null)).toBe(100);
});

describe('hasUnsavedChanges', () => {
    test('the list view never blocks', () => {
        expect(hasUnsavedChanges(listState(), null, false)).toBe(false);
        expect(hasUnsavedChanges(listState(), null, true)).toBe(false);
    });

    test('an untouched add form does not block', () => {
        expect(hasUnsavedChanges(addState(), null, false)).toBe(false);
    });

    test('an add form with a placed position blocks', () => {
        const state = { ...addState(), draft: { lat: 50, lng: 14, arrival_radius_m: 15 } };
        expect(hasUnsavedChanges(state, null, false)).toBe(true);
    });

    test('a dirty add form blocks even without a position', () => {
        expect(hasUnsavedChanges(addState(), null, true)).toBe(true);
    });

    test('an untouched edit form does not block', () => {
        expect(hasUnsavedChanges(editState(location), location, false)).toBe(false);
    });

    test('a dirty edit form blocks', () => {
        expect(hasUnsavedChanges(editState(location), location, true)).toBe(true);
    });

    test.each([
        ['lat', { lat: 50.2 }],
        ['lng', { lng: 14.5 }],
        ['radius', { arrival_radius_m: 30 }],
    ])('an edit draft with a changed %s blocks', (_, change) => {
        const state = editState(location);
        state.draft = { ...state.draft, ...change };
        expect(hasUnsavedChanges(state, location, false)).toBe(true);
    });

    test('an edit without its location only blocks when the form is dirty', () => {
        expect(hasUnsavedChanges(editState(location), null, false)).toBe(false);
        expect(hasUnsavedChanges(editState(location), null, true)).toBe(true);
    });
});

describe('addStateAt', () => {
    test('a new station placed at the given point', () => {
        expect(addStateAt(50.1, 14.2)).toEqual({
            panel: 'add',
            selectedId: null,
            draft: { lat: 50.1, lng: 14.2, arrival_radius_m: DEFAULT_RADIUS_M },
        });
    });
});

describe('mapClickAction', () => {
    test('an unplaced new station is placed by the click', () => {
        expect(mapClickAction(addState(), false)).toBe('place');
    });

    test('a placed new station is saved and the next one starts', () => {
        expect(mapClickAction(addStateAt(50, 14), false)).toBe('save-then-add');
    });

    test('an edited station with changes is saved first; stations never move by a click', () => {
        const location = { id: 3, lat: 50, lng: 14, arrival_radius_m: 15 };

        expect(mapClickAction(editState(location), true)).toBe('save-then-add');
        expect(mapClickAction(editState(location), false)).toBe('add');
    });

    test('from the list a click starts a new station', () => {
        expect(mapClickAction(listState(), false)).toBe('add');
    });
});
