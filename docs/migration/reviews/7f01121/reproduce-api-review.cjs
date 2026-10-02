// Review reproducer for 7f01121. Uses compiled code and an in-memory DB double.
// Does not import AppModule, load .env, start cron, or connect to PostgreSQL.
// Run from the repository: node docs/migration/reviews/7f01121/reproduce-api-review.cjs
const path = require('node:path');
const fs = require('node:fs');
const { createRequire } = require('node:module');
const { randomBytes } = require('node:crypto');
const root = path.resolve(__dirname, '../../../..');
const apiRequire = createRequire(path.join(root, 'apps/api/package.json'));
apiRequire('reflect-metadata');
const { Module, RequestMethod } = apiRequire('@nestjs/common');
const { NestFactory } = apiRequire('@nestjs/core');
const { PATH_METADATA, METHOD_METADATA } = apiRequire('@nestjs/common/constants');
const jwt = apiRequire('jsonwebtoken');
const built = (name) => require(path.join(root, 'apps/api/dist', name));
const { DatabaseService } = built('database/database.service.js');
const { AuthService } = built('modules/auth/auth.service.js');
const { JwtAuthGuard } = built('modules/auth/auth.guard.js');
const { AuthController } = built('modules/auth/auth.controller.js');
const { UserService } = built('modules/user/user.service.js');
const { UserController } = built('modules/user/user.controller.js');
const { OrderService } = built('modules/order/order.service.js');
const { OrderController } = built('modules/order/order.controller.js');
const { ExportService } = built('modules/export/export.service.js');
const { ExportController } = built('modules/export/export.controller.js');
const { AccountingService } = built('modules/accounting/accounting.service.js');

const staff = { id: 71001, name: 'Review fixture', email: 'review@example.test', role_id: 3, role: 'staff', is_admin: 0, store_id: 1, status: 'active' };
const calls = [];
const fakeDb = {
  async query(sql, params = []) {
    calls.push({ sql, params });
    if (/FROM users WHERE id/.test(sql)) return { rows: [staff], rowCount: 1 };
    if (/^UPDATE users SET password/.test(sql)) return { rows: [], rowCount: 1 };
    if (/COUNT\(DISTINCT o.id\)/.test(sql)) return { rows: [{ total: '0' }], rowCount: 1 };
    if (/FROM orders o/.test(sql)) return { rows: [], rowCount: 0 };
    if (/^SELECT \* FROM orders ORDER/.test(sql)) {
      return { rows: [{ id: 1, store_id: 1 }, { id: 2, store_id: 2 }], rowCount: 2 };
    }
    throw new Error('Unexpected query in isolated review; no DB connection exists');
  },
};

function collectRoutes() {
  const result = [];
  const moduleDir = path.join(root, 'apps/api/dist/modules');
  for (const dir of fs.readdirSync(moduleDir, { withFileTypes: true })) {
    if (!dir.isDirectory()) continue;
    for (const file of fs.readdirSync(path.join(moduleDir, dir.name))) {
      if (!file.endsWith('.controller.js')) continue;
      for (const Controller of Object.values(require(path.join(moduleDir, dir.name, file)))) {
        if (typeof Controller !== 'function') continue;
        const prefix = Reflect.getMetadata(PATH_METADATA, Controller);
        if (prefix === undefined) continue;
        for (const member of Object.getOwnPropertyNames(Controller.prototype)) {
          if (member === 'constructor') continue;
          const handler = Controller.prototype[member];
          const method = Reflect.getMetadata(METHOD_METADATA, handler);
          if (method === undefined) continue;
          const suffix = Reflect.getMetadata(PATH_METADATA, handler) || '';
          result.push({ method: RequestMethod[method], path: '/' + ['api', prefix, suffix].join('/').split('/').filter(Boolean).join('/') });
        }
      }
    }
  }
  return result;
}

async function main() {
  const previousSecret = process.env.JWT_SECRET;
  process.env.JWT_SECRET = randomBytes(32).toString('hex');
  class ReviewModule {}
  Module({
    controllers: [AuthController, UserController, OrderController, ExportController],
    providers: [AuthService, JwtAuthGuard, UserService, OrderService, ExportService, { provide: DatabaseService, useValue: fakeDb }],
  })(ReviewModule);
  let app;
  try {
    app = await NestFactory.create(ReviewModule, { logger: false });
    app.setGlobalPrefix('api'); // Same prefix as apps/api/src/main.ts at the reviewed commit.
    await app.listen(0, '127.0.0.1');
    const base = await app.getUrl();
    const token = jwt.sign({ sub: staff.id }, process.env.JWT_SECRET, { expiresIn: 60 });
    async function request(route, { method = 'GET', body, auth = true } = {}) {
      const response = await fetch(base + route, {
        method,
        headers: { 'Content-Type': 'application/json', ...(auth ? { Authorization: `Bearer ${token}` } : {}) },
        ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
        signal: AbortSignal.timeout(5000),
      });
      const raw = await response.text();
      let data;
      try { data = JSON.parse(raw); } catch { data = raw; }
      return { status: response.status, data };
    }
    const expectedLogin = await request('/api/auth/login', { method: 'POST', body: {}, auth: false });
    const actualLogin = await request('/api/api/auth/login', { method: 'POST', body: {}, auth: false });
    const verifyRoute = await request('/api/verify-token');
    const passwordChange = await request('/api/api/auth/users/change-password', { method: 'POST', body: { id: 71002, password: 'Synthetic-review-only' } });
    const passwordWrite = calls.some(c => /^UPDATE users SET password/.test(c.sql) && c.params[1] === 71002);
    const scopedList = await request('/api/api/auth/order/car-rental?store_id=2');
    const crossStore = calls.some(c => /COUNT\(DISTINCT o.id\)/.test(c.sql) && c.params[0] === 2);
    const exported = await request('/api/api/auth/export/orders?store_id=1');
    const unscopedExport = calls.some(c => /^SELECT \* FROM orders ORDER/.test(c.sql) && c.params.length === 0);
    const beforeLogout = await request('/api/api/auth/me');
    const logout = await request('/api/api/auth/logout', { method: 'POST' });
    const afterLogout = await request('/api/api/auth/me');
    let accountingDbFailureObserved = false;
    const accounting = new AccountingService({ async query() {
      accountingDbFailureObserved = true;
      throw new Error('Synthetic database failure; no real DB');
    } });
    const fabricatedEntry = await accounting.postJournalEntry({ description: 'Review fixture', amount: 100 }, staff.id);
    const routes = collectRoutes();
    const legacy = JSON.parse(fs.readFileSync(path.join(root, 'docs/migration/api-contracts.json'), 'utf8'));
    const normalize = p => p.replace(/\{[^}]+\}|:[^/]+/g, ':param').replace(/\/$/, '');
    const current = new Set(routes.map(r => `${r.method} ${normalize(r.path)}`));
    const repairedPrefix = new Set(routes.map(r => `${r.method} ${normalize(r.path.replace(/^\/api\/api\//, '/api/'))}`));
    const expectations = legacy.flatMap(r => r.method.split('|').filter(m => m !== 'HEAD').map(m => `${m} ${normalize('/' + r.uri)}`));
    const result = {
      reviewed_commit: '7f01121',
      isolation: 'Selected compiled controllers/services; in-memory DB double; no AppModule/env/cron/real DB',
      login_at_legacy_path: expectedLogin.status,
      login_at_doubled_prefix: { http: actualLogin.status, body_status_code: actualLogin.data.statusCode },
      legacy_verify_token_path: verifyRoute.status,
      non_admin_password_change_other_user: { http: passwordChange.status, fake_db_write_reached: passwordWrite },
      store_1_user_requests_store_2: { http: scopedList.status, store_2_query_reached: crossStore },
      order_list_data_is_array_for_current_frontend: Array.isArray(scopedList.data.data),
      export_with_store_filter: { http: exported.status, sql_has_no_scope: unscopedExport },
      logout_revocation: { before: beforeLogout.status, logout: logout.status, same_token_after: afterLogout.status },
      accounting_write_on_database_error: { query_failed: accountingDbFailureObserved, service_still_returned_entry: !!fabricatedEntry?.id },
      route_inventory: {
        legacy_contract_rows: legacy.length,
        legacy_method_path_pairs_excluding_head: expectations.length,
        node_declared_handlers: routes.length,
        legacy_pairs_missing_now: expectations.filter(r => !current.has(r)).length,
        legacy_pairs_missing_even_after_prefix_correction: expectations.filter(r => !repairedPrefix.has(r)).length,
        missing_examples_after_prefix_correction: expectations.filter(r => !repairedPrefix.has(r)).slice(0, 20),
        note: 'Parameter names normalized; handler counts and route matching do not prove behavioral parity.',
      },
    };
    fs.writeFileSync(path.join(__dirname, 'api-evidence.json'), JSON.stringify(result, null, 2) + '\n');
    console.log(JSON.stringify(result, null, 2));
  } finally {
    if (app) await app.close();
    if (previousSecret === undefined) delete process.env.JWT_SECRET;
    else process.env.JWT_SECRET = previousSecret;
  }
}
main().catch(error => { console.error(error.message); process.exitCode = 1; });
