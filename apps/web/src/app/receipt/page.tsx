'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { formatMoney, formatDateTime } from '@/lib/formatters';

interface ReceiptItem {
  id: number;
  code: string;
  type: 'thu' | 'chi';
  payer_receiver: string;
  amount: number;
  payment_method: string;
  reason: string;
  store_name: string;
  created_at: string;
}

const SAMPLE_RECEIPTS: ReceiptItem[] = [
  { id: 1, code: 'PT-2026-001', type: 'thu', payer_receiver: 'Trần Văn Mạnh', amount: 1500000, payment_method: 'Chuyển khoản VCB', reason: 'Tiền cọc đơn HD-202609-042', store_name: '02 Hàng Bút', created_at: '2026-09-20 08:35' },
  { id: 2, code: 'PT-2026-002', type: 'thu', payer_receiver: 'Lê Thị Thu', amount: 650000, payment_method: 'Tiền mặt', reason: 'Thanh toán phí thuê xe HD-202609-041', store_name: '66 Nguyễn Hoàng', created_at: '2026-09-19 12:10' },
  { id: 3, code: 'PC-2026-001', type: 'chi', payer_receiver: 'Lê Thị Thu', amount: 1000000, payment_method: 'Chuyển khoản TCB', reason: 'Hoàn trả cọc đơn HD-202609-041', store_name: '66 Nguyễn Hoàng', created_at: '2026-09-19 12:15' },
  { id: 4, code: 'PC-2026-002', type: 'chi', payer_receiver: 'Cửa hàng phụ tùng xe máy', amount: 350000, payment_method: 'Tiền mặt', reason: 'Thay nhớt và bảo dưỡng định kỳ 2 xe Vision', store_name: '02 Hàng Bút', created_at: '2026-09-18 15:20' },
];

export default function ReceiptPage() {
  const [receipts, setReceipts] = useState<ReceiptItem[]>(SAMPLE_RECEIPTS);
  const [typeFilter, setTypeFilter] = useState('');

  const filtered = typeFilter ? receipts.filter((r) => r.type === typeFilter) : receipts;

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b">
          <div className="card-header d-flex justify-content-between align-items-center flex-wrap py-3">
            <div className="card-title mb-2 mb-sm-0">
              <h3 className="card-label font-weight-bold text-dark mb-0">
                Quản lý phiếu thu & phiếu chi
              </h3>
            </div>
            <div className="d-flex gap-2">
              <button type="button" className="btn btn-success btn-sm mr-2" onClick={() => alert('Tạo phiếu thu mới')}>
                <i className="fas fa-plus mr-1" /> Tạo phiếu thu
              </button>
              <button type="button" className="btn btn-danger btn-sm" onClick={() => alert('Tạo phiếu chi mới')}>
                <i className="fas fa-minus mr-1" /> Tạo phiếu chi
              </button>
            </div>
          </div>

          <div className="card-body">
            {/* Filter buttons */}
            <div className="mb-3 d-flex gap-2">
              <button
                type="button"
                className={`btn btn-sm mr-2 ${!typeFilter ? 'btn-primary' : 'btn-light'}`}
                onClick={() => setTypeFilter('')}
              >
                Tất cả phiếu
              </button>
              <button
                type="button"
                className={`btn btn-sm mr-2 ${typeFilter === 'thu' ? 'btn-success' : 'btn-light-success text-success'}`}
                onClick={() => setTypeFilter('thu')}
              >
                Phiếu thu (+)
              </button>
              <button
                type="button"
                className={`btn btn-sm ${typeFilter === 'chi' ? 'btn-danger' : 'btn-light-danger text-danger'}`}
                onClick={() => setTypeFilter('chi')}
              >
                Phiếu chi (-)
              </button>
            </div>

            <div className="table-responsive">
              <table className="table table-hover table-striped align-middle">
                <thead className="thead-light">
                  <tr>
                    <th style={{ width: '50px' }}>#</th>
                    <th>Mã phiếu</th>
                    <th>Loại</th>
                    <th>Đối tượng nộp / nhận</th>
                    <th>Lý do / Diễn giải</th>
                    <th>Phương thức</th>
                    <th>Cơ sở</th>
                    <th>Số tiền</th>
                    <th>Ngày lập</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((r) => (
                    <tr key={r.id}>
                      <td>#{r.id}</td>
                      <td>
                        <span className="badge badge-light-dark font-weight-bold">{r.code}</span>
                      </td>
                      <td>
                        <span className={`badge ${r.type === 'thu' ? 'badge-success' : 'badge-danger'}`}>
                          {r.type === 'thu' ? 'PHIẾU THU' : 'PHIẾU CHI'}
                        </span>
                      </td>
                      <td><strong>{r.payer_receiver}</strong></td>
                      <td>{r.reason}</td>
                      <td><span className="badge badge-secondary">{r.payment_method}</span></td>
                      <td>{r.store_name}</td>
                      <td>
                        <strong className={`font-size-md ${r.type === 'thu' ? 'text-success' : 'text-danger'}`}>
                          {r.type === 'thu' ? '+' : '-'}{formatMoney(r.amount)}
                        </strong>
                      </td>
                      <td className="font-size-sm text-muted">{formatDateTime(r.created_at)}</td>
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
