import { describe, expect, test } from 'vitest';
import { reactive } from 'vue';
import { useImageTiles } from './useImageTiles';

const file = (name) => new File(['x'], name, { type: 'image/png' });

const setup = () => {
    const form = reactive({ existing: ['a.jpg', 'b.jpg'], picked: [file('c.png')] });
    const tiles = useImageTiles(form, {
        existingKey: 'existing',
        newKey: 'picked',
        stored: () => [{ path: 'a.jpg', url: '/a' }, { path: 'b.jpg', url: '/b' }],
    });

    return { form, ...tiles };
};

describe('useImageTiles', () => {
    test('stored pictures first, then picked files', () => {
        const { tiles } = setup();

        expect(tiles.value.map((tile) => tile.key)).toEqual(['existing:a.jpg', 'existing:b.jpg', 'new:0:c.png']);
        expect(tiles.value[1].src).toBe('/b');
        expect(tiles.value[2].src).toMatch(/^blob:/);
    });

    test('removing drops a stored picture or a picked file by position', () => {
        const { form, removeAt } = setup();

        removeAt(0);
        expect(form.existing).toEqual(['b.jpg']);

        removeAt(1);
        expect(form.picked).toEqual([]);
    });

    test('adding respects the maximum', () => {
        const { form, add } = setup();

        add([file('d.png'), file('e.png'), file('f.png')], 4);

        expect(form.picked.map((f) => f.name)).toEqual(['c.png', 'd.png']);
    });
});
