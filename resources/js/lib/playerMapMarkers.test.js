import { describe, expect, test } from 'vitest';
import {
    playerMapScene,
    currentStationIcon,
    doneStationIcon,
    futureStationIcon,
    playerIcon,
    stationIcon,
    stationState,
} from './playerMapMarkers';

const fakeL = { divIcon: (options) => options };

describe('stationState', () => {
    test('earlier stations are done', () => {
        expect(stationState(0, 2)).toBe('done');
        expect(stationState(1, 2)).toBe('done');
    });

    test('the station at currentIndex is current', () => {
        expect(stationState(2, 2)).toBe('current');
    });

    test('later stations are future', () => {
        expect(stationState(3, 2)).toBe('future');
    });
});

const locations = [
    { id: 10, lat: 50, lng: 14 },
    { id: 11, lat: 50.1, lng: 14.1 },
    { id: 12, lat: 50.2, lng: 14.2 },
];

describe('playerMapScene', () => {
    test('marks stations before currentIndex as done, the target as current, the rest as future', () => {
        const scene = playerMapScene({ locations, currentIndex: 1 });
        expect(scene).toEqual([
            { id: 10, lat: 50, lng: 14, state: 'done' },
            { id: 11, lat: 50.1, lng: 14.1, state: 'current' },
            { id: 12, lat: 50.2, lng: 14.2, state: 'future' },
        ]);
    });

    test('currentIndex 0 has no done stations yet', () => {
        expect(playerMapScene({ locations, currentIndex: 0 }).map((s) => s.state)).toEqual([
            'current',
            'future',
            'future',
        ]);
    });

    test('an empty route is an empty scene', () => {
        expect(playerMapScene({ locations: [], currentIndex: 0 })).toEqual([]);
    });
});

describe('playerIcon', () => {
    test('renders the avatar emoji and never a name', () => {
        const icon = playerIcon(fakeL, 'fox');
        expect(icon.html).toContain('\u{1F98A}');
        expect(icon.html).not.toContain('<script');
    });

    test('falls back to a default face for an unknown avatar key', () => {
        const icon = playerIcon(fakeL, 'not-a-real-key');
        expect(icon.html).not.toContain('not-a-real-key');
    });

    test('never lets markup through the avatar key', () => {
        const icon = playerIcon(fakeL, '<img src=x onerror=alert(1)>');
        expect(icon.html).not.toContain('<img');
    });
});

describe('station icons', () => {
    test('done icon is a static tick, no interpolation', () => {
        expect(doneStationIcon(fakeL).html).toContain('<svg');
    });

    test('current icon is a pulsing marker', () => {
        expect(currentStationIcon(fakeL).html).toContain('play-station-current__pulse');
    });

    test('future icon has no number', () => {
        const html = futureStationIcon(fakeL).html;
        expect(html).not.toMatch(/>\d+</);
    });

    test('stationIcon dispatches by state', () => {
        expect(stationIcon(fakeL, 'done').html).toContain('play-station-done');
        expect(stationIcon(fakeL, 'current').html).toContain('play-station-current');
        expect(stationIcon(fakeL, 'future').html).toContain('play-station-future');
    });
});
