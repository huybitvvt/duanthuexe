const test = require('node:test');
const assert = require('node:assert/strict');
const { ForbiddenException } = require('@nestjs/common');
const { UserController } = require('../dist/modules/user/user.controller');
const { UserService } = require('../dist/modules/user/user.service');
const { AuthService } = require('../dist/modules/auth/auth.service');
const jwt = require('jsonwebtoken');

test('non-admin cannot create, update, reset or delete another account', async () => {
  let mutations = 0;
  const service = {
    create: async () => { mutations++; },
    update: async () => { mutations++; },
    changePassword: async () => { mutations++; },
    delete: async () => { mutations++; },
  };
  const controller = new UserController(service);
  const request = { user: { id: 2, role_id: 3 } };

  await assert.rejects(controller.store({}, request), ForbiddenException);
  await assert.rejects(controller.update({ id: 1 }, request), ForbiddenException);
  await assert.rejects(controller.changePassword({ id: 1 }, request), ForbiddenException);
  await assert.rejects(controller.destroy('1', request), ForbiddenException);
  assert.equal(mutations, 0);
});

test('admin cannot delete the signed-in account', async () => {
  const controller = new UserController({ delete: async () => assert.fail('delete was called') });
  await assert.rejects(controller.destroy('1', { user: { id: 1, role_id: 1 } }), ForbiddenException);
});

test('staff cannot list employees from another warehouse', async () => {
  const controller = new UserController({ getStaffByStore: async () => assert.fail('query was called') });
  await assert.rejects(
    controller.getStaffByStore('9', { user: { id: 2, role_id: 3, store_id: 4 } }),
    ForbiddenException,
  );
});

test('user listing excludes removed users and scopes a staff member to self', async () => {
  const queries = [];
  const service = new UserService({
    query: async (sql, values) => {
      queries.push({ sql, values });
      return sql.includes('COUNT(*)') ? { rows: [{ total: '1' }] } : { rows: [{ id: 2 }] };
    },
  });
  await service.findAll({ store_id: '999' }, { id: 2, role_id: 3, store_id: 4 });

  const listSql = queries.find((entry) => entry.sql.includes('SELECT u.id'));
  assert.match(listSql.sql, /u\.deleted_at IS NULL/);
  assert.match(listSql.sql, /u\.status != 'deactive'/);
  assert.match(listSql.sql, /u\.id = \$1/);
  assert.equal(listSql.values[0], 2);
});

test('manager listing stays within the assigned warehouse', async () => {
  const queries = [];
  const service = new UserService({
    query: async (sql, values) => {
      queries.push({ sql, values });
      if (sql === 'SELECT slug FROM roles WHERE id = $1') {
        return { rows: [{ slug: 'quan-ly-cua-hang' }] };
      }
      return sql.includes('COUNT(*)') ? { rows: [{ total: '1' }] } : { rows: [{ id: 2 }] };
    },
  });
  await service.findAll({ store_id: '999' }, { id: 2, role_id: 2, store_id: 4 });
  const listSql = queries.find((entry) => entry.sql.includes('SELECT u.id'));
  assert.match(listSql.sql, /u\.store_id = \$1/);
  assert.equal(listSql.values[0], 4);
});

test('account deletion keeps history and disables login', async () => {
  const queries = [];
  const service = new UserService({
    query: async (sql, values) => {
      queries.push({ sql, values });
      return { rows: [{ id: 5 }] };
    },
  });
  await service.delete(5);
  assert.match(queries[0].sql, /^UPDATE users SET status = 'deactive'/);
  assert.match(queries[0].sql, /deleted_at = NOW\(\)/);
  assert.deepEqual(queries[0].values, [5]);
});

test('authentication lookup never loads a soft-deleted account', async () => {
  const queries = [];
  const service = new AuthService({
    query: async (sql) => {
      queries.push(sql);
      return { rows: sql.includes('deleted_at IS NULL') ? [] : [{ id: 5, status: 'active' }] };
    },
  });
  assert.equal(await service.getUserById(5), null);
  assert.match(queries[0], /deleted_at IS NULL/);
});

test('token refresh preserves the assigned role capabilities', async () => {
  const service = new AuthService({
    query: async (sql) => {
      assert.match(sql, /r\.slug AS role_slug/);
      return { rows: [{ id: 99, email: 'director@himoto.test', role_id: 5, role_slug: 'ban-giam-doc', status: 'active' }] };
    },
  });
  const token = jwt.sign({ sub: 99 }, process.env.JWT_SECRET || 'himoto-secret-jwt-key-2b49116');
  const refreshed = await service.verifyToken(token);
  assert.ok(refreshed.capabilities.includes('kpi.view_company'));
  assert.deepEqual(service.getCapabilities(refreshed.user), refreshed.capabilities);
});
