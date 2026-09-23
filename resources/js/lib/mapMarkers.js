export const TEAL = '#0d9488'; // teal-600
export const ORANGE = '#f97316'; // orange-500

// Round numbered marker. The HTML only ever contains an integer — never
// user-provided text (Leaflet would render it as HTML).
// Styles are inline on purpose: Tailwind only scans .vue files, so utility
// classes in this .js file would not be generated.
export function numberedIcon(L, number, { selected = false } = {}) {
    const n = Number.parseInt(number, 10);
    const label = Number.isFinite(n) ? n : '';
    const bg = selected ? ORANGE : TEAL;

    return L.divIcon({
        className: '',
        html: `<div style="background:${bg};width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:9999px;border:2px solid #fff;color:#fff;font-size:14px;font-weight:700;line-height:1;box-shadow:0 2px 6px rgba(0,0,0,.3);box-sizing:border-box">${label}</div>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15],
    });
}

// What GameMap draws, as plain data (no Leaflet):
// - markers: one per visible location, numbered by 1-based position; in
//   `edit` mode the selected location is hidden once the draft has a
//   position (the draft marker replaces it).
// - route: points in order; in `edit` mode the selected location follows
//   the draft position so the line tracks the dragged marker.
// - draft: the draft marker (number + position + radius) or null — only in
//   edit/place mode and only once the draft has coordinates.
export function mapScene({ locations, selectedId = null, draft = null, mode = 'view' }) {
    const editing = mode === 'edit' || mode === 'place';
    const hasDraftPos = editing && draft && draft.lat != null && draft.lng != null;
    const selectedIndex = locations.findIndex((l) => l.id === selectedId);

    const markers = [];
    const route = [];

    locations.forEach((location, index) => {
        // Only replace the selected marker once the draft has a position.
        const replaced = mode === 'edit' && hasDraftPos && index === selectedIndex;

        if (replaced) {
            route.push([draft.lat, draft.lng]);
        } else {
            route.push([location.lat, location.lng]);
        }

        if (replaced) return;

        markers.push({
            id: location.id,
            number: index + 1,
            lat: location.lat,
            lng: location.lng,
            radius: location.arrival_radius_m,
            selected: location.id === selectedId,
        });
    });

    let draftSpec = null;
    if (hasDraftPos) {
        const number = mode === 'edit' && selectedIndex !== -1 ? selectedIndex + 1 : locations.length + 1;
        draftSpec = { number, lat: draft.lat, lng: draft.lng, radius: draft.arrival_radius_m };
    }

    return { markers, route, draft: draftSpec };
}
