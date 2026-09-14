import moment from 'moment';

export function formatPrice(value) {
    if (value == '' || value == null) {
        return '0đ'
    }
    let val = (value / 1).toFixed(0).replace('.', ',')
    return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + 'đ'
}

export function formatDate(value) {
    if (!value) return '';
    const str = String(value).trim();
    if (!str) return '';
    const m = moment(str, ['YYYY-MM-DD', 'YYYY-MM-DD HH:mm:ss', 'DD/MM/YYYY', 'DD-MM-YYYY', 'DD/MM/YYYY HH:mm:ss', 'DD/MM/YYYY HH:mm'], true);
    if (m.isValid()) {
        return m.format('DD-MM-YYYY');
    }
    const loose = moment(str);
    return loose.isValid() ? loose.format('DD-MM-YYYY') : str;
}


export function formatDateTime(value) {
    if (!value) return '';
    const str = String(value).trim();
    if (!str) return '';
    const m = moment(str, ['YYYY-MM-DD HH:mm:ss', 'YYYY-MM-DDTHH:mm:ss', 'DD/MM/YYYY HH:mm:ss', 'DD-MM-YYYY HH:mm:ss', 'YYYY-MM-DD', 'DD/MM/YYYY', 'DD/MM/YYYY HH:mm'], true);
    if (m.isValid()) {
        return m.format('DD-MM-YYYY HH:mm:ss');
    }
    const loose = moment(str);
    return loose.isValid() ? loose.format('DD-MM-YYYY HH:mm:ss') : str;
}


