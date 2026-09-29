<?php

namespace App\Support;

/**
 * Permissions approved by the 29/09/2026 position matrix. Roles without a
 * named UAT account can be assigned later without changing this policy.
 */
class UatPermissionMatrix
{
    public const ROLES = [
        'quan-tri-vien' => ['Quản trị viên', ['*']],
        'ban-giam-doc' => ['Ban giám đốc', ['*']],
        'van-hanh' => ['Vận hành', ['*']],
        'quan-ly-cua-hang' => ['Trưởng phòng giao dịch', [
            'vehicle.view_all', 'dashboard.view_store', 'order.count',
            'reminder.view', 'cash_register.view', 'finance.bank.view_store',
            'order.view_store', 'order.create', 'order.update', 'order.handover',
            'order.return', 'order.settle_return', 'order.close_deposit',
            'order.renewal_fee', 'order.cancel_approve', 'order.cancel_settle', 'order.discount_approve',
            'order.report_store', 'cash_register.approve', 'approval.view', 'approval.decide',
        ]],
        'nhan-vien' => ['Nhân viên quầy', [
            'vehicle.view_all', 'dashboard.view_store', 'order.count',
            'reminder.view', 'cash_register.view', 'finance.bank.view_store',
            'order.view_store', 'order.create', 'order.update', 'order.handover',
            'order.return', 'cash_register.submit', 'approval.view',
            'order.cancel_request', 'order.discount_request',
        ]],
        'thue-so-huu-truong-phong' => ['Trưởng phòng thuê sở hữu', [
            'lease.view', 'vehicle.view_all', 'dashboard.view_store',
            'order.count', 'reminder.view', 'cash_register.view',
            'finance.bank.view_store',
            'lease.create_draft', 'lease.approve', 'lease.collect', 'lease.collect_initial',
            'lease.note', 'lease.recovery_approve', 'lease.liquidation_approve',
            'lease.schedule_approve', 'approval.view', 'approval.decide',
            'cash_register.approve',
            'lease.discount_approve', 'file.upload',
        ]],
        'thue-so-huu-hop-dong' => ['Nhân viên hợp đồng thuê sở hữu', [
            'lease.view', 'vehicle.view_lease', 'dashboard.view_store',
            'order.count', 'cash_register.view', 'finance.bank.view_store',
            'lease.create_draft', 'lease.collect_initial', 'customer.create',
            'file.upload', 'lease.schedule_propose', 'approval.view',
        ]],
        'thue-so-huu-thu-hoi-no' => ['Nhân viên thu hồi nợ', [
            'lease.view', 'vehicle.view_lease', 'reminder.view',
            'finance.bank.view_store',
            'lease.note', 'lease.recovery_propose', 'lease.liquidation_propose',
            'approval.view',
        ]],
        'telesale' => ['Telesale', ['vehicle.view_all']],
        'nhan-su' => ['Nhân sự', [
            'hr.view', 'hr.manage_staff', 'hr.manage_attendance',
            'hr.manage_schedule', 'hr.export', 'vehicle.view_all',
        ]],
        'ke-toan' => ['Kế toán', [
            'accounting.view', 'accounting.post', 'accounting.reverse',
            'accounting.close_period', 'accounting.reconcile',
            'accounting.export', 'finance.transaction.view',
            'finance.transaction.manage', 'finance.bank.view_all',
            'finance.cash.view_all', 'cash_register.view', 'cash_register.view_all',
        ]],
    ];

    public const PERMISSIONS = [
        '*' => 'Toàn quyền',
        'vehicle.view_all' => 'Xem xe ở tất cả kho',
        'vehicle.view_lease' => 'Xem xe kho thuê sở hữu',
        'dashboard.view_store' => 'Xem dashboard phòng giao dịch',
        'order.count' => 'Xem số lượng đơn',
        'order.view_store' => 'Xem đơn của phòng',
        'order.create' => 'Tạo đơn thuê xe',
        'order.update' => 'Cập nhật tiếp nhận đơn',
        'order.handover' => 'Bàn giao xe',
        'order.return' => 'Nhận trả xe',
        'order.settle_return' => 'Quyết toán hoàn trả đơn',
        'order.close_deposit' => 'Thanh lý hợp đồng cọc',
        'order.renewal_fee' => 'Ghi thu phí gia hạn',
        'order.cancel_approve' => 'Duyệt hủy đơn',
        'order.cancel_settle' => 'Quyết toán hoàn tiền hủy đơn',
        'order.discount_approve' => 'Duyệt giảm giá ngoại lệ',
        'order.cancel_request' => 'Đề nghị hủy đơn',
        'order.discount_request' => 'Đề nghị giảm giá ngoại lệ',
        'approval.view' => 'Xem đề nghị phê duyệt',
        'approval.decide' => 'Xử lý đề nghị phê duyệt',
        'order.report_store' => 'Xem doanh thu phòng',
        'cash_register.submit' => 'Gửi số kiểm đếm két',
        'cash_register.approve' => 'Duyệt chốt két',
        'cash_register.view' => 'Xem sổ két hàng ngày',
        'cash_register.view_all' => 'Xem sổ két toàn công ty',
        'finance.bank.view_store' => 'Xem tài khoản ngân hàng phòng giao dịch',
        'finance.bank.view_all' => 'Xem tài khoản ngân hàng toàn công ty',
        'finance.cash.view_all' => 'Xem quỹ tiền mặt toàn công ty',
        'finance.transaction.view' => 'Xem thu chi',
        'finance.transaction.manage' => 'Ghi thu chi',
        'lease.create_draft' => 'Tạo dự thảo hợp đồng thuê sở hữu',
        'lease.approve' => 'Duyệt hiệu lực hợp đồng thuê sở hữu',
        'lease.collect_initial' => 'Thu cọc hoặc kỳ đầu thuê sở hữu',
        'lease.recovery_propose' => 'Đề xuất thu hồi xe',
        'lease.recovery_approve' => 'Duyệt thu hồi xe',
        'lease.liquidation_propose' => 'Đề xuất thanh lý xe nợ xấu',
        'lease.liquidation_approve' => 'Duyệt phương án thanh lý xe nợ xấu',
        'lease.schedule_propose' => 'Đề nghị đổi lịch trả góp',
        'lease.schedule_approve' => 'Duyệt đổi lịch trả góp',
        'lease.discount_approve' => 'Duyệt chiết khấu tất toán thuê sở hữu',
    ];
}
