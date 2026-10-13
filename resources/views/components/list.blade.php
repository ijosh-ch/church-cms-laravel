{{-- DESIGN.md §5. One row per record: a title and at most three more lines. Rows are x-list.item. --}}
<ul {{ $attributes->class('divide-y divide-line overflow-hidden rounded-card border border-line bg-surface') }}>
    {{ $slot }}
</ul>
