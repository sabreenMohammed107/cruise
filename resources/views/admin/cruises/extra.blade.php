{{-- Under the cruise form fields: the route builder, then the day-by-day itinerary (CruiseResource::formExtra). --}}
@include('admin.cruises.route', ['record' => $record])

<div class="mt-8 pt-6 border-t border-line">@include('admin.cruises.itinerary', ['record' => $record])</div>
