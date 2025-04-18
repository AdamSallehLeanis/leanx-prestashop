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
### 3. 📂 Upload to PrestaShop
- Go to your PrestaShop **Back Office**
- Navigate to **Modules > Module Manager**
- Click **Upload a module** in the top right
- Upload the freshly zipped `leanx.zip`
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
- Enable/disable **Sandbox Mode**

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

---

## 🚫 Uninstallation
- Navigate to **Modules > Module Manager**
- Search for **LeanX**
- Click the dropdown and choose **Uninstall**

---

## ❓ Support / Contributions
For issues, contributions, or feature requests, please open an issue on [GitHub](https://github.com/your-org/leanx-prestashop/issues) (replace with your repo).

---

© 2025 LeanX Payment Team