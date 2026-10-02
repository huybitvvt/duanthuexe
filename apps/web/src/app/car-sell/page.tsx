'use client';

import React, { useState } from 'react';
import AppShell from '@/components/layout/AppShell';
import { formatCurrency, formatDate } from '@/lib/formatters';

interface OrderSell {
  id: number;
  created_at: string;
  vehicle_name: string;
  license: string;
  store_name: string;
  customer_name: string;
  phone: string;
  id_card: string;
  address: string;
  total_price: number;
  cost_price: number;
  profit: number;
  status: 'completed' | 'pending' | 'cancelled';
}

const MOCK_ORDER_SELLS: OrderSell[] = [
  {
    id: 1,
    created_at: '2026-09-18T10:30:00',
    vehicle_name: 'Honda Vision 2022 Đen nhám',
    license: '29B1-888.88',
    store_name: '02 Hàng Bút',
    customer_name: 'Nguyễn Văn Tuấn',
    phone: '0981112233',
    id_card: '001099012345',
    address: '15 Hàng Đào, Hoàn Kiếm, Hà Nội',
    total_price: 28500000,
    cost_price: 22000000,
    profit: 6500000,
    status: 'completed',
  },
  {
    id: 2,
    created_at: '2026-09-15T14:15:00',
    vehicle_name: 'Yamaha Grande 2021 Trắng',
    license: '29C1-777.99',
    store_name: 'Cầu Giấy',
    customer_name: 'Lê Thu Trang',
    phone: '0973334455',
    id_card: '001198054321',
    address: '88 Cầu Giấy, Hà Nội',
    total_price: 32000000,
    cost_price: 26500000,
    profit: 5500000,
    status: 'completed',
  },
];

export default function OrderSellPage() {
  const [dateRange, setDateRange] = useState({ from: '', to: '' });
  const [searchVehicle, setSearchVehicle] = useState('');
  const [searchCustomer, setSearchCustomer] = useState('');
  const [selectedStore, setSelectedStore] = useState('all');
  const [selectedStatus, setSelectedStatus] = useState('all');

  const totalSell = MOCK_ORDER_SELLS.length;
  const totalPrice = MOCK_ORDER_SELLS.reduce((acc, o) => acc + o.total_price, 0);
  const totalCost = MOCK_ORDER_SELLS.reduce((acc, o) => acc + o.cost_price, 0);
  const totalProfit = MOCK_ORDER_SELLS.reduce((acc, o) => acc + o.profit, 0);

  return (
    <AppShell>
      <div className="card card-custom gutter-b">
        <div className="card-header d-flex justify-content-between align-items-center">
          <div className="card-title">
            <h3 className="card-label font-weight-bolder text-dark m-0">
              Danh sách đơn hàng (Bán xe)
            </h3>
          </div>
          <div className="card-toolbar">
            <button type="button" className="btn btn-primary font-weight-bold">
              + Tạo đơn bán xe
            </button>
          </div>
        </div>

        {/* SUMMARY STATS */}
        <div className="alert alert-custom alert-white alert-shadow fade show m-6 p-4" role="alert">
          <div className="alert-text">
            <div className="row">
              <div className="col-md-3">
                <p className="mb-1 text-muted">Số lượt bán: <span className="font-weight-bold text-dark">{totalSell}</span></p>
                <p className="mb-0 text-muted">Tổng thu: <span className="font-weight-bold text-success">{formatCurrency(totalPrice)}</span></p>
              </div>
              <div className="col-md-3">
                <p className="mb-1 text-muted">Phí đầu tư: <span className="font-weight-bold text-dark">{formatCurrency(totalCost)}</span></p>
                <p className="mb-0 text-muted">Lợi nhuận: <span className="font-weight-bold text-primary">{formatCurrency(totalProfit)}</span></p>
              </div>
            </div>
          </div>
        </div>

        <div className="card-body pt-0">
          {/* FILTER BAR */}
          <div className="row align-items-end mb-6">
            <div className="col-md-2">
              <div className="form-group mb-0">
                <label className="font-weight-bold text-muted font-size-sm">Tên xe, biển số</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Tên xe, biển số"
                  value={searchVehicle}
                  onChange={(e) => setSearchVehicle(e.target.value)}
                />
              </div>
            </div>
            <div className="col-md-2">
              <div className="form-group mb-0">
                <label className="font-weight-bold text-muted font-size-sm">Tên khách, SĐT, CCCD</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Tên khách, phone..."
                  value={searchCustomer}
                  onChange={(e) => setSearchCustomer(e.target.value)}
                />
              </div>
            </div>
            <div className="col-md-2">
              <div className="form-group mb-0">
                <label className="font-weight-bold text-muted font-size-sm">Cửa hàng</label>
                <select
                  className="form-control"
                  value={selectedStore}
                  onChange={(e) => setSelectedStore(e.target.value)}
                >
                  <option value="all">Tất cả cửa hàng</option>
                  <option value="1">02 Hàng Bút</option>
                  <option value="2">Cầu Giấy</option>
                </select>
              </div>
            </div>
            <div className="col-md-2">
              <div className="form-group mb-0">
                <label className="font-weight-bold text-muted font-size-sm">Trạng thái</label>
                <select
                  className="form-control"
                  value={selectedStatus}
                  onChange={(e) => setSelectedStatus(e.target.value)}
                >
                  <option value="all">Tất cả trạng thái</option>
                  <option value="completed">Đã hoàn thành</option>
                  <option value="pending">Đang xử lý</option>
                  <option value="cancelled">Đã hủy</option>
                </select>
              </div>
            </div>
            <div className="col-md-2">
              <button type="button" className="btn btn-primary font-weight-bold w-100">
                Tìm kiếm
              </button>
            </div>
          </div>

          {/* ORDERS TABLE */}
          <div className="table-responsive">
            <table className="table table-bordered table-hover">
              <thead className="thead-light">
                <tr>
                  <th>#</th>
                  <th>Ngày tạo</th>
                  <th>Tên xe</th>
                  <th>Biển số</th>
                  <th>Cửa hàng</th>
                  <th>Khách hàng</th>
                  <th>Số điện thoại</th>
                  <th>CMT/CCCD</th>
                  <th>Địa chỉ</th>
                  <th>Giá bán</th>
                  <th>Giá gốc</th>
                  <th>Lợi nhuận</th>
                  <th>Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                {MOCK_ORDER_SELLS.map((order, idx) => (
                  <tr key={order.id}>
                    <td>{idx + 1}</td>
                    <td>{formatDate(order.created_at)}</td>
                    <td className="font-weight-bolder text-dark">{order.vehicle_name}</td>
                    <td><span className="badge badge-secondary font-weight-bold">{order.license}</span></td>
                    <td>{order.store_name}</td>
                    <td className="font-weight-bold text-primary">{order.customer_name}</td>
                    <td>{order.phone}</td>
                    <td>{order.id_card}</td>
                    <td>{order.address}</td>
                    <td className="font-weight-bold text-success">{formatCurrency(order.total_price)}</td>
                    <td>{formatCurrency(order.cost_price)}</td>
                    <td className="font-weight-bold text-primary">{formatCurrency(order.profit)}</td>
                    <td>
                      <span className="badge badge-success px-2 py-1 font-weight-bold">
                        Đã hoàn thành
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </AppShell>
  );
}
