// One back-camera stream for the whole game. iOS Safari asks for the
// camera again whenever a page starts a new capture after the previous one
// was stopped, so the stream is opened once (on the join screen or from the
// help), only paused (tracks disabled) while the AR view isn't shown, and
// really stopped when the player leaves the game.

let stream = null;
let pending = null;

const live = (candidate) => candidate?.getVideoTracks().some((track) => track.readyState === 'live');

const setEnabled = (enabled) => stream?.getVideoTracks().forEach((track) => (track.enabled = enabled));

// Resolves to a running stream (reusing the open one) or rejects when the
// camera is missing or refused.
export function acquireCamera(mediaDevices = globalThis.navigator?.mediaDevices) {
    if (live(stream)) {
        setEnabled(true);

        return Promise.resolve(stream);
    }

    if (!mediaDevices?.getUserMedia) return Promise.reject(new Error('No camera API'));

    pending ??= mediaDevices
        .getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
        .then((media) => {
            stream = media;

            return media;
        })
        .finally(() => {
            pending = null;
        });

    return pending;
}

// Keeps the capture (and so the permission) but stops sending frames.
export function pauseCamera() {
    setEnabled(false);
}

export function releaseCamera() {
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
}
