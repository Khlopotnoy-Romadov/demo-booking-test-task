<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        return Booking::where('user_id', $request->user()->id)
            ->orderByRaw('starts_at DESC')
            ->paginate(20);
    }

    /**
     * Create a booking for the authenticated user.
     *
     * The request validation checks the room, the working hours of the office the
     * room belongs to, the duration and the 15 minute step. The conflict check is
     * repeated inside a transaction under a per-room advisory lock, so two
     * simultaneous requests for the same slot cannot both succeed.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $room = $request->room();
        [$startsAt, $endsAt] = $request->validatedInterval();

        $booking = DB::transaction(function () use ($request, $room, $startsAt, $endsAt): Booking {
            DB::statement('select pg_advisory_xact_lock(?::bigint)', [$room->id]);

            $isTaken = Booking::query()
                ->overlapping($room->id, $startsAt, $endsAt)
                ->exists();

            if ($isTaken) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Комната занята: на выбранное время уже есть бронь.',
                ]);
            }

            $booking = new Booking([
                'room_id' => $room->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'title' => (string) $request->string('title'),
            ]);

            $booking->user_id = $request->user()->id;
            $booking->save();

            return $booking;
        });

        Log::channel('bookings')->info('Booking created', [
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'user_id' => $booking->user_id,
            'starts_at' => $booking->starts_at->toIso8601String(),
            'ends_at' => $booking->ends_at->toIso8601String(),
        ]);

        return response()->json($booking, 201);
    }

    /**
     * Cancel a booking. Only its author may do it.
     */
    public function destroy(Booking $booking): Response
    {
        $this->authorize('delete', $booking);

        $booking->delete();

        return response()->noContent();
    }

    public function today(Room $room)
    {
        return $room->bookings()
            ->whereDate('starts_at', today())
            ->get()
            ->map(fn ($b) => [
                'time' => $b->starts_at . ' – ' . $b->ends_at,
                'author' => $b->user->name,
            ]);
    }
}
