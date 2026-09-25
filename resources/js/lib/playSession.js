// True only when the server says a play.* request's session no longer
// exists — the dozor deleted this player mid-game. Any other failure
// (offline, validation, 500, timeout) is a real error the caller should
// still show/retry as usual.
export function isSessionGone(error) {
    return error?.response?.status === 403;
}

// Sends a deleted player's browser back to the start: the server has no
// session for them any more, so reloading lands them on the animal picker.
export function reloadForGoneSession() {
    window.location.reload();
}
