# Alowidat Gratisprodukt

WooCommerce-Plugin für automatische Gratisprodukte im Warenkorb.

## Aktuelle Version

**1.1.0**

## Funktionen

- Gratisprodukt unter **WooCommerce → Gratisprodukt** auswählen
- Optionaler Mindestwarenwert
- 0 oder leer = keine Mindestgrenze
- Gratisprodukt wird automatisch genau **1×** hinzugefügt
- Preis des Gratisprodukts wird im Warenkorb auf **0,00 €** gesetzt
- Gratisprodukt kann nicht dauerhaft entfernt werden, solange der Warenkorb berechtigt ist
- Menge bleibt immer 1
- Gratisprodukt verschwindet automatisch, wenn der Warenkorb leer ist oder die Mindestgrenze unterschritten wird
- Gratisprodukt wird im Shop-Katalog ausgeblendet
- Manuelles Hinzufügen des Gratisprodukts wird blockiert
- Unterstützung für klassischen WooCommerce-Warenkorb sowie Cart/Checkout Blocks
- Versandkosten zählen nicht zum Mindestwarenwert
- Reine Alowidat-Geschenkgutschein-Warenkörbe lösen keine Gratisprobe aus
- Gutscheinwerte zählen bei gemischten Warenkörben nicht zum Mindestwarenwert

## Installation / Update

Für WordPress bitte **nicht** die normale Funktion **Code → Download ZIP** verwenden.

Stattdessen unter **Releases** die Datei **itk-woocommerce-gratisprodukt.zip** herunterladen und in WordPress unter **Plugins → Installieren → Plugin hochladen** verwenden.

Die Release-ZIP enthält immer den korrekten Plugin-Ordner:

    itk-woocommerce-gratisprodukt/
    ├── itk-woocommerce-gratisprodukt.php
    ├── readme.txt
    └── uninstall.php

Dadurch kann das vorhandene Plugin sauber ersetzt bzw. aktualisiert werden, ohne vorher manuell zu entpacken oder neu zu zippen.

## Entwicklung und Releases

Die eigentlichen Plugin-Dateien liegen direkt im Repository. Bei jeder neuen funktionierenden Version werden:

1. Plugin-Code aktualisiert
2. Versionsnummer aktualisiert
3. readme.txt und CHANGELOG.md aktualisiert
4. Änderungen nach main übertragen
5. GitHub erstellt automatisch einen Release mit einer direkt installierbaren WordPress-ZIP

Die GitHub Action prüft außerdem, dass eine veröffentlichte Versionsnummer nicht versehentlich erneut für einen anderen Code-Stand verwendet wird.

## Anforderungen

- WordPress mit WooCommerce
- PHP 7.4 oder neuer
- WooCommerce 8.0 oder neuer

## Lizenz

Derzeit wurde keine separate Open-Source-Lizenz vergeben. Alle Rechte bleiben beim Repository-Inhaber, soweit nicht anders angegeben.
