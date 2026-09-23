import { afterEach, beforeEach, expect, test, vi } from 'vitest';

// feedback.js keeps one lazily created AudioContext in module state, so each
// test re-imports a fresh copy after stubbing the globals it needs.
const load = async () => {
    vi.resetModules();

    return import('./feedback');
};

const fakeAudioContext = ({ state = 'running', resume } = {}) => {
    const oscillators = [];
    const param = () => ({
        setValueAtTime: vi.fn(),
        exponentialRampToValueAtTime: vi.fn(),
        linearRampToValueAtTime: vi.fn(),
    });

    const Ctor = vi.fn(function () {
        this.state = state;
        this.currentTime = 0;
        this.destination = {};
        this.resume = resume ?? vi.fn(() => Promise.resolve());
        this.createOscillator = () => {
            const osc = { type: 'sine', frequency: param(), connect: vi.fn(), start: vi.fn(), stop: vi.fn() };
            oscillators.push(osc);

            return osc;
        };
        this.createGain = () => ({ gain: param(), connect: vi.fn() });
    });

    return { Ctor, oscillators };
};

beforeEach(() => {
    vi.stubGlobal('AudioContext', undefined);
    vi.stubGlobal('webkitAudioContext', undefined);
    vi.stubGlobal('navigator', {});
});

afterEach(() => {
    vi.unstubAllGlobals();
});

test('does nothing and never throws without WebAudio or vibration support', async () => {
    const { playSuccess, playWrong, vibrate, celebrate } = await load();

    expect(() => playSuccess()).not.toThrow();
    expect(() => playWrong()).not.toThrow();
    expect(() => vibrate(100)).not.toThrow();
    expect(() => celebrate()).not.toThrow();
});

test('creates the AudioContext lazily and only once', async () => {
    const { Ctor } = fakeAudioContext();
    vi.stubGlobal('AudioContext', Ctor);

    const { playSuccess, playWrong } = await load();
    expect(Ctor).not.toHaveBeenCalled();

    playSuccess();
    playWrong();
    expect(Ctor).toHaveBeenCalledTimes(1);
});

test('falls back to the prefixed webkitAudioContext', async () => {
    const { Ctor, oscillators } = fakeAudioContext();
    vi.stubGlobal('webkitAudioContext', Ctor);

    const { playSuccess } = await load();
    playSuccess();

    expect(oscillators.length).toBeGreaterThan(0);
});

test('success is a rising run of tones and wrong a falling pair', async () => {
    const { Ctor, oscillators } = fakeAudioContext();
    vi.stubGlobal('AudioContext', Ctor);
    const { playSuccess, playWrong } = await load();

    const frequencies = () => oscillators.map((osc) => osc.frequency.setValueAtTime.mock.calls[0][0]);

    playSuccess();
    const up = frequencies();
    expect(up.length).toBeGreaterThanOrEqual(3);
    expect([...up].sort((a, b) => a - b)).toEqual(up);
    oscillators.forEach((osc) => {
        expect(osc.start).toHaveBeenCalled();
        expect(osc.stop).toHaveBeenCalled();
    });

    oscillators.length = 0;
    playWrong();
    const down = frequencies();
    expect(down.length).toBe(2);
    expect(down[0]).toBeGreaterThan(down[1]);
});

test('resumes a suspended context (autoplay policy) and swallows a rejected resume', async () => {
    const resume = vi.fn(() => Promise.reject(new Error('not allowed')));
    const { Ctor } = fakeAudioContext({ state: 'suspended', resume });
    vi.stubGlobal('AudioContext', Ctor);
    const { playSuccess } = await load();

    expect(() => playSuccess()).not.toThrow();
    expect(resume).toHaveBeenCalled();
    // Let the rejection settle; an unhandled one would fail the run.
    await new Promise((resolve) => setTimeout(resolve, 0));
});

test('never throws when the AudioContext constructor or nodes blow up', async () => {
    vi.stubGlobal('AudioContext', vi.fn(() => {
        throw new Error('blocked');
    }));
    let feedback = await load();
    expect(() => feedback.playSuccess()).not.toThrow();
    expect(() => feedback.playWrong()).not.toThrow();

    const { Ctor } = fakeAudioContext();
    const Broken = vi.fn(function () {
        Ctor.call(this);
        this.createOscillator = () => {
            throw new Error('boom');
        };
    });
    vi.stubGlobal('AudioContext', Broken);
    feedback = await load();
    expect(() => feedback.playSuccess()).not.toThrow();
    expect(() => feedback.celebrate()).not.toThrow();
});

test('vibrate passes the pattern to navigator.vibrate and survives it throwing', async () => {
    const vibrateSpy = vi.fn();
    vi.stubGlobal('navigator', { vibrate: vibrateSpy });
    let feedback = await load();

    feedback.vibrate([10, 20]);
    expect(vibrateSpy).toHaveBeenCalledWith([10, 20]);

    vi.stubGlobal('navigator', {
        vibrate: () => {
            throw new Error('nope');
        },
    });
    feedback = await load();
    expect(() => feedback.vibrate(50)).not.toThrow();
});

test('vibrate is safe when there is no navigator at all', async () => {
    vi.stubGlobal('navigator', undefined);
    const { vibrate } = await load();

    expect(() => vibrate(50)).not.toThrow();
});

test('celebrate plays the success tones and vibrates 60-40-60', async () => {
    const { Ctor, oscillators } = fakeAudioContext();
    const vibrateSpy = vi.fn();
    vi.stubGlobal('AudioContext', Ctor);
    vi.stubGlobal('navigator', { vibrate: vibrateSpy });
    const { celebrate } = await load();

    celebrate();

    expect(vibrateSpy).toHaveBeenCalledWith([60, 40, 60]);
    expect(oscillators.length).toBeGreaterThanOrEqual(3);
});
