'use client';

import React from 'react';
import { formatMoney, formatDateTime } from '@/lib/formatters';
import { RentalOrderItem } from './RentalOrderTable';

interface ContractPrintModalProps {
  order: RentalOrderItem | null;
  isOpen: boolean;
  onClose: () => void;
}

export const ContractPrintModal: React.FC<ContractPrintModalProps> = ({
  order,
  isOpen,
  onClose,
}) => {
  if (!isOpen || !order) return null;

  const handlePrint = () => {
    window.print();
  };

  return (
    <div className="modal fade show d-block" tabIndex={-1} role="dialog" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
      <div className="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div className="modal-content">
          <div className="modal-header d-print-none">
            <h5 className="modal-title font-weight-bold">
              Xem trước hợp đồng: {order.contract_number}
            </h5>
            <div className="d-flex gap-2">
              <button type="button" className="btn btn-primary btn-sm mr-2" onClick={handlePrint}>
                <i className="fas fa-print mr-1" /> In hợp đồng
              </button>
              <button type="button" className="close" onClick={onClose} aria-label="Đóng">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
          </div>

          <div className="modal-body p-4 bg-white text-dark contract-print-body" id="printableContract">
            {/* Header branding */}
            <div className="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
              <div>
                <img src="/images/branding/logo-himoto-pdf.png" alt="HIMOTO" style={{ height: '45px' }} />
                <div className="font-size-sm text-muted">Hệ thống cho thuê xe máy du lịch & tự lái</div>
              </div>
              <div className="text-right">
                <h4 className="font-weight-bold text-uppercase mb-0">HỢP ĐỒNG THUÊ XE</h4>
                <div className="font-size-sm text-muted">Số HĐ: <strong className="text-dark">{order.contract_number}</strong></div>
              </div>
            </div>

            {/* Thông tin 2 bên */}
            <div className="row mb-3">
              <div className="col-6">
                <div className="p-2 border rounded bg-light font-size-sm">
                  <strong className="text-uppercase d-block mb-1">BÊN CHO THUÊ (BÊN A):</strong>
                  <div><strong>CÔNG TY TNHH HIMOTO VIỆT NAM</strong></div>
                  <div>Cơ sở: {order.store?.name || 'Cơ sở chính'}</div>
                  <div>Hotline: 098.123.4567</div>
                </div>
              </div>
              <div className="col-6">
                <div className="p-2 border rounded bg-light font-size-sm">
                  <strong className="text-uppercase d-block mb-1">BÊN THUÊ XE (BÊN B):</strong>
                  <div>Họ tên: <strong>{order.customer?.name || 'Khách hàng'}</strong></div>
                  <div>Số điện thoại: <strong>{order.customer?.phone || '--'}</strong></div>
                </div>
              </div>
            </div>

            {/* Chi tiết xe và thời gian */}
            <table className="table table-bordered table-sm font-size-sm mb-3">
              <thead className="thead-light">
                <tr>
                  <th>Xe thuê</th>
                  <th>Biển số xe</th>
                  <th>Thời gian nhận xe</th>
                  <th>Thời gian hẹn trả</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>{order.vehicles?.[0]?.name || 'Xe máy số / ga'}</td>
                  <td><strong>{order.vehicles?.[0]?.license || '--'}</strong></td>
                  <td>{formatDateTime(order.start_date)}</td>
                  <td>{formatDateTime(order.end_date)}</td>
                </tr>
              </tbody>
            </table>

            {/* Chi phí và tiền cọc */}
            <table className="table table-bordered table-sm font-size-sm mb-3">
              <thead className="thead-light">
                <tr>
                  <th>Khoản mục</th>
                  <th className="text-right">Số tiền</th>
                  <th>Ghi chú</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Tiền cọc xe</td>
                  <td className="text-right font-weight-bold">{formatMoney(order.deposit_amount)}</td>
                  <td>Hoàn trả khi kết thúc hợp đồng theo thỏa thuận</td>
                </tr>
                <tr>
                  <td>Tổng tiền thuê</td>
                  <td className="text-right font-weight-bold text-primary">{formatMoney(order.total_amount)}</td>
                  <td>Đã bao gồm thuế và phí dịch vụ</td>
                </tr>
              </tbody>
            </table>

            {/* Điều khoản & Cam kết */}
            <div className="border p-2 rounded font-size-xs mb-4 text-muted">
              <strong>ĐIỀU KHOẢN CHUNG:</strong>
              <ol className="mb-0 pl-3">
                <li>Bên B cam kết tuân thủ luật an toàn giao thông đường bộ Việt Nam.</li>
                <li>Bên B có trách nhiệm bảo quản tài sản xe, nón bảo hiểm và giấy tờ liên quan trong suốt thời gian thuê.</li>
                <li>Trường hợp trả xe muộn quá thời gian quy định sẽ tính phụ phí phạt muộn theo biểu phí niêm yết của HIMOTO.</li>
              </ol>
            </div>

            {/* Chữ ký */}
            <div className="row text-center pt-2">
              <div className="col-6">
                <div className="font-weight-bold mb-5">ĐẠI DIỆN BÊN A</div>
                <div className="text-muted font-size-xs">(Ký và ghi rõ họ tên)</div>
              </div>
              <div className="col-6">
                <div className="font-weight-bold mb-5">ĐẠI DIỆN BÊN B</div>
                <div className="text-muted font-size-xs">(Ký và ghi rõ họ tên)</div>
              </div>
            </div>
          </div>

          <div className="modal-footer d-print-none">
            <button type="button" className="btn btn-secondary" onClick={onClose}>
              Đóng
            </button>
            <button type="button" className="btn btn-primary" onClick={handlePrint}>
              <i className="fas fa-print mr-1" /> In bản in
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
