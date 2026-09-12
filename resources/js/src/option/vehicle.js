export const XE_THUE = 0;
export const XE_BAN = 1;
export const type_define = {
    xeso: "Xe số",
    xega: "Xe ga",
    xecon: "Xe côn",
    xesh: "Xe SH",
};

export const TYPE_VEHICLE = [
    { label: "Xe số", value: "xeso" },
    { label: "Xe ga", value: "xega" },
    { label: "Xe côn", value: "xecon" },
    { label: "Xe SH", value: "xesh" },
    { label: "Xe điện", value: "xe_dien" },
];

export const TYPE_PRICING_HIRE = [
    { label: "Thuê theo ngày", value: "day" },
    { label: "Thuê trọn gói", value: "total" },
];

export const brands = [
    {
        id: "honda",
        name: "Honda",
    },
    {
        id: "yamaha",
        name: "Yamaha",
    },
    {
        id: "piaggio",
        name: "Piaggio",
    },
    {
        id: "sym",
        name: "SYM",
    },
    {
        id: "suzuki",
        name: "Suzuki",
    },
    {
        id: "harley-davidson",
        name: "Harley Davidson",
    },
    {
        id: "ducati",
        name: "Ducati",
    },
    {
        id: "triumph",
        name: "Triumph",
    },
    {
        id: "bmw",
        name: "BMW",
    },
    {
        id: "vinfast",
        name: "Vinfast",
    },
];

export const status = [
    {
        id: "pending",
        name: "Chờ duyệt",
    },
    {
        id: "ready",
        name: "Sẵn sàng",
    },
    {
        id: "using",
        name: "Đang sử dụng",
    },
    {
        id: "repairing",
        name: "Đang sửa",
    },
    {
        id: "sold",
        name: "Đã bán",
    },
    {
        id: "broken",
        name: "Đã hỏng",
    },
    {
        id: "bad_debt",
        name: "Nợ xấu",
    },
];
export const status_define = {
    pending: "Chờ duyệt",
    ready: "Sẵn sàng",
    using: "Đang sử dụng",
    repairing: "Đang sửa",
    sold: "Đã bán",
    broken: "Đã hỏng",
    bad_debt: "Nợ xấu"
};

export const status_define_css = {
    pending: "badge badge-secondary",
    ready: "badge badge-primary",
    using: "badge badge-success",
    completed: "badge badge-success",
    repairing: "badge badge-warning",
    sold: "badge badge-danger",
    broken: "badge badge-danger",
    bad_debt: "badge badge-danger",
    deleted: "badge badge-danger"
};
export const types = [
    {
        id: "xeso",
        name: "Xe số",
    },
    {
        id: "xega",
        name: "Xe ga",
    },
    {
        id: "xecon",
        name: "Xe côn",
    },
    {
        id: "xesh",
        name: "Xe SH",
    },
    {
        id: "xe_dien",
        name: "Xe điện",
    },
];

export const typeOfService = {
    0: "Thuê",
    1: "Bán",
};

export const typeOfServices = [
    {
        id: 0,
        name: "Thuê",
    },
    {
        id: 1,
        name: "Bán",
    },
];
export const typeOfServiceDefineCss = {
    0: "badge badge-primary",
    1: "badge badge-success",
};
export const sortBys = [ 
     
    {
        id: "revenue",
        name: "Doanh thu",
    },
    {
        id: "count_order",
        name: "Tổng order",
    },
];
export const sortTypes = [ 
    {
        id: "desc",
        name: "Giảm dần",
    },
    {
        id: "asc",
        name: "Tăng dần",
    },
];
 
 