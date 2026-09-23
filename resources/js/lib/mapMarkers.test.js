import { describe, expect, test } from 'vitest';
import { mapScene, numberedIcon, playerIcon, ORANGE, TEAL } from './mapMarkers';

const fakeL = { divIcon: (options) => options };

describe('numberedIcon', () => {
    test('renders the number in a teal 30px marker', () => {
        const icon = numberedIcon(fakeL, 3);
        expect(icon.html).toContain('>3</div>');
        expect(icon.html).toContain(TEAL);
        expect(icon.iconSize).toEqual([30, 30]);
        expect(icon.iconAnchor).toEqual([15, 15]);
    });

    test('selected markers are orange', () => {
        expect(numberedIcon(fakeL, 1, { selected: true }).html).toContain(ORANGE);
    });

    test('never lets markup through', () => {
        const html = numberedIcon(fakeL, '<img src=x onerror=alert(1)>').html;
        expect(html).not.toContain('<img');
        expect(numberedIcon(fakeL, '7<b>x</b>').html).toContain('>7</div>');
    });
});

describe('playerIcon', () => {
    test('shows the animal emoji ringed in the player colour', () => {
        const icon = playerIcon(fakeL, { avatar: 'owl', color: '#e6194b' });
        expect(icon.html).toContain('\u{1F989}');
        expect(icon.html).toContain('#e6194b');
        expect(icon.iconSize).toEqual([34, 34]);
        expect(icon.iconAnchor).toEqual([17, 17]);
    });

    test('rings a stale player in grey instead of their colour', () => {
        const icon = playerIcon(fakeL, { avatar: 'owl', color: '#e6194b', stale: true });
        expect(icon.html).toContain('#9ca3af');
        expect(icon.html).not.toContain('#e6194b');
    });

    test('falls back to a neutral emoji for an unknown avatar', () => {
        expect(playerIcon(fakeL, { avatar: 'unknown', color: '#000' }).html).toContain('\u{1F642}');
    });

    test('never lets markup through via the avatar key', () => {
        const html = playerIcon(fakeL, { avatar: '<img src=x onerror=alert(1)>', color: '#000' }).html;
        expect(html).not.toContain('<img');
    });
});

const locations = [
    { id: 10, lat: 50, lng: 14, arrival_radius_m: 20 },
    { id: 11, lat: 50.1, lng: 14.1, arrival_radius_m: 30 },
    { id: 12, lat: 50.2, lng: 14.2, arrival_radius_m: 40 },
];

describe('mapScene', () => {
    test('view mode numbers every location and marks the selected one', () => {
        const scene = mapScene({ locations, selectedId: 11 });
        expect(scene.markers.map((m) => [m.id, m.number, m.selected, m.radius])).toEqual([
            [10, 1, false, 20],
            [11, 2, true, 30],
            [12, 3, false, 40],
        ]);
        expect(scene.route).toEqual([[50, 14], [50.1, 14.1], [50.2, 14.2]]);
        expect(scene.draft).toBeNull();
    });

    test('view mode ignores a draft', () => {
        expect(mapScene({ locations, draft: { lat: 1, lng: 2, arrival_radius_m: 5 } }).draft).toBeNull();
    });

    test('edit mode replaces the selected marker with the draft', () => {
        const scene = mapScene({
            locations,
            selectedId: 11,
            mode: 'edit',
            draft: { lat: 49, lng: 13, arrival_radius_m: 55 },
        });
        expect(scene.markers.map((m) => m.id)).toEqual([10, 12]);
        expect(scene.markers.map((m) => m.number)).toEqual([1, 3]);
        expect(scene.route[1]).toEqual([49, 13]);
        expect(scene.draft).toEqual({ number: 2, lat: 49, lng: 13, radius: 55 });
    });

    test('place mode has no draft marker until it has coordinates', () => {
        const scene = mapScene({
            locations,
            mode: 'place',
            draft: { lat: null, lng: null, arrival_radius_m: 25 },
        });
        expect(scene.markers).toHaveLength(3);
        expect(scene.draft).toBeNull();
    });

    test('place mode numbers the new location after the last one', () => {
        const scene = mapScene({
            locations,
            mode: 'place',
            draft: { lat: 49, lng: 13, arrival_radius_m: 25 },
        });
        expect(scene.draft).toEqual({ number: 4, lat: 49, lng: 13, radius: 25 });
        expect(scene.route).toHaveLength(3);
    });

    test('an empty game has an empty scene', () => {
        expect(mapScene({ locations: [] })).toEqual({ markers: [], route: [], draft: null });
    });
});

describe('mapScene in edit mode without a draft position', () => {
    test.each([null, { lat: null, lng: null, arrival_radius_m: 30 }])('keeps the selected marker (draft %o)', (draft) => {
        const scene = mapScene({ locations, selectedId: 11, mode: 'edit', draft });
        expect(scene.markers.map((m) => [m.id, m.selected])).toEqual([[10, false], [11, true], [12, false]]);
        expect(scene.route[1]).toEqual([50.1, 14.1]);
        expect(scene.draft).toBeNull();
    });
});
