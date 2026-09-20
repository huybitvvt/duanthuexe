'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { formatMoney } from '@/lib/formatters';

interface VehicleRevenueItem {
  id: number;
  name: string;
  license: string;
  store_name: string;
  total_trips: number;
  rental_revenue: number;
  late_fee: number;
  maintenance_cost: number;
  net_revenue: number;
}

const SAMPLE_VEHICLE_REVENUE: VehicleRevenueItem[] = [
  { id: 1, name: 'Honda Vision 2023 Trắng', license: '29B1-987.65', store_name: '02 Hàng Bút', total_trips: 18, rental_revenue: 14400000, late_fee: 400000, maintenance_cost: 650000, net_revenue: 14150000 },
  { id: 2, name: 'Yamaha Grande 125 Đỏ', license: '29H1-543.21', store_name: '66 Nguyễn Hoàng', total_trips: 15, rental_revenue: 12600000, late_fee: 200000, maintenance_cost: 350000, net_revenue: 12450000 },
  { id: 3, name: 'Honda Air Blade 160 Xanh', license: '29X5-888.99', store_name: '02 Hàng Bút', total_trips: 22, rental_revenue: 19800000, late_fee: 800000, maintenance_cost: 1200000, net_revenue: 19400000 },
  { id: 4, name: 'Honda Wave Alpha 110 Đen', license: '29A1-222.33', store_name: '476 Quang Trung', total_trips: 26, rental_revenue: 8200000, late_fee: 100000, maintenance_cost: 450000, net_revenue: 7850000 },
  { id: 5, name: 'Yamaha Exciter 155 VVA', license: '29F1-777.88', store_name: '91 Trần Quốc Hoàn', total_trips: 14, rental_revenue: 11200000, late_fee: 500000, maintenance_cost: 800000, net_revenue: 10900000 },
];

export default function VehicleRevenuePage() {
  const [data, setData] = useState<VehicleRevenueItem[]>(SAMPLE_VEHICLE_REVENUE);
  const [keyword, setKeyword] = useState('');
  const [storeFilter, setStoreFilter] = useState('');

  const totalRevenue = data.reduce((acc, row) => acc + row.net_revenue, 0);

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b mb-4">
          <div className="card-header d-flex justify-content-between align-items-center flex-wrap py-3">
            <div>
              <h3 className="card-label font-weight-bold text-dark mb-1">
                Báo cáo doanh thu theo xe
              </h3>
              <p className="text-muted font-size-sm mb-0">
                Tổng doanh thu ròng đội xe: <strong className="text-success font-size-md">{formatMoney(totalRevenue)}</strong>
              </p>
            </div>
            <button type="button" className="btn btn-primary btn-sm" onClick={() => alert('Xuất Excel doanh thu xe thành công')}>
              <i className="fas fa-file-excel mr-1" /> Xuất Excel
            </button>
          </div>

          <div className="card-body">
            <div className="row g-2 mb-4">
              <div className="col-12 col-md-4 mb-2">
                <input
                  type="text"
                  className="form-control"
                  placeholder="Tìm theo tên xe, biển số..."
                  value={keyword}
                  onChange={(e) => setKeyword(e.target.value)}
                />
              </div>
              <div className="col-12 col-md-3 mb-2">
                <select
                  className="form-control"
                  value={storeFilter}
                  onChange={(e) => setStoreFilter(e.target.value)}
                >
                  <option value="">Tất cả cơ sở</option>
                  <option value="02 Hàng Bút">02 Hàng Bút</option>
                  <option value="66 Nguyễn Hoàng">66 Nguyễn Hoàng</option>
                  <option value="476 Quang Trung">476 Quang Trung</option>
                  <option value="91 Trần Quốc Hoàn">91 Trần Quốc Hoàn</option>
                </select>
              </div>
            </div>

            <div className="table-responsive">
              <table className="table table-hover table-bordered align-middle">
                <thead className="thead-light text-center">
                  <tr>
                    <th style={{ width: '50px' }}>#</th>
                    <th>Tên xe</th>
                    <th>Biển số</th>
                    <th>Cơ sở</th>
                    <th>Lượt thuê</th>
                    <th>Doanh thu thuê</th>
                    <th>Phạt muộn</th>
                    <th>Chi phí bảo dưỡng</th>
                    <th>Doanh thu ròng</th>
                  </tr>
                </thead>
                <tbody>
                  {data.map((row, idx) => (
                    <tr key={row.id} className="text-center">
                      <td>{idx + 1}</td>
                      <td className="text-left font-weight-bold">{row.name}</td>
                      <td><span className="badge badge-light-dark font-weight-bold">{row.license}</span></td>
                      <td>{row.store_name}</td>
                      <td><span className="badge badge-info">{row.total_trips} lượt</span></td>
                      <td className="text-right text-success font-weight-bold">{formatMoney(row.rental_revenue)}</td>
                      <td className="text-right text-warning">{formatMoney(row.late_fee)}</td>
                      <td className="text-right text-danger">-{formatMoney(row.maintenance_cost)}</td>
                      <td className="text-right font-weight-bold text-primary font-size-md">
                        {formatMoney(row.net_revenue)}
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
