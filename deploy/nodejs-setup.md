# GlobexSky — Node.js Server Setup Guide (Namecheap cPanel)

The real-time server (`nodejs/server.js`) handles Socket.io chat connections and
PeerJS/WebRTC signalling. It must run as a persistent process alongside the PHP application.

---

## Prerequisites

- Namecheap hosting plan with **Node.js App** support (Shared Hosting → cPanel → Setup Node.js App)
- PHP app already deployed at `public_html/` (or a subdirectory)

---

## 1. Set Up the Node.js App in cPanel

1. Log in to cPanel → **Software** → **Setup Node.js App**
2. Click **Create Application**
3. Fill in the form:

| Field | Value |
|---|---|
| Node.js version | 18.x or 20.x (LTS recommended) |
| Application mode | **Production** |
| Application root | `public_html/nodejs` |
| Application URL | `yourdomain.com/nodejs` (or leave blank if using a sub-path) |
| Application startup file | `server.js` |

4. Click **Create**

---

## 2. Set Environment Variables

In the **Setup Node.js App** form, under **Environment Variables**, add:

| Variable | Value | Source |
|---|---|---|
| `NODE_ENV` | `production` | — |
| `PORT` | `3001` | Must match Apache ProxyPass |
| `JWT_SECRET` | *(strong random string)* | `openssl rand -hex 32` |
| `INTERNAL_API_KEY` | *(strong random string)* | `openssl rand -hex 32` |
| `CORS_ORIGIN` | `https://yourdomain.com` | Your live domain |
| `DB_HOST` | `localhost` | Same as PHP .env |
| `DB_NAME` | *(your database name)* | cPanel → MySQL Databases |
| `DB_USER` | *(your database user)* | cPanel → MySQL Databases |
| `DB_PASS` | *(your database password)* | cPanel → MySQL Databases |

> **Security:** `JWT_SECRET` and `INTERNAL_API_KEY` must match the values in your PHP `.env` file.

---

## 3. Install Dependencies

In cPanel → **Setup Node.js App**, click **Run NPM Install** on your application row.

Alternatively, via cPanel → Terminal:

```bash
cd ~/public_html/nodejs
npm install --production
```

---

## 4. Start / Restart the App

- **Start:** cPanel → Setup Node.js App → click ▶ (Start) next to your app
- **Restart:** click 🔄 (Restart) after any code changes
- **Stop:** click ■ (Stop)

The app runs on `http://127.0.0.1:3001` internally.

---

## 5. Apache ProxyPass Configuration

On Namecheap shared hosting you cannot edit the main `httpd.conf`, but you **can** add
ProxyPass rules to `.htaccess`. Add the following block to the project `.htaccess`:

```apache
# Node.js real-time server proxy
<IfModule mod_proxy.c>
    ProxyRequests Off
    ProxyPass /socket.io/ http://127.0.0.1:3001/socket.io/
    ProxyPassReverse /socket.io/ http://127.0.0.1:3001/socket.io/
    ProxyPass /internal/ http://127.0.0.1:3001/internal/
    ProxyPassReverse /internal/ http://127.0.0.1:3001/internal/
</IfModule>
```

> If `mod_proxy` is not available on your plan, contact Namecheap support or use a
> WebSocket-compatible reverse proxy alternative.

---

## 6. Verify the Server is Running

From cPanel → Terminal:

```bash
curl -s http://127.0.0.1:3001/health
```

Expected response: `{"status":"ok"}`

From the browser:
```
https://yourdomain.com/socket.io/?EIO=4&transport=polling
```

Expected: Socket.io handshake response (JSON).

---

## 7. PeerJS Server (WebRTC Signalling)

PeerJS signalling is handled by the same `nodejs/server.js` through Socket.io events.
No separate PeerJS server binary is required.

If you add a dedicated PeerJS server in the future:

```bash
npx peer --port 9000 --path /peerjs
```

Then add to `.htaccess`:
```apache
ProxyPass /peerjs/ http://127.0.0.1:9000/peerjs/
ProxyPassReverse /peerjs/ http://127.0.0.1:9000/peerjs/
```

---

## 8. Troubleshooting

| Problem | Solution |
|---|---|
| App won't start | Check cPanel → Node.js App → Logs for error output |
| `JWT_SECRET` error | Ensure it is set in cPanel env vars AND in PHP `.env` |
| CORS errors in browser | Set `CORS_ORIGIN` to exact `https://yourdomain.com` |
| DB connection fails | Verify DB_HOST=localhost and credentials match cPanel MySQL |
| Socket.io not connecting | Check `.htaccess` ProxyPass rules; confirm mod_proxy is enabled |
