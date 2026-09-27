{{--
    Uses the mail layout directly rather than <x-mail::message>, because that
    component hardcodes the app name in the header and an English
    "All rights reserved." in the footer. Donor e-mails carry the
    organisation's name instead.
--}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="$settings['stiftung_url']">
{{ $settings['stiftung_name'] }}
</x-mail::header>
</x-slot:header>

{{ $briefanrede }},

{{ $text }}

Mit freundlichen Grüßen<br>
@if($settings['unterzeichner_name'])
{{ $settings['unterzeichner_name'] }}@if($settings['unterzeichner_titel']), {{ $settings['unterzeichner_titel'] }}@endif<br>
@endif
{{ $settings['stiftung_name'] }}

<x-slot:footer>
<x-mail::footer>
{{ $settings['stiftung_name'] }}@if($settings['stiftung_web']) · {{ $settings['stiftung_web'] }}@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
