'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { formatMoney } from '@/lib/formatters';

export default function KpiReportPage() {
  const [startDate, setStartDate] = useState('2026-09-01');
  const [endDate, setEndDate] = useState('2026-09-20');
  const [storeId, setStoreId] = useState('');

  const kpis = [
    { label: 'Tỷ lệ lấp đầy đội xe (Fleet Utilization)', value: '78.5%', target: '75.0%', status: 'success' },
    { label: 'Thời gian thuê trung bình', value: '4.2 ngày', target: '3.5 ngày', status: 'success' },
    { label: 'Doanh thu trung bình / xe / ngày', value: '185.000 đ', target: '180.000 đ', status: 'success' },
    { label: 'Tỷ lệ khách trả trễ hạn', value: '3.2%', target: '< 5.0%', status: 'warning' },
  ];

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b mb-4">
          <div className="card-header d-flex justify-content-between align-items-center py-3">
            <h3 className="card-label font-weight-bold text-dark mb-0">
              Báo cáo hiệu suất KPI & Chiến dịch
            </h3>
            <button type="button" className="btn btn-primary btn-sm" onClick={() => alert('Xuất báo cáo KPI')}>
              <i className="fas fa-file-excel mr-1" /> Xuất báo cáo
            </button>
          </div>

          <div className="card-body">
            {/* Filter */}
            <div className="row g-2 mb-4 bg-light p-3 rounded">
              <div className="col-12 col-md-3 mb-2">
                <label className="font-size-sm font-weight-bold text-muted">Từ ngày:</label>
                <input
                  type="date"
                  className="form-control"
                  value={startDate}
                  onChange={(e) => setStartDate(e.target.value)}
                />
              </div>
              <div className="col-12 col-md-3 mb-2">
                <label className="font-size-sm font-weight-bold text-muted">Đến ngày:</label>
                <input
                  type="date"
                  className="form-control"
                  value={endDate}
                  onChange={(e) => setEndDate(e.target.value)}
                />
              </div>
              <div className="col-12 col-md-3 mb-2">
                <label className="font-size-sm font-weight-bold text-muted">Cơ sở:</label>
                <select className="form-control" value={storeId} onChange={(e) => setStoreId(e.target.value)}>
                  <option value="">Toàn hệ thống</option>
                  <option value="1">02 Hàng Bút</option>
                  <option value="2">66 Nguyễn Hoàng</option>
                  <option value="3">476 Quang Trung</option>
                  <option value="4">91 Trần Quốc Hoàn</option>
                </select>
              </div>
              <div className="col-12 col-md-3 mb-2 pt-md-4">
                <button type="button" className="btn btn-primary w-100 font-weight-bold" onClick={() => alert('Tải lại số liệu KPI!')}>
                  <i className="fas fa-search mr-1" /> Xem KPI
                </button>
              </div>
            </div>

            {/* KPI Cards */}
            <div className="row g-3">
              {kpis.map((k, idx) => (
                <div key={idx} className="col-12 col-sm-6 col-xl-3 mb-3">
                  <div className="card card-custom h-100 border shadow-xs">
                    <div className="card-body p-4">
                      <div className="text-muted font-size-xs text-uppercase font-weight-bold mb-2">
                        {k.label}
                      </div>
                      <div className="font-size-h3 font-weight-bolder text-dark mb-2">
                        {k.value}
                      </div>
                      <div className="font-size-xs text-muted">
                        Mục tiêu: <strong className="text-primary">{k.target}</strong>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </AppShell>
  );
}
