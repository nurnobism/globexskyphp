/**
 * Interactive API Documentation
 *
 * - "Try It" form builder from endpoint spec
 * - Execute request and display response
 * - Code example tabs (cURL, PHP, Python, JS, Ruby)
 * - Copy code button
 */

(function() {
    'use strict';

    /**
     * Execute a "Try It" API call
     */
    window.tryApiCall = function(resource, action, button) {
        const card = button.closest('.card-footer');
        if (!card) return;

        const apiKeyInput = card.querySelector('.try-api-key');
        const responseDiv = card.querySelector('.try-response');
        const responsePre = responseDiv ? responseDiv.querySelector('pre') : null;

        if (!responseDiv || !responsePre) return;

        const apiKey = apiKeyInput ? apiKeyInput.value.trim() : '';
        const baseUrl = window.location.origin;
        const url = `${baseUrl}/api/v1/gateway.php?resource=${encodeURIComponent(resource)}&action=${encodeURIComponent(action)}`;

        button.disabled = true;
        button.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending...';
        responseDiv.classList.remove('d-none');
        responsePre.textContent = 'Loading...';

        const headers = {
            'Accept': 'application/json'
        };
        if (apiKey) {
            headers['X-API-Key'] = apiKey;
        }

        const startTime = performance.now();

        fetch(url, {
            method: 'GET',
            headers: headers
        })
        .then(response => {
            const elapsed = Math.round(performance.now() - startTime);
            return response.text().then(text => ({
                status: response.status,
                statusText: response.statusText,
                body: text,
                elapsed: elapsed
            }));
        })
        .then(result => {
            let formatted;
            try {
                const json = JSON.parse(result.body);
                formatted = JSON.stringify(json, null, 2);
            } catch (e) {
                formatted = result.body;
            }

            responsePre.textContent =
                `HTTP ${result.status} ${result.statusText} (${result.elapsed}ms)\n\n${formatted}`;

            // Color code based on status
            if (result.status >= 200 && result.status < 300) {
                responsePre.className = 'bg-dark text-success p-2 rounded small';
            } else if (result.status >= 400 && result.status < 500) {
                responsePre.className = 'bg-dark text-warning p-2 rounded small';
            } else {
                responsePre.className = 'bg-dark text-danger p-2 rounded small';
            }
        })
        .catch(err => {
            responsePre.textContent = 'Error: ' + err.message;
            responsePre.className = 'bg-dark text-danger p-2 rounded small';
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-send"></i> Send Request';
        });
    };

    /**
     * Copy text to clipboard
     */
    window.copyCode = function(button) {
        const pre = button.closest('.position-relative')?.querySelector('pre');
        if (pre) {
            navigator.clipboard.writeText(pre.textContent).then(() => {
                const original = button.textContent;
                button.textContent = 'Copied!';
                setTimeout(() => { button.textContent = original; }, 2000);
            });
        }
    };

    /**
     * Generate code examples for an endpoint
     */
    function generateCodeExamples(resource, action, method, baseUrl) {
        const url = `${baseUrl}/api/v1/gateway.php?resource=${resource}&action=${action}`;

        return {
            curl: `curl "${url}" \\\n  -H "X-API-Key: YOUR_API_KEY"`,
            php: `<?php\n$ch = curl_init('${url}');\ncurl_setopt_array($ch, [\n    CURLOPT_RETURNTRANSFER => true,\n    CURLOPT_HTTPHEADER => ['X-API-Key: YOUR_API_KEY'],\n]);\n$response = json_decode(curl_exec($ch), true);\ncurl_close($ch);\nprint_r($response);`,
            python: `import requests\n\nresponse = requests.get(\n    '${url}',\n    headers={'X-API-Key': 'YOUR_API_KEY'}\n)\ndata = response.json()\nprint(data)`,
            javascript: `const response = await fetch('${url}', {\n    headers: { 'X-API-Key': 'YOUR_API_KEY' }\n});\nconst data = await response.json();\nconsole.log(data);`,
            ruby: `require 'net/http'\nrequire 'json'\n\nuri = URI('${url}')\nreq = Net::HTTP::Get.new(uri)\nreq['X-API-Key'] = 'YOUR_API_KEY'\nres = Net::HTTP.start(uri.hostname, uri.port) { |http| http.request(req) }\nputs JSON.parse(res.body)`
        };
    }

    // Export for use in docs page
    window.GlobexSkyApiDocs = {
        generateCodeExamples
    };
})();
