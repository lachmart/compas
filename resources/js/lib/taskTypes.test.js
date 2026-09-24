import { describe, expect, test } from 'vitest';
import { TASK_TYPE_LABELS, locationLabel, taskTypeLabel } from './taskTypes';

describe('taskTypeLabel', () => {
    test('translates all five task types', () => {
        expect(taskTypeLabel('arrival_only')).toBe('Jen dojít');
        expect(taskTypeLabel('pick_image')).toBe('Vybrat obrázek');
        expect(taskTypeLabel('photo_of_something')).toBe('Vyfotit něco');
        expect(taskTypeLabel('selfie_doing_something')).toBe('Vyfotit se při činnosti');
        expect(taskTypeLabel('password')).toBe('Zadat heslo');
    });

    test('falls back to the raw value for an unknown type', () => {
        expect(taskTypeLabel('something_new')).toBe('something_new');
    });

    test('exposes exactly the five known types', () => {
        expect(Object.keys(TASK_TYPE_LABELS)).toEqual([
            'arrival_only', 'pick_image', 'photo_of_something', 'selfie_doing_something', 'password',
        ]);
    });
});

describe('locationLabel', () => {
    test('uses the name when present', () => {
        expect(locationLabel({ name: 'Studánka' }, 3)).toBe('#3 Studánka');
    });

    test('falls back to a numbered name when empty or missing', () => {
        expect(locationLabel({ name: '' }, 2)).toBe('#2 Stanoviště 2');
        expect(locationLabel({ name: null }, 1)).toBe('#1 Stanoviště 1');
        expect(locationLabel({}, 4)).toBe('#4 Stanoviště 4');
    });
});
