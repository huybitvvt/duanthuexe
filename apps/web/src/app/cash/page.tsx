'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { formatMoney } from '@/lib/formatters';

interface CashFund {
  id: number;
  fund_name: string;
  store_name: string;
  manager_name: string;
  balance: number;
  status: number;
}

const SAMPLE_FUNDS: CashFund[] = [
  { id: 1, fund_name: 'Két tiền mặt cơ sở 1', store_name: '02 Hàng Bút', manager_name: 'Nguyễn Thu Hà', balance: 15750000, status: 1 },
  { id: 2, fund_name: 'Két tiền mặt cơ sở 2', store_name: '66 Nguyễn Hoàng', manager_name: 'Trần Văn Nam', balance: 8400000, status: 1 },
  { id: 3, fund_name: 'Két tiền mặt cơ sở 3', store_name: '476 Quang Trung', manager_name: 'Lê Văn Tùng', balance: 6200000, status: 1 },
  { id: 4, fund_name: 'Két tiền mặt cơ sở 4', store_name: '91 Trần Quốc Hoàn', manager_name: 'Phạm Hồng Nhung', balance: 9100000, status: 1 },
];

export default function CashPage() {
  const [funds, setFunds] = useState<CashFund[]>(SAMPLE_FUNDS);

  const totalCash = funds.reduce((acc, f) => acc + f.balance, 0);

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b mb-4">
          <div className="card-header d-flex justify-content-between align-items-center py-3">
            <div>
              <h3 className="card-label font-weight-bold text-dark mb-1">
                Quản lý quỹ tiền mặt tại các cơ sở
              </h3>
              <p className="text-muted font-size-sm mb-0">Tổng tồn quỹ tiền mặt toàn hệ thống: <strong className="text-success font-size-md">{formatMoney(totalCash)}</strong></p>
            </div>
          </div>
          <div className="card-body">
            <div className="table-responsive">
              <table className="table table-hover table-striped align-middle">
                <thead className="thead-light">
                  <tr>
                    <th style={{ width: '50px' }}>#ID</th>
                    <th>Tên quỹ</th>
                    <th>Cơ sở / Cửa hàng</th>
                    <th>Thủ quỹ phụ trách</th>
                    <th className="text-right">Số dư tồn két</th>
                    <th>Trạng thái</th>
                  </tr>
                </thead>
                <tbody>
                  {funds.map((f) => (
                    <tr key={f.id}>
                      <td className="font-weight-bold">#{f.id}</td>
                      <td><strong>{f.fund_name}</strong></td>
                      <td>{f.store_name}</td>
                      <td>{f.manager_name}</td>
                      <td className="text-right font-weight-bold text-primary font-size-md">
                        {formatMoney(f.balance)}
                      </td>
                      <td>
                        <span className="badge badge-light-success text-success">Hoạt động</span>
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
