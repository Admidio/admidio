# Tokenbasierte Kalender-Feeds

Admidio kann geschützte iCal-Feeds für Veranstaltungen bereitstellen. Der
öffentliche Export über `mode=subscribe` bleibt unverändert. Geschützte Feeds
verwenden den neuen Endpunkt `mode=feed` und HTTP-Basic-Authentifizierung:

- **Benutzername:** der Admidio-Loginname
- **Passwort:** ein im Benutzerprofil erzeugtes Feed-Token
- **URL:** wird im Profil oder in der Veranstaltungsansicht neben jedem Token angezeigt

Das Token ist unabhängig vom Admidio-Passwort. Zwei-Faktor-Authentifizierung,
Passwortänderungen und Passwort-Zurücksetzungen wirken sich daher nicht auf
Kalenderclients aus.

Eine englische Fassung steht in [calendar-feeds.md](calendar-feeds.md) bereit.

## Feed anlegen

1. Eigenes Profil öffnen und **Kalender-Feeds** wählen oder in der Veranstaltungsansicht **Kalender-Feeds** öffnen.
2. Einen Namen eingeben, optional einen Kalender und optional ein Ablaufdatum wählen.
3. Den Feed anlegen.
4. Token, Feed-URL und Benutzername kopieren. Das Token wird nur einmal angezeigt.
5. Ein Token kann jederzeit im selben Dialog widerrufen werden.

Der direkte Knopf **Abonnieren** in der Veranstaltungsansicht wird nur für
öffentliche Kalender angeboten. Bei privaten oder gemischten Kalenderansichten
erscheint stattdessen **Kalender-Feeds**, weil die direkte Webcal-URL kein Token
enthält und externe Kalenderclients keine Admidio-Session senden können.

Der Benutzer, der den Feed anlegt, benötigt einen Loginname und muss gültiges
Mitglied mindestens einer Rolle der Organisation sein. Der Feed verwendet die
Sichtbarkeitsregeln dieses Benutzers für Veranstaltungen.

Mehrere Token pro Benutzer und Organisation sind möglich, zum Beispiel eines
für Thunderbird, eines für ICSx5 und eines für Apple Kalender.

## Thunderbird

1. In der Kalenderansicht **Neuer Kalender > Im Netzwerk** wählen.
2. Die Feed-URL aus dem Profil eintragen.
3. Den im Profil angezeigten Benutzernamen wählen und das Token als Passwort einfügen.
4. Den Kalender speichern. Thunderbird aktualisiert ihn gemäß den normalen
   Einstellungen für die Kalenderaktualisierung.

## [ICSx5](https://icsx5.bitfire.at) (Android)

1. In ICSx5 einen neuen Kalender hinzufügen und die Feed-URL einfügen.
2. Den Admidio-Loginnamen als Benutzernamen eintragen.
3. Das Token als Passwort eintragen.
4. Den Kalender abonnieren.

## Apple Kalender (iOS/iPadOS)

1. **Einstellungen > Kalender > Accounts > Account hinzufügen > Andere** öffnen.
   Bei neueren iOS-Versionen lautet der Pfad
   **Einstellungen > Apps > Kalender > Kalenderaccounts > Account hinzufügen > Andere**.
2. **Kalenderabo** wählen, nicht **CalDAV**.
3. Die Feed-URL aus dem Profil eintragen und auf **Weiter** tippen.
4. Wenn iOS nach Zugangsdaten fragt, den Admidio-Loginnamen als Benutzernamen
   und das Feed-Token als Passwort eintragen.
5. Den Kalender speichern.

Alternativ in der Kalender-App: **Kalender > Kalender hinzufügen >
Abonnement-Kalender hinzufügen**.

Das Abonnement ist schreibgeschützt und wird von iOS nach eigenem Zeitplan
aktualisiert. Falls iOS die URL ablehnt, diese zuerst in Safari öffnen und
prüfen, ob eine iCalendar-Datei zurückkommt.

## Serverkonfiguration für HTTP-Basic-Authentifizierung

Kalenderclients senden die Zugangsdaten im `Authorization`-Header. Einige
Serverkonfigurationen entfernen diesen Header, bevor PHP ihn erhält. In diesem
Fall antwortet der Feed mit HTTP 401, obwohl Benutzername und Token korrekt sind.

Das TLS-Zertifikat muss für den exakten Hostnamen der Feed-URL gültig sein.
iOS und andere Clients lehnen selbstsignierte Zertifikate oder Zertifikate für
einen anderen Hostnamen ab.

Apache mit PHP-FPM benötigt in der Regel:

```apache
CGIPassAuth On
```

Das kann im Virtual Host oder in einer `.htaccess`-Datei gesetzt werden, sofern
Overrides erlaubt sind. Falls `CGIPassAuth` nicht verfügbar ist, können diese
Apache-Alternativen helfen:

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

Wenn nginx als Reverse Proxy arbeitet, muss er den Header weiterreichen:

```nginx
proxy_set_header Authorization $http_authorization;
```

Für nginx mit FastCGI:

```nginx
fastcgi_param HTTP_AUTHORIZATION $http_authorization;
```

Der `Authorization`-Header enthält das Token und darf niemals in Access-Logs
oder Anwendungslogs geschrieben werden.

## Fehlerbehebung

- **HTTP 401:** Benutzername oder Token fehlen bzw. sind falsch, der Benutzer ist
  kein Mitglied der Organisation, oder PHP erhält den `Authorization`-Header nicht.
- **HTTP 403:** Der Feed wird über einfaches HTTP aufgerufen, das
  Veranstaltungsmodul ist deaktiviert, oder der iCal-Export ist deaktiviert.
- **Leerer Kalender:** Der Benutzer kann keine passenden Veranstaltungen sehen,
  der gewählte Kalender enthält keine Veranstaltungen, oder das Token ist an
  einen Kalender ohne Veranstaltungen gebunden.
- **Token verloren:** Das Token wird nur einmal angezeigt und kann nicht
  wiederhergestellt werden. Den alten Feed widerrufen und einen neuen anlegen.

## Sicherheitshinweise für Administratoren

- Geschützte Feeds erfordern HTTPS. Reine HTTP-Aufrufe werden abgelehnt.
- Es wird nur ein SHA-256-Hash des Tokens gespeichert. Das Klartext-Token wird
  einmal angezeigt.
- Tokens sind an eine Organisation und einen Benutzer gebunden.
- Ein Token funktioniert nicht mehr, wenn es widerrufen wird, der Benutzer
  deaktiviert wird, der Benutzer kein Mitglied der Organisation mehr ist oder
  das Ablaufdatum erreicht ist.
- Der Feed verwendet die normalen Sichtbarkeitsregeln des Benutzers. Er zeigt
  niemals Kalender oder Veranstaltungen, die der Benutzer nicht sehen darf.
- Der Zeitstempel der letzten Verwendung wird höchstens einmal pro Stunde
  aktualisiert.

## Abgrenzung zu `mode=subscribe`

`mode=subscribe` bleibt der öffentliche Export, der über das normale Modul und
die iCal-Einstellungen gesteuert wird. In der Veranstaltungsansicht wird er
deshalb nur für öffentliche Kalender angeboten. `mode=feed` ist ein separater,
tokenbasierter Endpunkt für Kalenderclients, funktioniert auch für private
Kalender und leitet niemals zur HTML-Anmeldung weiter.
