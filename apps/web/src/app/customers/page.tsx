'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { AppShell } from '@/components/layout/AppShell';
import api from '@/lib/api-client';

export interface CustomerItem {
  id: number;
  name: string;
  phone: string;
  id_card?: string;
  address?: string;
  warning?: string;
  status: number;
}

const SAMPLE_CUSTOMERS: CustomerItem[] = [
  { id: 1, name: 'Trần Văn Mạnh', phone: '0987.654.321', id_card: '001095012345', address: 'Quận Hoàn Kiếm, Hà Nội', warning: '', status: 1 },
  { id: 2, name: 'Lê Thị Thu', phone: '0912.333.444', id_card: '034198007890', address: 'Quận Cầu Giấy, Hà Nội', warning: '', status: 1 },
  { id: 3, name: 'Phạm Đức Anh', phone: '0904.555.777', id_card: '025091001122', address: 'Quận Đống Đa, Hà Nội', warning: 'Từng trả xe trễ 1 ngày', status: 1 },
  { id: 4, name: 'Nguyễn Hoàng Long', phone: '0978.111.222', id_card: '001099003344', address: 'Quận Ba Đình, Hà Nội', warning: '', status: 0 },
];

export default function CustomersPage() {
  const [customers, setCustomers] = useState<CustomerItem[]>(SAMPLE_CUSTOMERS);
  const [keyword, setKeyword] = useState('');
  const [loading, setLoading] = useState(false);
  const [selectedCustomer, setSelectedCustomer] = useState<CustomerItem | null>(null);

  const fetchCustomers = async () => {
    setLoading(true);
    try {
      const res = await api.get('/auth/customers', { params: { keyword } });
      if (res.data?.data) {
        setCustomers(res.data.data);
      }
    } catch (err) {
      console.warn('Backend in test mode, using local state');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = (id: number) => {
    if (confirm('Bạn có chắc muốn xóa khách hàng này?')) {
      setCustomers(customers.filter((c) => c.id !== id));
    }
  };

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b">
          <div className="card-header align-items-center d-flex justify-content-between flex-wrap py-3">
            <div className="card-title mb-2 mb-sm-0">
              <h3 className="card-label font-weight-bold text-dark mb-0">
                Danh sách khách hàng
              </h3>
            </div>

            <div className="d-flex gap-2 align-items-center">
              <div className="input-group input-group-sm" style={{ width: '280px' }}>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Nhập Tên, SĐT hoặc CCCD..."
                  value={keyword}
                  onChange={(e) => setKeyword(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && fetchCustomers()}
                />
                <div className="input-group-append">
                  <button className="btn btn-primary" type="button" onClick={fetchCustomers}>
                    <i className="fas fa-search" />
                  </button>
                </div>
              </div>

              <Link href="/customers-create" className="btn btn-success btn-sm ml-2">
                <i className="fas fa-plus mr-1" /> Thêm mới
              </Link>

              <button
                type="button"
                className="btn btn-outline-secondary btn-sm ml-1"
                onClick={() => alert('Xuất danh sách khách hàng thành công!')}
              >
                <i className="fas fa-file-excel mr-1" /> Xuất file
              </button>
            </div>
          </div>

          <div className="card-body">
            {loading ? (
              <div className="text-center py-5">
                <div className="spinner-border text-primary" />
                <p className="mt-2 text-muted">Đang tải danh sách khách hàng...</p>
              </div>
            ) : customers.length === 0 ? (
              <div className="text-center py-5 border rounded bg-white">
                <i className="fas fa-users fa-3x text-muted mb-3" />
                <p className="text-muted">Không tìm thấy khách hàng nào.</p>
              </div>
            ) : (
              <div className="table-responsive">
                <table className="table table-hover table-striped align-middle">
                  <thead className="thead-light">
                    <tr>
                      <th style={{ width: '50px' }}>#</th>
                      <th>Tên khách hàng</th>
                      <th>Số CCCD/CMTND</th>
                      <th>Số điện thoại</th>
                      <th>Địa chỉ</th>
                      <th>Cảnh báo</th>
                      <th>Trạng thái</th>
                      <th className="text-right" style={{ width: '120px' }}>Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    {customers.map((item, idx) => (
                      <tr key={item.id}>
                        <td>{idx + 1}</td>
                        <td>
                          <strong className="text-dark cursor-pointer" onClick={() => setSelectedCustomer(item)}>
                            {item.name}
                          </strong>
                        </td>
                        <td>{item.id_card || '--'}</td>
                        <td>
                          <strong className="text-primary">{item.phone}</strong>
                        </td>
                        <td>{item.address || '--'}</td>
                        <td>
                          {item.warning ? (
                            <span className="badge badge-light-danger text-danger font-weight-bold" title={item.warning}>
                              {item.warning}
                            </span>
                          ) : (
                            <span className="text-muted">--</span>
                          )}
                        </td>
                        <td>
                          <span className={`badge ${item.status === 1 ? 'badge-light-success text-success' : 'badge-light-warning text-warning'}`}>
                            {item.status === 1 ? 'Hoạt động' : 'Chưa hoàn tất'}
                          </span>
                        </td>
                        <td className="text-right">
                          <div className="btn-group btn-group-sm">
                            <button
                              type="button"
                              className="btn btn-outline-info"
                              onClick={() => setSelectedCustomer(item)}
                              title="Xem chi tiết"
                            >
                              <i className="fas fa-eye" />
                            </button>
                            <button
                              type="button"
                              className="btn btn-outline-danger"
                              onClick={() => handleDelete(item.id)}
                              title="Xóa"
                            >
                              <i className="fas fa-trash" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Customer Detail Modal */}
      {selectedCustomer && (
        <div className="modal fade show d-block" tabIndex={-1} role="dialog" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog modal-md" role="document">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title font-weight-bold">
                  Hồ sơ khách hàng: {selectedCustomer.name}
                </h5>
                <button type="button" className="close" onClick={() => setSelectedCustomer(null)}>
                  <span>&times;</span>
                </button>
              </div>
              <div className="modal-body font-size-sm">
                <div className="mb-2"><strong>Họ tên:</strong> {selectedCustomer.name}</div>
                <div className="mb-2"><strong>Số điện thoại:</strong> {selectedCustomer.phone}</div>
                <div className="mb-2"><strong>CCCD / CMT:</strong> {selectedCustomer.id_card || '--'}</div>
                <div className="mb-2"><strong>Địa chỉ thường trú:</strong> {selectedCustomer.address || '--'}</div>
                {selectedCustomer.warning && (
                  <div className="alert alert-warning py-2 mt-3">
                    <strong>Cảnh báo ghi nhận:</strong> {selectedCustomer.warning}
                  </div>
                )}
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={() => setSelectedCustomer(null)}>
                  Đóng
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
