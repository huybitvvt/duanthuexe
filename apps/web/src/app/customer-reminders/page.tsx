'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { formatMoney, formatDateTime } from '@/lib/formatters';

interface ReminderItem {
  id: number;
  contract_number: string;
  customer_name: string;
  customer_phone: string;
  debt_amount: number;
  days_overdue: number;
  last_reminded_at?: string;
  status: 'pending' | 'sent' | 'paid';
}

const SAMPLE_REMINDERS: ReminderItem[] = [
  { id: 1, contract_number: 'HD-202609-040', customer_name: 'Phạm Đức Anh', customer_phone: '0904.555.777', debt_amount: 1200000, days_overdue: 3, last_reminded_at: '2026-09-19 14:00', status: 'sent' },
  { id: 2, contract_number: 'HD-202608-112', customer_name: 'Vũ Quốc Huy', customer_phone: '0912.888.777', debt_amount: 2500000, days_overdue: 7, last_reminded_at: '2026-09-18 09:30', status: 'pending' },
];

export default function CustomerRemindersPage() {
  const [reminders, setReminders] = useState<ReminderItem[]>(SAMPLE_REMINDERS);

  const handleSendReminder = (id: number) => {
    alert(`Đã gửi tin nhắn nhắc nợ và thông báo tới khách hàng!`);
    setReminders(reminders.map((r) => (r.id === id ? { ...r, status: 'sent', last_reminded_at: new Date().toISOString() } : r)));
  };

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b mb-4">
          <div className="card-header d-flex justify-content-between align-items-center py-3">
            <h3 className="card-label font-weight-bold text-dark mb-0">
              Nhắc nợ khách hàng & Quản lý công nợ quá hạn
            </h3>
            <button type="button" className="btn btn-warning btn-sm font-weight-bold" onClick={() => alert('Đã quét toàn bộ danh sách hợp đồng quá hạn!')}>
              <i className="fas fa-sync-alt mr-1" /> Quét công nợ tự động
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
                    <th>Số điện thoại</th>
                    <th>Số tiền nợ</th>
                    <th>Số ngày quá hạn</th>
                    <th>Lần nhắc gần nhất</th>
                    <th>Trạng thái</th>
                    <th className="text-right" style={{ width: '130px' }}>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  {reminders.map((r) => (
                    <tr key={r.id}>
                      <td>#{r.id}</td>
                      <td><strong className="text-primary">{r.contract_number}</strong></td>
                      <td><strong>{r.customer_name}</strong></td>
                      <td>{r.customer_phone}</td>
                      <td className="font-weight-bold text-danger font-size-md">{formatMoney(r.debt_amount)}</td>
                      <td>
                        <span className="badge badge-danger">{r.days_overdue} ngày</span>
                      </td>
                      <td className="font-size-sm text-muted">{r.last_reminded_at ? formatDateTime(r.last_reminded_at) : 'Chưa gửi'}</td>
                      <td>
                        <span className={`badge ${r.status === 'sent' ? 'badge-light-primary text-primary' : 'badge-light-warning text-warning'}`}>
                          {r.status === 'sent' ? 'Đã gửi SMS / Zalo' : 'Chưa gửi nhắc'}
                        </span>
                      </td>
                      <td className="text-right">
                        <button
                          type="button"
                          className="btn btn-sm btn-outline-warning font-weight-bold"
                          onClick={() => handleSendReminder(r.id)}
                        >
                          <i className="fas fa-paper-plane mr-1" /> Nhắc nợ
                        </button>
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
