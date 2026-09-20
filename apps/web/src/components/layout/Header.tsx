'use client';

import React, { useState } from 'react';
import Link from 'next/link';

interface HeaderProps {
  sidebarCollapsed: boolean;
  onToggleDesktopSidebar: () => void;
  onToggleMobileSidebar: () => void;
  selectedStoreId?: string;
  onStoreChange?: (storeId: string) => void;
  userName?: string;
}

export const Header: React.FC<HeaderProps> = ({
  sidebarCollapsed,
  onToggleDesktopSidebar,
  onToggleMobileSidebar,
  selectedStoreId = 'all',
  onStoreChange,
  userName = 'Quản trị viên',
}) => {
  const [searchQuery, setSearchQuery] = useState('');
  const [userMenuOpen, setUserMenuOpen] = useState(false);

  // Mock initial store list for layout display
  const stores = [
    { id: '1', name: '02 Hàng Bút' },
    { id: '2', name: '66 Nguyễn Hoàng' },
    { id: '3', name: '476 Quang Trung' },
    { id: '4', name: '91 Trần Quốc Hoàn' },
  ];

  return (
    <header className="app-header">
      <div className="header-left">
        {/* Mobile Sidebar Toggle */}
        <button
          type="button"
          className="header-action-btn mobile-menu-toggle"
          id="btnSidebarToggleMobile"
          onClick={onToggleMobileSidebar}
          aria-label="Mở menu"
        >
          <span className="btn-text-label">Menu</span>
        </button>

        {/* Desktop Sidebar Collapse Toggle */}
        <button
          type="button"
          className="header-action-btn desktop-menu-toggle"
          id="btnSidebarToggleDesktop"
          onClick={onToggleDesktopSidebar}
          title={sidebarCollapsed ? 'Mở rộng menu' : 'Thu gọn menu'}
          aria-label={sidebarCollapsed ? 'Mở rộng menu' : 'Thu gọn menu'}
        >
          <svg
            className={`sidebar-toggle-icon ${sidebarCollapsed ? 'is-collapsed' : ''}`}
            viewBox="0 0 24 24"
            aria-hidden="true"
            focusable="false"
            width="20"
            height="20"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
          >
            <path d="M5 4v16" />
            <path d="m15 7-5 5 5 5" />
          </svg>
        </button>

        {/* Store / Branch Selector */}
        <div className="store-selector-wrapper">
          <span className="store-label-text">Chi nhánh:</span>
          <select
            id="globalStoreSelect"
            className="store-select"
            value={selectedStoreId}
            onChange={(e) => onStoreChange && onStoreChange(e.target.value)}
            aria-label="Chọn chi nhánh"
          >
            <option value="all">Toàn hệ thống (Tất cả)</option>
            {stores.map((st) => (
              <option key={st.id} value={st.id}>
                {st.name}
              </option>
            ))}
          </select>
        </div>
      </div>

      {/* Desktop Global Search Bar */}
      <div className="header-search-wrapper">
        <div className="search-input-box">
          <input
            type="text"
            id="globalSearchInput"
            className="global-search-input"
            placeholder="Tìm: xe, khách hàng, hợp đồng..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            autoComplete="off"
            aria-label="Tìm kiếm toàn hệ thống"
          />
          <kbd className="search-kbd-hint">Ctrl K</kbd>
          {searchQuery && (
            <button
              type="button"
              className="search-clear-btn"
              onClick={() => setSearchQuery('')}
              aria-label="Xóa từ khóa"
            >
              Xóa
            </button>
          )}
        </div>
      </div>

      {/* Header Right Actions */}
      <div className="header-right">
        <Link href="/car-rental?action=create" className="btn btn-sm btn-primary header-quick-btn">
          <i className="fas fa-plus mr-1" /> + Tạo đơn thuê
        </Link>

        {/* User Profile */}
        <div className="user-profile-menu-wrapper" style={{ position: 'relative' }}>
          <button
            type="button"
            className="user-profile-trigger-btn"
            onClick={() => setUserMenuOpen(!userMenuOpen)}
            aria-label="Menu tài khoản"
          >
            <span className="user-avatar-circle">{userName.charAt(0).toUpperCase()}</span>
            <span className="user-display-name">{userName}</span>
          </button>

          {userMenuOpen && (
            <div className="user-dropdown-menu">
              <Link href="/user" className="dropdown-item">
                <i className="fas fa-user mr-2" /> Thông tin tài khoản
              </Link>
              <button
                type="button"
                className="dropdown-item text-danger"
                onClick={() => {
                  localStorage.removeItem('token');
                  window.location.href = '/login';
                }}
              >
                <i className="fas fa-sign-out-alt mr-2" /> Đăng xuất
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
};
