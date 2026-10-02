'use client';

import React from 'react';
import Link from 'next/link';
import { AppShell } from '@/components/layout/AppShell';

export default function DashboardPage() {
  const stats = [
    { label: 'Xe đang cho thuê', value: '42', unit: 'xe', change: '+5% so với hôm qua', color: 'primary' },
    { label: 'Xe sẵn sàng', value: '18', unit: 'xe', change: 'Còn trống', color: 'success' },
    { label: 'Doanh thu hôm nay', value: '12.850.000', unit: 'VNĐ', change: '+12% so với hôm qua', color: 'warning' },
    { label: 'Hợp đồng cần xử lý', value: '3', unit: 'đơn', change: 'Quá hạn / gia hạn', color: 'danger' },
  ];

  return (
    <AppShell>
      <div className="container-fluid py-4">
        {/* Page Header */}
        <div className="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h1 className="h3 font-weight-bold text-dark mb-1">Tổng quan hoạt động</h1>
            <p className="text-muted mb-0">Hệ thống quản lý đội xe và hợp đồng thuê HIMOTO</p>
          </div>
          <div className="d-flex gap-2">
            <Link href="/car-rental" className="btn btn-primary">
              <i className="fas fa-file-contract mr-1" /> Quản lý đơn thuê
            </Link>
          </div>
        </div>

        {/* Stats Grid */}
        <div className="row mb-4">
          {stats.map((st, idx) => (
            <div key={idx} className="col-12 col-sm-6 col-xl-3 mb-3">
              <div className={`card card-custom h-100 card-stat-${st.color}`} style={{ borderRadius: '10px', boxShadow: '0 0 20px 0 rgba(76,87,125,.05)' }}>
                <div className="card-body p-4">
                  <div className="text-muted font-weight-bold text-uppercase font-size-sm mb-2">
                    {st.label}
                  </div>
                  <div className="d-flex align-items-baseline mb-2">
                    <span className="font-size-h2 font-weight-bolder mr-2">{st.value}</span>
                    <span className="text-muted font-weight-bold font-size-sm">{st.unit}</span>
                  </div>
                  <div className="font-size-xs text-muted">
                    {st.change}
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Action Shortcuts */}
        <div className="card card-custom mb-4" style={{ borderRadius: '10px' }}>
          <div className="card-header border-0 pt-4 pb-2">
            <h2 className="card-title font-weight-bolder text-dark font-size-h5">
              Truy cập nhanh nghiệp vụ
            </h2>
          </div>
          <div className="card-body pt-0 pb-4">
            <div className="d-flex flex-wrap gap-3">
              <Link href="/car-rental" className="btn btn-outline-primary mr-2 mb-2">
                <i className="fas fa-file-contract mr-2" /> Đơn thuê xe
              </Link>
              <Link href="/vehicles" className="btn btn-outline-info mr-2 mb-2">
                <i className="fas fa-motorcycle mr-2" /> Danh sách xe
              </Link>
              <Link href="/transactions" className="btn btn-outline-success mr-2 mb-2">
                <i className="fas fa-money-bill-wave mr-2" /> Lịch sử thu chi
              </Link>
              <Link href="/report/detail-report" className="btn btn-outline-warning mr-2 mb-2">
                <i className="fas fa-chart-pie mr-2" /> Báo cáo tổng quan
              </Link>
              <Link href="/report/vehicle-revenue" className="btn btn-outline-secondary mr-2 mb-2">
                <i className="fas fa-coins mr-2" /> Doanh thu xe
              </Link>
            </div>
          </div>
        </div>
      </div>
    </AppShell>
  );
}
