@if (count($languages) > 1)
<div class="top-right links">
    @foreach($languages as $language => $link)
        <a href="{{ $link }}">{{ $language }}</a>
    @endforeach
</div>
@endif
