'use client';

import React from 'react';
import { formatMoney } from '@/lib/formatters';

export interface VehicleItem {
  id: number;
  name: string;
  license: string;
  store?: {
    id: number;
    name: string;
  };
  type_name?: string;
  current_odo?: number;
  price_daily?: number;
  status: number;
  image?: string;
}

export function getVehicleStatusBadge(status: number): { label: string; badgeClass: string } {
  switch (status) {
    case 1:
      return { label: 'Sẵn sàng', badgeClass: 'badge-success' };
    case 2:
      return { label: 'Đang thuê', badgeClass: 'badge-primary' };
    case 3:
      return { label: 'Bảo dưỡng', badgeClass: 'badge-warning' };
    case 4:
      return { label: 'Ngừng hoạt động', badgeClass: 'badge-danger' };
    default:
      return { label: 'Khác', badgeClass: 'badge-secondary' };
  }
}

interface VehicleTableProps {
  vehicles: VehicleItem[];
  loading?: boolean;
  onEdit: (vehicle: VehicleItem) => void;
  onDelete: (id: number) => void;
}

export const VehicleTable: React.FC<VehicleTableProps> = ({
  vehicles,
  loading = false,
  onEdit,
  onDelete,
}) => {
  if (loading) {
    return (
      <div className="text-center py-5">
        <div className="spinner-border text-primary" role="status" />
        <p className="mt-2 text-muted">Đang tải danh sách xe...</p>
      </div>
    );
  }

  if (vehicles.length === 0) {
    return (
      <div className="text-center py-5 border rounded bg-white">
        <i className="fas fa-motorcycle fa-3x text-muted mb-3" />
        <p className="text-muted">Không tìm thấy xe nào phù hợp.</p>
      </div>
    );
  }

  return (
    <div className="table-responsive">
      <table className="table table-hover table-striped align-middle">
        <thead className="thead-light">
          <tr>
            <th style={{ width: '60px' }}>#ID</th>
            <th>Tên xe</th>
            <th>Biển số</th>
            <th>Cơ sở</th>
            <th>Loại xe</th>
            <th>ODO (km)</th>
            <th>Giá thuê/ngày</th>
            <th>Trạng thái</th>
            <th className="text-right" style={{ width: '100px' }}>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          {vehicles.map((v) => {
            const badge = getVehicleStatusBadge(v.status);
            return (
              <tr key={v.id}>
                <td className="font-weight-bold">#{v.id}</td>
                <td>
                  <strong className="text-dark cursor-pointer" onClick={() => onEdit(v)}>
                    {v.name}
                  </strong>
                </td>
                <td>
                  <span className="badge badge-light-dark font-weight-bold">{v.license}</span>
                </td>
                <td>{v.store?.name || '--'}</td>
                <td>{v.type_name || 'Xe ga'}</td>
                <td>{v.current_odo ? v.current_odo.toLocaleString() : '0'} km</td>
                <td className="font-weight-bold text-primary">
                  {formatMoney(v.price_daily || 0)}
                </td>
                <td>
                  <span className={`badge ${badge.badgeClass}`}>{badge.label}</span>
                </td>
                <td className="text-right">
                  <div className="btn-group btn-group-sm">
                    <button
                      type="button"
                      className="btn btn-outline-primary"
                      onClick={() => onEdit(v)}
                      title="Chỉnh sửa"
                    >
                      <i className="fas fa-edit" />
                    </button>
                    <button
                      type="button"
                      className="btn btn-outline-danger"
                      onClick={() => onDelete(v.id)}
                      title="Xóa xe"
                    >
                      <i className="fas fa-trash" />
                    </button>
                  </div>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
};
