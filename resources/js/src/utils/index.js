const isRole = (roles, id, pattern = "quan-tri-vien") => {
    if (!roles || !Array.isArray(roles) || roles.length === 0) return false;

    const patterns = pattern.split("|");

    for (const pattern of patterns) {
        const pt = new RegExp(pattern, "gi");
        const matched = roles.find((r) => r?.slug?.match(pt));
        if (matched && matched.id === id) {
            return true;
        }
    }

    return false;
};

const getTextShort = (str, len = 20) => {
    if (str && typeof str === "string") {
        const reg = new RegExp(`(.{${len}})..+`);
        return str.replace(reg, "$1…");
    }

    return str;
};

export { isRole, getTextShort };
