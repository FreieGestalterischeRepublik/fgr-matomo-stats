# FGR Matomo Stats

Zeigt die Matomo-Statistiken dieser Seite direkt im WordPress-Backend an.

Funktioniert ausschließlich mit dem Matomo der Freien Gestalterischen Republik – die Seite muss dort als Property hinterlegt sein, sonst deaktiviert sich das Plugin nach der Aktivierung automatisch wieder.

## Funktionen

- Besucher, Seitenaufrufe, Absprungrate, Ø Besuchsdauer
- Umschaltbarer Zeitraum (Heute, Gestern, 7/30 Tage, Monat, Jahr)
- Top-Seiten und Traffic-Quellen
- Dashboard-Widget
- Zugriff für Administratoren + frei wählbare Benutzer

## Technischer Aufbau

Kein Matomo-Token liegt je auf der WordPress-Seite. Ein zentraler Dienst fragt Matomo einmal täglich für alle FGR-Kundenseiten ab und stellt die Zahlen als JSON-Datei pro Domain bereit; dieses Plugin ruft nur die eigene Datei ab.
