// Web Storage stand-ins for unit tests.

export const memoryStorage = () => {
    const data = new Map();

    return {
        getItem: (key) => data.get(key) ?? null,
        setItem: (key, value) => data.set(key, String(value)),
        removeItem: (key) => data.delete(key),
    };
};

export const brokenStorage = () => ({
    getItem: () => {
        throw new Error('blocked');
    },
    setItem: () => {
        throw new Error('blocked');
    },
});
