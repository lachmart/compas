// Sound and vibration feedback for the player's game. Tones are
// synthesised with WebAudio (no sound files). Everything here fails
// silently: no AudioContext (tests, old browsers), autoplay policies or a
// missing vibration API must never break the game.

let context = null;

function audioContext() {
    if (context) return context;

    const Ctor = globalThis.AudioContext ?? globalThis.webkitAudioContext;
    if (!Ctor) return null;

    context = new Ctor();

    return context;
}

// iOS/WebKit only lets an AudioContext play if it was created or resumed
// inside a user gesture. Call this synchronously from a tap handler (before
// any await) so later sounds — played after requests or timers — are heard.
// Idempotent and never throws.
export function unlockAudio() {
    try {
        const ctx = audioContext();
        if (!ctx) return;

        if (ctx.state !== 'running') {
            Promise.resolve(ctx.resume?.()).catch(() => {});
        }

        // A one-sample silent buffer: playing something inside the gesture
        // is what actually unlocks output on older iOS versions.
        const buffer = ctx.createBuffer(1, 1, 22050);
        const source = ctx.createBufferSource();
        source.buffer = buffer;
        source.connect(ctx.destination);
        source.start(0);
    } catch {
        // No WebAudio or blocked; sound stays off.
    }
}

// notes: [{ freq, at, dur }] with `at`/`dur` in seconds from now.
function playNotes(notes, type) {
    try {
        const ctx = audioContext();
        if (!ctx) return;

        if (ctx.state === 'suspended') {
            Promise.resolve(ctx.resume?.()).catch(() => {});
        }

        const now = ctx.currentTime;

        notes.forEach(({ freq, at, dur }) => {
            const start = now + at;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = type;
            osc.frequency.setValueAtTime(freq, start);
            // Quick fade in/out so the tones don't click.
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.25, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + dur);

            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(start);
            osc.stop(start + dur + 0.05);
        });
    } catch {
        // Sound is a nicety; ignore any failure.
    }
}

// Rising C-E-G-C arpeggio.
export function playSuccess() {
    playNotes(
        [
            { freq: 523.25, at: 0, dur: 0.12 },
            { freq: 659.25, at: 0.1, dur: 0.12 },
            { freq: 783.99, at: 0.2, dur: 0.12 },
            { freq: 1046.5, at: 0.3, dur: 0.3 },
        ],
        'triangle',
    );
}

// Gentle falling "uh-oh".
export function playWrong() {
    playNotes(
        [
            { freq: 330, at: 0, dur: 0.18 },
            { freq: 247, at: 0.18, dur: 0.3 },
        ],
        'sine',
    );
}

export function vibrate(pattern) {
    try {
        globalThis.navigator?.vibrate?.(pattern);
    } catch {
        // Unsupported or blocked; ignore.
    }
}

export function celebrate() {
    playSuccess();
    vibrate([60, 40, 60]);
}
