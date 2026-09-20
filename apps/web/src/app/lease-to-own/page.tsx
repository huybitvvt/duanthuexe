'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { formatMoney, formatDateTime } from '@/lib/formatters';

interface LeaseContractItem {
  id: number;
  contract_number: string;
  customer_name: string;
  vehicle_name: string;
  license: string;
  total_term_months: number;
  paid_months: number;
  monthly_payment: number;
  remaining_debt: number;
  status: 'active' | 'completed' | 'overdue';
}

const SAMPLE_LEASES: LeaseContractItem[] = [
  { id: 1, contract_number: 'TSH-2026-001', customer_name: 'Nguyễn Văn Thành', vehicle_name: 'Honda Vision 2024 Đen Nhám', license: '29B1-111.22', total_term_months: 12, paid_months: 5, monthly_payment: 3200000, remaining_debt: 22400000, status: 'active' },
  { id: 2, contract_number: 'TSH-2025-018', customer_name: 'Hoàng Quốc Việt', vehicle_name: 'Yamaha NVX 155 VVA', license: '29E1-333.44', total_term_months: 18, paid_months: 18, monthly_payment: 2800000, remaining_debt: 0, status: 'completed' },
];

export default function LeaseToOwnPage() {
  const [leases, setLeases] = useState<LeaseContractItem[]>(SAMPLE_LEASES);

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b mb-4">
          <div className="card-header d-flex justify-content-between align-items-center py-3">
            <h3 className="card-label font-weight-bold text-dark mb-0">
              Hợp đồng thuê xe sở hữu (Lease-to-own)
            </h3>
            <button type="button" className="btn btn-success btn-sm" onClick={() => alert('Tạo hợp đồng thuê sở hữu mới')}>
              <i className="fas fa-plus mr-1" /> Thêm hợp đồng
            </button>
          </div>

          <div className="card-body">
            <div className="table-responsive">
              <table className="table table-hover table-striped align-middle">
                <thead className="thead-light">
                  <tr>
                    <th style={{ width: '50px' }}>#</th>
                    <th>Số HĐ</th>
                    <th>Khách hàng</th>
                    <th>Xe thuê sở hữu</th>
                    <th>Kỳ hạn</th>
                    <th>Đã trả</th>
                    <th>Số tiền/kỳ</th>
                    <th>Dư nợ còn lại</th>
                    <th>Trạng thái</th>
                  </tr>
                </thead>
                <tbody>
                  {leases.map((l) => (
                    <tr key={l.id}>
                      <td>#{l.id}</td>
                      <td><strong className="text-primary">{l.contract_number}</strong></td>
                      <td><strong>{l.customer_name}</strong></td>
                      <td>
                        <strong>{l.vehicle_name}</strong>
                        <span className="badge badge-light-dark ml-2">{l.license}</span>
                      </td>
                      <td>{l.total_term_months} tháng</td>
                      <td>
                        <span className="badge badge-info">{l.paid_months} / {l.total_term_months}</span>
                      </td>
                      <td className="font-weight-bold text-dark">{formatMoney(l.monthly_payment)}</td>
                      <td className="font-weight-bold text-danger">{formatMoney(l.remaining_debt)}</td>
                      <td>
                        <span className={`badge ${l.status === 'completed' ? 'badge-light-success text-success' : 'badge-light-primary text-primary'}`}>
                          {l.status === 'completed' ? 'Đã tất toán' : 'Đang thực hiện'}
                        </span>
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
