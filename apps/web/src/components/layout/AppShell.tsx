'use client';

import React, { useState } from 'react';
import { Sidebar } from './Sidebar';
import { Header } from './Header';

interface AppShellProps {
  children: React.ReactNode;
}

export const AppShell: React.FC<AppShellProps> = ({ children }) => {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
  const [selectedStoreId, setSelectedStoreId] = useState('all');

  return (
    <div className={`app-container ${sidebarCollapsed ? 'sidebar-collapsed' : ''}`}>
      <Sidebar
        collapsed={sidebarCollapsed}
        mobileOpen={mobileSidebarOpen}
        onCloseMobile={() => setMobileSidebarOpen(false)}
        userName="Quản trị viên"
      />

      <div className="app-main-wrapper">
        <Header
          sidebarCollapsed={sidebarCollapsed}
          onToggleDesktopSidebar={() => setSidebarCollapsed(!sidebarCollapsed)}
          onToggleMobileSidebar={() => setMobileSidebarOpen(!mobileSidebarOpen)}
          selectedStoreId={selectedStoreId}
          onStoreChange={setSelectedStoreId}
          userName="Quản trị viên"
        />

        <main className="app-content-body">
          {children}
        </main>
      </div>
    </div>
  );
};

export default AppShell;

