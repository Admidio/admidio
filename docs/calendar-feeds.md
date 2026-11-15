# Token based calendar feeds

Admidio can provide protected iCal feeds for events. The public
`mode=subscribe` export is unchanged. Protected feeds use a new
`mode=feed` endpoint and HTTP Basic authentication:

- **Username:** the Admidio login name
- **Password:** a feed token generated in the user profile
- **URL:** shown next to each token in the profile

The token is independent of the Admidio password. Two-factor authentication,
password changes and password resets therefore do not affect calendar clients.

## Creating a feed

1. Open your profile and choose **Calendar feeds**.
2. Enter a name, optionally choose one calendar and optionally set an expiry date.
3. Create the feed.
4. Copy the token, the feed URL and the username. The token is shown only once.
5. Revoke a token at any time in the same dialog.

Several tokens per user and organization are supported, for example one for
Thunderbird and one for ICSx5.

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
