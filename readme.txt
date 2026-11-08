=== IT-Kayali WooCommerce Gratisprodukt ===
Contributors: itkayali
Tags: woocommerce, gratisprodukt, gift, free gift, warenkorb
Requires PHP: 7.4
Stable tag: 1.1.0

Automatisch genau ein Gratisprodukt in WooCommerce hinzufügen – optional ab Mindestwarenwert.

== Funktionen ==

* Gratisprodukt bequem unter WooCommerce > Gratisprodukt auswählen
* Mindestwarenwert optional festlegen
* 0 oder leer = keine Mindestgrenze
* Geschenk wird automatisch genau 1x hinzugefügt
* Geschenkpreis wird im Warenkorb immer auf 0,00 gesetzt
* Geschenk kann nicht dauerhaft entfernt werden, solange der Warenkorb berechtigt ist
* Menge des Geschenks bleibt immer 1
* Geschenk verschwindet, wenn der Warenkorb leer ist oder der Mindestwarenwert unterschritten wird
* Produkt wird im Shop-Katalog ausgeblendet
* Manuelles Hinzufügen des Gratisprodukts wird blockiert
* Unterstützt klassischen WooCommerce-Warenkorb sowie Cart/Checkout Blocks
* Versandkosten zählen nicht zum Mindestwarenwert
* Reine Alowidat-Geschenkgutschein-Warenkörbe lösen keine Gratisprobe aus
* Alowidat-Gutscheinwerte zählen bei gemischten Warenkörben nicht zum Mindestwarenwert; normale Produkte behalten ihre Gratisprobe

== Installation ==

1. ZIP-Datei in WordPress unter Plugins > Installieren > Plugin hochladen hochladen.
2. Plugin aktivieren.
3. WooCommerce > Gratisprodukt öffnen.
4. Gewünschtes einfaches Produkt auswählen.
5. Mindestwarenwert eintragen. Für keine Mindestgrenze 0 eintragen.
6. Einstellungen speichern.
7. Falls zuvor ein Code-Snippet für dasselbe Gratisprodukt verwendet wurde, dieses deaktivieren.

== Hinweise ==

Das ausgewählte Gratisprodukt muss ein einfaches WooCommerce-Produkt sein.

Wenn der Lagerbestand des Gratisprodukts verfolgt werden soll, kann die WooCommerce-Lagerverwaltung am Produkt aktiviert bleiben. Soll kein Bestand reduziert werden, die Lagerverwaltung für dieses Produkt deaktivieren.

== Changelog ==

= 1.1.0 =
* Exklusive Gutscheinbestellungen erhalten keine Gratisprobe und damit keine durch die Probe ausgelösten Versandkosten.
* Bei gemischten Warenkörben zählen Gutscheine nicht zum Mindestwarenwert.
* Alle bisherigen Regeln für normale Produkte bleiben erhalten.

= 1.0.0 =
* Erste Version.
