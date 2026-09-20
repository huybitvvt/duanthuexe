'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { formatDateTime } from '@/lib/formatters';

interface TransferItem {
  id: number;
  transfer_code: string;
  vehicle_name: string;
  license: string;
  from_store: string;
  to_store: string;
  driver_name: string;
  status: 'pending' | 'in_transit' | 'completed';
  created_at: string;
}

const SAMPLE_TRANSFERS: TransferItem[] = [
  { id: 1, transfer_code: 'DC-2026-001', vehicle_name: 'Honda Vision 2023 Trắng', license: '29B1-987.65', from_store: '02 Hàng Bút', to_store: '66 Nguyễn Hoàng', driver_name: 'Lê Văn Tùng', status: 'completed', created_at: '2026-09-18 10:00' },
  { id: 2, transfer_code: 'DC-2026-002', vehicle_name: 'Honda Air Blade 160 Xanh', license: '29X5-888.99', from_store: '476 Quang Trung', to_store: '02 Hàng Bút', driver_name: 'Nguyễn Văn Quân', status: 'in_transit', created_at: '2026-09-20 09:30' },
];

export default function WarehousesPage() {
  const [transfers, setTransfers] = useState<TransferItem[]>(SAMPLE_TRANSFERS);

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'completed':
        return <span className="badge badge-light-success text-success">Hoàn thành</span>;
      case 'in_transit':
        return <span className="badge badge-light-primary text-primary">Đang di chuyển</span>;
      default:
        return <span className="badge badge-light-warning text-warning">Đang chờ duyệt</span>;
    }
  };

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b mb-4">
          <div className="card-header d-flex justify-content-between align-items-center py-3">
            <h3 className="card-label font-weight-bold text-dark mb-0">
              Kho xe & Điều chuyển giữa các cơ sở
            </h3>
            <button type="button" className="btn btn-primary btn-sm" onClick={() => alert('Tạo phiếu điều chuyển')}>
              <i className="fas fa-exchange-alt mr-1" /> Tạo lệnh điều chuyển
            </button>
          </div>

          <div className="card-body">
            <div className="table-responsive">
              <table className="table table-hover table-striped align-middle">
                <thead className="thead-light">
                  <tr>
                    <th style={{ width: '50px' }}>#</th>
                    <th>Mã điều chuyển</th>
                    <th>Tên xe & Biển số</th>
                    <th>Cơ sở xuất</th>
                    <th>Cơ sở nhận</th>
                    <th>Người vận chuyển</th>
                    <th>Trạng thái</th>
                    <th>Thời gian</th>
                  </tr>
                </thead>
                <tbody>
                  {transfers.map((t) => (
                    <tr key={t.id}>
                      <td>#{t.id}</td>
                      <td><code className="text-primary font-weight-bold font-size-sm">{t.transfer_code}</code></td>
                      <td>
                        <strong>{t.vehicle_name}</strong>
                        <span className="badge badge-light-dark ml-2">{t.license}</span>
                      </td>
                      <td>{t.from_store}</td>
                      <td>{t.to_store}</td>
                      <td>{t.driver_name}</td>
                      <td>{getStatusBadge(t.status)}</td>
                      <td className="font-size-sm text-muted">{formatDateTime(t.created_at)}</td>
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
