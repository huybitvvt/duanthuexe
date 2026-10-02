// Share only requests that are still in flight. Completed results are never
// cached, so list data remains fresh after a save or an explicit refresh.
export function createPendingRequests() {
    const pending = new Map();
    return {
        run(key, fetch) {
            if (pending.has(key)) return pending.get(key);
            const request = Promise.resolve(fetch()).finally(() => {
                if (pending.get(key) === request) pending.delete(key);
            });
            pending.set(key, request);
            return request;
        },
        clear() { pending.clear(); },
    };
}
