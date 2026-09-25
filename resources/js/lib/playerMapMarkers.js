import { avatarEmoji } from '@/lib/avatars';

// Pure: a station's visual state from its position in the route and the
// player's current target index. Mirrors PlayerHud's stateOf so the map and
// the HUD dots never disagree about what's done and what's next.
export function stationState(index, currentIndex) {
    if (index < currentIndex) return 'done';
    if (index === currentIndex) return 'current';

    return 'future';
}

// Locations paired with their marker state, in game order. Pure data (no
// Leaflet) so it can be unit-tested without a map; MapScreen turns each
// entry into a marker with the matching icon.
export function playerMapScene({ locations, currentIndex }) {
    return locations.map((location, index) => ({
        id: location.id,
        lat: location.lat,
        lng: location.lng,
        state: stationState(index, currentIndex),
    }));
}

// Player marker: a white bubble with the fixed-list avatar emoji. Only that
// emoji ever goes into the divIcon html — never the player's name.
export function playerIcon(L, avatarKey) {
    const emoji = avatarEmoji(avatarKey);

    return L.divIcon({
        className: 'play-player-marker',
        html: `<span class="play-player-marker__bubble">${emoji}</span>`,
        iconSize: [44, 44],
        iconAnchor: [22, 22],
    });
}

// Completed station: green circle with a static SVG tick. The markup is a
// fixed string with no interpolation.
export function doneStationIcon(L) {
    return L.divIcon({
        className: '',
        html:
            '<div class="play-station-done">' +
            '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#fff" stroke-width="3" ' +
            'stroke-linecap="round" stroke-linejoin="round"><polyline points="4 12 9 17 20 6"></polyline></svg>' +
            '</div>',
        iconSize: [28, 28],
        iconAnchor: [14, 14],
    });
}

// Current target: large pulsing orange marker.
export function currentStationIcon(L) {
    return L.divIcon({
        className: 'play-station-current',
        html: '<span class="play-station-current__pulse"></span><span class="play-station-current__dot"></span>',
        iconSize: [46, 46],
        iconAnchor: [23, 23],
    });
}

// Future station: small grey dot, no number (the youngest players can't read).
export function futureStationIcon(L) {
    return L.divIcon({
        className: '',
        html: '<div class="play-station-future"></div>',
        iconSize: [14, 14],
        iconAnchor: [7, 7],
    });
}

export function stationIcon(L, state) {
    if (state === 'done') return doneStationIcon(L);
    if (state === 'current') return currentStationIcon(L);

    return futureStationIcon(L);
}
