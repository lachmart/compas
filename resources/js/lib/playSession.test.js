import { expect, test } from 'vitest';
import { isSessionGone } from './playSession';

test('a 403 response means the session is gone', () => {
    expect(isSessionGone({ response: { status: 403 } })).toBe(true);
});

test('other statuses are real errors, not a gone session', () => {
    expect(isSessionGone({ response: { status: 404 } })).toBe(false);
    expect(isSessionGone({ response: { status: 419 } })).toBe(false);
    expect(isSessionGone({ response: { status: 500 } })).toBe(false);
});

test('a network error with no response is not a gone session', () => {
    expect(isSessionGone(new Error('Network Error'))).toBe(false);
    expect(isSessionGone({})).toBe(false);
    expect(isSessionGone(undefined)).toBe(false);
});
