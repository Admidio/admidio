# Residents Plugin for Admidio

A billing and invoicing plugin for Admidio that helps manage resident invoices, payments, recurring charges, and device registrations.

## Features

- **Invoice Management** - Create, edit, preview, and delete invoices for residents
- **Payment Tracking** - Record and manage payments against invoices
- **Recurring Charges** - Define recurring charge templates for automated billing
- **Device Management** - Track and approve resident device registrations
- **Payment Gateway Integration** - CCAvenue payment gateway support for online payment transactions
- **PDF Generation** - Generate PDF invoices and payment receipts
- **Multi-Database Support** - Works with both MySQL and PostgreSQL databases
- **Role-Based Access Control** - Configurable admin roles for billing management
- **Mobile API** - REST API endpoints for mobile app integration

## Requirements

- Admidio 5.0 or higher
- PHP 7.2 or higher
- MySQL 5.7+ or PostgreSQL 9.5+

## Installation

1. **Download the Plugin**
   - Download the plugin from [GitHub](https://github.com/adhi-software/residents)
   - Unzip the downloaded file into your Admidio plugins folder: `adm_plugins/residents/`

2. **Run Installation**
   - Open your browser and navigate to:
     ```
     http://your-domain/adm_plugins/residents/installation.php
     ```
   - Click the **Install** button to create the required database tables

3. **Access the Plugin**
   - Log in to your Admidio installation as an administrator
   - Navigate to `Plugins` → `Residents` in the menu
   - Configure billing admin roles in the `Preferences` tab

4. **Configure Payment Gateway (Optional)**
   - Go to `Preferences` tab
   - Click **Add Payment Gateway** to configure CCAvenue for online payment transactions
   - Enter your CCAvenue merchant credentials (Merchant ID, Access Code, Working Key)

## Uninstallation

1. Navigate to `Preferences` tab in the Residents plugin
2. Click the **Uninstall Residents** button at the bottom of the page
3. Confirm the uninstallation to remove all plugin tables and data

## Configuration

### Admin Roles
Configure which Admidio roles have billing admin access in the Preferences tab:
- **Billing Admin** - Can manage invoices, payments, and charges
- **Payment Admin** - Can manage payment recordings

### Payment Gateway
For CCAvenue online payment integration, you need:
- Merchant ID
- Access Code
- Working Key (Encryption Key)

### v1.0.0
- Invoice creation and management with line items
- Payment recording with invoice allocation
- Recurring charge definitions
- Device registration and approval workflow
- CCAvenue payment gateway integration
- PDF generation for invoices and receipts
- Role-based access control for billing admins
- Support for MySQL and PostgreSQL databases
- Mobile API endpoints for resident app
- Multi-language support via Admidio localization

## Directory Structure

```
residents/
├── api/                    # REST API endpoints
│   ├── auth/              # Authentication APIs
│   ├── invoice/           # Invoice APIs
│   ├── message/           # Message APIs
│   ├── payment/           # Payment gateway callbacks
│   └── photos/            # Photo album APIs
├── charges/               # Recurring charges management
├── classes/               # PHP classes
├── devices/               # Device management
├── invoices/              # Invoice management
├── languages/             # Localization files
├── payment_gateway/       # Payment gateway integration
├── payments/              # Payment management
├── preferences/           # Plugin settings
├── common_function.php    # Shared utility functions
├── installation.php       # Database installation script
├── residents.php          # Main plugin entry point
└── version.php            # Plugin version
```

---
## REST API Endpoints
 
**Base URL**: `/adm_plugins/residents/api/`  
**Auth**: Send `api_key` as a request header (case-insensitive). Unless noted, all endpoints require it.  
**Format**: Most endpoints return JSON. Download endpoints stream files. CCAvenue endpoints return HTML.
 
### Auth (no API key required)
 
| Method | Endpoint | Description | Params / Body |
| --- | --- | --- | --- |
| GET | auth/org_list.php | List organizations for login | — |
| POST | auth/login.php | Device login, returns API key when approved | Query: `org_id` (optional). Body JSON: `username`, `password`, `device` `{deviceId, platform, brand, model}` |
| POST | auth/register_device.php | Create device approval request (no API key issued) | Body JSON: `username`, `password`, `device` `{deviceId, platform, brand, model}` |
 
### Profile
 
| Method | Endpoint | Description | Params / Body |
| --- | --- | --- | --- |
| GET | profile/profile.php | Current user profile + photo | — |
| POST | profile/upload_photo.php | Upload profile photo | Form-data: `photo` or `userfile[]` |
| DELETE | profile/delete_photo.php | Delete profile photo | — |
 
### About
 
| Method | Endpoint | Description | Params |
| --- | --- | --- | --- |
| GET | about/version.php | Admidio + Residents version info | — |
 
### Announcements
 
| Method | Endpoint | Description | Params |
| --- | --- | --- | --- |
| GET | announcement/list.php | List announcements | `cat_uuid` (optional), `limit` (default 10), `offset` |
 
### Events
 
| Method | Endpoint | Description | Params / Body |
| --- | --- | --- | --- |
| GET | event/filters.php | Calendar filters + default date range | — |
| GET | event/list.php | List events | `cat_uuid` (optional), `start`, `end`, `limit`, `offset` |
| POST | event/participate.php | Join/cancel event participation | JSON body: `dat_uuid`, `mode` (3=join, 4=cancel), `comment` (optional). Multipart supported via `payload`. |
 
### Contacts
 
| Method | Endpoint | Description | Params |
| --- | --- | --- | --- |
| GET | contact/list.php | List contacts with profile data | `limit` (optional), `offset` (optional), `search` (optional) |
| GET | contact/detail.php | Contact detail for a user | `contact_id` (required) |
 
### Invoices
 
| Method | Endpoint | Description | Params |
| --- | --- | --- | --- |
| GET | invoice/filters.php | Filter metadata (admin only for group/user lists) | `group_id` (optional) |
| GET | invoice/list.php | List invoices | `group_id`, `user_id`, `search` (admin only), `only_payable`, `start_date`, `end_date`, `status` (`paid`/`unpaid`), `limit`, `page` |
| GET | invoice/detail.php | Invoice detail | `id` (required) |
| DELETE/POST | invoice/delete.php | Delete invoice (admin only) | `id` (required) |
 
### Payments
 
| Method | Endpoint | Description | Params / Body |
| --- | --- | --- | --- |
| GET | payment/filters.php | Filter metadata | `group_id` (optional) |
| GET | payment/list.php | List payments | `group_id`, `user_id`, `type`, `start_date`, `end_date`, `limit`, `page`, `offset` |
| GET | payment/detail.php | Payment detail | `id` (required) |
| POST | payment/save.php | Create/update payment | JSON body: `rpa_id` (optional), `rpa_usr_id` (optional for admin), `rpa_date`, `rpa_pay_type`, `rpa_pg_pay_method`, `rtr_pg_id`, `rtr_bank_ref_no`, `rpi_inv_ids` (array). Multipart supported via `payload`. |
| DELETE/POST | payment/delete.php | Delete payment (admin only) | `id` (required) |
| GET/POST | payment/validate.php | Validate invoices before payment | `invoice_ids[]` (required) |
| POST | payment/ccavenue_pay.php | Start CCAvenue payment (HTML response) | Form body: `invoice_ids[]` |
| POST | payment/ccavenue_response.php | CCAvenue callback (HTML response) | CCAvenue POST fields |
 
### Messages
 
| Method | Endpoint | Description | Params / Body |
| --- | --- | --- | --- |
| GET | message/list.php | List PM/email threads | `type` (optional: `PM`/`EMAIL`) |
| GET | message/detail.php | Message thread detail | `msg_uuid` (required), `mark_read` (optional, default `true`) |
| POST | message/save.php | Send message or reply | JSON body: `msg_uuid` (optional for reply), `msg_type` (`PM`/`EMAIL`), `msg_subject` (required for new), `msg_body` (required), `recipients` (required for new), `forward_source_uuid` (optional). Multipart supported via `payload` + `attachments[]`. |
| DELETE/POST | message/delete.php | Delete message | `msg_uuid` (query or JSON body) |
| GET | message/common.php | Recipient search + limits | `limits_only`, `scope` (`user`/`role`), `query`, `limit`, `offset`, `membership_mode` (0,1,2) |
| GET | message/attachment.php | Download email attachment | `msa_uuid` (required), `view` (optional) |
 
### Photos
 
| Method | Endpoint | Description | Params / Body |
| --- | --- | --- | --- |
| GET | photos/list.php | List top-level albums | `limit` (optional), `offset` (optional) |
| GET | photos/photos.php | Album detail + images | `album_id` (optional), `meta` (optional), `names` (optional CSV), `limit`/`offset` (optional paging for root album) |
| POST | photos/upload.php | Upload photo (admin only) | Form-data: `album_id` (required), `photo` (file) |
 
### Files (Documents & Files module)
 
| Method | Endpoint | Description | Params |
| --- | --- | --- | --- |
| GET | files/list.php | List folder contents | `folder_uuid` (optional), `limit` (optional), `offset` (optional) |
| GET | files/download.php | Download a file | `file_uuid` (required), `view` (optional) |
 
### Language
 
| Method | Endpoint | Description | Params |
| --- | --- | --- | --- |
| GET | language/translations.php | Language strings for mobile app | `lang` (default `en`), `prefix` (optional), `include_main` (`1`/`0`) |
