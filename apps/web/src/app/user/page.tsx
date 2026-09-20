'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';

interface UserItem {
  id: number;
  name: string;
  email: string;
  phone: string;
  role_name: string;
  store_name: string;
  status: number;
}

const SAMPLE_USERS: UserItem[] = [
  { id: 1, name: 'HIMOTO Administrator', email: 'admin@himoto.vn', phone: '0981.234.567', role_name: 'Quản trị viên cấp cao (Admin)', store_name: 'Toàn hệ thống', status: 1 },
  { id: 2, name: 'Nguyễn Thu Hà', email: 'ha.nt@himoto.vn', phone: '0987.111.222', role_name: 'Quản lý cơ sở (Manager)', store_name: '02 Hàng Bút', status: 1 },
  { id: 3, name: 'Trần Văn Nam', email: 'nam.tv@himoto.vn', phone: '0987.333.444', role_name: 'Nhân viên vận hành (Staff)', store_name: '66 Nguyễn Hoàng', status: 1 },
  { id: 4, name: 'Lê Văn Tùng', email: 'tung.lv@himoto.vn', phone: '0987.555.666', role_name: 'Nhân viên kỹ thuật (Maintenance)', store_name: '476 Quang Trung', status: 1 },
];

export default function UsersPage() {
  const [users, setUsers] = useState<UserItem[]>(SAMPLE_USERS);

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b mb-4">
          <div className="card-header d-flex justify-content-between align-items-center py-3">
            <h3 className="card-label font-weight-bold text-dark mb-0">
              Quản lý tài khoản & Phân quyền nhân viên
            </h3>
            <button type="button" className="btn btn-success btn-sm" onClick={() => alert('Thêm người dùng mới')}>
              <i className="fas fa-user-plus mr-1" /> Thêm người dùng
            </button>
          </div>

          <div className="card-body">
            <div className="table-responsive">
              <table className="table table-hover table-striped align-middle">
                <thead className="thead-light">
                  <tr>
                    <th style={{ width: '50px' }}>#ID</th>
                    <th>Họ và tên</th>
                    <th>Email đăng nhập</th>
                    <th>Số điện thoại</th>
                    <th>Vai trò / Phân quyền</th>
                    <th>Chi nhánh trực thuộc</th>
                    <th>Trạng thái</th>
                    <th className="text-right" style={{ width: '100px' }}>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  {users.map((u) => (
                    <tr key={u.id}>
                      <td>#{u.id}</td>
                      <td>
                        <div className="d-flex align-items-center">
                          <span className="user-avatar-circle mr-2">{u.name.charAt(0)}</span>
                          <strong>{u.name}</strong>
                        </div>
                      </td>
                      <td><code>{u.email}</code></td>
                      <td>{u.phone}</td>
                      <td><span className="badge badge-light-primary text-primary font-weight-bold">{u.role_name}</span></td>
                      <td>{u.store_name}</td>
                      <td><span className="badge badge-light-success text-success">Đang hoạt động</span></td>
                      <td className="text-right">
                        <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => alert(`Chỉnh sửa ${u.name}`)}>
                          <i className="fas fa-edit" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </AppShell>
  );
}
