import Vue from "vue";
import axios from "axios";
import VueAxios from "vue-axios";
import JwtService from "@/core/services/jwt.service";

const compiledApiUrl = (process.env.MIX_API_URL || "").replace(/\/$/, "");

function resolveApiUrl() {
    if (!compiledApiUrl || typeof window === "undefined") {
        return compiledApiUrl;
    }

    try {
        const configured = new URL(compiledApiUrl);
        const localHosts = ["localhost", "127.0.0.1", "::1"];
        const configuredIsLocal = localHosts.includes(configured.hostname);
        const browserIsLocal = localHosts.includes(window.location.hostname);

        // A production bundle is often reused by the local launcher on a
        // different port. Keep local API calls same-origin so `-Port 8091`
        // does not silently keep calling a stale `localhost:8000` endpoint.
        if (configuredIsLocal && browserIsLocal) {
            return window.location.origin;
        }
    } catch (error) {
        // Axios can still resolve relative base URLs; leave those untouched.
    }

    return compiledApiUrl;
}

const configuredApiUrl = resolveApiUrl();

export const apiUrl = path => `${configuredApiUrl}${path}`;

function formatApiError(error, prefix = "[KT]") {
    const err = new Error(`${prefix} ApiService ${error}`);
    if (error && typeof error === "object") {
        err.response = error.response;
        err.status = error.response ? error.response.status : error.status;
        err.statusCode = err.status;
        err.data = error.response ? error.response.data : error.data;
    }
    return err;
}

/**
 * Service to call HTTP request via Axios
 */
const ApiService = {
    init() {
        Vue.use(VueAxios, axios);
        if (configuredApiUrl) {
            Vue.axios.defaults.baseURL = configuredApiUrl;
        }
    },

    setHeader() {
        const token = JwtService.getToken();
        if (token) {
            Vue.axios.defaults.headers.common[
                "Authorization"
            ] = `Bearer ${token}`;
        } else {
            delete Vue.axios.defaults.headers.common["Authorization"];
        }
    },

    query(resource, params) {
        return Vue.axios.get(resource, {
            params: params
        }).catch(error => {
            throw formatApiError(error, "[KT]");
        });
    },
    download(resource, params) {
        return Vue.axios.get(resource, {
            params: params,
            responseType: 'blob'  
        }).catch(error => {
            throw formatApiError(error, "[KT]");
        });
    },
    /**
     * Send the GET HTTP request
     * @param resource
     * @param slug
     * @returns {*}
     */
    get(resource, slug) {
        let url = null;
        if (slug) {
            url = resource + '/' + slug;
        } else {
            url = resource;
        }
        return Vue.axios.get(url).catch(error => {
            throw formatApiError(error, "[KT]");
        });
    },

    /**
     * Set the POST HTTP request
     * @param resource
     * @param params
     * @returns {*}
     */
    post(resource, params) {
        return Vue.axios.post(`${resource}`, params);
    },

    /**
     * Send the UPDATE HTTP request
     * @param resource
     * @param slug
     * @param params
     * @returns {IDBRequest<IDBValidKey> | Promise<void>}
     */
    update(resource, slug, params) {
        return Vue.axios.put(`${resource}/${slug}`, params);
    },

    /**
     * Send the PUT HTTP request
     * @param resource
     * @param params
     * @returns {IDBRequest<IDBValidKey> | Promise<void>}
     */
    put(resource, params) {
        return Vue.axios.put(`${resource}`, params);
    },

    /**
     * Send the DELETE HTTP request
     * @param resource
     * @returns {*}
     */
    delete(resource) {
        return Vue.axios.delete(resource).catch(error => {
            // console.log(error);
            throw formatApiError(error, "[RWV]");
        });
    }
};

export default ApiService;
