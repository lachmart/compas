export function pluralize(n, one, few, many) {
    const form = n === 1 ? one : n >= 2 && n <= 4 ? few : many;

    return `${n} ${form}`;
}

export function relativeTime(iso, nowMs) {
    if (!iso) return '';

    const seconds = Math.floor((nowMs - Date.parse(iso)) / 1000);

    if (seconds < 60) return 'právě teď';
    if (seconds < 3600) return `před ${Math.floor(seconds / 60)} min`;
    if (seconds < 86400) return `před ${Math.floor(seconds / 3600)} h`;
    if (seconds < 172800) return 'včera';

    const d = new Date(iso);

    return `${d.getDate()}. ${d.getMonth() + 1}. ${d.getFullYear()}`;
}
