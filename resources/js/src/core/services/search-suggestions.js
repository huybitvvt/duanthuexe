export function unwrapSuggestions(payload) {
    let value = payload;
    for (let depth = 0; depth < 5; depth++) {
        if (Array.isArray(value)) return value;
        if (!value || typeof value !== "object") return [];
        value = value.data || value.items || value.vehicles;
    }
    return [];
}

const normalize = value => String(value).normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/đ/g, "d").replace(/Đ/g, "D").toLowerCase();

function valuesAt(value, parts) {
    if (Array.isArray(value)) return value.flatMap(item => valuesAt(item, parts));
    if (value == null) return [];
    if (!parts.length) return [value];
    return valuesAt(value[parts[0]], parts.slice(1));
}

export function buildSuggestions(payload, fields, keyword) {
    const query = normalize(keyword.trim());
    if (!query) return [];
    const found = new Set();
    const result = [];
    for (const row of unwrapSuggestions(payload)) {
        for (const field of fields.split(",")) {
            for (const raw of valuesAt(row, field.trim().split("."))) {
                if (typeof raw !== "string" && typeof raw !== "number") continue;
                const value = String(raw).trim();
                if (!value || !normalize(value).includes(query) || found.has(value)) continue;
                found.add(value);
                result.push({ value });
                if (result.length === 10) return result;
            }
        }
    }
    return result;
}
