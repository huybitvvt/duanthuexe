'use client';

import React from 'react';
import { formatMoney } from '@/lib/formatters';
import { VehicleItem, getVehicleStatusBadge } from './VehicleTable';

interface VehicleGridProps {
  vehicles: VehicleItem[];
  loading?: boolean;
  onEdit: (vehicle: VehicleItem) => void;
  onDelete: (id: number) => void;
}

export const VehicleGrid: React.FC<VehicleGridProps> = ({
  vehicles,
  loading = false,
  onEdit,
  onDelete,
}) => {
  if (loading) {
    return (
      <div className="text-center py-5">
        <div className="spinner-border text-primary" role="status" />
        <p className="mt-2 text-muted">Đang tải dữ liệu lưới thẻ...</p>
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
    <div className="row g-3">
      {vehicles.map((v) => {
        const badge = getVehicleStatusBadge(v.status);
        return (
          <div key={v.id} className="col-12 col-sm-6 col-lg-4 col-xl-3 mb-3">
            <div className="card card-custom h-100 shadow-sm border-0" style={{ borderRadius: '10px' }}>
              <div className="card-body p-3 d-flex flex-column">
                <div className="d-flex justify-content-between align-items-start mb-2">
                  <div>
                    <h6 className="font-weight-bold mb-0 text-dark">{v.name}</h6>
                    <span className="badge badge-light-dark mt-1 font-weight-bold">{v.license}</span>
                  </div>
                  <span className={`badge ${badge.badgeClass}`}>{badge.label}</span>
                </div>

                <div className="font-size-sm text-muted mb-3">
                  <div>Cơ sở: <strong>{v.store?.name || '--'}</strong></div>
                  <div>Loại: <strong>{v.type_name || 'Xe ga'}</strong></div>
                  <div>ODO: <strong>{v.current_odo ? v.current_odo.toLocaleString() : '0'} km</strong></div>
                </div>

                <div className="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                  <div>
                    <span className="font-size-xs text-muted d-block">Giá thuê/ngày</span>
                    <strong className="text-primary font-size-h6">{formatMoney(v.price_daily || 0)}</strong>
                  </div>
                  <div className="btn-group btn-group-sm">
                    <button
                      type="button"
                      className="btn btn-outline-primary"
                      onClick={() => onEdit(v)}
                      title="Sửa xe"
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
                </div>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
};
