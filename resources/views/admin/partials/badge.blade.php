@php
  [$class, $text] = \App\Support\Format::badge($status);
@endphp
<span class="tag {{ $class }}">{{ $text }}</span>
