const assert = require('assert').strict;
const fs = require('fs');
const vm = require('vm');

function load(file, globals, result = '__module') {
    const source = fs.readFileSync(file, 'utf8').replace(/^import .*;\r?\n/gm, '')
        .replace(/export default /g, 'const __module = ').replace(/export /g, '');
    return vm.runInNewContext(source + '\n' + result, { console, ...globals });
}
const base = 'resources/js/src/';
const deferred = () => { let resolve, reject; const promise = new Promise((ok, fail) => { resolve = ok; reject = fail; }); return { promise, resolve, reject }; };

async function run() {
    const createPendingRequests = load(base + 'utils/pendingRequests.js', {}, 'createPendingRequests');
    const pending = createPendingRequests();
    let calls = 0;
    const first = deferred();
    const a = pending.run('list', () => { calls++; return first.promise; });
    const b = pending.run('list', () => { calls++; return Promise.resolve('duplicate'); });
    assert.equal(a, b);
    first.resolve('rows');
    assert.equal(await a, 'rows');
    assert.equal(calls, 1);
    await pending.run('list', () => { calls++; return Promise.resolve('fresh'); });
    assert.equal(calls, 2, 'Settled reads must not be cached');
    await assert.rejects(pending.run('error', () => Promise.reject(new Error('offline'))));
    assert.equal(await pending.run('error', () => Promise.resolve('retry')), 'retry');
    const old = deferred(), fresh = deferred();
    const oldRequest = pending.run('race', () => old.promise);
    pending.clear();
    const freshRequest = pending.run('race', () => fresh.promise);
    old.resolve('old'); await oldRequest;
    assert.equal(pending.run('race', () => Promise.resolve('wrong')), freshRequest);
    fresh.resolve('fresh'); await freshRequest;

    const axios = require('axios').create({ baseURL: 'https://fixture.invalid' });
    const readRequests = [], writeRequests = [];
    axios.get = (url, config) => { const request = deferred(); readRequests.push({ url, config, ...request }); return request.promise; };
    axios.post = () => { const request = deferred(); writeRequests.push(request); return request.promise; };
    let readIdentity = 'a';
    const service = load(base + 'core/services/api.service.js', {
        Vue: { axios }, JwtService: { getToken: () => readIdentity }, createPendingRequests,
        process: { env: {} }, window: { location: { hostname: 'localhost', origin: 'http://localhost' } }, URL
    });
    const sharedA = service.query('/list', { page: 1 });
    assert.equal(service.query('/list', { page: 1 }), sharedA);
    const otherPage = service.query('/list', { page: 2 });
    assert.notEqual(sharedA, otherPage, 'Distinct filters must not share a request');
    readIdentity = 'b';
    const sharedB = service.query('/list', { page: 1 });
    assert.notEqual(sharedA, sharedB, 'Different tokens must not share a request');
    readRequests.forEach(request => request.resolve({ data: [] }));
    await Promise.all([sharedA, otherPage, sharedB]);
    const beforeWrite = service.get('/list');
    const write = service.post('/list', {});
    const duringWrite = service.get('/list');
    assert.notEqual(beforeWrite, duringWrite, 'Writes must invalidate pending reads');
    writeRequests[0].resolve({ data: {} }); await write;
    const afterWrite = service.get('/list');
    assert.notEqual(duringWrite, afterWrite, 'Write completion must invalidate pending reads too');
    readRequests.slice(3).forEach(request => request.resolve({ data: [] }));
    await Promise.all([beforeWrite, duringWrite, afterWrite]);
    const deniedRead = service.get('/denied');
    readRequests[6].reject({ response: { status: 403, data: { message: 'denied' } } });
    await assert.rejects(deniedRead, error => error.status === 403 && error.response.data.message === 'denied');

    let token = 'fixture-a';
    let clock = 100000;
    const requests = [];
    const api = { setHeader() {}, invalidateReads() {}, get() { const request = deferred(); requests.push(request); return request.promise; } };
    const jwt = { getToken: () => token, saveToken: value => { token = value; }, destroyToken: () => { token = null; } };
    const auth = load(base + 'core/services/store/auth.module.js', { ApiService: api, JwtService: jwt, Date: { now: () => clock }, Math });
    const context = { state: auth.state, commit(name, value) { auth.mutations[name](context.state, value); } };
    const staffA = { user: { id: 1, store_id: 1, role: 'nhan-vien' }, capabilities: ['order.create'] };
    const staffB = { user: { id: 2, store_id: 2, role: 'nhan-vien' }, capabilities: ['order.create'] };
    const initial = auth.actions.verifyAuth(context);
    assert.equal(auth.actions.verifyAuth(context), initial, 'Concurrent verification must share one request');
    requests[0].resolve({ data: staffA }); await initial;
    const sessionA = context.state.authSessionId;
    await auth.actions.verifyAuth(context);
    assert.equal(requests.length, 1, 'Navigation within 30 seconds must reuse verification');
    clock += 30001;
    const expired = auth.actions.verifyAuth(context);
    requests[1].resolve({ data: staffA }); await expired;
    assert.equal(context.state.authSessionId, sessionA, 'Same user verification must preserve pending/cache ownership');
    const changedScope = auth.actions.verifyAuth(context, { force: true });
    requests[2].resolve({ data: { ...staffA, capabilities: ['order.create', 'order.update'] } }); await changedScope;
    assert.notEqual(context.state.authSessionId, sessionA, 'Permission changes must invalidate the session scope');
    const late = auth.actions.verifyAuth(context, { force: true });
    context.commit('logOut'); token = 'fixture-b'; context.commit('setUser', staffB);
    requests[3].resolve({ data: staffA }); await late;
    assert.equal(context.state.user.id, 2, 'Late verification must not restore the old user');
    const offline = auth.actions.verifyAuth(context, { force: true });
    requests[4].reject({ status: 503 }); await assert.rejects(offline);
    assert.equal(context.state.user.id, 2, 'Temporary failures must not discard a valid session');
    const denied = auth.actions.verifyAuth(context, { force: true });
    requests[5].reject({ status: 401 }); await denied;
    assert.equal(context.state.isAuthenticated, false);

    let session = 'session-a', isAuth = true;
    const storeRequests = [];
    const storeApi = { query() { const request = deferred(); storeRequests.push(request); return request.promise; } };
    const stores = load(base + 'core/services/store/store.module.js', { ApiService: storeApi, PURGE_AUTH: 'logOut', localStorage: { getItem: () => null, setItem() {} }, Date });
    const storeContext = { state: stores.state, rootGetters: { get authSessionId() { return session; }, get isAuthenticated() { return isAuth; } }, commit(name, value) { stores.mutations[name](stores.state, value); } };
    const listA = stores.actions.store_get_all(storeContext, {});
    assert.equal(stores.actions.store_get_all(storeContext, {}), listA);
    session = 'session-b';
    const listB = stores.actions.store_get_all(storeContext, {});
    assert.notEqual(listA, listB);
    storeRequests[0].resolve({ data: { data: [{ id: 1 }] } }); await listA;
    assert.equal(stores.state.storesLoaded, false);
    storeRequests[1].resolve({ data: { data: [{ id: 2 }] } }); await listB;
    assert.equal(stores.state.storeList[0].id, 2);
    await stores.actions.store_get_all(storeContext, {});
    assert.equal(storeRequests.length, 2);
    stores.mutations.INVALIDATE_CACHED_STORES(stores.state);
    const invalidated = stores.actions.store_get_all(storeContext, {});
    stores.mutations.INVALIDATE_CACHED_STORES(stores.state);
    storeRequests[2].resolve({ data: { data: [{ id: 99 }] } }); await invalidated;
    assert.equal(stores.state.storesLoaded, false, 'An invalidated in-flight response must not repopulate cache');
    stores.mutations.logOut(stores.state);
    assert.equal(stores.state.storeList.length, 0);

    console.log('PASS: request coalescing/retry, verification TTL, identity/permission isolation, stale auth/store response guards.');
}
run().catch(error => { console.error(error); process.exitCode = 1; });
