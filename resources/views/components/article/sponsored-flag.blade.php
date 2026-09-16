@props(['article', 'tone' => 'light'])
{{-- The disclosure, wherever an article appears.

     It was on the article page and on none of the six card weights, so a
     sponsored piece sat in a homepage grid or a category list with nothing to
     distinguish it until a reader clicked through. The advertise page promises
     the label appears "in every place it appears on the site" — this is what
     makes that true rather than a claim.

     It takes no condition from the caller on purpose: a card cannot choose not
     to disclose. --}}
@if ($article->is_sponsored)
    <span @class([
        'inline-flex shrink-0 items-center rounded px-1.5 py-0.5 text-[0.65rem] font-semibold',
        'bg-gold/25 text-gold-ink' => $tone === 'light',
        'bg-gold/30 text-cream' => $tone === 'dark',
    ])>
        محتوى مدفوع
    </span>
@endif
