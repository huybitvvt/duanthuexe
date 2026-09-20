'use client';

import React, { useState } from 'react';
import AppShell from '@/components/layout/AppShell';
import { formatDate } from '@/lib/formatters';

interface MaintenanceLogItem {
  id: number;
  vehicle_name: string;
  license: string;
  maintenance_type: string;
  maintenance_date: string;
  note: string;
  cost: number;
}

const MOCK_LOGS: MaintenanceLogItem[] = [
  { id: 101, vehicle_name: 'Honda Vision Trắng', license: '29B1-123.45', maintenance_type: 'Thay dầu máy', maintenance_date: '2026-09-18', note: 'Thay dầu Castrol Power1 10W40', cost: 120000 },
  { id: 102, vehicle_name: 'Air Blade 125 Đen', license: '29B1-678.90', maintenance_type: 'Bảo dưỡng định kỳ', maintenance_date: '2026-09-15', note: 'Bảo dưỡng nồi, thay lọc gió', cost: 350000 },
  { id: 103, vehicle_name: 'Yamaha Janus Đỏ', license: '29D1-345.67', maintenance_type: 'Kiểm tra phanh & lốp', maintenance_date: '2026-09-10', note: 'Thay má phanh trước', cost: 180000 },
];

export default function MaintenanceLogPage() {
  const [searchVehicle, setSearchVehicle] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');

  return (
    <AppShell>
      <div className="card card-custom gutter-b">
        <div className="card-header d-flex justify-content-between align-items-center">
          <div className="card-title">
            <h3 className="card-label font-weight-bolder text-dark m-0">
              Lịch sử bảo dưỡng
            </h3>
          </div>
          <div className="card-toolbar">
            <button type="button" className="btn btn-success font-weight-bold">
              + Thêm mới nhật ký
            </button>
          </div>
        </div>

        <div className="card-body">
          {/* FILTER BAR */}
          <div className="row mb-6 align-items-end">
            <div className="col-md-3">
              <label className="font-weight-bold text-muted font-size-sm">Tên xe, biển số</label>
              <input
                type="text"
                className="form-control"
                placeholder="Tên xe, biển số..."
                value={searchVehicle}
                onChange={(e) => setSearchVehicle(e.target.value)}
              />
            </div>
            <div className="col-md-3">
              <label className="font-weight-bold text-muted font-size-sm">Từ ngày</label>
              <input
                type="date"
                className="form-control"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
              />
            </div>
            <div className="col-md-3">
              <label className="font-weight-bold text-muted font-size-sm">Đến ngày</label>
              <input
                type="date"
                className="form-control"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
              />
            </div>
            <div className="col-md-3">
              <button type="button" className="btn btn-primary font-weight-bold w-100">
                Tìm kiếm
              </button>
            </div>
          </div>

          {/* TABLE */}
          <div className="table-responsive">
            <table className="table table-bordered table-hover">
              <thead className="thead-light">
                <tr>
                  <th style={{ width: 60 }}>Mã</th>
                  <th>Xe</th>
                  <th>Loại bảo dưỡng</th>
                  <th>Ngày bảo dưỡng</th>
                  <th>Ghi chú</th>
                  <th className="text-center" style={{ width: 120 }}>Hành động</th>
                </tr>
              </thead>
              <tbody>
                {MOCK_LOGS.map((item) => (
                  <tr key={item.id}>
                    <td className="font-weight-bold">#{item.id}</td>
                    <td>
                      <div className="font-weight-bolder text-dark">{item.vehicle_name}</div>
                      <span className="badge badge-secondary">{item.license}</span>
                    </td>
                    <td>
                      <span className="badge badge-primary font-weight-bold px-2 py-1">
                        {item.maintenance_type}
                      </span>
                    </td>
                    <td>{formatDate(item.maintenance_date)}</td>
                    <td>{item.note}</td>
                    <td className="text-center">
                      <button type="button" className="btn btn-sm btn-outline-primary mr-1 font-weight-bold">
                        Sửa
                      </button>
                      <button type="button" className="btn btn-sm btn-outline-danger font-weight-bold">
                        Xóa
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </AppShell>
  );
}
