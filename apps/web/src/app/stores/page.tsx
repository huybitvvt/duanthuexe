'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';

interface StoreItem {
  id: number;
  name: string;
  address: string;
  phone: string;
  vehicle_count: number;
  status: number;
}

const SAMPLE_STORES: StoreItem[] = [
  { id: 1, name: '02 Hàng Bút', address: '02 Hàng Bút, Hoàn Kiếm, Hà Nội', phone: '0981.234.567', vehicle_count: 22, status: 1 },
  { id: 2, name: '66 Nguyễn Hoàng', address: '66 Nguyễn Hoàng, Nam Từ Liêm, Hà Nội', phone: '0982.345.678', vehicle_count: 18, status: 1 },
  { id: 3, name: '476 Quang Trung', address: '476 Quang Trung, Hà Đông, Hà Nội', phone: '0983.456.789', vehicle_count: 14, status: 1 },
  { id: 4, name: '91 Trần Quốc Hoàn', address: '91 Trần Quốc Hoàn, Cầu Giấy, Hà Nội', phone: '0984.567.890', vehicle_count: 16, status: 1 },
];

export default function StoresPage() {
  const [stores, setStores] = useState<StoreItem[]>(SAMPLE_STORES);

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b mb-4">
          <div className="card-header d-flex justify-content-between align-items-center py-3">
            <h3 className="card-label font-weight-bold text-dark mb-0">
              Danh sách chi nhánh & Cơ sở HIMOTO
            </h3>
            <button type="button" className="btn btn-success btn-sm" onClick={() => alert('Thêm chi nhánh mới')}>
              <i className="fas fa-plus mr-1" /> Thêm cơ sở mới
            </button>
          </div>

          <div className="card-body">
            <div className="table-responsive">
              <table className="table table-hover table-striped align-middle">
                <thead className="thead-light">
                  <tr>
                    <th style={{ width: '50px' }}>#ID</th>
                    <th>Tên cơ sở</th>
                    <th>Địa chỉ chi nhánh</th>
                    <th>Hotline</th>
                    <th>Quy mô xe</th>
                    <th>Trạng thái</th>
                    <th className="text-right" style={{ width: '100px' }}>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  {stores.map((s) => (
                    <tr key={s.id}>
                      <td className="font-weight-bold">#{s.id}</td>
                      <td><strong className="text-primary font-size-md">{s.name}</strong></td>
                      <td>{s.address}</td>
                      <td><strong>{s.phone}</strong></td>
                      <td><span className="badge badge-info">{s.vehicle_count} xe</span></td>
                      <td><span className="badge badge-light-success text-success">Đang hoạt động</span></td>
                      <td className="text-right">
                        <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => alert(`Chỉnh sửa cơ sở ${s.name}`)}>
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
