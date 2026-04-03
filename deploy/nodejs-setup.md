# GlobexSky — Node.js Server Setup Guide (Namecheap cPanel)

The real-time server (`nodejs/server.js`) handles Socket.io chat connections and
PeerJS/WebRTC signalling. It must run as a persistent process alongside the PHP application.

---

## Namecheap Shared Hosting Limitations

| Feature | Shared Hosting | VPS/Cloud |
|---|---|---|
| Node.js App support | ✅ via cPanel Setup Node.js App | ✅ Full control |
| Persistent process | ⚠️ Managed by cPanel only | ✅ PM2 / systemd |
| Custom ports | ❌ No (only via ProxyPass) | ✅ Any port |
| WebSocket support | ⚠️ Depends on plan | ✅ Full |
| PM2 process manager | ❌ Not available | ✅ Full |

> **Recommendation:** For a production B2B platform with many concurrent users, use a
> separate VPS (e.g., DigitalOcean Droplet, Linode, AWS EC2) for the Node.js server.
> See **Option B** below.

---

## Option A: Namecheap cPanel Node.js App (Shared Hosting)

### Prerequisites

- Namecheap hosting plan with **Node.js App** support
- PHP app deployed at `~/globexsky.com/`

### 1. Set Up the Node.js App in cPanel

1. Log in to cPanel → **Software** → **Setup Node.js App**
2. Click **Create Application**
3. Fill in the form:

| Field | Value |
|---|---|
| Node.js version | 18.x or 20.x (LTS recommended) |
| Application mode | **Production** |
| Application root | `globexsky.com/nodejs` |
| Application URL | `globexsky.com` (sub-path handled by ProxyPass) |
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

---

## Option B: External VPS with PM2 (Recommended for Production)

If Namecheap shared hosting does not support Node.js apps or WebSockets are unreliable,
deploy the Node.js server on a separate VPS.

### 1. Install Node.js on VPS

```bash
# Ubuntu/Debian
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs
node --version  # Should be 20.x
```

### 2. Install PM2 Process Manager

```bash
sudo npm install -g pm2
```

### 3. Copy nodejs/ to VPS

```bash
# From your local machine:
scp -r nodejs/ user@your-vps-ip:~/globexsky/
scp .env user@your-vps-ip:~/globexsky/
```

### 4. Start with PM2

```bash
cd ~/globexsky/nodejs
npm install --production
pm2 start server.js --name globexsky-realtime
pm2 save
pm2 startup   # Auto-start on VPS reboot
```

### 5. PM2 Management Commands

```bash
pm2 list                    # List all processes
pm2 logs globexsky-realtime # View logs
pm2 restart globexsky-realtime  # Restart
pm2 stop globexsky-realtime     # Stop
pm2 delete globexsky-realtime   # Remove
```

### 6. Configure PHP to Use External VPS Node.js

Update your `.env` on the PHP server:
```env
NODE_SERVER_URL=https://realtime.globexsky.com
CORS_ORIGIN=https://globexsky.com
```

Update `nodejs/.env` on the VPS:
```env
NODE_ENV=production
PORT=3001
JWT_SECRET=<same_as_php_env>
INTERNAL_API_KEY=<same_as_php_env>
CORS_ORIGIN=https://globexsky.com
DB_HOST=localhost
DB_NAME=bidybxoc_globexsky
DB_USER=bidybxoc_globexsky
DB_PASS=<your_db_password>
```

### 7. Nginx Proxy Configuration (VPS)

```nginx
server {
    listen 443 ssl;
    server_name realtime.globexsky.com;

    ssl_certificate /etc/letsencrypt/live/realtime.globexsky.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/realtime.globexsky.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:3001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_cache_bypass $http_upgrade;
    }
}
```

---

## Environment Variables Reference

| Variable | Description | Example |
|---|---|---|
| `NODE_ENV` | Environment mode | `production` |
| `PORT` | Server port | `3001` |
| `JWT_SECRET` | JWT signing secret (must match PHP .env) | `openssl rand -hex 32` |
| `INTERNAL_API_KEY` | PHP→Node internal API key (must match PHP .env) | `openssl rand -hex 32` |
| `CORS_ORIGIN` | Allowed CORS origin | `https://globexsky.com` |
| `DB_HOST` | MySQL host | `localhost` |
| `DB_NAME` | MySQL database name | `bidybxoc_globexsky` |
| `DB_USER` | MySQL username | `bidybxoc_globexsky` |
| `DB_PASS` | MySQL password | *(your password)* |
