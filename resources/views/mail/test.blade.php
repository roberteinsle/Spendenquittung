<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $stiftung ?: config('app.name') }}
</x-mail::header>
</x-slot:header>

# Der Mailversand funktioniert

Diese Nachricht wurde am {{ $zeitpunkt }} Uhr aus den Einstellungen heraus verschickt, um den SMTP-Zugang zu prüfen.

Kommt sie an, können auch Zuwendungsbestätigungen versendet werden.

<x-slot:footer>
<x-mail::footer>
{{ $stiftung ?: config('app.name') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
