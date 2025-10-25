@if (session('inactive_message'))
    <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ session('inactive_message') }}
    </div>
@endif
