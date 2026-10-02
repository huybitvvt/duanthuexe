'use client';

import React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';

export interface NavItem {
  to: string;
  label: string;
  icon: string;
  badge?: string;
}

export interface NavGroup {
  title: string;
  items: NavItem[];
}

export const ADMIN_NAV_GROUPS: NavGroup[] = [
  {
    title: 'Tổng quan',
    items: [
      { to: '/dashboard', label: 'Dashboard', icon: 'fas fa-chart-line' },
      { to: '/car-rental', label: 'Đơn thuê xe', icon: 'fas fa-file-contract', badge: 'rentals' },
      { to: '/lease-to-own', label: 'Thuê sở hữu', icon: 'fas fa-key' },
      { to: '/warehouses', label: 'Kho xe & Điều chuyển', icon: 'fas fa-warehouse' },
      { to: '/customer-reminders', label: 'Nhắc nợ khách', icon: 'fas fa-bell' },
      { to: '/leads', label: 'Nguồn Lead', icon: 'fas fa-bullseye' },
    ],
  },
  {
    title: 'Danh mục & Nghiệp vụ',
    items: [
      { to: '/vehicles', label: 'Xe máy', icon: 'fas fa-motorcycle' },
      { to: '/customers', label: 'Khách hàng', icon: 'fas fa-users' },
      { to: '/pricing', label: 'Bảng giá thuê', icon: 'fas fa-tags' },
      { to: '/stores', label: 'Cơ sở & Cửa hàng', icon: 'fas fa-store' },
    ],
  },
  {
    title: 'Tài chính & Thu chi',
    items: [
      { to: '/transactions', label: 'Lịch sử thu chi', icon: 'fas fa-money-bill-wave' },
      { to: '/receipt', label: 'Phiếu thu chi', icon: 'fas fa-receipt' },
      { to: '/finances/daily-cash-register', label: 'Chốt két hàng ngày', icon: 'fas fa-cash-register' },
      { to: '/banks', label: 'Tài khoản ngân hàng', icon: 'fas fa-university' },
      { to: '/cash', label: 'Quỹ tiền mặt', icon: 'fas fa-wallet' },
      { to: '/accounting', label: 'Kế toán tổng hợp', icon: 'fas fa-calculator' },
    ],
  },
  {
    title: 'Báo cáo & Thống kê',
    items: [
      { to: '/report/detail-report', label: 'Báo cáo tổng quan', icon: 'fas fa-chart-pie' },
      { to: '/report/vehicle-revenue', label: 'Doanh thu theo xe', icon: 'fas fa-coins' },
      { to: '/report/kpi', label: 'KPI & Chiến dịch', icon: 'fas fa-trophy' },
    ],
  },
  {
    title: 'Bảo dưỡng & Vận hành',
    items: [
      { to: '/maintenance-schedule', label: 'Lịch bảo dưỡng', icon: 'fas fa-calendar-check' },
      { to: '/maintenance-log', label: 'Nhật ký bảo dưỡng', icon: 'fas fa-wrench' },
      { to: '/maintenance-rule', label: 'Quy tắc bảo dưỡng', icon: 'fas fa-cogs' },
      { to: '/maintenance-type', label: 'Loại bảo dưỡng', icon: 'fas fa-tools' },
      { to: '/hr/duty-schedule', label: 'Lịch trực & Nhân sự', icon: 'fas fa-user-clock' },
      { to: '/user', label: 'Tài khoản & Phân quyền', icon: 'fas fa-user-shield' },
    ],
  },
];

interface SidebarProps {
  collapsed?: boolean;
  mobileOpen?: boolean;
  onCloseMobile?: () => void;
  activeRentalCount?: number;
  userName?: string;
}

export const Sidebar: React.FC<SidebarProps> = ({
  collapsed = false,
  mobileOpen = false,
  onCloseMobile,
  activeRentalCount = 0,
  userName = 'Quản trị viên',
}) => {
  const pathname = usePathname();

  const isRouteActive = (to: string) => {
    if (to === '/dashboard') return pathname === '/dashboard' || pathname === '/';
    return pathname?.startsWith(to);
  };

  return (
    <div>
      {mobileOpen && (
        <div
          className="sidebar-mobile-backdrop"
          onClick={onCloseMobile}
          aria-hidden="true"
        />
      )}

      <aside
        id="himotoSidebar"
        className={`himoto-sidebar ${collapsed ? 'collapsed' : ''} ${mobileOpen ? 'mobile-open' : ''}`}
        aria-label="Điều hướng chính"
      >
        <div className="sidebar-brand">
          <Link href="/dashboard" className="brand-link" aria-label="Về Tổng quan">
            {collapsed ? (
              <span className="brand-mark" aria-hidden="true">H</span>
            ) : (
              <>
                <img
                  src="/images/branding/logo-himoto-pdf.png"
                  alt="HIMOTO"
                  className="brand-logo-img"
                  onError={(e) => {
                    // Fallback to text if image not ready
                    (e.target as HTMLElement).style.display = 'none';
                  }}
                />
                <span className="brand-subtext">Hệ thống quản lý xe</span>
              </>
            )}
          </Link>

          {mobileOpen && (
            <button
              type="button"
              className="btn-close-sidebar-mobile"
              onClick={onCloseMobile}
              aria-label="Đóng menu"
            >
              Đóng
            </button>
          )}
        </div>

        <nav className="sidebar-nav-container">
          <div className="nav-groups-wrapper">
            {ADMIN_NAV_GROUPS.map((group) => (
              <section key={group.title} className="nav-group" aria-label={group.title}>
                {!collapsed && <div className="nav-group-heading">{group.title}</div>}
                {group.items.map((item) => {
                  const active = isRouteActive(item.to);
                  return (
                    <Link
                      key={item.to}
                      href={item.to}
                      className={`sidebar-nav-item ${active ? 'active' : ''}`}
                      title={collapsed ? item.label : undefined}
                      aria-label={item.label}
                      onClick={onCloseMobile}
                    >
                      <i className={`nav-item-icon ${item.icon}`} aria-hidden="true" />
                      {!collapsed && <span className="nav-item-label">{item.label}</span>}
                      {!collapsed && item.badge === 'rentals' && activeRentalCount > 0 && (
                        <span className="nav-badge-pill">{activeRentalCount}</span>
                      )}
                    </Link>
                  );
                })}
              </section>
            ))}
          </div>
        </nav>

        {!collapsed && (
          <div className="sidebar-operator-footer">
            <div className="operator-badge">
              <div className="op-dot" aria-hidden="true" />
              <div className="op-info">
                <span className="op-name">{userName}</span>
                <span className="op-status">Trực tuyến</span>
              </div>
            </div>
          </div>
        )}
      </aside>
    </div>
  );
};
