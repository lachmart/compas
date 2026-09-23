import { describe, expect, test } from 'vitest';
import {
    addState,
    deleteGameWarning,
    editState,
    listState,
    mapModeFor,
    radiusSliderMax,
    reconcileWithLocations,
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
