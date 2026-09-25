import { beforeEach, describe, expect, test, vi } from 'vitest';

const fakeStream = () => {
    const track = { readyState: 'live', enabled: true, stop: vi.fn(function () { this.readyState = 'ended'; }) };

    return { track, getVideoTracks: () => [track], getTracks: () => [track] };
};

let camera;

beforeEach(async () => {
    vi.resetModules();
    camera = await import('./cameraStream');
});

describe('cameraStream', () => {
    test('the camera is opened once and reused, so iOS asks only once', async () => {
        const media = fakeStream();
        const devices = { getUserMedia: vi.fn(async () => media) };

        await camera.acquireCamera(devices);
        camera.pauseCamera();
        expect(media.track.enabled).toBe(false);

        expect(await camera.acquireCamera(devices)).toBe(media);
        expect(devices.getUserMedia).toHaveBeenCalledTimes(1);
        expect(media.track.enabled).toBe(true);
    });

    test('parallel requests share one prompt', async () => {
        const devices = { getUserMedia: vi.fn(async () => fakeStream()) };

        const [a, b] = await Promise.all([camera.acquireCamera(devices), camera.acquireCamera(devices)]);

        expect(a).toBe(b);
        expect(devices.getUserMedia).toHaveBeenCalledTimes(1);
    });

    test('released or ended cameras are opened anew', async () => {
        const devices = { getUserMedia: vi.fn(async () => fakeStream()) };

        const first = await camera.acquireCamera(devices);
        camera.releaseCamera();
        expect(first.track.stop).toHaveBeenCalled();

        await camera.acquireCamera(devices);
        expect(devices.getUserMedia).toHaveBeenCalledTimes(2);
    });

    test('no camera API rejects', async () => {
        await expect(camera.acquireCamera(undefined)).rejects.toThrow();
    });
});
