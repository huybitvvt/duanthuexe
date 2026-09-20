'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { formatDate } from '@/lib/formatters';

interface MaintenanceScheduleItem {
  id: number;
  vehicle_name: string;
  license: string;
  store_name: string;
  maintenance_type: string;
  current_odo: number;
  due_odo: number;
  due_date: string;
  status: 'upcoming' | 'overdue' | 'in_progress' | 'completed';
}

const SAMPLE_SCHEDULES: MaintenanceScheduleItem[] = [
  { id: 1, vehicle_name: 'Honda Vision 2023 Trắng', license: '29B1-987.65', store_name: '02 Hàng Bút', maintenance_type: 'Thay nhớt định kỳ 2.000km', current_odo: 14200, due_odo: 14000, due_date: '2026-09-18', status: 'overdue' },
  { id: 2, vehicle_name: 'Yamaha Grande 125 Đỏ', license: '29H1-543.21', store_name: '66 Nguyễn Hoàng', maintenance_type: 'Bảo dưỡng định kỳ 10.000km', current_odo: 8900, due_odo: 10000, due_date: '2026-09-28', status: 'upcoming' },
  { id: 3, vehicle_name: 'Honda Wave Alpha 110 Đen', license: '29A1-222.33', store_name: '476 Quang Trung', maintenance_type: 'Kiểm tra phanh & nhông sên dĩa', current_odo: 34000, due_odo: 34000, due_date: '2026-09-20', status: 'in_progress' },
];

export default function MaintenanceSchedulePage() {
  const [schedules, setSchedules] = useState<MaintenanceScheduleItem[]>(SAMPLE_SCHEDULES);

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'overdue':
        return <span className="badge badge-danger font-weight-bold">Quá hạn bảo dưỡng</span>;
      case 'in_progress':
        return <span className="badge badge-primary font-weight-bold">Đang thực hiện</span>;
      case 'completed':
        return <span className="badge badge-success font-weight-bold">Đã hoàn thành</span>;
      default:
        return <span className="badge badge-info font-weight-bold">Sắp đến hạn</span>;
    }
  };

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b mb-4">
          <div className="card-header d-flex justify-content-between align-items-center py-3">
            <h3 className="card-label font-weight-bold text-dark mb-0">
              Lịch bảo dưỡng & sửa chữa phương tiện
            </h3>
            <button type="button" className="btn btn-primary btn-sm" onClick={() => alert('Thêm lịch bảo dưỡng')}>
              <i className="fas fa-plus mr-1" /> Thêm lịch bảo dưỡng
            </button>
          </div>

          <div className="card-body">
            <div className="table-responsive">
              <table className="table table-hover table-striped align-middle">
                <thead className="thead-light">
                  <tr>
                    <th style={{ width: '50px' }}>#</th>
                    <th>Tên xe & Biển số</th>
                    <th>Cơ sở</th>
                    <th>Hạng mục bảo dưỡng</th>
                    <th>ODO hiện tại</th>
                    <th>ODO đến hạn</th>
                    <th>Hạn chót</th>
                    <th>Trạng thái</th>
                    <th className="text-right" style={{ width: '120px' }}>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  {schedules.map((s) => (
                    <tr key={s.id}>
                      <td>#{s.id}</td>
                      <td>
                        <strong>{s.vehicle_name}</strong>
                        <span className="badge badge-light-dark ml-2">{s.license}</span>
                      </td>
                      <td>{s.store_name}</td>
                      <td><span className="badge badge-light-info text-info">{s.maintenance_type}</span></td>
                      <td>{s.current_odo.toLocaleString()} km</td>
                      <td><strong>{s.due_odo.toLocaleString()} km</strong></td>
                      <td>{formatDate(s.due_date)}</td>
                      <td>{getStatusBadge(s.status)}</td>
                      <td className="text-right">
                        <button type="button" className="btn btn-sm btn-outline-success font-weight-bold" onClick={() => alert('Xác nhận hoàn thành bảo dưỡng')}>
                          <i className="fas fa-check mr-1" /> Hoàn thành
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
