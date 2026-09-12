import moment from 'moment';

export function formatPrice(value) {
    if (value == '' || value == null) {
        return '0đ'
    }
    let val = (value / 1).toFixed(0).replace('.', ',')
    return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + 'đ'
}

export function formatDate(value) {
    if (value) {
        return moment(String(value)).format('DD-MM-YYYY')

    }
}


export function formatDateTime(value) {
    if (value) {
        return moment(String(value)).format('DD-MM-YYYY HH:mm:ss')
    }
}


