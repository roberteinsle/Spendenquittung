<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zuwendungsbestätigung {{ $spende->bescheinigungsnummer }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #000;
            background: #fff;
            width: 210mm;
            min-height: 297mm;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 0;
            position: relative;
        }

        /* ─── Header ─────────────────────────────────────────── */
        .header {
            background-color: #f5c400; /* gelbes Band */
            width: 100%;
            padding: 12mm 15mm 8mm 15mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left .stiftung-name {
            font-size: 14pt;
            font-weight: bold;
            color: #333;
        }

        .header-left .stiftung-claim {
            font-size: 8pt;
            color: #555;
            margin-top: 2mm;
        }

        .header-right img.logo {
            max-height: 20mm;
            max-width: 40mm;
            object-fit: contain;
        }

        .header-right img.herzfigur {
            max-height: 18mm;
            max-width: 25mm;
            object-fit: contain;
            margin-left: 5mm;
        }

        /* ─── Adressblock Stiftung ───────────────────────────── */
        .absender-zeile {
            background-color: #fff;
            padding: 4mm 15mm 2mm 15mm;
            font-size: 7pt;
            color: #666;
            border-bottom: 1px solid #ddd;
        }

        /* ─── Inhalt ─────────────────────────────────────────── */
        .inhalt {
            padding: 8mm 15mm;
        }

        .bescheinigungsnummer-badge {
            float: right;
            font-size: 7pt;
            color: #888;
            margin-top: 2mm;
        }

        h1.titel {
            font-size: 11pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 6mm;
            color: #333;
        }

        h2.sub-titel {
            font-size: 9pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 6mm;
            color: #555;
        }

        /* Anschriftenblock Spender */
        .anschrift-block {
            margin-bottom: 6mm;
            padding: 4mm;
            background: #f9f9f9;
            border-left: 3px solid #f5c400;
        }

        .anschrift-block p {
            line-height: 1.5;
        }

        /* Bestätigungstext */
        .bestaetigung-text {
            margin-bottom: 5mm;
            line-height: 1.6;
        }

        .bestaetigung-text strong {
            font-weight: bold;
        }

        /* Betrag-Block */
        .betrag-block {
            margin: 4mm 0;
            padding: 4mm 6mm;
            border: 1px solid #ddd;
            background: #fafafa;
        }

        .betrag-block table {
            width: 100%;
        }

        .betrag-block td {
            padding: 1mm 2mm;
            vertical-align: top;
        }

        .betrag-block .label {
            font-weight: bold;
            width: 60mm;
        }

        /* Ankreuzfelder */
        .ankreuzfelder {
            margin: 5mm 0;
        }

        .ankreuzfeld-zeile {
            display: flex;
            align-items: flex-start;
            margin-bottom: 2mm;
            line-height: 1.5;
        }

        .checkbox-box {
            display: inline-block;
            width: 4mm;
            height: 4mm;
            border: 1px solid #000;
            margin-right: 3mm;
            flex-shrink: 0;
            margin-top: 0.5mm;
            text-align: center;
            font-size: 8pt;
            line-height: 4mm;
        }

        .checkbox-box.checked {
            background-color: #000;
            color: #fff;
        }

        /* Förderungstext */
        .foerderungs-text {
            margin: 4mm 0;
            line-height: 1.6;
        }

        /* Juristischer Text */
        .juristischer-text {
            margin: 5mm 0;
            font-size: 9pt;
            line-height: 1.5;
            border-top: 1px solid #eee;
            padding-top: 4mm;
        }

        /* Platzhalter-Banner (sichtbar bei ungeklärter Rechtsform) */
        .placeholder-banner {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            padding: 3mm 5mm;
            margin: 4mm 0;
            font-size: 9pt;
            font-weight: bold;
            color: #856404;
        }

        /* Unterschrift */
        .unterschrift-block {
            margin-top: 10mm;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .unterschrift-links {
            width: 80mm;
        }

        .unterschrift-linie {
            border-top: 1px solid #000;
            padding-top: 2mm;
            font-size: 8pt;
        }

        .unterschrift-img {
            max-height: 15mm;
            max-width: 60mm;
            display: block;
            margin-bottom: 1mm;
        }

        .datum-ort {
            text-align: right;
            font-size: 9pt;
        }

        /* Haftungshinweis */
        .disclaimer {
            margin-top: 8mm;
            font-size: 7pt;
            color: #555;
            line-height: 1.4;
            border-top: 1px solid #ccc;
            padding-top: 4mm;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- ─── Header mit Logo und gelbem Band ─────────────────────── --}}
    <div class="header">
        <div class="header-left">
            <div class="stiftung-name">{{ $settings['stiftung_name'] }}</div>
            <div class="stiftung-claim">wir haben Sonne im Herzen</div>
        </div>
        <div class="header-right" style="display:flex; align-items:center;">
            @if($settings['logo_pfad_data_uri'])
                <img class="logo" src="{{ $settings['logo_pfad_data_uri'] }}" alt="Logo">
            @endif
            @if($settings['herzfigur_pfad_data_uri'])
                <img class="herzfigur" src="{{ $settings['herzfigur_pfad_data_uri'] }}" alt="">
            @endif
        </div>
    </div>

    {{-- ─── Absenderzeile ──────────────────────────────────────────── --}}
    <div class="absender-zeile">
        {{ $settings['stiftung_name'] }} · {{ $settings['stiftung_strasse'] }} · {{ $settings['stiftung_plz_ort'] }}
        @if($settings['stiftung_telefon']) · Tel. {{ $settings['stiftung_telefon'] }} @endif
        @if($settings['stiftung_email']) · {{ $settings['stiftung_email'] }} @endif
        @if($settings['stiftung_web']) · {{ $settings['stiftung_web'] }} @endif
    </div>

    {{-- ─── Inhalt ──────────────────────────────────────────────────── --}}
    <div class="inhalt">

        <div class="bescheinigungsnummer-badge">
            Bescheinigungsnr. {{ $spende->bescheinigungsnummer }}
        </div>

        <h1 class="titel">Zuwendungsbestätigung</h1>
        <h2 class="sub-titel">
            Bestätigung über Geldzuwendungen im Sinne des § 10b EStG
        </h2>

        {{-- Anschriftenblock --}}
        <div class="anschrift-block">
            <p><strong>Zuwendender:</strong></p>
            @foreach(explode("\n", $spender->adressblock) as $zeile)
                <p>{{ $zeile }}</p>
            @endforeach
        </div>

        {{-- Betrag-Block --}}
        <div class="betrag-block">
            <table>
                <tr>
                    <td class="label">Betrag der Zuwendung:</td>
                    <td><strong>{{ $spende->betrag_formatiert }}</strong></td>
                    <td style="text-align:right; font-size:9pt; color:#555;">
                        ({{ $spende->betrag_in_worten }})
                    </td>
                </tr>
                <tr>
                    <td class="label">Tag der Zuwendung:</td>
                    <td>{{ $spende->spendendatum->format('d.m.Y') }}</td>
                    <td></td>
                </tr>
            </table>
        </div>

        {{-- Ankreuzfelder --}}
        <div class="ankreuzfelder">
            <p style="margin-bottom:2mm; font-weight:bold;">Die Zuwendung</p>

            <div class="ankreuzfeld-zeile">
                <span class="checkbox-box {{ $spende->ankreuzfeld?->value === 'vermoegenstock' ? 'checked' : '' }}">
                    @if($spende->ankreuzfeld?->value === 'vermoegenstock') ✓ @endif
                </span>
                <span>wurde in den Vermögensstock eingebracht</span>
            </div>

            <div class="ankreuzfeld-zeile">
                <span class="checkbox-box {{ $spende->ankreuzfeld?->value === 'unmittelbar' ? 'checked' : '' }}">
                    @if($spende->ankreuzfeld?->value === 'unmittelbar') ✓ @endif
                </span>
                <span>wird von uns unmittelbar für den angegebenen Zweck verwendet</span>
            </div>
        </div>

        {{-- Förderungstext --}}
        <div class="foerderungs-text">
            Es wird bestätigt, dass die Zuwendung nur zur Förderung
            <strong>{{ $zweck->text }}</strong>
            @if($spende->anlass)
                ({{ $spende->anlass }})
            @endif
            verwendet wird.
        </div>

        {{-- Juristischer Text – je nach Rechtsform ──────────────── --}}
        <div class="juristischer-text">

            @if($settings['rechtliche_form'] === 'oeffentlich_rechtlich')
                {{-- Variante: Körperschaft des öffentlichen Rechts --}}
                <p>
                    Wir sind eine nach § 5 Abs. 1 Nr. 9 KStG steuerbefreite
                    Körperschaft des öffentlichen Rechts.
                </p>
                <p style="margin-top:3mm;">
                    Es handelt sich nicht um Mitgliedsbeiträge, sonstige
                    Mitgliedsumlagen oder Aufnahmegebühren.
                </p>

            @elseif($settings['rechtliche_form'] === 'privatrechtlich')
                {{-- Variante: Gemeinnützige Stiftung privaten Rechts --}}
                <p>
                    Wir sind wegen Förderung
                    {{ $zweck->text }}
                    nach dem Freistellungsbescheid bzw. nach der Anlage zum
                    Körperschaftsteuerbescheid des
                    Finanzamts {{ $settings['stiftung_finanzamt'] }}
                    StNr. {{ $settings['stiftung_steuernummer'] }}
                    vom {{ $settings['stiftung_freistellung_datum'] }}
                    für den Veranlagungszeitraum
                    {{ $settings['stiftung_veranlagungszeitraum'] }}
                    nach § 5 Abs. 1 Nr. 9 des Körperschaftsteuergesetzes
                    von der Körperschaftsteuer und nach § 3 Nr. 6 des
                    Gewerbesteuergesetzes von der Gewerbesteuer befreit.
                </p>
                <p style="margin-top:3mm;">
                    Es handelt sich nicht um Mitgliedsbeiträge, sonstige
                    Mitgliedsumlagen oder Aufnahmegebühren.
                </p>

            @else
                {{-- Platzhalter – Rechtsform noch nicht geklärt --}}
                <div class="placeholder-banner">
                    ⚠️ HINWEIS: Rechtsform der Stiftung noch nicht geklärt!
                    Bitte unter Einstellungen → Rechtliche Form eintragen
                    (nach Rücksprache mit dem Steuerberater).
                    Dieser Text muss vor dem ersten echten Druck angepasst werden.
                </div>
                <p style="color: #999; font-style: italic;">
                    [Juristischer Text folgt nach Klärung der Rechtsform]
                </p>
            @endif
        </div>

        {{-- Spendenkonto --}}
        <div style="margin-top:5mm; font-size:9pt;">
            <strong>Spendenkonto:</strong>
            {{ $settings['spendenkonto_kontoempfaenger'] }} –
            IBAN {{ $settings['spendenkonto_iban'] }} –
            BIC {{ $settings['spendenkonto_bic'] }} –
            {{ $settings['spendenkonto_bank'] }}
        </div>

        {{-- Unterschrift --}}
        <div class="unterschrift-block">
            <div class="unterschrift-links">
                @if($settings['unterschrift_pfad_data_uri'])
                    <img class="unterschrift-img"
                         src="{{ $settings['unterschrift_pfad_data_uri'] }}"
                         alt="Unterschrift">
                @else
                    <div style="height:15mm; border-bottom:1px solid #ccc; margin-bottom:1mm;"></div>
                @endif
                <div class="unterschrift-linie">
                    {{ $settings['unterzeichner_name'] }},
                    {{ $settings['unterzeichner_titel'] }}
                </div>
            </div>
            <div class="datum-ort">
                {{ $settings['ausstellungsort'] }},
                den {{ $spende->ausstellungsdatum->format('d.m.Y') }}
            </div>
        </div>

        {{-- Haftungshinweis --}}
        <div class="disclaimer">
            <strong>Hinweis:</strong>
            Wer vorsätzlich oder grob fahrlässig eine unrichtige Zuwendungsbestätigung
            ausstellt oder wer veranlasst, dass Zuwendungen nicht zu den in der
            Bestätigung angegebenen steuerbegünstigten Zwecken verwendet werden,
            haftet für die entgangene Steuer (§ 10b Abs. 4 EStG, § 9 Abs. 3 KStG,
            § 9 Nr. 5 GewStG). Diese Bestätigung wird nicht als Nachweis für die
            steuerliche Berücksichtigung der Zuwendung anerkannt, wenn das Datum
            des Freistellungsbescheides länger als 5 Jahre, das Datum der
            Anlage zum Körperschaftsteuerbescheid länger als 3 Jahre
            seit Ausstellung der Bestätigung zurückliegt (§ 63 Abs. 5 AO).
        </div>

    </div>
</div>
</body>
</html>
