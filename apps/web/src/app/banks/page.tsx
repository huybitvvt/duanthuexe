'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { formatMoney } from '@/lib/formatters';

interface BankAccount {
  id: number;
  bank_name: string;
  account_number: string;
  account_holder: string;
  branch: string;
  balance: number;
  status: number;
}

const SAMPLE_BANKS: BankAccount[] = [
  { id: 1, bank_name: 'Vietcombank', account_number: '0011004321000', account_holder: 'CTY TNHH HIMOTO', branch: 'Sở giao dịch', balance: 145800000, status: 1 },
  { id: 2, bank_name: 'Techcombank', account_number: '1903554422001', account_holder: 'CTY TNHH HIMOTO', branch: 'Hà Nội', balance: 82400000, status: 1 },
  { id: 3, bank_name: 'MB Bank', account_number: '0888999888', account_holder: 'NGUYEN VAN QUAN', branch: 'Thăng Long', balance: 34100000, status: 1 },
];

export default function BanksPage() {
  const [banks, setBanks] = useState<BankAccount[]>(SAMPLE_BANKS);

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b">
          <div className="card-header d-flex justify-content-between align-items-center py-3">
            <h3 className="card-label font-weight-bold text-dark mb-0">
              Danh sách tài khoản ngân hàng
            </h3>
            <button type="button" className="btn btn-success btn-sm" onClick={() => alert('Thêm tài khoản ngân hàng')}>
              <i className="fas fa-plus mr-1" /> Thêm tài khoản
            </button>
          </div>
          <div className="card-body">
            <div className="table-responsive">
              <table className="table table-hover table-striped align-middle">
                <thead className="thead-light">
                  <tr>
                    <th style={{ width: '50px' }}>#ID</th>
                    <th>Ngân hàng</th>
                    <th>Số tài khoản</th>
                    <th>Chủ tài khoản</th>
                    <th>Chi nhánh</th>
                    <th className="text-right">Số dư khả dụng</th>
                    <th>Trạng thái</th>
                  </tr>
                </thead>
                <tbody>
                  {banks.map((b) => (
                    <tr key={b.id}>
                      <td className="font-weight-bold">#{b.id}</td>
                      <td><strong>{b.bank_name}</strong></td>
                      <td><code className="text-primary font-weight-bold font-size-sm">{b.account_number}</code></td>
                      <td>{b.account_holder}</td>
                      <td>{b.branch}</td>
                      <td className="text-right font-weight-bold text-success font-size-md">
                        {formatMoney(b.balance)}
                      </td>
                      <td>
                        <span className="badge badge-light-success text-success">Đang hoạt động</span>
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
