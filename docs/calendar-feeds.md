# Token based calendar feeds

Admidio can provide protected iCal feeds for events. The public
`mode=subscribe` export is unchanged. Protected feeds use a new
`mode=feed` endpoint and HTTP Basic authentication:

- **Username:** the Admidio login name
- **Password:** a feed token generated in the user profile
- **URL:** shown next to each token in the profile

The token is independent of the Admidio password. Two-factor authentication,
password changes and password resets therefore do not affect calendar clients.

A German translation is available in [calendar-feeds.de.md](calendar-feeds.de.md).

## Creating a feed

1. Open your profile and choose **Calendar feeds**.
2. Enter a name, optionally choose one calendar and optionally set an expiry date.
3. Create the feed.
4. Copy the token, the feed URL and the username. The token is shown only once.
5. Revoke a token at any time in the same dialog.

The user who creates the feed must have a login name and be a valid member of at
least one role in the organization. The feed uses the event visibility rules of
that user.

Several tokens per user and organization are supported, for example one for
Thunderbird, one for ICSx5 and one for Apple Calendar.

## Thunderbird

1. Open the calendar view and choose **New Calendar > On the Network**.
2. Enter the feed URL from the profile.
3. Choose the username shown in the profile and paste the token as the password.
4. Save the calendar. Thunderbird will refresh it according to its normal
   calendar refresh settings.

## ICSx5 (Android)

1. Add a new calendar in ICSx5 and paste the feed URL.
2. Enter the Admidio login name as the username.
3. Enter the token as the password.
4. Subscribe to the calendar.

## Apple Calendar (iOS/iPadOS)

Choose **Calendar Subscription** (some versions label it **Add Subscribed
Calendar**), not **CalDAV**. CalDAV is meant for two-way calendar accounts and
does not use the feed URL.

1. Open **Settings > Calendar > Accounts > Add Account > Other**.
   On newer iOS versions the path is
   **Settings > Apps > Calendar > Calendar Accounts > Add Account > Other**.
2. Choose **Calendar Subscription**.
3. Enter the feed URL from the profile and tap **Next**.
4. When iOS asks for credentials, enter the Admidio login name as the username
   and the feed token as the password.
5. Save the calendar.

Alternatively, use the Calendar app: **Calendars > Add Calendar >
Add Subscription Calendar**.

If iOS asks for a CalDAV server, username, password and description, the wrong
option was selected. Go back and choose **Calendar Subscription** instead.

The subscription is read-only and refreshed by iOS on its own schedule. If iOS
refuses the URL, first open it in Safari and check that it returns an iCalendar
file.

## Server configuration for HTTP Basic authentication

Calendar clients send the credentials in the `Authorization` header. Some server
setups remove this header before PHP receives it. In that case the feed answers
with HTTP 401 even though the username and token are correct.

The TLS certificate must be valid for the exact host name used in the feed URL.
iOS and other clients reject self-signed certificates or certificates issued for
a different host name.

Apache with PHP-FPM usually needs:

```apache
CGIPassAuth On
```

This can be set in the virtual host or in an `.htaccess` file if overrides are
allowed. If `CGIPassAuth` is not available, these Apache alternatives can help:

```apache
<IfModule mod_setenvif.c>
    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
</IfModule>
```

```apache
RewriteEngine On
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

When nginx works as a reverse proxy, it must forward the header:

```nginx
proxy_set_header Authorization $http_authorization;
```

For nginx with FastCGI:

```nginx
fastcgi_param HTTP_AUTHORIZATION $http_authorization;
```

The `Authorization` header contains the token and must never be written to
access logs or application logs.

## Troubleshooting

- **HTTP 401:** Missing or wrong username or token, the user is not a member of
  the organization, or PHP does not receive the `Authorization` header.
- **HTTP 403:** The feed is requested over plain HTTP, the events module is
  disabled, or the iCal export is disabled.
- **Empty calendar:** The user cannot see any matching events, the selected
  calendar has no events, or the token is bound to a calendar without events.
- **Token lost:** The token is shown only once and cannot be recovered. Revoke
  the old feed and create a new one.

## Security notes for administrators

- Protected feeds require HTTPS. Plain HTTP requests are rejected.
- Only a SHA-256 hash of the token is stored. The plain token is shown once.
- Tokens are bound to one organization and one user.
- A token stops working when it is revoked, when the user is deactivated, when
  the user is no longer a member of the organization, or when the expiry date
  is reached.
- The feed uses the normal event visibility rules of the user. It never exposes
  calendars or events the user cannot see.
- The last usage timestamp is updated at most once per hour.

## Delimitation from `mode=subscribe`

`mode=subscribe` remains the public export controlled by the normal module and
iCal preferences. `mode=feed` is a separate, token protected endpoint for
calendar clients and never redirects to the HTML login.
