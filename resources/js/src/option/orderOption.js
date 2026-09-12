export const ORDER_STATUS = [
    {value: "pending", label: "Đang chờ duyệt"},
    {value: "renting", label: "Đang thuê"},
    {value: "wait_payment", label: "Chờ thanh toán"},
    {value: "completed", label: "Hoàn thành"},
    {value: "canceled", label: "Hủy"},
    {value: "bad_debt", label: "Nợ xấu"},
    {value: "deposit_contract", label: "Đặt cọc"},
];
export const STATUS_COMPLETED = "completed";

export const ORDER_STATUS_DEFINE = {
    pending: "Đang chờ duyệt",
    renting: "Đang thuê",
    wait_payment: "Chờ thanh toán",
    completed: "Hoàn thành",
    canceled: "Hủy",
    wfpay: "wfpay",
    bad_debt: "Nợ xấu",
    deposit_contract: "Đặt cọc",
};
export const ORDER_STATUS_DEFINE_CSS = {
    pending: "badge badge-secondary",
    renting: "badge badge-primary",
    wait_payment: "badge badge-warning",
    completed: "badge badge-success",
    canceled: "badge badge-danger",
    wfpay: "badge badge-danger",
    bad_debt: "badge badge-danger",
	deposit_contract: "badge badge-warning",
};

export const THU = "in";
export const CHI = "out";
export const GIA_HAN_THEM = "addon";

export const HOAN_THANH = "completed";
export const TRANSACTION_TYPE = {
    in: "Thu",
    out: "Chi",
    addon: "Gia hạn thêm"
};

export const ORDER_OUTDATE_FILTERS = [
	{
		label: "Quá hạn",
		value: true,
	},
	{
		label: "Quá hạn trên 3 ngày",
		value: "expired-gt-3days",
	},
	{
		label: "Quá hạn trên 10 ngày",
		value: "expired-gt-10days",
	},
];

