'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { formatMoney, formatDateTime } from '@/lib/formatters';

interface AccountingEntry {
  id: number;
  entry_code: string;
  debit_account: string;
  credit_account: string;
  amount: number;
  description: string;
  created_at: string;
  status: 'posted' | 'draft' | 'reversed';
}

const SAMPLE_ENTRIES: AccountingEntry[] = [
  { id: 1, entry_code: 'BT-202609-001', debit_account: '1121 - Tiền gửi ngân hàng VCB', credit_account: '131 - Phải thu khách hàng (HD-042)', amount: 1500000, description: 'Thu tiền cọc thuê xe', created_at: '2026-09-20 08:35', status: 'posted' },
  { id: 2, entry_code: 'BT-202609-002', debit_account: '1111 - Tiền mặt tại két', credit_account: '511 - Doanh thu cho thuê xe', amount: 650000, description: 'Doanh thu tiền thuê xe HD-041', created_at: '2026-09-19 12:10', status: 'posted' },
  { id: 3, entry_code: 'BT-202609-003', debit_account: '3388 - Phải trả khác (Hoàn cọc)', credit_account: '1121 - Tiền gửi ngân hàng TCB', amount: 1000000, description: 'Hoàn trả tiền cọc xe máy', created_at: '2026-09-19 12:15', status: 'posted' },
];

export default function AccountingPage() {
  const [entries, setEntries] = useState<AccountingEntry[]>(SAMPLE_ENTRIES);

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b mb-4">
          <div className="card-header d-flex justify-content-between align-items-center py-3">
            <h3 className="card-label font-weight-bold text-dark mb-0">
              Kế toán tổng hợp & Bút toán sổ cái
            </h3>
            <button type="button" className="btn btn-primary btn-sm" onClick={() => alert('Thêm bút toán mới')}>
              <i className="fas fa-plus mr-1" /> Thêm bút toán
            </button>
          </div>

          <div className="card-body">
            <div className="table-responsive">
              <table className="table table-hover table-striped align-middle">
                <thead className="thead-light">
                  <tr>
                    <th style={{ width: '50px' }}>#</th>
                    <th>Mã bút toán</th>
                    <th>Tài khoản Nợ</th>
                    <th>Tài khoản Có</th>
                    <th>Diễn giải</th>
                    <th className="text-right">Số tiền</th>
                    <th>Trạng thái</th>
                    <th>Thời gian</th>
                  </tr>
                </thead>
                <tbody>
                  {entries.map((e) => (
                    <tr key={e.id}>
                      <td>#{e.id}</td>
                      <td><code className="text-primary font-weight-bold font-size-sm">{e.entry_code}</code></td>
                      <td><strong className="text-dark">{e.debit_account}</strong></td>
                      <td><strong className="text-dark">{e.credit_account}</strong></td>
                      <td>{e.description}</td>
                      <td className="text-right font-weight-bold text-success font-size-md">
                        {formatMoney(e.amount)}
                      </td>
                      <td>
                        <span className="badge badge-light-success text-success font-weight-bold">Đã vào sổ</span>
                      </td>
                      <td className="font-size-sm text-muted">{formatDateTime(e.created_at)}</td>
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
