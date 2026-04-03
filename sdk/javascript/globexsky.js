/**
 * GlobexSky JavaScript SDK
 *
 * Usage (Node.js):
 *   const GlobexSky = require('./globexsky');
 *   const gsk = new GlobexSky('gsk_live_xxxxxx');
 *   const products = await gsk.products.list({ category: 'electronics' });
 *   const order = await gsk.orders.create({ items: [...] });
 *
 * Usage (Browser):
 *   <script src="globexsky.js"></script>
 *   const gsk = new GlobexSky('gsk_live_xxxxxx');
 *   const products = await gsk.products.list();
 *
 * Methods mirror API endpoints.
 * Works in Node.js and browser.
 */

(function(root, factory) {
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = factory();
    } else {
        root.GlobexSky = factory();
    }
})(typeof globalThis !== 'undefined' ? globalThis : this, function() {

    class GlobexSkyResource {
        constructor(client, resource) {
            this._client = client;
            this._resource = resource;
        }

        async list(params = {}) {
            return this._client.request('GET', this._resource, 'list', params);
        }

        async detail(id) {
            return this._client.request('GET', this._resource, 'detail', { id });
        }

        async create(data) {
            return this._client.request('POST', this._resource, 'create', data);
        }

        async update(id, data) {
            return this._client.request('PUT', this._resource, 'update', { id, ...data });
        }

        async delete(id) {
            return this._client.request('DELETE', this._resource, 'delete', { id });
        }

        async action(action, params = {}, method = 'GET') {
            return this._client.request(method, this._resource, action, params);
        }
    }

    class GlobexSky {
        /**
         * @param {string} apiKey  Your GlobexSky API key
         * @param {object} options
         * @param {string} options.baseUrl  API base URL
         * @param {number} options.timeout  Request timeout in ms
         */
        constructor(apiKey, options = {}) {
            this._apiKey  = apiKey;
            this._baseUrl = (options.baseUrl || 'https://globexsky.com/api/v1/gateway.php').replace(/\/+$/, '');
            this._timeout = options.timeout || 30000;

            // Resource accessors
            this.products = new GlobexSkyResource(this, 'products');
            this.orders   = new GlobexSkyResource(this, 'orders');
            this.cart     = new GlobexSkyResource(this, 'cart');
            this.users    = new GlobexSkyResource(this, 'users');
            this.reviews  = new GlobexSkyResource(this, 'reviews');
            this.shipping = new GlobexSkyResource(this, 'shipping');
            this.dropship = new GlobexSkyResource(this, 'dropship');
            this.webhooks = new GlobexSkyResource(this, 'webhooks');
        }

        /**
         * Make an API request
         */
        async request(method, resource, action, params = {}) {
            let url = `${this._baseUrl}?resource=${encodeURIComponent(resource)}&action=${encodeURIComponent(action)}`;

            const headers = {
                'X-API-Key': this._apiKey,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            };

            const options = {
                method: method.toUpperCase(),
                headers
            };

            if (['GET', 'DELETE'].includes(method.toUpperCase())) {
                if (Object.keys(params).length > 0) {
                    const qs = new URLSearchParams(params).toString();
                    url += '&' + qs;
                }
            } else {
                options.body = JSON.stringify(params);
            }

            // Support both browser fetch and Node.js
            const fetchFn = typeof fetch !== 'undefined' ? fetch : require('node-fetch');

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), this._timeout);
            options.signal = controller.signal;

            try {
                const response = await fetchFn(url, options);
                clearTimeout(timeoutId);

                const data = await response.json();

                if (!response.ok) {
                    const errorMsg = data.errors?.[0]?.message || `HTTP ${response.status}`;
                    const err = new Error(errorMsg);
                    err.status = response.status;
                    err.response = data;
                    throw err;
                }

                return data;
            } catch (err) {
                clearTimeout(timeoutId);
                if (err.name === 'AbortError') {
                    throw new Error('Request timed out');
                }
                throw err;
            }
        }
    }

    return GlobexSky;
});
