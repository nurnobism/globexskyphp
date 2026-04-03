"""
GlobexSky Python SDK

Usage:
    from globexsky import GlobexSky

    client = GlobexSky('gsk_live_xxxxxx')
    products = client.products.list(category='electronics')
    order = client.orders.create(items=[...])

Methods mirror API endpoints.
"""

import json
import urllib.request
import urllib.parse
import urllib.error


class GlobexSkyException(Exception):
    """Exception raised for API errors."""

    def __init__(self, message, status_code=None, response=None):
        super().__init__(message)
        self.status_code = status_code
        self.response = response


class GlobexSkyResource:
    """Represents an API resource (products, orders, etc.)."""

    def __init__(self, client, resource):
        self._client = client
        self._resource = resource

    def list(self, **params):
        return self._client.request('GET', self._resource, 'list', params)

    def detail(self, id):
        return self._client.request('GET', self._resource, 'detail', {'id': id})

    def create(self, **data):
        return self._client.request('POST', self._resource, 'create', data)

    def update(self, id, **data):
        data['id'] = id
        return self._client.request('PUT', self._resource, 'update', data)

    def delete(self, id):
        return self._client.request('DELETE', self._resource, 'delete', {'id': id})

    def action(self, action, method='GET', **params):
        return self._client.request(method, self._resource, action, params)


class GlobexSky:
    """
    GlobexSky API Client.

    Args:
        api_key: Your GlobexSky API key (gsk_live_xxx or gsk_test_xxx).
        base_url: API base URL.
        timeout: Request timeout in seconds.
    """

    def __init__(self, api_key, base_url='https://globexsky.com/api/v1/gateway.php', timeout=30):
        self._api_key = api_key
        self._base_url = base_url.rstrip('/')
        self._timeout = timeout

        # Resource accessors
        self.products = GlobexSkyResource(self, 'products')
        self.orders = GlobexSkyResource(self, 'orders')
        self.cart = GlobexSkyResource(self, 'cart')
        self.users = GlobexSkyResource(self, 'users')
        self.reviews = GlobexSkyResource(self, 'reviews')
        self.shipping = GlobexSkyResource(self, 'shipping')
        self.dropship = GlobexSkyResource(self, 'dropship')
        self.webhooks = GlobexSkyResource(self, 'webhooks')

    def request(self, method, resource, action, params=None):
        """
        Make an API request.

        Args:
            method: HTTP method (GET, POST, PUT, DELETE).
            resource: Resource name (products, orders, etc.).
            action: Action name (list, detail, create, etc.).
            params: Request parameters.

        Returns:
            dict: Decoded JSON response.

        Raises:
            GlobexSkyException: On API errors.
        """
        params = params or {}
        url = f"{self._base_url}?resource={urllib.parse.quote(resource)}&action={urllib.parse.quote(action)}"

        headers = {
            'X-API-Key': self._api_key,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        }

        data = None
        if method.upper() in ('GET', 'DELETE'):
            if params:
                qs = urllib.parse.urlencode(params)
                url += '&' + qs
        else:
            data = json.dumps(params).encode('utf-8')

        req = urllib.request.Request(url, data=data, headers=headers, method=method.upper())

        try:
            with urllib.request.urlopen(req, timeout=self._timeout) as resp:
                body = resp.read().decode('utf-8')
                return json.loads(body)
        except urllib.error.HTTPError as e:
            body = e.read().decode('utf-8')
            try:
                decoded = json.loads(body)
                msg = decoded.get('errors', [{}])[0].get('message', f'HTTP {e.code}')
            except (json.JSONDecodeError, IndexError, KeyError):
                msg = f'HTTP {e.code}: {body[:200]}'
            raise GlobexSkyException(msg, status_code=e.code, response=body)
        except urllib.error.URLError as e:
            raise GlobexSkyException(f'Connection error: {e.reason}')
        except Exception as e:
            raise GlobexSkyException(str(e))
