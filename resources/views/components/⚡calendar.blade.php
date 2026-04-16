<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\CalendarEvent;

new #[Layout('layouts.app')] class extends Component {
    public int $year;
    public int $month;
    public $events = [];

    // Modal
    public bool $showModal = false;
    public ?int $editingId = null;
    public string $eventTitle = '';
    public string $eventDescription = '';
    public string $eventColor = '#4f46e5';
    public string $eventDate = '';
    public string $eventEndDate = '';
    public bool $eventAllDay = true;

    public bool $isConnected = false;

    public function mount(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
        $this->isConnected = !empty(auth()->user()->google_token);
        $this->loadEvents();
    }

    public function loadEvents(): void
    {
        $start = \Carbon\Carbon::create($this->year, $this->month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();
        $this->events = CalendarEvent::where('user_id', auth()->id())
            ->whereBetween('starts_at', [$start, $end])
            ->orderBy('starts_at')
            ->get()
            ->toArray();
    }

    public function prevMonth(): void
    {
        $dt = \Carbon\Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year = $dt->year;
        $this->month = $dt->month;
        $this->loadEvents();
    }

    public function nextMonth(): void
    {
        $dt = \Carbon\Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year = $dt->year;
        $this->month = $dt->month;
        $this->loadEvents();
    }

    public function openNewEvent(string $date = ''): void
    {
        $this->editingId = null;
        $this->eventTitle = '';
        $this->eventDescription = '';
        $this->eventColor = '#4f46e5';
        $this->eventDate = $date;
        $this->eventEndDate = '';
        $this->eventAllDay = true;
        $this->showModal = true;
    }

    public function openEditEvent(int $id): void
    {
        $event = CalendarEvent::where('user_id', auth()->id())->findOrFail($id);
        $this->editingId = $id;
        $this->eventTitle = $event->title;
        $this->eventDescription = $event->description ?? '';
        $this->eventColor = $event->color;
        $this->eventDate = $event->starts_at->format('Y-m-d');
        $this->eventEndDate = $event->ends_at ? $event->ends_at->format('Y-m-d') : '';
        $this->eventAllDay = $event->all_day;
        $this->showModal = true;
    }

    public function saveEvent(): void
    {
        $this->validate(['eventTitle' => 'required|string|max:255', 'eventDate' => 'required|date']);
        $data = [
            'user_id' => auth()->id(),
            'title' => $this->eventTitle,
            'description' => $this->eventDescription ?: null,
            'color' => $this->eventColor,
            'starts_at' => $this->eventDate,
            'ends_at' => $this->eventEndDate ?: null,
            'all_day' => $this->eventAllDay,
        ];
        if ($this->editingId) {
            CalendarEvent::where('user_id', auth()->id())->findOrFail($this->editingId)->update($data);
        } else {
            CalendarEvent::create($data);
        }
        $this->showModal = false;
        $this->loadEvents();
    }

    public function deleteEvent(int $id): void
    {
        CalendarEvent::where('user_id', auth()->id())->findOrFail($id)->delete();
        $this->loadEvents();
    }

    public function getDaysInMonth(): array
    {
        $start = \Carbon\Carbon::create($this->year, $this->month, 1);
        $end = $start->copy()->endOfMonth();
        $days = [];
        // Pad with nulls to align to week start (Monday)
        $startDow = ($start->dayOfWeek + 6) % 7; // 0=Mon
        for ($i = 0; $i < $startDow; $i++) {
            $days[] = null;
        }
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days[] = $d->copy();
        }
        return $days;
    }

    public function getEventsForDay(string $dateStr): array
    {
        return array_values(array_filter($this->events, function($e) use ($dateStr) {
            $start = substr($e['starts_at'], 0, 10);
            $end = $e['ends_at'] ? substr($e['ends_at'], 0, 10) : $start;
            return $dateStr >= $start && $dateStr <= $end;
        }));
    }
};
?>
<div class="h-full flex flex-col bg-surface p-4 sm:p-6 gap-4">

    {{-- Header --}}
    <div class="flex items-center justify-between shrink-0 flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <button wire:click="prevMonth"
                class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-surface-container-high text-on-surface-variant transition">
                <span class="material-symbols-outlined">chevron_left</span>
            </button>
            <h1 class="text-xl font-bold text-on-background min-w-[180px] text-center">
                {{ \Carbon\Carbon::create($year, $month, 1)->format('F Y') }}
            </h1>
            <button wire:click="nextMonth"
                class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-surface-container-high text-on-surface-variant transition">
                <span class="material-symbols-outlined">chevron_right</span>
            </button>
            <button wire:click="openNewEvent('{{ now()->format('Y-m-d') }}')"
                class="text-xs px-3 py-1 rounded-full border border-outline-variant text-on-surface-variant hover:bg-surface-container-high ml-2">
                Today
            </button>
        </div>
        <div class="flex items-center gap-2">
            @if(!$isConnected)
            <a href="{{ route('google.calendar.redirect') }}"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-surface-container-high text-on-surface rounded-xl text-xs hover:bg-surface-container-highest">
                <span class="material-symbols-outlined text-sm">add_link</span> Connect Google
            </a>
            @else
            <span class="flex items-center gap-1 text-xs text-secondary px-3 py-1.5 bg-secondary-container/30 rounded-xl">
                <span class="material-symbols-outlined text-sm">check_circle</span> Google connected
            </span>
            @endif
            <button wire:click="openNewEvent('')"
                class="flex items-center gap-1.5 px-4 py-2 bg-primary text-on-primary rounded-xl text-sm font-medium hover:bg-primary/90 transition">
                <span class="material-symbols-outlined text-base">add</span> New Event
            </button>
        </div>
    </div>

    {{-- Calendar grid --}}
    <div class="flex-1 min-h-0 flex flex-col bg-surface-container rounded-2xl overflow-hidden">
        {{-- Day headers --}}
        <div class="grid grid-cols-7 border-b border-outline-variant/30 shrink-0">
            @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)
            <div class="py-2 text-center text-xs font-semibold text-on-surface-variant uppercase tracking-wide">{{ $day }}</div>
            @endforeach
        </div>

        {{-- Day cells --}}
        <div class="grid grid-cols-7 flex-1 overflow-y-auto">
            @php $days = $this->getDaysInMonth(); $today = now()->format('Y-m-d'); @endphp
            @foreach($days as $day)
            @if($day === null)
            <div class="border-b border-r border-outline-variant/20 min-h-[80px] bg-surface-container/50"></div>
            @else
            @php
                $dateStr = $day->format('Y-m-d');
                $dayEvents = $this->getEventsForDay($dateStr);
                $isToday = $dateStr === $today;
            @endphp
            <div class="border-b border-r border-outline-variant/20 min-h-[80px] p-1 flex flex-col cursor-pointer hover:bg-surface-container-high/50 transition group"
                wire:click="openNewEvent('{{ $dateStr }}')">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold {{ $isToday
                        ? 'w-6 h-6 bg-primary text-on-primary rounded-full flex items-center justify-center text-[11px]'
                        : 'text-on-surface ml-1' }}">
                        {{ $day->day }}
                    </span>
                    <span class="material-symbols-outlined text-xs text-on-surface-variant/0 group-hover:text-on-surface-variant/50 transition">add</span>
                </div>
                <div class="flex flex-col gap-0.5 overflow-hidden">
                    @foreach(array_slice($dayEvents, 0, 3) as $ev)
                    <button wire:click.stop="openEditEvent({{ $ev['id'] }})"
                        class="text-[10px] sm:text-xs text-left w-full truncate rounded px-1 py-0.5 font-medium text-white leading-tight"
                        style="background-color: {{ $ev['color'] }}">
                        {{ $ev['title'] }}
                    </button>
                    @endforeach
                    @if(count($dayEvents) > 3)
                    <span class="text-[10px] text-on-surface-variant px-1">+{{ count($dayEvents) - 3 }} more</span>
                    @endif
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </div>

    {{-- Event Modal --}}
    @if($showModal)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" wire:click.self="$set('showModal', false)">
        <div class="bg-surface-container-lowest rounded-2xl shadow-xl p-6 w-full max-w-md">
            <h2 class="text-lg font-bold text-on-background mb-4">{{ $editingId ? 'Edit Event' : 'New Event' }}</h2>
            <form wire:submit="saveEvent" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Title <span class="text-error">*</span></label>
                    <input wire:model="eventTitle" type="text" autofocus placeholder="Event title"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    @error('eventTitle')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea wire:model="eventDescription" rows="2"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="block text-sm font-medium mb-1">Start Date <span class="text-error">*</span></label>
                        <input wire:model="eventDate" type="date"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                        @error('eventDate')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium mb-1">End Date</label>
                        <input wire:model="eventEndDate" type="date"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Color</label>
                        <input wire:model="eventColor" type="color" class="h-10 w-14 rounded-xl border border-outline-variant cursor-pointer" />
                    </div>
                    <label class="flex items-center gap-2 text-sm mt-4 cursor-pointer">
                        <input wire:model="eventAllDay" type="checkbox" class="accent-primary"> All day
                    </label>
                </div>
                <div class="flex justify-between pt-2">
                    @if($editingId)
                    <button type="button" wire:click="deleteEvent({{ $editingId }})" wire:confirm="Delete this event?"
                        class="px-3 py-2 rounded-xl text-sm text-error hover:bg-error-container/30">Delete</button>
                    @else
                    <span></span>
                    @endif
                    <div class="flex gap-3">
                        <button type="button" wire:click="$set('showModal', false)"
                            class="px-4 py-2 rounded-xl text-sm text-on-surface hover:bg-surface-container-high">Cancel</button>
                        <button type="submit"
                            class="px-4 py-2 bg-primary text-on-primary rounded-xl text-sm font-medium hover:bg-primary/90">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
