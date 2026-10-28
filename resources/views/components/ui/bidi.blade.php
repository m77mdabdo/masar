@props(['text'])
{{--
    Text that may be in another language than the page: editor-entered values
    (market labels, written in the default edition) and entity names that fell
    back to Arabic. Arabic-script text on a page in another script is wrapped
    in <bdi lang="ar" dir="rtl"> so a screen reader voices it as Arabic and the
    line breaks it as Arabic. Anything else is printed as is. Escaped always.
--}}
@php
    $script = \App\Support\Locales::script(app()->getLocale());
    $foreign = ! in_array($script, ['arabic', 'nastaliq'], true) && preg_match('/\p{Arabic}/u', (string) $text);
@endphp
@if ($foreign)<bdi lang="ar" dir="rtl">{{ $text }}</bdi>@else{{ $text }}@endif
