'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { AppShell } from '@/components/layout/AppShell';
import { formatDateTime } from '@/lib/formatters';

interface LeadItem {
  id: number;
  customer_name: string;
  customer_phone: string;
  vehicle_demand: string;
  source: string;
  store_name: string;
  status: 'pending' | 'completed' | 'canceled';
  created_at: string;
}

const SAMPLE_LEADS: LeadItem[] = [
  {
    id: 301,
    customer_name: 'Hoàng Minh Tuấn',
    customer_phone: '0934.888.999',
    vehicle_demand: 'Thuê Honda Vision 3 ngày cuối tuần',
    source: 'Landing page Himoto',
    store_name: '02 Hàng Bút',
    status: 'pending',
    created_at: '2026-09-20 14:30',
  },
  {
    id: 300,
    customer_name: 'Đỗ Phương Nga',
    customer_phone: '0977.222.333',
    vehicle_demand: 'Thuê xe Air Blade 1 tuần du lịch',
    source: 'Facebook Ads',
    store_name: '66 Nguyễn Hoàng',
    status: 'completed',
    created_at: '2026-09-19 10:15',
  },
  {
    id: 299,
    customer_name: 'Nguyễn Văn Hùng',
    customer_phone: '0918.444.555',
    vehicle_demand: 'Cần thuê 2 xe Wave cho bạn đi phượt',
    source: 'Zalo Hotline',
    store_name: '476 Quang Trung',
    status: 'pending',
    created_at: '2026-09-19 09:00',
  },
];

export default function LeadsPage() {
  const [leads, setLeads] = useState<LeadItem[]>(SAMPLE_LEADS);
  const [keyword, setKeyword] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [storeFilter, setStoreFilter] = useState('');

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'completed':
        return <span className="badge badge-light-success text-success">Hoàn thành / Đã chốt</span>;
      case 'pending':
        return <span className="badge badge-light-warning text-warning">Đang chờ xử lý</span>;
      default:
        return <span className="badge badge-light-danger text-danger">Đã hủy</span>;
    }
  };

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b">
          <div className="card-header align-items-center d-flex justify-content-between flex-wrap py-3">
            <div className="card-title mb-2 mb-sm-0">
              <h3 className="card-label font-weight-bold text-dark mb-0">
                Nguồn khách hàng tiềm năng (Leads)
              </h3>
            </div>

            <div className="d-flex gap-2">
              <button
                type="button"
                className="btn btn-success btn-sm"
                onClick={() => alert('Chức năng thêm lead thủ công')}
              >
                <i className="fas fa-plus mr-1" /> Thêm Lead mới
              </button>
            </div>
          </div>

          <div className="card-body">
            {/* Filter Row */}
            <div className="row g-2 mb-4">
              <div className="col-12 col-md-4 mb-2">
                <input
                  type="text"
                  className="form-control"
                  placeholder="Tên khách hàng, SĐT, loại xe cần thuê..."
                  value={keyword}
                  onChange={(e) => setKeyword(e.target.value)}
                />
              </div>
              <div className="col-12 col-md-3 mb-2">
                <select
                  className="form-control"
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                >
                  <option value="">Tất cả trạng thái</option>
                  <option value="pending">Đang chờ</option>
                  <option value="completed">Hoàn thành</option>
                </select>
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
                </select>
              </div>
            </div>

            {/* Leads Table */}
            <div className="table-responsive">
              <table className="table table-hover table-striped align-middle">
                <thead className="thead-light">
                  <tr>
                    <th style={{ width: '50px' }}>#ID</th>
                    <th>Khách hàng</th>
                    <th>Nhu cầu thuê</th>
                    <th>Cơ sở dự kiến</th>
                    <th>Nguồn Lead</th>
                    <th>Trạng thái</th>
                    <th>Thời gian gửi</th>
                    <th className="text-right" style={{ width: '130px' }}>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  {leads.map((lead) => (
                    <tr key={lead.id}>
                      <td className="font-weight-bold">#{lead.id}</td>
                      <td>
                        <div className="font-weight-bold">{lead.customer_name}</div>
                        <div className="text-primary font-size-sm">{lead.customer_phone}</div>
                      </td>
                      <td>
                        <span className="badge badge-light-info">{lead.vehicle_demand}</span>
                      </td>
                      <td>{lead.store_name}</td>
                      <td>
                        <span className="badge badge-secondary">{lead.source}</span>
                      </td>
                      <td>{getStatusBadge(lead.status)}</td>
                      <td className="font-size-sm text-muted">{formatDateTime(lead.created_at)}</td>
                      <td className="text-right">
                        <Link
                          href={`/car-rental?action=create&name=${encodeURIComponent(lead.customer_name)}&phone=${lead.customer_phone}`}
                          className="btn btn-sm btn-outline-primary mr-1"
                          title="Chuyển thành hợp đồng thuê"
                        >
                          <i className="fas fa-file-contract mr-1" /> Chốt đơn
                        </Link>
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
