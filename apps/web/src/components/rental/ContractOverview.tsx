import React from 'react';
import { formatMoney } from '@/lib/formatters';

export interface OrderStats {
  total_order: number;
  total_contracts_completed: number;
  total_contracts_renting: number;
  total_out_of_date: number;
}

export interface MoneyStats {
  total_deposit: number;
  total_renew: number;
  total_rental_fees: number;
  total_real_refund: number;
  total_origin_refund: number;
  total_money_early: number;
  total_money_out_date: number;
}

interface ContractOverviewProps {
  orderStats: OrderStats;
  moneyStats: MoneyStats;
}

export const ContractOverview: React.FC<ContractOverviewProps> = ({
  orderStats,
  moneyStats,
}) => {
  const totalIn =
    (moneyStats.total_deposit || 0) +
    (moneyStats.total_renew || 0) +
    (moneyStats.total_rental_fees || 0);

  return (
    <section className="contract-overview mb-4" aria-label="Thống kê hợp đồng">
      <div className="contract-overview-card">
        <h4>Hợp đồng</h4>
        <dl>
          <div>
            <dt>Tổng số hợp đồng</dt>
            <dd>{orderStats.total_order}</dd>
          </div>
          <div>
            <dt>Hoàn thành</dt>
            <dd>{orderStats.total_contracts_completed}</dd>
          </div>
          <div>
            <dt>Đang thuê</dt>
            <dd>{orderStats.total_contracts_renting}</dd>
          </div>
          <div className="text-danger">
            <dt>Quá hạn</dt>
            <dd className="font-weight-bold">{orderStats.total_out_of_date}</dd>
          </div>
        </dl>
      </div>

      <div className="contract-overview-card">
        <h4>Thu thực tế</h4>
        <dl>
          <div>
            <dt>Tổng thu</dt>
            <dd className="text-success font-weight-bold">{formatMoney(totalIn)}</dd>
          </div>
          <div>
            <dt>Thu cọc</dt>
            <dd>{formatMoney(moneyStats.total_deposit)}</dd>
          </div>
          <div>
            <dt>Thu gia hạn</dt>
            <dd>{formatMoney(moneyStats.total_renew)}</dd>
          </div>
          <div>
            <dt>Thu phí thuê</dt>
            <dd>{formatMoney(moneyStats.total_rental_fees)}</dd>
          </div>
        </dl>
      </div>

      <div className="contract-overview-card">
        <h4>Chi & hoàn trả</h4>
        <dl>
          <div>
            <dt>Tổng chi thực tế</dt>
            <dd className="text-danger font-weight-bold">{formatMoney(moneyStats.total_real_refund)}</dd>
          </div>
          <div>
            <dt>Tiền cọc phải trả</dt>
            <dd>{formatMoney(moneyStats.total_origin_refund)}</dd>
          </div>
          <div>
            <dt>Hoàn do trả sớm</dt>
            <dd>{formatMoney(Math.abs(moneyStats.total_money_early))}</dd>
          </div>
          <div>
            <dt>Phạt muộn</dt>
            <dd>{formatMoney(moneyStats.total_money_out_date)}</dd>
          </div>
        </dl>
      </div>
    </section>
  );
};
