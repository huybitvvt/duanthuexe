import React from 'react';
import { formatMoney } from '@/lib/formatters';

export interface VehicleReports {
  total_vehicle: number;
  total_price: number;
  total_vehicle_ready: number;
  total_vehicle_using: number;
  total_vehicle_ga: number;
  total_vehicle_so: number;
  total_vehicle_con: number;
}

interface VehicleSummaryPanelProps {
  reports: VehicleReports;
}

export const VehicleSummaryPanel: React.FC<VehicleSummaryPanelProps> = ({ reports }) => {
  return (
    <div className="vehicle-summary-panel mb-4" aria-label="Tổng hợp đội xe">
      <div className="vehicle-summary-title">Tổng hợp</div>
      <div className="vehicle-summary-grid">
        <div className="vehicle-summary-item">
          <span>Số lượng xe</span>
          <strong>{reports.total_vehicle || 0}</strong>
        </div>
        <div className="vehicle-summary-item wide">
          <span>Phí đầu tư</span>
          <strong>{formatMoney(reports.total_price || 0)}</strong>
        </div>
        <div className="vehicle-summary-item success">
          <span>Sẵn sàng</span>
          <strong>{reports.total_vehicle_ready || 0}</strong>
        </div>
        <div className="vehicle-summary-item primary">
          <span>Đang sử dụng</span>
          <strong>{reports.total_vehicle_using || 0}</strong>
        </div>
        <div className="vehicle-summary-item">
          <span>Xe ga</span>
          <strong>{reports.total_vehicle_ga || 0}</strong>
        </div>
        <div className="vehicle-summary-item">
          <span>Xe số</span>
          <strong>{reports.total_vehicle_so || 0}</strong>
        </div>
        <div className="vehicle-summary-item">
          <span>Xe côn</span>
          <strong>{reports.total_vehicle_con || 0}</strong>
        </div>
      </div>
    </div>
  );
};
