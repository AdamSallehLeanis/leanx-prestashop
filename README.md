# LeanX Payment Gateway for PrestaShop

This module allows merchants to accept payments via **LeanX** in their PrestaShop store. It integrates seamlessly into the checkout process and handles redirect flows, callback verification, and order status updates.

---

## 📁 Features
- Redirect-based payment via LeanX portal
- Configuration of API credentials in admin
- Manual API check after return for added validation
- Secure callback handling (IPN) from LeanX
- Order cloning for failed checkouts
- Out-of-stock item restoration alerts
- Cron job support to auto-cancel unpaid orders

---

## 📆 Compatibility
- PrestaShop: **8.2.0** and above
- PHP: **8.1** (recommended)

---

## 📦 Installation Instructions

### 1. ✉️ Download the Ready-to-Use ZIP

To install the module easily:

- Go to the [Releases Page](https://github.com/AdamSallehLeanis/leanx-prestashop/releases)
- Download the pre-packaged `leanx.zip` file
- **Do not extract it** — you'll upload this ZIP directly to PrestaShop

If you're a developer cloning the repo manually, make sure your ZIP archive structure looks like:

```
leanx.zip
 └── leanx/
     ├── leanx.php
     ├── views/
     ├── controllers/
     └── ...
```

### 2. 📂 Upload to PrestaShop
- Go to your PrestaShop **Back Office**
- Navigate to **Modules > Module Manager**
- Click **Upload a module** in the top right
- Upload the ZIP file `leanx.zip`
- Click **Install** when prompted

---

## ⚙️ Configuration
After installation:

1. Go to **Modules > Module Manager**
2. Find **LeanX** in the list
3. Click **Configure**

### You will need:
- **Auth Token** (from your LeanX merchant dashboard)
- **Hash Key**
- **Collection UUID**
- **Bill Invoice ID Prefix** (optional)
- **Order Timeout (minutes)** (optional)

---

## ⏰ Cron Integration for Timeout Handler

To automatically cancel **unpaid orders** after a set number of minutes (default is 30), you need to schedule a CRON job on your server.

> **Note:** Setting up a CRON job typically requires access to the operating system via SSH.  
> If you're on shared hosting or do not have terminal access, you may need to ask your **server administrator** or **PrestaShop host provider** to configure this for you.

### ✅ What it does:
- Periodically checks for orders still in **“Awaiting payment on LeanX”**
- Calls the LeanX API to re-check the payment status
- Cancels the order if it’s still unpaid or expired

---

### ⚙️ Step-by-Step Setup (Linux Server)

> Requires terminal access (SSH) to your hosting/server

#### 1. Connect to your server

```bash
ssh your-user@your-server-ip
```

#### 2. Open your user crontab

```bash
crontab -e
```

#### 3. Add the following CRON job at the bottom of the file:

```bash
*/15 * * * * /usr/bin/php /var/www/prestashop/modules/leanx/cron/timeout_handler.php > /dev/null 2>&1
```

This runs the script every 15 minutes. You can adjust the interval if needed.

> ⚠️ Make sure the path to PHP (`/usr/bin/php`) and your PrestaShop folder (`/var/www/prestashop/...`) are correct for your environment.

---

### 🧪 Test It Manually (Optional)

To manually test the timeout script:

```bash
php /var/www/prestashop/modules/leanx/cron/timeout_handler.php
```

You can check its log file here:

```bash
/var/www/prestashop/var/logs/leanx_timeout.log
```

---

### 📝 Notes
- The CRON job only affects orders in the **"Awaiting payment on LeanX"** state.
- You can configure the **timeout duration** (in minutes) in the module's configuration page.
- Orders with a successful payment won’t be affected.

---

## 🌟 Optional Features

- Failed orders show a retry button that restores the cart
- If any items are out of stock during retry, the customer is informed
- Out-of-stock alerts are shown as in-cart notifications

---

## ✅ Testing Checklist
- [ ] Set up sandbox credentials
- [ ] Enable sandbox mode
- [ ] Place a test order
- [ ] Verify redirect to LeanX and back
- [ ] Ensure callbacks update order status
- [ ] Ensure timeout handler cancels unpaid orders

---

## 🚫 Uninstallation
- Navigate to **Modules > Module Manager**
- Search for **LeanX**
- Click the dropdown and choose **Uninstall**

---

## ❓ Support / Contributions
For issues, contributions, or feature requests, please open an issue on [GitHub](https://github.com/AdamSallehLeanis/leanx-prestashop/issues).

---

© 2025 LeanX